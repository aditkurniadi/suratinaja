<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Enums\WorkspaceRole;
use App\Livewire\GlobalSearch;
use App\Models\BulkJob;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Admin Utama']);
        $this->workspace = Workspace::create([
            'name' => 'HIMATRIS Spotlight',
            'slug' => 'himatris-spotlight',
            'owner_id' => $this->admin->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        app(WorkspacePermissionRegistrar::class)->initWorkspaceRoles($this->workspace);
        setPermissionsTeamId($this->workspace->id);
        $this->admin->assignRole(WorkspacePermissionRegistrar::ROLE_ADMIN);

        app(CurrentWorkspace::class)->set($this->workspace);
    }

    public function test_global_search_trigger_rendered_on_app_layout(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Cari surat, amplop...')
            ->assertSee('⌘K')
            ->assertSee('open-global-search');
    }

    public function test_spotlight_opens_and_renders_quick_actions_when_empty(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(GlobalSearch::class)
            ->assertSet('isOpen', false)
            ->call('openSpotlight')
            ->assertSet('isOpen', true)
            ->assertSet('isPreviewOpen', false)
            ->assertSee('Aksi Cepat')
            ->assertSee('Buat Surat Keluar')
            ->assertSee('Kalender Persuratan')
            ->assertSee('Builder Kop Surat')
            ->call('closeSpotlight')
            ->assertSet('isOpen', false);
    }

    public function test_spotlight_searches_letters_by_perihal_and_formatted_number(): void
    {
        $this->actingAs($this->admin);

        $template = LetterTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Template Undangan',
            'file_path' => 'templates/test.docx',
            'scope' => 'workspace',
            'variables_schema' => [],
        ]);

        $letter = Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'template_id' => $template->id,
            'perihal' => 'Undangan Rapat Kerja Akbar 2026',
            'letter_date' => now()->toDateString(),
            'status' => LetterStatus::Draft,
            'field_values' => ['penerima' => 'BEM Universitas'],
        ]);

        LetterNumber::create([
            'workspace_id' => $this->workspace->id,
            'letter_id' => $letter->id,
            'formatted_number' => '045/UND/FTI/XI/2026',
            'sequence' => 45,
            'period_key' => '2026',
            'year' => 2026,
            'status' => 'aktif',
            'issued_at' => now(),
        ]);

        // Cari berdasarkan kata perihal
        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', 'Kerja Akbar')
            ->assertSee('Undangan Rapat Kerja Akbar 2026')
            ->assertSee('045/UND/FTI/XI/2026')
            ->assertSee('Ke: BEM Universitas');

        // Cari berdasarkan nomor surat
        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', '045/UND')
            ->assertSee('Undangan Rapat Kerja Akbar 2026')
            ->assertSee('045/UND/FTI/XI/2026');
    }

    public function test_spotlight_searches_surat_masuk(): void
    {
        $this->actingAs($this->admin);

        SuratMasuk::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'nomor_agenda' => 'SM/2026/099',
            'nomor_surat_pengirim' => 'B-108/KEMENPORA/2026',
            'pengirim' => 'Kementerian Pemuda dan Olahraga',
            'perihal' => 'Diseminasi Program Kepemudaan',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'sifat_surat' => SifatSuratMasuk::Penting,
            'status' => StatusSuratMasuk::Baru,
        ]);

        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', 'KEMENPORA')
            ->assertSee('SM/2026/099')
            ->assertSee('Kementerian Pemuda dan Olahraga')
            ->assertSee('Diseminasi Program Kepemudaan');
    }

    public function test_spotlight_isolates_records_per_workspace(): void
    {
        $this->actingAs($this->admin);

        // Surat di workspace aktif
        Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Surat Rahasia Internal Workspace 1',
            'letter_date' => now()->toDateString(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        // Buat workspace kedua dan surat di workspace kedua
        $otherWs = Workspace::create([
            'name' => 'Organisasi Lain',
            'slug' => 'org-lain',
            'owner_id' => $this->admin->id,
        ]);

        Letter::create([
            'workspace_id' => $otherWs->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Surat Tersembunyi Workspace 2',
            'letter_date' => now()->toDateString(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', 'Workspace 2')
            ->assertDontSee('Surat Tersembunyi Workspace 2')
            ->set('search', 'Internal Workspace 1')
            ->assertSee('Surat Rahasia Internal Workspace 1');
    }

    public function test_viewer_humas_role_permissions_enforced_in_spotlight(): void
    {
        $viewer = User::factory()->create(['name' => 'Staf Humas Viewer']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::Member,
        ]);

        setPermissionsTeamId($this->workspace->id);
        $viewer->assignRole(WorkspacePermissionRegistrar::ROLE_VIEWER);

        $this->actingAs($viewer);

        // Buat template amplop (hanya untuk admin)
        EnvelopeTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Kop Amplop Khusus Pengurus',
            'preset_size' => 'dl',
            'file_path' => 'dummy.docx',
            'is_default' => true,
        ]);

        // Buat surat keluar
        Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Siaran Pers Kegiatan Kampus',
            'letter_date' => now()->toDateString(),
            'status' => LetterStatus::Draft,
            'field_values' => ['penerima' => 'Wartawan Kampus'],
        ]);

        // Viewer BISA mencari surat keluar dan melihat aksi pratinjau
        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', 'Siaran Pers')
            ->assertSee('Siaran Pers Kegiatan Kampus')
            ->assertSee('Pratinjau')
            ->assertSee('Amplop');

        // Viewer TIDAK BISA melihat menu admin (Kop Builder, Pola Penomoran, Anggota) di Quick Action
        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', '')
            ->assertDontSee('Builder Kop Surat')
            ->assertDontSee('Pola Penomoran Otomatis')
            ->assertDontSee('Anggota & Hak Akses');

        // Viewer TIDAK BISA melihat hasil template amplop
        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->set('search', 'Kop Amplop Khusus')
            ->assertDontSee('Kop Amplop Khusus Pengurus');
    }

    public function test_anti_tumpuk_quick_preview_closes_spotlight_and_opens_preview_modal(): void
    {
        $this->actingAs($this->admin);

        $letter = Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Surat Audiensi Rektorat',
            'letter_date' => now()->toDateString(),
            'status' => LetterStatus::Draft,
            'field_values' => ['penerima' => 'Rektor'],
        ]);

        Livewire::test(GlobalSearch::class)
            ->call('openSpotlight')
            ->assertSet('isOpen', true)
            ->assertSet('isPreviewOpen', false)
            ->call('openPreview', 'letter_pdf', $letter->id)
            ->assertSet('isOpen', false) // Spotlight tertutup seketika (anti-tumpuk)
            ->assertSet('isPreviewOpen', true) // Modal Pratinjau terbuka
            ->assertSet('previewType', 'letter_pdf')
            ->assertSee('Surat Audiensi Rektorat')
            ->assertSee('Unduh PDF')
            ->assertSee('Tab Baru')
            ->call('closePreview')
            ->assertSet('isPreviewOpen', false);
    }
}
