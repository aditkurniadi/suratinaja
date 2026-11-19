<?php

namespace App\Services\Documents;

use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class DocxRenderer
{
    public function __construct(
        private readonly KopInjector $kopInjector,
    ) {}

    /**
     * Renders a single Letter into `storage/app/private/generated/{workspace}/{letter}-v{version}.docx`
     * and returns the relative storage path.
     *
     * @throws DomainException
     */
    public function renderSingle(Letter $letter): string
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        $template = $letter->template()->withoutGlobalScopes()->firstOrFail();

        $templateAbsPath = $this->resolveTemplatePath($template);
        $relativeOut = "generated/{$workspace->id}/letter-{$letter->id}-v{$letter->version}.docx";
        $targetAbs = Storage::disk('local')->path($relativeOut);
        $this->ensureDirectory(dirname($targetAbs));

        $workingCopy = tempnam(sys_get_temp_dir(), 'sa_docx_').'.docx';
        copy($templateAbsPath, $workingCopy);

        $values = $letter->field_values ?? [];
        $hasRepeaterRows = $this->hasNonEmptyRepeater($values);
        $hasEmbeddedKop = $template->hasEmbeddedKop();
        $signers = is_array($values['_signers'] ?? null) && count($values['_signers']) > 0
            ? array_values($values['_signers'])
            : $workspace->defaultSigners();

        try {
            PhpWordSettings::setOutputEscapingEnabled(true);
            $this->kopInjector->prepareTemplateCopy($workingCopy, $workspace, $hasEmbeddedKop);

            if (! $hasRepeaterRows) {
                $this->removeLampiranSectionFromXml($workingCopy);
            }

            $this->injectDynamicSignatureTableToXml($workingCopy, $signers);

            $processor = new TemplateProcessor($workingCopy);
            $this->kopInjector->applyValues($processor, $workspace, '', $hasEmbeddedKop);

            $this->applyLetterVariables(
                $processor,
                $template->variables_schema ?? [],
                $values,
                $this->buildSystemValues($workspace, $letter, $values, $hasRepeaterRows)
            );

            $processor->saveAs($targetAbs);
            $this->stripResidualPlaceholders($targetAbs, $hasRepeaterRows);
            $this->ensureLampiranPageBreakInXml($targetAbs);
        } finally {
            @unlink($workingCopy);
        }

        return $relativeOut;
    }

    /**
     * Renders N letters into a single multi-page .docx file (one page per recipient, AC-05-02).
     *
     * @param  array<int, Letter>  $letters
     * @return string Relative storage path
     */
    public function renderBulkDocument(
        Workspace $workspace,
        LetterTemplate $template,
        array $letters,
        string $jobId,
    ): string {
        $templateAbsPath = $this->resolveTemplatePath($template);
        $relativeOut = "generated/{$workspace->id}/{$jobId}.docx";
        $targetAbs = Storage::disk('local')->path($relativeOut);
        $this->ensureDirectory(dirname($targetAbs));

        $count = count($letters);
        if ($count === 0) {
            throw DomainException::bulkJobFailed('Tidak ada penerima untuk digenerate.');
        }

        $workingCopy = tempnam(sys_get_temp_dir(), 'sa_bulk_').'.docx';
        copy($templateAbsPath, $workingCopy);
        $hasEmbeddedKop = $template->hasEmbeddedKop();

        try {
            PhpWordSettings::setOutputEscapingEnabled(true);
            $this->kopInjector->prepareTemplateCopy($workingCopy, $workspace, $hasEmbeddedKop);
            $this->wrapBodyInRepeatableBlock($workingCopy, 'BULK_PAGE');

            $processor = new TemplateProcessor($workingCopy);
            $processor->cloneBlock('BULK_PAGE', $count, true, true);

            $schema = $template->variables_schema ?? [];
            foreach (array_values($letters) as $idx => $letter) {
                $i = $idx + 1;
                $suffix = "#{$i}";
                $values = $letter->field_values ?? [];
                $hasRepeaterRows = $this->hasNonEmptyRepeater($values);

                $this->kopInjector->applyValues($processor, $workspace, $suffix, $hasEmbeddedKop);

                $this->applyLetterVariables(
                    $processor,
                    $schema,
                    $values,
                    $this->buildSystemValues($workspace, $letter, $values, $hasRepeaterRows),
                    $suffix
                );
            }

            $processor->saveAs($targetAbs);
            $this->stripResidualPlaceholders($targetAbs, true);
            $this->ensureLampiranPageBreakInXml($targetAbs);
        } finally {
            @unlink($workingCopy);
        }

        return $relativeOut;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function buildSystemValues(Workspace $workspace, Letter $letter, array $values, bool $hasRepeaterRows): array
    {
        $signers = is_array($values['_signers'] ?? null) && count($values['_signers']) > 0
            ? array_values($values['_signers'])
            : $workspace->defaultSigners();

        $leftSigner = count($signers) >= 2 ? $signers[0] : ['position' => '', 'name' => '', 'id_label' => '', 'id_number' => ''];
        $rightSigner = count($signers) >= 2 ? $signers[1] : ($signers[0] ?? ['position' => '', 'name' => '', 'id_label' => '', 'id_number' => '']);

        $leftId = trim(($leftSigner['id_label'] ?? '').' '.($leftSigner['id_number'] ?? ''));
        $rightId = trim(($rightSigner['id_label'] ?? '').' '.($rightSigner['id_number'] ?? ''));

        $rawEventDate = $values['tanggal_kegiatan'] ?? $letter->letter_date;

        return [
            'nomor_surat' => (string) ($letter->formatted_number ?? ''),
            'perihal' => (string) $letter->perihal,
            'tanggal_surat' => $this->formatDate($letter->letter_date),
            'kota_surat' => (string) ($values['_city'] ?? $workspace->default_city ?: 'Cilacap'),
            'jumlah_lampiran' => $hasRepeaterRows ? 'Satu (1) lembar' : '-',
            'hari_tanggal_kegiatan' => $this->formatDayName($rawEventDate),
            'waktu_kegiatan' => (string) ($values['waktu_kegiatan'] ?? '07.00 s.d. Selesai'),
            'ttd_kiri_jabatan' => (string) ($leftSigner['position'] ?? ''),
            'ttd_kiri_nama' => (string) ($leftSigner['name'] ?? ''),
            'ttd_kiri_id' => $leftId,
            'ttd_kanan_jabatan' => (string) ($rightSigner['position'] ?? ''),
            'ttd_kanan_nama' => (string) ($rightSigner['name'] ?? ''),
            'ttd_kanan_id' => $rightId,
            'signature_block' => '',
            'tanda_tangan' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function hasNonEmptyRepeater(array $values): bool
    {
        foreach ($values as $k => $v) {
            if (str_starts_with((string) $k, '_') || ! is_array($v)) {
                continue;
            }
            $nonEmpty = array_filter(
                $v,
                fn ($r) => is_array($r) && count(array_filter($r, fn ($cell) => trim((string) $cell) !== '')) > 0
            );
            if (count($nonEmpty) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $systemValues
     */
    private function applyLetterVariables(
        TemplateProcessor $processor,
        array $schema,
        array $values,
        array $systemValues,
        string $suffix = '',
    ): void {
        foreach ($systemValues as $sysKey => $sysVal) {
            $processor->setValue($sysKey.$suffix, $sysVal);
        }

        $handledKeys = array_keys($systemValues);

        foreach ($schema as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? '';
            if ($name === '' || $type === 'system') {
                continue;
            }

            $handledKeys[] = $name;

            if ($type === 'repeater') {
                $this->applyRepeater($processor, $field, $values[$name] ?? [], $suffix);
                continue;
            }

            $raw = $values[$name] ?? '';
            if (is_array($raw)) {
                $raw = implode(', ', array_map('strval', $raw));
            }
            $formatted = $type === 'date' ? $this->formatDate($raw) : (string) $raw;
            $processor->setValue($name.$suffix, $formatted);
        }

        // Also apply any extra scalar keys passed in $values that were not in schema
        foreach ($values as $k => $v) {
            if (str_starts_with((string) $k, '_') || in_array($k, $handledKeys, true) || is_array($v)) {
                continue;
            }
            $processor->setValue($k.$suffix, (string) $v);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $rows
     */
    private function applyRepeater(
        TemplateProcessor $processor,
        array $field,
        mixed $rows,
        string $suffix = '',
    ): void {
        $group = $field['name'];
        $columns = $field['columns'] ?? [];
        if (empty($columns)) {
            return;
        }

        $firstToken = ($columns[0]['token'] ?? "{$group}.{$columns[0]['name']}").$suffix;
        $rows = is_array($rows)
            ? array_values(array_filter($rows, fn ($r) => is_array($r) && count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0))
            : [];

        if (empty($rows)) {
            $policy = config('suratinaja.templates.empty_repeater', 'delete_row');
            try {
                if ($policy === 'delete_row') {
                    $processor->deleteRow($firstToken);
                } else {
                    foreach ($columns as $col) {
                        $token = ($col['token'] ?? "{$group}.{$col['name']}").$suffix;
                        $processor->setValue($token, '-');
                    }
                    $processor->setValue('lampiran_no'.$suffix, '-');
                    $processor->setValue('lampiran_volume'.$suffix, '-');
                }
            } catch (Throwable) {
                foreach ($columns as $col) {
                    $token = ($col['token'] ?? "{$group}.{$col['name']}").$suffix;
                    $processor->setValue($token, '');
                }
                $processor->setValue('lampiran_no'.$suffix, '');
                $processor->setValue('lampiran_volume'.$suffix, '');
            }

            return;
        }

        $colNames = array_column($columns, 'name');
        $isLegacyDefaultLampiran = ($group === 'lampiran' && $colNames === ['nama_barang', 'jumlah']);

        $cloneValues = [];
        foreach (array_values($rows) as $rIdx => $row) {
            $mapped = [
                'lampiran_no'.$suffix => ($rIdx + 1).'.',
            ];

            foreach ($columns as $col) {
                $colName = $col['name'];
                $token = ($col['token'] ?? "{$group}.{$colName}").$suffix;
                $rawVal = trim((string) ($row[$colName] ?? ''));

                if (in_array(strtolower($colName), ['no', 'nomor'], true) && $rawVal === '') {
                    $rawVal = ($rIdx + 1).'.';
                }

                if ($isLegacyDefaultLampiran && $colName === 'jumlah') {
                    [$vol, $sat] = $this->splitVolumeAndSatuan($rawVal);
                    $mapped['lampiran_volume'.$suffix] = $vol;
                    $mapped[$token] = $sat;
                } else {
                    $mapped[$token] = $rawVal;
                }
            }

            if (! isset($mapped['lampiran_volume'.$suffix])) {
                $mapped['lampiran_volume'.$suffix] = (string) ($row['volume'] ?? '1');
            }

            $cloneValues[] = $mapped;
        }

        try {
            $processor->cloneRowAndSetValues($firstToken, $cloneValues);
        } catch (Throwable) {
            $first = $cloneValues[0] ?? [];
            foreach ($first as $token => $val) {
                $processor->setValue($token, $val);
            }
        }
    }

    /**
     * Splits a combined quantity string like "2 Unit" or "1 Set" into ["2", "Unit"].
     *
     * @return array{0: string, 1: string}
     */
    private function splitVolumeAndSatuan(string $raw): array
    {
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(.+)$/u', $raw, $m)) {
            return [$m[1], trim($m[2])];
        }
        if (is_numeric($raw)) {
            return [$raw, 'Unit'];
        }

        return ['1', $raw !== '' ? $raw : 'Unit'];
    }

    /**
     * Removes the entire Page 2 Lampiran block (including preceding page break) when the letter has no repeater rows.
     */
    private function removeLampiranSectionFromXml(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml !== false && str_contains($xml, '${__lampiran_section_start}')) {
            $cleaned = preg_replace(
                '/(?:<w:p\b[^>]*>(?:(?!<\/w:p>).)*<w:br\s+w:type="page"\s*\/>.*?<\/w:p>)?\s*<w:p\b[^>]*>(?:(?!<\/w:p>).)*\$\{__lampiran_section_start\}.*?\$\{__lampiran_section_end\}.*?<\/w:p>/s',
                '',
                $xml
            );
            if ($cleaned !== null) {
                $zip->addFromString('word/document.xml', $cleaned);
            }
        }

        $zip->close();
    }

    /**
     * Replaces the static 2-cell signature table or `${signature_block}` / `${tanda_tangan}` paragraph
     * with a dynamic multi-tier signature table XML.
     *
     * @param  array<int, array{prefix?: string, position?: string, name?: string, id_label?: string, id_number?: string, layout?: string}>  $signers
     */
    private function injectDynamicSignatureTableToXml(string $docxPath, array $signers): void
    {
        if (empty($signers)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $tableXml = $this->buildSignatureTableXml($signers);
        $newXml = null;

        if (preg_match('/<w:tbl\b(?:(?!<\/w:tbl>).)*?ttd_kiri.*?<\/w:tbl>/s', $xml)) {
            $newXml = preg_replace('/<w:tbl\b(?:(?!<\/w:tbl>).)*?ttd_kiri.*?<\/w:tbl>/s', $tableXml, $xml, 1);
        } elseif (preg_match('/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?(?:signature_block|tanda_tangan).*?<\/w:p>/s', $xml)) {
            $newXml = preg_replace('/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?(?:signature_block|tanda_tangan).*?<\/w:p>/s', $tableXml, $xml, 1);
        }

        if ($newXml !== null) {
            $zip->addFromString('word/document.xml', $newXml);
        }
        $zip->close();
    }

    /**
     * @param  array<int, array{prefix?: string, position?: string, name?: string, id_label?: string, id_number?: string, layout?: string}>  $signers
     */
    private function buildSignatureTableXml(array $signers): string
    {
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

        $currentTableRowsXml = '';

        foreach ($rows as $rowMeta) {
            $rType = $rowMeta['type'];
            $rSigners = $rowMeta['signers'];

            $currentTableRowsXml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';

            if ($rType === 'single') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $currentTableRowsXml .= '<w:tc><w:tcPr><w:tcW w:w="4510" w:type="dxa"/><w:noWrap/></w:tcPr><w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p></w:tc>';
                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefix, $pos, $name, $idLine);
            } elseif ($rType === 'center') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $currentTableRowsXml .= $this->buildDocxSignerCellXml(9020, $prefix, $pos, $name, $idLine, 2);
            } else {
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

                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefixLeft, $posLeft, $nameLeft, $idLineLeft);
                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefixRight, $posRight, $nameRight, $idLineRight);
            }

            $currentTableRowsXml .= '</w:tr>';
        }

        return $currentTableRowsXml !== '' ? $this->wrapDocxRowsInTable($currentTableRowsXml) : '';
    }

    private function wrapDocxRowsInTable(string $rowsXml): string
    {
        return '<w:tbl>'
            .'<w:tblGrid><w:gridCol w:w="4510" w:type="dxa"/><w:gridCol w:w="4510" w:type="dxa"/></w:tblGrid>'
            .'<w:tblPr>'
            .'<w:tblW w:w="9020" w:type="dxa"/>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar><w:top w:w="20" w:type="dxa"/><w:bottom w:w="20" w:type="dxa"/></w:tblCellMar>'
            .'<w:tblBorders>'
            .'<w:top w:val="none"/><w:left w:val="none"/><w:right w:val="none"/><w:bottom w:val="none"/><w:insideH w:val="none"/><w:insideV w:val="none"/>'
            .'</w:tblBorders>'
            .'</w:tblPr>'
            .$rowsXml
            .'</w:tbl>';
    }

    private function buildDocxSignerCellXml(int $width, string $prefix, string $pos, string $name, string $idLine, int $gridSpan = 1): string
    {
        $prefixEsc = htmlspecialchars($prefix, ENT_XML1, 'UTF-8');
        $posEsc = htmlspecialchars($pos, ENT_XML1, 'UTF-8');
        $nameEsc = htmlspecialchars($name, ENT_XML1, 'UTF-8');
        $idLineEsc = htmlspecialchars($idLine, ENT_XML1, 'UTF-8');

        $gridSpanXml = $gridSpan > 1 ? '<w:gridSpan w:val="'.$gridSpan.'"/>' : '';

        $prefixP = $prefix !== ''
            ? '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'.$prefixEsc.'</w:t></w:r></w:p>'
            : '';

        $posP = '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="1240"/></w:pPr><w:r><w:rPr/><w:t xml:space="preserve">'.$posEsc.'</w:t></w:r></w:p>';

        $nameP = '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="20"/></w:pPr><w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t xml:space="preserve">'.$nameEsc.'</w:t></w:r></w:p>';

        $idP = $idLine !== ''
            ? '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="200"/></w:pPr><w:r><w:rPr/><w:t xml:space="preserve">'.$idLineEsc.'</w:t></w:r></w:p>'
            : '';

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$gridSpanXml.'<w:noWrap/><w:vAlign w:val="top"/></w:tcPr>'
            .$prefixP.$posP.$nameP.$idP
            .'</w:tc>';
    }

    private function wrapBodyInRepeatableBlock(string $docxPath, string $blockName): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $openTag = '<w:p><w:r><w:t>${'.$blockName.'}</w:t></w:r></w:p>';
        $pageBreakAndClose = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
            .'<w:p><w:r><w:t>${/'.$blockName.'}</w:t></w:r></w:p>';

        $xml = preg_replace('/(<w:body[^>]*>)/', '$1'.$openTag, $xml, 1);
        if (str_contains((string) $xml, '<w:sectPr')) {
            $xml = preg_replace('/(<w:sectPr\b)/', $pageBreakAndClose.'$1', (string) $xml, 1);
        } else {
            $xml = str_replace('</w:body>', $pageBreakAndClose.'</w:body>', (string) $xml);
        }

        $zip->addFromString('word/document.xml', (string) $xml);
        $zip->close();
    }

    /**
     * Guarantees AC-03-04: any unreferenced ${...} tokens remaining in document.xml are cleared.
     */
    private function stripResidualPlaceholders(string $docxPath, bool $hasRepeaterRows = true): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml !== false) {
            // Remove marker paragraphs for __lampiran_section_start and __lampiran_section_end
            $cleaned = preg_replace(
                '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*\$\{__lampiran_section_(?:start|end)\}.*?<\/w:p>/s',
                '',
                $xml
            ) ?? $xml;

            $cleaned = preg_replace('/\$\{[^}]+\}/', '', $cleaned) ?? $cleaned;

            if (! $hasRepeaterRows && config('suratinaja.templates.empty_repeater', 'delete_row') === 'delete_row') {
                $cleaned = (string) preg_replace_callback(
                    '/(?:<w:p\/>)?<w:p\b[^>]*>(?:(?!<\/w:p>).)*Daftar (?:Lampiran|Perlengkapan)[^<]*<\/w:t><\/w:r><\/w:p>\s*<w:tbl\b[^>]*>.*?<\/w:tbl>/s',
                    function (array $m): string {
                        $chunk = $m[0];
                        if (str_contains($chunk, 'w:val="double"') || str_contains($chunk, 'NIM.') || str_contains($chunk, 'NIP.') || str_contains($chunk, 'Menyetujui') || str_contains($chunk, 'Mengetahui')) {
                            return $chunk;
                        }
                        if (preg_match('/<w:tbl\b[^>]*>.*?<\/w:tbl>/s', $chunk, $tblMatch)) {
                            if (substr_count($tblMatch[0], '</w:tr>') <= 1) {
                                return '';
                            }
                        }

                        return $chunk;
                    },
                    $cleaned
                );
            }

            // Normalize legacy template page margins (1134/1418 twips) to standard Word "Normal" margin (1440 twips = 2.54 cm / 1 inch)
            $cleaned = str_replace(
                'w:top="1134" w:right="1134" w:bottom="1134" w:left="1418"',
                'w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"',
                $cleaned
            );

            $zip->addFromString('word/document.xml', $cleaned);
        }
        $zip->close();
    }

    private function resolveTemplatePath(LetterTemplate $template): string
    {
        if (! Storage::disk('local')->exists($template->file_path)) {
            throw DomainException::templateInvalid('File master template .docx tidak ditemukan di storage.');
        }

        return Storage::disk('local')->path($template->file_path);
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

    private function formatDayName(mixed $value): string
    {
        if (empty($value)) {
            return 'Sabtu';
        }
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            return $carbon->locale('id')->translatedFormat('l');
        } catch (Throwable) {
            return 'Sabtu';
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /**
     * Ensures clean page break before the Lampiran section in DOCX XML:
     * 1. Strips any trailing empty paragraphs and standalone <w:br w:type="page"/> between the signature table and Lampiran.
     * 2. Injects <w:pageBreakBefore/> on the Lampiran paragraph or ensures Page 2 Kop starts with <w:pageBreakBefore/>.
     */
    private function ensureLampiranPageBreakInXml(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        if (! preg_match('/(?:Lampiran\s+[IVX]+|\$\{__lampiran_section_start\})/i', $xml)) {
            $zip->close();

            return;
        }

        // Clean up any standalone empty paragraphs or manual page breaks right before Lampiran I or __lampiran_section_start
        $cleanPattern = '/(?:<w:p\b[^>]*>(?:(?!<\/w:p>).)*<w:br\b[^>]*w:type="page"[^>]*>.*?<\/w:p>\s*|<w:p\b[^>]*>(?:<w:pPr>.*?<\/w:pPr>)?\s*<\/w:p>\s*)+(?=<w:p\b[^>]*>(?:(?!<\/w:p>).)*(?:\$\{__lampiran_section_start\}|Lampiran\s+[IVX]+(?!\s*:)))/is';
        $xml = preg_replace($cleanPattern, '', $xml) ?? $xml;

        // Ensure paragraph containing Lampiran [Roman] has <w:pageBreakBefore/> if not already preceded by Page 2 Kop
        if (! str_contains($xml, '__kop_p2')) {
            $xml = preg_replace_callback(
                '/<w:p\b([^>]*)>((?:(?!<\/w:p>).)*?\bLampiran\s+[IVX]+(?!\s*:).*?<\/w:p>)/is',
                function (array $m): string {
                    $full = $m[0];
                    if (str_contains($full, '<w:pageBreakBefore')) {
                        return $full;
                    }
                    if (preg_match('/<w:pPr\b[^>]*>/', $full)) {
                        return preg_replace('/(<w:pPr\b[^>]*>)/', '$1<w:pageBreakBefore/>', $full, 1) ?? $full;
                    }

                    return preg_replace('/<w:p\b([^>]*)>/', '<w:p$1><w:pPr><w:pageBreakBefore/></w:pPr>', $full, 1) ?? $full;
                },
                $xml
            ) ?? $xml;
        }

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }
}
