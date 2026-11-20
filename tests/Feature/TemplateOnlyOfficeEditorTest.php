<?php

namespace Tests\Feature;

use App\Actions\Templates\CreateBlankTemplateAction;
use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Templates\TemplateEditor;
use App\Livewire\Templates\TemplatesIndex;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Templates\SampleDocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class TemplateOnlyOfficeEditorTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspaceAndTemplate(
        WorkspaceRole $role = WorkspaceRole::Admin,
        TemplateScope $scope = TemplateScope::Workspace,
        bool $isSuperAdmin = false,
    ): array {
        Storage::fake('local');

        $workspace = Workspace::create([
            'name' => 'HIMATRIS Uji',
            'slug' => 'himatris-uji-'.uniqid(),
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'HIM',
            'seq_padding' => 3,
        ]);

        $user = User::factory()->create([
            'current_workspace_id' => $workspace->id,
            'is_super_admin' => $isSuperAdmin,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        $tplRel = $scope === TemplateScope::Global
            ? 'templates/global/test-global.docx'
            : "templates/workspaces/{$workspace->id}/test-ws.docx";

        SampleDocxBuilder::createStarter(Storage::disk('local')->path($tplRel));

        $template = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $scope === TemplateScope::Global ? null : $workspace->id,
            'scope' => $scope,
            'name' => 'Template Uji OnlyOffice',
            'file_path' => $tplRel,
            'variables_schema' => [
                ['name' => 'nomor_surat', 'label' => 'Nomor Surat', 'type' => 'system', 'required' => false],
            ],
            'version' => 1,
            'created_by' => $user->id,
        ]);

        return [$workspace, $user, $template];
    }

    public function test_workspace_writer_can_open_template_editor_with_plugin_config(): void
    {
        [$workspace, $user, $template] = $this->setupWorkspaceAndTemplate(WorkspaceRole::Secretary, TemplateScope::Workspace);

        $this->actingAs($user);

        $response = $this->get(route('templates.edit', $template->id));
        $response->assertStatus(200);
        $response->assertSee('Input Variabel Template');
        $response->assertSee('nomor_surat');
        $response->assertSee('signature_block');

        $service = app(OnlyOfficeService::class);
        $config = $service->buildTemplateEditorConfig($template, $user, true);

        $this->assertEquals('edit', $config['editorConfig']['mode']);
        $this->assertTrue($config['document']['permissions']['edit']);
        $this->assertContains(OnlyOfficeService::VARIABLE_PLUGIN_GUID, $config['editorConfig']['plugins']['autostart']);
        $this->assertStringContainsString('/api/onlyoffice/plugins/suratinaja-variables/config.json', $config['editorConfig']['plugins']['pluginsData'][0]);
        $this->assertArrayHasKey('token', $config);
    }

    public function test_chairman_readonly_member_gets_view_only_mode_without_plugin(): void
    {
        [$workspace, $user, $template] = $this->setupWorkspaceAndTemplate(WorkspaceRole::Chairman, TemplateScope::Workspace);

        $this->actingAs($user);

        Livewire::test(TemplateEditor::class, ['template' => $template])
            ->assertSet('canEdit', false)
            ->assertSee('Mode Baca-Saja');

        $service = app(OnlyOfficeService::class);
        $config = $service->buildTemplateEditorConfig($template, $user, false);

        $this->assertEquals('view', $config['editorConfig']['mode']);
        $this->assertFalse($config['document']['permissions']['edit']);
        $this->assertArrayNotHasKey('plugins', $config['editorConfig']);
    }

    public function test_global_template_is_readonly_for_regular_admin_and_editable_for_super_admin(): void
    {
        [$workspace, $regularAdmin, $globalTpl] = $this->setupWorkspaceAndTemplate(WorkspaceRole::Admin, TemplateScope::Global, false);

        $this->actingAs($regularAdmin);

        Livewire::test(TemplateEditor::class, ['template' => $globalTpl])
            ->assertSet('canEdit', false)
            ->assertSee('Duplikasi ke Workspace untuk Mengedit');

        $superAdmin = User::factory()->create([
            'current_workspace_id' => $workspace->id,
            'is_super_admin' => true,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $superAdmin->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(TemplateEditor::class, ['template' => $globalTpl])
            ->assertSet('canEdit', true)
            ->assertSee('Input Variabel Template');
    }

    public function test_template_download_endpoint_requires_valid_template_token_and_rejects_letter_token(): void
    {
        [$workspace, $user, $template] = $this->setupWorkspaceAndTemplate();
        $service = app(OnlyOfficeService::class);

        // Reject missing/invalid token
        $this->get(route('onlyoffice.templates.download', $template->id))->assertStatus(403);
        $this->get(route('onlyoffice.templates.download', ['templateId' => $template->id, 'token' => 'bad-token']))->assertStatus(403);

        // Reject a valid LETTER token (different namespace!)
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'template_id' => $template->id,
            'created_by' => $user->id,
            'status' => LetterStatus::Draft,
            'perihal' => 'Test',
            'letter_date' => now(),
            'field_values' => [],
            'version' => 1,
        ]);
        $letterToken = $service->generateAccessToken($letter, 'download');
        $this->get(route('onlyoffice.templates.download', ['templateId' => $template->id, 'token' => $letterToken]))
            ->assertStatus(403);

        // Accept valid template download token
        $tplToken = $service->generateTemplateAccessToken($template, 'download');
        $res = $this->get(route('onlyoffice.templates.download', ['templateId' => $template->id, 'token' => $tplToken]));
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_template_callback_saves_docx_autoheals_and_updates_variables_schema(): void
    {
        [$workspace, $user, $template] = $this->setupWorkspaceAndTemplate();
        $service = app(OnlyOfficeService::class);

        // Build an updated .docx containing new system, custom, date, and repeater variables
        $tmpUpdated = sys_get_temp_dir().'/updated_tpl_'.uniqid().'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor: ${nomor_surat}');
        $section->addText('Perihal: ${perihal}');
        $section->addText('Kepada: ${instansi_tujuan}');
        $section->addText('Tanggal Acara: ${tanggal_pelaksanaan}');
        $section->addText('${signature_block}');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('No');
        $table->addCell(4000)->addText('Nama');
        $table->addRow();
        $table->addCell(2000)->addText('${peserta.no}');
        $table->addCell(4000)->addText('${peserta.nama}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpUpdated);
        $updatedBytes = file_get_contents($tmpUpdated);
        @unlink($tmpUpdated);

        // Also build a WIP .docx with ZERO variables to test graceful handling
        $tmpBlank = sys_get_temp_dir().'/blank_wip_'.uniqid().'.docx';
        SampleDocxBuilder::createBlank($tmpBlank);
        $blankBytes = file_get_contents($tmpBlank);
        @unlink($tmpBlank);

        Http::fake([
            'http://onlyoffice/cache/updated-template.docx' => Http::response($updatedBytes, 200),
            'http://onlyoffice/cache/wip-blank.docx' => Http::response($blankBytes, 200),
        ]);

        $callbackToken = $service->generateTemplateAccessToken($template, 'callback');

        // 1. Forcesave (status 6) with newly added variables
        $res1 = $this->postJson(
            route('onlyoffice.templates.callback', ['templateId' => $template->id, 'token' => $callbackToken]),
            ['status' => 6, 'url' => 'http://onlyoffice/cache/updated-template.docx']
        );
        $res1->assertStatus(200)->assertJson(['error' => 0]);

        $template->refresh();
        $this->assertEquals(2, $template->version);

        $varNames = array_column($template->variables_schema ?? [], 'name');
        $this->assertContains('nomor_surat', $varNames);
        $this->assertContains('instansi_tujuan', $varNames);
        $this->assertContains('tanggal_pelaksanaan', $varNames);
        $this->assertContains('signature_block', $varNames);
        $this->assertContains('peserta', $varNames);

        $dateField = collect($template->variables_schema)->firstWhere('name', 'tanggal_pelaksanaan');
        $this->assertEquals('date', $dateField['type']);

        $repeaterField = collect($template->variables_schema)->firstWhere('name', 'peserta');
        $this->assertEquals('repeater', $repeaterField['type']);
        $this->assertCount(2, $repeaterField['columns']);

        // 2. WIP save (status 2) with zero variables still returns error: 0 without failing OnlyOffice
        $res2 = $this->postJson(
            route('onlyoffice.templates.callback', ['templateId' => $template->id, 'token' => $callbackToken]),
            ['status' => 2, 'url' => 'http://onlyoffice/cache/wip-blank.docx']
        );
        $res2->assertStatus(200)->assertJson(['error' => 0]);

        $template->refresh();
        $this->assertEquals(3, $template->version);
        $this->assertSame([], $template->variables_schema);
    }

    public function test_onlyoffice_variable_plugin_endpoints_serve_manifest_html_and_icon_with_cors(): void
    {
        $cfgRes = $this->get(route('onlyoffice.plugins.variables.config'));
        $cfgRes->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('guid', OnlyOfficeService::VARIABLE_PLUGIN_GUID)
            ->assertJsonPath('variations.0.isSystem', true)
            ->assertJsonPath('variations.0.isVisual', false);

        $htmlRes = $this->get(route('onlyoffice.plugins.variables.index'));
        $htmlRes->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertSee('PasteText')
            ->assertSee('CreateTable')
            ->assertSee('suratinaja-oo-plugin');

        $iconRes = $this->get(route('onlyoffice.plugins.variables.icon'));
        $iconRes->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertSee('<svg', false);
    }

    public function test_create_blank_and_starter_templates_directly_for_onlyoffice(): void
    {
        [$workspace, $user] = $this->setupWorkspaceAndTemplate(WorkspaceRole::Admin, TemplateScope::Workspace);
        $action = app(CreateBlankTemplateAction::class);

        $starter = $action->execute('Surat Tugas Baru', $user, $workspace, TemplateScope::Workspace, 'Deskripsi', false, 'starter');
        $this->assertTrue(Storage::disk('local')->exists($starter->file_path));
        $starterVarNames = array_column($starter->variables_schema ?? [], 'name');
        $this->assertContains('nomor_surat', $starterVarNames);
        $this->assertContains('signature_block', $starterVarNames);
        $this->assertContains('tujuan_surat', $starterVarNames);

        $blank = $action->execute('Template Kosong A4', $user, $workspace, TemplateScope::Workspace, null, true, 'blank');
        $this->assertTrue(Storage::disk('local')->exists($blank->file_path));
        $this->assertTrue($blank->has_embedded_kop);
        $this->assertSame([], $blank->variables_schema);

        $this->actingAs($user);
        Livewire::test(TemplatesIndex::class)
            ->set('newTemplateName', 'Undangan Rapat Internal')
            ->set('newStarterMode', 'starter')
            ->call('createInOnlyOffice')
            ->assertRedirect();
    }

    public function test_template_editor_align_kop_margin_updates_master_docx_and_reloads(): void
    {
        [$workspace, $user, $template] = $this->setupWorkspaceAndTemplate(WorkspaceRole::Admin, TemplateScope::Workspace);

        // Starter template has no word/header*.xml -> settings modal should NOT display Rapikan Margin button
        $this->actingAs($user);
        Livewire::test(TemplateEditor::class, ['template' => $template])
            ->call('openSettings')
            ->assertDontSee('Rapikan Margin Kop Otomatis');

        // Inject a floating Word header into the template master file
        $absPath = Storage::disk('local')->path($template->file_path);
        $zip = new \ZipArchive;
        $zip->open($absPath);
        $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><w:p><w:r><w:drawing><wp:anchor behindDoc="0"><wp:positionV relativeFrom="page"><wp:posOffset>1996440</wp:posOffset></wp:positionV><wp:extent cx="100" cy="100"/></wp:anchor></w:drawing></w:r></w:p></w:hdr>');
        $zip->close();

        Http::fake([
            '*/coauthoring/CommandService.ashx' => Http::response(['error' => 4], 200),
        ]);

        Livewire::test(TemplateEditor::class, ['template' => $template])
            ->call('openSettings')
            ->assertSee('Rapikan Margin Kop Otomatis')
            ->call('alignKopMargin')
            ->assertRedirect(route('templates.edit', $template->id));

        $template->refresh();
        $this->assertEquals(2, $template->version);

        $checkZip = new \ZipArchive;
        $checkZip->open($absPath);
        $docXml = $checkZip->getFromName('word/document.xml');
        $checkZip->close();

        $this->assertStringContainsString('w:top="3304"', $docXml);
    }
}
