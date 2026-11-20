<?php

namespace App\Livewire\SuratMasuk;

use App\Enums\StatusDisposisi;
use App\Enums\StatusSuratMasuk;
use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail Surat Masuk & Disposisi')]
class SuratMasukShow extends Component
{
    public SuratMasuk $suratMasuk;

    public bool $showDisposisiModal = false;
    public ?int $disposisiKepadaUserId = null;
    public string $disposisiTujuanDivisi = '';
    public string $disposisiInstruksi = '';
    public ?string $disposisiBatasWaktu = null;

    public bool $showTindakLanjutModal = false;
    public ?int $editingDisposisiId = null;
    public string $tindakLanjutStatus = 'belum_diproses';
    public string $tindakLanjutCatatan = '';

    public bool $showLinkSuratKeluarModal = false;
    public ?int $selectedSuratKeluarId = null;
    public string $searchSuratKeluar = '';

    public bool $showManualBalasanModal = false;
    public string $nomorSuratBalasan = '';
    public string $catatanBalasan = '';

    public function mount(SuratMasuk|int $suratMasuk): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            abort(403);
        }

        $sm = $suratMasuk instanceof SuratMasuk ? $suratMasuk : SuratMasuk::findOrFail($suratMasuk);
        if ((int) $sm->workspace_id !== (int) $ws->id) {
            abort(403);
        }

        if (! auth()->user()->can('surat_masuk.view') && ! $ws->hasMember(auth()->user())) {
            abort(403, 'Anda tidak memiliki hak akses melihat surat masuk.');
        }

        $sm->load(['replyLetters.activeNumber', 'replyLetters.author', 'linkedSuratKeluar.activeNumber', 'linkedSuratKeluar.author']);
        $this->suratMasuk = $sm;
        $this->nomorSuratBalasan = (string) ($sm->nomor_surat_balasan ?? '');
        $this->catatanBalasan = (string) ($sm->catatan_balasan ?? '');
    }

    public function openDisposisiModal(): void
    {
        if (! auth()->user()->can('disposisi.create')) {
            abort(403, 'Hanya Pimpinan atau Admin yang dapat memberi disposisi.');
        }

        $this->reset(['disposisiKepadaUserId', 'disposisiTujuanDivisi', 'disposisiInstruksi', 'disposisiBatasWaktu']);
        $this->showDisposisiModal = true;
    }

    public function saveDisposisi(): void
    {
        if (! auth()->user()->can('disposisi.create')) {
            abort(403);
        }

        $this->validate([
            'disposisiKepadaUserId' => ['nullable', 'exists:users,id'],
            'disposisiTujuanDivisi' => ['nullable', 'string', 'max:100'],
            'disposisiInstruksi' => ['required', 'string', 'max:2000'],
            'disposisiBatasWaktu' => ['nullable', 'date'],
        ]);

        if (empty($this->disposisiKepadaUserId) && empty(trim((string) $this->disposisiTujuanDivisi))) {
            $this->addError('disposisiTarget', 'Pilih anggota penerima atau sebutkan nama divisi tujuan disposisi.');

            return;
        }

        $disp = Disposisi::create([
            'surat_masuk_id' => $this->suratMasuk->id,
            'dari_user_id' => auth()->id(),
            'kepada_user_id' => $this->disposisiKepadaUserId ?: null,
            'tujuan_divisi' => trim((string) $this->disposisiTujuanDivisi) !== '' ? trim((string) $this->disposisiTujuanDivisi) : null,
            'instruksi' => trim($this->disposisiInstruksi),
            'batas_waktu' => $this->disposisiBatasWaktu ?: null,
            'status_tindak_lanjut' => StatusDisposisi::BelumDiproses,
        ]);

        // Auto update status surat masuk to didisposisi if it was baru / menunggu_disposisi
        if (in_array($this->suratMasuk->status, [StatusSuratMasuk::Baru, StatusSuratMasuk::MenungguDisposisi], true)) {
            $this->suratMasuk->update(['status' => StatusSuratMasuk::Didisposisi]);
        }

        $this->suratMasuk->refresh();
        $this->showDisposisiModal = false;

        $targetName = $disp->targetDisplay();
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Disposisi Diberikan',
            'description' => "Instruksi berhasil ditujukan kepada {$targetName}.",
        ]);
    }

    public function openTindakLanjutModal(int $disposisiId): void
    {
        $disp = Disposisi::where('surat_masuk_id', $this->suratMasuk->id)->findOrFail($disposisiId);
        $this->editingDisposisiId = $disp->id;
        $this->tindakLanjutStatus = $disp->status_tindak_lanjut->value;
        $this->tindakLanjutCatatan = (string) ($disp->catatan_tindak_lanjut ?? '');
        $this->showTindakLanjutModal = true;
    }

    public function saveTindakLanjut(): void
    {
        if (! auth()->user()->can('disposisi.update_status')) {
            abort(403);
        }

        $this->validate([
            'tindakLanjutStatus' => ['required', 'in:belum_diproses,sedang_diproses,selesai'],
            'tindakLanjutCatatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $disp = Disposisi::where('surat_masuk_id', $this->suratMasuk->id)->findOrFail($this->editingDisposisiId);

        $isCompleted = ($this->tindakLanjutStatus === StatusDisposisi::Selesai->value);
        $disp->update([
            'status_tindak_lanjut' => $this->tindakLanjutStatus,
            'catatan_tindak_lanjut' => trim((string) $this->tindakLanjutCatatan) !== '' ? trim((string) $this->tindakLanjutCatatan) : null,
            'diselesaikan_pada' => $isCompleted ? now() : null,
        ]);

        // If all dispositions are completed, automatically mark the letter as selesai
        $allDone = $this->suratMasuk->disposisis()->where('status_tindak_lanjut', '!=', StatusDisposisi::Selesai->value)->count() === 0;
        if ($allDone && $this->suratMasuk->status === StatusSuratMasuk::Didisposisi) {
            $this->suratMasuk->update(['status' => StatusSuratMasuk::Selesai]);
        }

        $this->suratMasuk->refresh();
        $this->showTindakLanjutModal = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Status Tindak Lanjut Diperbarui',
            'description' => "Status diubah menjadi {$disp->status_tindak_lanjut->label()}.",
        ]);
    }

    public function deleteDisposisi(int $disposisiId): void
    {
        if (! auth()->user()->can('disposisi.delete')) {
            abort(403);
        }

        $disp = Disposisi::where('surat_masuk_id', $this->suratMasuk->id)->findOrFail($disposisiId);
        $disp->delete();

        $this->suratMasuk->refresh();
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Disposisi dihapus',
        ]);
    }

    public function deleteSuratMasuk(): void
    {
        if (! auth()->user()->can('surat_masuk.delete')) {
            abort(403);
        }

        $ws = app(CurrentWorkspace::class)->get();
        if ($this->suratMasuk->file_path && Storage::disk('local')->exists($this->suratMasuk->file_path)) {
            Storage::disk('local')->delete($this->suratMasuk->file_path);
        }

        $agenda = $this->suratMasuk->nomor_agenda;
        $this->suratMasuk->delete();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Surat masuk dihapus',
            'description' => "Arsip {$agenda} telah dihapus.",
        ]);

        $this->redirect(route('surat-masuk.index'), navigate: false);
    }

    public function openLinkSuratKeluarModal(): void
    {
        $this->selectedSuratKeluarId = $this->suratMasuk->surat_keluar_id;
        $this->showLinkSuratKeluarModal = true;
    }

    public function saveLinkSuratKeluar(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! auth()->user()->can('surat_masuk.edit') && ! $ws?->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $this->validate([
            'selectedSuratKeluarId' => ['nullable', 'exists:letters,id'],
        ]);

        if ($this->selectedSuratKeluarId) {
            $letter = \App\Models\Letter::find($this->selectedSuratKeluarId);
            if ($letter && (int) $letter->workspace_id === (int) $this->suratMasuk->workspace_id) {
                $letter->update(['surat_masuk_id' => $this->suratMasuk->id]);
            }
        }

        $this->suratMasuk->update([
            'surat_keluar_id' => $this->selectedSuratKeluarId,
        ]);

        $this->showLinkSuratKeluarModal = false;
        $this->suratMasuk->load(['replyLetters.activeNumber', 'replyLetters.author', 'linkedSuratKeluar.activeNumber']);
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Surat Balasan Ditautkan',
            'description' => 'Tautan surat keluar balasan berhasil diperbarui.',
        ]);
    }

    public function unlinkLetter(int $letterId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! auth()->user()->can('surat_masuk.edit') && ! $ws?->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $letter = \App\Models\Letter::where('workspace_id', $this->suratMasuk->workspace_id)->find($letterId);
        if ($letter && (int) $letter->surat_masuk_id === (int) $this->suratMasuk->id) {
            $letter->update(['surat_masuk_id' => null]);
        }

        if ((int) $this->suratMasuk->surat_keluar_id === (int) $letterId) {
            $this->suratMasuk->update(['surat_keluar_id' => null]);
        }

        $this->suratMasuk->load(['replyLetters.activeNumber', 'replyLetters.author', 'linkedSuratKeluar.activeNumber']);
        $this->dispatch('toast', [
            'type' => 'info',
            'title' => 'Tautan Dihapus',
            'description' => 'Surat keluar tidak lagi tertaut dengan surat masuk ini.',
        ]);
    }

    public function openManualBalasanModal(): void
    {
        $this->nomorSuratBalasan = (string) ($this->suratMasuk->nomor_surat_balasan ?? '');
        $this->catatanBalasan = (string) ($this->suratMasuk->catatan_balasan ?? '');
        $this->showManualBalasanModal = true;
    }

    public function saveManualBalasan(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! auth()->user()->can('surat_masuk.edit') && ! $ws?->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $this->validate([
            'nomorSuratBalasan' => ['nullable', 'string', 'max:150'],
            'catatanBalasan' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->suratMasuk->update([
            'nomor_surat_balasan' => trim($this->nomorSuratBalasan) ?: null,
            'catatan_balasan' => trim($this->catatanBalasan) ?: null,
        ]);

        $this->showManualBalasanModal = false;
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Catatan Balasan Disimpan',
            'description' => 'Informasi surat balasan manual berhasil disimpan.',
        ]);
    }

    public function selectAndSaveLinkSuratKeluar(int $letterId): void
    {
        $this->selectedSuratKeluarId = $letterId;
        $this->saveLinkSuratKeluar();
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        $workspaceUsers = $ws->users()->orderBy('name')->get();

        $letterQuery = \App\Models\Letter::where('workspace_id', $ws->id)
            ->with(['activeNumber', 'author'])
            ->orderByDesc('id');

        if (filled($this->searchSuratKeluar)) {
            $search = '%' . trim($this->searchSuratKeluar) . '%';
            $letterQuery->where(function ($q) use ($search) {
                $q->where('perihal', 'like', $search)
                    ->orWhereHas('activeNumber', fn ($sub) => $sub->where('formatted_number', 'like', $search));
            });
        }

        $availableLetters = $letterQuery->limit(20)->get();

        $pageTitle = 'Detail Surat Masuk (' . $this->suratMasuk->nomor_agenda . ')';

        return view('livewire.surat-masuk.surat-masuk-show', [
            'workspace' => $ws,
            'workspaceUsers' => $workspaceUsers,
            'disposisis' => $this->suratMasuk->disposisis,
            'availableLetters' => $availableLetters,
        ])->layout('components.layouts.app', [
            'title' => $pageTitle,
            'breadcrumbs' => [
                ['label' => 'Surat Masuk', 'url' => route('surat-masuk.index')],
                ['label' => $pageTitle],
            ],
            'backUrl' => route('surat-masuk.index'),
        ]);
    }
}
