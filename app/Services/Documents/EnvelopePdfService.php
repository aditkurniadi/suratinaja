<?php

namespace App\Services\Documents;

use App\Models\BulkJob;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\Workspace;
use App\Services\Templates\EnvelopeStarterDocxBuilder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class EnvelopePdfService
{
    public function __construct(
        private readonly OnlyOfficeService $onlyOfficeService,
        private readonly EnvelopeStarterDocxBuilder $starterBuilder,
        private readonly PdfMergerService $pdfMerger,
    ) {}

    /**
     * Renders a single envelope PDF for a letter.
     *
     * @param  array<string, mixed>  $customVariables  Overrides for tujuan, instansi, alamat, nomor_surat
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function renderSingle(Letter $letter, ?EnvelopeTemplate $template = null, array $customVariables = []): array
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        $template = $template ?: $this->resolveDefaultTemplate($workspace);

        if (! Storage::disk('local')->exists($template->file_path)) {
            $newPath = $this->starterBuilder->build($workspace, $template->preset_size, $template->name);
            $template->forceFill(['file_path' => $newPath])->save();
        }

        $relativeDocx = "envelopes/{$workspace->id}/env_letter_{$letter->id}_" . time() . '.docx';
        Storage::disk('local')->put($relativeDocx, Storage::disk('local')->get($template->file_path));
        $workingDocx = Storage::disk('local')->path($relativeDocx);

        $resolved = EnvelopeRecipientResolver::resolve($letter, $workspace);
        $data = array_merge($resolved, array_filter($customVariables, fn ($v) => filled($v)));

        try {
            $processor = new TemplateProcessor($workingDocx);
            $processor->setValue('tujuan', $data['tujuan']);
            $processor->setValue('kepada', $data['kepada']);
            $processor->setValue('instansi', $data['instansi']);
            $processor->setValue('instansi_tertuju', $data['instansi']);
            $processor->setValue('alamat', $data['alamat']);
            $processor->setValue('nomor_surat', $data['nomor_surat']);
            $processor->setValue('perihal', $data['perihal']);
            $processor->saveAs($workingDocx);
        } catch (\Throwable $e) {
            Log::error("TemplateProcessor error for envelope: {$e->getMessage()}");
        }

        $relativeDocx = "envelopes/{$workspace->id}/" . basename($workingDocx);
        $relativePdf = "envelopes/{$workspace->id}/env_letter_{$letter->id}_" . time() . '.pdf';

        // 1. Convert via OnlyOffice Conversion API
        $res = $this->onlyOfficeService->convertArbitraryDocxToPdf(
            $relativeDocx,
            $relativePdf,
            "env_letter_{$letter->id}",
            "Amplop_{$letter->id}.docx"
        );

        if ($res['ok'] && ! empty($res['path']) && Storage::disk('local')->exists($res['path'])) {
            $res['docx_path'] = $relativeDocx;
            return $res;
        }

        // 2. Fallback to Dompdf vector render
        $fallbackRes = $this->renderFallbackDompdf($workspace, $template->preset_size, $data, $relativePdf);

        $fallbackRes['docx_path'] = $relativeDocx;
        return $fallbackRes;
    }

    /**
     * Renders a merged multi-page envelope PDF for all recipients in a BulkJob.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function renderBulk(BulkJob $job, ?EnvelopeTemplate $template = null): array
    {
        @set_time_limit(180);

        $workspace = $job->workspace()->withoutGlobalScopes()->firstOrFail();
        $template = $template ?: $this->resolveDefaultTemplate($workspace);

        if (! $template) {
            return ['ok' => false, 'message' => 'Workspace belum memiliki template amplop. Silakan buat template di menu Pengaturan Amplop.'];
        }

        $mergedRelativePdf = "envelopes/{$workspace->id}/bulk_envelopes_{$job->id}_{$template->id}.pdf";

        // Fast-path: jika file gabungan amplop sudah ada dan valid di storage, sajikan langsung
        if (Storage::disk('local')->exists($mergedRelativePdf) && Storage::disk('local')->size($mergedRelativePdf) > 0) {
            return ['ok' => true, 'path' => $mergedRelativePdf];
        }

        $letters = $job->letters()->with(['activeNumber', 'workspace'])->orderBy('id')->get();
        $pdfPaths = [];

        if ($letters->isNotEmpty()) {
            foreach ($letters as $letter) {
                $single = $this->renderSingle($letter, $template);
                if ($single['ok'] && ! empty($single['path'])) {
                    $pdfPaths[] = $single['path'];
                }
            }
        } elseif (! empty($job->recipients) && is_array($job->recipients)) {
            // Fallback cerdas untuk job lama tanpa record surat individu
            foreach ($job->recipients as $idx => $recipientRow) {
                $recipientData = array_merge((array) $recipientRow, [
                    'perihal' => $job->perihal ?: 'Surat Masal',
                ]);
                $single = $this->renderFromData($workspace, $template, $recipientData, "job_{$job->id}_{$idx}");
                if ($single['ok'] && ! empty($single['path'])) {
                    $pdfPaths[] = $single['path'];
                }
            }
        }

        if (empty($pdfPaths)) {
            return ['ok' => false, 'message' => 'Gagal menghasilkan file PDF amplop untuk batch ini. Tidak ada data surat atau penerima yang valid.'];
        }

        $mergeRes = $this->pdfMerger->merge($pdfPaths, $mergedRelativePdf);

        return $mergeRes;
    }

    public function renderFromData(Workspace $workspace, EnvelopeTemplate $template, array $data, string $key): array
    {
        if (! Storage::disk('local')->exists($template->file_path)) {
            $newPath = $this->starterBuilder->build($workspace, $template->preset_size, $template->name);
            $template->forceFill(['file_path' => $newPath])->save();
        }

        $relativeDocx = "envelopes/{$workspace->id}/env_{$key}_" . time() . '_' . Str::random(4) . '.docx';
        Storage::disk('local')->put($relativeDocx, Storage::disk('local')->get($template->file_path));
        $workingDocx = Storage::disk('local')->path($relativeDocx);

        $defaultData = [
            'tujuan' => $data['tujuan'] ?? $data['tempat_tujuan'] ?? 'Tempat',
            'kepada' => $data['kepada'] ?? $data['penerima'] ?? 'Yth. Penerima',
            'instansi' => $data['instansi'] ?? $data['instansi_tertuju'] ?? $data['lembaga'] ?? '',
            'alamat' => $data['alamat'] ?? '',
            'nomor_surat' => $data['nomor_surat'] ?? '',
            'perihal' => $data['perihal'] ?? '',
        ];

        try {
            $processor = new TemplateProcessor($workingDocx);
            $processor->setValue('tujuan', $defaultData['tujuan']);
            $processor->setValue('kepada', $defaultData['kepada']);
            $processor->setValue('instansi', $defaultData['instansi']);
            $processor->setValue('instansi_tertuju', $defaultData['instansi']);
            $processor->setValue('alamat', $defaultData['alamat']);
            $processor->setValue('nomor_surat', $defaultData['nomor_surat']);
            $processor->setValue('perihal', $defaultData['perihal']);
            $processor->saveAs($workingDocx);
        } catch (\Throwable $e) {
            Log::error("TemplateProcessor error for envelope from data: {$e->getMessage()}");
        }

        $relativePdf = "envelopes/{$workspace->id}/env_{$key}_" . time() . '_' . Str::random(4) . '.pdf';

        $res = $this->onlyOfficeService->convertArbitraryDocxToPdf(
            $relativeDocx,
            $relativePdf,
            "env_{$key}",
            "Amplop_{$key}.docx"
        );

        if ($res['ok'] && ! empty($res['path']) && Storage::disk('local')->exists($res['path'])) {
            $res['docx_path'] = $relativeDocx;
            return $res;
        }

        $fallbackRes = $this->renderFallbackDompdf($workspace, $template->preset_size, $defaultData, $relativePdf);
        $fallbackRes['docx_path'] = $relativeDocx;
        return $fallbackRes;
    }

    public function resolveDefaultTemplate(Workspace $workspace): EnvelopeTemplate
    {
        $existing = EnvelopeTemplate::where('workspace_id', $workspace->id)
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        $first = EnvelopeTemplate::where('workspace_id', $workspace->id)->first();
        if ($first) {
            return $first;
        }

        // Generate starter DL template
        $path = $this->starterBuilder->build($workspace, 'dl', 'Amplop Panjang Standar No. 90');

        return EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Amplop Panjang Standar No. 90',
            'preset_size' => 'dl',
            'file_path' => $path,
            'version' => 1,
            'is_default' => true,
        ]);
    }

    private function renderFallbackDompdf(Workspace $workspace, string $preset, array $data, string $relativePdf): array
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);

        $sizeCss = match ($preset) {
            'c5' => '@page { size: 229mm 162mm landscape; margin: 4mm 8mm; }',
            'a4' => '@page { size: 210mm 297mm portrait; margin: 6mm 8mm; }',
            default => '@page { size: 230mm 110mm landscape; margin: 4mm 8mm; }',
        };

        $kopHeader = e($workspace->kop_header_text ?: $workspace->name);
        $kopAddress = e($workspace->kop_address ?: 'Sekretariat: Kampus Politeknik');

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                {$sizeCss}
                body { font-family: Helvetica, Arial, sans-serif; margin: 0; padding: 2mm 4mm; color: #111; }
                .kop-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 3mm; margin-bottom: 4mm; }
                .kop-title { font-size: 13pt; font-weight: bold; text-transform: uppercase; text-align: center; }
                .kop-address { font-size: 8pt; text-align: center; color: #333; margin-top: 1mm; }
                .dest-box { float: right; width: 55%; margin-top: 10mm; font-size: 10pt; line-height: 1.4; }
                .dest-name { font-size: 12pt; font-weight: bold; }
                .num-tracking { position: absolute; bottom: 3mm; left: 6mm; font-size: 8pt; color: #555; }
            </style>
        </head>
        <body>
            <div class='kop-table'>
                <div class='kop-title'>{$kopHeader}</div>
                <div class='kop-address'>{$kopAddress}</div>
            </div>
            <div class='dest-box'>
                <div>Kepada Yth.</div>
                <div class='dest-name'>" . e($data['tujuan'] ?? '') . "</div>
                <div>" . e($data['instansi'] ?? '') . "</div>
                <div>" . e($data['alamat'] ?? '') . "</div>
                <div style='font-style: italic; margin-top: 2mm;'>Di - Tempat</div>
            </div>
            <div class='num-tracking'>No: " . e($data['nomor_surat'] ?? '') . "</div>
        </body>
        </html>
        ";

        $dompdf->loadHtml($html);
        $dompdf->render();

        Storage::disk('local')->put($relativePdf, $dompdf->output());

        return ['ok' => true, 'path' => $relativePdf];
    }
}
