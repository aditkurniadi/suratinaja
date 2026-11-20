<?php

namespace App\Http\Controllers;

use App\Models\BulkJob;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Documents\PdfMergerService;
use App\Services\Documents\PdfPreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BulkExportController extends Controller
{
    public function exportBatchPdf(
        Request $request,
        string $bulkJobId,
        PdfPreviewService $pdfService,
        PdfMergerService $merger,
        OnlyOfficeService $onlyOfficeService
    ): Response {
        @set_time_limit(180);

        $job = BulkJob::withoutGlobalScopes()->with('workspace')->findOrFail($bulkJobId);
        $user = $request->user();
        if (! $job->workspace || ! $job->workspace->hasMember($user)) {
            abort(403);
        }
        if (! $user->can('bulk.preview_download') && ! $user->can('surat_keluar.view')) {
            abort(403, 'Anda tidak memiliki izin mengunduh gabungan PDF surat massal.');
        }

        $outputPath = "bulk_exports/{$job->workspace_id}/batch_{$job->id}_full.pdf";
        $fileName = 'batch-surat-'.Str::slug($job->perihal ?: $job->id).'-full.pdf';

        // 1. Fast-Path: jika output PDF sudah dihasilkan dan valid di storage, sajikan langsung
        if (Storage::disk('local')->exists($outputPath) && Storage::disk('local')->size($outputPath) > 0) {
            $fullPath = Storage::disk('local')->path($outputPath);
            return $this->streamOrDownload($fullPath, $fileName, $request->boolean('download'));
        }

        // 2. Case A: Gabungkan dari record surat individu di dalam batch
        $letters = $job->letters()->with(['activeNumber', 'workspace', 'template'])->orderBy('id')->get();
        if ($letters->isNotEmpty()) {
            $pdfPaths = [];
            foreach ($letters as $letter) {
                $res = $pdfService->ensurePreviewPdf($letter, force: false);
                if ($res['ok'] && ! empty($res['path']) && Storage::disk('local')->exists($res['path'])) {
                    $pdfPaths[] = $res['path'];
                }
            }

            if (! empty($pdfPaths)) {
                $mergeRes = $merger->merge($pdfPaths, $outputPath);
                if ($mergeRes['ok'] && ! empty($mergeRes['path']) && Storage::disk('local')->exists($mergeRes['path'])) {
                    $job->forceFill(['output_pdf_path' => $mergeRes['path']])->save();
                    $fullPath = Storage::disk('local')->path($mergeRes['path']);
                    return $this->streamOrDownload($fullPath, $fileName, $request->boolean('download'));
                }

                Log::warning("Gagal merge surat individu untuk batch {$job->id}: " . ($mergeRes['message'] ?? 'Unknown error'));
            }
        }

        // 3. Case B: Fallback cerdas untuk batch lama yang memiliki file gabungan .docx
        if (! empty($job->output_path) && Storage::disk('local')->exists($job->output_path)) {
            $convRes = $onlyOfficeService->convertArbitraryDocxToPdf(
                $job->output_path,
                $outputPath,
                "bulk_job_{$job->id}",
                "batch-{$job->id}.docx"
            );

            if ($convRes['ok'] && ! empty($convRes['path']) && Storage::disk('local')->exists($convRes['path'])) {
                $job->forceFill(['output_pdf_path' => $convRes['path']])->save();
                $fullPath = Storage::disk('local')->path($convRes['path']);
                return $this->streamOrDownload($fullPath, $fileName, $request->boolean('download'));
            }

            Log::error("Gagal konversi fallback .docx ke PDF untuk batch {$job->id}: " . ($convRes['message'] ?? 'Unknown error'));
        }

        abort(500, 'Gagal menghasilkan gabungan PDF untuk batch ini. Pastikan file surat atau berkas .docx batch tersedia di storage.');
    }

    public function downloadBatchDocx(Request $request, string $bulkJobId): Response
    {
        $job = BulkJob::withoutGlobalScopes()->with('workspace')->findOrFail($bulkJobId);
        $user = $request->user();
        if (! $job->workspace || ! $job->workspace->hasMember($user)) {
            abort(403);
        }
        if (! $user->can('bulk.preview_download') && ! $user->can('surat_keluar.view')) {
            abort(403, 'Anda tidak memiliki izin mengunduh gabungan PDF surat massal.');
        }

        if (! $job->output_path || ! Storage::disk('local')->exists($job->output_path)) {
            abort(404, 'File .docx batch belum dihasilkan.');
        }

        $fullPath = Storage::disk('local')->path($job->output_path);
        $fileName = 'batch-surat-'.Str::slug($job->perihal ?: $job->id).'.docx';

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function streamOrDownload(string $fullPath, string $fileName, bool $download): Response
    {
        if (! $download) {
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        return response()->download($fullPath, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
