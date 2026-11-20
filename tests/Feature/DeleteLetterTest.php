<?php

namespace Tests\Feature;

use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\DeleteLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\DemoModeRestrictedException;
use App\Exceptions\DomainException;
use App\Livewire\Letters\LetterShow;
use App\Livewire\Letters\LettersIndex;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Templates\SampleDocxBuilder;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteLetterTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(WorkspaceRole $role = WorkspaceRole::Secretary, bool $isDemo = false): array
    {
        $ws = Workspace::create([
            'name' => 'HIMATRIS',
            'slug' => 'himatris-delete-test',
            'number_pattern' => '{seq}/HIMATRIS/{bulan_romawi}/{tahun}',
            'number_code' => 'HIMATRIS',
            'is_activity' => false,
            'seq_padding' => 3,
        ]);

        $user = User::factory()->create([
            'name' => 'Operator Test',
            'current_workspace_id' => $ws->id,
            'is_demo' => $isDemo,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        app(CurrentWorkspace::class)->set($ws);

        return [$ws, $user];
    }

    private function createTemplate(Workspace $ws, User $user): LetterTemplate
    {
        Storage::disk('local')->makeDirectory('templates/workspaces/'.$ws->id);
        $relPath = 'templates/workspaces/'.$ws->id.'/tpl_del.docx';
        SampleDocxBuilder::create(Storage::disk('local')->path($relPath));

        return LetterTemplate::create([
            'workspace_id' => $ws->id,
            'scope' => 'workspace',
            'name' => 'Undangan Rapat',
            'file_path' => $relPath,
            'variables_schema' => [
                ['name' => 'nama_kegiatan', 'type' => 'text', 'required' => true],
            ],
            'version' => 1,
            'created_by' => $user->id,
        ]);
    }

    public function test_can_delete_draft_letter_and_releases_number_without_burning(): void
    {
        [$ws, $user] = $this->setupWorkspace();
        $template = $this->createTemplate($ws, $user);

        // 1. Buat surat pertama (akan mendapat nomor urut 1)
        $creator = app(CreateLetterAction::class);
        $letter1 = $creator->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Undangan Diskusi 1',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Diskusi 1'],
        ]);

        $this->assertSame(1, (int) $letter1->activeNumber->sequence);
        $this->assertDatabaseHas('letter_number_sequences', [
            'workspace_id' => $ws->id,
            'last_sequence' => 1,
        ]);

        // 2. Buat surat kedua (akan mendapat nomor urut 2)
        $letter2 = $creator->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Undangan Diskusi 2 (Salah Buat)',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Diskusi 2'],
        ]);

        $this->assertSame(2, (int) $letter2->activeNumber->sequence);
        $this->assertDatabaseHas('letter_number_sequences', [
            'workspace_id' => $ws->id,
            'last_sequence' => 2,
        ]);

        // 3. Hapus draf surat kedua yang salah buat
        $deleteAction = app(DeleteLetterAction::class);
        $result = $deleteAction->execute($letter2, $user);

        $this->assertTrue($result);

        // Pastikan letter2 dan alokasi nomornya terhapus bersih dari database
        $this->assertDatabaseMissing('letters', ['id' => $letter2->id]);
        $this->assertDatabaseMissing('letter_numbers', ['letter_id' => $letter2->id]);

        // Pastikan counter last_sequence di-rollback ke 1 (bukan tetap 2)
        $this->assertDatabaseHas('letter_number_sequences', [
            'workspace_id' => $ws->id,
            'last_sequence' => 1,
        ]);

        // 4. Buat surat baru lagi -> harus mendapatkan kembali nomor urut 2 (TIDAK HANGUS / TIDAK LONCAT KE 3)
        $letter3 = $creator->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Undangan Diskusi Pengganti',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Diskusi Pengganti'],
        ]);

        $letter3Number = $letter3->activeNumber;
        $this->assertSame(2, (int) $letter3Number->sequence);
        $this->assertSame(NumberStatus::Aktif, $letter3Number->status);

        // Pastikan tidak ada nomor yang berstatus HANGUS
        $hangusCount = LetterNumber::where('workspace_id', $ws->id)->where('status', NumberStatus::Hangus->value)->count();
        $this->assertSame(0, $hangusCount);
    }

    public function test_cannot_delete_signed_letter(): void
    {
        [$ws, $user] = $this->setupWorkspace();
        $template = $this->createTemplate($ws, $user);

        $letter = app(CreateLetterAction::class)->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Surat Resmi Ditandatangani',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Rapat Resmi'],
        ]);

        // Transisi ke menunggu_ttd lalu selesai (signed)
        $transition = app(TransitionLetterStatusAction::class);
        $transition->execute($letter, $user, LetterStatus::MenungguTtd);
        $signedLetter = $transition->execute($letter, $user, LetterStatus::Selesai);

        $this->assertNotNull($signedLetter->signed_at);
        $this->assertSame(LetterStatus::Selesai, $signedLetter->status);

        // Eksekusi DeleteLetterAction harus ditolak dengan DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Surat yang sudah ditandatangani (Selesai) tidak dapat dihapus');

        app(DeleteLetterAction::class)->execute($signedLetter, $user);
    }

    public function test_non_authorized_user_cannot_delete_letter(): void
    {
        [$ws, $sec] = $this->setupWorkspace(WorkspaceRole::Secretary);
        $template = $this->createTemplate($ws, $sec);

        $letter = app(CreateLetterAction::class)->execute($ws, $sec, [
            'template_id' => $template->id,
            'perihal' => 'Surat Draf Rahasia',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Rapat Tertutup'],
        ]);

        // Buat user dengan role Chairman / Ketua (hanya baca, tidak punya surat_keluar.delete)
        $chairman = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $chairman->id,
            'role' => WorkspaceRole::Chairman,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Anda tidak memiliki izin menghapus surat');

        app(DeleteLetterAction::class)->execute($letter, $chairman);
    }

    public function test_demo_user_is_blocked_from_deleting_letter(): void
    {
        [$ws, $demoUser] = $this->setupWorkspace(WorkspaceRole::Admin, isDemo: true);
        $template = $this->createTemplate($ws, $demoUser);

        // Buat surat terlebih dahulu sebagai user biasa
        $realUser = User::factory()->create(['current_workspace_id' => $ws->id]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $realUser->id,
            'role' => WorkspaceRole::Admin,
        ]);

        $letter = app(CreateLetterAction::class)->execute($ws, $realUser, [
            'template_id' => $template->id,
            'perihal' => 'Draf Percobaan Demo',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Kegiatan Demo'],
        ]);

        $this->actingAs($demoUser);

        $this->expectException(DemoModeRestrictedException::class);
        app(DeleteLetterAction::class)->execute($letter, $demoUser);
    }

    public function test_livewire_letter_show_and_index_delete_action(): void
    {
        [$ws, $user] = $this->setupWorkspace();
        $template = $this->createTemplate($ws, $user);

        $letter = app(CreateLetterAction::class)->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Draf untuk Dihapus via UI',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Test UI'],
        ]);

        // Test LetterShow: confirmDelete
        Livewire::actingAs($user)
            ->test(LetterShow::class, ['letter' => $letter])
            ->assertSee('Hapus Draf Surat')
            ->call('confirmDelete')
            ->assertRedirect(route('letters.index'))
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('letters', ['id' => $letter->id]);

        // Buat satu lagi untuk test LettersIndex: deleteLetter
        $letter2 = app(CreateLetterAction::class)->execute($ws, $user, [
            'template_id' => $template->id,
            'perihal' => 'Draf Kedua untuk Dihapus via Index',
            'letter_date' => now()->toDateString(),
            'variables' => ['nama_kegiatan' => 'Test Index UI'],
        ]);

        Livewire::actingAs($user)
            ->test(LettersIndex::class)
            ->assertSee('Draf Kedua untuk Dihapus via Index')
            ->call('deleteLetter', $letter2->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('letters', ['id' => $letter2->id]);
    }
}
