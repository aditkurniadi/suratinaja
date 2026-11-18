<div>
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title>Builder Kop Surat — {{ $workspace->name }}</x-ui.card-title>
            <x-ui.card-description>
                Atur logo kiri, logo kanan, teks instansi, dan alamat. Perubahan berlaku untuk surat yang digenerate berikutnya (tidak mengubah surat lama secara retroaktif).
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {{-- Left: Paper canvas preview (always white paper surface per DESIGN.md §3) --}}
                <div class="lg:col-span-7 space-y-2">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Pratinjau Kanvas Kertas A4 (Halaman Pertama)</span>
                        <span class="font-mono">Rasio logo: object-contain</span>
                    </div>

                    <div class="rounded-lg border border-border bg-white text-zinc-900 p-6 shadow-xs">
                        <div class="grid grid-cols-12 items-center gap-3 border-b-4 border-double border-zinc-900 pb-4">
                            <div class="col-span-2 flex justify-center">
                                @if ($leftDataUri)
                                    <img src="{{ $leftDataUri }}" alt="Logo Kiri" class="h-16 w-16 object-contain" />
                                @else
                                    <div class="flex h-14 w-14 items-center justify-center rounded border border-dashed border-zinc-300 text-[10px] text-zinc-400 text-center">
                                        Logo Kiri
                                    </div>
                                @endif
                            </div>

                            <div class="col-span-8 text-center space-y-0.5">
                                @php
                                    $lines = preg_split('/\r\n|\r|\n/', $kopHeaderText ?: $workspace->name);
                                @endphp
                                <p class="text-sm font-bold uppercase tracking-wide">
                                    {{ $lines[0] ?? $workspace->name }}
                                </p>
                                @if (count($lines) > 1)
                                    <p class="text-xs font-semibold uppercase">
                                        {{ implode(' — ', array_slice($lines, 1)) }}
                                    </p>
                                @endif
                                <p class="text-[11px] text-zinc-600">
                                    {{ $kopAddress ?: 'Alamat sekretariat organisasi belum diisi' }}
                                </p>
                            </div>

                            <div class="col-span-2 flex justify-center">
                                @if ($rightDataUri)
                                    <img src="{{ $rightDataUri }}" alt="Logo Kanan" class="h-16 w-16 object-contain" />
                                @else
                                    <div class="flex h-14 w-14 items-center justify-center rounded border border-dashed border-zinc-300 text-[10px] text-zinc-400 text-center">
                                        Logo Kanan
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 space-y-2 text-xs text-zinc-400 font-serif">
                            <div class="flex justify-between">
                                <span>Nomor: 001/{{ $workspace->number_code ?? 'ORG' }}/VI/{{ now()->format('Y') }}</span>
                                <span>{{ now()->translatedFormat('d F Y') }}</span>
                            </div>
                            <p>Perihal: Contoh Tata Letak Surat</p>
                            <div class="h-2 w-2/3 rounded bg-zinc-100 mt-3"></div>
                            <div class="h-2 w-full rounded bg-zinc-100"></div>
                            <div class="h-2 w-5/6 rounded bg-zinc-100"></div>
                        </div>
                    </div>
                </div>

                {{-- Right: Configuration form --}}
                <form wire:submit="save" class="lg:col-span-5 space-y-4">
                    <div>
                        <x-ui.label for="kopHeaderText">Teks Header Kop (Baris 1 = Utama, Baris 2 = Sub-instansi)</x-ui.label>
                        <x-ui.textarea
                            id="kopHeaderText"
                            wire:model.live="kopHeaderText"
                            rows="3"
                            class="mt-1.5"
                            placeholder="HIMPUNAN MAHASISWA TEKNIK INFORMATIKA&#10;UNIVERSITAS CONTOH INDONESIA"
                        />
                        <x-ui.form-error for="kopHeaderText" />
                    </div>

                    <div>
                        <x-ui.label for="kopAddress">Alamat &amp; Kontak Sekretariat</x-ui.label>
                        <x-ui.input
                            id="kopAddress"
                            wire:model.live="kopAddress"
                            class="mt-1.5"
                            placeholder="Gedung PKM Lt. 2, Jl. Kampus Raya No. 1 — Email: himatris@kampus.ac.id"
                        />
                        <x-ui.form-error for="kopAddress" />
                    </div>

                    <x-ui.separator />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <x-ui.label for="logoLeftUpload">Logo Kiri (PNG/JPG)</x-ui.label>
                            <x-ui.input id="logoLeftUpload" type="file" wire:model="logoLeftUpload" accept="image/png,image/jpeg" />
                            <x-ui.form-error for="logoLeftUpload" />
                            @if ($workspace->kop_logo_left)
                                <button type="button" wire:click="removeLogo('left')" class="text-xs text-destructive hover:underline">
                                    Hapus Logo Kiri
                                </button>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <x-ui.label for="logoRightUpload">Logo Kanan (PNG/JPG)</x-ui.label>
                            <x-ui.input id="logoRightUpload" type="file" wire:model="logoRightUpload" accept="image/png,image/jpeg" />
                            <x-ui.form-error for="logoRightUpload" />
                            @if ($workspace->kop_logo_right)
                                <button type="button" wire:click="removeLogo('right')" class="text-xs text-destructive hover:underline">
                                    Hapus Logo Kanan
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <x-ui.button type="submit">
                            <x-lucide-save class="size-4" />
                            <span>Simpan Kop Surat</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.card-content>
    </x-ui.card>
</div>
