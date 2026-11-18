<?php

namespace App\Services\Documents;

use App\Models\Letter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PdfPreviewService
{
    public function __construct(
        private readonly DocxRenderer $docxRenderer,
    ) {}

    /**
     * Converts a letter's generated .docx into PDF via Gotenberg (`/forms/libreoffice/convert`)
     * and caches the PDF on disk per letter version.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function ensurePreviewPdf(Letter $letter): array
    {
        if ($letter->preview_pdf_path && Storage::disk('local')->exists($letter->preview_pdf_path)) {
            return ['ok' => true, 'path' => $letter->preview_pdf_path];
        }

        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $docxPath = $this->docxRenderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        $docxAbs = Storage::disk('local')->path($letter->generated_file_path);
        $baseUrl = rtrim((string) config('suratinaja.preview.gotenberg_url', 'http://localhost:3000'), '/');
        $timeout = (int) config('suratinaja.preview.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->attach('files', fopen($docxAbs, 'r'), "letter-{$letter->id}.docx")
                ->post("{$baseUrl}/forms/libreoffice/convert");

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Layanan konversi PDF (Gotenberg) mengembalikan status '.$response->status().'. Anda tetap dapat mengunduh file .docx resmi.',
                ];
            }

            $relativePdf = "previews/{$letter->workspace_id}/letter-{$letter->id}-v{$letter->version}.pdf";
            Storage::disk('local')->put($relativePdf, $response->body());
            $letter->forceFill(['preview_pdf_path' => $relativePdf])->saveQuietly();

            return ['ok' => true, 'path' => $relativePdf];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Pratinjau PDF belum tersedia (layanan Gotenberg tidak terjangkau). Silakan unduh file .docx untuk melihat dokumen.',
            ];
        }
    }
}
