<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Bulk\StartBulkGenerationAction;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\BulkJob;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkLetterController extends Controller
{
    /**
     * POST /api/v1/workspaces/{workspace}/letters/bulk
     */
    public function store(
        Request $request,
        Workspace $workspace,
        StartBulkGenerationAction $action,
    ): JsonResponse {
        $payload = $request->all();

        if ($request->hasFile('recipients_file')) {
            $stored = $request->file('recipients_file')->store('temp/bulk', 'local');
            $payload['recipients'] = [
                'mode' => 'upload',
                'file_path' => storage_path('app/private/'.$stored),
            ];
        }

        $job = $action->execute($workspace, $request->user(), $payload);

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->id,
                'status' => $job->status->value,
                'recipient_count' => $job->recipient_count,
                'failed_rows' => $job->failed_rows ?? [],
                'poll_url' => "/api/v1/jobs/{$job->id}",
            ],
            'message' => 'Job bulk generation diterima.',
        ], 202);
    }

    /**
     * GET /api/v1/jobs/{bulkJob}
     */
    public function showJob(Request $request, string $bulkJobId): JsonResponse
    {
        $job = BulkJob::withoutGlobalScopes()->find($bulkJobId);
        if (! $job) {
            throw DomainException::workspaceForbidden('Job bulk tidak ditemukan.');
        }

        $workspace = $job->workspace()->withoutGlobalScopes()->first();
        if (! $workspace || ! $workspace->hasMember($request->user())) {
            throw DomainException::workspaceForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->id,
                'status' => $job->status->value,
                'recipient_count' => $job->recipient_count,
                'processed_count' => $job->processed_count,
                'progress_percent' => $job->progressPercent(),
                'failed_rows' => $job->failed_rows ?? [],
                'download_url' => $job->output_path
                    ? route('bulk.download', ['bulkJob' => $job->id], false)
                    : null,
                'finished_at' => $job->finished_at?->toIso8601ZuluString(),
            ],
            'message' => 'Status job bulk generation.',
        ]);
    }
}
