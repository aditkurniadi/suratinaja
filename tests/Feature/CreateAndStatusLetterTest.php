<?php

namespace Tests\Feature;

use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateAndStatusLetterTest extends TestCase
{
    use RefreshDatabase;

    private function seedWorkspaceWithTemplate(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris',
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'HIMATRIS',
            'seq_padding' => 3,
        ]);

        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $rel = 'templates/test/create-letter.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Undangan Koordinasi',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        return [$ws, $sec, $tpl];
    }

    public function test_create_letter_endpoint_allocates_number_and_validates_required_variables_task_05(): void
    {
        [$ws, $sec, $tpl] = $this->seedWorkspaceWithTemplate();

        // Missing required variable -> 422 TEMPLATE_INVALID
        $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters", [
                'template_id' => $tpl->id,
                'perihal' => 'Undangan Rapat',
                'letter_date' => '2025-06-15',
                'variables' => [
                    'penerima' => 'Dr. Budi',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TEMPLATE_INVALID');

        // Valid request -> 201 with formatted number 001/HIMATRIS/VI/2025
        $response = $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters", [
                'template_id' => $tpl->id,
                'perihal' => 'Undangan Rapat Koordinasi',
                'letter_date' => '2025-06-15',
                'variables' => [
                    'penerima' => 'Dr. Budi Santoso',
                    'tempat_tujuan' => 'Fakultas Teknik',
                    'nama_kegiatan' => 'Rapat Koordinasi HIMATRIS',
                    'tanggal_kegiatan' => '2025-06-20',
                    'lampiran' => [
                        ['nama_barang' => 'Proyektor', 'jumlah' => '2'],
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.letter_number', '001/HIMATRIS/VI/2025')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_status_state_machine_enforces_legal_transitions_and_sets_signed_at_task_06(): void
    {
        [$ws, $sec, $tpl] = $this->seedWorkspaceWithTemplate();

        $createRes = $this->actingAs($sec)->postJson("/api/v1/workspaces/{$ws->id}/letters", [
            'template_id' => $tpl->id,
            'perihal' => 'Surat Keputusan',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'Anggota',
                'tempat_tujuan' => 'Sekretariat',
                'nama_kegiatan' => 'Pelantikan',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ])->assertStatus(201);

        $letterId = $createRes->json('data.id');

        // Illegal jump draft -> selesai must fail with 422 (AC-04-02)
        $this->actingAs($sec)
            ->patchJson("/api/v1/workspaces/{$ws->id}/letters/{$letterId}/status", [
                'status' => 'selesai',
            ])
            ->assertStatus(422);

        // Legal: draft -> menunggu_ttd -> selesai
        $this->actingAs($sec)
            ->patchJson("/api/v1/workspaces/{$ws->id}/letters/{$letterId}/status", [
                'status' => 'menunggu_ttd',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'menunggu_ttd');

        $finishRes = $this->actingAs($sec)
            ->patchJson("/api/v1/workspaces/{$ws->id}/letters/{$letterId}/status", [
                'status' => 'selesai',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        $this->assertNotNull($finishRes->json('data.signed_at'));

        // Selesai cannot revert to draft via status endpoint (AC-04-03)
        $this->actingAs($sec)
            ->patchJson("/api/v1/workspaces/{$ws->id}/letters/{$letterId}/status", [
                'status' => 'draft',
            ])
            ->assertStatus(422);
    }

    public function test_letter_index_filters_by_status_and_search_and_isolates_workspaces_task_07(): void
    {
        [$wsA, $secA, $tplA] = $this->seedWorkspaceWithTemplate();
        $wsB = Workspace::create(['name' => 'OASIS', 'number_code' => 'OASIS']);
        WorkspaceMember::create([
            'workspace_id' => $wsB->id,
            'user_id' => $secA->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        // Create 2 letters in wsA and 1 in wsB
        $this->actingAs($secA)->postJson("/api/v1/workspaces/{$wsA->id}/letters", [
            'template_id' => $tplA->id,
            'perihal' => 'Undangan Seminar Nasional',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'A',
                'tempat_tujuan' => 'B',
                'nama_kegiatan' => 'C',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ])->assertStatus(201);

        $l2 = $this->actingAs($secA)->postJson("/api/v1/workspaces/{$wsA->id}/letters", [
            'template_id' => $tplA->id,
            'perihal' => 'Permohonan Dana Sponsor',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'A',
                'tempat_tujuan' => 'B',
                'nama_kegiatan' => 'C',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ])->json('data.id');

        $this->actingAs($secA)->patchJson("/api/v1/workspaces/{$wsA->id}/letters/{$l2}/status", ['status' => 'menunggu_ttd']);
        $this->actingAs($secA)->patchJson("/api/v1/workspaces/{$wsA->id}/letters/{$l2}/status", ['status' => 'selesai']);

        // Filter status=selesai & search=sponsor
        $res = $this->actingAs($secA)
            ->getJson("/api/v1/workspaces/{$wsA->id}/letters?status=selesai&search=Sponsor")
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Permohonan Dana Sponsor', $res->json('data.0.perihal'));

        // Workspace B has 0 letters (strict isolation)
        $resB = $this->actingAs($secA)
            ->getJson("/api/v1/workspaces/{$wsB->id}/letters")
            ->assertStatus(200);

        $this->assertCount(0, $resB->json('data'));
    }

    public function test_letter_composer_drag_and_drop_reorder_and_resilient_deletion(): void
    {
        [$ws, $sec, $tpl] = $this->seedWorkspaceWithTemplate();
        $this->actingAs($sec);

        $presets = Workspace::signerPresets();
        $signers = $presets['5_signers']['signers'];

        $test = \Livewire\Livewire::test(\App\Livewire\Letters\LetterComposer::class)
            ->set('templateId', $tpl->id)
            ->set('perihal', 'Uji Reorder dan Hapus TTD')
            ->set('letterDate', '2026-06-15')
            ->set('signers', $signers)
            ->set('values', [
                'penerima' => 'Ketua Jurusan',
                'tempat_tujuan' => 'Kampus',
                'nama_kegiatan' => 'Makrab TRPL',
                'tanggal_kegiatan' => '2026-06-20',
            ]);

        // Test Move Signer (drag & drop reorder)
        $test->call('moveSigner', 0, 1) // Swap Ketua HIMATRIS and Ketua Pelaksana
            ->assertSet('signers.0.position', 'Ketua Pelaksana,')
            ->assertSet('signers.1.position', 'Ketua HIMATRIS,');

        // Test Move Up
        $test->call('moveSignerUp', 1)
            ->assertSet('signers.0.position', 'Ketua HIMATRIS,')
            ->assertSet('signers.1.position', 'Ketua Pelaksana,');

        // Test Deletion from 5 -> 4
        $test->call('removeSigner', 4) // Remove 5th
            ->assertCount('signers', 4);

        // Test Deletion from 4 -> 3
        $test->call('removeSigner', 3) // Remove 4th
            ->assertCount('signers', 3);

        // Save draft and ensure it renders properly
        $test->call('save')
            ->assertHasNoErrors();

        $letter = \App\Models\Letter::where('perihal', 'Uji Reorder dan Hapus TTD')->firstOrFail();
        $this->assertCount(3, $letter->field_values['_signers']);

        // Verify PDF preview generation with 3 signers (1 pair + 1 center)
        $pdfService = app(\App\Services\Documents\PdfPreviewService::class);
        $res = $pdfService->ensurePreviewPdf($letter);
        $this->assertTrue($res['ok']);
        $this->assertNotNull($letter->preview_pdf_path);

        // Now test updating an existing letter whose signers array has NO 'layout' key
        $letter->field_values = [
            'penerima' => 'Ketua Jurusan',
            'tempat_tujuan' => 'Kampus',
            'nama_kegiatan' => 'Makrab TRPL',
            'tanggal_kegiatan' => '2026-06-20',
            '_signers' => [
                ['prefix' => '', 'position' => 'Ketua HIMATRIS,', 'name' => 'Dimas', 'id_label' => 'NIM.', 'id_number' => '123'],
                ['prefix' => '', 'position' => 'Ketua Pelaksana,', 'name' => 'Karisma', 'id_label' => 'NIM.', 'id_number' => '456'],
            ],
        ];
        $letter->saveQuietly();

        \Livewire\Livewire::test(\App\Livewire\Letters\LetterComposer::class, ['letter' => $letter])
            ->set('perihal', 'Perihal Diperbarui')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('letters.show', $letter->id));

        $letter->refresh();
        $this->assertSame('Perihal Diperbarui', $letter->perihal);
    }
}
