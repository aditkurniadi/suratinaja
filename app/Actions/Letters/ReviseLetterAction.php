<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\LetterRevision;
use App\Models\User;
use App\Services\Documents\DocxRenderer;
use App\Services\Numbering\NumberAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviseLetterAction
{
    public function __construct(
        private readonly NumberAllocator $allocator,
        private readonly DocxRenderer $renderer,
    ) {}

    /**
     * Revises a numbered letter (DEC-005, FR-07):
     * - Requires non-empty revision_reason (else 422 REVISION_REASON_REQUIRED).
     * - If use_new_number=true: voids current active number to `hangus` (terminal) and allocates
     *   a new sequential active number with `revision_of` pointing to the old number.
     * - If use_new_number=false: keeps current active number and records the revision.
     *
     * @param  array<string, mixed>  $payload
     * @return array{letter: Letter, old_number: string, old_number_status: string, new_number: string, revision: LetterRevision}
     *
     * @throws DomainException
     */
    public function execute(Letter $letter, User $actor, array $payload): array
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan merevisi surat.');
        }

        if ($letter->status === LetterStatus::Dibatalkan) {
            throw new DomainException('LETTER_CANCELLED', 'Surat yang telah dibatalkan tidak dapat direvisi.', 422);
        }

        $reason = trim((string) ($payload['revision_reason'] ?? ''));
        if ($reason === '') {
            throw DomainException::revisionReasonRequired();
        }

        $useNewNumber = (bool) ($payload['use_new_number'] ?? false);
        $incomingVars = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
        $newPerihal = isset($payload['perihal']) && trim((string) $payload['perihal']) !== ''
            ? trim((string) $payload['perihal'])
            : null;

        $result = DB::transaction(function () use ($letter, $workspace, $actor, $reason, $useNewNumber, $incomingVars, $newPerihal) {
            /** @var Letter $locked */
            $locked = Letter::withoutGlobalScopes()->lockForUpdate()->findOrFail($letter->id);
            $currentNumber = $locked->activeNumber()->first() ?? $locked->latestNumber()->first();

            if (! $currentNumber) {
                throw new DomainException('NUMBER_MISSING', 'Surat tidak memiliki nomor aktif untuk direvisi.', 422);
            }

            $oldFormatted = $currentNumber->formatted_number;
            $statusBefore = $locked->status->value;

            if ($useNewNumber) {
                $currentNumber->forceFill([
                    'status' => NumberStatus::Hangus,
                    'revision_reason' => $reason,
                    'voided_at' => now(),
                ])->save();

                $newNumberRow = $this->allocator->allocateForLetter(
                    $locked,
                    $workspace,
                    $locked->letter_date ?? now(),
                    $currentNumber->id,
                    $reason,
                );
                $oldStatus = NumberStatus::Hangus->value;
                $newFormatted = $newNumberRow->formatted_number;
            } else {
                $currentNumber->forceFill([
                    'revision_reason' => $reason,
                ])->save();
                $newNumberRow = $currentNumber;
                $oldStatus = NumberStatus::Aktif->value;
                $newFormatted = $currentNumber->formatted_number;
            }

            $mergedVars = array_replace($locked->field_values ?? [], $incomingVars);
            $nextVersion = ((int) $locked->version) + 1;

            $afterRevisionStatus = $locked->status === LetterStatus::Selesai
                ? (LetterStatus::tryFrom((string) config('suratinaja.letters.status_after_revision', 'draft')) ?? LetterStatus::Draft)
                : $locked->status;

            if ($locked->preview_pdf_path) {
                Storage::disk('local')->delete($locked->preview_pdf_path);
            }

            $locked->fill([
                'perihal' => $newPerihal ?? $locked->perihal,
                'field_values' => $mergedVars,
                'version' => $nextVersion,
                'status' => $afterRevisionStatus,
                'preview_pdf_path' => null,
            ])->save();

            $revision = LetterRevision::withoutGlobalScopes()->create([
                'letter_id' => $locked->id,
                'workspace_id' => $workspace->id,
                'version' => $nextVersion,
                'mode' => $useNewNumber ? 'new_number' : 'keep_number',
                'old_number_id' => $currentNumber->id,
                'new_number_id' => $newNumberRow->id,
                'reason' => $reason,
                'changes' => $incomingVars,
                'status_before' => $statusBefore,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);

            LetterActivity::withoutGlobalScopes()->create([
                'letter_id' => $locked->id,
                'workspace_id' => $workspace->id,
                'actor_id' => $actor->id,
                'action' => 'revised',
                'payload' => [
                    'version' => $nextVersion,
                    'mode' => $useNewNumber ? 'new_number' : 'keep_number',
                    'old_number' => $oldFormatted,
                    'new_number' => $newFormatted,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);

            return [
                'letter' => $locked->fresh(['activeNumber', 'template', 'workspace']),
                'old_number' => $oldFormatted,
                'old_number_status' => $oldStatus,
                'new_number' => $newFormatted,
                'revision' => $revision,
            ];
        });

        $docxPath = $this->renderer->renderSingle($result['letter']);
        $result['letter']->forceFill(['generated_file_path' => $docxPath])->saveQuietly();

        return $result;
    }
}
