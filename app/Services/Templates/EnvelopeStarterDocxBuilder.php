<?php

namespace App\Services\Templates;

use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

class EnvelopeStarterDocxBuilder
{
    /**
     * Builds a starter .docx template file for envelopes with exact page dimensions
     * and standard Indonesian administration envelope layout.
     *
     * @param  string  $preset  'dl' (230x110 mm), 'c5' (229x162 mm), 'a4' (210x297 mm)
     * @return string Relative storage path of generated .docx
     */
    public function build(Workspace $workspace, string $preset = 'dl', string $name = 'Template Amplop'): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $sectionSettings = match ($preset) {
            'c5' => [
                'pageSizeW' => 12983, // 229mm
                'pageSizeH' => 9184,  // 162mm
                'orientation' => 'landscape',
                'marginTop' => 280,
                'marginBottom' => 280,
                'marginLeft' => 500,
                'marginRight' => 500,
            ],
            'a4' => [
                'pageSizeW' => 11906, // 210mm
                'pageSizeH' => 16838, // 297mm
                'orientation' => 'portrait',
                'marginTop' => 450,
                'marginBottom' => 450,
                'marginLeft' => 500,
                'marginRight' => 500,
            ],
            default => [ // DL / No. 90: 230mm x 110mm landscape
                'pageSizeW' => 13039, // 230mm
                'pageSizeH' => 6236,  // 110mm
                'orientation' => 'landscape',
                'marginTop' => 220,
                'marginBottom' => 220,
                'marginLeft' => 450,
                'marginRight' => 450,
            ],
        };

        $section = $phpWord->addSection($sectionSettings);

        // 1. Kop Surat Table
        $tableStyle = [
            'alignment' => JcTable::CENTER,
            'borderBottomSize' => 18,
            'borderBottomColor' => '000000',
            'cellMarginTop' => 0,
            'cellMarginBottom' => 60,
        ];
        $table = $section->addTable($tableStyle);
        $row = $table->addRow();

        $tableWidth = match ($preset) {
            'a4' => 10900,
            'c5' => 11980,
            default => 12100,
        };

        $logoLeftWidth = 1400;
        $logoRightWidth = 1400;
        $centerWidth = $tableWidth - ($logoLeftWidth + $logoRightWidth);

        // Left Logo Cell
        $cellLeft = $row->addCell($logoLeftWidth, ['alignment' => Jc::CENTER]);
        if ($workspace->kop_logo_left && Storage::disk('local')->exists($workspace->kop_logo_left)) {
            $cellLeft->addImage(Storage::disk('local')->path($workspace->kop_logo_left), [
                'width' => 45,
                'height' => 45,
                'alignment' => Jc::CENTER,
            ]);
        } else {
            $cellLeft->addText('${logo_left}', ['size' => 8, 'color' => '999999'], ['alignment' => Jc::CENTER]);
        }

        // Center Text Cell (Kop Text)
        $cellCenter = $row->addCell($centerWidth, ['alignment' => Jc::CENTER]);
        $kopHeader = trim((string) ($workspace->kop_header_text ?: $workspace->name));
        $kopAddress = trim((string) ($workspace->kop_address ?: 'Sekretariat: Kampus Politeknik'));

        $cellCenter->addText(strtoupper($kopHeader), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $cellCenter->addText($kopAddress, ['size' => 8.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);

        // Right Logo Cell
        $cellRight = $row->addCell($logoRightWidth, ['alignment' => Jc::CENTER]);
        if ($workspace->kop_logo_right && Storage::disk('local')->exists($workspace->kop_logo_right)) {
            $cellRight->addImage(Storage::disk('local')->path($workspace->kop_logo_right), [
                'width' => 45,
                'height' => 45,
                'alignment' => Jc::CENTER,
            ]);
        } else {
            $cellRight->addText('', ['size' => 8]);
        }

        // Space between Kop and Destination
        $spaceAfterKop = match ($preset) {
            'a4' => 500,
            'c5' => 300,
            default => 150,
        };
        $section->addTextBreak(match ($preset) {
            'a4' => 3,
            'c5' => 2,
            default => 1,
        });

        // 2. Destination Box (Tujuan Surat) - Aligned to bottom-right
        $destTable = $section->addTable(['alignment' => JcTable::CENTER]);
        $destRow = $destTable->addRow();

        $leftSpaceWidth = match ($preset) {
            'a4' => 5000,
            'c5' => 6000,
            default => 6500,
        };
        $boxWidth = $tableWidth - $leftSpaceWidth;

        $destRow->addCell($leftSpaceWidth); // Empty spacer cell
        $destCell = $destRow->addCell($boxWidth);

        $destCell->addText('Kepada Yth.', ['size' => 11], ['spaceAfter' => 30]);
        $destCell->addText('${tujuan}', ['bold' => true, 'size' => 12], ['spaceAfter' => 30]);
        $destCell->addText('${instansi}', ['size' => 11], ['spaceAfter' => 20]);
        $destCell->addText('${alamat}', ['size' => 10], ['spaceAfter' => 40]);
        $destCell->addText('Di - Tempat', ['italic' => true, 'size' => 10], ['spaceAfter' => 0]);

        // 3. Small Tracking Letter Number at Bottom Left
        $section->addTextBreak(match ($preset) {
            'a4' => 4,
            'c5' => 2,
            default => 1,
        });
        $section->addText('No: ${nomor_surat}', ['size' => 8, 'color' => '666666'], ['alignment' => Jc::START]);

        // Save to storage
        $filename = 'envelope_templates/'.$workspace->id.'/starter_'.strtolower($preset).'_'.time().'.docx';
        $fullPath = Storage::disk('local')->path($filename);

        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($fullPath);

        return $filename;
    }
}
