<?php

namespace Tests\Feature;

use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Settings\MembersIndex;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleTemplate(?Workspace $ws = null): LetterTemplate
    {
        $rel = 'templates/test/sample.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        return LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws?->id,
            'scope' => $ws ? TemplateScope::Workspace : TemplateScope::Global,
            'name' => 'Template Uji',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);
    }

    public function test_non_member_receives_403_ws_forbidden_on_workspace_api(): void
    {
        $wsA = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $wsB = Workspace::create(['name' => 'OASIS', 'number_code' => 'OASIS']);

        $outsider = User::factory()->create(['current_workspace_id' => $wsB->id]);
        WorkspaceMember::create([
            'workspace_id' => $wsB->id,
            'user_id' => $outsider->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/workspaces/{$wsA->id}/letters");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'WS_FORBIDDEN');
    }

    public function test_chairman_can_read_but_cannot_write_letters_ac_01_01(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $chairman = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $chairman->id,
            'role' => WorkspaceRole::Chairman,
        ]);

        $tpl = $this->createSampleTemplate();

        // Read allowed
        $this->actingAs($chairman)
            ->getJson("/api/v1/workspaces/{$ws->id}/letters")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Write rejected with 403 WS_FORBIDDEN
        $this->actingAs($chairman)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters", [
                'template_id' => $tpl->id,
                'perihal' => 'Test Ketua',
                'letter_date' => '2025-06-15',
                'variables' => [
                    'penerima' => 'Dekan',
                    'tempat_tujuan' => 'Kampus',
                    'nama_kegiatan' => 'Rapat',
                    'tanggal_kegiatan' => '2025-06-20',
                ],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'WS_FORBIDDEN');
    }

    public function test_revoked_member_immediately_loses_access_ac_01_03(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        $secretary = User::factory()->create(['current_workspace_id' => $ws->id]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);
        $secMember = WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $secretary->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $this->actingAs($secretary)
            ->getJson("/api/v1/workspaces/{$ws->id}/letters")
            ->assertStatus(200);

        $this->actingAs($admin);
        Livewire::test(MembersIndex::class)
            ->call('revoke', $secMember->id);

        $this->actingAs($secretary->fresh())
            ->getJson("/api/v1/workspaces/{$ws->id}/letters")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'WS_FORBIDDEN');
    }

    public function test_duplicate_workspace_membership_is_rejected(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        $member = User::factory()->create(['email' => 'dup@himatris.org']);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $member->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $this->actingAs($admin);
        Livewire::test(MembersIndex::class)
            ->set('inviteEmail', 'dup@himatris.org')
            ->set('inviteRole', 'secretary')
            ->call('invite')
            ->assertHasErrors(['inviteEmail']);
    }
}
