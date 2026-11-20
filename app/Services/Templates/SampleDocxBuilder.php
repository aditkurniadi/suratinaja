<?php

namespace App\Services\Templates;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class SampleDocxBuilder
{
    /**
     * Generates a clean, official Indonesian organizational letter .docx template with ${...} placeholders,
     * justified paragraphs, dynamic signature block placeholders, and a dedicated Page 2 attachment table
     * (`lampiran.nama_barang`, `lampiran.jumlah`).
     */
    public static function create(string $targetAbsolutePath, array $options = []): void
    {
        $dir = dirname($targetAbsolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $includeRepeater = $options['include_repeater'] ?? true;
        $extraVars = $options['extra_variables'] ?? [];

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        // Standard Microsoft Word "Normal" Margin: 2.54 cm (1 inch = 1440 twips) on all 4 sides
        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // City + Date (Right aligned)
        $section->addText('${kota_surat}, ${tanggal_surat}', [], [
            'alignment' => Jc::END,
            'spaceAfter' => 60,
        ]);

        // Metadata table (borderless: Nomor, Lampiran, Hal)
        $metaTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 0,
            'cellMarginRight' => 40,
        ]);

        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Nomor', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${nomor_surat}', [], ['spaceAfter' => 20]);

        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Lampiran', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${jumlah_lampiran}', [], ['spaceAfter' => 20]);

        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Hal', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${perihal}', ['bold' => true, 'underline' => 'single'], ['spaceAfter' => 20]);

        $section->addTextBreak(1);

        // Recipient block
        $section->addText('Kepada Yth.', [], ['spaceAfter' => 40]);
        $section->addText('${penerima}', ['bold' => true], ['spaceAfter' => 40]);
        $section->addText('Politeknik Negeri Cilacap', [], ['spaceAfter' => 40]);
        $section->addText('Di - Tempat', [], ['spaceAfter' => 120]);

        // Opening & Justified Body
        $section->addText('Dengan Hormat,', [], ['spaceAfter' => 80]);
        $section->addText(
            'Sehubungan dengan penyelenggaraan kegiatan ${nama_kegiatan} oleh Himpunan Mahasiswa Komputer dan Bisnis '
            .'(HIMATRIS) Politeknik Negeri Cilacap, kami bermaksud mengajukan permohonan/undangan terkait kegiatan tersebut. '
            .'Adapun kegiatan tersebut akan diselenggarakan pada:',
            [],
            [
                'alignment' => Jc::BOTH,
                'lineHeight' => 1.5,
                'spaceAfter' => 100,
            ]
        );

        // Indented Event Schedule Table (borderless)
        $scheduleTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 0,
            'cellMarginRight' => 40,
        ]);

        $scheduleTable->addRow();
        $scheduleTable->addCell(900)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Hari, Tanggal', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6100)->addText('${hari_tanggal_kegiatan} (${tanggal_kegiatan})', [], ['spaceAfter' => 20]);

        $scheduleTable->addRow();
        $scheduleTable->addCell(900)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Waktu', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6100)->addText('${waktu_kegiatan}', [], ['spaceAfter' => 20]);

        $scheduleTable->addRow();
        $scheduleTable->addCell(900)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Tempat', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6100)->addText('${tempat_tujuan}', [], ['spaceAfter' => 20]);

        foreach ($extraVars as $var) {
            $section->addText('${'.$var.'}', [], ['alignment' => Jc::BOTH]);
        }

        // Closing paragraph (Justified)
        $section->addText(
            'Demikian surat ini kami sampaikan. Atas perhatian dan izin yang diberikan, kami ucapkan terima kasih.',
            [],
            [
                'alignment' => Jc::BOTH,
                'lineHeight' => 1.5,
                'spaceBefore' => 80,
                'spaceAfter' => 240,
            ]
        );

        // Dynamic 2-column Signature Table (Borderless)
        $sigTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
        ]);
        $sigTable->addRow();
        $leftSigCell = $sigTable->addCell(4510);
        $leftSigCell->addText('${ttd_kiri_jabatan}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 1240]);
        $leftSigCell->addText('${ttd_kiri_nama}', ['underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $leftSigCell->addText('${ttd_kiri_id}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $rightSigCell = $sigTable->addCell(4510);
        $rightSigCell->addText('${ttd_kanan_jabatan}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 1240]);
        $rightSigCell->addText('${ttd_kanan_nama}', ['underline' => 'single'], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $rightSigCell->addText('${ttd_kanan_id}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        // Page 2: Dedicated Lampiran Page
        if ($includeRepeater) {
            $section->addPageBreak();
            $section->addText('${__lampiran_section_start}', [], ['spaceAfter' => 0]);
            $section->addText('Lampiran I', ['bold' => true, 'italic' => true], ['spaceAfter' => 60]);
            $section->addText('Nomor: ${nomor_surat}', [], ['spaceAfter' => 160]);
            $section->addText('Daftar Perlengkapan', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);

            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMarginTop' => 60,
                'cellMarginBottom' => 60,
                'cellMarginLeft' => 100,
                'cellMarginRight' => 100,
            ]);
            $table->addRow();
            $table->addCell(800, ['bgColor' => 'BFBFBF'])->addText('No', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(4620, ['bgColor' => 'BFBFBF'])->addText('Nama Barang', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(1800, ['bgColor' => 'BFBFBF'])->addText('Volume', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(1800, ['bgColor' => 'BFBFBF'])->addText('Satuan', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

            $table->addRow();
            $table->addCell(800)->addText('${lampiran_no}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(4620)->addText('${lampiran.nama_barang}', [], ['spaceAfter' => 0]);
            $table->addCell(1800)->addText('${lampiran_volume}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $table->addCell(1800)->addText('${lampiran.jumlah}', [], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $section->addText('${__lampiran_section_end}', [], ['spaceAfter' => 0]);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($targetAbsolutePath);
    }

    /**
     * Generates a clean starter organizational letter .docx using modern `${signature_block}`
     * and Times New Roman 12pt, optimized for direct editing in OnlyOffice.
     */
    public static function createStarter(string $targetAbsolutePath): void
    {
        $dir = dirname($targetAbsolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        $section->addText('${kota_surat}, ${tanggal_surat}', [], [
            'alignment' => Jc::END,
            'spaceAfter' => 60,
        ]);

        $metaTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 0,
            'cellMarginRight' => 40,
        ]);
        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Nomor', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${nomor_surat}', [], ['spaceAfter' => 20]);

        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Lampiran', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${jumlah_lampiran}', [], ['spaceAfter' => 20]);

        $metaTable->addRow();
        $metaTable->addCell(1500)->addText('Hal', [], ['spaceAfter' => 20]);
        $metaTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $metaTable->addCell(7300)->addText('${perihal}', ['bold' => true, 'underline' => 'single'], ['spaceAfter' => 20]);

        $section->addTextBreak(1);
        $section->addText('Kepada Yth.', [], ['spaceAfter' => 40]);
        $section->addText('${tujuan_surat}', ['bold' => true], ['spaceAfter' => 40]);
        $section->addText('Di - Tempat', [], ['spaceAfter' => 160]);

        $section->addText('Dengan hormat,', [], ['spaceAfter' => 80]);
        $section->addText(
            'Sehubungan dengan penyelenggaraan kegiatan ${nama_kegiatan}, kami bermaksud menyampaikan pemberitahuan/permohonan terkait kegiatan yang akan dilaksanakan pada:',
            [],
            ['alignment' => Jc::BOTH, 'lineHeight' => 1.5, 'spaceAfter' => 100]
        );

        $scheduleTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 0,
            'cellMarginRight' => 40,
        ]);
        $scheduleTable->addRow();
        $scheduleTable->addCell(800)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Hari, Tanggal', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6200)->addText('${hari_tanggal_kegiatan}, ${tanggal_kegiatan}', [], ['spaceAfter' => 20]);

        $scheduleTable->addRow();
        $scheduleTable->addCell(800)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Waktu', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6200)->addText('${waktu_kegiatan}', [], ['spaceAfter' => 20]);

        $scheduleTable->addRow();
        $scheduleTable->addCell(800)->addText('', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(1800)->addText('Tempat', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(220)->addText(':', [], ['spaceAfter' => 20]);
        $scheduleTable->addCell(6200)->addText('${tempat_kegiatan}', [], ['spaceAfter' => 20]);

        $section->addText(
            'Demikian surat ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.',
            [],
            ['alignment' => Jc::BOTH, 'lineHeight' => 1.5, 'spaceBefore' => 100, 'spaceAfter' => 200]
        );

        $section->addText('${signature_block}', [], ['spaceAfter' => 0]);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($targetAbsolutePath);
    }

    /**
     * Generates a clean blank A4 .docx (Times New Roman 12pt, 2.54 cm margins)
     * for authoring a template completely from scratch in OnlyOffice.
     */
    public static function createBlank(string $targetAbsolutePath): void
    {
        $dir = dirname($targetAbsolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        $section->addText('', [], ['spaceAfter' => 0]);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($targetAbsolutePath);
    }
}
