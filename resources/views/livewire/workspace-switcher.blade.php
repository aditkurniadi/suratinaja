<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = !open"
        class="flex w-full items-center gap-2.5 rounded-lg p-2 text-left text-sm transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
        aria-haspopup="menu"
        :aria-expanded="open"
    >
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-semibold text-xs">
            {{ strtoupper(substr($activeWorkspace?->name ?? 'SA', 0, 2)) }}
        </div>
        <div class="grid flex-1 text-left text-sm leading-tight min-w-0" x-show="$store.sidebar.open">
            <span class="truncate font-semibold text-sidebar-foreground">
                {{ $activeWorkspace?->name ?? 'Pilih Workspace' }}
            </span>
            <span class="truncate text-xs text-muted-foreground">
                {{ $activeRole?->label() ?? 'Belum bergabung' }}
            </span>
        </div>
        <x-lucide-chevrons-up-down x-show="$store.sidebar.open" class="ml-auto size-4 shrink-0 text-muted-foreground" />
    </button>

    <div
        x-cloak
        x-show="open"
        @click.outside="open = false"
        class="absolute left-0 top-full z-50 mt-1 w-64 rounded-lg border border-border bg-popover p-1.5 text-popover-foreground shadow-lg"
        role="menu"
    >
        <div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">
            Workspaces
        </div>
        @foreach ($workspaces as $ws)
            <button
                type="button"
                wire:click="switchTo({{ $ws->id }})"
                @click="open = false"
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                role="menuitem"
            >
                <div class="flex h-6 w-6 items-center justify-center rounded border border-border bg-background text-[11px] font-semibold">
                    {{ strtoupper(substr($ws->name, 0, 2)) }}
                </div>
                <span class="flex-1 truncate text-left font-medium">{{ $ws->name }}</span>
                @if ($activeWorkspace && $activeWorkspace->id === $ws->id)
                    <x-lucide-check class="size-4 text-foreground" />
                @endif
            </button>
        @endforeach

        <x-ui.separator class="my-1.5" />

        <button
            type="button"
            @click="open = false; $wire.set('showCreateModal', true)"
            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
        >
            <x-lucide-plus class="size-3.5" />
            <span>Buat Workspace Baru</span>
        </button>
    </div>

    @if ($showCreateModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-ws-title"
        >
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 id="create-ws-title" class="text-base font-semibold">Buat Workspace Baru</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Setiap organisasi atau kepanitiaan memiliki isolasi surat, kop, dan urutan nomor sendiri.
                </p>

                <form wire:submit="createWorkspace" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="ws-name">Nama Organisasi / Kepanitiaan</x-ui.label>
                        <x-ui.input id="ws-name" wire:model="newWorkspaceName" class="mt-1.5" placeholder="Mis. HIMATRIS / Panitia OASIS 2026" />
                        <x-ui.form-error for="newWorkspaceName" />
                    </div>
                    <div>
                        <x-ui.label for="ws-code">Kode Penomoran ({kode})</x-ui.label>
                        <x-ui.input id="ws-code" wire:model="newWorkspaceCode" class="mt-1.5 font-mono" placeholder="Mis. HIMATRIS" />
                        <x-ui.form-error for="newWorkspaceCode" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="outline" wire:click="$set('showCreateModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Buat Workspace</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
