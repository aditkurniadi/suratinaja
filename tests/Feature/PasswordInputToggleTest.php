<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordInputToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_password_input_with_eye_toggle(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('showPassword', false);
        $response->assertSee('Lihat kata sandi', false);
    }

    public function test_register_page_renders_password_input_with_eye_toggle(): void
    {
        config(['suratinaja.allow_public_registration' => true]);

        $response = $this->get(route('register'));
        $response->assertStatus(200);
        $response->assertSee('showPassword', false);
        $response->assertSee('Minimal 8 karakter', false);
    }

    public function test_accept_invitation_renders_password_input_with_eye_toggle(): void
    {
        $ws = Workspace::create([
            'name' => 'Organisasi Baru',
            'slug' => 'org-baru',
        ]);

        $inviter = User::factory()->create([
            'current_workspace_id' => $ws->id,
        ]);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $ws->id,
            'email' => 'calon@organisasi.id',
            'role' => 'admin',
            'token' => 'test-token-eye-12345678',
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get(route('invitations.accept', ['token' => $invitation->token]));
        $response->assertStatus(200);
        $response->assertSee('showPassword', false);
        $response->assertSee('Ulangi kata sandi', false);
    }
}
