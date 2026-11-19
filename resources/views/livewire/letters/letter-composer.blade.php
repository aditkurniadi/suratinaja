<div class="max-w-4xl mx-auto space-y-6">
    {{-- Stepper Progress Bar --}}
    <div class="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-xs">
        <div class="flex items-center justify-between">
            {{-- Step 1 Indicator --}}
            <button
                type="button"
                wire:click="goToStep(1)"
                class="flex items-center gap-2.5 text-left group cursor-pointer focus:outline-hidden"
            >
                <div @class([
                    'flex size-9 min-w-9 min-h-9 aspect-square shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all',
                    'bg-primary text-primary-foreground shadow-sm ring-4 ring-primary/20' => $currentStep === 1,
                    'bg-emerald-600 text-white' => $currentStep > 1,
                    'bg-muted text-muted-foreground group-hover:bg-muted/80' => $currentStep < 1,
                ])>
                    @if ($currentStep > 1)
                        <x-lucide-check class="size-4" />
                    @else
                        1
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold {{ $currentStep === 1 ? 'text-primary' : 'text-foreground' }}">Tahap 1</p>
                    <p class="text-[11px] text-muted-foreground hidden sm:block">Info Dasar &amp; Nomor</p>
                </div>
            </button>

            {{-- Divider 1-2 --}}
            <div @class([
                'flex-1 mx-2 sm:mx-6 h-0.5 transition-colors',
                'bg-emerald-600' => $currentStep > 1,
                'bg-border' => $currentStep <= 1,
            ])></div>

            {{-- Step 2 Indicator --}}
            <button
                type="button"
                wire:click="goToStep(2)"
                class="flex items-center gap-2.5 text-left group cursor-pointer focus:outline-hidden"
            >
                <div @class([
                    'flex size-9 min-w-9 min-h-9 aspect-square shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all',
                    'bg-primary text-primary-foreground shadow-sm ring-4 ring-primary/20' => $currentStep === 2,
                    'bg-emerald-600 text-white' => $currentStep > 2,
                    'bg-muted text-muted-foreground group-hover:bg-muted/80' => $currentStep < 2,
                ])>
                    @if ($currentStep > 2)
                        <x-lucide-check class="size-4" />
                    @else
                        2
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold {{ $currentStep === 2 ? 'text-primary' : 'text-foreground' }}">Tahap 2</p>
                    <p class="text-[11px] text-muted-foreground hidden sm:block">
                        {{ $letterMode === 'template' ? 'Isi Variabel Dokumen' : 'Detail & Link Dokumen' }}
                    </p>
                </div>
            </button>

            {{-- Divider 2-3 --}}
            <div @class([
                'flex-1 mx-2 sm:mx-6 h-0.5 transition-colors',
                'bg-emerald-600' => $currentStep > 2,
                'bg-border' => $currentStep <= 2,
            ])></div>

            {{-- Step 3 Indicator --}}
            <button
                type="button"
                wire:click="goToStep(3)"
                class="flex items-center gap-2.5 text-left group cursor-pointer focus:outline-hidden"
            >
                <div @class([
                    'flex size-9 min-w-9 min-h-9 aspect-square shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all',
                    'bg-primary text-primary-foreground shadow-sm ring-4 ring-primary/20' => $currentStep === 3,
                    'bg-muted text-muted-foreground group-hover:bg-muted/80' => $currentStep < 3,
                ])>
                    3
                </div>
                <div>
                    <p class="text-xs font-semibold {{ $currentStep === 3 ? 'text-primary' : 'text-foreground' }}">Tahap 3</p>
                    <p class="text-[11px] text-muted-foreground hidden sm:block">TTD &amp; Finalisasi</p>
                </div>
            </button>
        </div>
    </div>

    {{-- Main Wizard Card --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <div class="flex items-center justify-between">
                <div>
                    <x-ui.card-title class="flex items-center gap-2">
                        @if ($currentStep === 1)
                            <x-lucide-file-text class="size-5 text-primary" />
                            <span>Tahap 1: Mode Surat, Metadata &amp; Format Penomoran</span>
                        @elseif ($currentStep === 2)
                            <x-lucide-edit-3 class="size-5 text-primary" />
                            <span>Tahap 2: {{ $letterMode === 'template' ? 'Pengisian Variabel Dokumen (.docx)' : 'Data Penerima & Link Google Drive' }}</span>
                        @else
                            <x-lucide-signature class="size-5 text-primary" />
                            <span>Tahap 3: Konfigurasi Penandatangan &amp; Finalisasi Surat</span>
                        @endif
                    </x-ui.card-title>
                    <x-ui.card-description>
                        @if ($currentStep === 1)
                            Tentukan apakah surat dibuat menggunakan template Word atau registrasi nomor dokumen eksternal.
                        @elseif ($currentStep === 2)
                            Lengkapi seluruh data dokumen sesuai kebutuhan persuratan.
                        @else
                            Atur susunan penandatangan resmi dan lakukan verifikasi data sebelum menerbitkan surat.
                        @endif
                    </x-ui.card-description>
                </div>

                @if ($liveNumberPreview)
                    <div class="hidden md:flex flex-col items-end">
                        <span class="text-[11px] text-muted-foreground uppercase font-semibold">Estimasi Nomor</span>
                        <span class="font-mono text-xs font-bold text-primary bg-primary/10 px-2.5 py-1 rounded-md border border-primary/20">
                            {{ $liveNumberPreview }}
                        </span>
                    </div>
                @endif
            </div>
        </x-ui.card-header>

        <x-ui.card-content class="pt-6">
            @if ($errorMessage)
                <x-ui.alert variant="destructive" title="Terjadi Kesalahan" class="mb-5">
                    {{ $errorMessage }}
                </x-ui.alert>
            @endif

            {{-- ======================================================== --}}
            {{-- STEP 1: INFO DASAR & PENOMORAN --}}
            {{-- ======================================================== --}}
            @if ($currentStep === 1)
                <div class="space-y-6">
                    {{-- Mode Selection Toggle --}}
                    @if (! $letterId)
                        <div class="space-y-2">
                            <x-ui.label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Tipe / Mode Pembuatan Surat
                            </x-ui.label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    wire:click="setLetterMode('template')"
                                    @class([
                                        'rounded-xl border p-4 text-left transition-all cursor-pointer flex items-start gap-3.5',
                                        'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-xs' => $letterMode === 'template',
                                        'border-border bg-card hover:bg-muted/30' => $letterMode !== 'template',
                                    ])
                                >
                                    <div @class([
                                        'size-10 rounded-lg flex items-center justify-center shrink-0',
                                        'bg-primary text-primary-foreground' => $letterMode === 'template',
                                        'bg-muted text-muted-foreground' => $letterMode !== 'template',
                                    ])>
                                        <x-lucide-file-code-2 class="size-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">Gunakan Template Word (.docx)</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Sistem akan menginjeksi variabel, kop, dan TTD otomatis ke dalam file Word lalu menghasilkan file .docx dan preview PDF.
                                        </p>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    wire:click="setLetterMode('manual_number')"
                                    @class([
                                        'rounded-xl border p-4 text-left transition-all cursor-pointer flex items-start gap-3.5',
                                        'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-xs' => $letterMode === 'manual_number',
                                        'border-border bg-card hover:bg-muted/30' => $letterMode !== 'manual_number',
                                    ])
                                >
                                    <div @class([
                                        'size-10 rounded-lg flex items-center justify-center shrink-0',
                                        'bg-primary text-primary-foreground' => $letterMode === 'manual_number',
                                        'bg-muted text-muted-foreground' => $letterMode !== 'manual_number',
                                    ])>
                                        <x-lucide-external-link class="size-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">Ambil Nomor Saja / Dokumen Eksternal</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Untuk surat yang dibuat di luar sistem (Google Docs / Canva / Manual). Dialokasikan nomor urut resmi dan link Google Drive.
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Template Selection (If Template Mode) --}}
                    @if ($letterMode === 'template')
                        <div>
                            <div class="flex items-center justify-between">
                                <x-ui.label for="templateId">Template Dokumen (.docx) <span class="text-destructive">*</span></x-ui.label>
                                @if ($selectedTemplate)
                                    <div class="flex items-center gap-1.5 text-xs">
                                        @if ($selectedTemplate->has_embedded_kop)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                                Kop Mandiri (Word)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20">
                                                Kop Resmi Workspace
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <x-ui.select id="templateId" wire:model.live="templateId" class="mt-1.5" :disabled="(bool) $letterId">
                                <option value="">— Pilih Template Dokumen —</option>
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}">
                                        [{{ $tpl->scope->label() }}] {{ $tpl->name }} {{ $tpl->has_embedded_kop ? '— (Kop Mandiri)' : '— (Kop Workspace)' }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.form-error for="templateId" />
                        </div>
                    @endif

                    {{-- Metadata Surat (Perihal, Tanggal, Kota) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <x-ui.label for="perihal">Hal / Perihal Surat (${perihal}) <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="perihal" wire:model.live.debounce.300ms="perihal" class="mt-1.5" placeholder="Mis. Permohonan Izin Tempat &amp; Sound System" required />
                            <x-ui.form-error for="perihal" />
                        </div>

                        <div>
                            <x-ui.label for="letterDate">Tanggal Surat <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="letterDate" type="date" wire:model.live="letterDate" class="mt-1.5" required />
                            <x-ui.form-error for="letterDate" />
                        </div>

                        <div>
                            <x-ui.label for="city">Kota Surat</x-ui.label>
                            <x-ui.input id="city" wire:model.live.debounce.300ms="city" class="mt-1.5" placeholder="Cilacap" />
                            <x-ui.form-error for="city" />
                        </div>
                    </div>

                    {{-- Format & Penomoran Surat Workspace --}}
                    <div class="rounded-xl border border-border bg-muted/20 p-4 sm:p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-border">
                            <div class="flex items-center gap-2">
                                <x-lucide-hash class="size-4 text-primary" />
                                <span class="text-xs font-semibold text-foreground uppercase tracking-wider">Format Penomoran Surat</span>
                            </div>
                            @if ($liveNumberPreview)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-primary/10 border border-primary/20 text-xs font-mono font-semibold text-primary">
                                    <span>Pratinjau Nomor:</span>
                                    <span>{{ $liveNumberPreview }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <x-ui.label for="kategori" class="text-xs">Jenis Surat {kategori}</x-ui.label>
                                <x-ui.select id="kategori" wire:model.live="kategori" class="mt-1 text-xs">
                                    @foreach ($letterTypes as $lt)
                                        <option value="{{ $lt['code'] }}">[{{ $lt['code'] }}] {{ $lt['name'] }}</option>
                                    @endforeach
                                    <option value="custom">— Kustom (Ketik Kode Baru) —</option>
                                </x-ui.select>
                                @if ($kategori === 'custom')
                                    <x-ui.input
                                        wire:model.live.debounce.300ms="customKategori"
                                        class="mt-1.5 text-xs font-mono uppercase"
                                        placeholder="Ketik kode (misal: UND, DISP)..."
                                    />
                                @endif
                            </div>

                            <div>
                                <x-ui.label for="parameter" class="text-xs">Lingkup / Parameter {parameter}</x-ui.label>
                                <x-ui.select id="parameter" wire:model.live="parameter" class="mt-1 text-xs">
                                    @foreach ($letterParameters as $lp)
                                        <option value="{{ $lp['code'] }}">
                                            {{ $lp['code'] !== '' ? $lp['code'] . ' — ' . $lp['label'] : $lp['label'] }}
                                        </option>
                                    @endforeach
                                    <option value="custom">— Kustom —</option>
                                </x-ui.select>
                                @if ($parameter === 'custom')
                                    <x-ui.input
                                        wire:model.live.debounce.300ms="customParameter"
                                        class="mt-1.5 text-xs font-mono"
                                        placeholder="Ketik parameter (misal: -int, /Eks)..."
                                    />
                                @endif
                            </div>

                            <div>
                                <x-ui.label class="text-xs">Kode Kegiatan {kode_kegiatan}</x-ui.label>
                                <div class="mt-1 px-3 py-2 rounded-md border border-border bg-background text-xs font-mono flex items-center justify-between">
                                    <span class="text-primary font-semibold">
                                        {{ $this->getEffectiveKodeKegiatan() ?: '— (Bukan Kegiatan)' }}
                                    </span>
                                    <a href="{{ route('settings.numbering') }}" class="text-[10px] text-muted-foreground hover:text-foreground underline">
                                        Ubah di Setting
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Next Step Action --}}
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                        <x-ui.button variant="outline" :href="route('letters.index')">Batal</x-ui.button>
                        <x-ui.button type="button" wire:click="nextStep" class="gap-1.5">
                            <span>Lanjut ke Tahap 2</span>
                            <x-lucide-arrow-right class="size-4" />
                        </x-ui.button>
                    </div>
                </div>
            @endif

            {{-- ======================================================== --}}
            {{-- STEP 2: ISI VARIABEL DOKUMEN / DATA SURAT --}}
            {{-- ======================================================== --}}
            @if ($currentStep === 2)
                <div class="space-y-6">
                    @if ($letterMode === 'manual_number')
                        {{-- Mode Dokumen Eksternal / Ambil Nomor Saja --}}
                        <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-border">
                                <x-lucide-file-input class="size-4 text-primary" />
                                <span class="text-xs font-semibold text-foreground uppercase tracking-wider">
                                    Detail Dokumen Eksternal &amp; Penerima
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-ui.label for="recipientName">Penerima / Tujuan Surat <span class="text-destructive">*</span></x-ui.label>
                                    <x-ui.input
                                        id="recipientName"
                                        wire:model.live.debounce.300ms="recipientName"
                                        class="mt-1.5"
                                        placeholder="Mis. Direktur Politeknik Negeri Cilacap / Dr. Ir. ..."
                                        required
                                    />
                                    <x-ui.form-error for="recipientName" />
                                </div>

                                <div>
                                    <x-ui.label for="recipientAgency">Instansi / Tempat Tujuan (Opsional)</x-ui.label>
                                    <x-ui.input
                                        id="recipientAgency"
                                        wire:model.live.debounce.300ms="recipientAgency"
                                        class="mt-1.5"
                                        placeholder="Mis. Politeknik Negeri Cilacap / Dinas Pendidikan"
                                    />
                                    <x-ui.form-error for="recipientAgency" />
                                </div>

                                <div class="sm:col-span-2">
                                    <x-ui.label for="linkDrive">Link Berkas Google Drive / Cloud (Opsional)</x-ui.label>
                                    <div class="mt-1.5 relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                                            <x-lucide-link class="size-4" />
                                        </div>
                                        <x-ui.input
                                            id="linkDrive"
                                            wire:model.live.debounce.300ms="linkDrive"
                                            placeholder="https://drive.google.com/file/d/..."
                                            class="!pl-9"
                                        />
                                    </div>
                                    <p class="text-[11px] text-muted-foreground mt-1">
                                        Lampirkan link Google Drive berkas PDF/Word surat agar mudah diakses oleh seluruh anggota workspace.
                                    </p>
                                    <x-ui.form-error for="linkDrive" />
                                </div>

                                <div class="sm:col-span-2">
                                    <x-ui.label for="manualNotes">Catatan Tambahan (Opsional)</x-ui.label>
                                    <x-ui.textarea
                                        id="manualNotes"
                                        wire:model.live.debounce.300ms="manualNotes"
                                        rows="2"
                                        class="mt-1.5 text-xs"
                                        placeholder="Keterangan arsip, keperluan khusus, dll."
                                    />
                                    <x-ui.form-error for="manualNotes" />
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Mode Template Word --}}
                        <div class="space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <div class="flex items-center gap-2">
                                    <x-lucide-list-todo class="size-4 text-primary" />
                                    <span class="text-xs font-semibold text-foreground uppercase tracking-wider">
                                        Variabel Dokumen Word ({{ count($editableFields) }} Field)
                                    </span>
                                </div>
                                @if ($selectedTemplate)
                                    <span class="text-xs text-muted-foreground font-mono">
                                        Template: {{ $selectedTemplate->name }}
                                    </span>
                                @endif
                            </div>

                            @if (empty($editableFields))
                                <div class="rounded-lg border border-border p-6 text-center text-muted-foreground space-y-1">
                                    <x-lucide-check-circle-2 class="size-6 mx-auto text-primary" />
                                    <p class="text-sm font-medium text-foreground">Tidak ada variabel khusus yang perlu diisi</p>
                                    <p class="text-xs">Template ini hanya menggunakan nomor surat, perihal, dan tanggal yang sudah diisi di Tahap 1.</p>
                                </div>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach ($editableFields as $field)
                                        @php
                                            $fname = $field['name'];
                                            $ftype = $field['type'] ?? 'text';
                                        @endphp

                                        @if ($ftype === 'repeater')
                                            {{-- Repeater: full width --}}
                                            @php
                                                $repCols = $field['columns'] ?? [];
                                                $colCount = max(1, count($repCols));
                                            @endphp
                                            <div class="sm:col-span-2 rounded-xl border border-border p-4 space-y-3 bg-muted/20">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium">{{ $field['label'] }}</p>
                                                        <p class="text-xs text-muted-foreground font-mono">
                                                            Tabel dinamis (${{ '{' . $fname . '.*' . '}' }})
                                                        </p>
                                                    </div>
                                                    <x-ui.button type="button" variant="outline" size="sm" wire:click="addRepeaterRow('{{ $fname }}')">
                                                        <x-lucide-plus class="size-3.5" />
                                                        <span>Tambah Baris</span>
                                                    </x-ui.button>
                                                </div>

                                                @foreach ($values[$fname] ?? [] as $rowIdx => $rowVals)
                                                    <div
                                                        class="grid gap-2.5 items-end rounded-lg border border-border/60 bg-background p-2.5"
                                                        style="grid-template-columns: repeat({{ $colCount }}, minmax(0, 1fr)) auto;"
                                                        wire:key="rep-{{ $fname }}-{{ $rowIdx }}"
                                                    >
                                                        @foreach ($repCols as $col)
                                                            <div class="min-w-0">
                                                                <x-ui.label class="text-xs truncate block">{{ $col['label'] }}</x-ui.label>
                                                                <x-ui.input
                                                                    wire:model="values.{{ $fname }}.{{ $rowIdx }}.{{ $col['name'] }}"
                                                                    class="mt-1"
                                                                    placeholder="{{ in_array(strtolower($col['name']), ['no', 'nomor'], true) ? ($rowIdx + 1) . '.' : $col['label'] }}"
                                                                />
                                                            </div>
                                                        @endforeach
                                                        <div class="flex justify-end pb-0.5">
                                                            <x-ui.button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                wire:click="removeRepeaterRow('{{ $fname }}', {{ $rowIdx }})"
                                                                aria-label="Hapus baris"
                                                            >
                                                                <x-lucide-trash-2 class="size-4 text-destructive" />
                                                            </x-ui.button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($ftype === 'date')
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <x-ui.label for="var-{{ $fname }}">{{ $field['label'] }}</x-ui.label>
                                                    <code class="font-mono text-[11px] text-muted-foreground">${{ '{' . $fname . '}' }}</code>
                                                </div>
                                                <x-ui.input
                                                    id="var-{{ $fname }}"
                                                    type="date"
                                                    wire:model="values.{{ $fname }}"
                                                    class="mt-1.5"
                                                    required
                                                />
                                            </div>
                                        @else
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <x-ui.label for="var-{{ $fname }}">{{ $field['label'] }}</x-ui.label>
                                                    <code class="font-mono text-[11px] text-muted-foreground">${{ '{' . $fname . '}' }}</code>
                                                </div>
                                                <x-ui.input
                                                    id="var-{{ $fname }}"
                                                    wire:model="values.{{ $fname }}"
                                                    class="mt-1.5"
                                                    placeholder="Masukkan {{ strtolower($field['label']) }}..."
                                                    required
                                                />
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Navigation Buttons --}}
                    <div class="flex items-center justify-between pt-4 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="prevStep" class="gap-1.5">
                            <x-lucide-arrow-left class="size-4" />
                            <span>Kembali ke Tahap 1</span>
                        </x-ui.button>
                        <x-ui.button type="button" wire:click="nextStep" class="gap-1.5">
                            <span>Lanjut ke Tahap 3 (TTD &amp; Simpan)</span>
                            <x-lucide-arrow-right class="size-4" />
                        </x-ui.button>
                    </div>
                </div>
            @endif

            {{-- ======================================================== --}}
            {{-- STEP 3: TTD & FINALISASI --}}
            {{-- ======================================================== --}}
            @if ($currentStep === 3)
                <div class="space-y-6">
                    {{-- Ringkasan Surat --}}
                    <div class="rounded-xl border border-border bg-muted/20 p-4 sm:p-5 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <span class="text-xs font-semibold text-foreground uppercase tracking-wider flex items-center gap-2">
                                <x-lucide-file-check class="size-4 text-primary" />
                                <span>Ringkasan Akhir Surat</span>
                            </span>
                            @if ($liveNumberPreview)
                                <span class="font-mono text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20">
                                    {{ $liveNumberPreview }}
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="text-muted-foreground block">Hal / Perihal:</span>
                                <span class="font-semibold text-foreground">{{ $perihal ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block">Tanggal &amp; Kota:</span>
                                <span class="font-semibold text-foreground">{{ \Carbon\Carbon::parse($letterDate)->translatedFormat('d F Y') }} ({{ $city ?: 'Cilacap' }})</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block">Mode Dokumen:</span>
                                <span class="font-medium text-foreground">
                                    {{ $letterMode === 'template' ? ($selectedTemplate?->name ?? 'Template Word') : 'Ambil Nomor Saja (Dokumen Eksternal)' }}
                                </span>
                            </div>
                            @if ($letterMode === 'manual_number' && $linkDrive)
                                <div>
                                    <span class="text-muted-foreground block">Link Google Drive:</span>
                                    <a href="{{ $linkDrive }}" target="_blank" class="text-primary hover:underline truncate block">
                                        {{ $linkDrive }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Penandatangan Surat (TTD) --}}
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-border">
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">Penandatangan Surat Resmi (TTD Dinamis)</h3>
                                <p class="text-xs text-muted-foreground">Tersusun otomatis 2 kolom atau posisi tengah. Geser untuk mengatur urutan.</p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <x-ui.button type="button" variant="ghost" size="sm" class="text-xs" wire:click="applyPreset('2_signers')">
                                    Preset 2 TTD
                                </x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" class="text-xs" wire:click="applyPreset('5_signers')">
                                    Preset 5 TTD
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="addSigner">
                                    <x-lucide-plus class="size-3.5" />
                                    <span>Tambah TTD</span>
                                </x-ui.button>
                            </div>
                        </div>

                        <div
                            x-data="{
                                draggingIndex: null,
                                dragOverIndex: null,
                            }"
                            class="space-y-3"
                        >
                            @foreach ($signers as $sIdx => $sig)
                                @php
                                    $totalS = count($signers);
                                    $isOddLast = ($totalS % 2 === 1) && ($sIdx === $totalS - 1);
                                    if ($totalS === 1) {
                                        $posLabel = 'Tunggal (Rata Kanan)';
                                        $badgeColor = 'bg-primary/10 text-primary border-primary/20';
                                    } elseif ($isOddLast) {
                                        $rowNum = (int) floor($sIdx / 2) + 1;
                                        $posLabel = "Baris {$rowNum} — Rata Tengah (Center)";
                                        $badgeColor = 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
                                    } else {
                                        $rowNum = (int) floor($sIdx / 2) + 1;
                                        $colName = ($sIdx % 2 === 0) ? 'Kolom Kiri' : 'Kolom Kanan';
                                        $posLabel = "Baris {$rowNum} — {$colName}";
                                        $badgeColor = 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
                                    }
                                @endphp

                                <div
                                    draggable="true"
                                    @dragstart="draggingIndex = {{ $sIdx }}; $event.dataTransfer.setData('text/plain', {{ $sIdx }})"
                                    @dragover.prevent="dragOverIndex = {{ $sIdx }}"
                                    @dragleave="if (dragOverIndex === {{ $sIdx }}) dragOverIndex = null"
                                    @drop.prevent="
                                        if (draggingIndex !== null && draggingIndex !== {{ $sIdx }}) {
                                            $wire.moveSigner(draggingIndex, {{ $sIdx }});
                                        }
                                        draggingIndex = null;
                                        dragOverIndex = null;
                                    "
                                    :class="{
                                        'opacity-40 border-dashed border-primary': draggingIndex === {{ $sIdx }},
                                        'ring-2 ring-primary ring-offset-2': dragOverIndex === {{ $sIdx }} && draggingIndex !== {{ $sIdx }}
                                    }"
                                    class="rounded-lg border border-border p-4 space-y-3 bg-card transition-all"
                                    wire:key="composer-signer-{{ $sIdx }}"
                                >
                                    <div class="flex items-center justify-between pb-2 border-b border-border/60">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="cursor-grab active:cursor-grabbing text-muted-foreground hover:text-foreground p-1 rounded" title="Tahan dan geser untuk memindahkan urutan">
                                                <x-lucide-grip-vertical class="size-4" />
                                            </button>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badgeColor }}">
                                                #{{ $sIdx + 1 }} &bull; {{ $posLabel }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="moveSignerUp({{ $sIdx }})"
                                                @disabled($sIdx === 0)
                                                class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                                                title="Geser ke Atas"
                                            >
                                                <x-lucide-arrow-up class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="moveSignerDown({{ $sIdx }})"
                                                @disabled($sIdx === $totalS - 1)
                                                class="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer"
                                                title="Geser ke Bawah"
                                            >
                                                <x-lucide-arrow-down class="size-3.5" />
                                            </button>
                                            @if ($totalS > 1)
                                                <button
                                                    type="button"
                                                    wire:click="removeSigner({{ $sIdx }})"
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
                                            <x-ui.label class="text-xs text-muted-foreground">Label Pengesahan di Atas Jabatan (Opsional)</x-ui.label>
                                            <div class="flex items-center gap-1">
                                                <button type="button" wire:click="$set('signers.{{ $sIdx }}.prefix', '')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-muted-foreground transition cursor-pointer">
                                                    Tanpa Label
                                                </button>
                                                <button type="button" wire:click="$set('signers.{{ $sIdx }}.prefix', 'Menyetujui,')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-foreground font-semibold transition cursor-pointer">
                                                    + Menyetujui,
                                                </button>
                                                <button type="button" wire:click="$set('signers.{{ $sIdx }}.prefix', 'Mengetahui,')" class="text-[11px] px-2 py-0.5 rounded border border-border bg-background hover:bg-muted text-foreground font-semibold transition cursor-pointer">
                                                    + Mengetahui,
                                                </button>
                                            </div>
                                        </div>
                                        <x-ui.input wire:model="signers.{{ $sIdx }}.prefix" placeholder="Misal: Menyetujui, atau Mengetahui," />
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <x-ui.label class="text-xs">Jabatan Penandatangan</x-ui.label>
                                            <x-ui.input wire:model="signers.{{ $sIdx }}.position" class="mt-1" placeholder="Ketua HIMATRIS, / Ketua Pelaksana," />
                                        </div>
                                        <div>
                                            <x-ui.label class="text-xs">Nama Lengkap &amp; Gelar</x-ui.label>
                                            <x-ui.input wire:model="signers.{{ $sIdx }}.name" class="mt-1" placeholder="Dimas Surya Pratama / Lutfi Syafirullah, S.T., M.Kom." />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <x-ui.label class="text-xs">Label ID</x-ui.label>
                                            <x-ui.input wire:model="signers.{{ $sIdx }}.id_label" class="mt-1" placeholder="NIM. / NIP." />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <x-ui.label class="text-xs">Nomor Induk</x-ui.label>
                                            <x-ui.input wire:model="signers.{{ $sIdx }}.id_number" class="mt-1" placeholder="25.01.09.044 / 19841121202421 1 009" />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Submit & Final Action --}}
                    <div class="flex items-center justify-between pt-4 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="prevStep" class="gap-1.5">
                            <x-lucide-arrow-left class="size-4" />
                            <span>Kembali ke Tahap 2</span>
                        </x-ui.button>
                        <x-ui.button type="button" wire:click="save" class="gap-1.5">
                            <x-lucide-sparkles class="size-4" />
                            <span>{{ $letterId ? 'Simpan Perubahan Draft' : 'Terbitkan Surat & Nomor Resmi' }}</span>
                        </x-ui.button>
                    </div>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>
</div>
