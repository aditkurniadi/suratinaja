<?php

namespace App\Services\Templates;

use Illuminate\Support\Str;

class VariableTypeResolver
{
    /**
     * Converts raw variable names extracted from a .docx template into a structured schema.
     *
     * Rules (configurable in config/suratinaja.php, marked TBD in FEATURES.md §4.2):
     * - System variables (nomor_surat, tanggal_surat, perihal) are tagged as `system: true`
     *   and excluded from dynamic user inputs because they are supplied by the letter header.
     * - Names containing `.` (e.g. `lampiran.nama_barang`) are grouped into a single `repeater`
     *   entry with `columns: [{name, label}, ...]`.
     * - Names starting with `tgl_` or `tanggal_` become `date`.
     * - Everything else becomes `text`.
     *
     * @param  array<int, string>  $rawVariables
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $rawVariables): array
    {
        $systemVars = config('suratinaja.templates.system_variables', ['nomor_surat', 'tanggal_surat', 'perihal']);
        $datePrefixes = config('suratinaja.templates.date_prefixes', ['tgl_', 'tanggal_']);
        $sep = config('suratinaja.templates.repeater_separator', '.');

        $fields = [];
        $repeaters = [];
        $seen = [];

        foreach ($rawVariables as $raw) {
            $name = trim((string) $raw);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            // Ignore closing block tags like /BULK, internal __ tags, or #1 suffixes
            if (str_starts_with($name, '/') || str_starts_with($name, '__') || str_contains($name, '#')) {
                continue;
            }

            if (in_array($name, $systemVars, true)) {
                $fields[] = [
                    'name' => $name,
                    'label' => $this->humanize($name),
                    'type' => 'system',
                    'required' => false,
                ];
                continue;
            }

            if (str_contains($name, $sep)) {
                [$group, $col] = explode($sep, $name, 2);
                $group = trim($group);
                $col = trim($col);
                if ($group !== '' && $col !== '') {
                    $repeaters[$group][$col] = [
                        'name' => $col,
                        'token' => "{$group}{$sep}{$col}",
                        'label' => $this->humanize($col),
                    ];
                }
                continue;
            }

            $type = 'text';
            foreach ($datePrefixes as $prefix) {
                if (str_starts_with(strtolower($name), $prefix)) {
                    $type = 'date';
                    break;
                }
            }

            $fields[] = [
                'name' => $name,
                'label' => $this->humanize($name),
                'type' => $type,
                'required' => true,
            ];
        }

        foreach ($repeaters as $group => $columns) {
            $fields[] = [
                'name' => $group,
                'label' => $this->humanize($group),
                'type' => 'repeater',
                'required' => false,
                'columns' => array_values($columns),
            ];
        }

        return $fields;
    }

    /**
     * Returns only user-editable fields (excludes `system` type, unless $includePerihal is true).
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    public function editableFields(array $schema, bool $includePerihal = false): array
    {
        return array_values(array_map(function (array $f) {
            if (($f['name'] ?? '') === 'perihal') {
                $f['type'] = 'text';
                $f['label'] = 'Perihal';
            }

            return $f;
        }, array_filter($schema, function (array $f) use ($includePerihal) {
            $type = $f['type'] ?? 'text';
            $name = (string) ($f['name'] ?? '');

            if ($includePerihal && $name === 'perihal') {
                return true;
            }

            return $type !== 'system';
        })));
    }

    public function humanize(string $identifier): string
    {
        $label = Str::headline(str_replace(['.', '_', '-'], ' ', $identifier));

        return preg_replace_callback('/\b(Nim|Nip|Nik|Hp|Id|Sk|Rt|Rw)\b/', fn ($m) => strtoupper($m[1]), $label) ?? $label;
    }

    /**
     * Categorized catalog of all system variables supported by DocxRenderer,
     * used by the OnlyOffice Template Editor variable sidebar.
     *
     * @return array<int, array{category: string, items: array<int, array{name: string, token: string, label: string, description: string, example: string, recommended?: bool}>}>
     */
    public function systemVariableCatalog(): array
    {
        return [
            [
                'category' => 'Header & Identitas Surat',
                'items' => [
                    [
                        'name' => 'nomor_surat',
                        'token' => '${nomor_surat}',
                        'label' => 'Nomor Surat',
                        'description' => 'Nomor surat resmi yang dialokasikan otomatis oleh sistem',
                        'example' => '038/SU-i/PAN-OASIS/HIMATRIS/XI/2026',
                        'recommended' => true,
                    ],
                    [
                        'name' => 'tanggal_surat',
                        'token' => '${tanggal_surat}',
                        'label' => 'Tanggal Surat',
                        'description' => 'Tanggal penerbitan surat dalam format Indonesia',
                        'example' => '20 November 2026',
                        'recommended' => true,
                    ],
                    [
                        'name' => 'kota_surat',
                        'token' => '${kota_surat}',
                        'label' => 'Kota Surat',
                        'description' => 'Kota penerbitan dari pengaturan workspace',
                        'example' => 'Cilacap',
                    ],
                    [
                        'name' => 'perihal',
                        'token' => '${perihal}',
                        'label' => 'Perihal / Judul Surat',
                        'description' => 'Perihal atau subjek surat yang diisi pada form',
                        'example' => 'Permohonan Peminjaman Ruangan',
                        'recommended' => true,
                    ],
                    [
                        'name' => 'jumlah_lampiran',
                        'token' => '${jumlah_lampiran}',
                        'label' => 'Jumlah Lampiran',
                        'description' => 'Otomatis "Satu (1) lembar" jika ada tabel lampiran, atau "-"',
                        'example' => 'Satu (1) lembar',
                    ],
                ],
            ],
            [
                'category' => 'Blok Tanda Tangan Dinamis',
                'items' => [
                    [
                        'name' => 'signature_block',
                        'token' => '${signature_block}',
                        'label' => 'Blok Tanda Tangan Dinamis',
                        'description' => 'Otomatis diganti tabel 1–5+ penandatangan (Drag & Drop) sesuai font & ukuran template',
                        'example' => 'Tabel TTD Kiri / Kanan / Tengah',
                        'recommended' => true,
                    ],
                    [
                        'name' => 'tanda_tangan',
                        'token' => '${tanda_tangan}',
                        'label' => 'Alias Blok Tanda Tangan',
                        'description' => 'Fungsi sama dengan ${signature_block}',
                        'example' => 'Tabel TTD Dinamis',
                    ],
                ],
            ],
            [
                'category' => 'Jadwal Kegiatan (Otomatis)',
                'items' => [
                    [
                        'name' => 'hari_tanggal_kegiatan',
                        'token' => '${hari_tanggal_kegiatan}',
                        'label' => 'Nama Hari Kegiatan',
                        'description' => 'Nama hari otomatis dari tanggal_kegiatan / tanggal surat',
                        'example' => 'Jumat',
                    ],
                    [
                        'name' => 'waktu_kegiatan',
                        'token' => '${waktu_kegiatan}',
                        'label' => 'Waktu Kegiatan',
                        'description' => 'Waktu pelaksanaan (default 07.00 s.d. Selesai jika kosong)',
                        'example' => '08.00 s.d. Selesai',
                    ],
                ],
            ],
            [
                'category' => 'TTD Statis (Opsional / Legacy)',
                'items' => [
                    [
                        'name' => 'ttd_kiri_jabatan',
                        'token' => '${ttd_kiri_jabatan}',
                        'label' => 'Jabatan TTD Kiri',
                        'description' => 'Jabatan penandatangan pertama (untuk tabel statis 2 kolom)',
                        'example' => 'Ketua HIMATRIS,',
                    ],
                    [
                        'name' => 'ttd_kiri_nama',
                        'token' => '${ttd_kiri_nama}',
                        'label' => 'Nama TTD Kiri',
                        'description' => 'Nama lengkap penandatangan pertama',
                        'example' => 'Dimas Surya Pratama',
                    ],
                    [
                        'name' => 'ttd_kiri_id',
                        'token' => '${ttd_kiri_id}',
                        'label' => 'NIM / NIP TTD Kiri',
                        'description' => 'Identitas (NIM/NIP) penandatangan pertama',
                        'example' => 'NIM. 25.01.09.044',
                    ],
                    [
                        'name' => 'ttd_kanan_jabatan',
                        'token' => '${ttd_kanan_jabatan}',
                        'label' => 'Jabatan TTD Kanan',
                        'description' => 'Jabatan penandatangan kedua',
                        'example' => 'Ketua Pelaksana,',
                    ],
                    [
                        'name' => 'ttd_kanan_nama',
                        'token' => '${ttd_kanan_nama}',
                        'label' => 'Nama TTD Kanan',
                        'description' => 'Nama lengkap penandatangan kedua',
                        'example' => 'Karisma Putri Isabela',
                    ],
                    [
                        'name' => 'ttd_kanan_id',
                        'token' => '${ttd_kanan_id}',
                        'label' => 'NIM / NIP TTD Kanan',
                        'description' => 'Identitas (NIM/NIP) penandatangan kedua',
                        'example' => 'NIM. 25.03.15.020',
                    ],
                ],
            ],
        ];
    }

    /**
     * Common custom field suggestions for quick insertion in Template Editor.
     *
     * @return array<int, array{token: string, label: string, type: string}>
     */
    public function quickCustomVariables(): array
    {
        return [
            ['token' => '${tujuan_surat}', 'label' => 'Tujuan Surat', 'type' => 'text'],
            ['token' => '${instansi}', 'label' => 'Instansi / Lembaga', 'type' => 'text'],
            ['token' => '${nama_kegiatan}', 'label' => 'Nama Kegiatan', 'type' => 'text'],
            ['token' => '${tanggal_kegiatan}', 'label' => 'Tanggal Kegiatan', 'type' => 'date'],
            ['token' => '${waktu}', 'label' => 'Waktu', 'type' => 'text'],
            ['token' => '${tempat_kegiatan}', 'label' => 'Tempat Kegiatan', 'type' => 'text'],
            ['token' => '${keperluan}', 'label' => 'Keperluan', 'type' => 'text'],
            ['token' => '${lampiran}', 'label' => 'Lampiran (Manual)', 'type' => 'text'],
        ];
    }

    /**
     * Preset dynamic repeater tables ready for 1-click insertion in Template Editor.
     *
     * @return array<int, array{name: string, group: string, description: string, columns: array<int, array{header: string, key: string}>}>
     */
    public function repeaterTablePresets(): array
    {
        return [
            [
                'name' => 'Daftar Perlengkapan (4 Kolom)',
                'group' => 'perlengkapan',
                'description' => 'No, Nama Barang, Volume, Satuan',
                'columns' => [
                    ['header' => 'No', 'key' => 'no'],
                    ['header' => 'Nama Barang', 'key' => 'nama_barang'],
                    ['header' => 'Volume', 'key' => 'volume'],
                    ['header' => 'Satuan', 'key' => 'satuan'],
                ],
            ],
            [
                'name' => 'Daftar Peserta / Mahasiswa (4 Kolom)',
                'group' => 'peserta',
                'description' => 'No, Nama Mahasiswa, NIM, Program Studi',
                'columns' => [
                    ['header' => 'No', 'key' => 'no'],
                    ['header' => 'Nama Mahasiswa', 'key' => 'nama'],
                    ['header' => 'NIM', 'key' => 'nim'],
                    ['header' => 'Program Studi', 'key' => 'prodi'],
                ],
            ],
            [
                'name' => 'Rundown / Susunan Acara (4 Kolom)',
                'group' => 'rundown',
                'description' => 'No, Waktu, Kegiatan, Penanggung Jawab',
                'columns' => [
                    ['header' => 'No', 'key' => 'no'],
                    ['header' => 'Waktu', 'key' => 'waktu'],
                    ['header' => 'Kegiatan', 'key' => 'kegiatan'],
                    ['header' => 'PJ', 'key' => 'pj'],
                ],
            ],
        ];
    }
}
