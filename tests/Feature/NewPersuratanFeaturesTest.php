<?php

namespace Tests\Feature;

use App\Actions\Templates\UploadTemplateAction;
use App\Enums\LetterStatus;
use App\Enums\TemplateScope;
use App\Enums\WorkspaceRole;
use App\Livewire\Bulk\BulkGenerator;
use App\Livewire\Letters\LetterComposer;
use App\Livewire\Settings\LetterTypesSettings;
use App\Livewire\Templates\TemplatesIndex;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewPersuratanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS Test',
            'slug' => 'himatris-test',
            'number_code' => 'HIMATRIS',
            'number_pattern' => '{nomor}/{kategori}{parameter}/PAN-{kode_kegiatan}/HIMATRIS/{bulan_romawi}/{tahun}',
            'activity_code' => 'JKBFEST',
            'is_activity' => true,
            'default_city' => 'Cilacap',
        ]);

        $user = User::factory()->create([
            'email' => 'admin-feat@himatris.id',
            'name' => 'Admin Features',
            'current_workspace_id' => $ws->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Admin,
        ]);

        return [$ws, $user];
    }

    public function test_letter_composer_multistep_wizard_navigation_and_validation(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Surat Tugas',
            'file_path' => 'templates/test/stg.docx',
            'variables_schema' => [
                ['name' => 'perihal', 'type' => 'system', 'label' => 'Perihal'],
                ['name' => 'tujuan', 'type' => 'text', 'label' => 'Tujuan'],
            ],
            'version' => 1,
        ]);

        // Start at Step 1
        $component = Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->assertSet('currentStep', 1)
            ->set('templateId', null)
            ->set('perihal', '')
            ->call('nextStep')
            ->assertHasErrors(['templateId', 'perihal'])
            ->assertSet('currentStep', 1) // Did not advance due to errors
            ->set('templateId', $tpl->id)
            ->set('perihal', 'Surat Tugas Panitia')
            ->set('letterDate', '2026-11-20')
            ->set('kategori', 'STG')
            ->set('parameter', '-i')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2) // Advanced to Step 2
            ->call('prevStep')
            ->assertSet('currentStep', 1) // Returned to Step 1
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->call('nextStep')
            ->assertSet('currentStep', 3); // Advanced to Step 3
    }

    public function test_create_letter_without_template_manual_number_and_google_drive(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $comp = Livewire::actingAs($user)
            ->test(LetterComposer::class)
            ->call('setLetterMode', 'manual_number')
            ->set('perihal', 'Permohonan Kerjasama Sponsor Eksternal')
            ->set('letterDate', '2026-11-25')
            ->set('kategori', 'SPm')
            ->set('parameter', '-e')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->set('recipientName', 'PT Telkom Indonesia Tbk')
            ->set('recipientAgency', 'Witel Purwokerto')
            ->set('linkDrive', 'https://drive.google.com/file/d/1234567890/view')
            ->call('nextStep')
            ->assertSet('currentStep', 3)
            ->call('save');

        if ($comp->get('errorMessage')) {
            dump($comp->get('errorMessage'));
        }
        $comp->assertHasNoErrors();

        $letter = Letter::withoutGlobalScopes()->where('perihal', 'Permohonan Kerjasama Sponsor Eksternal')->first();
        $this->assertNotNull($letter);
        $this->assertTrue($letter->is_manual_number);
        $this->assertNull($letter->template_id);
        $this->assertSame('https://drive.google.com/file/d/1234567890/view', $letter->link_drive);
        $this->assertStringContainsString('001/SPm-e/PAN-JKBFEST/HIMATRIS/', $letter->formatted_number);
        $this->assertSame('PT Telkom Indonesia Tbk', $letter->field_values['penerima']);
    }

    public function test_letter_types_management_settings(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        $component = Livewire::actingAs($user)
            ->test(LetterTypesSettings::class)
            ->assertSee('SPm')
            ->assertSee('Surat Permohonan')
            // Add custom letter type
            ->set('code', 'DISP')
            ->set('name', 'Surat Dispensasi Mahasiswa')
            ->set('description', 'Izin dispensasi kuliah kegiatan')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('DISP')
            ->assertSee('Surat Dispensasi Mahasiswa');

        $ws->refresh();
        $types = $ws->letterTypes();
        $disp = collect($types)->firstWhere('code', 'DISP');
        $this->assertNotNull($disp);
        $this->assertSame('Surat Dispensasi Mahasiswa', $disp['name']);

        // Delete custom letter type
        $dispIndex = collect($types)->search(fn ($t) => $t['code'] === 'DISP');
        $component->call('deleteType', $dispIndex)
            ->assertDontSee('Surat Dispensasi Mahasiswa');

        $ws->refresh();
        $this->assertNull(collect($ws->letterTypes())->firstWhere('code', 'DISP'));
    }

    public function test_template_deletion_protection(): void
    {
        [$ws, $user] = $this->setupWorkspace();

        Storage::fake('local');
        $filePath = 'templates/ws/test-delete.docx';
        Storage::disk('local')->put($filePath, 'fake docx content');

        $tpl = LetterTemplate::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'scope' => TemplateScope::Workspace,
            'name' => 'Template Hapus Uji',
            'file_path' => $filePath,
            'variables_schema' => [],
            'version' => 1,
        ]);

        // Template has an existing letter
        $letter = Letter::withoutGlobalScopes()->create([
            'workspace_id' => $ws->id,
            'template_id' => $tpl->id,
            'created_by' => $user->id,
            'perihal' => 'Surat Menggunakan Template',
            'letter_date' => now(),
            'status' => LetterStatus::Draft,
            'field_values' => [],
        ]);

        // Test modal confirmation flow
        Livewire::actingAs($user)
            ->test(TemplatesIndex::class)
            ->call('confirmDelete', $tpl->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingTemplateId', $tpl->id)
            ->assertSet('deletingLetterCount', 1)
            ->call('cancelDelete')
            ->assertSet('showDeleteModal', false)
            ->call('confirmDelete', $tpl->id)
            ->call('deleteConfirmed')
            ->assertSet('showDeleteModal', false)
            ->assertDispatched('toast');

        // Template deleted, but letter preserved with template_id detached to null
        $this->assertDatabaseMissing('letter_templates', ['id' => $tpl->id]);
        $this->assertFalse(Storage::disk('local')->exists($filePath));

        $letter->refresh();
        $this->assertNull($letter->template_id);
    }
}
