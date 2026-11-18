<?php

/*
|--------------------------------------------------------------------------
| SuratinAja domain configuration
|--------------------------------------------------------------------------
| Every value marked "TBD" is a TEMPORARY default for a decision that is
| still open in docs/FEATURES.md §9 / PRD §5. They are configurable and are
| NOT final product decisions.
*/

return [

    'members' => [
        // TBD: minimum admin policy per workspace (FEATURES.md MOD-01 §2.4)
        'min_admins' => (int) env('SURATINAJA_MIN_ADMINS', 1),
        // TBD: invitation channel (FEATURES.md MOD-01 §2.2) — 'link' shows a copyable
        // accept link to the admin; 'mail' additionally sends it through the mailer.
        'invitation_channel' => env('SURATINAJA_INVITATION_CHANNEL', 'link'),
        'invitation_ttl_days' => 7,
    ],

    'uploads' => [
        // TBD: logo file size limit in KB (FEATURES.md MOD-02 §3.2)
        'logo_max_kb' => (int) env('SURATINAJA_LOGO_MAX_KB', 2048),
        // TBD: template max size in KB (PRD §5) - 50 MB
        'template_max_kb' => (int) env('SURATINAJA_TEMPLATE_MAX_KB', 51200),
        'recipients_max_kb' => 2048,
    ],

    'templates' => [
        // TBD: display format for `date` variables (FEATURES.md MOD-03 §4.2)
        'date_format' => env('SURATINAJA_DATE_FORMAT', 'd F Y'),
        // TBD: variable type rules (FEATURES.md MOD-03 §4.2) — prefix convention.
        'date_prefixes' => ['tgl_', 'tanggal_'],
        // Variables named "group.column" are repeater (table) columns.
        'repeater_separator' => '.',
        // TBD: empty repeater policy (FEATURES.md MOD-03 §4.4): 'delete_row' | 'blank_row'
        'empty_repeater' => env('SURATINAJA_EMPTY_REPEATER', 'delete_row'),
        // Variables filled automatically by the system (not shown in forms).
        'system_variables' => [
            'nomor_surat',
            'tanggal_surat',
            'perihal',
            'kota_surat',
            'jumlah_lampiran',
            'hari_tanggal_kegiatan',
            'waktu_kegiatan',
            'ttd_kiri_jabatan',
            'ttd_kiri_nama',
            'ttd_kiri_id',
            'ttd_kanan_jabatan',
            'ttd_kanan_nama',
            'ttd_kanan_id',
            'signature_block',
            'tanda_tangan',
            'lampiran_no',
            'lampiran_volume',
        ],
    ],

    'numbering' => [
        // TBD: sequence reset policy (FEATURES.md MOD-04 §5.4): 'never' | 'yearly' | 'monthly'
        'reset' => env('SURATINAJA_NUMBER_RESET', 'never'),
        'default_pattern' => '{seq}/{kategori}{parameter}/PAN-{kode_kegiatan}/{kode}/{bulan_romawi}/{tahun}',
        // TBD: default zero padding of {seq} (TASKS.md TASK-03)
        'default_padding' => 3,
        'retry_attempts' => 3,
        'presets' => [
            'himatris_kegiatan' => [
                'name' => 'Format Kegiatan HIMATRIS (dengan Panitia Kegiatan)',
                'pattern' => '{seq}/{kategori}{parameter}/PAN-{kode_kegiatan}/{kode}/{bulan_romawi}/{tahun}',
                'example' => '001/SPm-i/PAN-JKBFEST/HIMATRIS/VII/2026',
            ],
            'himatris_organisasi' => [
                'name' => 'Format Pengurus / Harian HIMATRIS (Non-Panitia)',
                'pattern' => '{seq}/{kategori}{parameter}/{kode}/{bulan_romawi}/{tahun}',
                'example' => '001/SU-i/HIMATRIS/VII/2026',
            ],
            'standar' => [
                'name' => 'Format Standar Sederhana',
                'pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
                'example' => '001/HIMATRIS/VII/2026',
            ],
        ],
    ],

    'letter_types' => [
        ['code' => 'MOU', 'name' => 'Surat Perjanjian / Memorandum of Understanding'],
        ['code' => 'SK', 'name' => 'Surat Ketetapan / Keputusan'],
        ['code' => 'SKet', 'name' => 'Surat Keterangan'],
        ['code' => 'SP', 'name' => 'Surat Peringatan'],
        ['code' => 'SPb', 'name' => 'Surat Pemberitahuan'],
        ['code' => 'SPm', 'name' => 'Surat Permohonan'],
        ['code' => 'SRmd', 'name' => 'Surat Rekomendasi'],
        ['code' => 'STG', 'name' => 'Surat Tugas'],
        ['code' => 'SU', 'name' => 'Surat Undangan'],
        ['code' => 'Sert', 'name' => 'Sertifikat'],
    ],

    'letter_parameters' => [
        ['code' => '-i', 'label' => 'Internal (-i)', 'description' => 'Untuk kalangan internal kampus / civitas'],
        ['code' => '-e', 'label' => 'Eksternal (-e)', 'description' => 'Untuk instansi, perusahaan, atau pihak luar'],
        ['code' => '', 'label' => 'Tanpa Parameter', 'description' => 'Tidak menggunakan akhiran parameter'],
    ],

    'letters' => [
        // TBD: status after revising a `selesai` letter (FEATURES.md MOD-06 §7.4)
        'status_after_revision' => env('SURATINAJA_STATUS_AFTER_REVISION', 'draft'),
        'per_page' => 15,
    ],

    'bulk' => [
        // TBD: max recipients per batch (FEATURES.md MOD-05 §6.4)
        'max_recipients' => (int) env('SURATINAJA_BULK_MAX', 200),
        // TBD: partial failure policy (FEATURES.md AC-05-03): 'skip_invalid' | 'reject_all'
        'partial_failure' => env('SURATINAJA_BULK_PARTIAL', 'skip_invalid'),
        // TBD: completion notification (FEATURES.md MOD-05 §6.2): in-app polling + database notification
        'notify' => 'database',
    ],

    'retention' => [
        // TBD: generated file retention in days; null = keep forever (PRD §5)
        'generated_days' => env('SURATINAJA_RETENTION_DAYS'),
    ],

    'preview' => [
        'gotenberg_url' => env('GOTENBERG_URL', 'http://localhost:3000'),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 60),
    ],

    'super_admin' => [
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],
];
