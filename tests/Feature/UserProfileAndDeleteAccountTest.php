<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Livewire\Profile\ProfileIndex;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileAndDeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): array
    {
        $ws = Workspace::create([
            'name' => 'Org Pengguna',
            'slug' => 'org-pengguna',
        ]);

        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@organisasi.id',
            'password' => 'password123',
            'current_workspace_id' => $ws->id,
            'is_super_admin' => false,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Member,
        ]);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $user];
    }

    public function test_user_can_update_profile_info(): void
    {
        [$ws, $user] = $this->setupUser();
        $this->actingAs($user);

        Livewire::test(ProfileIndex::class)
            ->set('name', 'Budi Santoso M.Kom')
            ->set('email', 'budi.baru@organisasi.id')
            ->call('updateProfile')
            ->assertSee('Informasi profil berhasil diperbarui');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Budi Santoso M.Kom',
            'email' => 'budi.baru@organisasi.id',
        ]);
    }

    public function test_user_can_update_password_with_current_password_check(): void
    {
        [$ws, $user] = $this->setupUser();
        $this->actingAs($user);

        // Wrong current password
        Livewire::test(ProfileIndex::class)
            ->set('current_password', 'passwordsalah')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        // Correct current password
        Livewire::test(ProfileIndex::class)
            ->set('current_password', 'password123')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('updatePassword')
            ->assertSee('Kata sandi akun Anda berhasil diperbarui');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_user_can_delete_own_account_with_password_confirmation(): void
    {
        [$ws, $user] = $this->setupUser();

        // Ensure another admin exists so user can delete account
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $this->actingAs($user);

        // Wrong password
        Livewire::test(ProfileIndex::class)
            ->set('delete_password', 'passwordsalah')
            ->call('deleteAccount')
            ->assertHasErrors(['delete_password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Correct password
        Livewire::test(ProfileIndex::class)
            ->set('delete_password', 'password123')
            ->call('deleteAccount')
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('workspace_members', ['user_id' => $user->id]);
    }

    public function test_sole_superadmin_cannot_delete_account(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(ProfileIndex::class)
            ->set('delete_password', 'password123')
            ->call('deleteAccount')
            ->assertSee('satu-satunya Super Admin');

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }
}
