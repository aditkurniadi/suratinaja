<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Calendar\CalendarIndex;
use App\Livewire\Dashboard;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Letters\LetterShow;
use App\Livewire\Letters\LettersIndex;
use App\Livewire\Settings\NumberingSettings;
use App\Livewire\SuratMasuk\SuratMasukShow;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAuditLog;
use App\Models\WorkspaceMember;
use App\Services\Numbering\NumberAllocator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinalPersuratanEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS Final',
            'slug' => 'himatris-final',
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{seq}/{kategori}{parameter}/HIMATRIS/{bulan_romawi}/{tahun}',
            'activity_code' => 'OASIS',
            'is_activity' => false,
            'default_city' => 'Cilacap',
        ]);

        $user = User::factory()->create([
            'email' => 'admin-final@himatris.id',
            'name' => 'Admin Persuratan',
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin,
        ]);

        return [$ws, $user];
    }

    public function test_breadcrumbs_and_back_buttons_rendered(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Undangan Rapat Evaluasi',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/010',
            'nomor_surat_pengirim' => '10/BEM/VI/2026',
            'pengirim' => 'BEM FTI',
            'perihal' => 'Peminjaman Ruangan',
            'sifat_surat' => 'biasa',
            'status' => 'baru',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'document_url' => 'https://drive.google.com/test10',
        ]);

        // 1. Dashboard HTTP response: breadcrumbs has HIMATRIS and Dashboard
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($ws->name)
            ->assertSee('Dashboard');

        // 2. LettersIndex: Daftar Surat
        $this->actingAs($user)->get(route('letters.index'))
            ->assertOk()
            ->assertSee('Daftar Surat');

        // 3. LetterShow: has Daftar Surat link, Detail Surat, and back button
        $this->actingAs($user)->get(route('letters.show', $letter->id))
            ->assertOk()
            ->assertSee('Daftar Surat')
            ->assertSee('Detail Surat')
            ->assertSee('Kembali ke halaman sebelumnya');

        // 4. SuratMasukShow: has Surat Masuk link, Detail Surat Masuk, and back button
        $this->actingAs($user)->get(route('surat-masuk.show', $sm->id))
            ->assertOk()
            ->assertSee('Surat Masuk')
            ->assertSee('Detail Surat Masuk (SM/2026/010)')
            ->assertSee('Kembali ke halaman sebelumnya');
    }

    public function test_dashboard_upgraded_kpi_cards_and_widgets(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        // Create Letter in MenungguTtd
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Menunggu Tanda Tangan',
            'letter_date' => now(),
            'status' => LetterStatus::MenungguTtd,
            'field_values' => [],
        ]);

        // Create SuratMasuk with upcoming event date
        $futureDate = now()->addDays(5)->toDateString();
        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/077',
            'nomor_surat_pengirim' => '77/EXT/VI/2026',
            'pengirim' => 'Mitra Kampus',
            'perihal' => 'Workshop IT Future',
            'sifat_surat' => 'penting',
            'status' => 'menunggu_disposisi',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'tanggal_acara_atau_deadline' => $futureDate,
            'document_url' => 'https://drive.google.com/test77',
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Surat Keluar')
            ->assertSee('Surat Masuk')
            ->assertSee('Perlu Tindakan')
            ->assertSee('Bulan Ini')
            ->assertSee('Perlu Perhatian Segera')
            ->assertSee('Agenda & Jadwal Terdekat')
            ->assertSee('Aktivitas Persuratan Terbaru')
            ->assertSee('Workshop IT Future')
            ->assertSee('Surat Menunggu Tanda Tangan');
    }

    public function test_calendar_index_grid_and_quick_preview(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $today = now();
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Kalender Uji',
            'letter_date' => $today,
            'status' => LetterStatus::Selesai,
            'field_values' => ['penerima' => 'Ketua Jurusan'],
        ]);

        $sm = SuratMasuk::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'nomor_agenda' => 'SM/2026/088',
            'nomor_surat_pengirim' => '88/ORG/VI/2026',
            'pengirim' => 'Himpunan Elektro',
            'perihal' => 'Undangan Seminar Kalender',
            'sifat_surat' => 'biasa',
            'status' => 'baru',
            'tanggal_surat' => $today->toDateString(),
            'tanggal_diterima' => $today->toDateString(),
            'tanggal_acara_atau_deadline' => $today->copy()->addDays(2)->toDateString(),
            'document_url' => 'https://drive.google.com/test88',
        ]);

        $component = Livewire::actingAs($user)
            ->test(CalendarIndex::class)
            ->assertSee($today->translatedFormat('F Y'))
            ->assertSee('Surat Kalender Uji')
            ->assertSee('Undangan Seminar Kalender');

        // Test month navigation
        $component->call('nextMonth')
            ->assertSet('month', (int) $today->copy()->addMonth()->month)
            ->call('prevMonth')
            ->assertSet('month', (int) $today->month)
            ->call('goToToday')
            ->assertSet('month', (int) $today->month);

        // Test filters
        $component->call('setFilterType', 'surat_keluar')
            ->assertSee('Surat Kalender Uji')
            ->assertDontSee('Undangan Seminar Kalender')
            ->call('setFilterType', 'all')
            ->assertSee('Undangan Seminar Kalender');

        // Test quick preview modal
        $component->call('openPreview', 'surat_keluar', $letter->id)
            ->assertSet('showPreviewModal', true)
            ->assertSee('Buka Halaman Detail Surat')
            ->call('closePreview')
            ->assertSet('showPreviewModal', false);
    }

    public function test_custom_sequence_strict_validation_and_audit_logging(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $allocator = app(NumberAllocator::class);

        // Issue letter #1 and #2
        $letter1 = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Pertama',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);
        $num1 = $allocator->allocateForLetter($letter1, $ws, now());
        $this->assertEquals(1, $num1->sequence);

        $letter2 = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Kedua',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);
        $num2 = $allocator->allocateForLetter($letter2, $ws, now());
        $this->assertEquals(2, $num2->sequence);

        // Highest issued sequence is now 2. The minimum nextSequence allowed is 3!
        // 1. Trying to set nextSequence to 1 or 2 must fail validation!
        Livewire::actingAs($user)
            ->test(NumberingSettings::class)
            ->assertSet('minAllowedSequence', 3)
            ->set('nextSequence', 2)
            ->call('save')
            ->assertHasErrors(['nextSequence'])
            ->assertSet('showConfirmModal', false);

        // 2. Setting nextSequence to 50 should pass validation and open confirmation modal
        Livewire::actingAs($user)
            ->test(NumberingSettings::class)
            ->set('nextSequence', 50)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showConfirmModal', true)
            ->call('confirmSave')
            ->assertSet('showConfirmModal', false)
            ->assertDispatched('toast');

        // 3. Verify audit log was recorded
        $this->assertDatabaseHas('workspace_audit_logs', [
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'action' => 'numbering_sequence_changed',
        ]);

        // 4. Verify sequence allocation uses the new sequence 50
        $letter3 = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Ketiga Lanjutan',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);
        $num3 = $allocator->allocateForLetter($letter3, $ws, now());
        $this->assertEquals(50, $num3->sequence);
        $this->assertStringContainsString('050', $num3->formatted_number);
    }
}
