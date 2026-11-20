<?php

namespace Tests\Feature;

use App\Enums\SifatSuratMasuk;
use App\Enums\StatusDisposisi;
use App\Enums\StatusSuratMasuk;
use App\Enums\WorkspaceRole;
use App\Livewire\SuratMasuk\SuratMasukForm;
use App\Livewire\SuratMasuk\SuratMasukIndex;
use App\Livewire\SuratMasuk\SuratMasukShow;
use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuratMasukAndDisposisiTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkspaceAndUser(string $name = 'HIMATRIS PNC'): array
    {
        $ws = Workspace::create(['name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name))]);
        $user = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $user->id, 'role' => WorkspaceRole::Admin]);

        setPermissionsTeamId($ws->id);
        $adminRole = Role::where('workspace_id', $ws->id)->where('name', WorkspacePermissionRegistrar::ROLE_ADMIN)->first();
        if ($adminRole) {
            $user->syncRoles([$adminRole]);
        }

        return [$ws, $user];
    }

    public function test_nomor_agenda_auto_generation(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser();

        $num1 = SuratMasuk::nextNomorAgenda($ws->id);
        $this->assertEquals('SM-001/' . now()->year, $num1);

        SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => $num1,
            'nomor_surat_pengirim' => '001/PNC/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Direktur PNC',
            'perihal' => 'Undangan Dies Natalis',
            'document_url' => 'https://drive.google.com/test',
            'created_by' => $user->id,
        ]);

        $num2 = SuratMasuk::nextNomorAgenda($ws->id);
        $this->assertEquals('SM-002/' . now()->year, $num2);
    }

    public function test_surat_masuk_validation_requires_either_file_or_document_url(): void
    {
        Storage::fake('local');
        [$ws, $user] = $this->createWorkspaceAndUser();
        $this->actingAs($user);

        // 1. Both file and documentUrl empty -> validation error
        Livewire::test(SuratMasukForm::class)
            ->set('nomorAgenda', 'SM-001/' . now()->year)
            ->set('nomorSuratPengirim', '123/ABC/2026')
            ->set('pengirim', 'BEM Universitas')
            ->set('perihal', 'Undangan Lomba')
            ->set('tanggalSurat', now()->toDateString())
            ->set('tanggalDiterima', now()->toDateString())
            ->set('file', null)
            ->set('documentUrl', '')
            ->call('save')
            ->assertHasErrors(['document_source']);

        // 2. With file upload -> success
        $uploaded = UploadedFile::fake()->create('surat_undangan.pdf', 500, 'application/pdf');

        Livewire::test(SuratMasukForm::class)
            ->set('nomorAgenda', 'SM-001/' . now()->year)
            ->set('nomorSuratPengirim', '123/ABC/2026')
            ->set('pengirim', 'BEM Universitas')
            ->set('perihal', 'Undangan Lomba')
            ->set('tanggalSurat', now()->toDateString())
            ->set('tanggalDiterima', now()->toDateString())
            ->set('file', $uploaded)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $sm = SuratMasuk::where('nomor_agenda', 'SM-001/' . now()->year)->first();
        $this->assertNotNull($sm);
        $this->assertTrue($sm->hasFile());
        $this->assertTrue(Storage::disk('local')->exists($sm->file_path));

        // 3. With external Google Drive link -> success
        Livewire::test(SuratMasukForm::class)
            ->set('nomorAgenda', 'SM-002/' . now()->year)
            ->set('nomorSuratPengirim', '124/ABC/2026')
            ->set('pengirim', 'Jurusan Teknik')
            ->set('perihal', 'Peminjaman Lab')
            ->set('tanggalSurat', now()->toDateString())
            ->set('tanggalDiterima', now()->toDateString())
            ->set('documentUrl', 'https://drive.google.com/file/d/test-doc/view')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $sm2 = SuratMasuk::where('nomor_agenda', 'SM-002/' . now()->year)->first();
        $this->assertNotNull($sm2);
        $this->assertTrue($sm2->hasExternalUrl());
    }

    public function test_surat_masuk_data_isolation_between_workspaces(): void
    {
        [$ws1, $user1] = $this->createWorkspaceAndUser('Workspace 1');
        [$ws2, $user2] = $this->createWorkspaceAndUser('Workspace 2');

        SuratMasuk::create([
            'workspace_id' => $ws1->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '001/WS1',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Pengirim WS 1',
            'perihal' => 'Rahasia WS 1',
            'document_url' => 'https://drive.google.com/ws1',
            'created_by' => $user1->id,
        ]);

        $this->actingAs($user2);
        setPermissionsTeamId($ws2->id);

        Livewire::test(SuratMasukIndex::class)
            ->assertDontSee('SM-001/2026')
            ->assertDontSee('Rahasia WS 1');
    }

    public function test_disposisi_workflow_and_status_transitions(): void
    {
        [$ws, $admin] = $this->createWorkspaceAndUser();
        $staf = User::factory()->create(['current_workspace_id' => $ws->id, 'name' => 'Budi Santoso']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $staf->id, 'role' => WorkspaceRole::Secretary]);

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '001/PNC/XI/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Wadir III',
            'perihal' => 'Delegasi Lomba Web Nasional',
            'status' => StatusSuratMasuk::Baru,
            'document_url' => 'https://drive.google.com/sample',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        // 1. Admin creates disposition to Budi Santoso
        Livewire::test(SuratMasukShow::class, ['suratMasuk' => $sm])
            ->call('openDisposisiModal')
            ->set('disposisiKepadaUserId', $staf->id)
            ->set('disposisiInstruksi', 'Segera siapkan tim 3 orang untuk pendaftaran lomba.')
            ->set('disposisiBatasWaktu', now()->addDays(3)->toDateString())
            ->call('saveDisposisi')
            ->assertHasNoErrors();

        $sm->refresh();
        $this->assertEquals(StatusSuratMasuk::Didisposisi, $sm->status);
        $disp = $sm->disposisis->first();
        $this->assertNotNull($disp);
        $this->assertEquals(StatusDisposisi::BelumDiproses, $disp->status_tindak_lanjut);

        // 2. Update tindak lanjut to Selesai
        Livewire::test(SuratMasukShow::class, ['suratMasuk' => $sm])
            ->call('openTindakLanjutModal', $disp->id)
            ->set('tindakLanjutStatus', 'selesai')
            ->set('tindakLanjutCatatan', 'Tim 3 orang telah mendaftar dan konfirmasi ke panitia.')
            ->call('saveTindakLanjut')
            ->assertHasNoErrors();

        $disp->refresh();
        $this->assertEquals(StatusDisposisi::Selesai, $disp->status_tindak_lanjut);
        $this->assertNotNull($disp->diselesaikan_pada);

        $sm->refresh();
        $this->assertEquals(StatusSuratMasuk::Selesai, $sm->status);
    }

    public function test_export_buku_agenda_surat_masuk(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser();

        SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '010/EXT/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Politeknik Negeri Cilacap',
            'perihal' => 'Undangan Rapat Pleno',
            'sifat_surat' => SifatSuratMasuk::Penting,
            'status' => StatusSuratMasuk::Baru,
            'document_url' => 'https://drive.google.com/test',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        // Test XLSX export
        $resXlsx = $this->get(route('surat-masuk.export', ['format' => 'xlsx']));
        $resXlsx->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $resXlsx->headers->get('Content-Type'));

        // Test CSV export
        $resCsv = $this->get(route('surat-masuk.export', ['format' => 'csv']));
        $resCsv->assertStatus(200);
        $this->assertStringContainsString('text/csv', $resCsv->headers->get('Content-Type'));
    }

    public function test_sekretaris_can_edit_surat_masuk_and_roles_are_synced_automatically(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS PNC', 'slug' => 'himatris-pnc']);
        $sekretaris = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sekretaris->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '010/EXT/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Politeknik Negeri Cilacap',
            'perihal' => 'Undangan Rapat Awal',
            'document_url' => 'https://drive.google.com/test',
            'created_by' => $sekretaris->id,
        ]);

        $this->actingAs($sekretaris);

        // Edit via Livewire SuratMasukForm
        Livewire::test(SuratMasukForm::class, ['suratMasuk' => $sm->id])
            ->assertOk()
            ->set('perihal', 'Undangan Rapat Telah Diperbarui')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('surat-masuk.show', $sm->id));

        $this->assertDatabaseHas('surat_masuks', [
            'id' => $sm->id,
            'perihal' => 'Undangan Rapat Telah Diperbarui',
        ]);
    }

    public function test_surat_balasan_linking_between_surat_masuk_and_surat_keluar(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser();

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '001/PNC/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Direktur PNC',
            'perihal' => 'Permohonan Delegasi',
            'document_url' => 'https://drive.google.com/test',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        // Buat Surat Keluar balasan via Livewire LetterComposer
        Livewire::withQueryParams(['reply_to_sm' => $sm->id])
            ->test(\App\Livewire\Letters\LetterComposer::class)
            ->assertSet('replyToSuratMasukId', $sm->id)
            ->set('letterMode', 'manual_number')
            ->set('recipientName', 'Direktur PNC')
            ->set('perihal', 'Balasan: Permohonan Delegasi')
            ->call('save')
            ->assertHasNoErrors();

        $letter = \App\Models\Letter::where('workspace_id', $ws->id)->latest('id')->first();
        $this->assertNotNull($letter);
        $this->assertEquals($sm->id, $letter->surat_masuk_id);

        // Verifikasi relasi di Surat Masuk
        $this->assertTrue($sm->hasReply());
        $this->assertTrue($sm->replyLetters()->where('letters.id', $letter->id)->exists());

        // Verifikasi SuratMasukShow menampilkan balasan
        Livewire::test(SuratMasukShow::class, ['suratMasuk' => $sm])
            ->assertOk()
            ->assertSee($letter->perihal);
    }

    public function test_surat_masuk_manual_balasan_recording(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser();

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'nomor_agenda' => 'SM-001/2026',
            'nomor_surat_pengirim' => '001/PNC/2026',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'pengirim' => 'Direktur PNC',
            'perihal' => 'Permohonan Kerjasama',
            'document_url' => 'https://drive.google.com/test',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(SuratMasukShow::class, ['suratMasuk' => $sm])
            ->call('openManualBalasanModal')
            ->set('nomorSuratBalasan', '055/HIMATRIS/VI/2026')
            ->set('catatanBalasan', 'Telah dikirim melalui surat fisik dan email')
            ->call('saveManualBalasan')
            ->assertHasNoErrors();

        $sm->refresh();
        $this->assertEquals('055/HIMATRIS/VI/2026', $sm->nomor_surat_balasan);
        $this->assertTrue($sm->hasReply());
    }
}
