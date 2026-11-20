<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Livewire\Bulk\BulkGenerator;
use App\Livewire\Calendar\CalendarIndex;
use App\Livewire\Letters\LettersIndex;
use App\Livewire\SuratMasuk\SuratMasukIndex;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Enums\WorkspaceRole;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResponsiveDualViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Responsive Test Org',
            'slug' => 'resp-org',
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

    public function test_calendar_index_supports_mobile_date_selection_and_renders_dual_views(): void
    {
        $this->actingAs($this->admin);

        Letter::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => LetterStatus::Draft,
            'letter_date' => now()->toDateString(),
            'perihal' => 'Undangan Rapat Responsif',
            'field_values' => ['penerima' => 'BEM Fakultas'],
        ]);

        SuratMasuk::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'nomor_agenda' => 'SM/001',
            'nomor_surat_pengirim' => '01/BEM/2026',
            'pengirim' => 'DPM Fakultas',
            'perihal' => 'Peminjaman Aula Bersama',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'sifat_surat' => SifatSuratMasuk::Biasa,
            'status' => StatusSuratMasuk::Baru,
        ]);

        $component = Livewire::test(CalendarIndex::class)
            ->assertSee('hidden md:block')
            ->assertSee('block md:hidden')
            ->assertSee('Agenda Surat')
            ->assertSee('Undangan Rapat Responsif')
            ->assertSee('Peminjaman Aula Bersama');

        $component->call('selectMobileDate', now()->addDay()->toDateString())
            ->assertSet('selectedMobileDate', now()->addDay()->toDateString());
    }

    public function test_letters_index_renders_desktop_table_and_mobile_cards(): void
    {
        $this->actingAs($this->admin);

        Letter::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'status' => LetterStatus::Draft,
            'letter_date' => now()->toDateString(),
            'perihal' => 'Surat Pengantar Dual View',
            'field_values' => [],
        ]);

        Livewire::test(LettersIndex::class)
            ->assertSee('hidden md:block')
            ->assertSee('block md:hidden')
            ->assertSee('Surat Pengantar Dual View');
    }

    public function test_surat_masuk_index_renders_desktop_table_and_mobile_cards(): void
    {
        $this->actingAs($this->admin);

        SuratMasuk::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'nomor_agenda' => 'SM/RESP/01',
            'nomor_surat_pengirim' => 'INV/009',
            'pengirim' => 'Kementerian Sosial',
            'perihal' => 'Surat Masuk Dual View',
            'tanggal_surat' => now()->toDateString(),
            'tanggal_diterima' => now()->toDateString(),
            'sifat_surat' => SifatSuratMasuk::Penting,
            'status' => StatusSuratMasuk::Baru,
        ]);

        Livewire::test(SuratMasukIndex::class)
            ->assertSee('hidden md:block')
            ->assertSee('block md:hidden')
            ->assertSee('SM/RESP/01')
            ->assertSee('Surat Masuk Dual View');
    }

    public function test_bulk_generator_renders_desktop_table_and_mobile_cards(): void
    {
        $this->actingAs($this->admin);

        $tpl = LetterTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sertifikat Massal',
            'file_path' => 'templates/test.docx',
            'scope' => 'workspace',
            'variables_schema' => [],
        ]);

        BulkJob::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
            'template_id' => $tpl->id,
            'perihal' => 'Sertifikat Kegiatan Dual View',
            'letter_date' => now()->toDateString(),
            'shared_variables' => ['perihal' => 'Sertifikat Kegiatan Dual View'],
            'recipients' => [['nama' => 'Penerima 1']],
            'status' => 'done',
            'recipient_count' => 10,
            'processed_count' => 10,
            'payload' => ['perihal' => 'Sertifikat Kegiatan Dual View'],
        ]);

        Livewire::test(BulkGenerator::class)
            ->assertSee('hidden lg:block')
            ->assertSee('block lg:hidden')
            ->assertSee('Sertifikat Kegiatan Dual View');
    }
}
