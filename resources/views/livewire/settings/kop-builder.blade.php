<div class="space-y-6">
    <div class="pb-2 border-b border-border">
        <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <x-lucide-stamp class="size-5 text-primary" />
            <span>Builder Kop Surat &amp; TTD Default</span>
        </h1>
        <p class="text-sm text-muted-foreground mt-0.5">
            Konfigurasi logo instansi, teks kepala surat (kop), alamat sekretariat, dan susunan penandatangan default untuk workspace <strong class="text-foreground">{{ $workspace->name }}</strong>.
        </p>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Left Pane (7 cols): Form Sections --}}
            <div class="lg:col-span-7 space-y-6">
                {{-- Section 1: Identitas Kop Surat & Logos --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border pb-4">
                        <x-ui.card-title class="flex items-center gap-2 text-base">
                            <x-lucide-building-2 class="size-4 text-primary" />
                            <span>1. Identitas Kepala Surat (Kop) &amp; Logo</span>
                        </x-ui.card-title>
                        <x-ui.card-description>
                            Teks kop akan disisipkan di baris atas dokumen surat resmi.
                        </x-ui.card-description>
                    </x-ui.card-header>

                    <x-ui.card-content class="pt-5 space-y-4">
                        <div>
                            <x-ui.label for="kopHeaderText">Teks Header Kop (1 Baris per Entri)</x-ui.label>
                            <x-ui.textarea
                                id="kopHeaderText"
                                wire:model.live="kopHeaderText"
                                rows="4"
                                class="mt-1.5 font-sans"
                                placeholder="PANITIA PELAKSANA MAKRAB PRODI TRPL&#10;POLITEKNIK NEGERI CILACAP&#10;HIMPUNAN MAHASISWA KOMPUTER DAN BISNIS&#10;POLITEKNIK NEGERI CILACAP"
                            />
                            <p class="text-[11px] text-muted-foreground mt-1">Gunakan baris baru (Enter) untuk memisahkan baris nama instansi/organisasi.</p>
                            <x-ui.form-error for="kopHeaderText" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <x-ui.label for="kopAddress">Alamat &amp; Kontak Sekretariat</x-ui.label>
                                <x-ui.input
                                    id="kopAddress"
                                    wire:model.live="kopAddress"
                                    class="mt-1.5"
                                    placeholder="Jalan Dr. Soetomo No.1, Sidakaya – Cilacap 53212"
                                />
                                <x-ui.form-error for="kopAddress" />
                            </div>
                            <div>
                                <x-ui.label for="defaultCity">Kota Surat Default</x-ui.label>
                                <x-ui.input
                                    id="defaultCity"
                                    wire:model.live="defaultCity"
                                    class="mt-1.5"
                                    placeholder="Cilacap"
                                />
                                <x-ui.form-error for="defaultCity" />
                            </div>
                        </div>

                        <x-ui.separator />

                        {{-- Upload Logos --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-border p-3.5 space-y-2 bg-muted/20">
                                <div class="flex items-center justify-between">
                                    <x-ui.label for="logoLeftUpload" class="text-xs font-semibold">Logo Kiri (PNG/JPG)</x-ui.label>
                                    @if ($workspace->kop_logo_left)
                                        <button type="button" wire:click="removeLogo('left')" class="text-[11px] text-destructive hover:underline cursor-pointer">
                                            Hapus Logo
                                        </button>
                                    @endif
                                </div>
                                <x-ui.input id="logoLeftUpload" type="file" wire:model="logoLeftUpload" accept="image/png,image/jpeg" class="text-xs" />
                                <x-ui.form-error for="logoLeftUpload" />
                            </div>

                            <div class="rounded-lg border border-border p-3.5 space-y-2 bg-muted/20">
                                <div class="flex items-center justify-between">
                                    <x-ui.label for="logoRightUpload" class="text-xs font-semibold">Logo Kanan (PNG/JPG)</x-ui.label>
                                    @if ($workspace->kop_logo_right)
                                        <button type="button" wire:click="removeLogo('right')" class="text-[11px] text-destructive hover:underline cursor-pointer">
                                            Hapus Logo
                                        </button>
                                    @endif
                                </div>
                                <x-ui.input id="logoRightUpload" type="file" wire:model="logoRightUpload" accept="image/png,image/jpeg" class="text-xs" />
                                <x-ui.form-error for="logoRightUpload" />
                            </div>
                        </div>
                    </x-ui.card-content>
                </x-ui.card>

                {{-- Section 2: Pejabat Penandatangan (TTD Default) --}}
                <x-ui.card>
                    <x-ui.card-header class="border-b border-border pb-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <x-ui.card-title class="flex items-center gap-2 text-base">
                                    <x-lucide-signature class="size-4 text-primary" />
                                    <span>2. Pejabat Penandatangan (TTD Default)</span>
                                </x-ui.card-title>
                                <x-ui.card-description>
                                    Daftar penandatangan default saat membuat surat baru. Geser (drag &amp; drop) untuk mengatur posisi.
                                </x-ui.card-description>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <x-ui.button type="button" variant="ghost" size="sm" class="text-xs" wire:click="applyPreset('2_signers')">
                                    Preset 2 TTD
                                </x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" class="text-xs" wire:click="applyPreset('5_signers')">
                                    Preset 5 TTD
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="addSigner" class="gap-1">
                                    <x-lucide-plus class="size-3.5" />
                                    <span>Tambah</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-content class="pt-5 space-y-3">
                        <div
                            x-data="{
                                draggingIndex: null,
                                dragOverIndex: null,
                            }"
                            class="space-y-3"
                        >
                            @foreach ($signers as $idx => $sig)
                                @php
                                    $totalS = count($signers);
                                    $isOddLast = ($totalS % 2 === 1) && ($idx === $totalS - 1);
                                    if ($totalS === 1) {
                                        $posLabel = 'Tunggal (Rata Kanan)';
                                        $badgeColor = 'bg-primary/10 text-primary border-primary/20';
                                    } elseif ($isOddLast) {
                                        $rowNum = (int) floor($idx / 2) + 1;
                                        $posLabel = "Baris {$rowNum} — Rata Tengah (Center)";
                                        $badgeColor = 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
                                    } else {
                                        $rowNum = (int) floor($idx / 2) + 1;
                                        $colName = ($idx % 2 === 0) ? 'Kolom Kiri' : 'Kolom Kanan';
                                        $posLabel = "Baris {$rowNum} — {$colName}";
                                        $badgeColor = 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
                                    }
                                @endphp

                                <div
                                    draggable="true"
                                    @dragstart="draggingIndex = {{ $idx }}; $event.dataTransfer.setData('text/plain', {{ $idx }})"
                                    @dragover.prevent="dragOverIndex = {{ $idx }}"
                                    @dragleave="if (dragOverIndex === {{ $idx }}) dragOverIndex = null"
                                    @drop.prevent="
                                        if (draggingIndex !== null && draggingIndex !== {{ $idx }}) {
                                            $wire.moveSigner(draggingIndex, {{ $idx }});
                                        }
                                        draggingIndex = null;
                                        dragOverIndex = null;
                                    "
                                    :class="{
                                        'opacity-40 border-dashed border-primary': draggingIndex === {{ $idx }},
                                        'ring-2 ring-primary ring-offset-2': dragOverIndex === {{ $idx }} && draggingIndex !== {{ $idx }}
                                    }"
                                    class="rounded-lg border border-border p-3.5 space-y-2.5 bg-card transition-all"
                                    wire:key="ws-signer-{{ $idx }}"
                                >
                                    <div class="flex items-center justify-between pb-2 border-b border-border/60">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground p-1 rounded" title="Tahan dan geser untuk memindahkan urutan">
                                                <x-lucide-grip-vertical class="size-4" />
                                            </button>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badgeColor }}">
                                                #{{ $idx + 1 }} &bull; {{ $posLabel }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="moveSignerUp({{ $idx }})"
                                                @disabled($idx === 0)
                                                class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                                                title="Geser ke Atas"
                                            >
                                                <x-lucide-arrow-up class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="moveSignerDown({{ $idx }})"
                                                @disabled($idx === $totalS - 1)
                                                class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                                                title="Geser ke Bawah"
                                            >
                                                <x-lucide-arrow-down class="size-3.5" />
                                            </button>
                                            @if ($totalS > 1)
                                                <button
                                                    type="button"
                                                    wire:click="removeSigner({{ $idx }})"
                                                    class="ml-2 p-1 rounded text-xs text-destructive hover:bg-destructive/10 transition cursor-pointer"
                                                    title="Hapus Penandatangan"
                                                >
                                                    <x-lucide-trash-2 class="size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <x-ui.label class="text-xs text-muted-foreground">Label Pengesahan (Opsional)</x-ui.label>
                                            <div class="flex items-center gap-1">
                                                <button type="button" wire:click="$set('signers.{{ $idx }}.prefix', '')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-muted-foreground transition cursor-pointer">
                                                    Tanpa Label
                                                </button>
                                                <button type="button" wire:click="$set('signers.{{ $idx }}.prefix', 'Menyetujui,')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-foreground font-semibold transition cursor-pointer">
                                                    + Menyetujui,
                                                </button>
                                                <button type="button" wire:click="$set('signers.{{ $idx }}.prefix', 'Mengetahui,')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-foreground font-semibold transition cursor-pointer">
                                                    + Mengetahui,
                                                </button>
                                            </div>
                                        </div>
                                        <x-ui.input wire:model.live="signers.{{ $idx }}.prefix" placeholder="Misal: Menyetujui, atau Mengetahui," />
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <x-ui.label class="text-xs">Jabatan</x-ui.label>
                                            <x-ui.input wire:model.live="signers.{{ $idx }}.position" class="mt-1" placeholder="Ketua HIMATRIS, / Ketua Pelaksana," />
                                        </div>
                                        <div>
                                            <x-ui.label class="text-xs">Nama Lengkap &amp; Gelar</x-ui.label>
                                            <x-ui.input wire:model.live="signers.{{ $idx }}.name" class="mt-1" placeholder="Dimas Surya Pratama / Lutfi Syafirullah, S.T., M.Kom." />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <x-ui.label class="text-xs">Label ID</x-ui.label>
                                            <x-ui.input wire:model.live="signers.{{ $idx }}.id_label" class="mt-1" placeholder="NIM. / NIP." />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <x-ui.label class="text-xs">Nomor Induk</x-ui.label>
                                            <x-ui.input wire:model.live="signers.{{ $idx }}.id_number" class="mt-1" placeholder="25.01.09.044 / 19841121202421 1 009" />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 flex justify-end">
                            <x-ui.button type="submit" class="gap-1.5">
                                <x-lucide-save class="size-4" />
                                <span>Simpan Konfigurasi Kop &amp; TTD</span>
                            </x-ui.button>
                        </div>
                    </x-ui.card-content>
                </x-ui.card>
            </div>

            {{-- Right Pane (5 cols): Sticky Paper Canvas Preview --}}
            <div class="lg:col-span-5 sticky top-6 space-y-3">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">Pratinjau Kertas A4 (Live)</span>
                    <span class="font-mono text-[11px]">Kop &amp; TTD Otomatis</span>
                </div>

                <div class="rounded-xl border border-border bg-white text-zinc-900 p-5 shadow-md">
                    {{-- Kop Header Preview --}}
                    <div class="grid grid-cols-12 items-center gap-2 border-b-2 border-zinc-900 pb-3">
                        <div class="col-span-2 flex justify-center">
                            @if ($leftDataUri)
                                <img src="{{ $leftDataUri }}" alt="Logo Kiri" class="h-12 w-12 object-contain" />
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded border border-dashed border-zinc-300 text-[9px] text-zinc-400 text-center">
                                    Logo
                                </div>
                            @endif
                        </div>

                        <div class="col-span-8 text-center space-y-0.5">
                            @php
                                $rawLines = preg_split('/\r\n|\r|\n/', $kopHeaderText ?: $workspace->name) ?: [];
                                $lines = array_values(array_filter(array_map('trim', $rawLines), fn ($l) => $l !== ''));
                                if ($lines === []) {
                                    $lines = [$workspace->name];
                                }
                            @endphp
                            @foreach ($lines as $idx => $line)
                                <p class="{{ $idx === 0 ? 'text-[11px] font-bold' : 'text-[10px] font-semibold' }} uppercase tracking-wide leading-tight">
                                    {{ $line }}
                                </p>
                            @endforeach
                            <p class="text-[9px] text-zinc-600 pt-0.5 leading-tight">
                                {{ $kopAddress ?: 'Alamat sekretariat belum diisi' }}
                            </p>
                        </div>

                        <div class="col-span-2 flex justify-center">
                            @if ($rightDataUri)
                                <img src="{{ $rightDataUri }}" alt="Logo Kanan" class="h-12 w-12 object-contain" />
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded border border-dashed border-zinc-300 text-[9px] text-zinc-400 text-center">
                                    Logo
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Mock Body --}}
                    <div class="mt-4 space-y-2 text-[10px] text-zinc-500 font-serif">
                        <div class="flex justify-end text-zinc-700">
                            <span>{{ $defaultCity ?: 'Cilacap' }}, {{ now()->translatedFormat('d F Y') }}</span>
                        </div>
                        <div class="text-zinc-700 space-y-0.5 text-[10px]">
                            <p>Nomor &nbsp;&nbsp;&nbsp;&nbsp;: 001/{{ $workspace->number_code ?? 'ORG' }}/VI/{{ now()->format('Y') }}</p>
                            <p>Lampiran : Satu (1) berkas</p>
                            <p>Hal &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <span class="font-bold underline">Permohonan Izin Tempat</span></p>
                        </div>
                        <div class="h-1.5 w-3/4 rounded bg-zinc-100 mt-2"></div>
                        <div class="h-1.5 w-full rounded bg-zinc-100"></div>
                        <div class="h-1.5 w-5/6 rounded bg-zinc-100"></div>

                        {{-- Signers Preview on Paper --}}
                        @if (count($signers) > 0)
                            @php
                                $previewRows = [];
                                $curr = 0;
                                $totalS = count($signers);
                                if ($totalS === 1) {
                                    $previewRows[] = [$signers[0]];
                                } else {
                                    while ($curr < $totalS) {
                                        if ($curr === $totalS - 1 && ($totalS % 2 === 1)) {
                                            $previewRows[] = [$signers[$curr]];
                                            $curr++;
                                        } else {
                                            $previewRows[] = [$signers[$curr], $signers[$curr + 1]];
                                            $curr += 2;
                                        }
                                    }
                                }
                            @endphp
                            <div class="pt-4 space-y-3 text-center text-zinc-800 text-[9px]">
                                @foreach ($previewRows as $rSigners)
                                    <div class="grid grid-cols-{{ count($rSigners) === 1 ? '1 max-w-[180px] mx-auto' : '2' }} gap-2 items-end">
                                        @foreach ($rSigners as $sig)
                                            <div class="space-y-6">
                                                <div>
                                                    @if (!empty($sig['prefix']))
                                                        <p class="font-bold">{{ $sig['prefix'] }}</p>
                                                    @endif
                                                    <p class="font-semibold">{{ $sig['position'] ?: 'Jabatan' }}</p>
                                                </div>
                                                <div>
                                                    <p class="font-bold underline">{{ $sig['name'] ?: 'Nama Pejabat' }}</p>
                                                    @if (!empty($sig['id_number']))
                                                        <p class="text-[8px] text-zinc-600 font-mono">{{ ($sig['id_label'] ?? 'NIP.') . ' ' . $sig['id_number'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
