<?php

namespace App\Http\Controllers;

use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuratMasukDocumentController extends Controller
{
    public function downloadFile(SuratMasuk $suratMasuk)
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || (int) $suratMasuk->workspace_id !== (int) $ws->id) {
            abort(403, 'Akses ditolak.');
        }

        if (! auth()->user()->can('surat_masuk.view') && ! auth()->user()->can('surat_masuk.preview_download')) {
            abort(403);
        }

        if (! $suratMasuk->file_path || ! Storage::disk('local')->exists($suratMasuk->file_path)) {
            abort(404, 'File lampiran surat masuk tidak ditemukan.');
        }

        $absPath = Storage::disk('local')->path($suratMasuk->file_path);
        $downloadName = $suratMasuk->file_name ?: basename($suratMasuk->file_path);

        return response()->download($absPath, $downloadName);
    }

    public function previewFile(SuratMasuk $suratMasuk)
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || (int) $suratMasuk->workspace_id !== (int) $ws->id) {
            abort(403, 'Akses ditolak.');
        }

        if (! auth()->user()->can('surat_masuk.view') && ! auth()->user()->can('surat_masuk.preview_download')) {
            abort(403);
        }

        if (! $suratMasuk->file_path || ! Storage::disk('local')->exists($suratMasuk->file_path)) {
            abort(404, 'File lampiran surat masuk tidak ditemukan.');
        }

        $absPath = Storage::disk('local')->path($suratMasuk->file_path);
        $mime = $suratMasuk->file_mime ?: mime_content_type($absPath) ?: 'application/octet-stream';

        return response()->file($absPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.($suratMasuk->file_name ?: 'dokumen').'"',
        ]);
    }

    public function exportAgenda(Request $request): StreamedResponse
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! auth()->user()->can('surat_masuk.view')) {
            abort(403);
        }

        $format = strtolower($request->query('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';

        $query = SuratMasuk::with(['disposisis.kepadaUser'])
            ->where('workspace_id', $ws->id)
            ->orderBy('id', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('sifat')) {
            $query->where('sifat_surat', $request->query('sifat'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_diterima', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_diterima', '<=', $request->query('date_to'));
        }

        $items = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Agenda Surat Masuk');

        // Header Title
        $sheet->setCellValue('A1', 'BUKU AGENDA SURAT MASUK');
        $sheet->setCellValue('A2', 'Workspace: ' . $ws->name);
        $sheet->setCellValue('A3', 'Diekspor pada: ' . now()->locale('id')->isoFormat('D MMMM Y, HH:mm') . ' WIB');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('555555');

        // Table Columns
        $headers = [
            'A5' => 'No',
            'B5' => 'No. Agenda',
            'C5' => 'No. Surat Pengirim',
            'D5' => 'Tgl Surat',
            'E5' => 'Tgl Diterima',
            'F5' => 'Pengirim / Asal Surat',
            'G5' => 'Perihal',
            'H5' => 'Sifat',
            'I5' => 'Status',
            'J5' => 'Disposisi Terakhir',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getStyle('A5:J5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $row = 6;
        $no = 1;
        foreach ($items as $item) {
            $lastDisp = $item->disposisis->first();
            $dispSummary = '-';
            if ($lastDisp) {
                $target = $lastDisp->kepadaUser?->name ?: $lastDisp->tujuan_divisi ?: 'Semua';
                $dispSummary = "Kpd: {$target} [{$lastDisp->status_tindak_lanjut->label()}]";
            }

            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $item->nomor_agenda);
            $sheet->setCellValue("C{$row}", $item->nomor_surat_pengirim);
            $sheet->setCellValue("D{$row}", $item->tanggal_surat?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("E{$row}", $item->tanggal_diterima?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("F{$row}", $item->pengirim);
            $sheet->setCellValue("G{$row}", $item->perihal);
            $sheet->setCellValue("H{$row}", $item->sifat_surat?->label() ?? 'Biasa');
            $sheet->setCellValue("I{$row}", $item->status?->label() ?? 'Baru');
            $sheet->setCellValue("J{$row}", $dispSummary);

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        $lastRow = max($row - 1, 5);
        $sheet->getStyle("A5:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Agenda_Surat_Masuk_' . str_replace(' ', '_', $ws->name) . '_' . date('Ymd_His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($spreadsheet) {
                $writer = new Csv($spreadsheet);
                $writer->save('php://output');
            }, "{$filename}.csv", [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
