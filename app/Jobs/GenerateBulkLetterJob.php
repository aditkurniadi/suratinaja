<?php

namespace App\Jobs;

use App\Enums\BulkJobStatus;
use App\Enums\LetterStatus;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Notifications\BulkGenerationCompletedNotification;
use App\Services\Documents\DocxRenderer;
use App\Services\Numbering\NumberAllocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateBulkLetterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly string $bulkJobId,
    ) {}

    public function handle(NumberAllocator $allocator, DocxRenderer $renderer): void
    {
        /** @var BulkJob|null $job */
        $job = BulkJob::withoutGlobalScopes()->find($this->bulkJobId);
        if (! $job) {
            return;
        }

        $job->forceFill(['status' => BulkJobStatus::Processing])->save();

        try {
            $workspace = $job->workspace()->withoutGlobalScopes()->firstOrFail();
            $template = $job->template()->withoutGlobalScopes()->firstOrFail();
            $shared = $job->shared_variables ?? [];
            $recipients = $job->recipients ?? [];

            $letters = [];
            $processed = 0;

            foreach ($recipients as $recipientVars) {
                $mergedVars = array_replace($shared, is_array($recipientVars) ? $recipientVars : []);

                // Ensure common recipient aliases populate template placeholders smoothly
                $nameVal = $mergedVars['tujuan'] ?? $mergedVars['penerima'] ?? $mergedVars['nama'] ?? null;
                if ($nameVal !== null) {
                    $mergedVars['tujuan'] = $mergedVars['tujuan'] ?? $nameVal;
                    $mergedVars['penerima'] = $mergedVars['penerima'] ?? $nameVal;
                    $mergedVars['nama'] = $mergedVars['nama'] ?? $nameVal;
                }

                $instansiVal = $mergedVars['instansi'] ?? $mergedVars['tempat_tujuan'] ?? $mergedVars['alamat'] ?? null;
                if ($instansiVal !== null) {
                    $mergedVars['instansi'] = $mergedVars['instansi'] ?? $instansiVal;
                    $mergedVars['tempat_tujuan'] = $mergedVars['tempat_tujuan'] ?? $instansiVal;
                    $mergedVars['alamat'] = $mergedVars['alamat'] ?? $instansiVal;
                }

                $letterPerihal = trim((string) ($mergedVars['perihal'] ?? ''));
                if ($letterPerihal === '') {
                    $letterPerihal = (string) $job->perihal;
                }
                $mergedVars['perihal'] = $letterPerihal;

                $letter = DB::transaction(function () use ($workspace, $template, $job, $mergedVars, $letterPerihal, $allocator) {
                    $letter = Letter::withoutGlobalScopes()->create([
                        'workspace_id' => $workspace->id,
                        'template_id' => $template->id,
                        'created_by' => $job->created_by,
                        'bulk_job_id' => $job->id,
                        'perihal' => $letterPerihal,
                        'letter_date' => $job->letter_date->toDateString(),
                        'status' => LetterStatus::Draft,
                        'field_values' => $mergedVars,
                        'version' => 1,
                    ]);

                    $number = $allocator->allocateForLetter($letter, $workspace, $job->letter_date);

                    LetterActivity::withoutGlobalScopes()->create([
                        'letter_id' => $letter->id,
                        'workspace_id' => $workspace->id,
                        'actor_id' => $job->created_by,
                        'action' => 'created',
                        'payload' => [
                            'number' => $number->formatted_number,
                            'bulk_job_id' => $job->id,
                        ],
                        'created_at' => now(),
                    ]);

                    return $letter->fresh(['activeNumber', 'template', 'workspace']);
                });

                $singlePath = $renderer->renderSingle($letter);
                $letter->forceFill(['generated_file_path' => $singlePath])->saveQuietly();

                $letters[] = $letter;
                $processed++;

                $job->forceFill(['processed_count' => $processed])->save();
            }

            $outputPath = $renderer->renderBulkDocument($workspace, $template, $letters, $job->id);

            $job->forceFill([
                'status' => BulkJobStatus::Done,
                'processed_count' => count($letters),
                'output_path' => $outputPath,
                'finished_at' => now(),
            ])->save();

            if ($job->creator) {
                $job->creator->notify(new BulkGenerationCompletedNotification($job->fresh()));
            }
        } catch (Throwable $e) {
            $job->forceFill([
                'status' => BulkJobStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
