<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Livewire\Auth\Login;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class InviteOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkspaceAndUser(string $status = 'active'): array
    {
        $ws = Workspace::create([
            'name' => 'Organisasi Tes',
            'slug' => 'organisasi-tes',
        ]);

        $user = User::factory()->create([
            'name' => 'Pengguna Tes',
            'email' => 'user@tes.id',
            'password' => Hash::make('password123'),
            'current_workspace_id' => $ws->id,
            'status' => $status,
            'is_super_admin' => false,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Member,
        ]);

        return [$ws, $user];
    }

    public function test_public_registration_disabled_by_default_redirects_to_login(): void
    {
        config(['suratinaja.allow_public_registration' => false]);

        $response = $this->get('/register');
        $response->assertRedirect(route('login'));

        $this->get('/login')
            ->assertSee('Pendaftaran publik sedang ditutup');
    }

    public function test_login_page_hides_register_link_when_registration_disabled(): void
    {
        config(['suratinaja.allow_public_registration' => false]);

        $this->get('/login')
            ->assertDontSee('Belum punya akun? Daftar');
    }

    public function test_public_registration_enabled_allows_register_access(): void
    {
        config(['suratinaja.allow_public_registration' => true]);

        $this->get('/login')
            ->assertSee('Belum punya akun? Daftar');

        $this->get('/register')
            ->assertOk();
    }

    public function test_suspended_user_cannot_login(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser(status: 'suspended');

        Livewire::test(Login::class)
            ->set('email', 'user@tes.id')
            ->set('password', 'password123')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_active_session_is_kicked_out_when_user_becomes_suspended(): void
    {
        [$ws, $user] = $this->createWorkspaceAndUser(status: 'active');
        app(CurrentWorkspace::class)->set($ws);

        $this->actingAs($user);
        $this->get('/dashboard')->assertOk();

        // Admin suspends user
        $user->forceFill(['status' => 'suspended'])->save();

        // User makes next request -> kicked out to login
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
