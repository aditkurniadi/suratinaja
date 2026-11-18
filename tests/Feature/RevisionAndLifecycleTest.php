<?php

namespace Tests\Feature;

use App\Actions\Letters\CancelLetterAction;
use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\DuplicateLetterAction;
use App\Actions\Letters\UpdateDraftAction;
use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Documents\PdfPreviewService;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createInitialLetter(): array
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

        $rel = 'templates/test/rev-tpl.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Revisi',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        $letter = app(CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $tpl->id,
            'perihal' => 'Undangan Rapat Awal',
            'letter_date' => '2025-06-15',
            'variables' => [
                'penerima' => 'Ketua BEM',
                'tempat_tujuan' => 'Gedung Mahasiswa',
                'nama_kegiatan' => 'Rapat Koordinasi',
                'tanggal_kegiatan' => '2025-06-20',
            ],
        ]);

        return [$ws, $sec, $letter];
    }

    public function test_revision_without_reason_is_rejected_with_422_revision_reason_required_ac_06_01(): void
    {
        [$ws, $sec, $letter] = $this->createInitialLetter();

        $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters/{$letter->id}/revise", [
                'use_new_number' => true,
                'revision_reason' => '   ',
                'variables' => ['tanggal_kegiatan' => '2025-06-25'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REVISION_REASON_REQUIRED');
    }

    public function test_revision_with_new_number_marks_old_number_hangus_and_links_revision_of_ac_06_02(): void
    {
        [$ws, $sec, $letter] = $this->createInitialLetter();
        $oldNumberId = $letter->activeNumber->id;

        $res = $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters/{$letter->id}/revise", [
                'use_new_number' => true,
                'revision_reason' => 'Perubahan tanggal pelaksanaan kegiatan.',
                'variables' => ['tanggal_kegiatan' => '2025-06-25'],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.old_number', '001/HIMATRIS/VI/2025')
            ->assertJsonPath('data.old_number_status', 'hangus')
            ->assertJsonPath('data.new_number', '002/HIMATRIS/VI/2025');

        $letter->refresh();
        $this->assertSame('002/HIMATRIS/VI/2025', $letter->formatted_number);
        $this->assertSame($oldNumberId, $letter->activeNumber->revision_of);

        $oldRow = $letter->numbers()->where('id', $oldNumberId)->first();
        $this->assertSame(NumberStatus::Hangus, $oldRow->status);
    }

    public function test_revision_keeping_number_preserves_active_number_and_records_history_ac_06_03(): void
    {
        [$ws, $sec, $letter] = $this->createInitialLetter();

        $res = $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters/{$letter->id}/revise", [
                'use_new_number' => false,
                'revision_reason' => 'Koreksi tipografi nama penerima.',
                'variables' => ['penerima' => 'Ketua BEM Universitas'],
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.old_number', '001/HIMATRIS/VI/2025')
            ->assertJsonPath('data.old_number_status', 'aktif')
            ->assertJsonPath('data.new_number', '001/HIMATRIS/VI/2025');

        $this->assertCount(1, $letter->revisions()->get());
    }

    public function test_draft_can_be_edited_freely_and_logged_and_letter_can_be_duplicated_and_cancelled(): void
    {
        [, $sec, $letter] = $this->createInitialLetter();

        // Free draft edit preserves number and logs 'edited' activity
        $updated = app(UpdateDraftAction::class)->execute($letter, $sec, [
            'perihal' => 'Undangan Rapat Awal (Diperbarui)',
            'variables' => ['tempat_tujuan' => 'Aula Utama'],
        ]);
        $this->assertSame('001/HIMATRIS/VI/2025', $updated->formatted_number);
        $this->assertTrue($updated->activities()->where('action', 'edited')->exists());

        // Duplicate allocates new sequential number 002
        $copy = app(DuplicateLetterAction::class)->execute($updated, $sec);
        $this->assertSame('002/HIMATRIS/VI/2025', $copy->formatted_number);
        $this->assertSame($updated->id, $copy->duplicated_from_id);

        // Cancel voids number to hangus and marks status dibatalkan
        $cancelled = app(CancelLetterAction::class)->execute($copy, $sec, 'Kegiatan ditunda semester depan');
        $this->assertSame(LetterStatus::Dibatalkan, $cancelled->status);
        $this->assertNull($cancelled->activeNumber);
        $this->assertSame(NumberStatus::Hangus, $cancelled->numbers()->first()->status);
    }

    public function test_pdf_preview_service_caches_pdf_and_handles_gotenberg_fallback_cleanly(): void
    {
        [, , $letter] = $this->createInitialLetter();

        Http::fake([
            '*/forms/libreoffice/convert' => Http::response('%PDF-1.4 fake pdf bytes', 200),
        ]);

        $res = app(PdfPreviewService::class)->ensurePreviewPdf($letter);
        $this->assertTrue($res['ok']);
        $this->assertTrue(Storage::disk('local')->exists($res['path']));
    }
}
