<?php

namespace Tests\Feature;

use App\Actions\Bulk\ImportRecipientsAction;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\BulkJob;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Services\Templates\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BulkGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'OASIS',
            'slug' => 'oasis',
            'number_pattern' => '{seq}/{kode}/{bulan_romawi}/{tahun}',
            'number_code' => 'OASIS',
            'seq_padding' => 3,
        ]);

        $sec = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $sec->id,
            'role' => WorkspaceRole::Secretary,
        ]);

        $rel = 'templates/test/bulk-tpl.docx';
        $abs = Storage::disk('local')->path($rel);
        SampleDocxBuilder::create($abs, ['include_repeater' => false]);
        $schema = app(TemplateParser::class)->parse($abs);

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Undangan Massal OASIS',
            'file_path' => $rel,
            'variables_schema' => $schema,
            'version' => 1,
        ]);

        return [$ws, $sec, $tpl];
    }

    public function test_manual_bulk_generation_creates_sequential_numbers_and_single_merged_docx_task_08(): void
    {
        [$ws, $sec, $tpl] = $this->setupWorkspace();

        $res = $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters/bulk", [
                'template_id' => $tpl->id,
                'template_source' => 'workspace',
                'perihal' => 'Undangan Seminar OASIS',
                'letter_date' => '2025-06-15',
                'shared_variables' => [
                    'nama_kegiatan' => 'Seminar OASIS 2025',
                    'tanggal_kegiatan' => '2025-06-25',
                ],
                'recipients' => [
                    'mode' => 'manual',
                    'items' => [
                        ['nama' => 'Dr. Budi Santoso, M.Kom.', 'alamat' => 'Fakultas Teknik'],
                        ['nama' => 'Ir. Siti Aminah, M.T.', 'alamat' => 'Fakultas Sains'],
                    ],
                ],
            ]);

        $res->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_count', 2);

        $jobId = $res->json('data.job_id');
        $job = BulkJob::withoutGlobalScopes()->findOrFail($jobId);

        $this->assertSame('done', $job->status->value);
        $this->assertNotNull($job->output_path);
        $this->assertTrue(Storage::disk('local')->exists($job->output_path));

        // Verify sequential numbers without duplicates (AC-05-02)
        $numbers = $ws->numbers()->orderBy('sequence')->pluck('formatted_number')->all();
        $this->assertSame([
            '001/OASIS/VI/2025',
            '002/OASIS/VI/2025',
        ], $numbers);

        // Verify exact recipient names in the combined .docx (AC-05-04)
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($job->output_path));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('Dr. Budi Santoso, M.Kom.', $xml);
        $this->assertStringContainsString('Ir. Siti Aminah, M.T.', $xml);
        $this->assertStringContainsString('001/OASIS/VI/2025', $xml);
        $this->assertStringContainsString('002/OASIS/VI/2025', $xml);
        $this->assertStringNotContainsString('${', $xml);
    }

    public function test_csv_importer_reports_invalid_rows_without_failing_valid_rows_ac_05_03(): void
    {
        $csv = "penerima,tempat_tujuan\n"
            ."Prof. Hendra Wijaya,Fakultas Ilmu Komputer\n"
            .",\n"
            ."Dr. Maya Sari,Fakultas Ekonomi\n";

        $tmpCsv = tempnam(sys_get_temp_dir(), 'rec_').'.csv';
        file_put_contents($tmpCsv, $csv);

        try {
            $result = app(ImportRecipientsAction::class)->fromFile($tmpCsv);
            $this->assertCount(2, $result['valid']);
            $this->assertCount(1, $result['failed']);
            $this->assertSame(3, $result['failed'][0]['row']);
        } finally {
            @unlink($tmpCsv);
        }
    }

    public function test_invalid_bulk_payload_returns_422_bulk_job_failed(): void
    {
        [$ws, $sec, $tpl] = $this->setupWorkspace();

        $this->actingAs($sec)
            ->postJson("/api/v1/workspaces/{$ws->id}/letters/bulk", [
                'template_id' => $tpl->id,
                'perihal' => 'Undangan Kosong',
                'letter_date' => '2025-06-15',
                'recipients' => [
                    'mode' => 'manual',
                    'items' => [],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BULK_JOB_FAILED');
    }

    public function test_bulk_generator_livewire_supports_custom_varying_fields_and_toggling(): void
    {
        [$ws, $sec] = $this->setupWorkspace();

        $customTpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Undangan Dosen Khusus',
            'file_path' => 'templates/test/bulk-tpl.docx',
            'variables_schema' => [
                ['name' => 'tujuan', 'type' => 'text', 'label' => 'Nama Dosen / Tujuan'],
                ['name' => 'instansi', 'type' => 'text', 'label' => 'Instansi / Jurusan'],
                ['name' => 'lampiran', 'type' => 'text', 'label' => 'Lampiran'],
                ['name' => 'saya', 'type' => 'text', 'label' => 'Saya'],
            ],
            'version' => 1,
        ]);

        \Livewire\Livewire::actingAs($sec)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->set('templateId', $customTpl->id)
            ->assertSet('varyingFieldNames', ['tujuan', 'instansi'])
            ->assertSee('(${tujuan})')
            ->assertSee('(${instansi})')
            ->assertSee('(${lampiran})')
            ->call('toggleVaryingField', 'lampiran')
            ->assertSet('varyingFieldNames', ['tujuan', 'instansi', 'lampiran'])
            ->call('toggleVaryingField', 'lampiran')
            ->assertSet('varyingFieldNames', ['tujuan', 'instansi'])
            ->set('perihal', 'Undangan Rapat Dosen')
            ->set('manualRows', [
                ['tujuan' => 'Dr. Budi Santoso, M.Kom.', 'instansi' => 'Fakultas Teknik'],
                ['tujuan' => 'Ir. Siti Aminah, M.T.', 'instansi' => 'Fakultas Sains'],
            ])
            ->call('startBulk')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $job = BulkJob::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($job);
        $this->assertSame(2, $job->recipient_count);
        $this->assertSame('Dr. Budi Santoso, M.Kom.', $job->recipients[0]['tujuan']);
    }

    public function test_bulk_generator_livewire_syncs_perihal_on_template_change_and_supports_source_letter(): void
    {
        [$ws, $sec, $tpl1] = $this->setupWorkspace();

        $tpl2 = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Undangan Dosen V1',
            'file_path' => 'templates/test/bulk-tpl.docx',
            'variables_schema' => [
                ['name' => 'perihal', 'type' => 'system', 'label' => 'Perihal'],
                ['name' => 'tujuan', 'type' => 'text', 'label' => 'Tujuan'],
                ['name' => 'instansi', 'type' => 'text', 'label' => 'Instansi'],
                ['name' => 'saya', 'type' => 'text', 'label' => 'Saya'],
            ],
            'version' => 1,
        ]);

        // 1. Test updating template changes perihal automatically
        $component = \Livewire\Livewire::actingAs($sec)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->set('templateId', $tpl2->id)
            ->assertSet('perihal', 'Template Undangan Dosen V1')
            ->assertSee('(${perihal})');

        // 2. Test source letter prefilling
        $sourceLetter = \App\Models\Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'template_id' => $tpl2->id,
            'created_by' => $sec->id,
            'perihal' => 'OASIS Muda JKB',
            'letter_date' => '2026-11-20',
            'status' => \App\Enums\LetterStatus::Draft,
            'field_values' => [
                'saya' => 'Panitia OASIS',
                'lampiran' => 'Dua (2) berkas',
            ],
            'version' => 1,
        ]);

        $component->set('sourceLetterId', $sourceLetter->id)
            ->assertSet('perihal', 'OASIS Muda JKB')
            ->assertSet('letterDate', '2026-11-20')
            ->assertSet('templateId', $tpl2->id)
            ->assertSet('sharedVariables.saya', 'Panitia OASIS');

        // 3. Test bulk execution creates letters with correct perihal and field_values['perihal']
        $component->set('manualRows', [
            ['tujuan' => 'Dr. Budi Santoso, M.Kom.', 'instansi' => 'Fakultas Teknik'],
            ['tujuan' => 'Ir. Siti Aminah, M.T.', 'instansi' => 'Fakultas Sains'],
        ])
            ->call('startBulk')
            ->assertHasNoErrors();

        $generatedLetters = \App\Models\Letter::withoutGlobalScopes()
            ->where('bulk_job_id', '!=', null)
            ->get();

        $this->assertCount(2, $generatedLetters);
        foreach ($generatedLetters as $l) {
            $this->assertSame('OASIS Muda JKB', $l->perihal);
            $this->assertSame('OASIS Muda JKB', $l->field_values['perihal']);
            $this->assertSame('Panitia OASIS', $l->field_values['saya']);
        }
    }
}
