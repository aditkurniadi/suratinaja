<?php

namespace App\Services\Documents;

use App\Exceptions\DomainException;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class KopInjector
{
    /**
     * If the workspace has a configured kop surat, validates logo files exist and injects
     * a 3-column header table with double bottom rule at the top of `word/document.xml`
     * (and at the top of the Page 2 Lampiran section if present).
     *
     * @throws DomainException
     */
    public function prepareTemplateCopy(string $workingDocxPath, Workspace $workspace, bool $hasEmbeddedKop = false): void
    {
        if ($hasEmbeddedKop || ! $workspace->hasKop()) {
            return;
        }

        $this->assertLogoExists($workspace->kop_logo_left, 'kiri');
        $this->assertLogoExists($workspace->kop_logo_right, 'kanan');

        $zip = new ZipArchive;
        if ($zip->open($workingDocxPath) !== true) {
            throw DomainException::templateInvalid('Gagal membuka salinan template untuk menyisipkan kop surat.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw DomainException::templateInvalid('Struktur word/document.xml tidak ditemukan.');
        }

        // Clean up any standalone empty paragraphs or manual page breaks right before Lampiran I or __lampiran_section_start
        $cleanPattern = '/(?:<w:p\b[^>]*>(?:(?!<\/w:p>).)*<w:br\b[^>]*w:type="page"[^>]*>.*?<\/w:p>\s*|<w:p\b[^>]*>(?:<w:pPr>.*?<\/w:pPr>)?\s*<\/w:p>\s*)+(?=<w:p\b[^>]*>(?:(?!<\/w:p>).)*(?:\$\{__lampiran_section_start\}|Lampiran\s+[IVX]+(?!\s*:)))/is';
        $xml = preg_replace($cleanPattern, '', $xml) ?? $xml;

        // Also clean up any inline <w:br w:type="page"/> inside the paragraph right before Lampiran (e.g. inside signature_block paragraph)
        $xml = preg_replace('/(<w:br\b[^>]*w:type="page"[^>]*>)(?=\s*<\/w:r>.*?<\/w:p>\s*<w:p\b[^>]*>(?:(?!<\/w:p>).)*(?:\$\{__lampiran_section_start\}|Lampiran\s+[IVX]+(?!\s*:)))/is', '', $xml) ?? $xml;

        $kopXml = $this->buildKopTableXml($workspace, '__kop');
        $updated = preg_replace('/(<w:body[^>]*>)/', '$1'.$kopXml, $xml, 1);

        $pageBreakBeforeXml = '<w:p><w:pPr><w:pageBreakBefore/><w:spacing w:before="0" w:after="0" w:line="0" w:lineRule="exact"/></w:pPr></w:p>';

        if ($updated !== null && str_contains($updated, '${__lampiran_section_start}')) {
            $kopPage2Xml = $this->buildKopTableXml($workspace, '__kop_p2');
            $updated = preg_replace(
                '/(<w:p\b[^>]*>(?:(?!<\/w:p>).)*\$\{__lampiran_section_start\}.*?<\/w:p>)/s',
                $pageBreakBeforeXml.'$1'.$kopPage2Xml,
                $updated,
                1
            ) ?? $updated;
        } elseif ($updated !== null && preg_match('/<w:p\b[^>]*>(?:(?!<\/w:p>).)*Lampiran\s+[IVX]+(?!\s*:).*?<\/w:p>/is', $updated)) {
            $kopPage2Xml = $this->buildKopTableXml($workspace, '__kop_p2');
            $updated = preg_replace(
                '/(<w:p\b[^>]*>(?:(?!<\/w:p>).)*Lampiran\s+[IVX]+(?!\s*:).*?<\/w:p>)/is',
                $pageBreakBeforeXml.$kopPage2Xml.'$1',
                $updated,
                1
            ) ?? $updated;
        } elseif ($updated !== null && preg_match('/<w:br\b[^>]*w:type="page"/i', $updated)) {
            $kopPage2Xml = $this->buildKopTableXml($workspace, '__kop_p2');
            $updated = preg_replace(
                '/(<w:p\b[^>]*>(?:(?!<\/w:p>).)*<w:br\b[^>]*w:type="page"[^>]*>.*?<\/w:p>)/is',
                '$1'.$kopPage2Xml,
                $updated,
                1
            ) ?? $updated;
        }

        if ($updated !== null) {
            $zip->addFromString('word/document.xml', $updated);
        }
        $zip->close();
    }

    /**
     * Populates the injected kop placeholders on a TemplateProcessor instance.
     * Supports optional `#index` suffix for cloned bulk blocks.
     */
    public function applyValues(TemplateProcessor $processor, Workspace $workspace, string $suffix = '', bool $hasEmbeddedKop = false): void
    {
        if ($hasEmbeddedKop || ! $workspace->hasKop()) {
            return;
        }

        foreach (['__kop', '__kop_p2'] as $prefix) {
            $lines = $this->headerLines($workspace);
            foreach ($lines as $idx => $line) {
                $processor->setValue("{$prefix}_header_line_{$idx}{$suffix}", mb_strtoupper($line));
            }

            $addrLines = $this->addressLines($workspace);
            foreach ($addrLines as $aIdx => $aLine) {
                $processor->setValue("{$prefix}_address_line_{$aIdx}{$suffix}", $aLine);
            }

            if ($workspace->kop_logo_left) {
                $leftPath = Storage::disk('local')->path($workspace->kop_logo_left);
                try {
                    $processor->setImageValue("{$prefix}_logo_left{$suffix}", [
                        'path' => $leftPath,
                        'width' => 85,
                        'height' => 85,
                        'ratio' => true,
                    ]);
                } catch (\Throwable) {
                    $processor->setValue("{$prefix}_logo_left{$suffix}", '');
                }
            } else {
                $processor->setValue("{$prefix}_logo_left{$suffix}", '');
            }

            if ($workspace->kop_logo_right) {
                $rightPath = Storage::disk('local')->path($workspace->kop_logo_right);
                try {
                    $processor->setImageValue("{$prefix}_logo_right{$suffix}", [
                        'path' => $rightPath,
                        'width' => 85,
                        'height' => 85,
                        'ratio' => true,
                    ]);
                } catch (\Throwable) {
                    $processor->setValue("{$prefix}_logo_right{$suffix}", '');
                }
            } else {
                $processor->setValue("{$prefix}_logo_right{$suffix}", '');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function headerLines(Workspace $workspace): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', (string) $workspace->kop_header_text) ?: [];
        $lines = array_values(array_filter(array_map('trim', $rawLines), fn (string $l) => $l !== ''));

        return $lines !== [] ? $lines : [trim((string) $workspace->name)];
    }

    /**
     * Splits workspace address into clean centered lines (matching official letter format:
     * Street line, Telp/Fax line, Website/Email line).
     *
     * @return array<int, string>
     */
    public function addressLines(Workspace $workspace): array
    {
        $raw = trim((string) ($workspace->kop_address ?? ''));
        if ($raw === '') {
            return [];
        }

        if (str_contains($raw, "\n")) {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        }

        // Split before "Telp:" and before "www." or "http" if on one single line
        $formatted = preg_replace('/\s+(?=Telp\s*:)/i', "\n", $raw) ?? $raw;
        $formatted = preg_replace('/\s+(?=(?:https?:\/\/|www\.))/i', "\n", $formatted) ?? $formatted;

        return array_values(array_filter(array_map('trim', explode("\n", $formatted))));
    }

    private function assertLogoExists(?string $relativePath, string $side): void
    {
        if (! $relativePath) {
            return;
        }

        if (! Storage::disk('local')->exists($relativePath)) {
            throw new DomainException(
                'KOP_LOGO_MISSING',
                "File logo {$side} kop surat tidak ditemukan di penyimpanan. Unggah ulang logo pada pengaturan Kop Surat.",
                422
            );
        }
    }

    public function buildKopTableXml(Workspace $workspace, string $prefix = '__kop'): string
    {
        $lines = $this->headerLines($workspace);
        $hasHimpunanLine = collect($lines)->contains(fn (string $l) => stripos($l, 'HIMPUNAN') !== false);

        $centerParagraphs = '';
        foreach ($lines as $idx => $line) {
            $isBold = count($lines) <= 2
                ? true
                : ($hasHimpunanLine ? stripos($line, 'HIMPUNAN') !== false : $idx === 0);

            $sz = count($lines) > 2 ? '26' : ($idx === 0 ? '26' : '22');
            $boldXml = $isBold ? '<w:b/><w:bCs/>' : '';

            $centerParagraphs .= '<w:p>'
                .'<w:pPr><w:spacing w:before="0" w:after="10" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>'
                .'<w:r><w:rPr>'.$boldXml.'<w:sz w:val="'.$sz.'"/><w:szCs w:val="'.$sz.'"/></w:rPr>'
                .'<w:t>${'.$prefix.'_header_line_'.$idx.'}</w:t></w:r>'
                .'</w:p>';
        }

        $addrLines = $this->addressLines($workspace);
        foreach ($addrLines as $aIdx => $aLine) {
            $after = ($aIdx === count($addrLines) - 1) ? '40' : '10';
            $centerParagraphs .= '<w:p>'
                .'<w:pPr><w:spacing w:before="0" w:after="'.$after.'" w:line="220" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>'
                .'<w:r><w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t>${'.$prefix.'_address_line_'.$aIdx.'}</w:t></w:r>'
                .'</w:p>';
        }

        return '<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblW w:w="5000" w:type="pct"/>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar>'
            .'<w:top w:w="0" w:type="dxa"/>'
            .'<w:left w:w="40" w:type="dxa"/>'
            .'<w:bottom w:w="80" w:type="dxa"/>'
            .'<w:right w:w="40" w:type="dxa"/>'
            .'</w:tblCellMar>'
            .'<w:tblBorders>'
            .'<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:bottom w:val="double" w:sz="12" w:space="4" w:color="000000"/>'
            .'<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:insideH w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:insideV w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'</w:tblBorders>'
            .'</w:tblPr>'
            .'<w:tblGrid>'
            .'<w:gridCol w:w="1400" w:type="dxa"/>'
            .'<w:gridCol w:w="6220" w:type="dxa"/>'
            .'<w:gridCol w:w="1400" w:type="dxa"/>'
            .'</w:tblGrid>'
            .'<w:tr>'
            .'<w:tc><w:tcPr><w:tcW w:w="780" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr><w:r><w:t>${'.$prefix.'_logo_left}</w:t></w:r></w:p>'
            .'</w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="3440" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .$centerParagraphs
            .'</w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="780" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr><w:r><w:t>${'.$prefix.'_logo_right}</w:t></w:r></w:p>'
            .'</w:tc>'
            .'</w:tr>'
            .'</w:tbl>'
            .'<w:p><w:pPr><w:spacing w:before="120" w:after="160"/></w:pPr></w:p>';
    }
}
