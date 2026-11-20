<?php

namespace App\Services\Documents;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class PdfMergerService
{
    /**
     * Merges multiple PDF files into one single PDF file using CLI tools (qpdf / pdfunite / gs).
     *
     * @param  array<int, string>  $relativePdfPaths  Array of relative storage paths to input PDF files.
     * @param  string  $relativeOutputPath  Desired relative storage path for the merged output.
     * @return array{ok: bool, path?: string, message?: string}
     *
     * @throws DomainException
     */
    public function merge(array $relativePdfPaths, string $relativeOutputPath): array
    {
        $validRelativePaths = array_values(array_filter(
            $relativePdfPaths,
            fn ($p) => is_string($p) && Storage::disk('local')->exists($p)
        ));

        if (empty($validRelativePaths)) {
            return ['ok' => false, 'message' => 'Tidak ada file PDF yang valid untuk digabungkan.'];
        }

        // If only 1 PDF, copy and return
        if (count($validRelativePaths) === 1) {
            Storage::disk('local')->copy($validRelativePaths[0], $relativeOutputPath);

            return ['ok' => true, 'path' => $relativeOutputPath];
        }

        $absoluteInputPaths = array_map(fn ($p) => Storage::disk('local')->path($p), $validRelativePaths);
        $absoluteOutputPath = Storage::disk('local')->path($relativeOutputPath);

        $outDir = dirname($absoluteOutputPath);
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        // 1. Detect binary: qpdf, pdfunite, gs
        $binary = $this->detectCliBinary();

        if (! $binary) {
            $msg = 'PDF Merger CLI (qpdf atau pdfunite) belum terpasang di sistem. Silakan jalankan `brew install qpdf` (macOS) atau `sudo apt-get install -y qpdf` (Ubuntu/Debian) pada terminal server.';
            Log::error($msg);

            throw DomainException::bulkJobFailed($msg);
        }

        $cmd = match ($binary['tool']) {
            'qpdf' => array_merge(
                [$binary['path'], '--empty', '--pages'],
                $absoluteInputPaths,
                ['--', $absoluteOutputPath]
            ),
            'pdfunite' => array_merge(
                [$binary['path']],
                $absoluteInputPaths,
                [$absoluteOutputPath]
            ),
            'gs' => array_merge(
                [$binary['path'], '-dBATCH', '-dNOPAUSE', '-q', '-sDEVICE=pdfwrite', "-sOutputFile={$absoluteOutputPath}"],
                $absoluteInputPaths
            ),
        };

        try {
            $result = Process::timeout(120)->run($cmd);

            if ($result->successful() && file_exists($absoluteOutputPath) && filesize($absoluteOutputPath) > 0) {
                return ['ok' => true, 'path' => $relativeOutputPath];
            }

            Log::error("PDF merge failed ({$binary['tool']}): " . $result->errorOutput());

            return ['ok' => false, 'message' => 'Proses penggabungan PDF gagal: ' . $result->errorOutput()];
        } catch (\Throwable $e) {
            Log::error("PDF merge exception ({$binary['tool']}): " . $e->getMessage());

            return ['ok' => false, 'message' => 'Terjadi kesalahan saat menggabungkan PDF: ' . $e->getMessage()];
        }
    }

    public function isAvailable(): bool
    {
        return $this->detectCliBinary() !== null;
    }

    /**
     * @return array{tool: string, path: string}|null
     */
    public function detectCliBinary(): ?array
    {
        // 1. Check custom .env / config for qpdf
        $customQpdf = config('suratinaja.qpdf_binary') ?: env('QPDF_BINARY');
        if (! empty($customQpdf) && $this->isValidBinary($customQpdf)) {
            return ['tool' => 'qpdf', 'path' => $customQpdf];
        }

        // 2. Candidate paths for qpdf in prioritized order
        $qpdfCandidates = [
            '/opt/homebrew/bin/qpdf',
            '/usr/local/bin/qpdf',
            '/usr/bin/qpdf',
            'qpdf',
        ];

        foreach ($qpdfCandidates as $cand) {
            if ($this->isValidBinary($cand)) {
                return ['tool' => 'qpdf', 'path' => $cand];
            }
        }

        // 3. Check custom .env / config for pdfunite
        $customPdfunite = config('suratinaja.pdfunite_binary') ?: env('PDFUNITE_BINARY');
        if (! empty($customPdfunite) && $this->isValidBinary($customPdfunite)) {
            return ['tool' => 'pdfunite', 'path' => $customPdfunite];
        }

        // 4. Candidate paths for pdfunite
        $pdfuniteCandidates = [
            '/opt/homebrew/bin/pdfunite',
            '/usr/local/bin/pdfunite',
            '/usr/bin/pdfunite',
            'pdfunite',
        ];

        foreach ($pdfuniteCandidates as $cand) {
            if ($this->isValidBinary($cand)) {
                return ['tool' => 'pdfunite', 'path' => $cand];
            }
        }

        // 5. Candidate paths for gs (Ghostscript)
        $gsCandidates = [
            '/opt/homebrew/bin/gs',
            '/usr/local/bin/gs',
            '/usr/bin/gs',
            'gs',
        ];

        foreach ($gsCandidates as $cand) {
            if ($this->isValidBinary($cand)) {
                return ['tool' => 'gs', 'path' => $cand];
            }
        }

        return null;
    }

    private function isValidBinary(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return file_exists($path) && is_executable($path);
        }

        try {
            $process = Process::run("command -v {$path} || which {$path}");
            return $process->successful() && filled(trim($process->output()));
        } catch (\Throwable) {
            return false;
        }
    }
}
