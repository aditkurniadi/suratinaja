<div class="space-y-6 max-w-4xl">
    {{-- Notifikasi Sukses / Error --}}
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

    {{-- KARTU 1: Identitas Workspace --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                    <x-lucide-building-2 class="size-5" />
                </div>
                <div>
                    <x-ui.card-title>Identitas Workspace</x-ui.card-title>
                    <x-ui.card-description>Ubah nama organisasi dan kode singkat untuk format penomoran surat.</x-ui.card-description>
                </div>
            </div>
        </x-ui.card-header>
        <x-ui.card-content class="pt-5">
            <form wire:submit="saveIdentity" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="ws-name">Nama Workspace / Organisasi</x-ui.label>
                        <x-ui.input id="ws-name" wire:model="name" class="mt-1.5" required placeholder="Mis. HIMATRIS" />
                        <x-ui.form-error for="name" />
                    </div>
                    <div>
                        <x-ui.label for="ws-code">Kode Penomoran Singkat ({kode})</x-ui.label>
                        <x-ui.input id="ws-code" wire:model="number_code" class="mt-1.5 font-mono uppercase" placeholder="Mis. HIMATRIS" />
                        <x-ui.form-error for="number_code" />
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" size="sm" class="gap-1.5">
                        <x-lucide-save class="size-4" />
                        <span>Simpan Perubahan</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>

    {{-- KARTU 2: Pemeliharaan Penyimpanan & Pembersihan Cache --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                        <x-lucide-hard-drive class="size-5" />
                    </div>
                    <div>
                        <x-ui.card-title>Penyimpanan &amp; Berkas Sementara</x-ui.card-title>
                        <x-ui.card-description>Pantau kapasitas storage dan bersihkan berkas PDF pratinjau yang mengendap.</x-ui.card-description>
                    </div>
                </div>
                <x-ui.button
                    type="button"
                    variant="outline"
                    size="sm"
                    wire:click="clearStorageCache"
                    wire:loading.attr="disabled"
                    class="gap-1.5 text-xs text-sky-600 hover:text-sky-700 hover:bg-sky-50 dark:hover:bg-sky-950/40"
                >
                    <x-lucide-sparkles class="size-3.5" wire:loading.remove wire:target="clearStorageCache" />
                    <x-lucide-loader-2 class="size-3.5 animate-spin" wire:loading wire:target="clearStorageCache" />
                    <span>Bersihkan Cache Pratinjau</span>
                </x-ui.button>
            </div>
        </x-ui.card-header>
        <x-ui.card-content class="pt-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-4 rounded-xl border border-border bg-muted/20 space-y-1">
                    <span class="text-xs text-muted-foreground font-medium">Pratinjau PDF Sementara</span>
                    <div class="flex items-baseline gap-2">
                        <p class="text-xl font-bold text-foreground">{{ $storageUsage['previews_formatted'] ?? '0 B' }}</p>
                        <span class="text-[11px] text-muted-foreground">({{ $storageUsage['previews_count'] ?? 0 }} file)</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground pt-1">Otomatis digenerate ulang saat surat dibuka.</p>
                </div>

                <div class="p-4 rounded-xl border border-border bg-muted/20 space-y-1">
                    <span class="text-xs text-muted-foreground font-medium">Hasil Generate / Bulk Export</span>
                    <div class="flex items-baseline gap-2">
                        <p class="text-xl font-bold text-foreground">{{ $storageUsage['generated_formatted'] ?? '0 B' }}</p>
                        <span class="text-[11px] text-muted-foreground">({{ $storageUsage['generated_count'] ?? 0 }} file)</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground pt-1">Hasil ekspor berkas surat massal.</p>
                </div>

                <div class="p-4 rounded-xl border border-border bg-muted/20 space-y-1">
                    <span class="text-xs text-muted-foreground font-medium">Total Ruang Digunakan</span>
                    <p class="text-xl font-bold text-primary">{{ $storageUsage['total_formatted'] ?? '0 B' }}</p>
                    <p class="text-[11px] text-muted-foreground pt-1">Termasuk template, logo, dan lampiran.</p>
                </div>
            </div>

            <div class="rounded-lg bg-sky-500/10 border border-sky-500/20 p-3 text-xs text-sky-900 dark:text-sky-200 flex items-start gap-2.5">
                <x-lucide-info class="size-4 text-sky-500 shrink-0 mt-0.5" />
                <div class="space-y-0.5">
                    <p class="font-semibold">Tentang Pembersihan Cache Pratinjau:</p>
                    <p class="text-muted-foreground">
                        Menekan tombol <b>Bersihkan Cache Pratinjau</b> sangat aman dan tidak menghapus surat, template, atau lampiran resmi. Fitur ini berguna untuk mengosongkan ruang disk VPS Dokploy dari file PDF sementara yang menumpuk.
                    </p>
                </div>
            </div>
        </x-ui.card-content>
    </x-ui.card>

    {{-- KARTU 3: Zona Bahaya (Danger Zone) --}}
    <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-5 space-y-4">
        <div class="flex items-center gap-2.5">
            <div class="p-2 rounded-lg bg-destructive/10 text-destructive">
                <x-lucide-alert-triangle class="size-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-destructive">Zona Bahaya</h3>
                <p class="text-xs text-muted-foreground">Tindakan berikut bersifat destruktif dan tidak dapat dibatalkan.</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2 border-t border-destructive/20">
            <div>
                <p class="text-sm font-semibold text-foreground">Hapus Workspace Ini</p>
                <p class="text-xs text-muted-foreground">
                    Menghapus seluruh surat keluar, surat masuk, master template, nomor surat, dan seluruh berkas fisik workspace ini secara permanen.
                </p>
            </div>
            <x-ui.button
                type="button"
                variant="destructive"
                size="sm"
                wire:click="openDeleteModal"
                class="shrink-0 gap-1.5"
            >
                <x-lucide-trash-2 class="size-4" />
                <span>Hapus Workspace</span>
            </x-ui.button>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS WORKSPACE --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5 bg-destructive/10">
                    <div class="flex items-center gap-2 text-destructive">
                        <x-lucide-alert-triangle class="size-5 shrink-0" />
                        <h3 class="text-base font-bold">Konfirmasi Hapus Workspace</h3>
                    </div>
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <div class="p-3.5 rounded-lg border border-destructive/30 bg-destructive/10 text-destructive space-y-1">
                        <p class="font-bold text-sm">Peringatan: Tindakan ini permanen!</p>
                        <p>
                            Workspace <strong>{{ $workspace->name }}</strong> beserta seluruh data persuratan dan berkas di dalamnya akan dihapus seketika dan tidak dapat dipulihkan.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <x-ui.label for="confirm-name">
                            Ketik nama workspace persis <strong class="text-foreground font-mono bg-muted px-1.5 py-0.5 rounded">{{ $workspace->name }}</strong> untuk mengonfirmasi:
                        </x-ui.label>
                        <x-ui.input
                            id="confirm-name"
                            wire:model.live="confirmWorkspaceName"
                            placeholder="{{ $workspace->name }}"
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
                        :disabled="trim($confirmWorkspaceName) !== $workspace->name"
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
