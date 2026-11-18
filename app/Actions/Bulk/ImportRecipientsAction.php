<?php

namespace App\Actions\Bulk;

use App\Exceptions\DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ImportRecipientsAction
{
    /**
     * Parses a CSV or XLSX file into validated recipient rows and per-row failures (AC-05-03).
     *
     * @param  array<int, string>  $requiredColumns
     * @return array{valid: array<int, array<string, string>>, failed: array<int, array{row: int, errors: array<int, string>}>}
     *
     * @throws DomainException
     */
    public function fromFile(string $absolutePath, array $requiredColumns = []): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw DomainException::bulkJobFailed('File penerima tidak ditemukan atau tidak dapat dibaca.');
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw DomainException::bulkJobFailed('Format file tidak didukung. Gunakan CSV atau XLSX.');
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray('', true, true, false);
        } catch (Throwable $e) {
            throw DomainException::bulkJobFailed('Gagal membaca file CSV/XLSX: '.$e->getMessage());
        }

        if (count($rows) < 2) {
            throw DomainException::bulkJobFailed('File penerima kosong atau tidak memiliki baris data di bawah header.');
        }

        $headers = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $rows[0] ?? []
        );

        $nonEmptyHeaders = array_filter($headers, fn ($h) => $h !== '');
        if (empty($nonEmptyHeaders)) {
            throw DomainException::bulkJobFailed('Baris pertama file harus berisi nama kolom (header).');
        }

        $valid = [];
        $failed = [];

        foreach (array_slice($rows, 1) as $idx => $rawRow) {
            $rowNumber = $idx + 2; // 1-based including header
            $assoc = [];
            foreach ($headers as $colIdx => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($rawRow[$colIdx] ?? ''));
            }

            if (count(array_filter($assoc, fn ($v) => $v !== '')) === 0) {
                $failed[] = ['row' => $rowNumber, 'errors' => ['Baris kosong.']];
                continue;
            }

            $rowErrors = [];
            foreach ($requiredColumns as $reqCol) {
                $key = strtolower($reqCol);
                if (($assoc[$key] ?? '') === '') {
                    $rowErrors[] = "Kolom '{$reqCol}' kosong.";
                }
            }

            // Default fallback check: first non-empty header must have a value
            $firstKey = array_key_first($assoc);
            if (empty($requiredColumns) && $firstKey !== null && ($assoc[$firstKey] ?? '') === '') {
                $rowErrors[] = "Kolom utama '{$firstKey}' wajib diisi.";
            }

            if (! empty($rowErrors)) {
                $failed[] = ['row' => $rowNumber, 'errors' => $rowErrors];
                continue;
            }

            $valid[] = $assoc;
        }

        if (empty($valid)) {
            throw DomainException::bulkJobFailed(
                'Tidak ada baris penerima yang valid pada file yang diunggah.',
                ['failed_rows' => $failed]
            );
        }

        return ['valid' => $valid, 'failed' => $failed];
    }

    /**
     * Normalizes manual recipient array items and validates them per row.
     *
     * @param  array<int, mixed>  $items
     * @return array{valid: array<int, array<string, string>>, failed: array<int, array{row: int, errors: array<int, string>}>}
     */
    public function fromManualItems(array $items): array
    {
        $valid = [];
        $failed = [];

        foreach (array_values($items) as $idx => $item) {
            $rowNumber = $idx + 1;
            if (! is_array($item)) {
                $failed[] = ['row' => $rowNumber, 'errors' => ['Format baris penerima harus berupa objek key-value.']];
                continue;
            }

            $cleaned = [];
            foreach ($item as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $cleaned[trim((string) $k)] = trim((string) $v);
                }
            }

            $nonEmpty = array_filter($cleaned, fn ($v) => $v !== '');
            if (empty($nonEmpty)) {
                $failed[] = ['row' => $rowNumber, 'errors' => ['Data penerima pada baris ini kosong.']];
                continue;
            }

            $valid[] = $cleaned;
        }

        if (empty($valid)) {
            throw DomainException::bulkJobFailed(
                'Daftar penerima manual tidak valid atau kosong.',
                ['failed_rows' => $failed]
            );
        }

        return ['valid' => $valid, 'failed' => $failed];
    }
}
