<?php

namespace App\Services\Documents;

use App\Models\Letter;
use App\Models\Workspace;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class PdfPreviewService
{
    public function __construct(
        private readonly DocxRenderer $docxRenderer,
        private readonly KopInjector $kopInjector,
        private readonly OnlyOfficeService $onlyOfficeService,
    ) {}

    /**
     * Converts a letter's generated .docx into PDF via OnlyOffice Document Server Conversion API (100% WYSIWYG),
     * or falls back to Gotenberg (`/forms/libreoffice/convert`) or local Dompdf rendering
     * (A4 portrait, 2.54cm Normal Margin, no browser watermarks), caching the PDF on disk per letter version.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    public function ensurePreviewPdf(Letter $letter, bool $force = false): array
    {
        if (! $force && $letter->preview_pdf_path && Storage::disk('local')->exists($letter->preview_pdf_path)) {
            return ['ok' => true, 'path' => $letter->preview_pdf_path];
        }

        if (! $letter->generated_file_path || ! Storage::disk('local')->exists($letter->generated_file_path)) {
            $docxPath = $this->docxRenderer->renderSingle($letter);
            $letter->forceFill(['generated_file_path' => $docxPath])->saveQuietly();
        }

        // 1. Primary Engine: OnlyOffice Document Server Conversion API (100% WYSIWYG matching online editor)
        try {
            $ooResult = $this->onlyOfficeService->convertToPdf($letter, $force);
            if ($ooResult['ok'] && ! empty($ooResult['path'])) {
                return $ooResult;
            }
            Log::warning("OnlyOffice ConvertService did not return success for letter {$letter->id}, falling back: " . ($ooResult['message'] ?? 'unknown error'));
        } catch (Throwable $e) {
            Log::warning("OnlyOffice ConvertService failed for letter {$letter->id}, falling back: {$e->getMessage()}");
        }

        // 2. Secondary Engine: Gotenberg (LibreOffice)
        $docxAbs = Storage::disk('local')->path($letter->generated_file_path);
        $baseUrl = rtrim((string) config('suratinaja.preview.gotenberg_url', 'http://localhost:3000'), '/');
        $timeout = (int) config('suratinaja.preview.timeout', 3);
        $relativePdf = "previews/{$letter->workspace_id}/letter-{$letter->id}-v{$letter->version}.pdf";

        try {
            $response = Http::connectTimeout(2)
                ->timeout($timeout)
                ->attach('files', fopen($docxAbs, 'r'), "letter-{$letter->id}.docx")
                ->post("{$baseUrl}/forms/libreoffice/convert");

            if ($response->successful()) {
                Storage::disk('local')->put($relativePdf, $response->body());
                $letter->forceFill(['preview_pdf_path' => $relativePdf])->saveQuietly();

                return ['ok' => true, 'path' => $relativePdf];
            }
        } catch (Throwable) {
            // Fall through to local Dompdf generator
        }

        // 3. Fallback Engine: Local Dompdf
        return $this->renderWithDompdf($letter, $relativePdf);
    }

    /**
     * Renders the letter to a true binary PDF using Dompdf with standard 2.54cm (1 inch) Normal margins,
     * justified paragraphs, dynamic signature block, and a dedicated Page 2 for Lampiran when present.
     *
     * @return array{ok: bool, path?: string, message?: string}
     */
    private function renderWithDompdf(Letter $letter, string $relativePdf): array
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        $template = $letter->template()->withoutGlobalScopes()->first();
        $values = $letter->field_values ?? [];
        $schema = $template?->variables_schema ?? [];

        $hasEmbeddedKop = $template ? $template->hasEmbeddedKop() : false;
        $embeddedKopHtml = null;
        if ($hasEmbeddedKop || ! $workspace->hasKop()) {
            $embeddedKopHtml = $this->extractEmbeddedKopHtml($letter->generated_file_path);
        }

        $hasKop = false;
        $runningHeaderHtml = '';
        $marginTop = '2.54cm';
        $headerTop = '-2.0cm';

        if ($embeddedKopHtml !== null && trim($embeddedKopHtml) !== '') {
            $hasKop = true;
            $runningHeaderHtml = '<div class="header-kop">'.$embeddedKopHtml.'</div>';
            $lineCount = max(4, substr_count($embeddedKopHtml, '<p') + substr_count($embeddedKopHtml, '<tr'));
            $kopContentHeightCm = max(3.2, ($lineCount * 0.48) + 0.8);
            $marginTopCm = round($kopContentHeightCm + 1.45, 2);
            $marginTop = "{$marginTopCm}cm";
            $headerTop = '-'.round($marginTopCm - 0.7, 2).'cm';
        } elseif ($workspace->hasKop() && ! $hasEmbeddedKop) {
            $hasKop = true;
            $lines = $this->kopInjector->headerLines($workspace);
            $addrLines = $this->kopInjector->addressLines($workspace);
            $nHeader = count($lines);
            $nAddr = count($addrLines);
            $kopContentHeightCm = max(3.2, ($nHeader * 0.55) + ($nAddr * 0.45) + 0.8);
            $marginTopCm = round($kopContentHeightCm + 1.45, 2);
            $marginTop = "{$marginTopCm}cm";
            $headerTop = '-'.round($marginTopCm - 0.7, 2).'cm';

            $kopHtml = $this->buildKopHtml($workspace);
            $runningHeaderHtml = '<div class="header-kop">'.$kopHtml.'</div>';
        }

        $city = e((string) ($values['_city'] ?? $workspace->default_city ?: 'Cilacap'));
        $letterDateFormatted = e($this->formatDate($letter->letter_date));
        $nomorSurat = e((string) ($letter->formatted_number ?? '—'));
        $perihal = e((string) $letter->perihal);

        $repeaterTables = $this->extractRepeaterTables($schema, $values);
        $hasLampiran = count($repeaterTables) > 0;
        $jumlahLampiran = $hasLampiran ? 'Satu (1) lembar' : '-';

        $penerima = e((string) ($values['penerima'] ?? $values['tujuan'] ?? $values['kepada'] ?? $values['nama_penerima'] ?? '—'));
        $instansiTujuan = e((string) ($values['instansi_tujuan'] ?? $values['instansi'] ?? $values['nama_instansi'] ?? 'Politeknik Negeri Cilacap'));
        $namaKegiatan = e((string) ($values['nama_kegiatan'] ?? $letter->perihal));
        $tempatKegiatan = e((string) ($values['tempat_tujuan'] ?? 'Politeknik Negeri Cilacap'));
        $waktuKegiatan = e((string) ($values['waktu_kegiatan'] ?? '07.00 s.d. Selesai'));
        $hariTanggalKegiatan = e($this->formatDayAndDate($values['tanggal_kegiatan'] ?? $letter->letter_date));
        $extraScalarRowsHtml = $this->buildExtraScalarRowsHtml($schema, $values);

        $signers = is_array($values['_signers'] ?? null) && count($values['_signers']) > 0
            ? array_values($values['_signers'])
            : $workspace->defaultSigners();

        $signatureHtml = $this->buildSignatureTableHtml($signers);
        $page2Html = $hasLampiran
            ? $this->buildDynamicLampiranPagesHtml($nomorSurat, $repeaterTables)
            : '';

        $isCustomTemplate = $template && (
            ! in_array((int) $template->id, [1], true) &&
            $template->file_path !== 'templates/global/undangan-kegiatan-standar.docx'
        );

        if ($isCustomTemplate) {
            $contentHtml = $this->renderCustomDocxBodyHtml($letter, $hasKop, $signatureHtml, $page2Html, $hasEmbeddedKop);
        } else {
            $contentHtml = <<<HTML
            <p style="text-align: right; margin-top: 0; margin-bottom: 6pt; font-size: 12pt;">{$city}, {$letterDateFormatted}</p>

            <table class="meta-table">
                <tr>
                    <td style="width: 68pt;">Nomor</td>
                    <td style="width: 12pt;">:</td>
                    <td>{$nomorSurat}</td>
                </tr>
                <tr>
                    <td>Lampiran</td>
                    <td>:</td>
                    <td>{$jumlahLampiran}</td>
                </tr>
                <tr>
                    <td>Hal</td>
                    <td>:</td>
                    <td><span style="font-weight: bold; text-decoration: underline;">{$perihal}</span></td>
                </tr>
            </table>

            <div style="margin-bottom: 12pt; line-height: 1.4;">
                <p>Kepada Yth.</p>
                <p style="font-weight: bold;">{$penerima}</p>
                <p>{$instansiTujuan}</p>
                <p>Di - Tempat</p>
            </div>

            <p style="margin-bottom: 6pt;">Dengan Hormat,</p>

            <p class="justify" style="margin-bottom: 4pt;">
                Sehubungan dengan penyelenggaraan kegiatan {$namaKegiatan} oleh Himpunan Mahasiswa Komputer dan Bisnis (HIMATRIS) Politeknik Negeri Cilacap, kami bermaksud mengajukan permohonan/undangan sebagaimana tercantum pada perihal surat ini. Adapun kegiatan tersebut akan diselenggarakan pada:
            </p>

            <table class="schedule-table">
                <tr>
                    <td style="width: 36pt;"></td>
                    <td style="width: 88pt;">Hari, Tanggal</td>
                    <td style="width: 12pt;">:</td>
                    <td>{$hariTanggalKegiatan}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Waktu</td>
                    <td>:</td>
                    <td>{$waktuKegiatan}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Tempat</td>
                    <td>:</td>
                    <td>{$tempatKegiatan}</td>
                </tr>
                {$extraScalarRowsHtml}
            </table>

            <p class="justify" style="margin-top: 4pt;">
                Demikian surat ini kami sampaikan. Atas perhatian dan izin yang diberikan, kami ucapkan terima kasih.
            </p>

            {$signatureHtml}

            {$page2Html}
HTML;
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    size: A4 portrait;
                    margin: {$marginTop} 2.54cm 2.2cm 2.54cm;
                }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Times New Roman', Times, serif;
                    font-size: 12pt;
                    line-height: 1.35;
                    color: #000000;
                }
                div.header-kop {
                    position: fixed;
                    top: {$headerTop};
                    left: 0;
                    right: 0;
                }
                p {
                    margin: 0 0 3pt 0;
                    padding: 0;
                }
                .justify {
                    text-align: justify;
                    line-height: 1.5;
                }
                table.kop-table {
                    width: 100%;
                    table-layout: fixed;
                    border-collapse: collapse;
                    border: none;
                    margin: 0;
                }
                table.kop-table td {
                    border: none;
                    vertical-align: middle;
                    padding: 0 2pt 2pt 2pt;
                    text-align: center;
                }
                table.kop-table td.kop-logo-cell {
                    width: 14%;
                }
                table.kop-table td.kop-text-cell {
                    width: 72%;
                }
                .kop-header-line {
                    font-size: 12pt;
                    line-height: 1.15;
                    margin: 0;
                    text-transform: uppercase;
                }
                .kop-address-line {
                    font-size: 10pt;
                    line-height: 1.2;
                    margin: 0;
                }
                .kop-link {
                    color: #0563c1;
                    text-decoration: underline;
                }
                .kop-divider {
                    border-top: 0.75pt solid #000000;
                    border-bottom: 2.25pt solid #000000;
                    height: 1.5pt;
                    margin-top: 4pt;
                    margin-bottom: 10pt;
                }
                table.meta-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: none;
                    margin-bottom: 12pt;
                }
                table.meta-table td {
                    border: none;
                    padding: 1pt 0;
                    vertical-align: top;
                    font-size: 12pt;
                }
                table.schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: none;
                    margin: 4pt 0 6pt 0;
                }
                table.schedule-table td {
                    border: none;
                    padding: 1.5pt 0;
                    vertical-align: top;
                    font-size: 12pt;
                }
                table.sig-table {
                    width: 100%;
                    table-layout: fixed;
                    border-collapse: collapse;
                    border: none;
                    page-break-inside: avoid;
                }
                table.sig-table td {
                    border: none;
                    text-align: center;
                    padding: 0 4pt;
                }
                tr.sig-header-row td {
                    vertical-align: bottom;
                    padding-bottom: 0;
                }
                tr.sig-body-row td {
                    vertical-align: top;
                    padding-top: 0;
                }
                .sig-prefix {
                    font-weight: bold;
                    margin: 0 0 2pt 0;
                }
                .sig-position {
                    margin: 0;
                }
                .sig-space {
                    height: 62pt;
                }
                .sig-name {
                    text-decoration: underline;
                    margin: 0 0 2pt 0;
                }
                .sig-id {
                    margin: 0;
                    font-size: 11pt;
                }
                .page-break {
                    page-break-before: always !important;
                    break-before: page !important;
                    clear: both;
                    display: block;
                    height: 0;
                    margin: 0;
                    padding: 0;
                }
                .lampiran-section {
                    page-break-before: always !important;
                    break-before: page !important;
                    clear: both;
                }
                table.lampiran-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8pt;
                }
                table.lampiran-table th,
                table.lampiran-table td {
                    border: 0.75pt solid #000000;
                    padding: 3.5pt 6pt;
                    font-size: 10.5pt;
                    vertical-align: middle;
                }
                table.lampiran-table th {
                    background-color: #f2f2f2;
                    font-weight: bold;
                    text-align: center;
                    font-size: 11pt;
                }
            </style>
        </head>
        <body>
            {$runningHeaderHtml}
            {$contentHtml}
        </body>
        </html>
        HTML;

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Times-Roman');
            $options->set('defaultPaperSize', 'a4');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            Storage::disk('local')->put($relativePdf, $dompdf->output());
            $letter->forceFill(['preview_pdf_path' => $relativePdf])->saveQuietly();

            return ['ok' => true, 'path' => $relativePdf];
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => 'Pratinjau PDF belum tersedia. Silakan unduh file .docx untuk melihat dokumen.',
            ];
        }
    }

    private function buildKopHtml(Workspace $workspace): string
    {
        if (! $workspace->hasKop()) {
            return '';
        }

        $leftImg = $this->logoDataUri($workspace->kop_logo_left);
        $rightImg = $this->logoDataUri($workspace->kop_logo_right);

        $leftHtml = $leftImg ? '<img src="'.$leftImg.'" style="max-width: 84px; max-height: 84px; width: auto; height: auto;" />' : '';
        $rightHtml = $rightImg ? '<img src="'.$rightImg.'" style="max-width: 84px; max-height: 84px; width: auto; height: auto;" />' : '';

        $lines = $this->kopInjector->headerLines($workspace);
        $hasHimpunanLine = collect($lines)->contains(fn (string $l) => stripos($l, 'HIMPUNAN') !== false);

        $centerHtml = '';
        foreach ($lines as $idx => $line) {
            $isBold = count($lines) <= 2
                ? true
                : ($hasHimpunanLine ? stripos($line, 'HIMPUNAN') !== false : $idx === 0);

            $weight = $isBold ? 'font-weight: bold;' : 'font-weight: normal;';
            $centerHtml .= '<p class="kop-header-line" style="'.$weight.'">'.e(mb_strtoupper($line)).'</p>';
        }

        foreach ($this->kopInjector->addressLines($workspace) as $aLine) {
            $escaped = e($aLine);
            // Style website & email with standard link underline as in the reference PDF
            $escaped = (string) preg_replace(
                '/\b((?:https?:\/\/|www\.)[^\s,]+|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/',
                '<span class="kop-link">$1</span>',
                $escaped
            );
            $centerHtml .= '<p class="kop-address-line">'.$escaped.'</p>';
        }

        return <<<HTML
        <table class="kop-table">
            <tr>
                <td class="kop-logo-cell">{$leftHtml}</td>
                <td class="kop-text-cell">{$centerHtml}</td>
                <td class="kop-logo-cell">{$rightHtml}</td>
            </tr>
        </table>
        <div class="kop-divider"></div>
        HTML;
    }

    /**
     * @param  array<int, array{prefix?: string, position?: string, name?: string, id_label?: string, id_number?: string, layout?: string}>  $signers
     */
    private function buildSignatureTableHtml(array $signers): string
    {
        if (count($signers) === 0) {
            return '';
        }

        // Pure automatic grouping: pairs of 2 (Left & Right), odd last item centered
        $rows = [];
        $curr = 0;
        $total = count($signers);

        if ($total === 1) {
            $rows[] = ['type' => 'single', 'signers' => [$signers[0]]];
        } else {
            while ($curr < $total) {
                if ($curr === $total - 1 && ($total % 2 === 1)) {
                    $rows[] = ['type' => 'center', 'signers' => [$signers[$curr]]];
                    $curr++;
                } else {
                    $rows[] = ['type' => 'pair', 'signers' => [$signers[$curr], $signers[$curr + 1]]];
                    $curr += 2;
                }
            }
        }

        $html = '';
        foreach ($rows as $rIdx => $rowMeta) {
            $rType = $rowMeta['type'];
            $rSigners = $rowMeta['signers'];
            $marginTop = $rIdx === 0 ? '14pt' : '18pt';

            if ($rType === 'single') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $prefixHtml = $prefix !== '' ? '<p class="sig-prefix">'.e($prefix).'</p>' : '';
                $posHtml = $pos !== '' ? '<p class="sig-position">'.e($pos).'</p>' : '';
                $idHtml = $idLine !== '' ? '<p class="sig-id">'.e($idLine).'</p>' : '';

                $html .= <<<HTML
                <table class="sig-table" style="margin-top: {$marginTop};">
                    <tr class="sig-header-row">
                        <td style="width: 50%;"></td>
                        <td class="sig-header-cell" style="width: 50%;">
                            {$prefixHtml}
                            {$posHtml}
                        </td>
                    </tr>
                    <tr class="sig-body-row">
                        <td style="width: 50%;"></td>
                        <td class="sig-body-cell" style="width: 50%;">
                            <div class="sig-space"></div>
                            <p class="sig-name">{$name}</p>
                            {$idHtml}
                        </td>
                    </tr>
                </table>
                HTML;
            } elseif ($rType === 'center') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $prefixHtml = $prefix !== '' ? '<p class="sig-prefix">'.e($prefix).'</p>' : '';
                $posHtml = $pos !== '' ? '<p class="sig-position">'.e($pos).'</p>' : '';
                $idHtml = $idLine !== '' ? '<p class="sig-id">'.e($idLine).'</p>' : '';

                $html .= <<<HTML
                <table class="sig-table" style="margin-top: {$marginTop};">
                    <tr class="sig-header-row">
                        <td class="sig-header-cell" style="width: 100%;">
                            {$prefixHtml}
                            {$posHtml}
                        </td>
                    </tr>
                    <tr class="sig-body-row">
                        <td class="sig-body-cell" style="width: 100%;">
                            <div class="sig-space"></div>
                            <p class="sig-name">{$name}</p>
                            {$idHtml}
                        </td>
                    </tr>
                </table>
                HTML;
            } else {
                // Pair: 2 columns (Left & Right)
                $sLeft = $rSigners[0];
                $sRight = $rSigners[1];

                $prefixLeft = trim((string) ($sLeft['prefix'] ?? ''));
                $posLeft = trim((string) ($sLeft['position'] ?? ''));
                $nameLeft = trim((string) ($sLeft['name'] ?? ''));
                $idLineLeft = trim(($sLeft['id_label'] ?? '').' '.($sLeft['id_number'] ?? ''));

                $prefixRight = trim((string) ($sRight['prefix'] ?? ''));
                $posRight = trim((string) ($sRight['position'] ?? ''));
                $nameRight = trim((string) ($sRight['name'] ?? ''));
                $idLineRight = trim(($sRight['id_label'] ?? '').' '.($sRight['id_number'] ?? ''));

                $prefixLeftHtml = $prefixLeft !== '' ? '<p class="sig-prefix">'.e($prefixLeft).'</p>' : '';
                $posLeftHtml = $posLeft !== '' ? '<p class="sig-position">'.e($posLeft).'</p>' : '';
                $idLeftHtml = $idLineLeft !== '' ? '<p class="sig-id">'.e($idLineLeft).'</p>' : '';

                $prefixRightHtml = $prefixRight !== '' ? '<p class="sig-prefix">'.e($prefixRight).'</p>' : '';
                $posRightHtml = $posRight !== '' ? '<p class="sig-position">'.e($posRight).'</p>' : '';
                $idRightHtml = $idLineRight !== '' ? '<p class="sig-id">'.e($idLineRight).'</p>' : '';

                $html .= <<<HTML
                <table class="sig-table" style="margin-top: {$marginTop};">
                    <tr class="sig-header-row">
                        <td class="sig-header-cell" style="width: 50%;">
                            {$prefixLeftHtml}
                            {$posLeftHtml}
                        </td>
                        <td class="sig-header-cell" style="width: 50%;">
                            {$prefixRightHtml}
                            {$posRightHtml}
                        </td>
                    </tr>
                    <tr class="sig-body-row">
                        <td class="sig-body-cell" style="width: 50%;">
                            <div class="sig-space"></div>
                            <p class="sig-name">{$nameLeft}</p>
                            {$idLeftHtml}
                        </td>
                        <td class="sig-body-cell" style="width: 50%;">
                            <div class="sig-space"></div>
                            <p class="sig-name">{$nameRight}</p>
                            {$idRightHtml}
                        </td>
                    </tr>
                </table>
                HTML;
            }
        }

        return $html;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $values
     */
    private function buildExtraScalarRowsHtml(array $schema, array $values): string
    {
        $standardKeys = [
            'penerima',
            'tujuan',
            'kepada',
            'nama_penerima',
            'instansi_tujuan',
            'instansi',
            'nama_instansi',
            'nama_kegiatan',
            'tanggal_kegiatan',
            'tanggal_keiatan',
            'tempat_tujuan',
            'waktu_kegiatan',
            'waktu',
            'ruang_kelas',
            'lampiran',
            'kota_surat',
            'tanggal_surat',
            'nomor_surat',
            'perihal',
        ];

        $html = '';
        foreach ($schema as $field) {
            $type = $field['type'] ?? 'text';
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || $type === 'system' || $type === 'repeater' || in_array($name, $standardKeys, true)) {
                continue;
            }

            $raw = $values[$name] ?? '';
            if (is_array($raw) || trim((string) $raw) === '') {
                continue;
            }

            $label = e((string) ($field['label'] ?? $name));
            $val = e($type === 'date' ? $this->formatDate($raw) : (string) $raw);

            $html .= <<<HTML
            <tr>
                <td></td>
                <td>{$label}</td>
                <td>:</td>
                <td>{$val}</td>
            </tr>
            HTML;
        }

        return $html;
    }

    /**
     * Renders a custom Word template directly from its generated .docx document.xml
     * preserving the user's exact paragraphs, styling, and values without hardcoded template text.
     */
    private function renderCustomDocxBodyHtml(Letter $letter, bool $hasKop, string $signatureHtml, string $page2Html, bool $hasEmbeddedKop = false): string
    {
        $docxAbs = Storage::disk('local')->path($letter->generated_file_path);
        if (! file_exists($docxAbs)) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($docxAbs) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $docRelsXml = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $imgMap = [];
        if ($docRelsXml !== '') {
            preg_match_all('/<Relationship\b[^>]*Id="([^"]+)"[^>]*Type="[^"]*relationships\/image"[^>]*Target="([^"]+)"/', $docRelsXml, $imgMatches, PREG_SET_ORDER);
            foreach ($imgMatches as $im) {
                $rId = $im[1];
                $imgTarget = $im[2];
                $imgPath = 'word/'.ltrim(str_replace('../', '', $imgTarget), '/');
                $raw = $zip->getFromName($imgPath);
                if ($raw) {
                    $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                    $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
                    $imgMap[$rId] = 'data:'.$mime.';base64,'.base64_encode($raw);
                }
            }
        }
        $zip->close();

        // If workspace Kop is rendered in running header, remove all Kop tables from body XML
        if ($hasKop) {
            $xml = preg_replace('/<w:tbl\b(?:(?!<\/w:tbl>).)*?w:val="double".*?<\/w:tbl>/s', '', $xml) ?? $xml;
        }

        // Replace signature table in-place so styled $signatureHtml stays on Page 1 before any Lampiran page breaks
        $signatureReplaced = false;
        $sigPattern = '/<w:tbl\b(?:(?!<\/w:tbl>).)*?(?:NIM\.|NIP\.|ttd_|Menyetujui|Mengetahui).*?<\/w:tbl>/s';
        if (preg_match($sigPattern, $xml)) {
            $xml = preg_replace($sigPattern, '<w:p><w:r><w:t>__SIGNATURE_HTML_PLACEHOLDER__</w:t></w:r></w:p>', $xml, 1) ?? $xml;
            $signatureReplaced = true;
        }

        preg_match_all('/(<w:p\b.*?<\/w:p>|<w:tbl\b.*?<\/w:tbl>)/s', $xml, $elements);
        $bodyHtml = '';
        $justEmittedPageBreak = false;

        foreach ($elements[0] as $idx => $elem) {
            if (str_starts_with($elem, '<w:tbl')) {
                $justEmittedPageBreak = false;
                $colWidths = [];
                if (preg_match_all('/<w:gridCol\b[^>]*w:w="(\d+)"/', $elem, $colMatches)) {
                    $rawWidths = array_map('intval', $colMatches[1]);
                    $totalWidth = array_sum($rawWidths) ?: 1;
                    foreach ($rawWidths as $w) {
                        $colWidths[] = round(($w / $totalWidth) * 100, 1).'%';
                    }
                }

                $hasGridStyle = (bool) preg_match('/<w:tblStyle\b[^>]*w:val="[^"]*(?:Grid|Table)[^"]*"/i', $elem)
                    && ! str_contains($elem, 'w:val="TableNormal"');
                $hasExplicitSingleBorder = str_contains($elem, 'w:val="single"');
                $hasHeaderKeyword = (bool) preg_match('/\b(Tanggal|Waktu|Kegiatan|Acara|Jadwal|Rundown|Pelaksana|Keterangan|Uraian|NIM|Nama|No|Peserta|Panitia|Jabatan|Materi|Narasumber)\b/i', strip_tags($elem));
                $isTableBordered = $hasGridStyle || $hasExplicitSingleBorder || $hasHeaderKeyword || count($colWidths) >= 3;

                preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $elem, $trMatches);
                $parsedRows = [];
                foreach ($trMatches[0] as $trXml) {
                    preg_match_all('/<w:tc\b.*?<\/w:tc>/s', $trXml, $tcMatches);
                    $parsedRows[] = [
                        'xml' => $trXml,
                        'cells' => $tcMatches[0],
                    ];
                }

                $tableRows = '';
                foreach ($parsedRows as $rIdx => $rowMeta) {
                    $trXml = $rowMeta['xml'];
                    $tcList = $rowMeta['cells'];
                    $isHeaderRow = $isTableBordered && ($rIdx === 0 || str_contains($trXml, '<w:tblHeader'));
                    $cells = '';

                    foreach ($tcList as $cIdx => $tcXml) {
                        $cellImgs = '';
                        if (preg_match_all('/(?:r:embed|r:id)="([^"]+)"/', $tcXml, $blips)) {
                            foreach ($blips[1] as $bId) {
                                if (isset($imgMap[$bId])) {
                                    $cellImgs .= '<img src="'.$imgMap[$bId].'" style="max-height: 70px; width: auto;" /><br/>';
                                }
                            }
                        }

                        preg_match_all('/<w:p\b.*?<\/w:p>/s', $tcXml, $cellPMatches);
                        $cellLines = [];
                        $cellAlign = 'left';
                        if (preg_match('/<w:jc\s+w:val="([^"]+)"/', $tcXml, $cjm)) {
                            $cellAlign = $cjm[1] === 'both' ? 'justify' : $cjm[1];
                        }

                        foreach ($cellPMatches[0] as $cpXml) {
                            preg_match_all('/<w:r\b.*?<\/w:r>/s', $cpXml, $crMatches);
                            $lineHtml = '';
                            foreach ($crMatches[0] as $crXml) {
                                $cBold = str_contains($crXml, '<w:b/>') || str_contains($crXml, '<w:b ');
                                $cItalic = str_contains($crXml, '<w:i/>') || str_contains($crXml, '<w:i ');
                                $cUnderline = str_contains($crXml, '<w:u ');
                                if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $crXml, $ctm)) {
                                    $tText = e(implode('', $ctm[1]));
                                    if ($tText !== '') {
                                        if ($cBold && ! $isHeaderRow) {
                                            $tText = '<strong>'.$tText.'</strong>';
                                        }
                                        if ($cUnderline) {
                                            $tText = '<u>'.$tText.'</u>';
                                        }
                                        if ($cItalic) {
                                            $tText = '<em>'.$tText.'</em>';
                                        }
                                        $lineHtml .= $tText;
                                    }
                                }
                            }
                            if (trim(strip_tags($lineHtml)) !== '') {
                                $cellLines[] = $lineHtml;
                            }
                        }

                        $cellTextHtml = implode('<br/>', $cellLines);
                        $cellContent = $cellImgs.($cellTextHtml !== '' ? $cellTextHtml : '&nbsp;');
                        $wStyle = isset($colWidths[$cIdx]) ? 'width: '.$colWidths[$cIdx].'; ' : '';

                        if ($isHeaderRow) {
                            $cellStyle = $wStyle.'border: 0.75pt solid #000000; padding: 4pt 6pt; background-color: #f2f2f2; font-weight: bold; text-align: center; font-size: 11pt; vertical-align: middle;';
                            $cells .= '<th style="'.$cellStyle.'">'.$cellContent.'</th>';
                        } elseif ($isTableBordered) {
                            $isVMergeContinue = (bool) preg_match('/<w:vMerge\s*\/>/', $tcXml);
                            $nextRowCellXml = $parsedRows[$rIdx + 1]['cells'][$cIdx] ?? '';
                            $nextIsVMergeContinue = $nextRowCellXml !== '' && (bool) preg_match('/<w:vMerge\s*\/>/', $nextRowCellXml);

                            $bTop = $isVMergeContinue ? 'border-top: none;' : 'border-top: 0.75pt solid #000000;';
                            $bBottom = $nextIsVMergeContinue ? 'border-bottom: none;' : 'border-bottom: 0.75pt solid #000000;';
                            $cellStyle = $wStyle.$bTop.' '.$bBottom.' border-left: 0.75pt solid #000000; border-right: 0.75pt solid #000000; padding: 3.5pt 6pt; font-size: 10.5pt; vertical-align: top; text-align: '.$cellAlign.';';
                            $cells .= '<td style="'.$cellStyle.'">'.$cellContent.'</td>';
                        } else {
                            $cellStyle = $wStyle.'border: none; padding: 2pt 4pt; vertical-align: top;';
                            $cells .= '<td style="'.$cellStyle.'">'.$cellContent.'</td>';
                        }
                    }
                    $tableRows .= '<tr>'.$cells.'</tr>';
                }
                $tblClass = $isTableBordered ? 'lampiran-table' : 'custom-table';
                $bodyHtml .= '<table class="'.$tblClass.'" style="width:100%; border-collapse:collapse; margin: 8pt 0;">'.$tableRows.'</table>';
            } else {
                $hasPageBreak = (bool) preg_match('/<w:br\b[^>]*w:type="page"/i', $elem)
                    || str_contains($elem, '<w:pageBreakBefore');

                $align = 'left';
                if (preg_match('/<w:jc\s+w:val="([^"]+)"/', $elem, $m)) {
                    $align = $m[1] === 'both' ? 'justify' : $m[1];
                }
                preg_match_all('/<w:r\b.*?<\/w:r>/s', $elem, $rMatches);
                $pContent = '';
                foreach ($rMatches[0] as $rXml) {
                    $isBold = str_contains($rXml, '<w:b/>') || str_contains($rXml, '<w:b ');
                    $isUnderline = str_contains($rXml, '<w:u ');
                    $isItalic = str_contains($rXml, '<w:i/>') || str_contains($rXml, '<w:i ');

                    $tImg = '';
                    if (preg_match('/(?:r:embed|r:id)="([^"]+)"/', $rXml, $blipM)) {
                        $bId = $blipM[1];
                        if (isset($imgMap[$bId])) {
                            $tImg = '<img src="'.$imgMap[$bId].'" style="max-height: 70px; width: auto;" />';
                        }
                    }

                    $inner = preg_replace_callback('/(<w:tab\/>|<w:t\b[^>]*>.*?<\/w:t>)/s', function (array $tm): string {
                        if ($tm[0] === '<w:tab/>') {
                            return "\t";
                        }

                        return e(strip_tags($tm[0]));
                    }, $rXml) ?? '';

                    $t = $tImg.(preg_replace('/<[^>]+>/', '', $inner) ?? '');

                    if ($t !== '' && $t !== "\t") {
                        if ($isBold) {
                            $t = '<strong>'.$t.'</strong>';
                        }
                        if ($isUnderline) {
                            $t = '<u>'.$t.'</u>';
                        }
                        if ($isItalic) {
                            $t = '<em>'.$t.'</em>';
                        }
                    }
                    $pContent .= $t;
                }

                $plainCheck = trim(str_replace("\t", '', strip_tags($pContent)));
                if ($plainCheck === '__SIGNATURE_HTML_PLACEHOLDER__') {
                    $bodyHtml .= $signatureHtml;
                    $justEmittedPageBreak = false;

                    continue;
                }

                if ($plainCheck === '' && ! str_contains($pContent, '<img')) {
                    if ($hasPageBreak) {
                        if (! $justEmittedPageBreak) {
                            $bodyHtml .= '<div class="page-break" style="page-break-before: always;"></div>';
                            $justEmittedPageBreak = true;
                        }
                        continue;
                    }
                    if ($justEmittedPageBreak) {
                        continue;
                    }
                    // Skip artificial empty paragraph #0 injected right after KopInjector table
                    if ($idx === 0 && $hasKop && ! $hasEmbeddedKop) {
                        continue;
                    }
                    // Render full 1-line empty paragraph height identical to Microsoft Word
                    $bodyHtml .= '<p style="margin: 0; line-height: 1.35; font-size: 12pt;">&nbsp;</p>';

                    continue;
                }

                // Case A: Indented tab-aligned row (e.g. "\tHari, Tanggal\t: 18 November 2026")
                if (preg_match('/^\t+([^\t:]+)\t*:\s*(.*)$/s', $pContent, $tabMatch)) {
                    $label = trim($tabMatch[1]);
                    $val = trim($tabMatch[2]);
                    $bodyHtml .= <<<HTML
                    <table style="width: 100%; border: none; border-collapse: collapse; margin: 0 0 1.5pt 0;">
                        <tr>
                            <td style="border: none; padding: 0; width: 36pt;"></td>
                            <td style="border: none; padding: 0; width: 92pt; vertical-align: top;">{$label}</td>
                            <td style="border: none; padding: 0; width: 12pt; vertical-align: top;">:</td>
                            <td style="border: none; padding: 0; vertical-align: top;">{$val}</td>
                        </tr>
                    </table>
                    HTML;
                    $justEmittedPageBreak = false;

                    continue;
                }

                // Case B: Metadata tab-aligned row (e.g. "Nomor\t: ...", "Lampiran\t: ...", "Hal\t: ...")
                if (preg_match('/^([^\t:]+)\t+:\s*(.*)$/s', $pContent, $metaMatch)) {
                    $label = trim($metaMatch[1]);
                    $val = trim($metaMatch[2]);
                    $plainLabel = strtolower(trim(strip_tags($label)));
                    if (in_array($plainLabel, ['hal', 'perihal'], true) && ! str_contains($val, '<u>')) {
                        $val = '<span style="font-weight: bold; text-decoration: underline;">'.$val.'</span>';
                    }
                    $bodyHtml .= <<<HTML
                    <table style="width: 100%; border: none; border-collapse: collapse; margin: 0 0 1.5pt 0;">
                        <tr>
                            <td style="border: none; padding: 0; width: 68pt; vertical-align: top;">{$label}</td>
                            <td style="border: none; padding: 0; width: 12pt; vertical-align: top;">:</td>
                            <td style="border: none; padding: 0; vertical-align: top;">{$val}</td>
                        </tr>
                    </table>
                    HTML;
                    $justEmittedPageBreak = false;

                    continue;
                }

                // Case C: Normal paragraph (replace any remaining \t with 36pt tab span)
                $isLampiranHeading = (bool) preg_match('/^Lampiran\s+[IVX]+(?!\s*:)/i', $plainCheck)
                    || str_contains($pContent, '__lampiran_section_start');
                if ($isLampiranHeading) {
                    // Remove trailing empty paragraphs right before lampiran to avoid pushing an empty line
                    $bodyHtml = preg_replace('/(?:<p style="margin: 0; line-height: 1.35; font-size: 12pt;">&nbsp;<\/p>\s*)+$/', '', $bodyHtml) ?? $bodyHtml;
                }

                $shouldBreak = ($hasPageBreak || $isLampiranHeading) && ! $justEmittedPageBreak;
                $pBreakStyle = $shouldBreak ? 'page-break-before: always !important; break-before: page !important; ' : '';
                $pContent = str_replace("\t", '<span style="display:inline-block; width: 36pt;"></span>', $pContent);
                $bodyHtml .= '<p style="'.$pBreakStyle.'text-align:'.$align.'; margin: 0 0 2pt 0; line-height: 1.38;">'.$pContent.'</p>';
                $justEmittedPageBreak = $shouldBreak;
            }
        }

        $hasLampiranInBody = str_contains($bodyHtml, 'Lampiran I')
            || str_contains($bodyHtml, 'Daftar Perlengkapan')
            || str_contains($bodyHtml, 'lampiran-table');

        return $bodyHtml.($signatureReplaced ? '' : $signatureHtml).($hasLampiranInBody ? '' : $page2Html);
    }

    /**
     * Extracts repeater tables from values according to template schema (supporting 2, 3, 4, 5+ custom columns).
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $values
     * @return array<int, array{group: string, label: string, columns: array<int, array{name: string, label: string}>, rows: array<int, array<string, string>>}>
     */
    private function extractRepeaterTables(array $schema, array $values): array
    {
        $schemaRepeaters = [];
        foreach ($schema as $field) {
            if (($field['type'] ?? '') === 'repeater' && ! empty($field['name'])) {
                $schemaRepeaters[$field['name']] = $field;
            }
        }

        $tables = [];
        foreach ($values as $k => $v) {
            if (str_starts_with((string) $k, '_') || ! is_array($v)) {
                continue;
            }

            $validRows = [];
            foreach ($v as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $nonEmpty = array_filter($row, fn ($cell) => trim((string) $cell) !== '');
                if (count($nonEmpty) > 0) {
                    $validRows[] = array_map(fn ($cell) => trim((string) $cell), $row);
                }
            }

            if (count($validRows) === 0) {
                continue;
            }

            $fieldMeta = $schemaRepeaters[$k] ?? null;
            $columns = $fieldMeta['columns'] ?? [];

            if (empty($columns)) {
                foreach (array_keys($validRows[0]) as $colKey) {
                    $columns[] = [
                        'name' => (string) $colKey,
                        'label' => ucwords(str_replace(['_', '-'], ' ', (string) $colKey)),
                    ];
                }
            }

            $colNames = array_column($columns, 'name');
            $isLegacyDefaultLampiran = ($k === 'lampiran' && $colNames === ['nama_barang', 'jumlah']);

            if ($isLegacyDefaultLampiran) {
                $legacyRows = [];
                foreach ($validRows as $r) {
                    $nama = $r['nama_barang'] ?? '';
                    $rawJumlah = $r['jumlah'] ?? '1 Unit';
                    if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(.+)$/u', $rawJumlah, $m)) {
                        $vol = $m[1];
                        $sat = trim($m[2]);
                    } else {
                        $vol = is_numeric($rawJumlah) ? $rawJumlah : '1';
                        $sat = is_numeric($rawJumlah) ? 'Unit' : ($rawJumlah ?: 'Unit');
                    }
                    $legacyRows[] = [
                        'nama_barang' => $nama,
                        'volume' => $vol,
                        'satuan' => $sat,
                    ];
                }

                $tables[] = [
                    'group' => (string) $k,
                    'label' => 'Daftar Perlengkapan',
                    'columns' => [
                        ['name' => 'nama_barang', 'label' => 'Nama Barang'],
                        ['name' => 'volume', 'label' => 'Volume'],
                        ['name' => 'satuan', 'label' => 'Satuan'],
                    ],
                    'rows' => $legacyRows,
                ];
            } else {
                $tables[] = [
                    'group' => (string) $k,
                    'label' => (string) ($fieldMeta['label'] ?? 'Daftar '.ucwords(str_replace(['_', '-'], ' ', (string) $k))),
                    'columns' => $columns,
                    'rows' => $validRows,
                ];
            }
        }

        return $tables;
    }

    /**
     * Builds dynamic multi-column Lampiran pages (supports 3, 4, 5+ custom columns).
     *
     * @param  array<int, array{group: string, label: string, columns: array<int, array{name: string, label: string}>, rows: array<int, array<string, string>>}>  $tables
     */
    private function buildDynamicLampiranPagesHtml(string $nomorSurat, array $tables): string
    {
        $allHtml = '';
        $roman = ['I', 'II', 'III', 'IV', 'V'];

        foreach ($tables as $tIdx => $table) {
            $columns = $table['columns'];
            $firstColName = strtolower((string) ($columns[0]['name'] ?? ''));
            $hasExplicitNoColumn = in_array($firstColName, ['no', 'nomor'], true);

            $headersHtml = '';
            if (! $hasExplicitNoColumn) {
                $headersHtml .= '<th style="width: 10%;">No</th>';
            }

            foreach ($columns as $col) {
                $colLabel = e((string) ($col['label'] ?? $col['name']));
                $headersHtml .= '<th>'.$colLabel.'</th>';
            }

            $rowsHtml = '';
            foreach ($table['rows'] as $rIdx => $row) {
                $cellsHtml = '';
                if (! $hasExplicitNoColumn) {
                    $no = ($rIdx + 1).'.';
                    $cellsHtml .= '<td style="text-align: center;">'.$no.'</td>';
                }

                foreach ($columns as $cIdx => $col) {
                    $cName = (string) $col['name'];
                    $val = trim((string) ($row[$cName] ?? ''));
                    if ($cIdx === 0 && $hasExplicitNoColumn && $val === '') {
                        $val = ($rIdx + 1).'.';
                    }
                    $align = ($cIdx === 0 && $hasExplicitNoColumn) || in_array(strtolower($cName), ['volume', 'satuan', 'jumlah', 'nim', 'nip'], true)
                        ? 'center'
                        : 'left';
                    $cellsHtml .= '<td style="text-align: '.$align.';">'.e($val).'</td>';
                }

                $rowsHtml .= '<tr>'.$cellsHtml.'</tr>';
            }

            $lampiranRoman = $roman[$tIdx] ?? (string) ($tIdx + 1);
            $tableTitle = e($table['label']);

            $allHtml .= <<<HTML
            <div class="lampiran-section" style="page-break-before: always !important; break-before: page !important; clear: both;">
                <p style="font-weight: bold; font-style: italic; margin-top: 0; margin-bottom: 6pt;">Lampiran {$lampiranRoman}</p>
                <p style="margin-top: 0; margin-bottom: 14pt;">Nomor: {$nomorSurat}</p>
                <p style="text-align: center; font-weight: bold; margin-top: 0; margin-bottom: 8pt;">{$tableTitle}</p>
                <table class="lampiran-table">
                    <thead>
                        <tr>
                            {$headersHtml}
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>
            </div>
            HTML;
        }

        return $allHtml;
    }

    private function logoDataUri(?string $relativePath): ?string
    {
        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($relativePath);
        $mime = str_ends_with(strtolower($relativePath), '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            return $carbon->locale('id')->translatedFormat(config('suratinaja.templates.date_format', 'd F Y'));
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function formatDayAndDate(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            return $carbon->locale('id')->translatedFormat('l, d F Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /**
     * Extracts embedded Kop Surat (header text, logos, divider) from a Word .docx header.
     */
    private function extractEmbeddedKopHtml(string $relativeDocxPath): ?string
    {
        $docxAbs = Storage::disk('local')->path($relativeDocxPath);
        if (! file_exists($docxAbs)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxAbs) !== true) {
            return null;
        }

        $docXml = (string) $zip->getFromName('word/document.xml');
        $docRels = (string) $zip->getFromName('word/_rels/document.xml.rels');

        // 1. Locate default header XML
        $targetHeader = null;
        if (preg_match('/<w:headerReference\b[^>]*w:type="default"[^>]*r:id="([^"]+)"/', $docXml, $hm)) {
            if (preg_match('/<Relationship\b[^>]*Id="'.preg_quote($hm[1], '/').'"[^>]*Target="([^"]+)"/', $docRels, $rm)) {
                $targetHeader = $rm[1];
            }
        }

        if (! $targetHeader) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('/^word\/header\d+\.xml$/', $name) && strlen((string) $zip->getFromName($name)) > 1000) {
                    $targetHeader = basename($name);
                    break;
                }
            }
        }

        if (! $targetHeader) {
            $zip->close();

            return null;
        }

        $headerZipPath = 'word/'.ltrim($targetHeader, '/');
        $headerXml = (string) $zip->getFromName($headerZipPath);
        if (strlen($headerXml) < 100) {
            $zip->close();

            return null;
        }

        // Strip mc:Fallback to prevent duplicate content
        $cleanXml = preg_replace('/<mc:Fallback>.*?<\/mc:Fallback>/s', '', $headerXml) ?? $headerXml;

        // 2. Read images from header rels
        $headerRelsPath = 'word/_rels/'.basename($targetHeader).'.rels';
        $headerRels = (string) $zip->getFromName($headerRelsPath);
        preg_match_all('/<Relationship\b[^>]*Id="([^"]+)"[^>]*Type="[^"]*relationships\/image"[^>]*Target="([^"]+)"/', $headerRels, $imgMatches, PREG_SET_ORDER);
        $imgMap = [];
        foreach ($imgMatches as $im) {
            $rId = $im[1];
            $imgTarget = $im[2];
            $imgPath = 'word/'.ltrim(str_replace('../', '', $imgTarget), '/');
            $raw = $zip->getFromName($imgPath);
            if ($raw) {
                $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
                $imgMap[$rId] = 'data:'.$mime.';base64,'.base64_encode($raw);
            }
        }

        // 3. Locate drawings / images with horizontal positioning
        preg_match_all('/<w:drawing\b.*?<\/w:drawing>/s', $cleanXml, $dwMatches);
        $orderedImgs = [];
        foreach ($dwMatches[0] as $dwIdx => $dw) {
            if (preg_match('/(?:r:embed|r:id)="([^"]+)"/', $dw, $bm)) {
                $rId = $bm[1];
                if (isset($imgMap[$rId])) {
                    $offset = 0;
                    if (preg_match('/<wp:positionH\b.*?<wp:posOffset>([-\d]+)<\/wp:posOffset>/s', $dw, $pm)) {
                        $offset = (int) $pm[1];
                    }
                    $orderedImgs[] = ['uri' => $imgMap[$rId], 'offset' => $offset, 'idx' => $dwIdx];
                }
            }
        }

        $leftImg = null;
        $rightImg = null;
        if (count($orderedImgs) === 1) {
            if ($orderedImgs[0]['offset'] > 2500000) {
                $rightImg = $orderedImgs[0]['uri'];
            } else {
                $leftImg = $orderedImgs[0]['uri'];
            }
        } elseif (count($orderedImgs) >= 2) {
            usort($orderedImgs, function ($a, $b) {
                if ($a['offset'] === $b['offset']) {
                    return $a['idx'] <=> $b['idx'];
                }

                return $a['offset'] <=> $b['offset'];
            });
            $leftImg = $orderedImgs[0]['uri'];
            $rightImg = $orderedImgs[count($orderedImgs) - 1]['uri'];
        }

        // 4. Extract paragraphs / text
        preg_match_all('/<w:p\b.*?<\/w:p>/s', $cleanXml, $pMatches);
        $centerHtml = '';
        foreach ($pMatches[0] as $p) {
            $pClean = preg_replace('/<w:drawing\b.*?<\/w:drawing>/s', '', $p) ?? $p;
            preg_match_all('/<w:r\b.*?<\/w:r>/s', $pClean, $rMatches);
            $lineText = '';
            $isBold = false;
            foreach ($rMatches[0] as $r) {
                if (str_contains($r, '<w:b/>') || str_contains($r, '<w:b ')) {
                    $isBold = true;
                }
                if (str_contains($r, '<w:br/>') || str_contains($r, '<w:br ')) {
                    $lineText .= '<br/>';
                }
                if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $r, $tm)) {
                    $lineText .= implode('', $tm[1]);
                }
            }
            $lineClean = trim(strip_tags($lineText));
            if ($lineClean === '') {
                continue;
            }

            $escaped = e($lineClean);
            if (str_contains($lineText, '<br/>')) {
                $parts = array_map('trim', explode('<br/>', $lineText));
                $escaped = implode('<br/>', array_map('e', array_filter($parts)));
            }

            $escaped = preg_replace('/\b((?:https?:\/\/|www\.)[^\s,]+|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/', '<span class="kop-link">$1</span>', $escaped) ?? $escaped;

            $isAddressOrContact = preg_match('/(?:jalan|jl\.|telp|fax|email|www|\.ac\.id|\.go\.id|\.com)/i', $escaped);
            if ($isAddressOrContact) {
                $centerHtml .= '<p class="kop-address-line">'.$escaped.'</p>';
            } else {
                $style = $isBold ? 'font-weight: bold;' : 'font-weight: normal;';
                $centerHtml .= '<p class="kop-header-line" style="'.$style.'">'.$escaped.'</p>';
            }
        }

        $zip->close();

        if ($leftImg === null && $rightImg === null && trim($centerHtml) === '') {
            return null;
        }

        $leftCell = $leftImg ? '<img src="'.$leftImg.'" style="max-width: 84px; max-height: 84px; width: auto; height: auto;" />' : '';
        $rightCell = $rightImg ? '<img src="'.$rightImg.'" style="max-width: 84px; max-height: 84px; width: auto; height: auto;" />' : '';

        return <<<HTML
        <table class="kop-table">
            <tr>
                <td class="kop-logo-cell">{$leftCell}</td>
                <td class="kop-text-cell">{$centerHtml}</td>
                <td class="kop-logo-cell">{$rightCell}</td>
            </tr>
        </table>
        <div class="kop-divider"></div>
        HTML;
    }
}
