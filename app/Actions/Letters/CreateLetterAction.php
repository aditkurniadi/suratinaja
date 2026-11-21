<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\DocxRenderer;
use App\Services\Numbering\NumberAllocator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateLetterAction
{
    public function __construct(
        private readonly NumberAllocator $allocator,
        private readonly DocxRenderer $renderer,
    ) {}

    /**
     * Creates a single draft letter, allocates its active number, and renders the .docx file.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException
     */
    public function execute(Workspace $workspace, User $actor, array $payload): Letter
    {
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan membuat surat.');
        }

        $isManual = (bool) ($payload['is_manual_number'] ?? false) || empty($payload['template_id']);
        $template = null;

        if (! $isManual) {
            $templateId = (int) ($payload['template_id'] ?? 0);
            /** @var LetterTemplate|null $template */
            $template = LetterTemplate::withoutGlobalScopes()->find($templateId);
            if (! $template || ! $template->isAccessibleBy($workspace)) {
                throw DomainException::templateInvalid('Template surat tidak ditemukan atau tidak tersedia untuk workspace ini.');
            }
        }

        $perihal = trim((string) ($payload['perihal'] ?? ''));
        if ($perihal === '') {
            throw DomainException::templateInvalid('Perihal surat wajib diisi.', ['perihal' => ['Perihal wajib diisi.']]);
        }

        $letterDate = Carbon::parse($payload['letter_date'] ?? now());
        $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];

        if ($template) {
            $this->validateRequiredVariables($template->variables_schema ?? [], $variables);
        }

        $linkDrive = trim((string) ($payload['link_drive'] ?? ''));

        $letter = DB::transaction(function () use ($workspace, $actor, $template, $perihal, $letterDate, $variables, $linkDrive, $isManual, $payload) {
            $letter = Letter::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'template_id' => $template?->id,
                'created_by' => $actor->id,
                'duplicated_from_id' => $payload['duplicated_from_id'] ?? null,
                'surat_masuk_id' => ! empty($payload['surat_masuk_id']) ? (int) $payload['surat_masuk_id'] : null,
                'perihal' => $perihal,
                'letter_date' => $letterDate->toDateString(),
                'tanggal_kegiatan' => ! empty($payload['tanggal_kegiatan']) ? Carbon::parse($payload['tanggal_kegiatan'])->toDateString() : null,
                'status' => LetterStatus::Draft,
                'field_values' => $variables,
                'link_drive' => $linkDrive ?: null,
                'is_manual_number' => $isManual,
                'is_manual' => (bool) ($payload['is_manual'] ?? $isManual),
                'keterangan' => $payload['keterangan'] ?? null,
                'version' => 1,
            ]);

            $number = $this->allocator->allocateForLetter($letter, $workspace, $letterDate);

            LetterActivity::withoutGlobalScopes()->create([
                'letter_id' => $letter->id,
                'workspace_id' => $workspace->id,
                'actor_id' => $actor->id,
                'action' => isset($payload['duplicated_from_id']) ? 'duplicated' : ($isManual ? 'number_allocated' : 'created'),
                'payload' => [
                    'number' => $number->formatted_number,
                    'is_manual_number' => $isManual,
                    'duplicated_from_id' => $payload['duplicated_from_id'] ?? null,
                ],
                'created_at' => now(),
            ]);

            return $letter->fresh(['activeNumber', 'template', 'workspace']);
        });

        if ($template) {
            $docxPath = $this->renderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        return $letter;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $variables
     */
    public function validateRequiredVariables(array $schema, array $variables): void
    {
        $missing = [];
        foreach ($schema as $field) {
            $type = $field['type'] ?? 'text';
            if ($type === 'system' || $type === 'repeater') {
                continue;
            }
            $required = (bool) ($field['required'] ?? true);
            $name = (string) ($field['name'] ?? '');
            if ($required && $name !== '') {
                $val = $variables[$name] ?? null;
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    $missing[$name] = ["Variabel \${{$name}} wajib diisi."];
                }
            }
        }

        if (! empty($missing)) {
            throw DomainException::templateInvalid(
                'Variabel wajib pada template belum lengkap.',
                $missing
            );
        }
    }
}
