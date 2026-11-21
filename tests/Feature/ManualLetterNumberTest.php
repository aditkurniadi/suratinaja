<?php

namespace Tests\Feature;

use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\UploadManualDocumentAction;
use App\Enums\LetterStatus;
use App\Enums\WorkspaceRole;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Letters\LetterShow;
use App\Models\Letter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManualLetterNumberTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->workspace = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-test',
            'number_pattern' => '{seq}/HIMATRIS/{tahun}',
            'number_code' => 'HIMATRIS',
            'seq_padding' => 3,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.org',
            'current_workspace_id' => $this->workspace->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_manual_letter_without_template(): void
    {
        $creator = app(CreateLetterAction::class);

        $letter = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'is_manual_number' => true,
            'perihal' => 'Surat Permohonan Kerjasama Eksternal',
            'letter_date' => '2026-11-21',
            'tanggal_kegiatan' => '2026-11-25',
            'keterangan' => 'Dibuat langsung di Microsoft Word',
            'variables' => [
                'instansi' => 'PT Mitra Teknologi Nusantara',
                'penerima' => 'Direktur Utama',
            ],
        ]);

        $this->assertNotNull($letter->id);
        $this->assertTrue((bool) $letter->is_manual);
        $this->assertTrue((bool) $letter->is_manual_number);
        $this->assertNull($letter->template_id);
        $this->assertEquals('Surat Permohonan Kerjasama Eksternal', $letter->perihal);
        $this->assertEquals('Dibuat langsung di Microsoft Word', $letter->keterangan);
        $this->assertEquals('001/HIMATRIS/2026', $letter->formatted_number);
        $this->assertEquals(LetterStatus::Draft, $letter->status);
    }

    public function test_letter_composer_mounts_manual_mode_via_query_param(): void
    {
        Livewire::withQueryParams(['mode' => 'manual'])
            ->test(LetterComposer::class)
            ->assertSet('letterMode', 'manual_number');
    }

    public function test_letter_composer_creates_manual_letter_flow(): void
    {
        Livewire::test(LetterComposer::class)
            ->call('setLetterMode', 'manual_number')
            ->set('perihal', 'Surat Izin Tempat Manual')
            ->set('letterDate', '2026-11-21')
            ->call('nextStep')
            ->set('recipientName', 'Kepala UPT Laboratorium')
            ->set('recipientAgency', 'Politeknik Negeri Cilacap')
            ->set('keterangan', 'Arsip manual nomor urut')
            ->call('nextStep')
            ->call('save')
            ->assertHasNoErrors();

        $letter = Letter::where('perihal', 'Surat Izin Tempat Manual')->first();
        $this->assertNotNull($letter);
        $this->assertTrue($letter->isManualDocument());
        $this->assertEquals('001/HIMATRIS/2026', $letter->formatted_number);
        $this->assertEquals('Arsip manual nomor urut', $letter->keterangan);
    }

    public function test_letter_show_renders_manual_card_without_onlyoffice(): void
    {
        $creator = app(CreateLetterAction::class);
        $letter = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'perihal' => 'Surat Undangan Pembicara Manual',
            'letter_date' => '2026-11-21',
            'variables' => [
                'instansi' => 'Universitas Indonesia',
                'penerima' => 'Dr. Bambang',
            ],
        ]);

        Livewire::test(LetterShow::class, ['letter' => $letter])
            ->assertSee('Surat Manual / Ambil Nomor Saja')
            ->assertSee('Salin Nomor')
            ->assertSee('Universitas Indonesia')
            ->assertDontSee('Menghubungkan ke Microsoft Word Online Editor...');
    }

    public function test_upload_manual_document_stores_pdf_as_preview_and_records_activity(): void
    {
        $creator = app(CreateLetterAction::class);
        $letter = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'perihal' => 'Surat Pengantar Magang',
            'letter_date' => '2026-11-21',
        ]);

        $fakePdf = UploadedFile::fake()->create('surat-magang.pdf', 500, 'application/pdf');

        $action = app(UploadManualDocumentAction::class);
        $updated = $action->execute($letter, $this->admin, $fakePdf);

        $this->assertNotNull($updated->preview_pdf_path);
        Storage::disk('local')->assertExists($updated->preview_pdf_path);

        $this->assertDatabaseHas('letter_activities', [
            'letter_id' => $letter->id,
            'action' => 'document_uploaded',
        ]);
    }

    public function test_manual_letter_metadata_update_via_livewire(): void
    {
        $creator = app(CreateLetterAction::class);
        $letter = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'perihal' => 'Perihal Awal',
            'letter_date' => '2026-11-21',
            'variables' => [
                'instansi' => 'Instansi Lama',
            ],
        ]);

        Livewire::test(LetterShow::class, ['letter' => $letter])
            ->call('openEditMetadataModal')
            ->set('editPerihal', 'Perihal Baru yang Direvisi')
            ->set('editInstansiTujuan', 'Instansi Baru')
            ->set('editTanggalSurat', '2026-11-22')
            ->set('editKeterangan', 'Catatan revisi metadata')
            ->call('saveMetadata')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $letter->refresh();
        $this->assertEquals('Perihal Baru yang Direvisi', $letter->perihal);
        $this->assertEquals('2026-11-22', $letter->letter_date->toDateString());
        $this->assertEquals('Instansi Baru', $letter->field_values['instansi']);
        $this->assertEquals('Catatan revisi metadata', $letter->keterangan);
        // Letter number sequence must remain unchanged
        $this->assertEquals('001/HIMATRIS/2026', $letter->formatted_number);
    }

    public function test_start_sequence_22_allocates_022_accurately(): void
    {
        $periodKey = app(\App\Services\Numbering\NumberAllocator::class)->periodKey(now());

        // Configure next sequence to 22 (stores last_sequence = 21)
        DB::table('letter_number_sequences')->updateOrInsert(
            ['workspace_id' => $this->workspace->id, 'period_key' => $periodKey],
            ['last_sequence' => 21, 'updated_at' => now()]
        );

        $creator = app(CreateLetterAction::class);
        $letter1 = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'perihal' => 'Surat Pertama dengan Nomor 22',
            'letter_date' => '2026-11-21',
        ]);

        $this->assertEquals('022/HIMATRIS/2026', $letter1->formatted_number);

        // Next letter must allocate 23
        $letter2 = $creator->execute($this->workspace, $this->admin, [
            'template_id' => null,
            'is_manual' => true,
            'perihal' => 'Surat Kedua dengan Nomor 23',
            'letter_date' => '2026-11-21',
        ]);

        $this->assertEquals('023/HIMATRIS/2026', $letter2->formatted_number);
    }
}
