<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Livewire\Superadmin\SuperadminDashboard;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SuperadminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): array
    {
        $ws = Workspace::create([
            'name' => 'Workspace Utama',
            'slug' => 'workspace-utama',
            'number_code' => 'UTAMA',
        ]);

        $superadmin = User::factory()->create([
            'name' => 'Root Super Admin',
            'email' => 'superadmin@suratinaja.test',
            'password' => Hash::make('password123'),
            'current_workspace_id' => $ws->id,
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $superadmin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $superadmin];
    }

    public function test_non_superadmin_forbidden_from_superadmin_panel(): void
    {
        $ws = Workspace::create([
            'name' => 'Workspace Biasa',
            'slug' => 'workspace-biasa',
        ]);

        $user = User::factory()->create([
            'name' => 'User Biasa',
            'email' => 'biasa@organisasi.id',
            'current_workspace_id' => $ws->id,
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $this->actingAs($user);
        $this->get('/superadmin')->assertForbidden();
    }

    public function test_superadmin_can_access_panel_and_see_stats(): void
    {
        [$ws, $superadmin] = $this->createSuperAdmin();

        $this->actingAs($superadmin);
        $this->get('/superadmin')
            ->assertOk()
            ->assertSee('Panel Super Admin')
            ->assertSee('Total Pengguna')
            ->assertSee('Total Workspace')
            ->assertSee('Workspace Utama');
    }

    public function test_superadmin_can_create_user_manually_with_workspace(): void
    {
        [$ws, $superadmin] = $this->createSuperAdmin();
        $this->actingAs($superadmin);

        Livewire::test(SuperadminDashboard::class)
            ->call('openCreateModal')
            ->set('newName', 'Budi Staff Baru')
            ->set('newEmail', 'budi.staff@suratinaja.test')
            ->set('newPassword', 'rahasia12345')
            ->set('newWorkspaceId', $ws->id)
            ->set('newWorkspaceRole', WorkspaceRole::Secretary->value)
            ->call('createUser')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Staff Baru',
            'email' => 'budi.staff@suratinaja.test',
            'status' => 'active',
            'current_workspace_id' => $ws->id,
        ]);

        $newUser = User::where('email', 'budi.staff@suratinaja.test')->first();
        $this->assertTrue(Hash::check('rahasia12345', $newUser->password));
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $ws->id,
            'user_id' => $newUser->id,
            'role' => WorkspaceRole::Secretary->value,
        ]);
    }

    public function test_superadmin_can_toggle_suspend_status_but_cannot_suspend_self(): void
    {
        [$ws, $superadmin] = $this->createSuperAdmin();
        $this->actingAs($superadmin);

        $targetUser = User::factory()->create([
            'name' => 'Anggota Percobaan',
            'email' => 'target@percobaan.id',
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        // Suspend user
        Livewire::test(SuperadminDashboard::class)
            ->call('toggleUserStatus', $targetUser->id)
            ->assertDispatched('toast');

        $this->assertEquals('suspended', $targetUser->fresh()->status);

        // Reactivate user
        Livewire::test(SuperadminDashboard::class)
            ->call('toggleUserStatus', $targetUser->id)
            ->assertDispatched('toast');

        $this->assertEquals('active', $targetUser->fresh()->status);

        // Attempt to self-suspend -> blocked
        Livewire::test(SuperadminDashboard::class)
            ->call('toggleUserStatus', $superadmin->id)
            ->assertDispatched('toast');

        $this->assertEquals('active', $superadmin->fresh()->status);
    }

    public function test_superadmin_can_reset_password_for_user(): void
    {
        [$ws, $superadmin] = $this->createSuperAdmin();
        $this->actingAs($superadmin);

        $targetUser = User::factory()->create([
            'name' => 'User Reset',
            'email' => 'reset@percobaan.id',
            'password' => Hash::make('password_lama'),
        ]);

        Livewire::test(SuperadminDashboard::class)
            ->call('openResetPasswordModal', $targetUser->id)
            ->set('resetPassword', 'PasswordBaru999!')
            ->call('resetUserPassword')
            ->assertDispatched('toast');

        $this->assertTrue(Hash::check('PasswordBaru999!', $targetUser->fresh()->password));
    }
}
