<?php

namespace Tests\Feature;

use App\Actions\Letters\CreateLetterAction;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Exceptions\DomainException;
use App\Livewire\Settings\KopBuilder;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class KopBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_kop_builder_ac_02_03(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $secretary = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $secretary->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $this->actingAs($secretary)
            ->get(route('settings.kop'))
            ->assertStatus(403);
    }

    public function test_admin_can_configure_kop_and_generated_docx_includes_kop_ac_02_01(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $this->actingAs($admin);
        $logo = UploadedFile::fake()->image('logo-kiri.png', 120, 120);

        Livewire::test(KopBuilder::class)
            ->set('kopHeaderText', "HIMATRIS FAKULTAS TEKNIK\nUNIVERSITAS NUSANTARA")
            ->set('kopAddress', 'Jl. Kampus No. 99')
            ->set('logoLeftUpload', $logo)
            ->call('save')
            ->assertHasNoErrors();

        $ws->refresh();
        $this->assertNotNull($ws->kop_logo_left);
        $this->assertSame('Jl. Kampus No. 99', $ws->kop_address);

        // Generate a letter and verify kop text appears in word/document.xml
        $rel = 'templates/test/kop-tpl.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Kop',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        $letter = app(CreateLetterAction::class)->execute($ws, $admin, [
            'template_id' => $tpl->id,
            'perihal' => 'Uji Kop Surat',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'Ketua Jurusan',
                'tempat_tujuan' => 'Gedung Dekanat',
                'nama_kegiatan' => 'Seminar Nasional',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ]);

        $generatedAbs = Storage::disk('local')->path($letter->generated_file_path);
        $zip = new ZipArchive;
        $zip->open($generatedAbs);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('HIMATRIS FAKULTAS TEKNIK', (string) $xml);
        $this->assertStringContainsString('Jl. Kampus No. 99', (string) $xml);
        $this->assertStringNotContainsString('${', (string) $xml);
    }

    public function test_missing_logo_file_fails_with_clear_domain_exception_mod_02_edge_case(): void
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'number_code' => 'HIMATRIS',
            'kop_header_text' => 'HIMATRIS',
            'kop_logo_left' => 'kop/999/missing-logo.png',
        ]);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $rel = 'templates/test/missing-logo-tpl.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Logo Hilang',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('File logo kiri kop surat tidak ditemukan');

        app(CreateLetterAction::class)->execute($ws, $admin, [
            'template_id' => $tpl->id,
            'perihal' => 'Uji Logo Hilang',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'Dekan',
                'tempat_tujuan' => 'Kampus',
                'nama_kegiatan' => 'Rapat',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ]);
    }

    public function test_admin_can_configure_multi_tier_signers_and_apply_preset(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $admin = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $this->actingAs($admin);

        Livewire::test(KopBuilder::class)
            ->call('applyPreset', '5_signers')
            ->call('save')
            ->assertHasNoErrors();

        $ws->refresh();
        $this->assertCount(5, $ws->signers_config);
        $this->assertSame('Menyetujui,', $ws->signers_config[2]['prefix']);
        $this->assertSame('Mengetahui,', $ws->signers_config[4]['prefix']);

        // Test drag & drop / reordering
        Livewire::test(KopBuilder::class)
            ->call('moveSigner', 0, 1)
            ->assertSet('signers.0.position', 'Ketua Pelaksana,')
            ->assertSet('signers.1.position', 'Ketua HIMATRIS,')
            ->call('moveSignerUp', 1)
            ->assertSet('signers.0.position', 'Ketua HIMATRIS,')
            ->call('removeSigner', 4) // Delete 5th signer -> now 4 signers
            ->assertCount('signers', 4)
            ->call('save')
            ->assertHasNoErrors();

        // Generate letter and verify docx contains all signers with prefix
        $rel = 'templates/test/multi-signer-tpl.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Multi Signer',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        $letter = app(CreateLetterAction::class)->execute($ws, $admin, [
            'template_id' => $tpl->id,
            'perihal' => 'Uji Multi Signer',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'Ketua Jurusan',
                'tempat_tujuan' => 'Gedung Dekanat',
                'nama_kegiatan' => 'Makrab TRPL',
                'tanggal_kegiatan' => '2025-06-20',
                '_signers' => $ws->signers_config,
            ],
        ]);

        $generatedAbs = Storage::disk('local')->path($letter->generated_file_path);
        $zip = new ZipArchive;
        $zip->open($generatedAbs);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Menyetujui,', (string) $xml);
        $this->assertStringContainsString('Mengetahui,', (string) $xml);
        $this->assertStringContainsString('Koordinator Prodi,', (string) $xml);
        $this->assertStringContainsString('Dwi Novia Prasetyanti', (string) $xml);
    }
}
