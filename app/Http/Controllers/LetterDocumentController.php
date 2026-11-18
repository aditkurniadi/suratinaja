<?php

namespace App\Http\Controllers;

use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\PdfPreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LetterDocumentController extends Controller
{
    public function downloadLetter(Request $request, int $letterId, DocxRenderer $renderer): Response
    {
        $letter = Letter::withoutGlobalScopes()->with('workspace')->findOrFail($letterId);
        if (! $letter->workspace || ! $letter->workspace->hasMember($request->user())) {
            abort(403);
        }

        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $path = $renderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $path])->saveQuietly();
        }

        $filename = Str::slug(($letter->formatted_number ?: "surat-{$letter->id}").'-'.$letter->perihal).'.docx';

        return Storage::disk('local')->download($letter->generated_file_path, $filename);
    }

    public function downloadBulk(Request $request, string $bulkJobId): Response
    {
        $job = BulkJob::withoutGlobalScopes()->with('workspace')->findOrFail($bulkJobId);
        if (! $job->workspace || ! $job->workspace->hasMember($request->user())) {
            abort(403);
        }

        if (! $job->output_path || ! Storage::disk('local')->exists($job->output_path)) {
            abort(404, 'File hasil bulk generation belum siap atau tidak ditemukan.');
        }

        $filename = Str::slug("bulk-{$job->perihal}-{$job->id}").'.docx';

        return Storage::disk('local')->download($job->output_path, $filename);
    }

    public function downloadTemplate(Request $request, int $templateId): Response
    {
        $template = LetterTemplate::withoutGlobalScopes()->with('workspace')->findOrFail($templateId);
        $activeWorkspace = $request->user()->activeWorkspace();

        if (! $template->isGlobal() && (! $activeWorkspace || ! $template->isAccessibleBy($activeWorkspace))) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($template->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($template->file_path, Str::slug($template->name).'.docx');
    }

    public function previewPdf(Request $request, int $letterId, PdfPreviewService $pdfService): Response
    {
        $letter = Letter::withoutGlobalScopes()->with('workspace')->findOrFail($letterId);
        if (! $letter->workspace || ! $letter->workspace->hasMember($request->user())) {
            abort(403);
        }

        $result = $pdfService->ensurePreviewPdf($letter);
        if (! $result['ok']) {
            return response($result['message'] ?? 'Pratinjau tidak tersedia.', 503);
        }

        $absPath = Storage::disk('local')->path($result['path']);

        return response()->file($absPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-'.$letter->id.'.pdf"',
        ]);
    }
}
