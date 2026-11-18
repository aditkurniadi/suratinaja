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
     * a 3-column header table with double bottom rule at the top of `word/document.xml`.
     *
     * @throws DomainException
     */
    public function prepareTemplateCopy(string $workingDocxPath, Workspace $workspace): void
    {
        if (! $workspace->hasKop()) {
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

        $kopXml = $this->buildKopTableXml($workspace);
        $updated = preg_replace('/(<w:body[^>]*>)/', '$1'.$kopXml, $xml, 1);

        if ($updated !== null) {
            $zip->addFromString('word/document.xml', $updated);
        }
        $zip->close();
    }

    /**
     * Populates the injected kop placeholders on a TemplateProcessor instance.
     * Supports optional `#index` suffix for cloned bulk blocks.
     */
    public function applyValues(TemplateProcessor $processor, Workspace $workspace, string $suffix = ''): void
    {
        if (! $workspace->hasKop()) {
            return;
        }

        $headerLines = preg_split('/\r\n|\r|\n/', (string) $workspace->kop_header_text) ?: [''];
        $primaryHeader = trim($headerLines[0] ?? '');
        $subHeader = trim(implode(' — ', array_slice($headerLines, 1)));

        $processor->setValue("__kop_header_main{$suffix}", $primaryHeader);
        $processor->setValue("__kop_header_sub{$suffix}", $subHeader);
        $processor->setValue("__kop_address{$suffix}", (string) ($workspace->kop_address ?? ''));

        if ($workspace->kop_logo_left) {
            $leftPath = Storage::disk('local')->path($workspace->kop_logo_left);
            $processor->setImageValue("__kop_logo_left{$suffix}", [
                'path' => $leftPath,
                'width' => 64,
                'height' => 64,
                'ratio' => true,
            ]);
        } else {
            $processor->setValue("__kop_logo_left{$suffix}", '');
        }

        if ($workspace->kop_logo_right) {
            $rightPath = Storage::disk('local')->path($workspace->kop_logo_right);
            $processor->setImageValue("__kop_logo_right{$suffix}", [
                'path' => $rightPath,
                'width' => 64,
                'height' => 64,
                'ratio' => true,
            ]);
        } else {
            $processor->setValue("__kop_logo_right{$suffix}", '');
        }
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

    private function buildKopTableXml(Workspace $workspace): string
    {
        return '<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblW w:w="5000" w:type="pct"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:bottom w:val="double" w:sz="12" w:space="4" w:color="000000"/>'
            .'<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:insideH w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'<w:insideV w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
            .'</w:tblBorders>'
            .'</w:tblPr>'
            .'<w:tr>'
            .'<w:tc><w:tcPr><w:tcW w:w="800" w:type="pct"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>${__kop_logo_left}</w:t></w:r></w:p>'
            .'</w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="3400" w:type="pct"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="26"/></w:rPr><w:t>${__kop_header_main}</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="22"/></w:rPr><w:t>${__kop_header_sub}</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t>${__kop_address}</w:t></w:r></w:p>'
            .'</w:tc>'
            .'<w:tc><w:tcPr><w:tcW w:w="800" w:type="pct"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>${__kop_logo_right}</w:t></w:r></w:p>'
            .'</w:tc>'
            .'</w:tr>'
            .'</w:tbl>'
            .'<w:p/>';
    }
}
