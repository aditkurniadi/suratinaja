<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Livewire\Settings\MembersIndex;
use App\Livewire\Workspaces\AcceptInvitation;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-inv',
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{seq}/{kategori}{parameter}/{kode}/{bulan_romawi}/{tahun}',
        ]);

        $user = User::factory()->create([
            'name' => 'Admin HIMATRIS',
            'email' => 'admin@himatris.id',
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

    public function test_admin_can_invite_member_and_mail_is_sent(): void
    {
        Mail::fake();
        [$ws, $admin] = $this->setupWorkspace();

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->set('inviteEmail', 'sekretaris.baru@himatris.id')
            ->set('inviteRole', WorkspacePermissionRegistrar::ROLE_SECRETARY)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSee('sekretaris.baru@himatris.id');

        Mail::assertSent(WorkspaceInvitationMail::class, function ($mail) {
            return $mail->hasTo('sekretaris.baru@himatris.id');
        });

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $ws->id,
            'email' => 'sekretaris.baru@himatris.id',
            'role' => WorkspacePermissionRegistrar::ROLE_SECRETARY,
            'status' => 'pending',
        ]);
    }

    public function test_resend_and_revoke_invitation(): void
    {
        Mail::fake();
        [$ws, $admin] = $this->setupWorkspace();

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $ws->id,
            'invited_by' => $admin->id,
            'email' => 'pengurus@himatris.id',
            'role' => WorkspacePermissionRegistrar::ROLE_SECRETARY,
            'token' => 'test-token-12345',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('resendInvitation', $invitation->id)
            ->assertHasNoErrors();

        Mail::assertSent(WorkspaceInvitationMail::class);

        Livewire::actingAs($admin)
            ->test(MembersIndex::class)
            ->call('revokeInvitation', $invitation->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('workspace_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_new_user_can_register_and_join_workspace_via_invitation(): void
    {
        [$ws, $admin] = $this->setupWorkspace();

        $roleSekretaris = Role::firstOrCreate([
            'workspace_id' => $ws->id,
            'name' => WorkspacePermissionRegistrar::ROLE_SECRETARY,
        ]);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $ws->id,
            'invited_by' => $admin->id,
            'email' => 'baru@himatris.id',
            'role' => WorkspacePermissionRegistrar::ROLE_SECRETARY,
            'token' => 'new-user-token-999',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::test(AcceptInvitation::class, ['token' => 'new-user-token-999'])
            ->assertSee('baru@himatris.id')
            ->assertSee('Daftar & Terima Undangan')
            ->set('name', 'Budi Baru')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('registerAndAccept')
            ->assertRedirect(route('dashboard'));

        $newUser = User::where('email', 'baru@himatris.id')->firstOrFail();
        $this->assertEquals('Budi Baru', $newUser->name);
        $this->assertEquals($ws->id, $newUser->current_workspace_id);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $ws->id,
            'user_id' => $newUser->id,
        ]);

        setPermissionsTeamId($ws->id);
        $this->assertTrue($newUser->hasRole(WorkspacePermissionRegistrar::ROLE_SECRETARY));

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
    }
}
