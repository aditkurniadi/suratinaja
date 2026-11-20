<?php

namespace App\Livewire\SuratMasuk;

use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Daftar Surat Masuk')]
class SuratMasukIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $sifat = '';

    #[Url(history: true)]
    public string $balasan = '';

    #[Url(history: true)]
    public string $dateFrom = '';

    #[Url(history: true)]
    public string $dateTo = '';

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || (! auth()->user()->can('surat_masuk.view') && ! $ws->hasMember(auth()->user()))) {
            abort(403, 'Anda tidak memiliki hak akses melihat surat masuk.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSifat(): void
    {
        $this->resetPage();
    }

    public function updatingBalasan(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'sifat', 'balasan', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function deleteSuratMasuk(int $id): void
    {
        if (! auth()->user()->can('surat_masuk.delete')) {
            abort(403);
        }

        $ws = app(CurrentWorkspace::class)->get();
        $sm = SuratMasuk::where('workspace_id', $ws->id)->findOrFail($id);

        if ($sm->file_path && Storage::disk('local')->exists($sm->file_path)) {
            Storage::disk('local')->delete($sm->file_path);
        }

        $sm->delete();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Surat masuk dihapus',
            'description' => "Arsip {$sm->nomor_agenda} berhasil dihapus.",
        ]);
    }

    public function archiveSuratMasuk(int $id): void
    {
        if (! auth()->user()->can('surat_masuk.archive')) {
            abort(403);
        }

        $ws = app(CurrentWorkspace::class)->get();
        $sm = SuratMasuk::where('workspace_id', $ws->id)->findOrFail($id);
        $sm->update(['status' => StatusSuratMasuk::Diarsipkan]);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Surat masuk diarsipkan',
            'description' => "Status {$sm->nomor_agenda} diubah menjadi Diarsipkan.",
        ]);
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();

        $query = SuratMasuk::with(['createdBy', 'disposisis.kepadaUser', 'replyLetters.activeNumber', 'linkedSuratKeluar.activeNumber'])
            ->where('workspace_id', $ws->id)
            ->orderByDesc('id');

        if ($this->search !== '') {
            $q = trim($this->search);
            $query->where(function ($sub) use ($q) {
                $sub->where('nomor_surat_pengirim', 'LIKE', "%{$q}%")
                    ->orWhere('nomor_agenda', 'LIKE', "%{$q}%")
                    ->orWhere('pengirim', 'LIKE', "%{$q}%")
                    ->orWhere('perihal', 'LIKE', "%{$q}%")
                    ->orWhere('ringkasan', 'LIKE', "%{$q}%")
                    ->orWhere('nomor_surat_balasan', 'LIKE', "%{$q}%");
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->sifat !== '') {
            $query->where('sifat_surat', $this->sifat);
        }

        if ($this->balasan === 'sudah') {
            $query->where(function ($bSub) {
                $bSub->has('replyLetters')
                    ->orWhereNotNull('surat_keluar_id')
                    ->orWhere(fn ($rawQ) => $rawQ->whereNotNull('nomor_surat_balasan')->where('nomor_surat_balasan', '!=', ''));
            });
        } elseif ($this->balasan === 'belum') {
            $query->whereDoesntHave('replyLetters')
                ->whereNull('surat_keluar_id')
                ->where(function ($rawQ) {
                    $rawQ->whereNull('nomor_surat_balasan')->orWhere('nomor_surat_balasan', '=', '');
                });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('tanggal_diterima', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('tanggal_diterima', '<=', $this->dateTo);
        }

        $suratMasuks = $query->paginate(15);

        return view('livewire.surat-masuk.surat-masuk-index', [
            'workspace' => $ws,
            'suratMasuks' => $suratMasuks,
            'sifatOptions' => SifatSuratMasuk::options(),
            'statusOptions' => StatusSuratMasuk::options(),
        ])->layout('components.layouts.app', [
            'title' => 'Surat Masuk',
            'breadcrumbs' => [
                ['label' => 'Surat Masuk'],
            ],
        ]);
    }
}
