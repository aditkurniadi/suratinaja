<?php

namespace Tests\Feature;

use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Calendar\CalendarIndex;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Letters\LettersIndex;
use App\Livewire\SuratMasuk\SuratMasukForm;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarDateSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(array $attributes = []): array
    {
        $ws = Workspace::create(array_merge([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-cal-' . uniqid(),
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{seq}/{kategori}{parameter}/{kode}/{bulan_romawi}/{tahun}',
            'default_city' => 'Cilacap',
            'is_activity' => false,
        ], $attributes));

        $user = User::factory()->create([
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin,
        ]);

        setPermissionsTeamId($ws->id);
        $role = Role::firstOrCreate(['workspace_id' => $ws->id, 'name' => WorkspacePermissionRegistrar::ROLE_ADMIN]);
        $user->assignRole($role);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $user];
    }

    public function test_letter_composer_prefills_date_from_calendar_query_param(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        session(['active_workspace_id' => $workspace->id]);

        // Case 1: ?tanggal_surat=2026-11-21
        Livewire::actingAs($user)
            ->withQueryParams(['tanggal_surat' => '2026-11-21'])
            ->test(LetterComposer::class)
            ->assertSet('letterDate', '2026-11-21')
            ->assertSee('21 November 2026');

        // Case 2: ?tanggal_kegiatan=2026-12-05
        Livewire::actingAs($user)
            ->withQueryParams(['tanggal_kegiatan' => '2026-12-05'])
            ->test(LetterComposer::class)
            ->assertSet('tanggalKegiatan', '2026-12-05')
            ->assertSee('05 Desember 2026');
    }

    public function test_surat_masuk_form_prefills_date_from_calendar_query_param(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        session(['active_workspace_id' => $workspace->id]);

        Livewire::actingAs($user)
            ->withQueryParams(['tanggal_diterima' => '2026-11-25'])
            ->test(SuratMasukForm::class)
            ->assertSet('tanggalDiterima', '2026-11-25')
            ->assertSet('tanggalSurat', '2026-11-25')
            ->assertSee('25 November 2026');

        Livewire::actingAs($user)
            ->withQueryParams(['tanggal_acara' => '2026-12-10'])
            ->test(SuratMasukForm::class)
            ->assertSet('tanggalAcaraDeadline', '2026-12-10')
            ->assertSee('10 Desember 2026');
    }

    public function test_calendar_filters_by_letter_date_and_event_date_accurately(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        session(['active_workspace_id' => $workspace->id]);

        $t = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Rapat',
            'file_path' => 'templates/test/rapat.docx',
            'variables_schema' => [],
        ]);

        // Letter: letter_date = 2026-11-10, tanggal_kegiatan = 2026-11-25
        $letter = Letter::create([
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'perihal' => 'Rapat Akbar Akhir Tahun',
            'letter_date' => '2026-11-10',
            'tanggal_kegiatan' => '2026-11-25',
            'field_values' => [],
            'status' => 'draft',
        ]);

        // Mode 1: letter_date (default)
        $component = Livewire::actingAs($user)
            ->test(CalendarIndex::class)
            ->set('year', 2026)
            ->set('month', 11)
            ->set('dateReference', 'letter_date');

        $component->assertSee('Rapat Akbar Akhir Tahun');

        // Mode 2: event_date
        $component->set('dateReference', 'event_date');
        $component->assertSee('Rapat Akbar Akhir Tahun');
    }

    public function test_letters_index_filters_by_bulk_job_id(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        session(['active_workspace_id' => $workspace->id]);

        $t = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Undangan',
            'file_path' => 'templates/test/undangan.docx',
            'variables_schema' => [],
        ]);

        $bulkJob = \App\Models\BulkJob::create([
            'id' => 'bulk-abc-123',
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'perihal' => 'Bulk Job ABC',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [],
            'status' => \App\Enums\BulkJobStatus::Done,
            'recipient_count' => 1,
            'processed_count' => 1,
        ]);

        $letter1 = Letter::create([
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
            'bulk_job_id' => 'bulk-abc-123',
            'perihal' => 'Surat Undangan Batch ABC',
        ]);

        $letter2 = Letter::create([
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
            'bulk_job_id' => null,
            'perihal' => 'Surat Biasa Non-Batch',
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['bulk_job_id' => 'bulk-abc-123'])
            ->test(LettersIndex::class)
            ->assertSee('Surat Undangan Batch ABC')
            ->assertDontSee('Surat Biasa Non-Batch')
            ->assertSee('bulk-abc');
    }
}
