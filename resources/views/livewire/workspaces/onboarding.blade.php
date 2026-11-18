<div class="space-y-5">
    @if ($invitations->isNotEmpty())
        <div class="space-y-2">
            <h2 class="text-sm font-semibold">Undangan Workspace Menunggu</h2>
            @foreach ($invitations as $inv)
                <div class="flex items-center justify-between rounded-lg border border-border p-3 text-xs">
                    <div>
                        <p class="font-medium text-foreground">{{ $inv->workspace->name }}</p>
                        <p class="text-muted-foreground">Peran: {{ $inv->role->label() }}</p>
                    </div>
                    <x-ui.button size="sm" wire:click="acceptPending({{ $inv->id }})">Gabung</x-ui.button>
                </div>
            @endforeach
        </div>
        <x-ui.separator />
    @endif

    <div>
        <h2 class="text-base font-semibold">Buat Workspace Organisasi</h2>
        <p class="text-xs text-muted-foreground mt-0.5">
            Anda belum tergabung pada workspace mana pun. Buat workspace baru untuk memulai.
        </p>
    </div>

    <form wire:submit="create" class="space-y-3.5">
        <div>
            <x-ui.label for="onboard-name">Nama Organisasi / Kepanitiaan</x-ui.label>
            <x-ui.input id="onboard-name" wire:model="name" class="mt-1" placeholder="Mis. HIMATRIS" required />
            <x-ui.form-error for="name" />
        </div>
        <div>
            <x-ui.label for="onboard-code">Kode Penomoran ({kode})</x-ui.label>
            <x-ui.input id="onboard-code" wire:model="code" class="mt-1 font-mono" placeholder="Mis. HIMATRIS" />
        </div>
        <x-ui.button type="submit" class="w-full">Buat Workspace</x-ui.button>
    </form>
</div>
