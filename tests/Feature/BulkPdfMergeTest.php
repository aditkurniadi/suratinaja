<?php

namespace Tests\Feature;

use App\Enums\BulkJobStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Services\Documents\PdfMergerService;
use App\Support\CurrentWorkspace;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkPdfMergeTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(array $attributes = []): array
    {
        $ws = Workspace::create(array_merge([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-merge-' . uniqid(),
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

    private function generateDummyPdf(string $text): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml("<html><body><h1>{$text}</h1></body></html>");
        $dompdf->render();
        return $dompdf->output();
    }

    public function test_pdf_merger_service_merges_multiple_pdfs(): void
    {
        $merger = new PdfMergerService();
        if (! $merger->isAvailable()) {
            $this->markTestSkipped('CLI PDF merger tool (qpdf / pdfunite) is not available in environment.');
        }

        $pdf1 = $this->generateDummyPdf('Halaman Surat Pertama');
        $pdf2 = $this->generateDummyPdf('Halaman Surat Kedua');

        $p1 = 'test_merge/p1_' . uniqid() . '.pdf';
        $p2 = 'test_merge/p2_' . uniqid() . '.pdf';
        $out = 'test_merge/out_' . uniqid() . '.pdf';

        Storage::disk('local')->put($p1, $pdf1);
        Storage::disk('local')->put($p2, $pdf2);

        $res = $merger->merge([$p1, $p2], $out);

        $this->assertTrue($res['ok']);
        $this->assertTrue(Storage::disk('local')->exists($out));
        $this->assertGreaterThan(100, Storage::disk('local')->size($out));

        // Cleanup
        Storage::disk('local')->delete([$p1, $p2, $out]);
    }

    public function test_bulk_export_pdf_route_returns_combined_pdf(): void
    {
        [$workspace, $user] = $this->setupWorkspace();

        $t = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Undangan',
            'file_path' => 'templates/test/undangan.docx',
            'variables_schema' => [],
        ]);

        $pdfContent1 = $this->generateDummyPdf('Surat 1');
        $pdfPath1 = 'letters/test_l1_' . uniqid() . '.pdf';
        Storage::disk('local')->put($pdfPath1, $pdfContent1);

        $pdfContent2 = $this->generateDummyPdf('Surat 2');
        $pdfPath2 = 'letters/test_l2_' . uniqid() . '.pdf';
        Storage::disk('local')->put($pdfPath2, $pdfContent2);

        $jobId = 'bulk-job-' . uniqid();
        $bulkJob = BulkJob::create([
            'id' => $jobId,
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'perihal' => 'Undangan Pentas Seni',
            'letter_date' => now()->toDateString(),
            'shared_variables' => [],
            'recipients' => [],
            'status' => BulkJobStatus::Done,
            'recipient_count' => 2,
            'processed_count' => 2,
        ]);

        Letter::create([
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'preview_pdf_path' => $pdfPath1,
            'status' => 'draft',
            'bulk_job_id' => $bulkJob->id,
            'perihal' => 'Undangan Pentas Seni - Penerima 1',
        ]);

        Letter::create([
            'workspace_id' => $workspace->id,
            'template_id' => $t->id,
            'created_by' => $user->id,
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'preview_pdf_path' => $pdfPath2,
            'status' => 'draft',
            'bulk_job_id' => $bulkJob->id,
            'perihal' => 'Undangan Pentas Seni - Penerima 2',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('bulk.export.pdf', $bulkJob->id));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        // Default is inline preview
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('batch-surat-', $response->headers->get('content-disposition'));

        // Download flag forces attachment
        $downloadResponse = $this->actingAs($user)
            ->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('bulk.export.pdf', ['bulkJobId' => $bulkJob->id, 'download' => 1]));

        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', $downloadResponse->headers->get('content-disposition'));

        // Test BulkGenerator Livewire modal triggers
        session(['active_workspace_id' => $workspace->id]);
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->assertSet('previewModalType', null)
            ->call('openBatchPdfPreview', $bulkJob->id)
            ->assertSet('previewModalType', 'full_pdf')
            ->assertSee('Pratinjau Gabungan 1 PDF Utuh')
            ->assertSee('Unduh PDF')
            ->call('closePreviewModal')
            ->assertSet('previewModalType', null);
    }
}
