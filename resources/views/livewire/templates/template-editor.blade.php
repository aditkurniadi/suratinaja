<div
    x-data="templateVariableBridge({
        canEdit: @js($canEdit),
        systemCatalog: @js($systemCatalog),
        quickCustomVars: @js($quickCustomVars),
        repeaterPresets: @js($repeaterPresets),
    })"
    x-init="initBridge()"
    class="w-full flex-1 flex flex-col min-h-0"
>
    <style>
        .tpl-editor-shell-normal {
            height: calc(100vh - 120px) !important;
            min-height: 620px !important;
            width: 100% !important;
        }
        .tpl-editor-shell-fullscreen {
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
        #onlyoffice-template-frame {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        #onlyoffice-template-frame iframe,
        #onlyoffice-template-frame > div {
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

    {{-- Floating Controls when in Focus / Fullscreen Mode --}}
    <div
        x-show="isToolbarMinimized"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="fixed top-3 right-4 z-50 flex items-center gap-1.5"
    >
        @if ($canEdit)
            <button
                type="button"
                @click="showVarPanel = !showVarPanel"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-background/95 text-foreground border border-border shadow-lg backdrop-blur-md hover:bg-muted transition-all cursor-pointer"
                title="Buka / Tutup Panel Variabel"
            >
                <x-lucide-braces class="size-3.5 text-primary" />
                <span x-text="showVarPanel ? 'Tutup Variabel' : 'Variabel'"></span>
            </button>
        @endif
        <button
            type="button"
            @click="isToolbarMinimized = false"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-background/95 text-foreground border border-border shadow-lg backdrop-blur-md hover:bg-muted transition-all cursor-pointer"
            title="Kembalikan Toolbar &amp; Navigasi"
        >
            <x-lucide-maximize-2 class="size-3.5 text-blue-600 dark:text-blue-400" />
            <span>Tampilkan Toolbar</span>
        </button>
    </div>

    {{-- Compact Top Action Toolbar (1 Baris) --}}
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
        <div class="flex items-center justify-between gap-2">
            {{-- Sisi Kiri: Kembali + Identitas Template --}}
            <div class="flex items-center gap-2 min-w-0">
                <a
                    href="{{ $backUrl }}"
                    @click.prevent="window.SuratinAjaNav ? window.SuratinAjaNav.goBack('{{ $backUrl }}') : (window.location.href = '{{ $backUrl }}')"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground transition-colors cursor-pointer"
                    title="Kembali ke halaman sebelumnya"
                    aria-label="Kembali ke halaman sebelumnya"
                >
                    <x-lucide-arrow-left class="size-4" />
                </a>

                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-semibold text-foreground truncate max-w-[160px] sm:max-w-xs md:max-w-sm" title="{{ $template->name }}">
                        {{ $template->name }}
                    </span>

                    @if ($template->isGlobal())
                        <x-ui.badge variant="outline" class="shrink-0 text-[10px] border-blue-500/30 text-blue-600 dark:text-blue-400">
                            Global
                        </x-ui.badge>
                    @else
                        <x-ui.badge variant="secondary" class="shrink-0 text-[10px]">
                            Workspace
                        </x-ui.badge>
                    @endif

                    <span class="font-mono text-[11px] text-muted-foreground shrink-0 hidden sm:inline">
                        v{{ $template->version }}
                    </span>

                    <span class="hidden lg:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border border-border bg-muted/60 text-muted-foreground shrink-0">
                        {{ $template->has_embedded_kop ? 'Kop Mandiri' : 'Kop Workspace' }}
                    </span>

                    @php $detectedCount = count($template->variables_schema ?? []); @endphp
                    <span
                        @class([
                            'hidden md:inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-medium shrink-0',
                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20' => $detectedCount > 0,
                            'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20' => $detectedCount === 0,
                        ])
                        title="Jumlah variabel terdeteksi di template"
                    >
                        <x-lucide-braces class="size-3" />
                        <span>{{ $detectedCount }} var</span>
                    </span>
                </div>
            </div>

            {{-- Sisi Kanan: Kontrol Editor & Variabel --}}
            <div class="flex items-center gap-1.5 shrink-0">
                @if ($canEdit)
                    <x-ui.button
                        size="sm"
                        :variant="'outline'"
                        type="button"
                        @click="showVarPanel = !showVarPanel"
                        x-bind:class="showVarPanel ? 'border-primary/50 bg-primary/5 text-primary' : ''"
                        class="gap-1.5 h-8 px-2.5"
                        title="Buka / Tutup Panel Input Variabel"
                    >
                        <x-lucide-braces class="size-3.5" />
                        <span class="hidden sm:inline">Panel Variabel</span>
                    </x-ui.button>

                    <x-ui.button
                        size="sm"
                        variant="outline"
                        type="button"
                        wire:click="syncVariables"
                        wire:loading.attr="disabled"
                        class="gap-1.5 h-8 px-2.5"
                        title="Simpan paksa &amp; pindai ulang variabel ${...} dari dokumen"
                    >
                        <x-lucide-refresh-cw class="size-3.5 text-emerald-600 dark:text-emerald-400" wire:loading.class="animate-spin" wire:target="syncVariables" />
                        <span class="hidden md:inline" wire:loading.remove wire:target="syncVariables">Sinkronkan Variabel</span>
                        <span class="hidden md:inline" wire:loading wire:target="syncVariables">Memindai...</span>
                    </x-ui.button>

                    <x-ui.button
                        size="sm"
                        variant="outline"
                        type="button"
                        wire:click="openSettings"
                        class="gap-1.5 h-8 px-2.5"
                        title="Ubah Nama, Deskripsi, &amp; Mode Kop Template"
                    >
                        <x-lucide-settings-2 class="size-3.5 text-muted-foreground" />
                        <span class="hidden lg:inline">Pengaturan</span>
                    </x-ui.button>
                @elseif ($template->isGlobal() && $role?->canWrite())
                    <x-ui.button
                        size="sm"
                        type="button"
                        wire:click="duplicateToWorkspace"
                        class="gap-1.5 h-8 px-2.5"
                    >
                        <x-lucide-copy class="size-3.5" />
                        <span>Duplikasi ke Workspace untuk Mengedit</span>
                    </x-ui.button>
                @endif

                <x-ui.button
                    as="a"
                    size="sm"
                    variant="outline"
                    href="{{ route('templates.download', $template->id) }}"
                    class="gap-1.5 h-8 px-2.5"
                    title="Unduh file master .docx"
                >
                    <x-lucide-download class="size-3.5 text-muted-foreground" />
                    <span class="hidden xl:inline">.docx</span>
                </x-ui.button>

                @if ($role?->canWrite() && ! $template->is_deprecated)
                    <x-ui.button
                        as="a"
                        size="sm"
                        href="{{ route('letters.create', ['template_id' => $template->id]) }}"
                        class="gap-1.5 h-8 px-2.5"
                        title="Buat surat baru memakai template ini"
                    >
                        <x-lucide-file-plus-2 class="size-3.5" />
                        <span class="hidden sm:inline">Pakai Surat</span>
                    </x-ui.button>
                @endif

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

    @if (! $canEdit)
        <div class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-800 dark:text-amber-300">
            <div class="flex items-center gap-2">
                <x-lucide-eye class="size-3.5 shrink-0" />
                <span>
                    @if ($template->isGlobal())
                        <strong>Mode Baca-Saja (Template Global):</strong> Template global tidak dapat diubah langsung oleh workspace. Duplikasi ke workspace Anda untuk memodifikasinya.
                    @else
                        <strong>Mode Baca-Saja:</strong> Peran Anda hanya memiliki akses melihat template ini.
                    @endif
                </span>
            </div>
        </div>
    @endif

    {{-- Main Split Area: OnlyOffice Editor + Collapsible Variable Panel --}}
    <div
        :class="isToolbarMinimized
            ? 'tpl-editor-shell-fullscreen bg-white dark:bg-zinc-950 flex'
            : 'tpl-editor-shell-normal relative rounded-lg border border-border bg-background flex shadow-xs overflow-hidden'"
    >
        {{-- Kiri: Canvas OnlyOffice Word Editor --}}
        <div class="flex-1 min-w-0 h-full relative flex flex-col overflow-hidden">
            @if ($configError || ! $onlyOfficeConfig)
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center space-y-3 bg-muted/10">
                    <div class="size-12 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <x-lucide-alert-triangle class="size-6" />
                    </div>
                    <h4 class="text-sm font-semibold text-foreground">Konfigurasi Editor Template Tidak Tersedia</h4>
                    <p class="text-xs text-muted-foreground max-w-md">{{ $configError ?: 'File master template tidak dapat dimuat.' }}</p>
                </div>
            @else
                <div
                    wire:ignore
                    x-data="onlyOfficeTemplateDocEditor({
                        apiUrl: '{{ rtrim(config('suratinaja.onlyoffice.url', 'http://127.0.0.1:8082'), '/') }}/web-apps/apps/api/documents/api.js',
                        config: @js($onlyOfficeConfig),
                    })"
                    x-init="initEditor()"
                    class="w-full h-full flex-1 relative bg-background overflow-hidden"
                    style="height: 100% !important; width: 100% !important;"
                >
                    <div x-show="loading" class="absolute inset-0 flex flex-col items-center justify-center bg-background/80 backdrop-blur-xs z-10 space-y-3">
                        <div class="size-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-xs text-muted-foreground font-medium">Memuat Editor Template OnlyOffice...</p>
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
                            <x-ui.button as="a" size="sm" variant="outline" href="{{ route('templates.download', $template->id) }}">
                                <span>Unduh .docx Saja</span>
                            </x-ui.button>
                        </div>
                    </div>

                    <div id="onlyoffice-template-frame" class="w-full h-full"></div>
                </div>
            @endif
        </div>

        {{-- Kanan: Panel Input Variabel Sistem, Kustom, Tabel Dinamis, & Variabel Terdeteksi --}}
        @if ($canEdit)
            <aside
                x-show="showVarPanel"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-4"
                :class="isToolbarMinimized ? 'fixed top-14 right-3 bottom-3 z-50 rounded-xl border shadow-2xl' : 'border-l'"
                class="w-80 xl:w-[360px] shrink-0 border-border bg-card flex flex-col min-h-0 overflow-hidden text-xs"
            >
                {{-- Header Panel Variabel + Status Bridge --}}
                <div class="px-3 py-2.5 border-b border-border bg-muted/30 shrink-0 space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5 font-semibold text-foreground text-xs">
                            <x-lucide-braces class="size-3.5 text-primary" />
                            <span>Input Variabel Template</span>
                        </div>
                        <button
                            type="button"
                            @click="showVarPanel = false"
                            class="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted cursor-pointer"
                            title="Tutup Panel"
                        >
                            <x-lucide-x class="size-3.5" />
                        </button>
                    </div>

                    {{-- Indikator status koneksi 1-klik plugin OnlyOffice --}}
                    <div class="flex items-center gap-1.5 text-[11px]">
                        <span
                            class="inline-block size-2 rounded-full shrink-0"
                            :class="pluginReady ? 'bg-emerald-500' : 'bg-amber-500'"
                        ></span>
                        <span
                            class="text-muted-foreground truncate"
                            x-text="pluginReady
                                ? 'Terhubung — klik variabel langsung sisip ke kursor'
                                : 'Klik variabel untuk salin cepat (Cmd/Ctrl+V)'"
                        ></span>
                    </div>

                    {{-- 4 Sub-Tab Panel Variabel --}}
                    <div class="grid grid-cols-4 gap-1 rounded-md bg-muted p-0.5 text-[11px] font-medium">
                        <button
                            type="button"
                            @click="activeVarTab = 'sistem'"
                            :class="activeVarTab === 'sistem' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="rounded px-1.5 py-1 transition-colors cursor-pointer"
                        >
                            Sistem
                        </button>
                        <button
                            type="button"
                            @click="activeVarTab = 'kustom'"
                            :class="activeVarTab === 'kustom' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="rounded px-1.5 py-1 transition-colors cursor-pointer"
                        >
                            Kustom
                        </button>
                        <button
                            type="button"
                            @click="activeVarTab = 'tabel'"
                            :class="activeVarTab === 'tabel' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="rounded px-1.5 py-1 transition-colors cursor-pointer"
                        >
                            Tabel
                        </button>
                        <button
                            type="button"
                            @click="activeVarTab = 'terdeteksi'"
                            :class="activeVarTab === 'terdeteksi' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="rounded px-1.5 py-1 transition-colors cursor-pointer truncate"
                        >
                            Aktif ({{ count($template->variables_schema ?? []) }})
                        </button>
                    </div>
                </div>

                {{-- Body Scrollable --}}
                <div class="flex-1 overflow-y-auto p-3 space-y-3 min-h-0">
                    {{-- TAB 1: VARIABEL SISTEM --}}
                    <div x-show="activeVarTab === 'sistem'" class="space-y-3">
                        <div class="relative">
                            <x-lucide-search class="size-3.5 text-muted-foreground absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Cari variabel sistem (mis. nomor, ttd)..."
                                class="w-full h-8 pl-8 pr-2.5 rounded-md border border-border bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                            />
                        </div>

                        <template x-for="group in filteredSystemCatalog" :key="group.category">
                            <div class="space-y-1.5">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground px-0.5" x-text="group.category"></p>
                                <div class="space-y-1.5">
                                    <template x-for="item in group.items" :key="item.name">
                                        <div class="group rounded-lg border border-border bg-background hover:border-primary/50 hover:bg-primary/5 transition-colors">
                                            <div class="flex items-start gap-1.5 p-2">
                                                <button
                                                    type="button"
                                                    @click="insertToken(item.token)"
                                                    class="flex-1 min-w-0 text-left cursor-pointer space-y-0.5"
                                                    :title="'Klik untuk menyisipkan ' + item.token + ' ke posisi kursor'"
                                                >
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <code class="font-mono font-semibold text-[11px] text-primary" x-text="item.token"></code>
                                                        <template x-if="item.recommended">
                                                            <span class="px-1 py-0.2 rounded text-[9px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                                                Utama
                                                            </span>
                                                        </template>
                                                    </div>
                                                    <p class="text-[11px] font-medium text-foreground" x-text="item.label"></p>
                                                    <p class="text-[10px] text-muted-foreground leading-snug" x-text="item.description"></p>
                                                    <p class="text-[10px] text-muted-foreground/80 font-mono truncate" x-text="'Contoh: ' + item.example"></p>
                                                </button>
                                                <div class="flex flex-col gap-1 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="insertToken(item.token)"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded border border-border bg-card text-primary hover:bg-primary hover:text-primary-foreground transition-colors cursor-pointer"
                                                        title="Sisipkan ke Kursor"
                                                    >
                                                        <x-lucide-plus class="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyToken(item.token)"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                                                        title="Salin ke Clipboard"
                                                    >
                                                        <x-lucide-copy class="size-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredSystemCatalog.length === 0" class="py-6 text-center text-muted-foreground text-xs">
                            Tidak ada variabel sistem yang cocok dengan "<span x-text="searchQuery"></span>".
                        </div>
                    </div>

                    {{-- TAB 2: VARIABEL KUSTOM --}}
                    <div x-show="activeVarTab === 'kustom'" x-cloak class="space-y-4">
                        <div class="rounded-lg border border-border bg-background p-3 space-y-2.5">
                            <div class="space-y-0.5">
                                <p class="font-semibold text-foreground text-xs">Buat Variabel Input Form Baru</p>
                                <p class="text-[11px] text-muted-foreground leading-snug">
                                    Variabel kustom otomatis menjadi kolom input di form <strong>Buat Surat</strong>.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-foreground">Nama Field / Variabel</label>
                                <input
                                    type="text"
                                    x-model="customVarInput"
                                    @keydown.enter.prevent="insertCustomVariable()"
                                    placeholder="Mis. Tujuan Surat, Tempat Acara..."
                                    class="w-full h-8 px-2.5 rounded-md border border-border bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-foreground">Tipe Input di Form Buat Surat</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        type="button"
                                        @click="customVarType = 'text'"
                                        :class="customVarType === 'text' ? 'border-primary bg-primary/10 text-primary font-semibold' : 'border-border bg-background text-muted-foreground hover:text-foreground'"
                                        class="rounded-md border px-2 py-1.5 text-[11px] text-center cursor-pointer transition-colors"
                                    >
                                        Teks Biasa
                                    </button>
                                    <button
                                        type="button"
                                        @click="customVarType = 'date'"
                                        :class="customVarType === 'date' ? 'border-primary bg-primary/10 text-primary font-semibold' : 'border-border bg-background text-muted-foreground hover:text-foreground'"
                                        class="rounded-md border px-2 py-1.5 text-[11px] text-center cursor-pointer transition-colors"
                                    >
                                        Tanggal (Date Picker)
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-md border border-dashed border-border bg-muted/40 px-2.5 py-2 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-[10px] text-muted-foreground">Preview Token Word:</p>
                                    <code class="font-mono font-semibold text-xs text-primary break-all" x-text="customVarToken || '${nama_variabel}'"></code>
                                </div>
                                <button
                                    type="button"
                                    :disabled="!customVarToken"
                                    @click="copyToken(customVarToken)"
                                    class="p-1.5 rounded border border-border bg-background text-muted-foreground hover:text-foreground disabled:opacity-40 cursor-pointer shrink-0"
                                    title="Salin token"
                                >
                                    <x-lucide-copy class="size-3.5" />
                                </button>
                            </div>

                            <button
                                type="button"
                                :disabled="!customVarToken"
                                @click="insertCustomVariable()"
                                class="w-full inline-flex items-center justify-center gap-1.5 h-8 rounded-md bg-primary text-primary-foreground font-medium text-xs hover:bg-primary/90 disabled:opacity-40 cursor-pointer transition-colors"
                            >
                                <x-lucide-plus-circle class="size-3.5" />
                                <span>Sisipkan ke Posisi Kursor</span>
                            </button>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground px-0.5">
                                Variabel Kustom Populer (1-Klik)
                            </p>
                            <div class="grid grid-cols-1 gap-1.5">
                                <template x-for="qv in quickCustomVars" :key="qv.token">
                                    <div class="flex items-center justify-between gap-1.5 rounded-md border border-border bg-background px-2.5 py-1.5 hover:border-primary/50 hover:bg-primary/5 transition-colors">
                                        <button
                                            type="button"
                                            @click="insertToken(qv.token)"
                                            class="flex-1 min-w-0 text-left cursor-pointer"
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <code class="font-mono text-[11px] font-semibold text-primary truncate" x-text="qv.token"></code>
                                                <span
                                                    class="text-[9px] uppercase px-1 rounded border border-border bg-muted text-muted-foreground"
                                                    x-text="qv.type === 'date' ? 'tanggal' : 'teks'"
                                                ></span>
                                            </div>
                                            <p class="text-[10px] text-muted-foreground" x-text="qv.label"></p>
                                        </button>
                                        <button
                                            type="button"
                                            @click="insertToken(qv.token)"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded border border-border bg-card text-primary hover:bg-primary hover:text-primary-foreground cursor-pointer shrink-0"
                                            title="Sisipkan"
                                        >
                                            <x-lucide-plus class="size-3.5" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 3: TABEL DINAMIS (REPEATER) --}}
                    <div x-show="activeVarTab === 'tabel'" x-cloak class="space-y-4">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground px-0.5">
                                Preset Tabel Lampiran (1-Klik Sisip Tabel)
                            </p>
                            <div class="space-y-1.5">
                                <template x-for="preset in repeaterPresets" :key="preset.group">
                                    <div class="rounded-lg border border-border bg-background p-2.5 space-y-1.5 hover:border-primary/50 transition-colors">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <p class="font-semibold text-foreground text-[11px]" x-text="preset.name"></p>
                                                <p class="text-[10px] text-muted-foreground" x-text="preset.description"></p>
                                            </div>
                                            <button
                                                type="button"
                                                @click="applyRepeaterPreset(preset)"
                                                class="text-[10px] text-primary hover:underline cursor-pointer shrink-0"
                                            >
                                                Ubah
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1.5 pt-0.5">
                                            <button
                                                type="button"
                                                @click="insertRepeaterTable(preset.group, preset.columns)"
                                                class="flex-1 inline-flex items-center justify-center gap-1 h-7 rounded-md bg-primary/10 text-primary hover:bg-primary hover:text-primary-foreground font-medium text-[11px] cursor-pointer transition-colors"
                                            >
                                                <x-lucide-table class="size-3" />
                                                <span>Sisipkan Tabel ke Kursor</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Custom Repeater Table Builder --}}
                        <div class="rounded-lg border border-border bg-background p-3 space-y-2.5">
                            <p class="font-semibold text-foreground text-xs">Builder Tabel Dinamis Kustom</p>

                            <div class="space-y-1">
                                <label class="text-[11px] font-medium text-foreground">Nama Grup Tabel</label>
                                <input
                                    type="text"
                                    x-model="repeaterGroup"
                                    placeholder="Mis. peserta / barang / rundown"
                                    class="w-full h-8 px-2.5 rounded-md border border-border bg-background font-mono text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-[11px] font-medium text-foreground">Kolom Tabel</label>
                                    <button
                                        type="button"
                                        @click="addRepeaterColumn()"
                                        class="inline-flex items-center gap-1 text-[11px] text-primary hover:underline cursor-pointer"
                                    >
                                        <x-lucide-plus class="size-3" />
                                        <span>Tambah Kolom</span>
                                    </button>
                                </div>

                                <div class="space-y-1.5">
                                    <template x-for="(col, idx) in repeaterColumns" :key="idx">
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="text"
                                                x-model="col.header"
                                                @input="syncColumnKey(col)"
                                                placeholder="Judul Kolom"
                                                class="flex-1 min-w-0 h-7 px-2 rounded border border-border bg-background text-[11px]"
                                            />
                                            <input
                                                type="text"
                                                x-model="col.key"
                                                placeholder="key_kolom"
                                                class="w-24 h-7 px-2 rounded border border-border bg-muted/40 font-mono text-[10px]"
                                            />
                                            <button
                                                type="button"
                                                @click="removeRepeaterColumn(idx)"
                                                :disabled="repeaterColumns.length <= 1"
                                                class="p-1 text-muted-foreground hover:text-destructive disabled:opacity-30 cursor-pointer"
                                                title="Hapus kolom"
                                            >
                                                <x-lucide-trash-2 class="size-3" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="insertRepeaterTable(sanitizedRepeaterGroup, repeaterColumns)"
                                :disabled="!sanitizedRepeaterGroup || repeaterColumns.length === 0"
                                class="w-full inline-flex items-center justify-center gap-1.5 h-8 rounded-md bg-primary text-primary-foreground font-medium text-xs hover:bg-primary/90 disabled:opacity-40 cursor-pointer transition-colors"
                            >
                                <x-lucide-table-2 class="size-3.5" />
                                <span>Sisipkan Tabel Kustom ke Kursor</span>
                            </button>

                            <div class="pt-1 border-t border-border space-y-1">
                                <p class="text-[10px] text-muted-foreground">Atau klik token per kolom (jika tabel sudah dibuat manual):</p>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="(col, idx) in repeaterColumns" :key="'tok-' + idx">
                                        <button
                                            type="button"
                                            @click="insertToken('${' + sanitizedRepeaterGroup + '.' + sanitizeSlug(col.key || col.header) + '}')"
                                            class="px-1.5 py-0.5 rounded border border-border bg-muted/60 hover:border-primary hover:text-primary font-mono text-[10px] cursor-pointer"
                                            x-text="'${' + sanitizedRepeaterGroup + '.' + sanitizeSlug(col.key || col.header) + '}'"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 4: VARIABEL TERDETEKSI PADA TEMPLATE --}}
                    <div x-show="activeVarTab === 'terdeteksi'" x-cloak class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-foreground">
                                Skema Terdeteksi (v{{ $template->version }})
                            </p>
                            <button
                                type="button"
                                wire:click="syncVariables"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1 text-[11px] text-primary hover:underline cursor-pointer"
                            >
                                <x-lucide-refresh-cw class="size-3" wire:loading.class="animate-spin" wire:target="syncVariables" />
                                <span>Pindai Ulang</span>
                            </button>
                        </div>

                        @php $schemaVars = $template->variables_schema ?? []; @endphp
                        @if (empty($schemaVars))
                            <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 space-y-1.5 text-[11px] text-amber-800 dark:text-amber-300">
                                <p class="font-semibold">Belum ada variabel terdeteksi</p>
                                <p class="leading-snug">
                                    Sisipkan minimal satu variabel (mis. <code class="font-mono">${nomor_surat}</code> atau <code class="font-mono">${tujuan_surat}</code>), tekan <strong>Ctrl+S</strong> di OnlyOffice, lalu klik <strong>Pindai Ulang</strong>.
                                </p>
                            </div>
                        @else
                            <div class="space-y-1.5">
                                @foreach ($schemaVars as $v)
                                    @if (($v['type'] ?? '') === 'repeater')
                                        <div class="rounded-lg border border-primary/30 bg-primary/5 p-2 space-y-1">
                                            <div class="flex items-center justify-between gap-1">
                                                <code class="font-mono font-semibold text-[11px] text-primary">${{ '{' . $v['name'] . '.*' . '}' }}</code>
                                                <span class="px-1.5 py-0.5 rounded text-[9px] uppercase font-semibold bg-primary/15 text-primary">
                                                    Tabel ({{ count($v['columns'] ?? []) }} kolom)
                                                </span>
                                            </div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($v['columns'] ?? [] as $col)
                                                    <code class="font-mono text-[10px] px-1 py-0.5 rounded bg-background border border-border text-foreground">
                                                        ${{ '{' . ($col['token'] ?? ($v['name'].'.'.$col['name'])) . '}' }}
                                                    </code>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $vType = $v['type'] ?? 'text';
                                            $badgeClass = match ($vType) {
                                                'system' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20',
                                                'date' => 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20',
                                                default => 'bg-muted text-muted-foreground border-border',
                                            };
                                            $typeLabel = match ($vType) {
                                                'system' => 'Sistem',
                                                'date' => 'Tanggal',
                                                default => 'Teks',
                                            };
                                        @endphp
                                        <div class="flex items-center justify-between gap-2 rounded-md border border-border bg-background px-2.5 py-1.5">
                                            <div class="min-w-0">
                                                <code class="font-mono text-[11px] font-semibold text-foreground block truncate">${{ '{' . $v['name'] . '}' }}</code>
                                                <span class="text-[10px] text-muted-foreground block truncate">{{ $v['label'] ?? $v['name'] }}</span>
                                            </div>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] uppercase font-semibold border shrink-0 {{ $badgeClass }}">
                                                {{ $typeLabel }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <p class="text-[10px] text-muted-foreground leading-relaxed pt-1 border-t border-border">
                            💡 Tekan <strong>Ctrl+S</strong> (atau ikon Simpan) di OnlyOffice setiap selesai mengubah template agar skema variabel otomatis diperbarui.
                        </p>
                    </div>
                </div>
            </aside>
        @endif
    </div>

    {{-- Modal Pengaturan Metadata Template --}}
    @if ($showSettingsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">Pengaturan Master Template</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Ubah nama, deskripsi, atau mode Kop Surat tanpa memuat ulang editor.</p>
                    </div>
                    <button type="button" wire:click="$set('showSettingsModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <form wire:submit="saveSettings" class="space-y-4">
                    <div>
                        <x-ui.label for="editTplName">Nama Template</x-ui.label>
                        <x-ui.input id="editTplName" wire:model="editName" class="mt-1.5" required />
                        <x-ui.form-error for="editName" />
                    </div>

                    <div>
                        <x-ui.label for="editTplDesc">Deskripsi Singkat (Opsional)</x-ui.label>
                        <x-ui.input id="editTplDesc" wire:model="editDescription" class="mt-1.5" />
                        <x-ui.form-error for="editDescription" />
                    </div>

                    <div class="space-y-2">
                        <x-ui.label>Mode Kop Surat</x-ui.label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => ! $editHasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => $editHasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="editHasEmbeddedKop" value="0" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Kop Resmi Workspace</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">Sistem otomatis menyisipkan kop surat workspace saat surat dibuat.</p>
                            </label>

                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $editHasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => ! $editHasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="editHasEmbeddedKop" value="1" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Kop Mandiri (di Word)</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">Kop sudah didesain sendiri di header dokumen Word ini.</p>
                            </label>
                        </div>
                    </div>

                    @if ($hasWordHeader)
                        <div class="rounded-lg border border-border bg-muted/40 p-3 flex items-start justify-between gap-3">
                            <div class="space-y-0.5 text-xs">
                                <p class="font-semibold text-foreground">Rapikan Margin Kop Otomatis</p>
                                <p class="text-[11px] text-muted-foreground leading-snug">
                                    Menyesuaikan margin atas master template agar isi surat mulai tepat di bawah kop header tanpa perlu Enter manual. Editor akan dimuat ulang.
                                </p>
                            </div>
                            <x-ui.button
                                type="button"
                                size="sm"
                                variant="outline"
                                wire:click="alignKopMargin"
                                wire:loading.attr="disabled"
                                wire:target="alignKopMargin"
                                class="shrink-0 gap-1.5"
                            >
                                <x-lucide-wand-sparkles class="size-3.5 text-primary" />
                                <span wire:loading.remove wire:target="alignKopMargin">Rapikan Margin</span>
                                <span wire:loading wire:target="alignKopMargin">Memproses...</span>
                            </x-ui.button>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showSettingsModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Pengaturan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('templateVariableBridge', ({ canEdit, systemCatalog, quickCustomVars, repeaterPresets }) => ({
        isToolbarMinimized: false,
        showVarPanel: Boolean(canEdit),
        activeVarTab: 'sistem',
        searchQuery: '',
        systemCatalog: systemCatalog || [],
        quickCustomVars: quickCustomVars || [],
        repeaterPresets: repeaterPresets || [],

        customVarInput: '',
        customVarType: 'text',

        repeaterGroup: 'peserta',
        repeaterColumns: [
            { header: 'No', key: 'no' },
            { header: 'Nama', key: 'nama' },
            { header: 'NIM', key: 'nim' },
            { header: 'Prodi', key: 'prodi' },
        ],

        pluginWindow: null,
        pluginReady: false,
        pendingRequests: {},
        requestSeq: 0,

        initBridge() {
            this.$watch('isToolbarMinimized', () => this.triggerResize(120));
            this.$watch('showVarPanel', () => this.triggerResize(150));
            this.$watch('$store.sidebar.open', () => this.triggerResize(250));

            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                const data = event.data;
                if (!data || data.source !== 'suratinaja-oo-plugin') return;

                if (data.type === 'ready') {
                    this.pluginWindow = event.source;
                    this.pluginReady = true;
                    return;
                }

                if (data.type === 'insert_ack' && data.id && this.pendingRequests[data.id]) {
                    const resolver = this.pendingRequests[data.id];
                    delete this.pendingRequests[data.id];
                    resolver(Boolean(data.ok), data.error || null);
                }
            });
        },

        triggerResize(delayMs = 120) {
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, delayMs);
        },

        get filteredSystemCatalog() {
            const q = (this.searchQuery || '').trim().toLowerCase();
            if (!q) return this.systemCatalog;
            return this.systemCatalog
                .map((group) => ({
                    category: group.category,
                    items: group.items.filter((item) =>
                        item.name.toLowerCase().includes(q)
                        || item.token.toLowerCase().includes(q)
                        || item.label.toLowerCase().includes(q)
                        || item.description.toLowerCase().includes(q)
                    ),
                }))
                .filter((g) => g.items.length > 0);
        },

        sanitizeSlug(raw) {
            return String(raw || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        },

        get customVarToken() {
            let slug = this.sanitizeSlug(this.customVarInput);
            if (!slug) return '';
            if (this.customVarType === 'date' && !slug.startsWith('tgl_') && !slug.startsWith('tanggal_')) {
                slug = 'tanggal_' + slug;
            }
            return '${' + slug + '}';
        },

        get sanitizedRepeaterGroup() {
            return this.sanitizeSlug(this.repeaterGroup) || 'lampiran';
        },

        syncColumnKey(col) {
            col.key = this.sanitizeSlug(col.header);
        },

        addRepeaterColumn() {
            this.repeaterColumns.push({ header: 'Kolom Baru', key: 'kolom_' + (this.repeaterColumns.length + 1) });
        },

        removeRepeaterColumn(idx) {
            if (this.repeaterColumns.length > 1) {
                this.repeaterColumns.splice(idx, 1);
            }
        },

        applyRepeaterPreset(preset) {
            this.repeaterGroup = preset.group;
            this.repeaterColumns = preset.columns.map((c) => ({ header: c.header, key: c.key }));
        },

        sendToPlugin(payload, timeoutMs = 500) {
            return new Promise((resolve) => {
                if (!this.pluginReady || !this.pluginWindow) {
                    resolve(false);
                    return;
                }
                const id = 'req_' + (++this.requestSeq) + '_' + Date.now();
                const timer = setTimeout(() => {
                    if (this.pendingRequests[id]) {
                        delete this.pendingRequests[id];
                        resolve(false);
                    }
                }, timeoutMs);

                this.pendingRequests[id] = (ok) => {
                    clearTimeout(timer);
                    resolve(ok);
                };

                try {
                    this.pluginWindow.postMessage(
                        Object.assign({ source: 'suratinaja-host', id }, payload),
                        window.location.origin
                    );
                } catch (e) {
                    clearTimeout(timer);
                    delete this.pendingRequests[id];
                    resolve(false);
                }
            });
        },

        async insertToken(token) {
            if (!token) return;
            const inserted = await this.sendToPlugin({ type: 'insert_text', text: token });
            if (inserted) {
                this.showToast('success', 'Variabel disisipkan', token + ' ditambahkan di posisi kursor.');
                return;
            }
            await this.copyToClipboard(token);
            this.showToast('info', 'Disalin ke Clipboard', token + ' disalin — klik dokumen lalu tekan Cmd+V / Ctrl+V.');
        },

        async insertCustomVariable() {
            const token = this.customVarToken;
            if (!token) return;
            await this.insertToken(token);
            this.customVarInput = '';
        },

        async insertRepeaterTable(group, columns) {
            const cleanGroup = this.sanitizeSlug(group) || 'lampiran';
            const cleanCols = (columns || [])
                .map((c, i) => ({
                    header: String(c.header || ('Kolom ' + (i + 1))).trim(),
                    key: this.sanitizeSlug(c.key || c.header) || ('kolom_' + (i + 1)),
                }))
                .filter((c) => c.key !== '');

            if (cleanCols.length === 0) return;

            const headers = cleanCols.map((c) => c.header);
            const tokens = cleanCols.map((c) => '${' + cleanGroup + '.' + c.key + '}');

            const inserted = await this.sendToPlugin({ type: 'insert_table', headers, tokens }, 1200);
            if (inserted) {
                this.showToast('success', 'Tabel dinamis disisipkan', 'Tabel ${' + cleanGroup + '.*} (' + cleanCols.length + ' kolom) ditambahkan di posisi kursor.');
                return;
            }

            await this.copyToClipboard(tokens.join('\t'));
            this.showToast('info', 'Token tabel disalin', 'Baris token ' + tokens.join(' | ') + ' disalin ke clipboard.');
        },

        async copyToken(token) {
            if (!token) return;
            await this.copyToClipboard(token);
            this.showToast('info', 'Disalin ke Clipboard', token);
        },

        async copyToClipboard(text) {
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                    return;
                }
            } catch (e) {}
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
        },

        showToast(type, title, description = '') {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: [{ type, title, description }],
            }));
        },
    }));

    Alpine.data('onlyOfficeTemplateDocEditor', ({ apiUrl, config }) => ({
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
            const isLocalApi = apiUrl.includes('127.0.0.1') || apiUrl.includes('localhost');
            const isPublicPage = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

            if (isLocalApi && isPublicPage) {
                this.error = 'Konfigurasi ONLYOFFICE_URL belum diatur ke domain publik Dokploy (saat ini: ' + apiUrl + '). Silakan atur ONLYOFFICE_URL=https://office.domainanda.com di Environment Dokploy dan pastikan domain diarahkan ke service onlyoffice port 80.';
            } else {
                this.error = 'Tidak dapat menghubungi server OnlyOffice di (' + apiUrl + '). Pastikan container OnlyOffice Document Server sedang berjalan dan domain dapat diakses.';
            }
        },

        mountEditor() {
            try {
                this.loading = false;
                const container = document.getElementById('onlyoffice-template-frame');
                if (!container || !window.DocsAPI) return;

                container.innerHTML = '';

                const editorConfig = Object.assign({}, config, {
                    width: '100%',
                    height: '100%',
                });
                editorConfig.events = editorConfig.events || {};

                const origOnAppReady = editorConfig.events.onAppReady;
                editorConfig.events.onAppReady = () => {
                    this.loading = false;
                    if (origOnAppReady) origOnAppReady();
                };

                editorConfig.events.onError = (event) => {
                    console.error('OnlyOffice Template Editor error:', event);
                };

                this.editorInstance = new window.DocsAPI.DocEditor('onlyoffice-template-frame', editorConfig);
            } catch (e) {
                this.loading = false;
                this.error = e.message || 'Terjadi kesalahan saat memuat editor template.';
            }
        },
    }));
});
</script>
@endpush
