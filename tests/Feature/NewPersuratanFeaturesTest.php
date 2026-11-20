<?php

namespace Tests\Feature;

use App\Actions\Templates\UploadTemplateAction;
use App\Enums\BulkJobStatus;
use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Bulk\BulkGenerator;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Settings\LetterTypesSettings;
use App\Livewire\Settings\NumberingSettings;
use App\Livewire\SuratMasuk\SuratMasukForm;
use App\Livewire\SuratMasuk\SuratMasukShow;
use App\Livewire\Templates\TemplatesIndex;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use App\Services\Numbering\NumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewPersuratanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS Test',
            'slug' => 'himatris-test',
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{nomor}/{kategori}{parameter}/PAN-{kode_kegiatan}/HIMATRIS/{bulan_romawi}/{tahun}',
            'activity_code' => 'JKBFEST',
            'is_activity' => true,
            'default_city' => 'Cilacap',
        ]);

        $user = User::factory()->create([
            'email' => 'admin-feat@himatris.id',
            'name' => 'Admin Features',
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin,
        ]);

        return [$ws, $user];
    }

    public function test_letter_composer_multistep_wizard_navigation_and_validation(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Surat Tugas',
            'file_path' => 'templates/test/stg.docx',
            'variables_schema' => [
                ['name' => 'perihal', 'type' => 'system', 'label' => 'Perihal'],
                ['name' => 'tujuan', 'type' => 'text', 'label' => 'Tujuan'],
            ],
            'version' => 1,
        ]);

        // Start at Step 1
        $component = Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->assertSet('currentStep', 1)
            ->set('templateId', null)
            ->set('perihal', '')
            ->call('nextStep')
            ->assertHasErrors(['templateId', 'perihal'])
            ->assertSet('currentStep', 1) // Did not advance due to errors
            ->set('templateId', $tpl->id)
            ->set('perihal', 'Surat Tugas Panitia')
            ->set('letterDate', '2026-11-20')
            ->set('kategori', 'STG')
            ->set('parameter', '-i')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2) // Advanced to Step 2
            ->call('prevStep')
            ->assertSet('currentStep', 1) // Returned to Step 1
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->call('nextStep')
            ->assertSet('currentStep', 3); // Advanced to Step 3
    }

    public function test_create_letter_without_template_manual_number_and_google_drive(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $comp = Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->call('setLetterMode', 'manual_number')
            ->set('perihal', 'Permohonan Kerjasama Sponsor Eksternal')
            ->set('letterDate', '2026-11-25')
            ->set('kategori', 'SPm')
            ->set('parameter', '-e')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->set('recipientName', 'PT Telkom Indonesia Tbk')
            ->set('recipientAgency', 'Witel Purwokerto')
            ->set('linkDrive', 'https://drive.google.com/file/d/1234567890/view')
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->call('save');

        if ($comp->get('errorMessage')) {
            dump($comp->get('errorMessage'));
        }
        $comp->assertHasNoErrors();

        $letter = Letter::withoutGlobalScopes()->where('perihal', 'Permohonan Kerjasama Sponsor Eksternal')->first();
        $this->assertNotNull($letter);
        $this->assertTrue($letter->is_manual_number);
        $this->assertNull($letter->template_id);
        $this->assertSame('https://drive.google.com/file/d/1234567890/view', $letter->link_drive);
        $this->assertStringContainsString('001/SPm-e/PAN-JKBFEST/HIMATRIS/', $letter->formatted_number);
        $this->assertSame('PT Telkom Indonesia Tbk', $letter->field_values['penerima']);
    }

    public function test_letter_types_management_settings(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $component = Livewire::actingAs($user)
            ->test(LetterTypesSettings::class)
            ->assertSee('SPm')
            ->assertSee('Surat Permohonan')
            // Add custom letter type
            ->set('code', 'DISP')
            ->set('name', 'Surat Dispensasi Mahasiswa')
            ->set('description', 'Izin dispensasi kuliah kegiatan')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('DISP')
            ->assertSee('Surat Dispensasi Mahasiswa');

        $ws->refresh();
        $types = $ws->letterTypes();
        $disp = collect($types)->firstWhere('code', 'DISP');
        $this->assertNotNull($disp);
        $this->assertSame('Surat Dispensasi Mahasiswa', $disp['name']);

        // Delete custom letter type
        $dispIndex = collect($types)->search(fn ($t) => $t['code'] === 'DISP');
        $component->call('deleteType', $dispIndex)
            ->assertDontSee('Surat Dispensasi Mahasiswa');

        $ws->refresh();
        $this->assertNull(collect($ws->letterTypes())->firstWhere('code', 'DISP'));
    }

    public function test_template_deletion_protection(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        Storage::fake('local');
        $filePath = 'templates/ws/test-delete.docx';
        Storage::disk('local')->put($filePath, 'fake docx content');

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Hapus Uji',
            'file_path' => $filePath,
            'variables_schema' => [],
            'version' => 1,
        ]);

        // Template has an existing letter
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'template_id' => $tpl->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Menggunakan Template',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        // Test modal confirmation flow
        Livewire::actingAs($user)
            ->test(TemplatesIndex::class)
            ->call('confirmDelete', $tpl->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingTemplateId', $tpl->id)
            ->assertSet('deletingLetterCount', 1)
            ->call('cancelDelete')
            ->assertSet('showDeleteModal', false)
            ->call('confirmDelete', $tpl->id)
            ->call('deleteConfirmed')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('toast');

        // Template deleted, but letter preserved with template_id detached to null
        $this->assertDatabaseMissing('letter_templates', ['id' => $tpl->id]);
        $this->assertFalse(Storage::disk('local')->exists($filePath));

        $letter->refresh();
        $this->assertNull($letter->template_id);
    }

    public function test_numbering_settings_custom_starting_sequence(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        // Initially sequence starts at 1
        Livewire::actingAs($user)
            ->test(NumberingSettings::class)
            ->assertSet('nextSequence', 1)
            // Change next sequence to 45 (e.g. continuing from previous manual books)
            ->set('nextSequence', 45)
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->call('confirmSave')
            ->assertSet('showConfirmModal', false)
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        // Check sequence row in database
        $allocator = app(NumberAllocator::class);
        $periodKey = $allocator->periodKey(now());
        $this->assertDatabaseHas('letter_number_sequences', [
            'workspace_id' => $ws->id,
            'period_key' => $periodKey,
            'last_sequence' => 44,
        ]);

        // Allocating next letter number gives 45!
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Uji Urutan',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        $letterNumber = $allocator->allocateForLetter($letter, $ws, now());
        $this->assertEquals(45, $letterNumber->sequence);
        $this->assertStringContainsString('045', $letterNumber->formatted_number);
    }

    public function test_bulk_generator_done_status_and_download_link(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Bulk',
            'file_path' => 'templates/test/bulk.docx',
            'variables_schema' => [
                ['name' => 'penerima', 'type' => 'text', 'label' => 'Penerima'],
            ],
            'version' => 1,
        ]);

        // Create completed BulkJob with status Done
        $job = BulkJob::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'template_id' => $tpl->id,
            'perihal' => 'Undangan Massal Uji',
            'letter_date' => now(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'Budi']],
            'recipient_count' => 1,
            'processed_count' => 1,
            'status' => BulkJobStatus::Done,
            'output_path' => 'bulk-exports/test_bulk_result.zip',
            'finished_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(BulkGenerator::class)
            ->assertSee('Selesai')
            ->assertDontSee('Gagal')
            ->assertSee('Unduh .docx');
    }

    public function test_letter_composer_searchable_reply_surat_masuk_picker(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Balasan',
            'file_path' => 'templates/test/balasan.docx',
            'variables_schema' => [
                ['name' => 'penerima', 'type' => 'text', 'label' => 'Penerima'],
            ],
            'version' => 1,
        ]);

        $sm1 = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/001',
            'nomor_surat_pengirim' => '01/BEM/VI/2026',
            'pengirim' => 'BEM FTI',
            'perihal' => 'Permohonan Peminjaman Alat Sound',
            'sifat_surat' => 'biasa',
            'status' => 'baru',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'document_url' => 'https://drive.google.com/test1',
        ]);

        $sm2 = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/002',
            'nomor_surat_pengirim' => '05/HMM/VI/2026',
            'pengirim' => 'Himpunan Mesin',
            'perihal' => 'Undangan Futsal Cup',
            'sifat_surat' => 'biasa',
            'status' => 'baru',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'document_url' => 'https://drive.google.com/test2',
        ]);

        // Test modal search and select in LetterComposer
        Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->assertSet('replyToSuratMasukId', null)
            ->call('openSelectSmModal')
            ->assertSet('showSelectSmModal', true)
            ->set('searchSuratMasuk', 'Sound')
            ->call('selectSuratMasuk', $sm1->id)
            ->assertSet('showSelectSmModal', false)
            ->assertSet('replyToSuratMasukId', $sm1->id)
            ->assertSet('perihal', 'Balasan: ' . $sm1->perihal)
            ->assertSet('recipientAgency', 'BEM FTI')
            ->call('removeReplySuratMasuk')
            ->assertSet('replyToSuratMasukId', null);
    }

    public function test_surat_masuk_searchable_reply_surat_keluar_picker(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Jawaban Peminjaman Ruang Aula',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/099',
            'nomor_surat_pengirim' => '99/ORG/VI/2026',
            'pengirim' => 'Organisasi Mitra',
            'perihal' => 'Permohonan Peminjaman Aula',
            'sifat_surat' => 'biasa',
            'status' => 'baru',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'document_url' => 'https://drive.google.com/test99',
        ]);

        // In SuratMasukShow: test searching & linking
        Livewire::actingAs($user)
            ->test(SuratMasukShow::class, ['suratMasuk' => $sm])
            ->call('openLinkSuratKeluarModal')
            ->assertSet('showLinkSuratKeluarModal', true)
            ->set('searchSuratKeluar', 'Aula')
            ->call('selectAndSaveLinkSuratKeluar', $letter->id)
            ->assertSet('showLinkSuratKeluarModal', false);

        $sm->refresh();
        $letter->refresh();
        $this->assertEquals($letter->id, $sm->surat_keluar_id);
        $this->assertEquals($sm->id, $letter->surat_masuk_id);

        // In SuratMasukForm: test modal selection
        Livewire::actingAs($user)
            ->test(SuratMasukForm::class, ['suratMasuk' => $sm])
            ->assertSet('suratKeluarId', $letter->id)
            ->call('removeSuratKeluar')
            ->assertSet('suratKeluarId', null)
            ->call('openSelectLetterModal')
            ->assertSet('showSelectLetterModal', true)
            ->call('selectSuratKeluar', $letter->id)
            ->assertSet('suratKeluarId', $letter->id)
            ->assertSet('showSelectLetterModal', false);
    }
}
