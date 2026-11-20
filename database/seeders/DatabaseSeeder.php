<?php

namespace Database\Seeders;

use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make(config('suratinaja.super_admin.password') ?: 'password');

        $superAdmin = User::firstOrCreate(
            ['email' => config('suratinaja.super_admin.email') ?: 'superadmin@suratinaja.test'],
            [
                'name' => 'Super Admin SuratinAja',
                'password' => $password,
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ]
        );

        $adminHimatris = User::firstOrCreate(
            ['email' => 'admin@himatris.org'],
            [
                'name' => 'Raka Pratama (Admin HIMATRIS)',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $sekretaris = User::firstOrCreate(
            ['email' => 'sekretaris@himatris.org'],
            [
                'name' => 'Nadia Putri (Sekretaris)',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $ketua = User::firstOrCreate(
            ['email' => 'ketua@himatris.org'],
            [
                'name' => 'Bagas Wijaya (Ketua)',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $himatris = Workspace::firstOrCreate(
            ['slug' => 'himatris'],
            [
                'name' => 'HIMATRIS',
                'kop_header_text' => "HIMPUNAN MAHASISWA TEKNIK INFORMATIKA (HIMATRIS)\nFAKULTAS TEKNIK & SAINS",
                'kop_address' => 'Gedung PKM Lt. 2, Jl. Kampus Raya No. 10 — Email: sekretariat@himatris.org',
                'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
                'number_code' => 'HIMATRIS',
                'seq_padding' => 3,
            ]
        );

        $oasis = Workspace::firstOrCreate(
            ['slug' => 'oasis'],
            [
                'name' => 'OASIS',
                'kop_header_text' => "PANITIA PELAKSANA ORIENTASI AKADEMIK & SEMINAR INFORMATIKA (OASIS)\nHIMATRIS",
                'kop_address' => 'Sekretariat Panitia OASIS, Gedung Fakultas Teknik R.104',
                'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
                'number_code' => 'PAN-OASIS',
                'seq_padding' => 3,
            ]
        );

        foreach ([
            [$himatris, $superAdmin, WorkspaceRole::Admin],
            [$himatris, $adminHimatris, WorkspaceRole::Admin],
            [$himatris, $sekretaris, WorkspaceRole::Secretary],
            [$himatris, $ketua, WorkspaceRole::Chairman],
            [$oasis, $sekretaris, WorkspaceRole::Secretary],
            [$oasis, $adminHimatris, WorkspaceRole::Admin],
        ] as [$ws, $u, $role]) {
            WorkspaceMember::firstOrCreate(
                ['workspace_id' => $ws->id, 'user_id' => $u->id],
                ['role' => $role, 'joined_at' => now()]
            );
            if (! $u->current_workspace_id) {
                $u->forceFill(['current_workspace_id' => $ws->id])->save();
            }
        }

        // Sync Spatie roles & permissions for seeded workspace members
        app(\App\Services\Authorization\WorkspacePermissionRegistrar::class)->migrateExistingMembers();

        // Build and register global sample .docx template
        $globalRelPath = 'templates/global/undangan-kegiatan-standar.docx';
        $globalAbsPath = Storage::disk('local')->path($globalRelPath);
        SampleDocxBuilder::create($globalAbsPath, ['include_repeater' => true]);

        /** @var TemplateParser $parser */
        $parser = app(TemplateParser::class);
        $schema = $parser->parse($globalAbsPath);

        $globalTemplate = LetterTemplate::withoutGlobalScopes()->firstOrCreate(
            ['scope' => TemplateScope::Global->value, 'name' => 'Template Undangan & Peminjaman Resmi'],
            [
                'workspace_id' => null,
                'description' => 'Master template global dengan variabel kegiatan, penerima, dan tabel lampiran barang.',
                'file_path' => $globalRelPath,
                'variables_schema' => $schema,
                'version' => 1,
                'is_deprecated' => false,
                'created_by' => $superAdmin->id,
            ]
        );

        if ($himatris->letters()->count() === 0) {
            /** @var CreateLetterAction $creator */
            $creator = app(CreateLetterAction::class);
            /** @var TransitionLetterStatusAction $transitioner */
            $transitioner = app(TransitionLetterStatusAction::class);

            $l1 = $creator->execute($himatris, $sekretaris, [
                'template_id' => $globalTemplate->id,
                'perihal' => 'Undangan Rapat Koordinasi Pengurus HIMATRIS',
                'letter_date' => '2026-06-15',
                'variables' => [
                    'penerima' => 'Seluruh Pengurus Inti HIMATRIS',
                    'tempat_tujuan' => 'Ruang Sidang Utama Lt. 2',
                    'nama_kegiatan' => 'Rapat Koordinasi Semester Ganjil',
                    'tanggal_kegiatan' => '2026-06-20',
                    'lampiran' => [
                        ['nama_barang' => 'Proyektor LCD', 'jumlah' => '2 Unit'],
                        ['nama_barang' => 'Sound System Portable', 'jumlah' => '1 Set'],
                    ],
                ],
            ]);
            $transitioner->execute($l1, $sekretaris, LetterStatus::MenungguTtd);
            $transitioner->execute($l1, $sekretaris, LetterStatus::Selesai);

            $l2 = $creator->execute($himatris, $sekretaris, [
                'template_id' => $globalTemplate->id,
                'perihal' => 'Permohonan Peminjaman Aula Seminar',
                'letter_date' => '2026-06-16',
                'variables' => [
                    'penerima' => 'Wakil Dekan Bidang Umum & Keuangan',
                    'tempat_tujuan' => 'Dekanat Fakultas Teknik',
                    'nama_kegiatan' => 'Workshop Pemrograman Kompetitif',
                    'tanggal_kegiatan' => '2026-06-28',
                    'lampiran' => [],
                ],
            ]);
            $transitioner->execute($l2, $sekretaris, LetterStatus::MenungguTtd);

            $creator->execute($himatris, $sekretaris, [
                'template_id' => $globalTemplate->id,
                'perihal' => 'Undangan Pembicara Tamu Kuliah Umum',
                'letter_date' => '2026-06-18',
                'variables' => [
                    'penerima' => 'Dr. Budi Santoso, M.Kom.',
                    'tempat_tujuan' => 'Laboratorium Kecerdasan Buatan',
                    'nama_kegiatan' => 'Kuliah Umum Rekayasa Perangkat Lunak',
                    'tanggal_kegiatan' => '2026-07-02',
                    'lampiran' => [],
                ],
            ]);
        }
    }
}
