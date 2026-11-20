<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\WorkspaceMember;
use App\Livewire\Calendar\CalendarIndex;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Settings\NumberingSettings;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Numbering\NumberAllocator;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAndNumberingSyncTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-test',
            'number_pattern' => '{seq}/{kategori}{parameter}/{kode_kegiatan}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'HIMATRIS',
            'activity_code' => 'JKBFEST',
            'is_activity' => true,
            'seq_padding' => 3,
        ]);

        $user = User::factory()->create([
            'name' => 'Adit Test',
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin,
        ]);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $user];
    }

    public function test_custom_sequence_syncs_immediately_to_letter_composer_preview(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $allocator = app(NumberAllocator::class);

        // Before any letters or custom settings: sequence is 1
        $this->assertEquals(1, $allocator->resolveNextSequence($ws, now()));

        // Admin configures custom sequence in NumberingSettings to 91 (last_sequence = 90)
        Livewire::actingAs($user)
            ->test(NumberingSettings::class)
            ->set('nextSequence', 91)
            ->call('save')
            ->assertSet('showConfirmModal', true)
            ->call('confirmSave')
            ->assertHasNoErrors();

        // resolveNextSequence must immediately return 91 WITHOUT needing to generate a letter first
        $this->assertEquals(91, $allocator->resolveNextSequence($ws, now()));

        // Template for composer
        $template = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Surat Keputusan',
            'file_path' => 'templates/test/sk.docx',
            'created_by' => $user->id,
            'variables_schema' => [
                ['name' => 'perihal', 'type' => 'system', 'label' => 'Perihal'],
            ],
            'version' => 1,
        ]);

        // Form "Buat Surat" (LetterComposer) must immediately preview '091'
        Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->set('templateId', $template->id)
            ->assertSee('091');

        // Now issue letter via allocator
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'SK Kepanitiaan',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        $num = $allocator->allocateForLetter($letter, $ws, now());
        $this->assertEquals(91, $num->sequence);
        $this->assertStringContainsString('091', $num->formatted_number);

        // Next sequence must automatically become 92
        $this->assertEquals(92, $allocator->resolveNextSequence($ws, now()));
    }

    public function test_calendar_modal_transitions_without_overlapping_and_renders_notion_style(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $today = now();

        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Undangan Rapat Kerja',
            'letter_date' => $today,
            'status' => LetterStatus::Selesai,
            'field_values' => ['penerima' => 'Ketua BEM'],
        ]);
        app(NumberAllocator::class)->allocateForLetter($letter, $ws, $today);

        $suratMasuk = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/001',
            'nomor_surat_pengirim' => '01/BEM/XI/2026',
            'pengirim' => 'BEM Politeknik',
            'perihal' => 'Peminjaman Aula',
            'tanggal_surat' => $today->toDateString(),
            'tanggal_diterima' => $today->toDateString(),
            'document_url' => 'https://drive.google.com/test-agenda',
        ]);

        $component = Livewire::actingAs($user)
            ->test(CalendarIndex::class)
            // Notion header days (Sun - Sat)
            ->assertSee('Min')
            ->assertSee('Sen')
            ->assertSee('Sab')
            ->assertSee('Undangan Rapat Kerja')
            ->assertSee('Peminjaman Aula')
            ->assertSee('Kalender');

        // 1. Open Day-List modal (+X lainnya)
        $component->call('openMoreModal', $today->toDateString())
            ->assertSet('activeModal', 'day-list')
            ->assertSet('showMoreModal', true)
            ->assertSet('showPreviewModal', false)
            ->assertSee('Agenda Surat:');

        // 2. Click "Pratinjau PDF" from inside Day-List modal
        // It should seamlessly switch to 'pdf-preview' without stacking modals
        $component->call('openPreview', 'surat_keluar', $letter->id, true)
            ->assertSet('activeModal', 'pdf-preview')
            ->assertSet('showMoreModal', false)
            ->assertSet('showPreviewModal', true)
            ->assertSet('openedFromDayList', true)
            ->assertSee('Kembali ke Daftar')
            ->assertSee('Unduh PDF');

        // 3. Click "Kembali ke Daftar"
        $component->call('backToDayList')
            ->assertSet('activeModal', 'day-list')
            ->assertSet('showMoreModal', true)
            ->assertSet('showPreviewModal', false)
            ->assertSet('openedFromDayList', false);

        // 4. Close modal completely
        $component->call('closeModal')
            ->assertSet('activeModal', null)
            ->assertSet('showMoreModal', false)
            ->assertSet('showPreviewModal', false);
    }

    public function test_numbering_settings_handles_empty_sequence_input_without_crashing(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        Livewire::actingAs($user)
            ->test(NumberingSettings::class)
            // 1. Simulasikan user menghapus input nomor urut (backspace -> empty string)
            ->set('nextSequence', '')
            ->assertOk()
            ->call('save')
            ->assertHasErrors(['nextSequence' => 'required'])
            // 2. Simulasikan user memasukkan angka di bawah batas minimum
            ->set('nextSequence', 0)
            ->call('save')
            ->assertHasErrors(['nextSequence'])
            // 3. Simulasikan padding dikosongkan
            ->set('nextSequence', 10)
            ->set('seqPadding', '')
            ->call('save')
            ->assertHasErrors(['seqPadding' => 'required'])
            // 4. Masukkan nilai valid dan simpan
            ->set('seqPadding', 3)
            ->set('nextSequence', 50)
            ->call('save')
            ->assertHasNoErrors()
            ->call('confirmSave')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('letter_number_sequences', [
            'workspace_id' => $ws->id,
            'last_sequence' => 49,
        ]);
    }
}
