<?php

namespace App\Services\Workspaces;

use App\Models\Letter;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

class WorkspaceStorageService
{
    /**
     * Calculates storage breakdown and total bytes for a workspace.
     *
     * @return array{
     *     previews_bytes: int,
     *     previews_count: int,
     *     previews_formatted: string,
     *     generated_bytes: int,
     *     generated_count: int,
     *     generated_formatted: string,
     *     documents_bytes: int,
     *     documents_formatted: string,
     *     total_bytes: int,
     *     total_formatted: string
     * }
     */
    public function calculateUsage(Workspace $workspace): array
    {
        $disk = Storage::disk('local');
        $wsId = $workspace->id;

        // 1. Temporary PDF Previews
        $previewDir = "previews/{$wsId}";
        $previewFiles = $disk->exists($previewDir) ? $disk->allFiles($previewDir) : [];
        $previewsBytes = 0;
        foreach ($previewFiles as $file) {
            $previewsBytes += $disk->size($file);
        }

        // 2. Generated Outputs (Bulk / Export)
        $generatedDir = "generated/{$wsId}";
        $generatedFiles = $disk->exists($generatedDir) ? $disk->allFiles($generatedDir) : [];
        $generatedBytes = 0;
        foreach ($generatedFiles as $file) {
            $generatedBytes += $disk->size($file);
        }

        // 3. Permanent Documents (Templates, Surat Masuk, Envelopes)
        $docDirs = ["templates/{$wsId}", "surat-masuk/{$wsId}", "envelopes/{$wsId}"];
        $docBytes = 0;
        foreach ($docDirs as $d) {
            if ($disk->exists($d)) {
                foreach ($disk->allFiles($d) as $f) {
                    $docBytes += $disk->size($f);
                }
            }
        }

        // Public kop logos
        $publicDisk = Storage::disk('public');
        $kopDir = "kop/{$wsId}";
        if ($publicDisk->exists($kopDir)) {
            foreach ($publicDisk->allFiles($kopDir) as $f) {
                $docBytes += $publicDisk->size($f);
            }
        }

        $totalBytes = $previewsBytes + $generatedBytes + $docBytes;

        return [
            'previews_bytes' => $previewsBytes,
            'previews_count' => count($previewFiles),
            'previews_formatted' => $this->formatBytes($previewsBytes),
            'generated_bytes' => $generatedBytes,
            'generated_count' => count($generatedFiles),
            'generated_formatted' => $this->formatBytes($generatedBytes),
            'documents_bytes' => $docBytes,
            'documents_formatted' => $this->formatBytes($docBytes),
            'total_bytes' => $totalBytes,
            'total_formatted' => $this->formatBytes($totalBytes),
        ];
    }

    /**
     * Safely clears temporary preview PDFs and resets cached letter preview paths.
     * Original .docx templates, logos, and attachments are NEVER deleted.
     *
     * @return array{files_deleted: int, bytes_freed: int, freed_formatted: string}
     */
    public function clearTemporaryFiles(Workspace $workspace): array
    {
        $disk = Storage::disk('local');
        $wsId = $workspace->id;

        $previewDir = "previews/{$wsId}";
        $files = $disk->exists($previewDir) ? $disk->allFiles($previewDir) : [];
        $count = count($files);
        $freedBytes = 0;

        foreach ($files as $file) {
            $freedBytes += $disk->size($file);
            $disk->delete($file);
        }

        // Also clean empty directories in preview
        if ($disk->exists($previewDir)) {
            $disk->deleteDirectory($previewDir);
        }

        // Reset cached preview paths in letters so they will regenerate cleanly on demand
        Letter::withoutGlobalScopes()
            ->where('workspace_id', $wsId)
            ->whereNotNull('preview_pdf_path')
            ->update(['preview_pdf_path' => null]);

        // Reset Spatie permissions cache
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {}

        return [
            'files_deleted' => $count,
            'bytes_freed' => $freedBytes,
            'freed_formatted' => $this->formatBytes($freedBytes),
        ];
    }

    /**
     * Format bytes into a human readable string.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
