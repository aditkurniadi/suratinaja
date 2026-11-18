<?php

namespace App\Services\Templates;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class SampleDocxBuilder
{
    /**
     * Generates a clean, realistic organizational letter .docx template with ${...} placeholders
     * including a repeater table (`lampiran.nama_barang`, `lampiran.jumlah`).
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

        $section = $phpWord->addSection([
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1418,
            'marginRight' => 1134,
        ]);

        $section->addText('Tanggal: ${tanggal_surat}', [], ['alignment' => Jc::END]);
        $section->addText('Nomor   : ${nomor_surat}');
        $section->addText('Perihal : ${perihal}');
        $section->addTextBreak(1);

        $section->addText('Kepada Yth.');
        $section->addText('${penerima}', ['bold' => true]);
        $section->addText('di ${tempat_tujuan}');
        $section->addTextBreak(1);

        $section->addText('Dengan hormat,');
        $section->addText(
            'Sehubungan dengan akan dilaksanakannya kegiatan ${nama_kegiatan} pada tanggal ${tanggal_kegiatan}, '
            .'kami bermaksud mengundang Bapak/Ibu/Saudara/i untuk hadir dalam kegiatan tersebut.'
        );

        foreach ($extraVars as $var) {
            $section->addText('${'.$var.'}');
        }

        if ($includeRepeater) {
            $section->addTextBreak(1);
            $section->addText('Daftar Lampiran Kebutuhan:', ['bold' => true]);
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '333333',
                'cellMargin' => 80,
            ]);
            $table->addRow();
            $table->addCell(5000)->addText('Nama Barang', ['bold' => true]);
            $table->addCell(2500)->addText('Jumlah', ['bold' => true]);

            $table->addRow();
            $table->addCell(5000)->addText('${lampiran.nama_barang}');
            $table->addCell(2500)->addText('${lampiran.jumlah}');
        }

        $section->addTextBreak(1);
        $section->addText('Demikian surat ini kami sampaikan. Atas perhatian dan kerjasamanya kami ucapkan terima kasih.');
        $section->addTextBreak(2);
        $section->addText('Hormat kami,');
        $section->addTextBreak(2);
        $section->addText('Panitia Pelaksana', ['bold' => true]);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($targetAbsolutePath);
    }
}
