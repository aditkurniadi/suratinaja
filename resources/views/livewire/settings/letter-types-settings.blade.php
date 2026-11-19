<div class="max-w-4xl space-y-6">
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <x-ui.card-title>Manajemen Jenis Surat — {{ $workspace->name }}</x-ui.card-title>
                <x-ui.card-description>
                    Kelola daftar kode jenis surat resmi, nama, dan deskripsi fungsinya. Jenis surat ini menjadi pilihan baku saat membuat surat di workspace ini.
                </x-ui.card-description>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button type="button" variant="outline" size="sm" wire:click="resetToDefaults" wire:confirm="Kembalikan daftar jenis surat ke 10 jenis standar HIMATRIS?">
                    <x-lucide-rotate-ccw class="size-3.5" />
                    <span>Reset Default</span>
                </x-ui.button>
                <x-ui.button type="button" size="sm" wire:click="openCreateModal">
                    <x-lucide-plus class="size-4" />
                    <span>Tambah Jenis Surat</span>
                </x-ui.button>
            </div>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            <div class="overflow-x-auto border border-border rounded-lg">
                <table class="w-full text-xs">
                    <thead class="bg-muted/50 border-b border-border text-muted-foreground text-left">
                        <tr>
                            <th class="p-3 text-center w-10 font-mono">#</th>
                            <th class="p-3 w-28">Kode {kategori}</th>
                            <th class="p-3 w-64">Nama Jenis Surat</th>
                            <th class="p-3">Deskripsi &amp; Penggunaan</th>
                            <th class="p-3 text-right w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($letterTypes as $idx => $lt)
                            <tr class="hover:bg-muted/20 transition-colors">
                                <td class="p-3 text-center font-mono text-muted-foreground">{{ $idx + 1 }}</td>
                                <td class="p-3">
                                    <code class="font-mono font-bold bg-primary/10 text-primary px-2 py-0.5 rounded text-xs">
                                        {{ $lt['code'] }}
                                    </code>
                                </td>
                                <td class="p-3 font-semibold text-foreground">
                                    {{ $lt['name'] }}
                                </td>
                                <td class="p-3 text-muted-foreground leading-relaxed">
                                    {{ !empty($lt['description']) ? $lt['description'] : '—' }}
                                </td>
                                <td class="p-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <x-ui.button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            wire:click="openEditModal({{ $idx }})"
                                            aria-label="Edit jenis surat"
                                        >
                                            <x-lucide-pencil class="size-3.5" />
                                        </x-ui.button>
                                        <x-ui.button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-destructive hover:text-destructive"
                                            wire:click="deleteType({{ $idx }})"
                                            wire:confirm="Yakin ingin menghapus jenis surat '{{ $lt['code'] }}'?"
                                            aria-label="Hapus jenis surat"
                                        >
                                            <x-lucide-trash-2 class="size-3.5" />
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-muted-foreground">
                                    Belum ada jenis surat yang terdaftar. Klik "Reset Default" untuk memuat jenis surat standar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card-content>
    </x-ui.card>

    {{-- Modal Tambah / Edit Jenis Surat --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div class="bg-card border border-border rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-semibold text-foreground">
                        {{ $editingIndex !== null ? 'Edit Jenis Surat' : 'Tambah Jenis Surat Baru' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-ui.label for="type-code">Kode Jenis Surat (Singkat, Huruf/Angka) <span class="text-destructive">*</span></x-ui.label>
                        <x-ui.input
                            id="type-code"
                            wire:model="code"
                            class="mt-1.5 font-mono uppercase"
                            placeholder="Mis. DISP, SPm, UND"
                            required
                        />
                        <x-ui.form-error for="code" />
                    </div>

                    <div>
                        <x-ui.label for="type-name">Nama Lengkap Jenis Surat <span class="text-destructive">*</span></x-ui.label>
                        <x-ui.input
                            id="type-name"
                            wire:model="name"
                            class="mt-1.5"
                            placeholder="Mis. Surat Dispensasi Mahasiswa"
                            required
                        />
                        <x-ui.form-error for="name" />
                    </div>

                    <div>
                        <x-ui.label for="type-desc">Deskripsi &amp; Fungsi Surat</x-ui.label>
                        <x-ui.textarea
                            id="type-desc"
                            wire:model="description"
                            rows="3"
                            class="mt-1.5 text-xs"
                            placeholder="Jelaskan tujuan atau kondisi penggunaan jenis surat ini..."
                        />
                        <x-ui.form-error for="description" />
                    </div>

                    <div class="pt-2 flex justify-end gap-2 border-t border-border pt-4">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showModal', false)">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit">
                            <x-lucide-save class="size-4" />
                            <span>{{ $editingIndex !== null ? 'Simpan Perubahan' : 'Tambahkan' }}</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
