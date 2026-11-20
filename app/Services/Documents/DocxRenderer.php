<?php

namespace App\Services\Documents;

use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Workspace;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class DocxRenderer
{
    /**
     * Number of single-spaced empty paragraphs inside each signature cell (~60-63pt / ~2.2 cm at 12pt)
     * to provide comfortable wet signature & stamp clearance without requiring manual Enter presses.
     */
    private const SIGNATURE_BLANK_LINES = 4;

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
     * with a dynamic multi-tier signature table XML that inherits the template placeholder's typography.
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

        $pattern = null;
        $matchedXml = null;

        if (preg_match('/<w:tbl\b(?:(?!<\/w:tbl>).)*?ttd_kiri.*?<\/w:tbl>/s', $xml, $match)) {
            $pattern = '/<w:tbl\b(?:(?!<\/w:tbl>).)*?ttd_kiri.*?<\/w:tbl>/s';
            $matchedXml = $match[0];
        } elseif (preg_match('/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?(?:signature_block|tanda_tangan).*?<\/w:p>/s', $xml, $match)) {
            $pattern = '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?(?:signature_block|tanda_tangan).*?<\/w:p>/s';
            $matchedXml = $match[0];
        }

        if ($pattern !== null && $matchedXml !== null) {
            $stylesXml = $zip->getFromName('word/styles.xml');
            $fontStyle = $this->extractSignatureStyleFromTemplate(
                $matchedXml,
                $xml,
                is_string($stylesXml) ? $stylesXml : null
            );
            $tableXml = $this->buildSignatureTableXml($signers, $fontStyle);
            $newXml = preg_replace_callback($pattern, fn (): string => $tableXml, $xml, 1);

            if ($newXml !== null) {
                $zip->addFromString('word/document.xml', $newXml);
            }
        }
        $zip->close();
    }

    /**
     * Extracts effective typography (rFonts, sz, szCs, color, lang) from the matched
     * signature placeholder block following the OOXML style inheritance hierarchy:
     *   1. Direct run <w:rPr> on the placeholder run
     *   2. Character style (<w:rStyle>) + <w:basedOn> chain in styles.xml
     *   3. Paragraph default <w:pPr>/<w:rPr>
     *   4. Paragraph style (<w:pStyle>) + <w:basedOn> chain in styles.xml
     *   5. Default paragraph style (w:type="paragraph" w:default="1") in styles.xml
     *   6. Document defaults (<w:docDefaults>/<w:rPrDefault>/<w:rPr>) in styles.xml
     *
     * @return array{rFonts?: string, sz?: string, szCs?: string, color?: string, lang?: string}
     */
    private function extractSignatureStyleFromTemplate(
        string $matchedBlockXml,
        string $fullDocumentXml,
        ?string $stylesXml,
    ): array {
        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $docOpenTag = '<w:document xmlns:w="'.$wNs.'">';
        if (preg_match('/<w:document\b[^>]*>/', $fullDocumentXml, $docOpenMatch)) {
            $docOpenTag = $docOpenMatch[0];
        }

        $docDom = new DOMDocument;
        $prevUseErrors = libxml_use_internal_errors(true);
        $loadedDoc = $docDom->loadXML($docOpenTag.'<w:body>'.$matchedBlockXml.'</w:body></w:document>', LIBXML_NONET);
        libxml_clear_errors();

        if (! $loadedDoc) {
            libxml_use_internal_errors($prevUseErrors);

            return [];
        }

        $docXpath = new DOMXPath($docDom);
        $docXpath->registerNamespace('w', $wNs);

        // Locate target <w:p> containing the signature placeholder (or first <w:p> as fallback)
        $targetParagraph = null;
        $paragraphs = $docXpath->query('//w:p');
        if ($paragraphs !== false) {
            foreach ($paragraphs as $pNode) {
                if (! $pNode instanceof DOMElement) {
                    continue;
                }
                if (preg_match('/signature_block|tanda_tangan|ttd_kiri_jabatan|ttd_kiri/i', $pNode->textContent)) {
                    $targetParagraph = $pNode;
                    break;
                }
            }
            if ($targetParagraph === null && $paragraphs->length > 0 && $paragraphs->item(0) instanceof DOMElement) {
                $targetParagraph = $paragraphs->item(0);
            }
        }

        // Parse word/styles.xml if present
        $stylesById = [];
        $defaultParagraphStyleId = null;
        $docDefaultsRPr = null;

        if ($stylesXml !== null && $stylesXml !== '') {
            $stylesDom = new DOMDocument;
            $loadedStyles = $stylesDom->loadXML($stylesXml, LIBXML_NONET);
            libxml_clear_errors();

            if ($loadedStyles) {
                $stylesXpath = new DOMXPath($stylesDom);
                $stylesXpath->registerNamespace('w', $wNs);

                $styleNodes = $stylesXpath->query('/w:styles/w:style');
                if ($styleNodes !== false) {
                    foreach ($styleNodes as $styleNode) {
                        if (! $styleNode instanceof DOMElement) {
                            continue;
                        }
                        $styleId = $styleNode->getAttributeNS($wNs, 'styleId') ?: $styleNode->getAttribute('w:styleId');
                        if ($styleId === '') {
                            continue;
                        }
                        $stylesById[$styleId] = $styleNode;

                        $styleType = $styleNode->getAttributeNS($wNs, 'type') ?: $styleNode->getAttribute('w:type');
                        $isDefault = $styleNode->getAttributeNS($wNs, 'default') ?: $styleNode->getAttribute('w:default');
                        if ($styleType === 'paragraph' && ($isDefault === '1' || strtolower($isDefault) === 'true')) {
                            $defaultParagraphStyleId = $styleId;
                        }
                    }
                }

                $docDefNodes = $stylesXpath->query('/w:styles/w:docDefaults/w:rPrDefault/w:rPr');
                if ($docDefNodes !== false && $docDefNodes->length > 0 && $docDefNodes->item(0) instanceof DOMElement) {
                    $docDefaultsRPr = $docDefNodes->item(0);
                }
            }
        }

        libxml_use_internal_errors($prevUseErrors);

        /** @var array<int, DOMElement> $rPrChain */
        $rPrChain = [];

        $appendStyleChain = function (?string $startStyleId) use (&$rPrChain, $stylesById, $wNs): void {
            $visited = [];
            $currentId = $startStyleId;
            $depth = 0;

            while ($currentId !== null && $currentId !== '' && ! isset($visited[$currentId]) && $depth < 10) {
                $visited[$currentId] = true;
                $depth++;

                $styleEl = $stylesById[$currentId] ?? null;
                if (! $styleEl instanceof DOMElement) {
                    break;
                }

                foreach ($styleEl->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->localName === 'rPr' && $child->namespaceURI === $wNs) {
                        $rPrChain[] = $child;
                        break;
                    }
                }

                $nextId = null;
                foreach ($styleEl->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->localName === 'basedOn' && $child->namespaceURI === $wNs) {
                        $nextId = $child->getAttributeNS($wNs, 'val') ?: $child->getAttribute('w:val');
                        break;
                    }
                }
                $currentId = ($nextId !== null && $nextId !== '') ? $nextId : null;
            }
        };

        $pStyleId = null;
        if ($targetParagraph instanceof DOMElement) {
            // 1. Direct run <w:rPr> from the run containing the placeholder token (or first non-empty text run)
            $targetRun = null;
            $firstTextRun = null;
            $runNodes = $docXpath->query('./w:r', $targetParagraph);
            if ($runNodes !== false) {
                foreach ($runNodes as $rNode) {
                    if (! $rNode instanceof DOMElement) {
                        continue;
                    }
                    $runText = $rNode->textContent;
                    if ($firstTextRun === null && trim($runText) !== '') {
                        $firstTextRun = $rNode;
                    }
                    if (preg_match('/signature_block|tanda_tangan|ttd_kiri/i', $runText)) {
                        $targetRun = $rNode;
                        break;
                    }
                }
            }
            $targetRun ??= $firstTextRun;

            $rStyleId = null;
            if ($targetRun instanceof DOMElement) {
                $runRPrNodes = $docXpath->query('./w:rPr', $targetRun);
                if ($runRPrNodes !== false && $runRPrNodes->length > 0 && $runRPrNodes->item(0) instanceof DOMElement) {
                    $runRPr = $runRPrNodes->item(0);
                    $rPrChain[] = $runRPr;

                    $rStyleNodes = $docXpath->query('./w:rStyle', $runRPr);
                    if ($rStyleNodes !== false && $rStyleNodes->length > 0 && $rStyleNodes->item(0) instanceof DOMElement) {
                        $rStyleVal = $rStyleNodes->item(0)->getAttributeNS($wNs, 'val') ?: $rStyleNodes->item(0)->getAttribute('w:val');
                        if ($rStyleVal !== '') {
                            $rStyleId = $rStyleVal;
                        }
                    }
                }
            }

            // 2. Character style (<w:rStyle>) + basedOn chain
            if ($rStyleId !== null) {
                $appendStyleChain($rStyleId);
            }

            // 3. Paragraph-level <w:pPr>/<w:rPr> and <w:pStyle>
            $pPrNodes = $docXpath->query('./w:pPr', $targetParagraph);
            if ($pPrNodes !== false && $pPrNodes->length > 0 && $pPrNodes->item(0) instanceof DOMElement) {
                $pPrEl = $pPrNodes->item(0);

                $pRPrNodes = $docXpath->query('./w:rPr', $pPrEl);
                if ($pRPrNodes !== false && $pRPrNodes->length > 0 && $pRPrNodes->item(0) instanceof DOMElement) {
                    $rPrChain[] = $pRPrNodes->item(0);
                }

                $pStyleNodes = $docXpath->query('./w:pStyle', $pPrEl);
                if ($pStyleNodes !== false && $pStyleNodes->length > 0 && $pStyleNodes->item(0) instanceof DOMElement) {
                    $val = $pStyleNodes->item(0)->getAttributeNS($wNs, 'val') ?: $pStyleNodes->item(0)->getAttribute('w:val');
                    if ($val !== '') {
                        $pStyleId = $val;
                    }
                }
            }
        }

        // 4 & 5. Paragraph style (<w:pStyle>) or default paragraph style (Normal) + basedOn chain
        $appendStyleChain($pStyleId ?? $defaultParagraphStyleId);

        // 6. Document defaults (<w:docDefaults>/<w:rPrDefault>/<w:rPr>)
        if ($docDefaultsRPr instanceof DOMElement) {
            $rPrChain[] = $docDefaultsRPr;
        }

        // Resolve each typography property from highest to lowest priority
        $resolved = [];

        foreach ($rPrChain as $rPrEl) {
            foreach ($rPrEl->childNodes as $child) {
                if (! $child instanceof DOMElement || $child->namespaceURI !== $wNs) {
                    continue;
                }

                if ($child->localName === 'rFonts' && ! isset($resolved['rFonts'])) {
                    $ascii = $child->getAttributeNS($wNs, 'ascii') ?: $child->getAttribute('w:ascii');
                    $hAnsi = $child->getAttributeNS($wNs, 'hAnsi') ?: $child->getAttribute('w:hAnsi');
                    $cs = $child->getAttributeNS($wNs, 'cs') ?: $child->getAttribute('w:cs');
                    $eastAsia = $child->getAttributeNS($wNs, 'eastAsia') ?: $child->getAttribute('w:eastAsia');

                    // Require Latin font (ascii or hAnsi) so a lone w:cs run doesn't hijack inherited Latin font
                    $primaryFont = $ascii !== '' ? $ascii : $hAnsi;
                    if ($primaryFont !== '') {
                        $asciiVal = htmlspecialchars($ascii !== '' ? $ascii : $primaryFont, ENT_XML1, 'UTF-8');
                        $hAnsiVal = htmlspecialchars($hAnsi !== '' ? $hAnsi : $primaryFont, ENT_XML1, 'UTF-8');
                        $csVal = htmlspecialchars($cs !== '' ? $cs : $primaryFont, ENT_XML1, 'UTF-8');
                        $eastAsiaVal = htmlspecialchars($eastAsia !== '' ? $eastAsia : $primaryFont, ENT_XML1, 'UTF-8');
                        $resolved['rFonts'] = '<w:rFonts w:ascii="'.$asciiVal.'" w:hAnsi="'.$hAnsiVal.'" w:cs="'.$csVal.'" w:eastAsia="'.$eastAsiaVal.'"/>';
                    } else {
                        $themeAttrs = [];
                        foreach (['asciiTheme', 'hAnsiTheme', 'eastAsiaTheme', 'cstheme'] as $tAttr) {
                            $tVal = $child->getAttributeNS($wNs, $tAttr) ?: $child->getAttribute('w:'.$tAttr);
                            if ($tVal !== '') {
                                $themeAttrs[] = 'w:'.$tAttr.'="'.htmlspecialchars($tVal, ENT_XML1, 'UTF-8').'"';
                            }
                        }
                        if ($themeAttrs !== []) {
                            $resolved['rFonts'] = '<w:rFonts '.implode(' ', $themeAttrs).'/>';
                        }
                    }
                } elseif ($child->localName === 'sz' && ! isset($resolved['sz'])) {
                    $szVal = $child->getAttributeNS($wNs, 'val') ?: $child->getAttribute('w:val');
                    if ($szVal !== '' && ctype_digit($szVal)) {
                        $resolved['sz'] = $szVal;
                    }
                } elseif ($child->localName === 'szCs' && ! isset($resolved['szCs'])) {
                    $szCsVal = $child->getAttributeNS($wNs, 'val') ?: $child->getAttribute('w:val');
                    if ($szCsVal !== '' && ctype_digit($szCsVal)) {
                        $resolved['szCs'] = $szCsVal;
                    }
                } elseif ($child->localName === 'color' && ! isset($resolved['color'])) {
                    $colorVal = $child->getAttributeNS($wNs, 'val') ?: $child->getAttribute('w:val');
                    if ($colorVal !== '' && strtolower($colorVal) !== 'auto') {
                        $resolved['color'] = $colorVal;
                    }
                } elseif ($child->localName === 'lang' && ! isset($resolved['lang'])) {
                    $langAttrs = [];
                    foreach (['val', 'eastAsia', 'bidi'] as $lAttr) {
                        $lVal = $child->getAttributeNS($wNs, $lAttr) ?: $child->getAttribute('w:'.$lAttr);
                        if ($lVal !== '') {
                            $langAttrs[] = 'w:'.$lAttr.'="'.htmlspecialchars($lVal, ENT_XML1, 'UTF-8').'"';
                        }
                    }
                    if ($langAttrs !== []) {
                        $resolved['lang'] = '<w:lang '.implode(' ', $langAttrs).'/>';
                    }
                }
            }

            // Prevent lower-priority styles (e.g. Normal szCs=22) from leaking when a higher-priority level sets sz without szCs
            if (isset($resolved['sz']) && ! isset($resolved['szCs'])) {
                $resolved['szCs'] = $resolved['sz'];
            }
        }

        return $resolved;
    }

    /**
     * Builds an ECMA-376 schema-ordered <w:rPr> XML string from resolved template typography.
     *
     * @param  array{rFonts?: string, sz?: string, szCs?: string, color?: string, lang?: string}  $fontStyle
     */
    private function buildSignatureRunPrXml(array $fontStyle, bool $bold = false, bool $underline = false): string
    {
        $parts = '';

        if (! empty($fontStyle['rFonts'])) {
            $parts .= $fontStyle['rFonts'];
        }
        if ($bold) {
            $parts .= '<w:b/><w:bCs/>';
        }
        if (! empty($fontStyle['color'])) {
            $parts .= '<w:color w:val="'.htmlspecialchars($fontStyle['color'], ENT_XML1, 'UTF-8').'"/>';
        }
        if (! empty($fontStyle['sz'])) {
            $parts .= '<w:sz w:val="'.htmlspecialchars($fontStyle['sz'], ENT_XML1, 'UTF-8').'"/>';
        }
        if (! empty($fontStyle['szCs'])) {
            $parts .= '<w:szCs w:val="'.htmlspecialchars($fontStyle['szCs'], ENT_XML1, 'UTF-8').'"/>';
        }
        if ($underline) {
            $parts .= '<w:u w:val="single"/>';
        }
        if (! empty($fontStyle['lang'])) {
            $parts .= $fontStyle['lang'];
        }

        return $parts !== '' ? '<w:rPr>'.$parts.'</w:rPr>' : '<w:rPr/>';
    }

    /**
     * @param  array<int, array{prefix?: string, position?: string, name?: string, id_label?: string, id_number?: string, layout?: string}>  $signers
     * @param  array{rFonts?: string, sz?: string, szCs?: string, color?: string, lang?: string}  $fontStyle
     */
    private function buildSignatureTableXml(array $signers, array $fontStyle = []): string
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

        $baseRPr = $this->buildSignatureRunPrXml($fontStyle);
        $pPrRPr = $baseRPr !== '<w:rPr/>' ? $baseRPr : '';
        $currentTableRowsXml = '';
        $rowCount = count($rows);

        foreach ($rows as $rowIndex => $rowMeta) {
            $rType = $rowMeta['type'];
            $rSigners = $rowMeta['signers'];
            $hasNextRow = $rowIndex < ($rowCount - 1);

            $currentTableRowsXml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';

            if ($rType === 'single') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $currentTableRowsXml .= '<w:tc><w:tcPr><w:tcW w:w="4510" w:type="dxa"/></w:tcPr><w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'.$pPrRPr.'</w:pPr></w:p></w:tc>';
                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefix, $pos, $name, $idLine, 1, $fontStyle, false, $hasNextRow);
            } elseif ($rType === 'center') {
                $s = $rSigners[0];
                $prefix = trim((string) ($s['prefix'] ?? ''));
                $pos = trim((string) ($s['position'] ?? ''));
                $name = trim((string) ($s['name'] ?? ''));
                $idLine = trim(($s['id_label'] ?? '').' '.($s['id_number'] ?? ''));

                $currentTableRowsXml .= $this->buildDocxSignerCellXml(9020, $prefix, $pos, $name, $idLine, 2, $fontStyle, false, $hasNextRow);
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

                $rowHasPrefix = $prefixLeft !== '' || $prefixRight !== '';

                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefixLeft, $posLeft, $nameLeft, $idLineLeft, 1, $fontStyle, $rowHasPrefix, $hasNextRow);
                $currentTableRowsXml .= $this->buildDocxSignerCellXml(4510, $prefixRight, $posRight, $nameRight, $idLineRight, 1, $fontStyle, $rowHasPrefix, $hasNextRow);
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

    /**
     * @param  array{rFonts?: string, sz?: string, szCs?: string, color?: string, lang?: string}  $fontStyle
     */
    private function buildDocxSignerCellXml(
        int $width,
        string $prefix,
        string $pos,
        string $name,
        string $idLine,
        int $gridSpan = 1,
        array $fontStyle = [],
        bool $reservePrefixSlot = false,
        bool $hasNextRow = false,
    ): string {
        $prefixEsc = htmlspecialchars($prefix, ENT_XML1, 'UTF-8');
        $posEsc = htmlspecialchars($pos, ENT_XML1, 'UTF-8');
        $nameEsc = htmlspecialchars($name, ENT_XML1, 'UTF-8');
        $idLineEsc = htmlspecialchars($idLine, ENT_XML1, 'UTF-8');

        $gridSpanXml = $gridSpan > 1 ? '<w:gridSpan w:val="'.$gridSpan.'"/>' : '';

        $regularRPr = $this->buildSignatureRunPrXml($fontStyle);
        $boldRPr = $this->buildSignatureRunPrXml($fontStyle, bold: true);
        $underlineRPr = $this->buildSignatureRunPrXml($fontStyle, underline: true);
        $pPrRPr = $regularRPr !== '<w:rPr/>' ? $regularRPr : '';

        if ($prefix !== '') {
            $prefixP = '<w:p><w:pPr><w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr><w:r>'.$boldRPr.'<w:t xml:space="preserve">'.$prefixEsc.'</w:t></w:r></w:p>';
        } elseif ($reservePrefixSlot) {
            $prefixP = '<w:p><w:pPr><w:spacing w:before="0" w:after="40" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr></w:p>';
        } else {
            $prefixP = '';
        }

        $posP = '<w:p><w:pPr><w:spacing w:before="0" w:after="60" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr><w:r>'.$regularRPr.'<w:t xml:space="preserve">'.$posEsc.'</w:t></w:r></w:p>';

        $sigBlankLine = '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr></w:p>';
        $sigSpaceXml = str_repeat($sigBlankLine, self::SIGNATURE_BLANK_LINES);

        $bottomAfter = $hasNextRow ? '200' : '80';
        $nameAfter = $idLine !== '' ? '20' : $bottomAfter;

        $nameP = '<w:p><w:pPr><w:spacing w:before="0" w:after="'.$nameAfter.'" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr><w:r>'.$underlineRPr.'<w:t xml:space="preserve">'.$nameEsc.'</w:t></w:r></w:p>';

        $idP = $idLine !== ''
            ? '<w:p><w:pPr><w:spacing w:before="0" w:after="'.$bottomAfter.'" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/>'.$pPrRPr.'</w:pPr><w:r>'.$regularRPr.'<w:t xml:space="preserve">'.$idLineEsc.'</w:t></w:r></w:p>'
            : '';

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$gridSpanXml.'<w:vAlign w:val="top"/></w:tcPr>'
            .$prefixP.$posP.$sigSpaceXml.$nameP.$idP
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
     * Returns true if the .docx contains at least one non-empty Word header (word/header*.xml)
     * with a table, drawing, image, or text content.
     */
    public function hasWordHeader(string $docxPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return false;
        }

        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('/^word\/header\d+\.xml$/', $name)) {
                $hXml = $zip->getFromName($name);
                if ($hXml !== false && strlen($hXml) >= 50 && preg_match('/<(?:w:tbl|w:drawing|w:pict|w:t)\b/i', $hXml)) {
                    $found = true;
                    break;
                }
            }
        }

        $zip->close();

        return $found;
    }

    /**
     * Template-level header and top-margin sanitizer (called once on template upload or opt-in
     * from the Template Editor, NEVER during letter generation so Template == Letter 1:1):
     * 1. Operates ONLY when the .docx actually contains a non-empty word/header*.xml
     *    (Workspace-Kop templates without header XML are never modified).
     * 2. For table-based headers (<w:tbl>): strips <w:tblpPr>, converts <wp:anchor> to <wp:inline>,
     *    and ensures spacing after the last header paragraph.
     * 3. For floating/freeform headers (without <w:tbl>): preserves <wp:anchor> coordinates and
     *    leaves header*.xml untouched while measuring max vertical page offset (EMU) to compute safe w:top.
     * 4. In word/document.xml: when $hasKop is true, raises w:top to at least 2550 twips (or measured
     *    floating header height + 160 twips) and sets w:header="567" — UNLESS !$force and the first
     *    body paragraph P[0] already has manual leading spacing (blank paragraph or leading <w:br/>).
     *    Never overwrites w:bottom and never removes empty paragraphs before <w:tbl>.
     */
    public function sanitizeHeaderAndMarginsInDocx(string $docxPath, bool $hasKop = true, bool $force = false): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return false;
        }

        $minRequiredTopTwips = 2550;
        $hasNonEmptyHeader = false;
        $modified = false;

        // 1. Inspect all word/header*.xml files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! preg_match('/^word\/header\d+\.xml$/', $name)) {
                continue;
            }

            $hXml = $zip->getFromName($name);
            if ($hXml === false || strlen($hXml) < 50 || ! preg_match('/<(?:w:tbl|w:drawing|w:pict|w:t)\b/i', $hXml)) {
                continue;
            }

            $hasNonEmptyHeader = true;
            $originalHXml = $hXml;
            $hasHeaderTable = str_contains($hXml, '<w:tbl');

            if ($hasHeaderTable) {
                // Strip w:tblpPr from all tables in header
                $hXml = preg_replace('/<w:tblpPr\b[^>]*\/?>/is', '', $hXml) ?? $hXml;

                // Convert wp:anchor inside table-based headers to wp:inline
                $hXml = preg_replace_callback('/<wp:anchor\b[^>]*>(.*?)<\/wp:anchor>/s', function (array $m): string {
                    $inner = $m[1];
                    preg_match('/<wp:extent\b[^>]*\/>/is', $inner, $ext);
                    preg_match('/<wp:effectExtent\b[^>]*\/>/is', $inner, $eff);
                    preg_match('/<wp:docPr\b[^>]*\/>/is', $inner, $docPr);
                    preg_match('/<wp:cNvGraphicFramePr\b[^>]*>.*?<\/wp:cNvGraphicFramePr>/is', $inner, $cNv);
                    preg_match('/<a:graphic\b[^>]*>.*?<\/a:graphic>/is', $inner, $graph);

                    return '<wp:inline distT="0" distB="0" distL="0" distR="0">'
                        .($ext[0] ?? '')
                        .($eff[0] ?? '<wp:effectExtent l="0" t="0" r="0" b="0"/>')
                        .($docPr[0] ?? '')
                        .($cNv[0] ?? '')
                        .($graph[0] ?? '')
                        .'</wp:inline>';
                }, $hXml) ?? $hXml;

                // Ensure the last paragraph in table-based headers has spacing after
                $hXml = preg_replace_callback('/<w:p\b([^>]*)>(?!.*<w:p\b)(.*?)<\/w:p>/s', function (array $m): string {
                    $pAttrs = $m[1];
                    $pContent = $m[2];
                    if (preg_match('/<w:pPr\b[^>]*>/is', $pContent)) {
                        if (preg_match('/<w:spacing\b[^>]*\/?>/is', $pContent)) {
                            $pContent = preg_replace('/<w:spacing\b[^>]*\/?>/is', '<w:spacing w:before="0" w:after="200" w:line="240" w:lineRule="auto"/>', $pContent);
                        } else {
                            $pContent = preg_replace('/(<w:pPr\b[^>]*>)/is', '$1<w:spacing w:before="0" w:after="200" w:line="240" w:lineRule="auto"/>', $pContent);
                        }
                    } else {
                        $pContent = '<w:pPr><w:spacing w:before="0" w:after="200" w:line="240" w:lineRule="auto"/></w:pPr>'.$pContent;
                    }

                    return '<w:p'.$pAttrs.'>'.$pContent.'</w:p>';
                }, $hXml) ?? $hXml;

                if ($hXml !== $originalHXml) {
                    $zip->addFromString($name, $hXml);
                    $modified = true;
                }
            } else {
                // Floating header without <w:tbl>: preserve <wp:anchor> & header XML untouched,
                // and measure max vertical posOffset to determine safe body w:top margin.
                if (preg_match_all('/<wp:positionV\b[^>]*relativeFrom="page"[^>]*>\s*<wp:posOffset>(\d+)<\/wp:posOffset>/is', $hXml, $posMatches)) {
                    foreach ($posMatches[1] as $emuStr) {
                        $emu = (int) $emuStr;
                        // 1 twip = 635 EMU; add 160 twips (~8pt) breathing room below the bottom divider line
                        $neededTwips = (int) ceil($emu / 635) + 160;
                        if ($neededTwips > $minRequiredTopTwips && $neededTwips <= 4000) {
                            $minRequiredTopTwips = $neededTwips;
                        }
                    }
                }
            }
        }

        if (! $hasNonEmptyHeader) {
            $zip->close();

            return false;
        }

        // 2. Align w:top and w:header in word/document.xml when applicable
        $docXml = $zip->getFromName('word/document.xml');
        if ($docXml !== false && $hasKop) {
            $skipMarginBump = ! $force && $this->firstBodyParagraphHasLeadingSpacing($docXml);

            if (! $skipMarginBump) {
                $updatedDocXml = preg_replace_callback('/<w:pgMar\b([^>]*)\/>/is', function (array $m) use ($minRequiredTopTwips): string {
                    $attrs = $m[1];
                    if (preg_match('/w:top="([^"]*)"/', $attrs, $topM)) {
                        $currentTop = (int) $topM[1];
                        if ($currentTop < $minRequiredTopTwips) {
                            $attrs = preg_replace('/w:top="[^"]*"/', 'w:top="'.$minRequiredTopTwips.'"', $attrs) ?? $attrs;
                        }
                    } else {
                        $attrs .= ' w:top="'.$minRequiredTopTwips.'"';
                    }

                    // Ensure w:header is compact (567 twips = 1.0 cm)
                    if (str_contains($attrs, 'w:header=')) {
                        $attrs = preg_replace('/w:header="[^"]*"/', 'w:header="567"', $attrs) ?? $attrs;
                    } else {
                        $attrs .= ' w:header="567"';
                    }

                    return '<w:pgMar'.$attrs.'/>';
                }, $docXml) ?? $docXml;

                if ($updatedDocXml !== $docXml) {
                    $zip->addFromString('word/document.xml', $updatedDocXml);
                    $modified = true;
                }
            }
        }

        $zip->close();

        return $modified;
    }

    /**
     * Detects whether the first child of <w:body> already starts with manual vertical spacing
     * (either an empty blank paragraph or leading non-page <w:br/> before any text/drawing).
     */
    private function firstBodyParagraphHasLeadingSpacing(string $docXml): bool
    {
        $dom = new DOMDocument;
        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($docXml);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if (! $loaded) {
            return false;
        }

        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', $wNs);

        $firstChild = $xpath->query('/w:document/w:body/*[1]');
        if ($firstChild === false || $firstChild->length === 0) {
            return false;
        }

        $firstNode = $firstChild->item(0);
        if (! $firstNode instanceof DOMElement || $firstNode->localName !== 'p') {
            return false;
        }

        $hasDrawingOrPict = ($xpath->query('.//w:drawing | .//w:pict', $firstNode)?->length ?? 0) > 0;
        if (trim($firstNode->textContent) === '' && ! $hasDrawingOrPict) {
            return true;
        }

        $tokens = $xpath->query('.//w:r/w:br | .//w:r/w:t | .//w:r/w:drawing | .//w:r/w:pict', $firstNode);
        if ($tokens === false) {
            return false;
        }

        foreach ($tokens as $tok) {
            if (! $tok instanceof DOMElement) {
                continue;
            }
            if ($tok->localName === 'br') {
                $type = $tok->getAttributeNS($wNs, 'type') ?: $tok->getAttribute('w:type');
                if (strtolower($type) !== 'page') {
                    return true;
                }
            } elseif ($tok->localName === 't') {
                if (trim($tok->textContent) !== '') {
                    return false;
                }
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Ensures clean page break before the Lampiran section in DOCX XML without destroying
     * intentional user Enters or Workspace-Kop Page 2 tables:
     * 1. If the Lampiran paragraph has <w:pageBreakBefore/>, strips it and any redundant leading
     *    <w:br/> inside the Lampiran paragraph, and normalizes redundant breaks immediately before it
     *    into ONE clean <w:br w:type="page"/> paragraph (unless Kop Page 2 + page break already precede it).
     * 2. If the Lampiran paragraph does NOT have <w:pageBreakBefore/> and a <w:br w:type="page"/>
     *    already precedes it (with optional Kop Page 2 table and/or user Enters in between),
     *    leaves the document 100% verbatim.
     * 3. If no page break precedes the Lampiran paragraph at all, inserts ONE clean <w:br w:type="page"/>
     *    paragraph immediately before it.
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

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if (! $loaded) {
            $zip->close();

            return;
        }

        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', $wNs);

        $bodyNodes = $xpath->query('/w:document/w:body');
        if ($bodyNodes === false || $bodyNodes->length === 0 || ! $bodyNodes->item(0) instanceof DOMElement) {
            $zip->close();

            return;
        }
        $body = $bodyNodes->item(0);

        $lampiranParagraphs = [];
        $bodyParagraphs = $xpath->query('./w:p', $body);
        if ($bodyParagraphs !== false) {
            foreach ($bodyParagraphs as $pNode) {
                if (! $pNode instanceof DOMElement) {
                    continue;
                }
                $text = $pNode->textContent;
                if (preg_match('/(?:\$\{__lampiran_section_start\}|\bLampiran\s+[IVX]+(?!\s*:))/i', $text)) {
                    $lampiranParagraphs[] = $pNode;
                }
            }
        }

        if ($lampiranParagraphs === []) {
            $zip->close();

            return;
        }

        $modified = false;

        $createCleanBreakParagraph = function () use ($dom, $wNs): DOMElement {
            $breakP = $dom->createElementNS($wNs, 'w:p');
            $breakPPr = $dom->createElementNS($wNs, 'w:pPr');
            $breakSpacing = $dom->createElementNS($wNs, 'w:spacing');
            $breakSpacing->setAttributeNS($wNs, 'w:before', '0');
            $breakSpacing->setAttributeNS($wNs, 'w:after', '0');
            $breakSpacing->setAttributeNS($wNs, 'w:line', '240');
            $breakSpacing->setAttributeNS($wNs, 'w:lineRule', 'auto');
            $breakPPr->appendChild($breakSpacing);
            $breakP->appendChild($breakPPr);

            $breakR = $dom->createElementNS($wNs, 'w:r');
            $breakBr = $dom->createElementNS($wNs, 'w:br');
            $breakBr->setAttributeNS($wNs, 'w:type', 'page');
            $breakR->appendChild($breakBr);
            $breakP->appendChild($breakR);

            return $breakP;
        };

        foreach ($lampiranParagraphs as $lampiranP) {
            // 1. Strip any <w:pageBreakBefore/> from Lampiran paragraph
            $hadPageBreakBefore = false;
            $pbNodes = $xpath->query('./w:pPr/w:pageBreakBefore', $lampiranP);
            if ($pbNodes !== false && $pbNodes->length > 0) {
                $hadPageBreakBefore = true;
                foreach (iterator_to_array($pbNodes) as $pbNode) {
                    $pbNode->parentNode?->removeChild($pbNode);
                }
                $modified = true;
            }

            // Strip redundant leading <w:br/> inside the Lampiran paragraph when it had pageBreakBefore
            if ($hadPageBreakBefore) {
                $runs = $xpath->query('./w:r', $lampiranP);
                if ($runs !== false) {
                    $stopScanning = false;
                    foreach (iterator_to_array($runs) as $run) {
                        if ($stopScanning || ! $run instanceof DOMElement) {
                            break;
                        }
                        foreach (iterator_to_array($run->childNodes) as $rChild) {
                            if (! $rChild instanceof DOMElement || $rChild->localName === 'rPr') {
                                continue;
                            }
                            if ($rChild->localName === 'br') {
                                $run->removeChild($rChild);
                                $modified = true;
                            } elseif ($rChild->localName === 't' && trim($rChild->textContent) === '') {
                                continue;
                            } else {
                                $stopScanning = true;
                                break;
                            }
                        }
                    }
                }
            }

            // 2. Inspect preceding siblings to see if a clean page break (and optional Kop Page 2 table) already exists
            $foundPageBreakNode = null;
            $foundKopTable = false;
            $emptyBetween = [];

            $prev = $lampiranP->previousSibling;
            while ($prev !== null) {
                if (! $prev instanceof DOMElement) {
                    $prev = $prev->previousSibling;

                    continue;
                }

                if ($prev->localName === 'p') {
                    $hasPageBr = ($xpath->query('.//w:br[@w:type="page"]', $prev)?->length ?? 0) > 0;
                    if ($hasPageBr) {
                        $foundPageBreakNode = $prev;
                        break;
                    }

                    $hasContent = ($xpath->query('.//w:t | .//w:drawing | .//w:pict', $prev)?->length ?? 0) > 0;
                    if (! $hasContent) {
                        $emptyBetween[] = $prev;
                        $prev = $prev->previousSibling;

                        continue;
                    }

                    break;
                }

                if ($prev->localName === 'tbl' && ! $foundKopTable) {
                    // KopInjector tables have double bottom border (<w:bottom w:val="double"/>)
                    $isKopTbl = ($xpath->query('.//w:tblBorders/w:bottom[@w:val="double"]', $prev)?->length ?? 0) > 0;
                    if ($isKopTbl) {
                        $foundKopTable = true;
                        $prev = $prev->previousSibling;

                        continue;
                    }

                    break;
                }

                break;
            }

            if ($hadPageBreakBefore) {
                if ($foundKopTable && $foundPageBreakNode !== null) {
                    // Kop Page 2 and its preceding page break are already cleanly structured
                    continue;
                }

                foreach ($emptyBetween as $emptyNode) {
                    $emptyNode->parentNode?->removeChild($emptyNode);
                }
                if ($foundPageBreakNode !== null) {
                    $foundPageBreakNode->parentNode?->removeChild($foundPageBreakNode);
                }

                $body->insertBefore($createCleanBreakParagraph(), $lampiranP);
                $modified = true;
            } elseif ($foundPageBreakNode === null) {
                // No page break before Lampiran at all -> insert ONE clean page break
                $body->insertBefore($createCleanBreakParagraph(), $lampiranP);
                $modified = true;
            }
        }

        if ($modified) {
            $savedXml = $dom->saveXML();
            if ($savedXml !== false) {
                $zip->addFromString('word/document.xml', $savedXml);
            }
        }

        $zip->close();
    }
}
