<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Livewire\Settings\MembersIndex;
use App\Livewire\Settings\RolesManager;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceRoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(WorkspacePermissionRegistrar::class)->syncGlobalPermissions();
    }

    public function test_workspace_creation_automatically_seeds_default_roles(): void
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS Test',
            'slug' => 'himatris-test',
        ]);

        setPermissionsTeamId($ws->id);
        $roles = Role::where('workspace_id', $ws->id)->pluck('name')->all();

        $this->assertContains(WorkspacePermissionRegistrar::ROLE_ADMIN, $roles);
        $this->assertContains(WorkspacePermissionRegistrar::ROLE_SECRETARY, $roles);
        $this->assertContains(WorkspacePermissionRegistrar::ROLE_CHAIRMAN, $roles);
        $this->assertContains(WorkspacePermissionRegistrar::ROLE_MEMBER, $roles);

        $adminRole = Role::where('workspace_id', $ws->id)->where('name', WorkspacePermissionRegistrar::ROLE_ADMIN)->first();
        $this->assertCount(count(WorkspacePermissionRegistrar::allPermissionKeys()), $adminRole->permissions);
    }

    public function test_user_can_have_different_roles_in_different_workspaces(): void
    {
        $ws1 = Workspace::create(['name' => 'Workspace A', 'slug' => 'ws-a']);
        $ws2 = Workspace::create(['name' => 'Workspace B', 'slug' => 'ws-b']);

        $user = User::factory()->create(['current_workspace_id' => $ws1->id]);

        WorkspaceMember::create(['workspace_id' => $ws1->id, 'user_id' => $user->id, 'role' => WorkspaceRole::Admin]);
        WorkspaceMember::create(['workspace_id' => $ws2->id, 'user_id' => $user->id, 'role' => WorkspaceRole::Chairman]);

        // Assign Admin in WS 1
        setPermissionsTeamId($ws1->id);
        $adminRole = Role::where('workspace_id', $ws1->id)->where('name', WorkspacePermissionRegistrar::ROLE_ADMIN)->first();
        $user->syncRoles([$adminRole]);

        // Assign Member in WS 2
        setPermissionsTeamId($ws2->id);
        $memberRole = Role::where('workspace_id', $ws2->id)->where('name', WorkspacePermissionRegistrar::ROLE_MEMBER)->first();
        $user->syncRoles([$memberRole]);

        // Check permissions in WS 1
        setPermissionsTeamId($ws1->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($user->can('kop_surat.manage'));
        $this->assertTrue($user->can('role.manage'));

        // Check permissions in WS 2
        setPermissionsTeamId($ws2->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse($user->can('kop_surat.manage'));
        $this->assertFalse($user->can('role.manage'));
        $this->assertTrue($user->can('surat_masuk.view'));
    }

    public function test_super_admin_bypasses_all_permission_gates(): void
    {
        $ws = Workspace::create(['name' => 'Workspace Test', 'slug' => 'ws-test']);
        $superAdmin = User::factory()->create([
            'current_workspace_id' => $ws->id,
            'is_super_admin' => true,
        ]);

        setPermissionsTeamId($ws->id);
        $this->actingAs($superAdmin);

        $this->assertTrue($superAdmin->can('role.manage'));
        $this->assertTrue($superAdmin->can('kop_surat.manage'));
        $this->assertTrue($superAdmin->can('surat_keluar.acc'));
        $this->assertTrue($superAdmin->can('non_existent.permission'));
    }

    public function test_custom_role_crud_and_permission_matrix(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS Organisasi', 'slug' => 'himatris-org']);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $admin->id, 'role' => WorkspaceRole::Admin]);

        setPermissionsTeamId($ws->id);
        $adminRole = Role::where('workspace_id', $ws->id)->where('name', WorkspacePermissionRegistrar::ROLE_ADMIN)->first();
        $admin->syncRoles([$adminRole]);

        $this->actingAs($admin);

        // 1. Create custom role "Staf Dokumentasi" with specific permissions
        Livewire::test(RolesManager::class)
            ->call('openCreateModal')
            ->set('roleName', 'Staf Dokumentasi')
            ->set('selectedPermissions', ['surat_masuk.view', 'surat_masuk.create'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $customRole = Role::where('workspace_id', $ws->id)->where('name', 'Staf Dokumentasi')->first();
        $this->assertNotNull($customRole);
        $this->assertCount(2, $customRole->permissions);

        // 2. Toggle group and update permissions
        Livewire::test(RolesManager::class)
            ->call('openEditModal', $customRole->id)
            ->call('toggleGroup', 'disposisi')
            ->call('saveRole')
            ->assertHasNoErrors();

        $customRole->refresh();
        $this->assertTrue($customRole->hasPermissionTo('disposisi.view'));
        $this->assertTrue($customRole->hasPermissionTo('disposisi.create'));

        // 3. Delete custom role
        Livewire::test(RolesManager::class)
            ->call('deleteRole', $customRole->id);

        $this->assertNull(Role::where('workspace_id', $ws->id)->where('name', 'Staf Dokumentasi')->first());
    }

    public function test_members_index_assign_role_and_prevents_demoting_last_admin(): void
    {
        $ws = Workspace::create(['name' => 'BEM Politeknik', 'slug' => 'bem-politeknik']);
        $adminUser = User::factory()->create(['current_workspace_id' => $ws->id]);
        $memberUser = User::factory()->create(['current_workspace_id' => $ws->id]);

        $adminMember = WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $adminUser->id, 'role' => WorkspaceRole::Admin]);
        $secMember = WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $memberUser->id, 'role' => WorkspaceRole::Secretary]);

        setPermissionsTeamId($ws->id);
        $adminRole = Role::where('workspace_id', $ws->id)->where('name', WorkspacePermissionRegistrar::ROLE_ADMIN)->first();
        $secRole = Role::where('workspace_id', $ws->id)->where('name', WorkspacePermissionRegistrar::ROLE_SECRETARY)->first();

        $adminUser->syncRoles([$adminRole]);
        $memberUser->syncRoles([$secRole]);

        $this->actingAs($adminUser);

        // Update secondary member to Ketua / Pimpinan
        Livewire::test(MembersIndex::class)
            ->call('updateRole', $secMember->id, WorkspacePermissionRegistrar::ROLE_CHAIRMAN)
            ->assertHasNoErrors();

        setPermissionsTeamId($ws->id);
        $this->assertTrue($memberUser->fresh()->hasRole(WorkspacePermissionRegistrar::ROLE_CHAIRMAN));

        // Attempt to demote the sole admin -> must be blocked
        Livewire::test(MembersIndex::class)
            ->call('updateRole', $adminMember->id, WorkspacePermissionRegistrar::ROLE_MEMBER);

        $this->assertTrue($adminUser->fresh()->hasRole(WorkspacePermissionRegistrar::ROLE_ADMIN));
    }
}
