<div class="space-y-6">
    {{-- Header Dashboard Bulk --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <x-lucide-layers class="size-5 text-primary" />
                <span>Bulk Generation (Mailing)</span>
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola antrean pencetakan massal surat ke dalam satu dokumen .docx multi-halaman dengan nomor urut resmi otomatis.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button type="button" wire:click="openBulkModal" class="gap-1.5 shadow-sm">
                <x-lucide-plus class="size-4" />
                <span>Buat Bulk Generation Baru</span>
            </x-ui.button>
        </div>
    </div>

    @if ($errorMessage && ! $showBulkModal)
        <x-ui.alert variant="destructive" title="Bulk Generation Gagal">
            {{ $errorMessage }}
        </x-ui.alert>
    @endif

    {{-- Bulk Job Queue History with Live Polling (Full Width Dashboard) --}}
    <x-ui.card wire:poll.3s>
        <x-ui.card-header class="border-b border-border pb-4 flex flex-row items-center justify-between">
            <div>
                <x-ui.card-title class="flex items-center gap-2">
                    <x-lucide-history class="size-4 text-primary" />
                    <span>Riwayat &amp; Status Job Bulk Generation</span>
                </x-ui.card-title>
                <x-ui.card-description>
                    Pantau progres pemrosesan antrean dan unduh file .docx utuh multi-halaman setelah selesai.
                </x-ui.card-description>
            </div>
            <span class="text-xs text-muted-foreground flex items-center gap-1 font-mono">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Auto-refresh</span>
            </span>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            @if ($jobs->isEmpty())
                <div class="py-12 text-center space-y-3">
                    <div class="mx-auto size-12 rounded-full bg-muted/50 flex items-center justify-center text-muted-foreground">
                        <x-lucide-layers class="size-6" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">Belum ada riwayat job bulk generation</p>
                        <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                            Mulai buat puluhan surat undangan atau sertifikat sekaligus dengan satu kali klik.
                        </p>
                    </div>
                    <x-ui.button type="button" wire:click="openBulkModal" variant="outline" size="sm" class="mt-2 gap-1.5">
                        <x-lucide-plus class="size-3.5" />
                        <span>Mulai Bulk Generate</span>
                    </x-ui.button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-semibold text-muted-foreground">
                                <th class="pb-3 pr-4 font-mono">Job ID</th>
                                <th class="pb-3 pr-4">Template</th>
                                <th class="pb-3 pr-4">Perihal</th>
                                <th class="pb-3 pr-4">Jumlah Penerima</th>
                                <th class="pb-3 pr-4">Status</th>
                                <th class="pb-3 pr-4">Waktu Dibuat</th>
                                <th class="pb-3 pr-4">Selesai</th>
                                <th class="pb-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($jobs as $job)
                                <tr wire:key="job-{{ $job->id }}" class="hover:bg-muted/30 transition-colors">
                                    <td class="py-3 pr-4 font-mono text-xs text-muted-foreground">#{{ $job->id }}</td>
                                    <td class="py-3 pr-4 font-medium text-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <x-lucide-file-text class="size-3.5 text-primary shrink-0" />
                                            <span>{{ $job->template?->name ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-muted-foreground max-w-[200px] truncate">
                                        {{ $job->payload['perihal'] ?? '—' }}
                                    </td>
                                    <td class="py-3 pr-4 font-mono text-xs">
                                        <span class="font-semibold text-foreground">{{ $job->processed_count }}</span>
                                        <span class="text-muted-foreground">/ {{ $job->recipient_count }}</span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        @if ($job->status === 'queued')
                                            <x-ui.badge variant="secondary" class="gap-1 text-[11px]">
                                                <x-lucide-clock class="size-3" />
                                                <span>Antrean</span>
                                            </x-ui.badge>
                                        @elseif ($job->status === 'processing')
                                            <x-ui.badge variant="outline" class="gap-1 text-primary border-primary/30 bg-primary/10 text-[11px]">
                                                <x-lucide-loader-2 class="size-3 animate-spin" />
                                                <span>Memproses...</span>
                                            </x-ui.badge>
                                        @elseif ($job->status === 'completed')
                                            <x-ui.badge variant="default" class="gap-1 bg-emerald-600 hover:bg-emerald-600 text-white text-[11px]">
                                                <x-lucide-check-circle-2 class="size-3" />
                                                <span>Selesai</span>
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge variant="destructive" class="gap-1 text-[11px]">
                                                <x-lucide-x-circle class="size-3" />
                                                <span>Gagal</span>
                                            </x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-muted-foreground">
                                        {{ $job->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-muted-foreground">
                                        {{ $job->completed_at ? $job->completed_at->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td class="py-3 text-right">
                                        @if ($job->status === 'completed' && $job->output_file_path)
                                            <x-ui.button
                                                as="a"
                                                href="{{ route('bulk.download', $job) }}"
                                                size="sm"
                                                variant="outline"
                                                class="gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/10"
                                            >
                                                <x-lucide-download class="size-3.5" />
                                                <span>Unduh .docx</span>
                                            </x-ui.button>
                                        @elseif ($job->status === 'failed')
                                            <span class="text-xs text-destructive" title="{{ $job->error_message }}">Lihat Error</span>
                                        @else
                                            <span class="text-xs text-muted-foreground italic">Sedang berjalan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- MODAL DIALOG FORM BULK GENERATION --}}
    <div
        x-data="{ open: @entangle('showBulkModal') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/75 backdrop-blur-xs p-4 sm:p-6 flex items-center justify-center"
        role="dialog"
        aria-modal="true"
    >
        <div
            @click.outside="$wire.closeBulkModal()"
            class="relative w-full max-w-4xl bg-card rounded-xl border border-border shadow-2xl flex flex-col max-h-[88vh] overflow-hidden my-auto"
        >
                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-border px-6 py-4 bg-muted/30 shrink-0">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-foreground flex items-center gap-2">
                            <x-lucide-layers class="w-4 h-4 text-primary shrink-0" />
                            <span>Formulir Bulk Generation (Mailing)</span>
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Workspace: <span class="font-medium text-foreground">{{ $workspace?->name }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeBulkModal"
                        class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer transition-colors"
                        title="Tutup Modal"
                    >
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body (Scrollable) --}}
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    @if ($errorMessage)
                        <x-ui.alert variant="destructive" title="Bulk Generation Gagal">
                            {{ $errorMessage }}
                        </x-ui.alert>
                    @endif

                    @if ($errors->any())
                        <x-ui.alert variant="destructive" title="Formulir Belum Lengkap">
                            <ul class="list-disc pl-4 space-y-0.5 text-xs mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    @endif

                    {{-- Opsi: Salin dari Surat yang Sudah Ada --}}
                    @if (! empty($recentLetters) && count($recentLetters) > 0)
                        <div class="rounded-lg border border-border/80 bg-muted/20 p-3.5 space-y-2">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="bulk-source-letter" class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <x-lucide-copy class="size-3.5 text-primary" />
                                    <span>Salin dari Surat yang Ada (Opsional)</span>
                                </x-ui.label>
                                @if ($sourceLetterId)
                                    <button
                                        type="button"
                                        wire:click="$set('sourceLetterId', null)"
                                        class="text-[11px] text-muted-foreground hover:text-foreground underline cursor-pointer"
                                    >
                                        Reset Pilihan
                                    </button>
                                @endif
                            </div>
                            <x-ui.select id="bulk-source-letter" wire:model.live="sourceLetterId" class="mt-1 text-xs">
                                <option value="">— Buat baru dari template (tanpa salin surat) —</option>
                                @foreach ($recentLetters as $rl)
                                    <option value="{{ $rl->id }}">
                                        {{ $rl->formatted_number ?? '#' . $rl->id }} — {{ $rl->perihal }} ({{ $rl->template?->name ?? 'Template' }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif

                    {{-- Form Baris 1: Template, Perihal, Tanggal --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="bulk-tpl">Template Dokumen (.docx) <span class="text-destructive">*</span></x-ui.label>
                                @if ($selectedTemplate)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 px-2 py-0.5 rounded">
                                        <x-lucide-file-text class="size-3" />
                                        <span>{{ $selectedTemplate->name }}</span>
                                    </span>
                                @endif
                            </div>
                            <x-ui.select id="bulk-tpl" wire:model.live="templateId" class="mt-1.5">
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}">[{{ $tpl->scope->label() }}] {{ $tpl->name }}</option>
                                @endforeach
                            </x-ui.select>
                            @error('templateId')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="bulk-perihal">Hal / Perihal Surat (${perihal}) <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="bulk-perihal" wire:model.live.debounce.300ms="perihal" class="mt-1.5" placeholder="Mis. Undangan Seminar OASIS 2026" />
                            @error('perihal')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="bulk-date">Tanggal Surat <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="bulk-date" type="date" wire:model.live="letterDate" class="mt-1.5" />
                            @error('letterDate')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Format & Kategori Penomoran --}}
                    <div class="rounded-lg border border-border bg-muted/20 p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <x-lucide-hash class="size-3.5 text-primary" />
                                <span>Format &amp; Kategori Penomoran Surat Massal</span>
                            </p>
                            @if ($liveNumberPreview)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-primary/10 border border-primary/20 text-xs font-mono font-semibold text-primary">
                                    <span>Pratinjau Nomor:</span>
                                    <span>{{ $liveNumberPreview }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-ui.label for="bulk-kategori" class="text-xs">Jenis Surat {kategori}</x-ui.label>
                                <x-ui.select id="bulk-kategori" wire:model.live="kategori" class="mt-1 text-xs">
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
                                <x-ui.label for="bulk-parameter" class="text-xs">Lingkup / Parameter {parameter}</x-ui.label>
                                <x-ui.select id="bulk-parameter" wire:model.live="parameter" class="mt-1 text-xs">
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
                        </div>
                    </div>

                    {{-- Selector: Variabel Multi-Isi (Berbeda Tiap Surat) --}}
                    @if (! empty($allEditableFields))
                        <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-2">
                            <div>
                                <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <x-lucide-sliders-horizontal class="size-3.5 text-primary" />
                                    <span>Pilih Variabel yang Berbeda Tiap Surat (Multi-isi / Per-Penerima)</span>
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Klik untuk mengaktifkan variabel yang nilainya berbeda untuk setiap penerima. Variabel yang tidak dicentang otomatis menjadi <strong>Variabel Bersama</strong> (diisi 1x untuk semua).
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach ($allEditableFields as $f)
                                    @php
                                        $isVarying = in_array($f['name'], $varyingFieldNames, true);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleVaryingField('{{ $f['name'] }}')"
                                        wire:key="toggle-varying-{{ $f['name'] }}"
                                        @class([
                                            'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer',
                                            'bg-primary text-primary-foreground shadow-2xs' => $isVarying,
                                            'bg-background hover:bg-accent text-foreground border border-border' => ! $isVarying,
                                        ])
                                    >
                                        @if ($isVarying)
                                            <x-lucide-check class="w-3.5 h-3.5 shrink-0" />
                                        @else
                                            <x-lucide-plus class="w-3.5 h-3.5 shrink-0 text-muted-foreground" />
                                        @endif
                                        <span>{{ $f['label'] }} (${{ '{' . $f['name'] . '}' }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Variabel Bersama (Sama untuk semua surat) --}}
                    @if (! empty($sharedFields))
                        <div class="rounded-lg border border-border bg-card p-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Variabel Bersama (Sama untuk Seluruh Surat)
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Nilai berikut akan diterapkan otomatis ke semua surat yang dibuat.
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($sharedFields as $field)
                                    <div wire:key="shared-field-{{ $field['name'] }}">
                                        <x-ui.label class="text-xs">{{ $field['label'] }} (${{ '{' . $field['name'] . '}' }})</x-ui.label>
                                        <x-ui.input
                                            type="{{ ($field['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                            wire:model.live.debounce.300ms="sharedVariables.{{ $field['name'] }}"
                                            class="mt-1"
                                            placeholder="Isi {{ $field['label'] }}..."
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-ui.separator />

                    {{-- Recipient Input Tabs --}}
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex rounded-lg border border-border bg-muted p-1 text-xs">
                                <button
                                    type="button"
                                    wire:click="$set('inputTab', 'manual')"
                                    @class([
                                        'rounded-md px-3 py-1.5 font-medium transition-colors cursor-pointer',
                                        'bg-background text-foreground shadow-2xs' => $inputTab === 'manual',
                                        'text-muted-foreground hover:text-foreground' => $inputTab !== 'manual',
                                    ])
                                >
                                    Input Manual
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('inputTab', 'upload')"
                                    @class([
                                        'rounded-md px-3 py-1.5 font-medium transition-colors cursor-pointer',
                                        'bg-background text-foreground shadow-2xs' => $inputTab === 'upload',
                                        'text-muted-foreground hover:text-foreground' => $inputTab !== 'upload',
                                    ])
                                >
                                    Upload CSV / XLSX
                                </button>
                            </div>

                            @if ($inputTab === 'manual')
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="addManualRow">
                                    <x-lucide-plus class="size-3.5" />
                                    <span>Tambah Baris Penerima</span>
                                </x-ui.button>
                            @endif
                        </div>

                        @if ($inputTab === 'manual')
                            <div class="space-y-2">
                                {{-- Header Kolom Dinamis (Flex responsif menyesuaikan jumlah field) --}}
                                @if (! empty($varyingFields))
                                    <div class="flex items-center gap-2 text-[11px] font-semibold text-muted-foreground px-2 pb-1.5 border-b border-border">
                                        <div class="w-8 shrink-0 text-center font-mono">#</div>
                                        @foreach ($varyingFields as $vIdx => $vf)
                                            <div class="flex-1 min-w-0">
                                                {{ $vf['label'] }} (${{ '{' . $vf['name'] . '}' }})
                                            </div>
                                        @endforeach
                                        <div class="w-10 shrink-0 text-right pr-1">Aksi</div>
                                    </div>
                                @endif

                                @foreach ($manualRows as $idx => $row)
                                    <div class="flex items-center gap-2 px-1" wire:key="m-row-{{ $idx }}">
                                        <div class="w-8 shrink-0 text-center font-mono text-xs text-muted-foreground">
                                            {{ $idx + 1 }}
                                        </div>
                                        @foreach ($varyingFields as $vIdx => $vf)
                                            <div class="flex-1 min-w-0" wire:key="m-cell-{{ $idx }}-{{ $vf['name'] }}">
                                                <x-ui.input
                                                    wire:model.live.debounce.300ms="manualRows.{{ $idx }}.{{ $vf['name'] }}"
                                                    placeholder="{{ $vf['label'] }}..."
                                                    class="text-xs w-full"
                                                />
                                            </div>
                                        @endforeach
                                        <div class="w-10 shrink-0 text-right">
                                            @if (count($manualRows) > 1)
                                                <button
                                                    type="button"
                                                    wire:click="removeManualRow({{ $idx }})"
                                                    class="p-1.5 text-muted-foreground hover:text-destructive cursor-pointer rounded"
                                                    title="Hapus Baris"
                                                >
                                                    <x-lucide-trash-2 class="size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Upload Tab --}}
                            <div class="rounded-lg border-2 border-dashed border-border p-6 text-center space-y-3">
                                <x-lucide-upload-cloud class="size-8 mx-auto text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium text-foreground">Pilih file spreadsheet penerima</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">Mendukung file .csv, .xlsx, .xls (maksimal 500 baris)</p>
                                </div>
                                <input
                                    type="file"
                                    wire:model="recipientsFile"
                                    accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    class="text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                                />
                                @error('recipientsFile')
                                    <p class="text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror

                                @if (! empty($uploadedRows))
                                    <div class="pt-3 border-t border-border text-left">
                                        <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            ✓ {{ count($uploadedRows) }} baris data berhasil dimuat dari file.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Pratinjau Antrean Penerima --}}
                    <div class="rounded-lg border border-border bg-card p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <x-lucide-list-ordered class="size-3.5 text-primary" />
                                <span>Ringkasan Antrean Surat ({{ count($queueItems) }} Penerima)</span>
                            </p>
                            <span class="text-[11px] text-muted-foreground">
                                Tiap baris akan dibuatkan 1 surat dengan nomor berurutan
                            </span>
                        </div>

                        <div class="max-h-52 overflow-y-auto divide-y divide-border rounded-lg border border-border text-xs">
                            @forelse ($queueItems as $idx => $item)
                                @php
                                    $primaryKey = $varyingFieldNames[0] ?? array_key_first($item);
                                    $secondaryKey = $varyingFieldNames[1] ?? (array_keys($item)[1] ?? null);
                                    $primaryVal = $item[$primaryKey] ?? $item['tujuan'] ?? $item['penerima'] ?? $item['nama'] ?? reset($item);
                                    $secondaryVal = $secondaryKey ? ($item[$secondaryKey] ?? null) : null;
                                    if (! $secondaryVal) {
                                        $secondaryVal = $item['instansi'] ?? $item['tempat_tujuan'] ?? $item['alamat'] ?? '';
                                    }
                                @endphp
                                <div class="flex items-center justify-between px-3 py-2">
                                    <div class="min-w-0">
                                        <span class="font-mono text-muted-foreground mr-2">#{{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="font-medium text-foreground">
                                            {{ $primaryVal ?: '—' }}
                                        </span>
                                    </div>
                                    @if ($secondaryVal)
                                        <span class="text-muted-foreground truncate ml-2">
                                            {{ $secondaryVal }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-center text-muted-foreground">
                                    Belum ada penerima dalam antrean.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Modal Footer (Sticky) --}}
                <div class="border-t border-border px-6 py-4 bg-muted/30 shrink-0 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                        <span>Total: <strong class="text-foreground">{{ count($queueItems) }} Surat</strong></span>
                        @if ($liveNumberPreview)
                            <span class="text-border">|</span>
                            <span>Mulai nomor: <strong class="font-mono text-primary">{{ $liveNumberPreview }}</strong></span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 justify-end">
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeBulkModal">
                            Batal
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            wire:click="startBulk"
                            wire:loading.attr="disabled"
                            size="sm"
                            class="gap-1.5"
                            :disabled="count($queueItems) === 0"
                        >
                            <span wire:loading.remove wire:target="startBulk" class="inline-flex items-center gap-1.5">
                                <x-lucide-play class="size-3.5 fill-current" />
                                <span>Jalankan Bulk Generate ({{ count($queueItems) }} Penerima)</span>
                            </span>
                            <span wire:loading wire:target="startBulk" class="inline-flex items-center gap-1.5">
                                <x-lucide-loader-2 class="size-3.5 animate-spin" />
                                <span>Memproses Surat...</span>
                            </span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
