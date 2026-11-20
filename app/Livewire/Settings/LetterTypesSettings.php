<?php

namespace App\Livewire\Settings;

use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manajemen Jenis Surat')]
class LetterTypesSettings extends Component
{
    /** @var array<int, array{code: string, name: string, description: string}> */
    public array $letterTypes = [];

    public bool $showModal = false;

    public ?int $editingIndex = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        $this->letterTypes = $ws->letterTypes();
    }

    public function openCreateModal(): void
    {
        $this->reset(['code', 'name', 'description', 'editingIndex']);
        $this->showModal = true;
    }

    public function openEditModal(int $index): void
    {
        if (! isset($this->letterTypes[$index])) {
            return;
        }

        $this->editingIndex = $index;
        $this->code = $this->letterTypes[$index]['code'];
        $this->name = $this->letterTypes[$index]['name'];
        $this->description = $this->letterTypes[$index]['description'] ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        $this->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
        ], [
            'code.required' => 'Kode jenis surat wajib diisi.',
            'code.regex' => 'Kode jenis surat hanya boleh berisi huruf, angka, dan strip (contoh: SPm, DISP, SKet).',
            'name.required' => 'Nama jenis surat wajib diisi.',
        ]);

        $entry = [
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'description' => trim($this->description),
        ];

        if ($this->editingIndex !== null && isset($this->letterTypes[$this->editingIndex])) {
            $this->letterTypes[$this->editingIndex] = $entry;
            $msg = 'Jenis surat diperbarui';
        } else {
            // Check duplicate code
            foreach ($this->letterTypes as $existing) {
                if (strcasecmp($existing['code'], $entry['code']) === 0) {
                    $this->addError('code', 'Kode jenis surat ini sudah terdaftar.');

                    return;
                }
            }
            $this->letterTypes[] = $entry;
            $msg = 'Jenis surat baru ditambahkan';
        }

        $this->persist($ws);
        $this->showModal = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => $msg,
            'description' => "[{$entry['code']}] {$entry['name']}",
        ]);
    }

    public function deleteType(int $index): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        if (isset($this->letterTypes[$index])) {
            $deleted = $this->letterTypes[$index];
            unset($this->letterTypes[$index]);
            $this->letterTypes = array_values($this->letterTypes);
            $this->persist($ws);

            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Jenis surat dihapus',
                'description' => "[{$deleted['code']}] {$deleted['name']}",
            ]);
        }
    }

    public function resetToDefaults(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        $this->letterTypes = config('suratinaja.letter_types', []);
        $this->persist($ws);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Daftar jenis surat direset',
            'description' => 'Kembali ke 10 jenis surat standar HIMATRIS.',
        ]);
    }

    private function persist($ws): void
    {
        $ws->fill(['letter_types_config' => array_values($this->letterTypes)])->save();
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();

        return view('livewire.settings.letter-types-settings', [
            'workspace' => $ws,
        ])->layout('components.layouts.app', [
            'title' => 'Jenis Surat',
            'breadcrumbs' => [
                ['label' => 'Konfigurasi', 'url' => route('settings.numbering')],
                ['label' => 'Jenis Surat'],
            ],
        ]);
    }
}
