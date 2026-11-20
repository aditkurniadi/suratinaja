<div
    x-data="{
        showDetailModal: false,
        showPdfModal: false,
        showAuditModal: false,
        isToolbarMinimized: false,
        isActionDropdownOpen: false,
        isSyncingPdf: false,
        pdfVersion: '{{ $letter->updated_at?->timestamp ?? time() }}',
        openPdfModal() {
            this.isSyncingPdf = true;
            this.showPdfModal = true;
            fetch('{{ route('letters.preview-pdf', ['letter' => $letter->id, 'sync' => 1]) }}', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(() => {
                this.pdfVersion = Date.now();
                this.isSyncingPdf = false;
            }).catch(() => {
                this.isSyncingPdf = false;
            });
        },
        init() {
            this.$watch('isToolbarMinimized', () => {
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 100);
            });
            this.$watch('$store.sidebar.open', () => {
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 250);
            });
        }
    }"
    class="w-full flex-1 flex flex-col min-h-0"
>
    {{-- CSS Rules to force 100% width and height on OnlyOffice injected iframe --}}
    <style>
        .onlyoffice-wrapper-normal {
            height: calc(100vh - 120px) !important;
            min-height: 650px !important;
            width: 100% !important;
        }
        .onlyoffice-wrapper-fullscreen {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 40 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #onlyoffice-editor-frame {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        #onlyoffice-editor-frame iframe,
        #onlyoffice-editor-frame > div {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 100% !important;
            max-height: 100% !important;
            display: block !important;
            border: 0 !important;
        }
    </style>

    {{-- Floating Pill to Restore Toolbar When Minimized / Focus Mode --}}
    <div
        x-show="isToolbarMinimized"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="fixed top-3 right-4 z-50"
    >
        <button
            type="button"
            @click="isToolbarMinimized = false"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-background/95 text-foreground border border-border shadow-lg backdrop-blur-md hover:bg-muted transition-all cursor-pointer hover:scale-105"
            title="Kembalikan Toolbar &amp; Navigasi"
        >
            <x-lucide-maximize-2 class="size-3.5 text-blue-600 dark:text-blue-400" />
            <span>Tampilkan Toolbar</span>
        </button>
    </div>

    {{-- Compact Action Toolbar (1 Baris Ringkas & Ramping) --}}
    <header
        x-show="!isToolbarMinimized"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="w-full shrink-0 mb-2 rounded-lg border border-border bg-card p-2 sm:px-3 text-xs shadow-2xs"
    >
        <div class="flex items-center justify-between gap-3">
            {{-- Sisi Kiri: Status Badge, Nomor Surat, & Perihal --}}
            <div class="flex items-center gap-2 min-w-0">
                <x-ui.status-badge :status="$letter->status" />
                @if ($letter->is_manual_number)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                        Eksternal
                    </span>
                @endif
                <span class="font-mono font-semibold text-foreground truncate max-w-[180px] sm:max-w-xs" title="{{ $letter->formatted_number }}">
                    {{ $letter->formatted_number ?? 'Draft' }}
                </span>
                <span class="text-muted-foreground hidden md:inline">•</span>
                <span class="text-muted-foreground truncate hidden md:inline max-w-xs lg:max-w-md font-medium text-foreground" title="{{ $letter->perihal }}">
                    {{ $letter->perihal }}
                </span>
            </div>

            {{-- Sisi Kanan: Tombol-tombol Aksi Modal & Workflow --}}
            <div class="flex items-center gap-1.5 shrink-0">
                {{-- Tombol Detail Surat --}}
                <x-ui.button
                    size="sm"
                    variant="outline"
                    type="button"
                    @click="showDetailModal = true"
                    class="gap-1.5 h-8 px-2.5"
                    title="Lihat Detail & Variabel Surat"
                >
                    <x-lucide-info class="size-3.5 text-blue-600 dark:text-blue-400" />
                    <span class="hidden sm:inline">Detail Surat</span>
                </x-ui.button>

                {{-- Tombol Pratinjau PDF --}}
                <x-ui.button
                    size="sm"
                    variant="outline"
                    type="button"
                    @click="openPdfModal()"
                    class="gap-1.5 h-8 px-2.5"
                    title="Buka Pratinjau Dokumen PDF"
                >
                    <x-lucide-file-text class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                    <span class="hidden sm:inline">Pratinjau PDF</span>
                </x-ui.button>

                {{-- Tombol Riwayat & Audit --}}
                <x-ui.button
                    size="sm"
                    variant="outline"
                    type="button"
                    @click="showAuditModal = true"
                    class="gap-1.5 h-8 px-2.5"
                    title="Lihat Rantai Nomor & Riwayat Audit"
                >
                    <x-lucide-history class="size-3.5 text-purple-600 dark:text-purple-400" />
                    <span class="hidden sm:inline">Riwayat &amp; Audit</span>
                </x-ui.button>

                <div class="h-4 w-px bg-border mx-0.5 hidden sm:block"></div>

                {{-- Quick Workflow Transition Button --}}
                @if ($role?->canWrite())
                    @if ($letter->status === \App\Enums\LetterStatus::Draft)
                        <x-ui.button size="sm" wire:click="transition('menunggu_ttd')" class="h-8 px-2.5 gap-1">
                            <x-lucide-send class="size-3.5" />
                            <span class="hidden sm:inline">Ajukan TTD</span>
                        </x-ui.button>
                    @elseif ($letter->status === \App\Enums\LetterStatus::MenungguTtd)
                        <x-ui.button size="sm" wire:click="transition('selesai')" class="h-8 px-2.5 gap-1 bg-emerald-600 hover:bg-emerald-700 text-white">
                            <x-lucide-check-check class="size-3.5" />
                            <span class="hidden sm:inline">Tandai Selesai</span>
                        </x-ui.button>
                    @endif
                @endif

                {{-- Action Dropdown (More actions) --}}
                <div class="relative" @click.outside="isActionDropdownOpen = false">
                    <x-ui.button
                        size="sm"
                        variant="outline"
                        type="button"
                        @click="isActionDropdownOpen = !isActionDropdownOpen"
                        class="gap-1 h-8 px-2.5"
                    >
                        <span>Aksi</span>
                        <x-lucide-chevron-down class="size-3" />
                    </x-ui.button>

                    <div
                        x-show="isActionDropdownOpen"
                        x-cloak
                        x-transition
                        class="absolute right-0 mt-1 w-48 rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-lg z-40 text-xs"
                    >
                        @if ($letter->link_drive)
                            <a href="{{ $letter->link_drive }}" target="_blank" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-accent transition-colors">
                                <x-lucide-external-link class="size-3.5 text-blue-600" />
                                <span>Buka di Drive</span>
                            </a>
                        @endif

                        @if (! $letter->is_manual_number)
                            <a href="{{ route('letters.download', $letter->id) }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-accent transition-colors">
                                <x-lucide-download class="size-3.5 text-muted-foreground" />
                                <span>Unduh .docx</span>
                            </a>
                            <a href="{{ route('letters.preview-pdf', ['letter' => $letter->id, 'download' => 1]) }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-accent transition-colors">
                                <x-lucide-file-down class="size-3.5 text-muted-foreground" />
                                <span>Unduh .pdf</span>
                            </a>
                            <div class="my-1 border-t border-border"></div>
                        @endif

                        @if ($role?->canWrite())
                            @if ($letter->status !== \App\Enums\LetterStatus::Dibatalkan)
                                <button type="button" @click="isActionDropdownOpen = false; $wire.set('showReviseModal', true)" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-accent text-left transition-colors cursor-pointer">
                                    <x-lucide-git-branch class="size-3.5 text-muted-foreground" />
                                    <span>Revisi Surat</span>
                                </button>
                            @endif

                            <button type="button" wire:click="duplicate" @click="isActionDropdownOpen = false" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-accent text-left transition-colors cursor-pointer">
                                <x-lucide-copy class="size-3.5 text-muted-foreground" />
                                <span>Duplikasi Surat</span>
                            </button>

                            @if ($letter->status !== \App\Enums\LetterStatus::Dibatalkan)
                                <div class="my-1 border-t border-border"></div>
                                <button type="button" @click="isActionDropdownOpen = false; $wire.set('showCancelModal', true)" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-destructive/10 text-destructive text-left transition-colors cursor-pointer">
                                    <x-lucide-ban class="size-3.5" />
                                    <span>Batalkan Surat</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Refresh Status Button --}}
                <button
                    type="button"
                    wire:click="refreshPreview"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground cursor-pointer"
                    title="Sinkronkan &amp; Refresh Data"
                >
                    <x-lucide-refresh-cw class="size-3.5" />
                </button>

                {{-- Minimize Toolbar (Mode Fokus Penuh) --}}
                <button
                    type="button"
                    @click="isToolbarMinimized = true"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground cursor-pointer"
                    title="Sembunyikan Toolbar (Mode Fokus Penuh)"
                >
                    <x-lucide-minimize-2 class="size-3.5" />
                </button>
            </div>
        </div>
    </header>

    {{-- Main Area: OnlyOffice Word Editor Canvas (Definite Viewport Height & Fullscreen Minimize Mode) --}}
    <div
        :class="isToolbarMinimized
            ? 'onlyoffice-wrapper-fullscreen bg-white dark:bg-zinc-950 flex flex-col'
            : 'onlyoffice-wrapper-normal relative rounded-lg border border-border bg-background flex flex-col shadow-xs overflow-hidden'"
    >
        @if ($letter->is_manual_number)
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center space-y-4 bg-muted/10">
                <div class="size-16 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <x-lucide-external-link class="size-8" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-semibold text-foreground">Surat Eksternal (Ambil Nomor Saja)</h3>
                    <p class="text-xs text-muted-foreground max-w-md">
                        Dokumen surat ini dibuat di luar sistem SuratinAja dengan nomor urut resmi yang terdaftar di database.
                    </p>
                </div>
                @if ($letter->link_drive)
                    <x-ui.button as="a" href="{{ $letter->link_drive }}" target="_blank" class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white">
                        <x-lucide-external-link class="size-4" />
                        <span>Buka di Google Drive</span>
                    </x-ui.button>
                @endif
            </div>
        @else
            {{-- Container OnlyOffice dengan wire:ignore agar tidak pernah reload saat Livewire berinteraksi --}}
            <div
                wire:ignore
                x-data="onlyOfficeDocEditor({
                    apiUrl: '{{ rtrim(config('suratinaja.onlyoffice.url', 'https://office-suratinaja.test'), '/') }}/web-apps/apps/api/documents/api.js',
                    config: @js($onlyOfficeConfig),
                })"
                x-init="initEditor()"
                class="w-full h-full flex-1 relative bg-background overflow-hidden"
                style="height: 100% !important; width: 100% !important;"
            >
                <div x-show="loading" class="absolute inset-0 flex flex-col items-center justify-center bg-background/80 backdrop-blur-xs z-10 space-y-3">
                    <div class="size-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs text-muted-foreground font-medium">Menghubungkan ke Microsoft Word Online Editor...</p>
                </div>

                <div x-show="error" x-cloak class="absolute inset-0 flex flex-col items-center justify-center p-8 text-center space-y-3 bg-background z-20">
                    <div class="size-12 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <x-lucide-alert-triangle class="size-6" />
                    </div>
                    <h4 class="text-sm font-semibold text-foreground">Gagal Menghubungkan ke OnlyOffice Document Server</h4>
                    <p class="text-xs text-muted-foreground max-w-md mx-auto" x-text="error"></p>
                    <div class="pt-2 flex justify-center gap-2">
                        <x-ui.button type="button" size="sm" @click="error = null; initEditor()">
                            <x-lucide-refresh-cw class="size-3.5" />
                            <span>Coba Hubungkan Ulang</span>
                        </x-ui.button>
                        <x-ui.button type="button" size="sm" variant="outline" @click="showPdfModal = true">
                            <span>Buka Pratinjau PDF Saja</span>
                        </x-ui.button>
                    </div>
                </div>

                <div id="onlyoffice-editor-frame" class="w-full h-full"></div>
            </div>
        @endif
    </div>

    {{-- MODAL 1: Detail & Variabel Surat (Client-Side Alpine.js) --}}
    <div
        x-show="showDetailModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="showDetailModal = false"
    >
        <div
            @click.outside="showDetailModal = false"
            class="w-full max-w-2xl rounded-xl border border-border bg-background p-6 shadow-2xl max-h-[85vh] overflow-y-auto space-y-5"
        >
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div class="space-y-0.5">
                    <h3 class="text-base font-semibold text-foreground">Detail &amp; Variabel Surat</h3>
                    <p class="font-mono text-xs text-muted-foreground">{{ $letter->formatted_number ?? 'Draft' }}</p>
                </div>
                <button type="button" @click="showDetailModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                    <x-lucide-x class="size-5" />
                </button>
            </div>

            {{-- Metadata Grid --}}
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-muted/40 p-3 rounded-lg border border-border">
                <div>
                    <dt class="text-muted-foreground">Template</dt>
                    <dd class="font-medium mt-0.5 truncate">{{ $letter->template?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Tanggal Surat</dt>
                    <dd class="font-medium mt-0.5">{{ $letter->letter_date?->translatedFormat('d F Y') }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Dibuat Oleh</dt>
                    <dd class="font-medium mt-0.5">{{ $letter->author?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Waktu TTD Basah</dt>
                    <dd class="font-medium mt-0.5">{{ $letter->signed_at ? $letter->signed_at->format('d M Y H:i') : 'Belum ditandatangani' }}</dd>
                </div>
            </dl>

            @if ($letter->cancel_reason)
                <x-ui.alert variant="destructive" title="Alasan Pembatalan">
                    {{ $letter->cancel_reason }}
                </x-ui.alert>
            @endif

            {{-- Variable values summary --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nilai Variabel Isian (${...})</h4>
                    @if ($role?->canWrite() && $letter->status === \App\Enums\LetterStatus::Draft)
                        <a href="{{ route('letters.edit', $letter->id) }}" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 font-medium">
                            <x-lucide-pencil class="size-3" />
                            <span>Edit Form di Composer</span>
                        </a>
                    @endif
                </div>
                <div class="rounded-lg border border-border divide-y divide-border text-xs max-h-56 overflow-y-auto">
                    @forelse (collect($letter->field_values ?? [])->reject(fn ($v, $k) => str_starts_with((string) $k, '_')) as $k => $v)
                        <div class="flex items-start justify-between gap-3 px-3 py-2">
                            <code class="font-mono text-muted-foreground">${{ '{' . $k . '}' }}</code>
                            <div class="text-right font-medium break-all">
                                @if (is_array($v))
                                    <span>{{ count($v) }} baris lampiran / tabel</span>
                                @else
                                    <span>{{ $v ?: '—' }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-2 text-muted-foreground">Tidak ada variabel isian khusus.</div>
                    @endforelse
                </div>
            </div>

            {{-- Signers --}}
            @php
                $letterSigners = is_array($letter->field_values['_signers'] ?? null) && count($letter->field_values['_signers']) > 0
                    ? $letter->field_values['_signers']
                    : ($workspace ? $workspace->defaultSigners() : []);
            @endphp
            @if (count($letterSigners) > 0)
                <div class="space-y-2">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Penandatangan Resmi (TTD)</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        @foreach ($letterSigners as $sig)
                            <div class="rounded-lg border border-border p-2.5 space-y-0.5 bg-muted/20">
                                <p class="text-muted-foreground">{{ $sig['position'] ?? '' }}</p>
                                <p class="font-semibold underline">{{ $sig['name'] ?? '' }}</p>
                                @if (!empty($sig['id_number']))
                                    <p class="font-mono text-[11px] text-muted-foreground">{{ trim(($sig['id_label'] ?? '') . ' ' . $sig['id_number']) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-2 border-t border-border">
                <x-ui.button variant="outline" @click="showDetailModal = false">Tutup</x-ui.button>
            </div>
        </div>
    </div>

    {{-- MODAL 2: Pratinjau Dokumen PDF (Client-Side Alpine.js) --}}
    <div
        x-show="showPdfModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="showPdfModal = false"
    >
        <div
            @click.outside="showPdfModal = false"
            class="w-full max-w-5xl rounded-xl border border-border bg-background p-5 shadow-2xl flex flex-col h-[88vh]"
        >
            <div class="flex items-center justify-between border-b border-border pb-3 shrink-0">
                <div class="flex items-center gap-2">
                    <x-lucide-file-text class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Pratinjau Dokumen PDF</h3>
                        <p class="text-xs text-muted-foreground">A4 Portrait (Margin Normal 2,54 cm)</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        :href="'{{ route('letters.preview-pdf', ['letter' => $letter->id, 'download' => 1]) }}&v=' + pdfVersion"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border border-border bg-background hover:bg-accent hover:text-accent-foreground transition-colors"
                    >
                        <x-lucide-download class="size-3.5" />
                        <span>Unduh PDF</span>
                    </a>
                    <a
                        :href="'{{ route('letters.preview-pdf', $letter->id) }}?v=' + pdfVersion"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium border border-border bg-background hover:bg-accent hover:text-accent-foreground transition-colors"
                    >
                        <x-lucide-external-link class="size-3.5" />
                        <span>Buka di Tab Baru</span>
                    </a>
                    <button type="button" @click="showPdfModal = false" class="text-muted-foreground hover:text-foreground ml-2 cursor-pointer">
                        <x-lucide-x class="size-5" />
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 pt-4 relative">
                {{-- Syncing overlay --}}
                <div
                    x-show="isSyncingPdf"
                    x-cloak
                    class="absolute inset-0 flex flex-col items-center justify-center bg-background/80 backdrop-blur-xs z-10 space-y-2 rounded-lg"
                >
                    <div class="size-7 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs text-muted-foreground font-medium">Menyinkronkan dokumen dari editor OnlyOffice...</p>
                </div>

                <iframe
                    :src="'{{ route('letters.preview-pdf', $letter->id) }}?v=' + pdfVersion"
                    class="w-full h-full rounded-lg border border-border bg-muted/20"
                    title="Pratinjau PDF Surat"
                ></iframe>
            </div>
        </div>
    </div>

    {{-- MODAL 3: Riwayat Nomor & Audit (Client-Side Alpine.js) --}}
    <div
        x-show="showAuditModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="showAuditModal = false"
    >
        <div
            @click.outside="showAuditModal = false"
            class="w-full max-w-2xl rounded-xl border border-border bg-background p-6 shadow-2xl max-h-[85vh] overflow-y-auto space-y-5"
        >
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div class="space-y-0.5">
                    <h3 class="text-base font-semibold text-foreground">Rantai Nomor &amp; Riwayat Audit</h3>
                    <p class="text-xs text-muted-foreground">Audit jejak integritas nomor dan tindakan surat</p>
                </div>
                <button type="button" @click="showAuditModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                    <x-lucide-x class="size-5" />
                </button>
            </div>

            {{-- Number chain --}}
            <div class="space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Rantai Nomor Surat (DEC-005)</h4>
                <div class="divide-y divide-border rounded-lg border border-border text-xs">
                    @foreach ($letter->numbers as $num)
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3">
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'font-mono font-semibold',
                                        'line-through text-muted-foreground' => $num->status === \App\Enums\NumberStatus::Hangus,
                                    ])>
                                        {{ $num->formatted_number }}
                                    </span>
                                    <x-ui.status-badge :status="$num->status" />
                                </div>
                                <p class="text-muted-foreground text-[11px]">
                                    Alokasi: {{ $num->allocated_at?->format('d M Y H:i') }}
                                    @if ($num->burned_at)
                                        • Hangus: {{ $num->burned_at->format('d M Y H:i') }}
                                    @endif
                                </p>
                            </div>
                            @if ($num->status === \App\Enums\NumberStatus::Hangus && $num->burn_reason)
                                <span class="text-xs text-muted-foreground italic">
                                    "{{ $num->burn_reason }}"
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Activity timeline --}}
            <div class="space-y-3 pt-2">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Log Aktivitas &amp; Audit</h4>
                <ol class="relative border-l border-border pl-4 space-y-4 text-xs">
                    @forelse ($letter->activities->sortByDesc('created_at') as $act)
                        <li class="space-y-1">
                            <div class="absolute -left-1.5 mt-1.5 size-3 rounded-full border border-background bg-muted-foreground"></div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-foreground">{{ $act->label() }}</span>
                                <time class="text-muted-foreground">{{ $act->created_at?->diffForHumans() }}</time>
                            </div>
                            <p class="text-muted-foreground">
                                Oleh: <strong>{{ $act->actor?->name ?? 'Sistem' }}</strong>
                            </p>
                            @if (!empty($act->payload['reason']))
                                <p class="italic text-muted-foreground">"{{ $act->payload['reason'] }}"</p>
                            @endif
                            @if (!empty($act->payload['description']))
                                <p class="text-[11px] text-muted-foreground">{{ $act->payload['description'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-muted-foreground">Belum ada riwayat aktivitas.</li>
                    @endforelse
                </ol>
            </div>

            <div class="flex justify-end pt-2 border-t border-border">
                <x-ui.button variant="outline" @click="showAuditModal = false">Tutup</x-ui.button>
            </div>
        </div>
    </div>

    {{-- MODAL 4: Revisi Surat (Livewire Dialog) --}}
    @if ($showReviseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-foreground">Revisi Surat (DEC-005)</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Perubahan nomor dan perihal akan dicatat dalam riwayat revisi resmi.
                </p>

                @if ($revisionError)
                    <x-ui.alert variant="destructive" class="mt-3">
                        {{ $revisionError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="submitRevision" class="mt-4 space-y-4 text-xs">
                    <div>
                        <x-ui.label for="revReason">Alasan Revisi (Wajib)</x-ui.label>
                        <x-ui.textarea id="revReason" wire:model="revisionReason" class="mt-1" placeholder="Contoh: Perubahan tanggal kegiatan..." />
                    </div>

                    <div>
                        <x-ui.label for="revPerihal">Perihal Baru</x-ui.label>
                        <x-ui.input id="revPerihal" wire:model="revisionPerihal" class="mt-1" />
                    </div>

                    <div class="rounded-lg border border-border p-3 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="useNewNumber" class="rounded border-border text-primary focus:ring-primary">
                            <span class="font-medium text-foreground">Gunakan Nomor Baru (Nomor lama HANGUS)</span>
                        </label>
                        <p class="text-[11px] text-muted-foreground">
                            Jika tidak dicentang, nomor aktif saat ini tetap dipertahankan.
                        </p>
                    </div>

                    @foreach ($editableFields as $field)
                        @if (! str_starts_with($field['name'], '_'))
                            <div>
                                <x-ui.label for="rev_var_{{ $field['name'] }}">
                                    ${{ '{' . $field['name'] . '}' }} ({{ $field['label'] ?? $field['name'] }})
                                </x-ui.label>
                                <x-ui.input
                                    id="rev_var_{{ $field['name'] }}"
                                    type="{{ ($field['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                    wire:model="revisionVariables.{{ $field['name'] }}"
                                    class="mt-1"
                                />
                            </div>
                        @endif
                    @endforeach

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="outline" wire:click="$set('showReviseModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Revisi</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 5: Batalkan Surat (Livewire Dialog) --}}
    @if ($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-2xl">
                <h3 class="text-base font-semibold text-destructive">Batalkan Surat &amp; Hanguskan Nomor</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Nomor <code class="font-mono text-foreground">{{ $letter->formatted_number }}</code> akan berstatus <strong>HANGUS</strong> secara permanen dan surat tetap tersimpan di riwayat audit.
                </p>

                @if ($cancelError)
                    <x-ui.alert variant="destructive" class="mt-3">
                        {{ $cancelError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="submitCancel" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="cancelReason">Alasan Pembatalan (Wajib)</x-ui.label>
                        <x-ui.textarea id="cancelReason" wire:model="cancelReason" class="mt-1.5" placeholder="Jelaskan alasan pembatalan surat..." />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="outline" wire:click="$set('showCancelModal', false)">Tutup</x-ui.button>
                        <x-ui.button variant="destructive" type="submit">Ya, Batalkan Surat</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('onlyOfficeDocEditor', ({ apiUrl, config }) => ({
        loading: true,
        error: null,
        editorInstance: null,

        initEditor() {
            if (!config) {
                this.loading = false;
                this.error = 'Konfigurasi editor OnlyOffice belum tersedia.';
                return;
            }

            if (window.DocsAPI && window.DocsAPI.DocEditor) {
                this.mountEditor();
                return;
            }

            const existingScript = document.getElementById('onlyoffice-api-script');
            if (existingScript) {
                existingScript.addEventListener('load', () => this.mountEditor());
                existingScript.addEventListener('error', () => this.handleScriptError());
                return;
            }

            const script = document.createElement('script');
            script.id = 'onlyoffice-api-script';
            script.src = apiUrl;
            script.onload = () => this.mountEditor();
            script.onerror = () => this.handleScriptError();
            document.head.appendChild(script);
        },

        handleScriptError() {
            this.loading = false;
            this.error = 'Tidak dapat menghubungi server OnlyOffice di (' + apiUrl + '). Pastikan container OnlyOffice Document Server sedang berjalan.';
        },

        mountEditor() {
            try {
                this.loading = false;
                const container = document.getElementById('onlyoffice-editor-frame');
                if (!container || !window.DocsAPI) return;

                container.innerHTML = '';

                const editorConfig = Object.assign({}, config, {
                    width: "100%",
                    height: "100%",
                });
                editorConfig.events = editorConfig.events || {};

                const origOnAppReady = editorConfig.events.onAppReady;
                editorConfig.events.onAppReady = () => {
                    this.loading = false;
                    if (origOnAppReady) origOnAppReady();
                };

                editorConfig.events.onError = (event) => {
                    console.error('OnlyOffice DocsAPI error:', event);
                };

                this.editorInstance = new window.DocsAPI.DocEditor('onlyoffice-editor-frame', editorConfig);
            } catch (e) {
                this.loading = false;
                this.error = e.message || 'Terjadi kesalahan saat memuat editor.';
            }
        }
    }));
});
</script>
@endpush
