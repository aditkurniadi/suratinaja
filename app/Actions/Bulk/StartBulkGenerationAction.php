<?php

namespace App\Actions\Bulk;

use App\Enums\BulkJobStatus;
use App\Exceptions\DomainException;
use App\Jobs\GenerateBulkLetterJob;
use App\Models\BulkJob;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Str;

class StartBulkGenerationAction
{
    public function __construct(
        private readonly ImportRecipientsAction $importer,
    ) {}

    /**
     * Validates a bulk generation request, stores the BulkJob, and dispatches GenerateBulkLetterJob.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DomainException
     */
    public function execute(Workspace $workspace, User $actor, array $payload): BulkJob
    {
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan menjalankan bulk generation.');
        }

        $templateId = (int) ($payload['template_id'] ?? 0);
        /** @var LetterTemplate|null $template */
        $template = LetterTemplate::withoutGlobalScopes()->find($templateId);
        if (! $template || ! $template->isAccessibleBy($workspace)) {
            throw DomainException::bulkJobFailed('Template surat tidak ditemukan atau tidak tersedia untuk workspace ini.');
        }

        $perihal = trim((string) ($payload['perihal'] ?? ''));
        if ($perihal === '') {
            throw DomainException::bulkJobFailed('Perihal surat massal wajib diisi.');
        }

        $letterDate = Carbon::parse($payload['letter_date'] ?? now());
        $sharedVariables = is_array($payload['shared_variables'] ?? null) ? $payload['shared_variables'] : [];

        $recipientsSpec = $payload['recipients'] ?? null;
        if (! is_array($recipientsSpec)) {
            throw DomainException::bulkJobFailed('Format data penerima (`recipients`) tidak valid.');
        }

        $mode = $recipientsSpec['mode'] ?? 'manual';
        $validRows = [];
        $failedRows = [];

        if ($mode === 'manual') {
            $items = $recipientsSpec['items'] ?? null;
            if (! is_array($items) || empty($items)) {
                throw DomainException::bulkJobFailed('Daftar penerima manual (`recipients.items`) tidak boleh kosong.');
            }
            $parsed = $this->importer->fromManualItems($items);
            $validRows = $parsed['valid'];
            $failedRows = $parsed['failed'];
        } elseif ($mode === 'upload') {
            $filePath = $recipientsSpec['file_path'] ?? null;
            if (! is_string($filePath) || $filePath === '') {
                throw DomainException::bulkJobFailed('File penerima (`file_path`) belum diunggah.');
            }
            $parsed = $this->importer->fromFile($filePath);
            $validRows = $parsed['valid'];
            $failedRows = $parsed['failed'];
        } else {
            throw DomainException::bulkJobFailed("Mode penerima '{$mode}' tidak didukung. Gunakan 'manual' atau 'upload'.");
        }

        $maxRecipients = (int) config('suratinaja.bulk.max_recipients', 200);
        if (count($validRows) > $maxRecipients) {
            throw DomainException::bulkJobFailed("Jumlah penerima melebihi batas maksimum ({$maxRecipients} penerima per batch).");
        }

        $jobId = 'bulk_'.strtolower((string) Str::ulid());

        $bulkJob = BulkJob::withoutGlobalScopes()->create([
            'id' => $jobId,
            'workspace_id' => $workspace->id,
            'template_id' => $template->id,
            'created_by' => $actor->id,
            'perihal' => $perihal,
            'letter_date' => $letterDate->toDateString(),
            'shared_variables' => $sharedVariables,
            'recipients' => $validRows,
            'failed_rows' => $failedRows,
            'status' => BulkJobStatus::Queued,
            'recipient_count' => count($validRows),
            'processed_count' => 0,
        ]);

        GenerateBulkLetterJob::dispatch($bulkJob->id);

        return $bulkJob->fresh();
    }
}
