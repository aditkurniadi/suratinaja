<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Services\Templates\EnvelopeStarterDocxBuilder;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnvelopeDocxOnlyOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(array $attributes = []): array
    {
        $ws = Workspace::create(array_merge([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-env-' . uniqid(),
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{seq}/{kategori}{parameter}/{kode}/{bulan_romawi}/{tahun}',
            'default_city' => 'Cilacap',
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

    public function test_starter_template_builder_creates_valid_dl_c5_a4_docx(): void
    {
        Storage::fake('local');
        [$workspace] = $this->setupWorkspace();
        $builder = new EnvelopeStarterDocxBuilder();

        $dlPath = $builder->build($workspace, 'dl', 'Template DL');
        $this->assertTrue(Storage::disk('local')->exists($dlPath));

        $c5Path = $builder->build($workspace, 'c5', 'Template C5');
        $this->assertTrue(Storage::disk('local')->exists($c5Path));

        $a4Path = $builder->build($workspace, 'a4', 'Template A4');
        $this->assertTrue(Storage::disk('local')->exists($a4Path));
    }

    public function test_user_can_view_envelope_templates_index_and_set_default(): void
    {
        Storage::fake('local');
        [$workspace, $user] = $this->setupWorkspace();

        $t1 = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Template 1',
            'preset_size' => 'dl',
            'file_path' => 'dummy1.docx',
            'is_default' => true,
            'version' => 1,
        ]);

        $t2 = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Template 2',
            'preset_size' => 'c5',
            'file_path' => 'dummy2.docx',
            'is_default' => false,
            'version' => 1,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('settings.envelopes'));

        $response->assertOk()
            ->assertSee('Template 1')
            ->assertSee('Template 2')
            ->assertSee('Master Template Kop Amplop (.docx)');
    }

    public function test_single_letter_envelope_preview_and_download_flow(): void
    {
        Storage::fake('local');
        [$workspace, $user] = $this->setupWorkspace([
            'kop_header_text' => "HIMPUNAN MAHASISWA\nPOLITEKNIK NEGERI",
        ]);

        $builder = new EnvelopeStarterDocxBuilder();
        $templatePath = $builder->build($workspace, 'dl', 'Template DL Standar');

        $envelopeTemplate = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Template DL Standar',
            'preset_size' => 'dl',
            'file_path' => $templatePath,
            'is_default' => true,
            'version' => 1,
        ]);

        $letter = Letter::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'perihal' => 'Undangan Rapat Koordinasi',
            'letter_date' => now()->toDateString(),
            'status' => 'draft',
            'field_values' => [
                'penerima' => 'Dr. H. Ahmad Sudrajat, M.Kom',
                'instansi' => 'Jurusan Teknik Informatika',
                'alamat' => 'Gedung A Lt. 2 Kampus Politeknik',
                'tujuan' => 'Cilacap',
            ],
        ]);

        $previewResponse = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('letters.envelope.preview', [
                'letter' => $letter->id,
                'template_id' => $envelopeTemplate->id,
            ]));

        $previewResponse->assertOk();
        $this->assertEquals('application/pdf', $previewResponse->headers->get('content-type'));

        $downloadDocx = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('letters.envelope.download', [
                'letter' => $letter->id,
                'template_id' => $envelopeTemplate->id,
                'format' => 'docx',
            ]));

        $downloadDocx->assertOk();
        $this->assertStringContainsString('.docx', $downloadDocx->headers->get('content-disposition'));
    }

    public function test_envelope_recipient_resolver_extracts_fields_automatically(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        $letter = Letter::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'perihal' => 'Peminjaman Aula Utama Kampus',
            'letter_date' => '2026-11-20',
            'status' => 'draft',
            'field_values' => [
                'nama_tujuan' => 'Prof. Dr. Ir. H. Joko Santoso',
                'lembaga' => 'Badan Eksekutif Mahasiswa',
                'lokasi' => 'Gedung Rektorat Lt. 3',
                'kota' => 'Semarang',
            ],
        ]);

        $resolved = \App\Services\Documents\EnvelopeRecipientResolver::resolve($letter, $workspace);

        $this->assertNotEmpty($resolved['nomor_surat']);
        $this->assertEquals('Prof. Dr. Ir. H. Joko Santoso', $resolved['kepada']);
        $this->assertEquals('Badan Eksekutif Mahasiswa', $resolved['instansi']);
        $this->assertEquals('Badan Eksekutif Mahasiswa', $resolved['instansi_tertuju']);
        $this->assertEquals('Peminjaman Aula Utama Kampus', $resolved['perihal']);
    }

    public function test_letter_show_zero_input_envelope_preview_modal(): void
    {
        Storage::fake('local');
        [$workspace, $user] = $this->setupWorkspace();

        $builder = new EnvelopeStarterDocxBuilder();
        $templatePath = $builder->build($workspace, 'dl', 'Template DL Standar');

        $envelopeTemplate = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Template DL Standar',
            'preset_size' => 'dl',
            'file_path' => $templatePath,
            'is_default' => true,
            'version' => 1,
        ]);

        $letter = Letter::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'perihal' => 'Undangan Pentas Seni',
            'letter_date' => now()->toDateString(),
            'status' => 'draft',
            'field_values' => ['kepada' => 'Ketua Jurusan'],
        ]);

        session(['active_workspace_id' => $workspace->id]);
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Letters\LetterShow::class, ['letter' => $letter])
            ->assertSet('showEnvelopeModal', false)
            ->call('openEnvelopeModal')
            ->assertSet('showEnvelopeModal', true)
            ->assertSet('selectedEnvelopeTemplateId', $envelopeTemplate->id)
            ->assertSee('Pratinjau Kop Amplop')
            ->assertSee('Unduh PDF')
            ->assertSee('Tab Baru');
    }
}
