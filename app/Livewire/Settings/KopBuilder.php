<?php

namespace App\Livewire\Settings;

use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Builder Kop Surat')]
class KopBuilder extends Component
{
    use WithFileUploads;

    public string $kopHeaderText = '';

    public string $kopAddress = '';

    public $logoLeftUpload = null;

    public $logoRightUpload = null;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $this->kopHeaderText = (string) ($ws->kop_header_text ?? '');
        $this->kopAddress = (string) ($ws->kop_address ?? '');
    }

    public function save(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $maxKb = (int) config('suratinaja.uploads.logo_max_kb', 2048);

        $this->validate([
            'kopHeaderText' => ['nullable', 'string', 'max:255'],
            'kopAddress' => ['nullable', 'string', 'max:255'],
            'logoLeftUpload' => ['nullable', 'file', 'mimes:png,jpg,jpeg', "max:{$maxKb}"],
            'logoRightUpload' => ['nullable', 'file', 'mimes:png,jpg,jpeg', "max:{$maxKb}"],
        ]);

        $updates = [
            'kop_header_text' => trim($this->kopHeaderText) ?: null,
            'kop_address' => trim($this->kopAddress) ?: null,
        ];

        if ($this->logoLeftUpload) {
            if ($ws->kop_logo_left) {
                Storage::disk('local')->delete($ws->kop_logo_left);
            }
            $updates['kop_logo_left'] = $this->logoLeftUpload->store("kop/{$ws->id}", 'local');
            $this->logoLeftUpload = null;
        }

        if ($this->logoRightUpload) {
            if ($ws->kop_logo_right) {
                Storage::disk('local')->delete($ws->kop_logo_right);
            }
            $updates['kop_logo_right'] = $this->logoRightUpload->store("kop/{$ws->id}", 'local');
            $this->logoRightUpload = null;
        }

        $ws->fill($updates)->save();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Konfigurasi kop surat disimpan',
            'description' => 'Kop baru akan otomatis menempel pada surat yang digenerate berikutnya.',
        ]);
    }

    public function removeLogo(string $side): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $column = $side === 'right' ? 'kop_logo_right' : 'kop_logo_left';
        if ($ws->{$column}) {
            Storage::disk('local')->delete($ws->{$column});
            $ws->forceFill([$column => null])->save();
        }
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get()?->fresh();

        return view('livewire.settings.kop-builder', [
            'workspace' => $ws,
            'leftDataUri' => $this->logoDataUri($ws?->kop_logo_left),
            'rightDataUri' => $this->logoDataUri($ws?->kop_logo_right),
        ]);
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
