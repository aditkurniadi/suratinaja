<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\User;
use App\Services\Documents\DocxRenderer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class UpdateDraftAction
{
    public function __construct(
        private readonly CreateLetterAction $creator,
        private readonly DocxRenderer $renderer,
    ) {}

    /**
     * Freely edits a letter while still in `draft` status (no revision reason required,
     * active number is preserved, change is recorded in letter_activities).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException
     */
    public function execute(Letter $letter, User $actor, array $payload): Letter
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan mengedit surat.');
        }

        if ($letter->status !== LetterStatus::Draft) {
            throw new DomainException(
                'DRAFT_ONLY_EDIT',
                'Surat yang sudah diajukan TTD atau Selesai hanya dapat diubah melalui alur Revisi (wajib alasan).',
                422
            );
        }

        $isManual = (bool) ($letter->is_manual || $letter->is_manual_number || ! $letter->template_id);
        $template = $letter->template()->withoutGlobalScopes()->first();
        if (! $isManual && ! $template) {
            throw DomainException::templateInvalid('Template surat tidak ditemukan.');
        }

        $perihal = isset($payload['perihal']) ? trim((string) $payload['perihal']) : $letter->perihal;
        if ($perihal === '') {
            throw DomainException::templateInvalid('Perihal surat wajib diisi.');
        }

        $letterDate = isset($payload['letter_date'])
            ? Carbon::parse($payload['letter_date'])
            : $letter->letter_date;

        $variables = is_array($payload['variables'] ?? null)
            ? array_replace($letter->field_values ?? [], $payload['variables'])
            : ($letter->field_values ?? []);

        if ($template) {
            $this->creator->validateRequiredVariables($template->variables_schema ?? [], $variables);
        }

        $before = [
            'perihal' => $letter->perihal,
            'letter_date' => $letter->letter_date?->toDateString(),
            'field_values' => $letter->field_values,
        ];

        if (! $isManual && $letter->preview_pdf_path) {
            Storage::disk('local')->delete($letter->preview_pdf_path);
        }

        $fillData = [
            'perihal' => $perihal,
            'letter_date' => $letterDate->toDateString(),
            'field_values' => $variables,
        ];

        if (! $isManual) {
            $fillData['preview_pdf_path'] = null;
        }

        if (array_key_exists('keterangan', $payload)) {
            $fillData['keterangan'] = $payload['keterangan'] ?: null;
        }

        if (array_key_exists('link_drive', $payload)) {
            $fillData['link_drive'] = $payload['link_drive'] ?: null;
        }

        if (array_key_exists('tanggal_kegiatan', $payload)) {
            $fillData['tanggal_kegiatan'] = ! empty($payload['tanggal_kegiatan']) ? Carbon::parse($payload['tanggal_kegiatan'])->toDateString() : null;
        }

        if (array_key_exists('surat_masuk_id', $payload)) {
            $fillData['surat_masuk_id'] = ! empty($payload['surat_masuk_id']) ? (int) $payload['surat_masuk_id'] : null;
        }

        $letter->fill($fillData)->save();

        if ($template) {
            $docxPath = $this->renderer->renderSingle($letter->fresh(['activeNumber', 'template', 'workspace']));
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        LetterActivity::withoutGlobalScopes()->create([
            'letter_id' => $letter->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $actor->id,
            'action' => 'edited',
            'payload' => [
                'before_perihal' => $before['perihal'],
                'after_perihal' => $perihal,
                'updated_keys' => array_keys(array_diff_assoc(
                    array_map('json_encode', $variables),
                    array_map('json_encode', $before['field_values'] ?? [])
                )),
            ],
            'created_at' => now(),
        ]);

        return $letter->fresh(['activeNumber', 'template']);
    }
}
