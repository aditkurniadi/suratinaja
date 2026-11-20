<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SmartBackNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private LetterTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Admin Nav']);
        $this->workspace = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris',
            'owner_id' => $this->user->id,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => WorkspaceRole::Admin->value,
        ]);
        $this->user->update(['active_workspace_id' => $this->workspace->id]);
        app(CurrentWorkspace::class)->set($this->workspace);

        $this->template = LetterTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Surat Tugas',
            'file_path' => 'templates/test.docx',
            'scope' => 'workspace',
            'variables_schema' => [],
        ]);
    }

    public function test_letter_show_renders_smart_back_button_with_fallback_and_accessibility(): void
    {
        $letter = Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'perihal' => 'Undangan Rapat Kerja',
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('letters.show', $letter->id));

        $response->assertOk();
        $response->assertSee('title="Kembali ke halaman sebelumnya"', false);
        $response->assertSee('aria-label="Kembali ke halaman sebelumnya"', false);
        $response->assertSee('href="' . route('letters.index') . '"', false);
        $response->assertSee('window.SuratinAjaNav', false);
        $response->assertSee('Daftar Surat');
    }

    public function test_letter_composer_create_mode_renders_smart_back_fallback_to_letters_index(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('letters.create'));

        $response->assertOk();
        $response->assertSee('href="' . route('letters.index') . '"', false);
        $response->assertSee('window.SuratinAjaNav', false);
        $response->assertSee('Buat Surat');
    }

    public function test_letter_composer_edit_mode_renders_smart_back_fallback_to_letter_show(): void
    {
        $letter = Letter::create([
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
            'template_id' => $this->template->id,
            'perihal' => 'Draft Surat Tugas',
            'letter_date' => now()->toDateString(),
            'field_values' => [],
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('letters.edit', $letter->id));

        $response->assertOk();
        $response->assertSee('href="' . route('letters.show', $letter->id) . '"', false);
        $response->assertSee('window.SuratinAjaNav', false);
        $response->assertSee('Detail Surat');
        $response->assertSee('Edit Draft Surat');
    }

    public function test_surat_masuk_show_renders_smart_back_button(): void
    {
        $suratMasuk = SuratMasuk::create([
            'workspace_id' => $this->workspace->id,
            'nomor_agenda' => 'SM/2026/001',
            'nomor_surat_pengirim' => '01/BEM/XI/2026',
            'pengirim' => 'BEM Fasilkom',
            'perihal' => 'Undangan Pembukaan Expo',
            'tanggal_surat' => '2026-11-15',
            'tanggal_diterima' => '2026-11-16',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('surat-masuk.show', $suratMasuk->id));

        $response->assertOk();
        $response->assertSee('title="Kembali ke halaman sebelumnya"', false);
        $response->assertSee('href="' . route('surat-masuk.index') . '"', false);
        $response->assertSee('window.SuratinAjaNav', false);
    }

    public function test_envelope_templates_index_and_editor_render_smart_back_buttons(): void
    {
        Storage::fake('local');
        $path = 'templates/envelopes/' . $this->workspace->id . '/test.docx';
        Storage::disk('local')->put($path, 'dummy content');

        $envelope = EnvelopeTemplate::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Amplop Kabinet DL',
            'preset_size' => 'dl',
            'file_path' => $path,
            'is_default' => true,
        ]);

        $indexResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('settings.envelopes'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('href="' . route('settings.kop') . '"', false);
        $indexResponse->assertSee('window.SuratinAjaNav', false);

        $editorResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('settings.envelopes.edit', $envelope->id));
        $editorResponse->assertOk();
        $editorResponse->assertSee('href="' . route('settings.envelopes') . '"', false);
        $editorResponse->assertSee('window.SuratinAjaNav', false);
    }

    public function test_app_layout_derives_effective_fallback_from_breadcrumbs_when_back_url_omitted(): void
    {
        $view = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->blade(
                '<x-layouts.app :breadcrumbs="$breadcrumbs" title="Halaman Uji"><p>Konten</p></x-layouts.app>',
                [
                    'breadcrumbs' => [
                        ['label' => 'Induk 1', 'url' => 'https://suratinaja.test/induk-1'],
                        ['label' => 'Induk 2', 'url' => 'https://suratinaja.test/induk-2'],
                        ['label' => 'Halaman Uji'],
                    ],
                ]
            );

        $view->assertSee('href="https://suratinaja.test/induk-2"', false);
        $view->assertSee('window.SuratinAjaNav', false);
        $view->assertSee('Induk 1');
        $view->assertSee('Induk 2');
        $view->assertSee('Halaman Uji');
    }

    public function test_js_app_bundle_contains_smart_nav_tracker_and_ignored_patterns(): void
    {
        $appJsContent = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('SuratinAjaNav', $appJsContent);
        $this->assertStringContainsString('suratinaja:nav_history', $appJsContent);
        $this->assertStringContainsString('IGNORED_BACK_PATTERNS', $appJsContent);
        $this->assertStringContainsString('create', $appJsContent);
        $this->assertStringContainsString('login', $appJsContent);
        $this->assertStringContainsString('invitations', $appJsContent);
        $this->assertStringContainsString('getValidPreviousUrl', $appJsContent);
        $this->assertStringContainsString('getPreviousLabel', $appJsContent);
        $this->assertStringContainsString('goBack', $appJsContent);
        $this->assertStringContainsString('trackCurrent', $appJsContent);
    }
}
