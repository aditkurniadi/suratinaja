<?php

namespace Tests\Feature;

use App\Actions\Templates\DuplicateTemplateAction;
use App\Actions\Templates\UploadTemplateAction;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class TemplateUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_docx_upload_extracts_variables_schema_ac_03_01(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'tpl_').'.docx';
        SampleDocxBuilder::create($tmpDocx, ['include_repeater' => true]);

        $uploaded = new UploadedFile($tmpDocx, 'undangan.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $template = app(UploadTemplateAction::class)->execute($uploaded, 'Undangan Rapat', $sec, $ws);

        $varNames = array_column($template->variables_schema, 'name');
        $this->assertContains('nama_kegiatan', $varNames);
        $this->assertContains('tanggal_kegiatan', $varNames);
        $this->assertContains('lampiran', $varNames);

        @unlink($tmpDocx);
    }

    public function test_docx_without_variables_is_rejected_with_template_invalid_task_04(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $tmpDocx = tempnam(sys_get_temp_dir(), 'empty_tpl_').'.docx';
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Dokumen statis tanpa placeholder sama sekali.');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpDocx);

        $uploaded = new UploadedFile($tmpDocx, 'statis.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        try {
            app(UploadTemplateAction::class)->execute($uploaded, 'Dokumen Statis', $sec, $ws);
            $this->fail('Expected TEMPLATE_INVALID DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertSame('TEMPLATE_INVALID', $e->errorCode);
        } finally {
            @unlink($tmpDocx);
        }
    }

    public function test_global_template_is_read_only_and_can_be_duplicated_to_workspace_ac_03_03(): void
    {
        $ws = Workspace::create(['name' => 'HIMATRIS', 'number_code' => 'HIMATRIS']);
        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $rel = 'templates/global/master.docx';
        SampleDocxBuilder::create(Storage::disk('local')->path($rel));

        $global = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => null,
            'scope' => TemplateScope::Global,
            'name' => 'Master Global',
            'file_path' => $rel,
            'variables_schema' => [['name' => 'nama_kegiatan', 'type' => 'text', 'label' => 'Nama Kegiatan']],
            'version' => 1,
        ]);

        // Policy denies direct update on global template
        $this->assertFalse($sec->can('update', $global));

        // Duplicating creates a writable workspace-scoped copy
        $copy = app(DuplicateTemplateAction::class)->execute($global, $ws, $sec);
        $this->assertSame(TemplateScope::Workspace, $copy->scope);
        $this->assertSame($ws->id, $copy->workspace_id);
        $this->assertSame($global->id, $copy->source_template_id);
        $this->assertTrue($sec->can('update', $copy));
    }
}
