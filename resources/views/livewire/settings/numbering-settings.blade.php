<div class="max-w-4xl space-y-6">
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title>Konfigurasi Pola Penomoran — {{ $workspace->name }}</x-ui.card-title>
            <x-ui.card-description>
                Tentukan format nomor surat otomatis untuk workspace ini. Penomoran dikunci secara atomik di dalam transaksi database dan mendukung aturan kegiatan HIMATRIS.
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-6 space-y-6">
            {{-- Preset Cepat Pola Penomoran --}}
            <div class="space-y-2">
                <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                    <x-lucide-sparkles class="size-3.5 text-primary" />
                    <span>Pilihan Preset Format Penomoran:</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    @foreach ($presets as $key => $preset)
                        <button
                            type="button"
                            wire:click="applyPreset('{{ $key }}')"
                            @class([
                                'p-3 rounded-lg border text-left transition-all cursor-pointer text-xs space-y-1',
                                'border-primary bg-primary/5 text-foreground ring-1 ring-primary/30' => $numberPattern === $preset['pattern'],
                                'border-border bg-card hover:bg-muted/50 text-muted-foreground hover:text-foreground' => $numberPattern !== $preset['pattern'],
                            ])
                        >
                            <div class="font-medium text-foreground flex items-center justify-between">
                                <span>{{ $preset['name'] }}</span>
                                @if ($numberPattern === $preset['pattern'])
                                    <x-lucide-check class="size-3.5 text-primary shrink-0" />
                                @endif
                            </div>
                            <p class="font-mono text-[11px] text-muted-foreground truncate">{{ $preset['pattern'] }}</p>
                            <p class="text-[10px] text-primary/80 font-mono">Contoh: {{ $preset['example'] }}</p>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Pratinjau Nomor Surat --}}
            <div class="rounded-lg border border-border bg-muted/40 p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-muted-foreground">Pratinjau Hasil Format (Contoh: SPm-i Kegiatan JKBFEST, Urutan 001, Juli 2026)</p>
                    <span class="text-[10px] font-mono text-muted-foreground uppercase">Real-time Preview</span>
                </div>
                @if ($preview)
                    <p class="mt-1.5 font-mono text-lg font-bold tracking-tight text-primary">{{ $preview }}</p>
                @else
                    <p class="mt-1.5 text-xs text-destructive">{{ $previewError }}</p>
                @endif
            </div>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <x-ui.label for="numberPattern">String Pola Nomor <span class="text-destructive">*</span></x-ui.label>
                    <x-ui.input
                        id="numberPattern"
                        wire:model.live.debounce.200ms="numberPattern"
                        class="mt-1.5 font-mono font-medium"
                        placeholder="{seq}/{kategori}{parameter}/PAN-{kode_kegiatan}/{kode}/{bulan_romawi}/{tahun}"
                    />
                    <div class="mt-2 text-xs text-muted-foreground space-y-1">
                        <p class="font-semibold text-foreground">Token dinamis yang didukung:</p>
                        <div class="flex flex-wrap gap-1.5 pt-0.5">
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{seq} / {nomor_urut}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{kategori} / {jenis_surat}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{parameter}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{kode_kegiatan}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{panitia}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{kode}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{bulan_romawi}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{bulan}</span>
                            <span class="font-mono bg-muted px-1.5 py-0.5 rounded text-foreground text-[11px]">{tahun}</span>
                        </div>
                    </div>
                    <x-ui.form-error for="numberPattern" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <x-ui.label for="numberCode">Kode Organisasi ({kode}) <span class="text-destructive">*</span></x-ui.label>
                        <x-ui.input id="numberCode" wire:model.live="numberCode" class="mt-1.5 font-mono" placeholder="HIMATRIS" />
                        <x-ui.form-error for="numberCode" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <x-ui.label for="activityCode">Kode Kegiatan ({kode_kegiatan})</x-ui.label>
                            <label class="inline-flex items-center gap-1 text-[11px] text-muted-foreground cursor-pointer">
                                <input type="checkbox" wire:model.live="isActivity" class="rounded border-border text-primary size-3" />
                                <span>Kepanitiaan</span>
                            </label>
                        </div>
                        <x-ui.input
                            id="activityCode"
                            wire:model.live="activityCode"
                            class="mt-1.5 font-mono uppercase"
                            placeholder="Mis. JKBFEST, OASIS"
                            :disabled="! $isActivity"
                        />
                        <p class="text-[10px] text-muted-foreground mt-0.5">
                            @if ($isActivity)
                                Menghasilkan: <code class="font-mono text-primary font-semibold">PAN-{{ strtoupper(trim($activityCode ?: 'KEGIATAN')) }}</code>
                            @else
                                Non-kepanitiaan (kode kegiatan dinonaktifkan).
                            @endif
                        </p>
                        <x-ui.form-error for="activityCode" />
                    </div>

                    <div>
                        <x-ui.label for="seqPadding">Digit Padding {seq} <span class="text-destructive">*</span></x-ui.label>
                        <x-ui.select id="seqPadding" wire:model.live="seqPadding" class="mt-1.5 font-mono">
                            <option value="1">1 digit (1)</option>
                            <option value="2">2 digit (01)</option>
                            <option value="3">3 digit (001) — Standar</option>
                            <option value="4">4 digit (0001)</option>
                        </x-ui.select>
                        <x-ui.form-error for="seqPadding" />
                    </div>

                    <div>
                        <x-ui.label for="nextSequence">Nomor Urut Berikutnya <span class="text-destructive">*</span></x-ui.label>
                        <x-ui.input
                            id="nextSequence"
                            type="number"
                            min="{{ $minAllowedSequence }}"
                            wire:model.live="nextSequence"
                            class="mt-1.5 font-mono"
                            placeholder="1"
                        />
                        <p class="text-[10px] text-muted-foreground mt-0.5">
                            Batas minimum yang diizinkan: <strong class="text-foreground">{{ $minAllowedSequence }}</strong>
                        </p>
                        <x-ui.form-error for="nextSequence" />
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <x-ui.button type="submit">
                        <x-lucide-save class="size-4" />
                        <span>Simpan Pola Penomoran</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>

    {{-- Panduan Referensi 10 Jenis Surat HIMATRIS --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title class="text-sm font-semibold flex items-center gap-2">
                <x-lucide-book-open class="size-4 text-primary" />
                <span>Referensi 10 Jenis Surat Administrasi HIMATRIS</span>
            </x-ui.card-title>
            <x-ui.card-description>
                Daftar kode jenis surat baku di lingkungan HIMATRIS yang otomatis muncul pada pilihan formulir surat. Kode baru juga dapat diketik secara bebas saat membuat surat.
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="overflow-x-auto border border-border rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/50 border-b border-border text-muted-foreground text-left">
                            <tr>
                                <th class="p-2 text-center w-8">#</th>
                                <th class="p-2">Jenis Surat</th>
                                <th class="p-2 text-right">Kode {kategori}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($letterTypes as $idx => $lt)
                                <tr class="hover:bg-muted/20">
                                    <td class="p-2 text-center font-mono text-muted-foreground">{{ $idx + 1 }}</td>
                                    <td class="p-2 font-medium">{{ $lt['name'] }}</td>
                                    <td class="p-2 text-right">
                                        <code class="font-mono font-bold bg-muted px-1.5 py-0.5 rounded text-primary">{{ $lt['code'] }}</code>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3">
                    <div class="p-3.5 rounded-lg border border-border bg-muted/20 space-y-2">
                        <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                            <x-lucide-tag class="size-3.5 text-primary" />
                            <span>Parameter Lingkup Surat ({parameter}):</span>
                        </h4>
                        <ul class="text-xs space-y-1.5 text-muted-foreground">
                            @foreach ($letterParameters as $lp)
                                <li class="flex items-start gap-2">
                                    <code class="font-mono font-bold bg-muted px-1.5 py-0.5 rounded text-foreground shrink-0">
                                        {{ $lp['code'] !== '' ? $lp['code'] : '(kosong)' }}
                                    </code>
                                    <div>
                                        <p class="font-medium text-foreground">{{ $lp['label'] }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ $lp['description'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="p-3.5 rounded-lg border border-primary/20 bg-primary/5 space-y-1 text-xs text-muted-foreground">
                        <p class="font-semibold text-primary flex items-center gap-1">
                            <x-lucide-info class="size-3.5" />
                            <span>Kustomisasi untuk Kegiatan / Organisasi Lain</span>
                        </p>
                        <p class="text-[11px]">
                            Jika Anda mengikuti kegiatan lain dengan kode dan format berbeda, Anda tetap dapat mengetikkan kode jenis surat kustom langsung di form surat, memasukkan nama kegiatan apapun, atau mengubah pola penomoran di atas secara bebas.
                        </p>
                    </div>
                </div>
            </div>
        </x-ui.card-content>
    </x-ui.card>

    {{-- MODAL KONFIRMASI PERUBAHAN NOMOR URUT --}}
    @if ($showConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <x-lucide-alert-triangle class="size-5 text-amber-500" />
                        <h3 class="text-base font-semibold text-foreground">Konfirmasi Perubahan Nomor Urut</h3>
                    </div>
                    <button type="button" wire:click="$set('showConfirmModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-muted-foreground">
                        Anda akan mengubah nomor urut surat berikutnya untuk workspace <strong>{{ $workspace->name }}</strong>.
                    </p>

                    <div class="grid grid-cols-2 gap-3 p-3 rounded-lg border border-border bg-muted/30 font-mono">
                        <div>
                            <span class="text-[11px] text-muted-foreground block font-sans">Nomor Urut Semula:</span>
                            <span class="text-base font-bold text-muted-foreground">{{ $initialNextSequence }}</span>
                        </div>
                        <div>
                            <span class="text-[11px] text-muted-foreground block font-sans">Nomor Urut Baru:</span>
                            <span class="text-base font-bold text-primary">{{ $nextSequence }}</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg border border-primary/20 bg-primary/5 space-y-1">
                        <span class="text-[11px] text-muted-foreground font-semibold">Estimasi Nomor Surat Berikutnya:</span>
                        <p class="font-mono text-sm font-bold text-primary">
                            {{ $preview ?: '—' }}
                        </p>
                    </div>

                    <div class="p-3 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 space-y-1">
                        <div class="flex items-center gap-1.5 font-semibold text-xs">
                            <x-lucide-info class="size-3.5 shrink-0" />
                            <span>Perhatian</span>
                        </div>
                        <p class="text-[11px] leading-relaxed">
                            Mengubah nomor urut ke angka yang lebih tinggi akan melompati nomor-nomor sebelumnya. Nomor yang terlewati tidak dapat diturunkan kembali ke angka lebih rendah dari nomor surat yang sudah terbit demi mencegah bentrok nomor surat.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-border">
                    <x-ui.button type="button" variant="outline" wire:click="$set('showConfirmModal', false)">
                        Batal
                    </x-ui.button>
                    <x-ui.button type="button" wire:click="confirmSave" class="bg-primary hover:bg-primary/90 text-primary-foreground gap-1.5">
                        <x-lucide-check class="size-4" />
                        <span>Ya, Terapkan Perubahan</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
