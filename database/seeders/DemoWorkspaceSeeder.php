<?php

namespace Database\Seeders;

use App\Enums\LetterStatus;
use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\Disposisi;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $demoEmail = config('suratinaja.demo.email', 'demo@suratinaja.test');
        $demoPassword = config('suratinaja.demo.password', 'DemoSuratinAja2026!');
        $workspaceName = config('suratinaja.demo.workspace_name', 'Himpunan Mahasiswa Demo');

        // 1. Create or update Demo Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'demo-workspace'],
            [
                'name' => $workspaceName,
                'kop_header_text' => "HIMPUNAN MAHASISWA ILMU KOMPUTER (HIMAKOM)\nUNIVERSITAS TEKNOLOGI NUSANTARA",
                'kop_address' => "Gedung PKM Lt. 2, Jl. Pendidikan No. 45, Jakarta Selatan • Surel: himakom.demo@kampus.ac.id",
                'number_pattern' => '{seq}/DEMO/{bulan_romawi}/{tahun}',
                'number_code' => 'DEMO',
                'seq_padding' => 3,
            ]
        );

        // 2. Create or update Demo User
        $demoUser = User::firstOrCreate(
            ['email' => $demoEmail],
            [
                'name' => 'Pengguna Demo (Hanya Lihat)',
                'password' => Hash::make($demoPassword),
                'email_verified_at' => now(),
                'status' => 'active',
                'is_demo' => true,
                'is_super_admin' => false,
            ]
        );

        $demoUser->forceFill([
            'is_demo' => true,
            'status' => 'active',
            'is_super_admin' => false,
            'current_workspace_id' => $workspace->id,
        ])->saveQuietly();

        // 3. Assign Demo User as Admin of Demo Workspace
        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $demoUser->id],
            ['role' => WorkspaceRole::Admin, 'joined_at' => now()]
        );

        // Sync Spatie roles & permissions for the demo workspace
        /** @var WorkspacePermissionRegistrar $registrar */
        $registrar = app(WorkspacePermissionRegistrar::class);
        $registrar->ensureUserRoleSynced($workspace, $demoUser);

        // 4. Sample Letter Template in Demo Workspace
        $templateRelPath = "templates/workspaces/{$workspace->id}/surat-undangan-demo.docx";
        $templateAbsPath = Storage::disk('local')->path($templateRelPath);
        if (! Storage::disk('local')->exists($templateRelPath)) {
            SampleDocxBuilder::create($templateAbsPath, ['include_repeater' => true]);
        }

        /** @var TemplateParser $parser */
        $parser = app(TemplateParser::class);
        $schema = Storage::disk('local')->exists($templateRelPath)
            ? $parser->parse($templateAbsPath)
            : [];

        $template = LetterTemplate::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'name' => 'Template Undangan & Peminjaman (Demo)',
            ],
            [
                'scope' => TemplateScope::Workspace->value,
                'description' => 'Template resmi persuratan acara dengan variabel daftar penerima dan agenda kegiatan.',
                'file_path' => $templateRelPath,
                'variables_schema' => $schema,
                'version' => 1,
                'is_deprecated' => false,
                'created_by' => $demoUser->id,
            ]
        );

        // 5. Sample Envelope Template in Demo Workspace
        $envelopeRelPath = "envelopes/{$workspace->id}/amplop-kop-resmi.docx";
        $envelopeAbsPath = Storage::disk('local')->path($envelopeRelPath);
        if (! Storage::disk('local')->exists($envelopeRelPath)) {
            SampleDocxBuilder::createBlank($envelopeAbsPath);
        }

        EnvelopeTemplate::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'name' => 'Amplop Kop Resmi (No. 90 / DL)',
            ],
            [
                'preset_size' => 'dl',
                'file_path' => $envelopeRelPath,
                'version' => 1,
                'is_default' => true,
                'created_by' => $demoUser->id,
            ]
        );

        // 6. Pre-staged dummy PDF file for instant preview in browser
        $dummyPdfRelPath = 'letters/demo-sample-preview.pdf';
        if (! Storage::disk('local')->exists($dummyPdfRelPath)) {
            $minimalPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
            Storage::disk('local')->put($dummyPdfRelPath, $minimalPdf);
        }

        // 7. Seed Sample Outgoing Letters (Surat Keluar)
        $letter1 = Letter::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'perihal' => 'Undangan Dialog Terbuka Mahasiswa & Dekanat',
            ],
            [
                'template_id' => $template->id,
                'letter_date' => now()->subDays(2)->format('Y-m-d'),
                'tanggal_kegiatan' => now()->addDays(5)->format('Y-m-d'),
                'status' => LetterStatus::Selesai,
                'created_by' => $demoUser->id,
                'generated_file_path' => $templateRelPath,
                'preview_pdf_path' => $dummyPdfRelPath,
                'field_values' => [
                    'penerima' => 'Dekan Fakultas Ilmu Komputer',
                    'nama_kegiatan' => 'Dialog Terbuka Aspirasi Mahasiswa 2026',
                    'tempat_tujuan' => 'Auditorium Lantai 3',
                ],
            ]
        );

        \App\Models\LetterNumber::firstOrCreate(
            ['letter_id' => $letter1->id],
            [
                'workspace_id' => $workspace->id,
                'formatted_number' => '001/DEMO/VI/2026',
                'sequence' => 1,
                'period_key' => '2026',
                'status' => \App\Enums\NumberStatus::Aktif,
                'issued_at' => now()->subDays(2),
            ]
        );

        $letter2 = Letter::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'perihal' => 'Permohonan Peminjaman Laboratorium Komputer Terpadu',
            ],
            [
                'template_id' => $template->id,
                'letter_date' => now()->format('Y-m-d'),
                'tanggal_kegiatan' => now()->addDays(12)->format('Y-m-d'),
                'status' => LetterStatus::Draft,
                'created_by' => $demoUser->id,
                'generated_file_path' => $templateRelPath,
                'field_values' => [
                    'penerima' => 'Kepala Laboratorium Komputer',
                    'nama_kegiatan' => 'Workshop UI/UX & Cloud Native',
                ],
            ]
        );

        \App\Models\LetterNumber::firstOrCreate(
            ['letter_id' => $letter2->id],
            [
                'workspace_id' => $workspace->id,
                'formatted_number' => '002/DEMO/VI/2026',
                'sequence' => 2,
                'period_key' => '2026',
                'status' => \App\Enums\NumberStatus::Aktif,
                'issued_at' => now(),
            ]
        );

        $letter3 = Letter::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'perihal' => 'Surat Tugas Delegasi Peserta Lomba Hackathon Nasional',
            ],
            [
                'template_id' => $template->id,
                'letter_date' => now()->format('Y-m-d'),
                'tanggal_kegiatan' => now()->addDays(20)->format('Y-m-d'),
                'status' => LetterStatus::MenungguTtd,
                'created_by' => $demoUser->id,
                'generated_file_path' => $templateRelPath,
                'field_values' => [
                    'penerima' => 'Panitia Penyelenggara Hackathon 2026',
                    'nama_kegiatan' => 'Lomba Hackathon Nasional 2026',
                ],
            ]
        );

        \App\Models\LetterNumber::firstOrCreate(
            ['letter_id' => $letter3->id],
            [
                'workspace_id' => $workspace->id,
                'formatted_number' => '003/DEMO/VI/2026',
                'sequence' => 3,
                'period_key' => '2026',
                'status' => \App\Enums\NumberStatus::Aktif,
                'issued_at' => now(),
            ]
        );

        // 8. Seed Sample Incoming Letter (Surat Masuk & Disposisi)
        $suratMasuk = SuratMasuk::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'nomor_agenda' => '001/SM-DEMO/2026',
            ],
            [
                'nomor_surat_pengirim' => 'B-104/KEMAHASISWAAN/VI/2026',
                'tanggal_surat' => now()->subDays(4)->format('Y-m-d'),
                'tanggal_diterima' => now()->subDays(2)->format('Y-m-d'),
                'pengirim' => 'Biro Kemahasiswaan & Alumni Universitas',
                'perihal' => 'Pemberitahuan Sosialisasi Hibah Pendanaan Ormawa 2026',
                'ringkasan' => 'Diharapkan perwakilan pengurus himpunan hadir dalam sosialisasi teknis pengajuan hibah ormawa tahun anggaran 2026.',
                'sifat_surat' => SifatSuratMasuk::Penting,
                'tanggal_acara_atau_deadline' => now()->addDays(7)->format('Y-m-d'),
                'status' => StatusSuratMasuk::Didisposisi,
                'created_by' => $demoUser->id,
            ]
        );

        Disposisi::firstOrCreate(
            [
                'surat_masuk_id' => $suratMasuk->id,
                'kepada_user_id' => $demoUser->id,
            ],
            [
                'dari_user_id' => $demoUser->id,
                'instruksi' => 'Tolong koordinasikan dengan divisi humas & sekretaris untuk menyusun proposal hibah kegiatan.',
                'batas_waktu' => now()->addDays(5)->format('Y-m-d'),
                'status_tindak_lanjut' => \App\Enums\StatusDisposisi::SedangDiproses,
                'catatan_tindak_lanjut' => 'Batas pengumpulan draf proposal H-2 sebelum sosialisasi.',
            ]
        );

        $this->command?->info('DemoWorkspaceSeeder berhasil dijalankan: Akun & Workspace Demo siap digunakan.');
    }
}
