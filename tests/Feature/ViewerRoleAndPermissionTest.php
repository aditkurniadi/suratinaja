<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ViewerRoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspaceWithViewer(): array
    {
        Storage::fake('local');

        $ws = Workspace::create([
            'name' => 'HIMATRIS Viewer Org',
            'slug' => 'himatris-viewer-' . uniqid(),
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{seq}/{kategori}{parameter}/{kode}/{bulan_romawi}/{tahun}',
            'default_city' => 'Cilacap',
        ]);

        $registrar = app(WorkspacePermissionRegistrar::class);
        $registrar->initWorkspaceRoles($ws);

        $viewerUser = User::factory()->create([
            'name' => 'Humas Viewer',
            'email' => 'humas_' . uniqid() . '@himatris.id',
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $viewerUser->id,
            'role' => WorkspaceRole::Member,
        ]);

        setPermissionsTeamId($ws->id);
        $viewerRole = Role::where('workspace_id', $ws->id)
            ->where('name', WorkspacePermissionRegistrar::ROLE_VIEWER)
            ->firstOrFail();
        $viewerUser->assignRole($viewerRole);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $viewerUser];
    }

    private function generateDummyPdf(string $text): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml("<html><body><h1>{$text}</h1></body></html>");
        $dompdf->render();
        return $dompdf->output();
    }

    public function test_workspace_initialization_seeds_viewer_role_with_granular_preview_download_permissions(): void
    {
        [$ws, $viewerUser] = $this->setupWorkspaceWithViewer();

        setPermissionsTeamId($ws->id);

        $this->assertTrue($viewerUser->hasRole(WorkspacePermissionRegistrar::ROLE_VIEWER));
        $this->assertTrue($viewerUser->can('surat_keluar.preview_download'));
        $this->assertTrue($viewerUser->can('surat_masuk.preview_download'));
        $this->assertTrue($viewerUser->can('amplop.preview_download'));
        $this->assertTrue($viewerUser->can('bulk.preview_download'));
        $this->assertTrue($viewerUser->can('surat_keluar.view'));
        $this->assertTrue($viewerUser->can('surat_masuk.view'));

        // Viewer must NOT have mutation permissions
        $this->assertFalse($viewerUser->can('surat_keluar.create'));
        $this->assertFalse($viewerUser->can('surat_keluar.edit'));
        $this->assertFalse($viewerUser->can('surat_keluar.delete'));
        $this->assertFalse($viewerUser->can('surat_keluar.acc'));
        $this->assertFalse($viewerUser->can('disposisi.create'));
        $this->assertFalse($viewerUser->can('kop_surat.manage'));
        $this->assertFalse($viewerUser->can('penomoran.manage'));
    }

    public function test_viewer_can_preview_and_download_letter_and_envelope_pdfs(): void
    {
        [$ws, $viewerUser] = $this->setupWorkspaceWithViewer();

        $pdfContent = $this->generateDummyPdf('Surat Resmi Humas');
        $pdfPath = 'letters/preview_' . uniqid() . '.pdf';
        Storage::disk('local')->put($pdfPath, $pdfContent);

        $letter = Letter::create([
            'workspace_id' => $ws->id,
            'created_by' => $viewerUser->id,
            'perihal' => 'Undangan Humas Media',
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
            'preview_pdf_path' => $pdfPath,
            'generated_file_path' => $pdfPath,
        ]);

        // Preview PDF route
        $resPreview = $this->actingAs($viewerUser)
            ->withSession(['active_workspace_id' => $ws->id])
            ->get(route('letters.preview-pdf', $letter->id));

        $resPreview->assertOk();
        $this->assertEquals('application/pdf', $resPreview->headers->get('content-type'));

        // Download PDF route
        $resDownload = $this->actingAs($viewerUser)
            ->withSession(['active_workspace_id' => $ws->id])
            ->get(route('letters.preview-pdf', ['letter' => $letter->id, 'download' => 1]));

        $resDownload->assertOk();
        $this->assertStringContainsString('attachment', $resDownload->headers->get('content-disposition'));
    }

    public function test_viewer_in_letter_show_renders_direct_pdf_canvas_and_blocks_mutations(): void
    {
        [$ws, $viewerUser] = $this->setupWorkspaceWithViewer();

        $letter = Letter::create([
            'workspace_id' => $ws->id,
            'created_by' => $viewerUser->id,
            'perihal' => 'Pengumuman Kerjasama Humas',
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
        ]);

        session(['active_workspace_id' => $ws->id]);

        $test = Livewire::actingAs($viewerUser)
            ->test(\App\Livewire\Letters\LetterShow::class, ['letter' => $letter])
            ->assertSet('canEdit', false)
            ->assertSet('onlyOfficeConfig', null)
            ->assertSee('Unduh PDF')
            ->assertSee('Pratinjau Amplop')
            ->assertDontSee('Ajukan TTD')
            ->assertDontSee('Revisi Surat');

        // Attempting illegal mutation must dispatch error toast
        $test->call('transition', 'menunggu_ttd')
            ->assertDispatched('toast');
    }

    public function test_viewer_is_forbidden_from_creating_or_editing_letters(): void
    {
        [$ws, $viewerUser] = $this->setupWorkspaceWithViewer();

        // Direct HTTP route get to create letter should return 403
        $this->actingAs($viewerUser)
            ->withSession(['active_workspace_id' => $ws->id])
            ->get(route('letters.create'))
            ->assertForbidden();

        // Direct HTTP route to edit letter should return 403
        $letter = Letter::create([
            'workspace_id' => $ws->id,
            'created_by' => $viewerUser->id,
            'perihal' => 'Draf Rahasia',
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_workspace_id' => $ws->id])
            ->get(route('letters.edit', $letter->id))
            ->assertForbidden();
    }

    public function test_viewer_is_forbidden_from_starting_bulk_jobs(): void
    {
        [$ws, $viewerUser] = $this->setupWorkspaceWithViewer();

        session(['active_workspace_id' => $ws->id]);
        Livewire::actingAs($viewerUser)
            ->test(\App\Livewire\Bulk\BulkGenerator::class)
            ->call('openBulkModal')
            ->assertSet('showBulkModal', false)
            ->assertSet('errorMessage', 'Anda tidak memiliki hak akses membuat surat massal.');
    }
}
