<div>
    {{-- 1. MODAL SPOTLIGHT (COMMAND PALETTE) --}}
    @if ($isOpen && ! $isPreviewOpen)
        <div
            x-cloak
            class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-start justify-center pt-[8vh] sm:pt-[12vh] p-3 sm:p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                x-data="{
                    selectedIndex: 0,
                    totalItems: 0,
                    init() {
                        this.updateTotal();
                        this.$watch('selectedIndex', () => this.scrollToSelected());
                    },
                    updateTotal() {
                        this.$nextTick(() => {
                            const items = this.$refs.resultsList?.querySelectorAll('[data-search-item]');
                            this.totalItems = items ? items.length : 0;
                            if (this.selectedIndex >= this.totalItems) {
                                this.selectedIndex = Math.max(0, this.totalItems - 1);
                            }
                        });
                    },
                    selectNext() {
                        if (this.totalItems > 0) {
                            this.selectedIndex = (this.selectedIndex + 1) % this.totalItems;
                        }
                    },
                    selectPrev() {
                        if (this.totalItems > 0) {
                            this.selectedIndex = (this.selectedIndex - 1 + this.totalItems) % this.totalItems;
                        }
                    },
                    scrollToSelected() {
                        this.$nextTick(() => {
                            const el = this.$refs.resultsList?.querySelector(`[data-index='${this.selectedIndex}']`);
                            if (el) {
                                el.scrollIntoView({ block: 'nearest' });
                            }
                        });
                    },
                    executeSelected() {
                        const el = this.$refs.resultsList?.querySelector(`[data-index='${this.selectedIndex}']`);
                        if (el) {
                            const mainLink = el.querySelector('a[data-main-link]');
                            if (mainLink) {
                                mainLink.click();
                            } else if (el.tagName === 'A') {
                                el.click();
                            } else {
                                const anyLink = el.querySelector('a, button');
                                if (anyLink) anyLink.click();
                            }
                        }
                    }
                }"
                x-init="updateTotal()"
                @keydown.arrow-down.prevent="selectNext()"
                @keydown.arrow-up.prevent="selectPrev()"
                @keydown.enter.prevent="executeSelected()"
                @keydown.escape.window="$wire.closeSpotlight()"
                @click.outside="$wire.closeSpotlight()"
                class="relative w-full max-w-2xl bg-card text-foreground rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col max-h-[78vh]"
            >
                {{-- Search Bar Header --}}
                <div class="flex items-center px-4 py-3.5 border-b border-border bg-background/80 shrink-0 gap-3">
                    <x-lucide-search class="size-5 text-muted-foreground shrink-0" />
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        x-ref="searchInput"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="Cari surat keluar, surat masuk, amplop, bulk, atau menu..."
                        class="w-full bg-transparent text-sm sm:text-base font-medium placeholder:text-muted-foreground/60 focus:outline-none"
                        @input="updateTotal(); selectedIndex = 0"
                    />
                    <div class="flex items-center gap-2 shrink-0">
                        <span wire:loading wire:target="search" class="inline-flex items-center">
                            <x-lucide-loader-2 class="size-4 animate-spin text-primary" />
                        </span>
                        <button
                            type="button"
                            wire:click="closeSpotlight"
                            class="p-1 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer"
                            title="Tutup (Esc)"
                        >
                            <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono text-muted-foreground bg-muted border border-border rounded">
                                ESC
                            </kbd>
                            <x-lucide-x class="size-4 sm:hidden" />
                        </button>
                    </div>
                </div>

                {{-- Scrollable Results Body --}}
                <div x-ref="resultsList" class="flex-1 min-h-0 overflow-y-auto divide-y divide-border/60 p-2 space-y-3">
                    @php
                        $itemIndex = 0;
                        $queryLength = strlen(trim($search));
                        $totalResults = $letters->count() + $suratMasuks->count() + $envelopeTemplates->count() + $bulkJobs->count() + ($queryLength >= 2 ? $quickActions->count() : 0);
                    @endphp

                    {{-- A. State Input Kurang dari 2 Karakter: Aksi Cepat & Navigasi --}}
                    @if ($queryLength < 2)
                        <div class="space-y-1">
                            <div class="px-3 py-1.5 text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                <span>Aksi Cepat &bull; Navigasi Menu</span>
                                <span class="text-[10px] font-normal lowercase opacity-70">Ketik minimal 2 karakter untuk mencari dokumen</span>
                            </div>
                            <div class="space-y-0.5">
                                @foreach ($quickActions as $action)
                                    @php $currIdx = $itemIndex++; @endphp
                                    <a
                                        href="{{ $action['url'] }}"
                                        data-search-item
                                        data-main-link
                                        data-index="{{ $currIdx }}"
                                        @mouseenter="selectedIndex = {{ $currIdx }}"
                                        @class([
                                            'flex items-center justify-between px-3 py-2 rounded-xl text-xs sm:text-sm transition-colors group cursor-pointer',
                                            'bg-accent text-accent-foreground font-medium' => false,
                                        ])
                                        :class="selectedIndex === {{ $currIdx }} ? 'bg-accent text-accent-foreground font-medium' : 'hover:bg-muted/50 text-foreground'"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="p-2 rounded-lg bg-muted text-muted-foreground group-hover:text-primary transition-colors shrink-0">
                                                <x-dynamic-component :component="'lucide-' . $action['icon']" class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-medium truncate">{{ $action['label'] }}</p>
                                                <p class="text-[11px] text-muted-foreground truncate">{{ $action['description'] }}</p>
                                            </div>
                                        </div>
                                        <x-lucide-arrow-right class="size-3.5 text-muted-foreground/60 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2" />
                                    </a>
                                @endforeach
                            </div>
                        </div>

                    {{-- B. State Input >= 2 Karakter: Hasil Pencarian Kategori --}}
                    @elseif ($totalResults === 0)
                        {{-- Empty State Friendly --}}
                        <div class="py-12 text-center text-muted-foreground space-y-2">
                            <div class="size-10 rounded-full bg-muted/60 flex items-center justify-center mx-auto text-muted-foreground">
                                <x-lucide-search-x class="size-5" />
                            </div>
                            <p class="text-sm font-semibold text-foreground">Tidak ada hasil untuk "{{ $search }}"</p>
                            <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                                Coba periksa kembali ejaan nomor surat, nama instansi penerima, atau kata kunci perihal.
                            </p>
                        </div>
                    @else
                        {{-- 1. Kategori: Surat Keluar --}}
                        @if ($letters->count() > 0)
                            <div class="space-y-1">
                                <div class="px-3 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-send class="size-3 text-sky-500" />
                                        <span>Surat Keluar ({{ $letters->count() }})</span>
                                    </span>
                                    <a href="{{ route('letters.index', ['search' => $search]) }}" class="text-[11px] text-primary hover:underline font-medium lowercase">
                                        Lihat semua di Daftar Surat &rarr;
                                    </a>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($letters as $letter)
                                        @php
                                            $currIdx = $itemIndex++;
                                            $penerima = $letter->field_values['penerima'] ?? ($letter->field_values['instansi'] ?? '—');
                                            $nomorFormatted = $letter->formatted_number ?? 'Draf (Belum Bernomor)';
                                        @endphp
                                        <div
                                            data-search-item
                                            data-index="{{ $currIdx }}"
                                            @mouseenter="selectedIndex = {{ $currIdx }}"
                                            :class="selectedIndex === {{ $currIdx }} ? 'bg-accent/80 text-accent-foreground ring-1 ring-primary/30' : 'hover:bg-muted/40'"
                                            class="relative flex items-center justify-between p-2.5 rounded-xl border border-border/50 bg-background/50 transition-colors gap-3"
                                        >
                                            <a href="{{ route('letters.show', $letter->id) }}" data-main-link class="absolute inset-0 z-0 rounded-xl" title="Buka Detail Surat"></a>

                                            <div class="min-w-0 flex-1 relative z-10 pointer-events-none">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-mono text-xs font-bold text-primary">
                                                        {{ $nomorFormatted }}
                                                    </span>
                                                    <x-ui.status-badge :status="$letter->status" />
                                                </div>
                                                <p class="font-semibold text-xs sm:text-sm text-foreground truncate mt-0.5">
                                                    {{ $letter->perihal ?: 'Tanpa Perihal' }}
                                                </p>
                                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                                    <span>{{ $letter->letter_date?->format('d M Y') }}</span>
                                                    <span>&bull;</span>
                                                    <span class="truncate">Ke: {{ $penerima }}</span>
                                                </div>
                                            </div>

                                            {{-- Quick Actions Right Side --}}
                                            <div class="flex items-center gap-1.5 relative z-20 shrink-0">
                                                <button
                                                    type="button"
                                                    wire:click.stop="openPreview('letter_pdf', {{ $letter->id }})"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition cursor-pointer"
                                                    title="Pratinjau PDF Surat (Tanpa Pindah Halaman)"
                                                >
                                                    <x-lucide-file-text class="size-3" />
                                                    <span class="hidden sm:inline">Pratinjau</span>
                                                </button>

                                                @if ($canAmplopPreview)
                                                    <button
                                                        type="button"
                                                        wire:click.stop="openPreview('envelope_pdf', {{ $letter->id }})"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
                                                        title="Pratinjau Kop Amplop Otomatis"
                                                    >
                                                        <x-lucide-mail class="size-3" />
                                                        <span class="hidden sm:inline">Amplop</span>
                                                    </button>
                                                @endif

                                                <a
                                                    href="{{ route('letters.show', $letter->id) }}"
                                                    class="p-1.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition"
                                                    title="Buka Detail"
                                                >
                                                    <x-lucide-chevron-right class="size-4" />
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- 2. Kategori: Surat Masuk --}}
                        @if ($suratMasuks->count() > 0)
                            <div class="space-y-1">
                                <div class="px-3 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-inbox class="size-3 text-amber-500" />
                                        <span>Surat Masuk ({{ $suratMasuks->count() }})</span>
                                    </span>
                                    <a href="{{ route('surat-masuk.index', ['search' => $search]) }}" class="text-[11px] text-primary hover:underline font-medium lowercase">
                                        Lihat semua di Surat Masuk &rarr;
                                    </a>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($suratMasuks as $sm)
                                        @php $currIdx = $itemIndex++; @endphp
                                        <div
                                            data-search-item
                                            data-index="{{ $currIdx }}"
                                            @mouseenter="selectedIndex = {{ $currIdx }}"
                                            :class="selectedIndex === {{ $currIdx }} ? 'bg-accent/80 text-accent-foreground ring-1 ring-primary/30' : 'hover:bg-muted/40'"
                                            class="relative flex items-center justify-between p-2.5 rounded-xl border border-border/50 bg-background/50 transition-colors gap-3"
                                        >
                                            <a href="{{ route('surat-masuk.show', $sm->id) }}" data-main-link class="absolute inset-0 z-0 rounded-xl" title="Buka Detail Surat Masuk"></a>

                                            <div class="min-w-0 flex-1 relative z-10 pointer-events-none">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-mono text-xs font-bold text-foreground">
                                                        {{ $sm->nomor_agenda }}
                                                    </span>
                                                    <span class="text-[11px] text-muted-foreground truncate max-w-[180px]">
                                                        ({{ $sm->nomor_surat_pengirim }})
                                                    </span>
                                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold {{ $sm->sifat_surat?->badgeClasses() }}">
                                                        {{ $sm->sifat_surat?->label() }}
                                                    </span>
                                                </div>
                                                <p class="font-semibold text-xs sm:text-sm text-foreground truncate mt-0.5">
                                                    {{ $sm->perihal ?: 'Tanpa Perihal' }}
                                                </p>
                                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                                    <span class="font-medium text-foreground">Dari: {{ $sm->pengirim }}</span>
                                                    <span>&bull;</span>
                                                    <span>Terima: {{ $sm->tanggal_diterima?->format('d/m/Y') }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-1.5 relative z-20 shrink-0">
                                                @if (! empty($sm->file_path))
                                                    <button
                                                        type="button"
                                                        wire:click.stop="openPreview('surat_masuk', {{ $sm->id }})"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition cursor-pointer"
                                                        title="Pratinjau Berkas Lampiran"
                                                    >
                                                        <x-lucide-paperclip class="size-3" />
                                                        <span class="hidden sm:inline">Berkas</span>
                                                    </button>
                                                @endif

                                                <a
                                                    href="{{ route('surat-masuk.show', $sm->id) }}"
                                                    class="p-1.5 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition"
                                                    title="Buka Detail"
                                                >
                                                    <x-lucide-chevron-right class="size-4" />
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- 3. Kategori: Master Template Amplop & Bulk Batch --}}
                        @if ($envelopeTemplates->count() > 0 || $bulkJobs->count() > 0)
                            <div class="space-y-1">
                                <div class="px-3 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-layers class="size-3 text-indigo-500" />
                                        <span>Template Amplop &amp; Bulk Batch</span>
                                    </span>
                                </div>
                                <div class="space-y-1">
                                    {{-- Template Amplop --}}
                                    @foreach ($envelopeTemplates as $tpl)
                                        @php $currIdx = $itemIndex++; @endphp
                                        <a
                                            href="{{ route('settings.envelopes') }}"
                                            data-search-item
                                            data-main-link
                                            data-index="{{ $currIdx }}"
                                            @mouseenter="selectedIndex = {{ $currIdx }}"
                                            :class="selectedIndex === {{ $currIdx }} ? 'bg-accent/80 text-accent-foreground ring-1 ring-primary/30' : 'hover:bg-muted/40'"
                                            class="flex items-center justify-between p-2.5 rounded-xl border border-border/50 bg-background/50 transition-colors gap-3 cursor-pointer group"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <x-lucide-mail class="size-3.5 text-indigo-600 shrink-0" />
                                                    <span class="font-semibold text-xs sm:text-sm text-foreground truncate">
                                                        {{ $tpl->name }}
                                                    </span>
                                                    <span class="px-1.5 py-0.2 rounded text-[10px] uppercase font-bold bg-muted text-muted-foreground">
                                                        {{ $tpl->preset_size }}
                                                    </span>
                                                    @if ($tpl->is_default)
                                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                                            Default
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                                    Master Template Amplop Word (.docx) &bull; Klik untuk kelola di Settings
                                                </p>
                                            </div>
                                            <x-lucide-arrow-right class="size-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                                        </a>
                                    @endforeach

                                    {{-- Bulk Jobs --}}
                                    @foreach ($bulkJobs as $job)
                                        @php
                                            $currIdx = $itemIndex++;
                                            $isDone = in_array($job->status, [\App\Enums\BulkJobStatus::Done, 'done', 'completed'], true) || ($job->status?->value ?? '') === 'done';
                                        @endphp
                                        <div
                                            data-search-item
                                            data-index="{{ $currIdx }}"
                                            @mouseenter="selectedIndex = {{ $currIdx }}"
                                            :class="selectedIndex === {{ $currIdx }} ? 'bg-accent/80 text-accent-foreground ring-1 ring-primary/30' : 'hover:bg-muted/40'"
                                            class="relative flex items-center justify-between p-2.5 rounded-xl border border-border/50 bg-background/50 transition-colors gap-3"
                                        >
                                            <a href="{{ route('letters.index', ['bulk_job_id' => $job->id]) }}" data-main-link class="absolute inset-0 z-0 rounded-xl" title="Buka Daftar Surat Batch"></a>

                                            <div class="min-w-0 flex-1 relative z-10 pointer-events-none">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs font-bold text-muted-foreground">
                                                        #{{ substr($job->id, 0, 8) }}
                                                    </span>
                                                    <span class="font-semibold text-xs text-foreground truncate">
                                                        {{ $job->template?->name ?? 'Mailing Massal' }}
                                                    </span>
                                                    <span class="font-mono text-[11px] text-primary">
                                                        ({{ $job->processed_count }}/{{ $job->recipient_count }} surat)
                                                    </span>
                                                </div>
                                                <p class="text-xs text-muted-foreground truncate mt-0.5">
                                                    {{ $job->perihal ?? '—' }} &bull; {{ $job->created_at->format('d/m/Y') }}
                                                </p>
                                            </div>

                                            @if ($isDone)
                                                <div class="flex items-center gap-1.5 relative z-20 shrink-0">
                                                    <button
                                                        type="button"
                                                        wire:click.stop="openPreview('bulk_pdf', '{{ $job->id }}')"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition cursor-pointer"
                                                        title="Pratinjau Seluruh Surat dalam 1 PDF"
                                                    >
                                                        <x-lucide-eye class="size-3" />
                                                        <span class="hidden sm:inline">1 PDF</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click.stop="openPreview('bulk_envelopes', '{{ $job->id }}')"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
                                                        title="Pratinjau Seluruh Kop Amplop Batch"
                                                    >
                                                        <x-lucide-mail class="size-3" />
                                                        <span class="hidden sm:inline">Amplop</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- 4. Kategori: Navigasi Cepat (Quick Jump) --}}
                        @if ($quickActions->count() > 0)
                            <div class="space-y-1">
                                <div class="px-3 py-1 text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-compass class="size-3 text-muted-foreground" />
                                        <span>Navigasi Menu ({{ $quickActions->count() }})</span>
                                    </span>
                                </div>
                                <div class="space-y-0.5">
                                    @foreach ($quickActions as $action)
                                        @php $currIdx = $itemIndex++; @endphp
                                        <a
                                            href="{{ $action['url'] }}"
                                            data-search-item
                                            data-main-link
                                            data-index="{{ $currIdx }}"
                                            @mouseenter="selectedIndex = {{ $currIdx }}"
                                            :class="selectedIndex === {{ $currIdx }} ? 'bg-accent text-accent-foreground font-medium' : 'hover:bg-muted/50 text-foreground'"
                                            class="flex items-center justify-between px-3 py-2 rounded-xl text-xs sm:text-sm transition-colors group cursor-pointer"
                                        >
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="p-1.5 rounded-lg bg-muted text-muted-foreground group-hover:text-primary transition-colors shrink-0">
                                                    <x-dynamic-component :component="'lucide-' . $action['icon']" class="size-3.5" />
                                                </div>
                                                <span class="font-medium truncate">{{ $action['label'] }}</span>
                                            </div>
                                            <x-lucide-arrow-right class="size-3.5 text-muted-foreground/60 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 ml-2" />
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Footer Info & Shortcuts --}}
                <div class="shrink-0 px-4 py-2.5 border-t border-border bg-muted/30 text-[11px] text-muted-foreground flex items-center justify-between">
                    <div class="hidden sm:flex items-center gap-3.5">
                        <span class="inline-flex items-center gap-1">
                            <kbd class="font-mono bg-background px-1.5 py-0.5 rounded border border-border text-[10px]">↑</kbd>
                            <kbd class="font-mono bg-background px-1.5 py-0.5 rounded border border-border text-[10px]">↓</kbd>
                            <span>Navigasi</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <kbd class="font-mono bg-background px-1.5 py-0.5 rounded border border-border text-[10px]">↵</kbd>
                            <span>Buka</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <kbd class="font-mono bg-background px-1.5 py-0.5 rounded border border-border text-[10px]">ESC</kbd>
                            <span>Tutup</span>
                        </span>
                    </div>
                    <div class="truncate text-right w-full sm:w-auto">
                        <span>Workspace: <strong class="text-foreground font-semibold">{{ auth()->user()?->activeWorkspace()?->name ?? 'SuratinAja' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. MODAL PRATINJAU PDF DOKUMEN / AMPLOP (ANTI-TUMPUK PREVIEW-FIRST) --}}
    @if ($isPreviewOpen && ! empty($previewUrl))
        <div
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 sm:p-4 backdrop-blur-xs"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-5xl rounded-2xl border border-border bg-background p-4 sm:p-5 shadow-2xl flex flex-col h-[88vh]">
                {{-- Header Modal Pratinjau --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-border pb-3 shrink-0 gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="p-2 rounded-xl bg-primary/10 text-primary shrink-0">
                            @if ($previewType === 'envelope_pdf' || $previewType === 'bulk_envelopes')
                                <x-lucide-mail class="size-5" />
                            @else
                                <x-lucide-file-text class="size-5" />
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm sm:text-base font-bold text-foreground truncate">
                                {{ $previewTitle }}
                            </h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5 flex items-center gap-2">
                                <span>Pratinjau Dokumen Siap Cetak</span>
                                <span>&bull;</span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">Preview-First</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 justify-end shrink-0">
                        @if (! empty($previewDownloadUrl))
                            <x-ui.button
                                as="a"
                                href="{{ $previewDownloadUrl }}"
                                download
                                size="sm"
                                class="gap-1.5 text-xs font-semibold"
                            >
                                <x-lucide-download class="size-3.5" />
                                <span>Unduh PDF</span>
                            </x-ui.button>
                        @endif

                        @if (! empty($previewExternalUrl))
                            <x-ui.button
                                as="a"
                                href="{{ $previewExternalUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs hidden sm:inline-flex"
                                title="Buka PDF di tab baru browser untuk cetak langsung"
                            >
                                <x-lucide-external-link class="size-3.5" />
                                <span>Tab Baru</span>
                            </x-ui.button>
                        @endif

                        <x-ui.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            wire:click="closePreview"
                            class="text-xs"
                        >
                            Tutup
                        </x-ui.button>
                    </div>
                </div>

                {{-- Kanvas Iframe PDF --}}
                <div class="flex-1 min-h-0 bg-muted/20 rounded-xl overflow-hidden border border-border mt-3 relative">
                    <iframe
                        src="{{ $previewUrl }}"
                        class="w-full h-full border-0"
                        title="Pratinjau PDF"
                    ></iframe>
                </div>
            </div>
        </div>
    @endif
</div>
