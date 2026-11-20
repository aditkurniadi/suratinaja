<?php

namespace Tests\Feature;

use App\Enums\BulkJobStatus;
use App\Enums\WorkspaceRole;
use App\Models\BulkJob;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Documents\EnvelopePdfService;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Documents\PdfMergerService;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BulkPreviewAndEnvelopeFixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;
    private LetterTemplate $template;
    private EnvelopeTemplate $envelopeTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create(['name' => 'Admin Bulk']);
        $this->workspace = Workspace::create([
            'name' => 'HIMATRIS Bulk Test',
            'slug' => 'himatris-bulk-test',
            'owner_id' => $this->admin->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->admin->id,
            'role' => WorkspaceRole::Admin->value,
        ]);

        $this->admin->update(['active_workspace_id' => $this->workspace->id]);
        app(CurrentWorkspace::class)->set($this->workspace);

        $this->template = LetterTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Template Undangan',
            'file_path' => 'templates/test.docx',
            'scope' => 'workspace',
            'variables_schema' => [],
        ]);

        $envDocx = 'templates/envelopes/' . $this->workspace->id . '/dl_test.docx';
        Storage::disk('local')->put($envDocx, 'dummy envelope docx');

        $this->envelopeTemplate = EnvelopeTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Amplop Kabinet DL',
            'preset_size' => 'dl',
            'file_path' => $envDocx,
            'is_default' => true,
            'version' => 1,
        ]);
    }

    public function test_qpdf_binary_detection_prioritizes_valid_executable_paths(): void
    {
        $merger = app(PdfMergerService::class);
        $binary = $merger->detectCliBinary();

        $this->assertNotNull($binary, 'Binary qpdf/pdfunite should be detected on development system.');
        $this->assertArrayHasKey('tool', $binary);
        $this->assertArrayHasKey('path', $binary);
        $this->assertFileExists($binary['path']);
    }

    public function test_open_batch_pdf_preview_modal_sets_state_and_generates_valid_url(): void
    {
        $job = BulkJob::create([
            'id' => 'bulk_test_001',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Undangan Rapat Kerja',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'A']],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 2,
            'processed_count' => 2,
            'output_path' => 'generated/' . $this->workspace->id . '/bulk_test_001.docx',
        ]);
        Storage::disk('local')->put($job->output_path, 'dummy docx');

        session(['active_workspace_id' => $this->workspace->id]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->assertSet('previewModalType', null)
            ->call('openBatchPdfPreview', $job->id)
            ->assertSet('previewModalType', 'full_pdf')
            ->assertSet('previewJobId', $job->id)
            ->assertSee('Pratinjau Gabungan 1 PDF Utuh')
            ->assertSee('Unduh PDF')
            ->assertSeeHtml('@keydown.escape.window="$wire.closePreviewModal()"')
            ->assertSeeHtml('@click.outside="$wire.closePreviewModal()"')
            ->assertSeeHtml('title="Tutup Modal (Esc)"')
            ->call('closePreviewModal')
            ->assertSet('previewModalType', null);
    }

    public function test_open_batch_envelope_preview_modal_sets_state_and_generates_valid_url(): void
    {
        $job = BulkJob::create([
            'id' => 'bulk_test_002',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Undangan Pengurus',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'A']],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 1,
            'processed_count' => 1,
            'output_path' => 'generated/' . $this->workspace->id . '/bulk_test_002.docx',
        ]);
        Storage::disk('local')->put($job->output_path, 'dummy docx');

        session(['active_workspace_id' => $this->workspace->id]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->assertSet('previewModalType', null)
            ->call('openBatchEnvelopePreview', $job->id)
            ->assertSet('previewModalType', 'envelopes')
            ->assertSet('previewJobId', $job->id)
            ->assertSee('Pratinjau Semua Amplop')
            ->call('closePreviewModal')
            ->assertSet('previewModalType', null);
    }

    public function test_open_batch_envelope_preview_dispatches_error_toast_when_no_template_exists(): void
    {
        EnvelopeTemplate::where('workspace_id', $this->workspace->id)->delete();

        $job = BulkJob::create([
            'id' => 'bulk_test_003',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Undangan Tanpa Template Amplop',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'A']],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 1,
            'processed_count' => 1,
            'output_path' => 'generated/' . $this->workspace->id . '/bulk_test_003.docx',
        ]);

        session(['active_workspace_id' => $this->workspace->id]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->call('openBatchEnvelopePreview', $job->id)
            ->assertDispatched('toast', function ($event, $params) {
                $payload = is_array($params[0] ?? null) ? $params[0] : $params;
                return ($payload['type'] ?? '') === 'error' && str_contains($payload['title'] ?? '', 'Amplop');
            })
            ->assertSet('previewModalType', null);
    }

    public function test_bulk_export_pdf_route_supports_old_job_fallback_via_output_path_docx(): void
    {
        $docxPath = 'generated/' . $this->workspace->id . '/bulk_old_job.docx';
        Storage::disk('local')->put($docxPath, 'dummy merged docx');

        $job = BulkJob::create([
            'id' => 'bulk_old_job',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Batch Lama Tanpa Relasi Surat',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'A']],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 2,
            'processed_count' => 2,
            'output_path' => $docxPath,
        ]);

        $mockOnlyOffice = $this->createMock(OnlyOfficeService::class);
        $mockOnlyOffice->method('convertArbitraryDocxToPdf')
            ->willReturnCallback(function ($src, $dest) {
                Storage::disk('local')->put($dest, '%PDF-1.4 dummy converted pdf');
                return ['ok' => true, 'path' => $dest];
            });
        $this->app->instance(OnlyOfficeService::class, $mockOnlyOffice);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('bulk.export.pdf', ['bulkJobId' => $job->id, 'preview' => 1]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_envelope_service_bulk_render_supports_old_job_fallback_via_recipients_array(): void
    {
        $job = BulkJob::create([
            'id' => 'bulk_old_envelope_job',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Batch Lama Amplop',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 2,
            'processed_count' => 2,
            'output_path' => 'generated/test.docx',
            'recipients' => [
                ['penerima' => 'Dr. Budi Santoso', 'instansi' => 'Fakultas Teknik'],
                ['penerima' => 'Siti Rahma, M.Sc.', 'instansi' => 'Fakultas MIPA'],
            ],
        ]);

        $envService = app(EnvelopePdfService::class);
        $res = $envService->renderBulk($job, $this->envelopeTemplate);

        $this->assertTrue($res['ok']);
        $this->assertNotEmpty($res['path']);
        $this->assertTrue(Storage::disk('local')->exists($res['path']));
    }

    public function test_polling_and_autorefresh_badge_status_reflect_active_jobs(): void
    {
        // 1. When all jobs are done, no active polling
        BulkJob::create([
            'id' => 'bulk_done_job',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Job Selesai',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'A']],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 1,
            'processed_count' => 1,
        ]);

        session(['active_workspace_id' => $this->workspace->id]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->assertDontSee('wire:poll.3s', false)
            ->assertSee('Semua job selesai');

        // 2. When a job is processing, active polling is present
        BulkJob::create([
            'id' => 'bulk_processing_job',
            'workspace_id' => $this->workspace->id,
            'template_id' => $this->template->id,
            'created_by' => $this->admin->id,
            'perihal' => 'Job Sedang Berjalan',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [['penerima' => 'B']],
            'status' => BulkJobStatus::Processing,
            'recipient_count' => 10,
            'processed_count' => 2,
        ]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->assertSee('wire:poll.3s', false)
            ->assertSee('Auto-refresh antrean...');
    }

    public function test_bulk_generator_blade_view_has_perfect_div_tag_balance_and_single_root_element(): void
    {
        $content = trim(file_get_contents(resource_path('views/livewire/bulk/bulk-generator.blade.php')));
        $lines = explode("\n", $content);

        $depth = 0;
        foreach ($lines as $idx => $line) {
            $depth += substr_count($line, '<div');
            $depth -= substr_count($line, '</div>');
            if ($idx < count($lines) - 1) {
                $this->assertGreaterThanOrEqual(1, $depth, "Tag <div> tertutup lebih awal pada baris " . ($idx + 1));
            }
        }

        $this->assertEquals(0, $depth, 'Tag <div> penutup di akhir file tidak seimbang.');
    }
}
