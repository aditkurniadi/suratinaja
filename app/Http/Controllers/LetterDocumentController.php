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
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
            return $this->renderHtmlPreviewFallback($letter, $result['message'] ?? null);
        }

        $absPath = Storage::disk('local')->path($result['path']);
        $filename = Str::slug(($letter->formatted_number ?: "surat-{$letter->id}").'-'.$letter->perihal).'.pdf';

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($result['path'], $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->file($absPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function renderHtmlPreviewFallback(Letter $letter, ?string $fallbackReason = null): Response
    {
        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            return response($fallbackReason ?? 'Pratinjau tidak tersedia.', 503);
        }

        $docxAbs = Storage::disk('local')->path($letter->generated_file_path);

        try {
            PhpWordSettings::setOutputEscapingEnabled(false);
            $phpWord = IOFactory::load($docxAbs);
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $rawHtml = (string) ob_get_clean();
        } catch (Throwable) {
            return response($fallbackReason ?? 'Pratinjau tidak tersedia.', 503);
        } finally {
            PhpWordSettings::setOutputEscapingEnabled(true);
        }

        $bodyHtml = $rawHtml;
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $rawHtml, $matches)) {
            $bodyHtml = $matches[1];
        }

        // Strip PhpWord's oversized inline width/height on images so CSS controls logo sizing cleanly
        $bodyHtml = (string) preg_replace(
            '/(<img\b[^>]*?)\s+style="[^"]*"/i',
            '$1',
            $bodyHtml
        );

        // Preserve alignment spaces in label-colon prefixes like "Nomor   :"
        $bodyHtml = (string) preg_replace_callback(
            '/(<p[^>]*>)([^<]*?:)/u',
            fn (array $m) => $m[1].str_replace('  ', '&nbsp;&nbsp;', $m[2]),
            $bodyHtml
        );

        $title = e(($letter->formatted_number ?: "Surat #{$letter->id}").' — '.$letter->perihal);

        $styledHtml = <<<HTML
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>{$title}</title>
            <style>
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    padding: 20px;
                    background: #e2e8f0;
                    font-family: 'Times New Roman', Times, serif;
                    font-size: 12pt;
                    color: #000;
                }
                .preview-toolbar {
                    max-width: 210mm;
                    margin: 0 auto 12px auto;
                    padding: 8px 14px;
                    background: #1e293b;
                    color: #f8fafc;
                    border-radius: 6px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                }
                .preview-toolbar button {
                    background: #fff;
                    color: #0f172a;
                    border: none;
                    border-radius: 4px;
                    padding: 5px 12px;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                }
                .preview-toolbar button:hover {
                    background: #f1f5f9;
                }
                div[style*="page"] {
                    background: #ffffff;
                    max-width: 210mm;
                    min-height: 297mm;
                    margin: 0 auto;
                    padding: 18mm 22mm 20mm 25mm;
                    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
                    border-radius: 2px;
                }
                p { margin: 0 0 4px 0; line-height: 1.5; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                td, th { padding: 4px 8px; vertical-align: middle; }
                div[style*="page"] > table:first-of-type {
                    table-layout: fixed !important;
                    width: 100% !important;
                    border: none !important;
                    border-bottom: 4px double #000 !important;
                    margin-bottom: 18px;
                }
                div[style*="page"] > table:first-of-type td {
                    border: none !important;
                    vertical-align: middle !important;
                    padding: 2px 6px 10px 6px !important;
                }
                div[style*="page"] > table:first-of-type td:first-child,
                div[style*="page"] > table:first-of-type td:last-child {
                    width: 16% !important;
                    text-align: center !important;
                }
                div[style*="page"] > table:first-of-type td:nth-child(2) {
                    width: 68% !important;
                    text-align: center !important;
                }
                div[style*="page"] > table:first-of-type td p {
                    margin: 0 0 2px 0 !important;
                    line-height: 1.25 !important;
                }
                div[style*="page"] > table:first-of-type img {
                    max-width: 76px !important;
                    max-height: 76px !important;
                    width: auto !important;
                    height: auto !important;
                    object-fit: contain;
                    display: inline-block;
                }
                div[style*="page"] > table:not(:first-of-type) {
                    margin-top: 6px;
                    margin-bottom: 12px;
                }
                div[style*="page"] > table:not(:first-of-type) td,
                div[style*="page"] > table:not(:first-of-type) th {
                    border: 1px solid #333;
                    padding: 6px 10px;
                }
                @media print {
                    body { background: #fff; padding: 0; }
                    .preview-toolbar { display: none !important; }
                    div[style*="page"] {
                        box-shadow: none;
                        padding: 0;
                        max-width: none;
                        min-height: auto;
                    }
                }
            </style>
        </head>
        <body>
            <div class="preview-toolbar">
                <span>Pratinjau Dokumen Lokal (Mode Tanpa Gotenberg)</span>
                <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
            </div>
            {$bodyHtml}
        </body>
        </html>
        HTML;

        return response($styledHtml, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
