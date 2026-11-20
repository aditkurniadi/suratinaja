<?php

namespace App\Services\Documents;

use App\Models\Letter;
use App\Models\Workspace;

class EnvelopeRecipientResolver
{
    /**
     * Resolves envelope recipient variables from a Letter model or an associative array.
     *
     * @param  Letter|array<string, mixed>  $letterOrData
     * @return array{nomor_surat: string, instansi: string, instansi_tertuju: string, kepada: string, tujuan: string, alamat: string, perihal: string}
     */
    public static function resolve(Letter|array $letterOrData, Workspace $workspace): array
    {
        if ($letterOrData instanceof Letter) {
            $formattedNumber = $letterOrData->formatted_number ?? 'Draf';
            $perihal = $letterOrData->perihal ?: '';
            $vals = $letterOrData->field_values ?? [];
        } else {
            $formattedNumber = (string) ($letterOrData['formatted_number'] ?? $letterOrData['nomor_surat'] ?? 'Draf');
            $perihal = (string) ($letterOrData['perihal'] ?? '');
            $vals = is_array($letterOrData['field_values'] ?? null) ? $letterOrData['field_values'] : $letterOrData;
        }

        $instansi = (string) ($vals['instansi'] ?? $vals['lembaga'] ?? $vals['organisasi'] ?? $vals['instansi_tertuju'] ?? $vals['perusahaan'] ?? '');
        $kepada = (string) ($vals['kepada'] ?? $vals['nama_penerima'] ?? $vals['penerima'] ?? $vals['yth'] ?? $vals['nama_tujuan'] ?? $vals['nama'] ?? '');

        // Fallback: jika kepada kosong tetapi instansi tersedia, gunakan instansi sebagai penerima
        if ($kepada === '' && $instansi !== '') {
            $kepada = $instansi;
        }

        $alamat = (string) ($vals['alamat'] ?? $vals['alamat_tujuan'] ?? '');
        $tujuan = (string) ($vals['tujuan'] ?? $vals['kota'] ?? $vals['tempat'] ?? $workspace->default_city ?: 'Di Tempat');

        return [
            'nomor_surat' => $formattedNumber,
            'instansi' => $instansi,
            'instansi_tertuju' => $instansi,
            'kepada' => $kepada,
            'tujuan' => $tujuan,
            'alamat' => $alamat,
            'perihal' => $perihal,
        ];
    }
}
