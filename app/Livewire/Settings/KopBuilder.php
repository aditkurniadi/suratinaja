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

    public string $defaultCity = 'Cilacap';

    /** @var array<int, array{prefix: string, position: string, name: string, id_label: string, id_number: string, layout: string}> */
    public array $signers = [];

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
        $this->defaultCity = (string) ($ws->default_city ?: 'Cilacap');
        $this->signers = $ws->defaultSigners();
    }

    public function addSigner(): void
    {
        $this->signers[] = [
            'prefix' => '',
            'position' => '',
            'name' => '',
            'id_label' => 'NIP.',
            'id_number' => '',
            'layout' => 'auto',
        ];
    }

    public function applyPreset(string $key): void
    {
        $presets = \App\Models\Workspace::signerPresets();
        if (isset($presets[$key])) {
            $this->signers = $presets[$key]['signers'];
        }
    }

    public function removeSigner(int $index): void
    {
        if (isset($this->signers[$index])) {
            unset($this->signers[$index]);
            $this->signers = array_values($this->signers);
        }
    }

    public function moveSigner(int $fromIndex, int $toIndex): void
    {
        if (! isset($this->signers[$fromIndex]) || ! isset($this->signers[$toIndex])) {
            return;
        }

        $item = $this->signers[$fromIndex];
        array_splice($this->signers, $fromIndex, 1);
        array_splice($this->signers, $toIndex, 0, [$item]);
        $this->signers = array_values($this->signers);
    }

    public function moveSignerUp(int $index): void
    {
        if ($index > 0) {
            $this->moveSigner($index, $index - 1);
        }
    }

    public function moveSignerDown(int $index): void
    {
        if ($index < count($this->signers) - 1) {
            $this->moveSigner($index, $index + 1);
        }
    }

    public function save(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $maxKb = (int) config('suratinaja.uploads.logo_max_kb', 2048);

        $this->validate([
            'kopHeaderText' => ['nullable', 'string', 'max:500'],
            'kopAddress' => ['nullable', 'string', 'max:500'],
            'defaultCity' => ['nullable', 'string', 'max:100'],
            'signers' => ['nullable', 'array', 'max:12'],
            'signers.*.prefix' => ['nullable', 'string', 'max:60'],
            'signers.*.position' => ['nullable', 'string', 'max:120'],
            'signers.*.name' => ['nullable', 'string', 'max:120'],
            'signers.*.id_label' => ['nullable', 'string', 'max:30'],
            'signers.*.id_number' => ['nullable', 'string', 'max:60'],
            'signers.*.layout' => ['nullable', 'string', 'in:auto,center,new_page'],
            'logoLeftUpload' => ['nullable', 'file', 'mimes:png,jpg,jpeg', "max:{$maxKb}"],
            'logoRightUpload' => ['nullable', 'file', 'mimes:png,jpg,jpeg', "max:{$maxKb}"],
        ]);

        $cleanSigners = array_values(array_filter(
            array_map(fn (array $s) => [
                'prefix' => trim((string) ($s['prefix'] ?? '')),
                'position' => trim((string) ($s['position'] ?? '')),
                'name' => trim((string) ($s['name'] ?? '')),
                'id_label' => trim((string) ($s['id_label'] ?? '')),
                'id_number' => trim((string) ($s['id_number'] ?? '')),
                'layout' => (string) ($s['layout'] ?? 'auto'),
            ], $this->signers),
            fn (array $s) => $s['name'] !== '' || $s['position'] !== '' || $s['prefix'] !== ''
        ));

        $updates = [
            'kop_header_text' => trim($this->kopHeaderText) ?: null,
            'kop_address' => trim($this->kopAddress) ?: null,
            'default_city' => trim($this->defaultCity) ?: 'Cilacap',
            'signers_config' => $cleanSigners !== [] ? $cleanSigners : null,
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
