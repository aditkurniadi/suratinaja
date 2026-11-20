<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Exceptions\DemoModeRestrictedException;
use App\Livewire\Auth\Login;
use App\Models\Letter;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\OnlyOfficeService;
use App\Support\CurrentWorkspace;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoAccountAndGuardTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): array
    {
        config([
            'suratinaja.demo.enabled' => true,
            'suratinaja.demo.email' => 'demo@suratinaja.test',
            'suratinaja.demo.password' => 'Demo12345!',
            'suratinaja.demo.workspace_name' => 'Himpunan Mahasiswa Demo',
        ]);

        $seeder = new DemoWorkspaceSeeder;
        $seeder->run();

        $demoUser = User::where('email', 'demo@suratinaja.test')->firstOrFail();
        $demoWorkspace = Workspace::where('slug', 'demo-workspace')->firstOrFail();

        return [$demoWorkspace, $demoUser];
    }

    public function test_demo_workspace_seeder_runs_idempotently(): void
    {
        [$ws1, $u1] = $this->runSeeder();

        // Run seeder second time
        (new DemoWorkspaceSeeder)->run();

        $this->assertEquals(1, Workspace::where('slug', 'demo-workspace')->count());
        $this->assertEquals(1, User::where('email', 'demo@suratinaja.test')->count());
        $this->assertTrue($u1->fresh()->is_demo);
        $this->assertEquals('active', $u1->fresh()->status);

        // Check sample letters exist
        $this->assertEquals(3, Letter::withoutGlobalScopes()->where('workspace_id', $ws1->id)->count());
    }

    public function test_login_page_renders_demo_button_when_enabled(): void
    {
        config(['suratinaja.demo.enabled' => true]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk Akun Demo (Hanya Lihat)');
    }

    public function test_demo_login_authenticates_demo_user(): void
    {
        [$ws, $demoUser] = $this->runSeeder();

        Livewire::test(Login::class)
            ->call('loginDemo')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($demoUser);
    }

    public function test_demo_guard_blocks_letter_modification(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        app(CurrentWorkspace::class)->set($ws);
        $this->actingAs($demoUser);

        $letter = Letter::withoutGlobalScopes()->where('workspace_id', $ws->id)->firstOrFail();

        $this->expectException(DemoModeRestrictedException::class);

        $letter->perihal = 'Perihal Baru yang Diubah User Demo';
        $letter->save();
    }

    public function test_demo_guard_blocks_creating_new_letter(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        app(CurrentWorkspace::class)->set($ws);
        $this->actingAs($demoUser);

        $this->expectException(DemoModeRestrictedException::class);

        Letter::create([
            'workspace_id' => $ws->id,
            'perihal' => 'Surat Baru dari User Demo',
            'letter_date' => now()->format('Y-m-d'),
            'status' => LetterStatus::Draft,
            'created_by' => $demoUser->id,
        ]);
    }

    public function test_demo_guard_allows_technical_session_updates_on_demo_user(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        app(CurrentWorkspace::class)->set($ws);
        $this->actingAs($demoUser);

        // Demo user switches workspace or updates remember token -> allowed
        $demoUser->remember_token = 'token_sesi_baru_123';
        $demoUser->save();

        $this->assertEquals('token_sesi_baru_123', $demoUser->fresh()->remember_token);
    }

    public function test_onlyoffice_service_forces_view_mode_for_demo_user(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        $letter = Letter::withoutGlobalScopes()->where('workspace_id', $ws->id)->firstOrFail();

        /** @var OnlyOfficeService $service */
        $service = app(OnlyOfficeService::class);
        $config = $service->buildEditorConfig($letter, $demoUser);

        $this->assertEquals('view', $config['editorConfig']['mode']);
        $this->assertFalse($config['document']['permissions']['edit']);
        $this->assertTrue($config['document']['permissions']['download']);
        $this->assertTrue($config['document']['permissions']['print']);
    }

    public function test_onlyoffice_save_callback_ignores_demo_workspace_documents(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        $letter = Letter::withoutGlobalScopes()->where('workspace_id', $ws->id)->firstOrFail();

        $service = app(OnlyOfficeService::class);
        $callbackToken = $service->generateAccessToken($letter, 'callback');

        $initialVersion = $letter->version;

        $response = $this->postJson(route('onlyoffice.callback', [
            'letter' => $letter->id,
            'token' => $callbackToken,
        ]), [
            'status' => 2,
            'url' => 'http://onlyoffice/dummy-file.docx',
        ]);

        $response->assertOk();
        $response->assertJson(['error' => 0]);

        $this->assertEquals($initialVersion, $letter->fresh()->version);
    }

    public function test_demo_user_layout_renders_demo_indicator_banner(): void
    {
        [$ws, $demoUser] = $this->runSeeder();
        app(CurrentWorkspace::class)->set($ws);
        $this->actingAs($demoUser);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Mode Demo (Hanya Lihat)');
    }
}
