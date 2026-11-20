<div class="space-y-6">
    {{-- Header & Notifikasi --}}
    @if ($successMessage)
        <x-ui.alert variant="default" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
            <x-lucide-check-circle-2 class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <div class="text-xs font-medium">{{ $successMessage }}</div>
        </x-ui.alert>
    @endif

    @if ($errorMessage)
        <x-ui.alert variant="destructive">
            <x-lucide-alert-triangle class="size-4 shrink-0" />
            <div class="text-xs font-medium">{{ $errorMessage }}</div>
        </x-ui.alert>
    @endif

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-foreground">Daftar Seluruh Workspace</h2>
            <p class="text-xs text-muted-foreground">Kelola, pantau statistik, atau hapus workspace organisasi di seluruh sistem.</p>
        </div>
        <div class="w-full sm:w-72">
            <x-ui.input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama, slug, kode..."
                class="text-xs"
            />
        </div>
    </div>

    {{-- Tabel Seluruh Workspace --}}
    <x-ui.card>
        <x-ui.card-content class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 text-left text-xs font-semibold text-muted-foreground">
                            <th class="px-5 py-3">Nama Workspace</th>
                            <th class="px-4 py-3">Kode Penomoran</th>
                            <th class="px-4 py-3 text-center">Anggota</th>
                            <th class="px-4 py-3 text-center">Surat Keluar</th>
                            <th class="px-4 py-3 text-center">Surat Masuk</th>
                            <th class="px-4 py-3">Tanggal Dibuat</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($workspaces as $ws)
                            <tr class="hover:bg-muted/20 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-bold text-xs">
                                            {{ strtoupper(substr($ws->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-foreground text-xs">{{ $ws->name }}</p>
                                            <p class="text-[11px] text-muted-foreground font-mono">/{{ $ws->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs text-muted-foreground">
                                    {{ $ws->number_code ?: '-' }}
                                </td>
                                <td class="px-4 py-3.5 text-center text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-muted">
                                        {{ $ws->members_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                        {{ $ws->letters_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                        {{ $ws->surat_masuks_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-muted-foreground">
                                    {{ $ws->created_at?->translatedFormat('d M Y') ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <x-ui.button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            wire:click="switchToWorkspace({{ $ws->id }})"
                                            class="h-8 gap-1 text-xs"
                                            title="Masuk ke Workspace ini"
                                        >
                                            <x-lucide-external-link class="size-3.5" />
                                            <span>Buka</span>
                                        </x-ui.button>
                                        <x-ui.button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            wire:click="confirmDelete({{ $ws->id }})"
                                            class="h-8 px-2 text-xs"
                                            title="Hapus Workspace"
                                        >
                                            <x-lucide-trash-2 class="size-3.5" />
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-xs text-muted-foreground">
                                    Tidak ada workspace yang sesuai dengan pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($workspaces->hasPages())
                <div class="p-4 border-t border-border">
                    {{ $workspaces->links() }}
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- MODAL KONFIRMASI HAPUS WORKSPACE (SUPERADMIN) --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5 bg-destructive/10">
                    <div class="flex items-center gap-2 text-destructive">
                        <x-lucide-alert-triangle class="size-5 shrink-0" />
                        <h3 class="text-base font-bold">Hapus Workspace (Super Admin)</h3>
                    </div>
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <div class="p-3.5 rounded-lg border border-destructive/30 bg-destructive/10 text-destructive space-y-1">
                        <p class="font-bold text-sm">Peringatan: Tindakan Destruktif Permanen!</p>
                        <p>
                            Workspace <strong>{{ $selectedWorkspaceName }}</strong> beserta seluruh surat keluar, surat masuk, arsip berkas, dan hak akses anggotanya akan dihapus permanen dari sistem.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <x-ui.label for="confirm-admin-name">
                            Ketik nama workspace persis <strong class="text-foreground font-mono bg-muted px-1.5 py-0.5 rounded">{{ $selectedWorkspaceName }}</strong> untuk mengonfirmasi:
                        </x-ui.label>
                        <x-ui.input
                            id="confirm-admin-name"
                            wire:model.live="confirmWorkspaceName"
                            placeholder="{{ $selectedWorkspaceName }}"
                            class="font-mono text-sm"
                            autofocus
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3 bg-muted/20">
                    <x-ui.button type="button" variant="outline" wire:click="$set('showDeleteModal', false)">
                        Batal
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="destructive"
                        wire:click="deleteWorkspace"
                        wire:loading.attr="disabled"
                        :disabled="trim($confirmWorkspaceName) !== $selectedWorkspaceName"
                        class="gap-1.5"
                    >
                        <x-lucide-trash-2 class="size-4" wire:loading.remove wire:target="deleteWorkspace" />
                        <x-lucide-loader-2 class="size-4 animate-spin" wire:loading wire:target="deleteWorkspace" />
                        <span>Hapus Permanen</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
