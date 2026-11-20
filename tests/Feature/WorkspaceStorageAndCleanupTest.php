<?php

namespace Tests\Feature;

use App\Actions\Workspaces\DeleteWorkspaceAction;
use App\Enums\WorkspaceRole;
use App\Livewire\Admin\WorkspacesIndex;
use App\Livewire\Settings\GeneralSettings;
use App\Models\Letter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Workspaces\WorkspaceStorageService;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceStorageAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(string $name = 'HIMATRIS'): array
    {
        $ws = Workspace::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'number_code' => $name,
            'number_pattern' => '{seq}/{kategori}/{kode}/{tahun}',
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin ' . $name,
            'email' => 'admin@' . \Illuminate\Support\Str::slug($name) . '.org',
            'current_workspace_id' => $ws->id,
            'is_super_admin' => false,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $admin];
    }

    public function test_workspace_storage_service_calculates_and_cleans_temporary_previews(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$ws, $admin] = $this->setupWorkspace('Org Satu');
        $service = app(WorkspaceStorageService::class);

        // Put dummy preview PDF
        $previewPath = "previews/{$ws->id}/letter-1-v1.pdf";
        Storage::disk('local')->put($previewPath, 'Dummy PDF content for preview testing');

        // Put dummy permanent template (should NOT be deleted)
        $templatePath = "templates/{$ws->id}/template-1.docx";
        Storage::disk('local')->put($templatePath, 'Dummy Template Docx');

        $usage = $service->calculateUsage($ws);
        $this->assertGreaterThan(0, $usage['previews_bytes']);
        $this->assertEquals(1, $usage['previews_count']);

        // Clean temporary files
        $result = $service->clearTemporaryFiles($ws);
        $this->assertEquals(1, $result['files_deleted']);

        // Preview should be deleted
        Storage::disk('local')->assertMissing($previewPath);

        // Template should be PRESERVED
        Storage::disk('local')->assertExists($templatePath);
    }

    public function test_admin_can_update_workspace_identity_and_clear_cache_via_livewire(): void
    {
        Storage::fake('local');
        [$ws, $admin] = $this->setupWorkspace('Org Asli');

        $this->actingAs($admin);

        Livewire::test(GeneralSettings::class)
            ->set('name', 'Org Diperbarui')
            ->set('number_code', 'ORGBARU')
            ->call('saveIdentity')
            ->assertSee('Identitas workspace berhasil diperbarui');

        $this->assertDatabaseHas('workspaces', [
            'id' => $ws->id,
            'name' => 'Org Diperbarui',
            'number_code' => 'ORGBARU',
        ]);
    }

    public function test_workspace_deletion_requires_exact_name_match(): void
    {
        [$ws1, $admin] = $this->setupWorkspace('Workspace Utama');
        [$ws2, $other] = $this->setupWorkspace('Workspace Cadangan');

        app(CurrentWorkspace::class)->set($ws1);
        $this->actingAs($admin);

        // Wrong name
        Livewire::test(GeneralSettings::class)
            ->call('openDeleteModal')
            ->set('confirmWorkspaceName', 'Salah Nama')
            ->call('deleteWorkspace')
            ->assertSee('Nama workspace yang diketik tidak cocok');

        $this->assertDatabaseHas('workspaces', ['id' => $ws1->id]);

        // Exact name match
        Livewire::test(GeneralSettings::class)
            ->call('openDeleteModal')
            ->set('confirmWorkspaceName', 'Workspace Utama')
            ->call('deleteWorkspace')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('workspaces', ['id' => $ws1->id]);
    }

    public function test_superadmin_can_view_and_delete_workspace_from_admin_panel(): void
    {
        [$ws1, $user1] = $this->setupWorkspace('Workspace Satu');
        [$ws2, $user2] = $this->setupWorkspace('Workspace Dua');

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'current_workspace_id' => $ws1->id,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(WorkspacesIndex::class)
            ->assertSee('Workspace Satu')
            ->assertSee('Workspace Dua')
            ->call('confirmDelete', $ws2->id)
            ->set('confirmWorkspaceName', 'Workspace Dua')
            ->call('deleteWorkspace')
            ->assertSee('berhasil dihapus permanen');

        $this->assertDatabaseMissing('workspaces', ['id' => $ws2->id]);
    }
}
