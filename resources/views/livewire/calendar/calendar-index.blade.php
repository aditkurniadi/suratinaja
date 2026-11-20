<div class="space-y-4">
    {{-- Top Controls Bar (Notion Calendar Style) --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
        {{-- Sisi Kiri: Label Kalender, Judul Bulan, & Navigasi < Hari Ini > --}}
        <div class="flex items-center gap-3 flex-wrap">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/70 text-xs font-semibold text-foreground border border-border/50">
                <x-lucide-calendar class="size-3.5 text-primary" />
                <span>Kalender</span>
            </div>

            <h2 class="text-base sm:text-lg font-bold text-foreground capitalize tracking-tight">
                {{ $currentMonth->translatedFormat('F Y') }}
            </h2>

            {{-- Navigasi Terpadu < Hari Ini > ala Notion --}}
            <div class="inline-flex items-center border border-border rounded-lg bg-card shadow-2xs overflow-hidden">
                <button
                    type="button"
                    wire:click="prevMonth"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer border-r border-border"
                    title="Bulan Sebelumnya"
                >
                    <x-lucide-chevron-left class="size-4" />
                </button>
                <button
                    type="button"
                    wire:click="goToToday"
                    class="px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted transition-colors cursor-pointer border-r border-border"
                >
                    Hari Ini
                </button>
                <button
                    type="button"
                    wire:click="nextMonth"
                    class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                    title="Bulan Berikutnya"
                >
                    <x-lucide-chevron-right class="size-4" />
                </button>
            </div>
        </div>

        {{-- Sisi Kanan: Filter Cepat & Tombol Buat Surat --}}
        <div class="flex items-center gap-2.5 flex-wrap">
            {{-- Filter Tipe Surat --}}
            <div class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5 text-xs">
                <button
                    type="button"
                    wire:click="setFilterType('all')"
                    @class([
                        'px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer',
                        'bg-background text-foreground shadow-2xs font-semibold' => $filterType === 'all',
                        'text-muted-foreground hover:text-foreground' => $filterType !== 'all',
                    ])
                >
                    Semua
                </button>
                <button
                    type="button"
                    wire:click="setFilterType('surat_keluar')"
                    @class([
                        'px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                        'bg-background text-sky-700 dark:text-sky-300 shadow-2xs font-semibold' => $filterType === 'surat_keluar',
                        'text-muted-foreground hover:text-foreground' => $filterType !== 'surat_keluar',
                    ])
                >
                    <span class="size-1.5 rounded-full bg-sky-500"></span>
                    <span>Keluar</span>
                </button>
                <button
                    type="button"
                    wire:click="setFilterType('surat_masuk')"
                    @class([
                        'px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                        'bg-background text-emerald-700 dark:text-emerald-300 shadow-2xs font-semibold' => $filterType === 'surat_masuk',
                        'text-muted-foreground hover:text-foreground' => $filterType !== 'surat_masuk',
                    ])
                >
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    <span>Masuk</span>
                </button>
            </div>

            {{-- Filter Acuan Tanggal --}}
            <div class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5 text-xs">
                <button
                    type="button"
                    wire:click="setDateReference('letter_date')"
                    @class([
                        'px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer',
                        'bg-background text-foreground shadow-2xs font-semibold' => $dateReference === 'letter_date',
                        'text-muted-foreground hover:text-foreground' => $dateReference !== 'letter_date',
                    ])
                    title="Berdasarkan Tanggal Surat"
                >
                    Tgl Surat
                </button>
                <button
                    type="button"
                    wire:click="setDateReference('event_date')"
                    @class([
                        'px-2.5 py-1 rounded-md font-medium transition-colors cursor-pointer',
                        'bg-background text-foreground shadow-2xs font-semibold' => $dateReference === 'event_date',
                        'text-muted-foreground hover:text-foreground' => $dateReference !== 'event_date',
                    ])
                    title="Berdasarkan Tanggal Acara / Deadline Surat Masuk"
                >
                    Tgl Acara
                </button>
            </div>

            {{-- Tombol Tambah Surat (Hanya jika punya hak akses) --}}
            @if ($canCreateLetter)
                <x-ui.button
                    as="a"
                    href="{{ route('letters.create') }}"
                    size="sm"
                    class="gap-1.5 font-medium text-xs h-8"
                >
                    <x-lucide-plus class="size-3.5" />
                    <span>Buat Surat</span>
                </x-ui.button>
            @endif
        </div>
    </div>

    {{-- Indikator Mode Acuan Tanggal Aktif --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs px-3.5 py-2 rounded-xl bg-muted/40 border border-border/60">
        <div class="flex items-center gap-2 text-muted-foreground">
            <x-lucide-info class="size-3.5 text-primary shrink-0" />
            @if ($dateReference === 'event_date')
                <span>Menampilkan jadwal berdasarkan <strong>Tanggal Kegiatan / Deadline Acara</strong> (Surat Keluar: Tanggal Kegiatan, Surat Masuk: Deadline/Acara).</span>
            @else
                <span>Menampilkan jadwal berdasarkan <strong>Tanggal Surat</strong> (Surat Keluar: Tanggal Surat Resmi, Surat Masuk: Tanggal Surat Diterima).</span>
            @endif
        </div>
        <span class="text-[11px] text-muted-foreground/80 hidden md:inline">
            Arahkan kursor &amp; klik tanda <strong>+</strong> pada sel tanggal untuk membuat surat langsung.
        </span>
    </div>

    {{-- Grid Kalender 7 Kolom Gaya Notion (Desktop/Tablet md:) --}}
    <div class="hidden md:block rounded-xl border border-border bg-card shadow-xs overflow-hidden">
        {{-- Header Nama Hari (Sun -> Sat) --}}
        <div class="grid grid-cols-7 border-b border-border bg-muted/30 text-center text-xs font-semibold text-muted-foreground">
            <div class="py-2.5 border-r border-border/60 text-rose-600 dark:text-rose-400">Min</div>
            <div class="py-2.5 border-r border-border/60">Sen</div>
            <div class="py-2.5 border-r border-border/60">Sel</div>
            <div class="py-2.5 border-r border-border/60">Rab</div>
            <div class="py-2.5 border-r border-border/60">Kam</div>
            <div class="py-2.5 border-r border-border/60">Jum</div>
            <div class="py-2.5 text-amber-600 dark:text-amber-400">Sab</div>
        </div>

        {{-- Sel Tanggal Bulanan --}}
        <div class="grid grid-cols-7 divide-y divide-border">
            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    <div @class([
                        'min-h-[135px] sm:min-h-[155px] p-1.5 sm:p-2 flex flex-col justify-between transition-colors border-r border-border/60 last:border-r-0 group relative',
                        'bg-card text-foreground' => $day['isCurrentMonth'],
                        'bg-muted/15 text-muted-foreground/60' => ! $day['isCurrentMonth'],
                        'bg-primary/5 ring-1 ring-inset ring-primary/20' => $day['isToday'],
                    ])>
                        {{-- Header Sel Tanggal Ala Notion (Angka di Kanan Atas, Tombol + di Kiri saat Hover) --}}
                        <div class="flex items-center justify-between flex-row-reverse mb-1.5">
                            <div>
                                @if ($day['isToday'])
                                    {{-- Penanda Hari Ini Ala Notion (Coral Red Circle) --}}
                                    <div class="size-6 flex items-center justify-center rounded-full text-xs font-bold bg-[#eb5757] text-white shadow-2xs">
                                        {{ $day['day'] }}
                                    </div>
                                @else
                                    <span @class([
                                        'text-xs font-medium',
                                        'font-bold text-foreground' => $day['day'] === 1 && $day['isCurrentMonth'],
                                        'text-foreground' => $day['isCurrentMonth'] && $day['day'] !== 1,
                                        'text-muted-foreground/40' => ! $day['isCurrentMonth'],
                                    ])>
                                        {{ $day['dayLabel'] }}
                                    </span>
                                @endif
                            </div>

                            {{-- Tombol Cepat Tambah Surat pada Hover & Popover Pilihan --}}
                            @if ($canCreateLetter || $canCreateSuratMasuk)
                                @php
                                    $dayCarbon = \Carbon\Carbon::parse($day['date']);
                                    $dateFormattedShort = $dayCarbon->translatedFormat('d M');
                                    $dateFormattedFull = $dayCarbon->translatedFormat('d M Y');
                                    $tooltipText = $dateReference === 'event_date'
                                        ? "Jadwalkan kegiatan pada {$dateFormattedFull}"
                                        : "Buat surat tertanggal {$dateFormattedFull}";
                                @endphp
                                <div x-data="{ openMenu: false }" class="relative">
                                    <button
                                        type="button"
                                        @click="openMenu = !openMenu"
                                        class="opacity-0 group-hover:opacity-100 p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-opacity cursor-pointer flex items-center justify-center bg-card border border-border shadow-2xs"
                                        title="{{ $tooltipText }}"
                                    >
                                        <x-lucide-plus class="size-3" />
                                    </button>

                                    <div
                                        x-show="openMenu"
                                        @click.outside="openMenu = false"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute left-0 top-full mt-1.5 w-60 rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-xl z-30 space-y-1 text-xs"
                                    >
                                        <div class="px-2 py-1 border-b border-border text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">
                                            Tambah &bull; {{ $dateFormattedShort }}
                                        </div>

                                        @if ($canCreateLetter)
                                            @php
                                                $letterUrl = $dateReference === 'event_date'
                                                    ? route('letters.create', ['tanggal_kegiatan' => $day['date']])
                                                    : route('letters.create', ['tanggal_surat' => $day['date']]);
                                                $letterLabel = $dateReference === 'event_date'
                                                    ? "Buat Surat Keluar (kegiatan {$dateFormattedShort})"
                                                    : "Buat Surat Keluar (tertanggal {$dateFormattedShort})";
                                            @endphp
                                            <a
                                                href="{{ $letterUrl }}"
                                                class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-accent text-sky-700 dark:text-sky-300 transition-colors font-medium text-[11px]"
                                            >
                                                <x-lucide-send class="size-3.5 shrink-0" />
                                                <span>{{ $letterLabel }}</span>
                                            </a>
                                        @endif

                                        @if ($canCreateSuratMasuk)
                                            @php
                                                $smUrl = $dateReference === 'event_date'
                                                    ? route('surat-masuk.create', ['tanggal_acara' => $day['date']])
                                                    : route('surat-masuk.create', ['tanggal_diterima' => $day['date']]);
                                                $smLabel = $dateReference === 'event_date'
                                                    ? "Catat Surat Masuk (deadline {$dateFormattedShort})"
                                                    : "Catat Surat Masuk (diterima {$dateFormattedShort})";
                                            @endphp
                                            <a
                                                href="{{ $smUrl }}"
                                                class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-accent text-emerald-700 dark:text-emerald-300 transition-colors font-medium text-[11px]"
                                            >
                                                <x-lucide-inbox class="size-3.5 shrink-0" />
                                                <span>{{ $smLabel }}</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Daftar Kartu Bergaya Notion (Multi-Line Rich Card) --}}
                        <div class="space-y-1.5 flex-1 overflow-hidden">
                            @foreach (array_slice($day['items'], 0, 3) as $item)
                                <div
                                    wire:click="openPreview('{{ $item['type'] }}', {{ $item['id'] }})"
                                    @class([
                                        'w-full text-left p-1.5 rounded-md text-xs transition-all cursor-pointer border shadow-2xs hover:shadow-xs group/card',
                                        'bg-sky-50/90 dark:bg-sky-950/40 text-slate-800 dark:text-slate-200 border-sky-200/80 dark:border-sky-800/60 hover:bg-sky-100/90 dark:hover:bg-sky-900/50 hover:border-sky-300' => $item['type'] === 'surat_keluar',
                                        'bg-amber-50/90 dark:bg-amber-950/40 text-slate-800 dark:text-slate-200 border-amber-200/80 dark:border-amber-800/60 hover:bg-amber-100/90 dark:hover:bg-amber-900/50 hover:border-amber-300' => $item['type'] === 'surat_masuk',
                                    ])
                                    title="{{ $item['number'] }} • {{ $item['perihal'] }}"
                                >
                                    {{-- Baris 1: Judul Perihal dengan Panah Arah Ala Notion --}}
                                    <div class="font-medium text-[11px] leading-tight truncate text-foreground group-hover/card:text-primary flex items-center gap-1">
                                        @if ($item['type'] === 'surat_keluar')
                                            <span class="text-sky-600 dark:text-sky-400 font-bold text-xs shrink-0">&uarr;</span>
                                        @else
                                            <span class="text-amber-600 dark:text-amber-400 font-bold text-xs shrink-0">&darr;</span>
                                        @endif
                                        <span class="truncate">{{ $item['perihal'] }}</span>
                                    </div>

                                    {{-- Baris 2: Status Pill dengan Colored Dot (Gaya Notion) --}}
                                    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-background/90 border border-border/50 text-foreground shadow-2xs shrink-0">
                                            <span class="size-1.5 rounded-full {{ $item['dot_color'] }}"></span>
                                            <span>{{ $item['status_label'] }}</span>
                                        </span>
                                        @if (! empty($item['has_file']))
                                            <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] text-muted-foreground font-mono bg-muted/40">
                                                <x-lucide-file-text class="size-2.5 text-primary" />
                                                <span>PDF</span>
                                            </span>
                                        @elseif (! empty($item['has_link']))
                                            <span class="inline-flex items-center gap-0.5 px-1 py-0.2 rounded text-[9px] text-muted-foreground font-mono bg-muted/40">
                                                <x-lucide-external-link class="size-2.5 text-blue-500" />
                                                <span>URL</span>
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Baris 3: Konteks / Tag @ Pengirim / Penerima --}}
                                    <div class="mt-1 text-[10px] text-muted-foreground truncate flex items-center gap-0.5">
                                        <span class="opacity-70 font-semibold">@</span>
                                        <span class="truncate">{{ $item['tag'] ?? $item['actor'] }}</span>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Tombol +X lainnya jika lebih dari 3 surat --}}
                            @if (count($day['items']) > 3)
                                <button
                                    type="button"
                                    wire:click="openMoreModal('{{ $day['date'] }}')"
                                    class="w-full text-center py-1 text-[10px] font-semibold text-primary hover:underline hover:bg-muted/50 rounded transition-colors cursor-pointer block"
                                >
                                    +{{ count($day['items']) - 3 }} lainnya
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Kalender Adaptif Khusus Layar Mobile (< md) --}}
    <div class="block md:hidden space-y-3.5">
        {{-- Mini Month Grid Picker --}}
        <div class="rounded-xl border border-border bg-card shadow-xs p-3">
            {{-- Header Nama Hari --}}
            <div class="grid grid-cols-7 text-center text-xs font-semibold text-muted-foreground pb-2 border-b border-border">
                <div class="text-rose-600 dark:text-rose-400">Min</div>
                <div>Sen</div>
                <div>Sel</div>
                <div>Rab</div>
                <div>Kam</div>
                <div>Jum</div>
                <div class="text-amber-600 dark:text-amber-400">Sab</div>
            </div>

            {{-- Grid Tanggal Kompak --}}
            <div class="grid grid-cols-7 gap-1 pt-2">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        @php
                            $isSelected = $day['date'] === $selectedMobileDate;
                            $hasKeluar = collect($day['items'])->contains('type', 'surat_keluar');
                            $hasMasuk = collect($day['items'])->contains('type', 'surat_masuk');
                        @endphp
                        <button
                            type="button"
                            wire:click="selectMobileDate('{{ $day['date'] }}')"
                            @class([
                                'min-h-[42px] py-1 px-0.5 rounded-lg flex flex-col items-center justify-between transition cursor-pointer relative',
                                'bg-primary text-primary-foreground font-bold shadow-2xs' => $isSelected,
                                'hover:bg-muted/70 text-foreground' => ! $isSelected && $day['isCurrentMonth'],
                                'text-muted-foreground/40 hover:bg-muted/30' => ! $isSelected && ! $day['isCurrentMonth'],
                                'ring-1 ring-inset ring-[#eb5757]' => $day['isToday'] && ! $isSelected,
                            ])
                        >
                            <span @class([
                                'text-xs',
                                'font-bold' => $day['isToday'] || $isSelected,
                            ])>
                                {{ $day['day'] }}
                            </span>

                            {{-- Indikator Dots Surat --}}
                            <div class="flex items-center justify-center gap-0.5 h-1.5 w-full">
                                @if ($hasKeluar)
                                    <span @class([
                                        'size-1 rounded-full',
                                        'bg-white' => $isSelected,
                                        'bg-sky-500' => ! $isSelected,
                                    ])></span>
                                @endif
                                @if ($hasMasuk)
                                    <span @class([
                                        'size-1 rounded-full',
                                        'bg-white/80' => $isSelected,
                                        'bg-amber-500' => ! $isSelected,
                                    ])></span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Daftar Agenda Surat pada Tanggal yang Dipilih (Vertical Card List) --}}
        @php
            $selectedCarbon = \Carbon\Carbon::parse($selectedMobileDate);
            $selectedDateFormatted = $selectedCarbon->translatedFormat('l, d F Y');
            $selectedShort = $selectedCarbon->translatedFormat('d M');
        @endphp
        <div class="rounded-xl border border-border bg-card shadow-xs p-4 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-foreground flex items-center gap-1.5 truncate">
                        <x-lucide-calendar-days class="size-4 text-primary shrink-0" />
                        <span>Agenda Surat</span>
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ $selectedDateFormatted }}</p>
                </div>

                @if ($canCreateLetter || $canCreateSuratMasuk)
                    <div x-data="{ openQuick: false }" class="relative shrink-0">
                        <button
                            type="button"
                            @click="openQuick = !openQuick"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary text-primary-foreground shadow-2xs hover:bg-primary/90 transition"
                        >
                            <x-lucide-plus class="size-3.5" />
                            <span>Tambah</span>
                        </button>

                        <div
                            x-show="openQuick"
                            @click.outside="openQuick = false"
                            x-cloak
                            class="absolute right-0 top-full mt-1.5 w-56 rounded-xl border border-border bg-popover p-1.5 shadow-xl z-30 space-y-1 text-xs"
                        >
                            @if ($canCreateLetter)
                                <a
                                    href="{{ route('letters.create', ['tanggal_surat' => $selectedMobileDate]) }}"
                                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-accent text-sky-700 dark:text-sky-300 font-medium text-xs"
                                >
                                    <x-lucide-send class="size-3.5 shrink-0" />
                                    <span>Buat Surat Keluar</span>
                                </a>
                            @endif
                            @if ($canCreateSuratMasuk)
                                <a
                                    href="{{ route('surat-masuk.create', ['tanggal_diterima' => $selectedMobileDate]) }}"
                                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg hover:bg-accent text-emerald-700 dark:text-emerald-300 font-medium text-xs"
                                >
                                    <x-lucide-inbox class="size-3.5 shrink-0" />
                                    <span>Catat Surat Masuk</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if (empty($selectedMobileItems))
                <div class="py-6 text-center text-xs text-muted-foreground space-y-1.5">
                    <x-lucide-calendar-x class="size-7 mx-auto text-muted-foreground/40 stroke-1" />
                    <p class="font-medium text-foreground">Tidak ada surat pada tanggal ini</p>
                    <p class="text-[11px]">Silakan pilih tanggal lain pada kalender di atas atau buat surat baru.</p>
                </div>
            @else
                <div class="space-y-2.5">
                    @foreach ($selectedMobileItems as $mItem)
                        <div class="p-3 rounded-lg border border-border bg-background space-y-2 hover:border-primary/50 transition-colors shadow-2xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold {{ $mItem['type'] === 'surat_keluar' ? 'text-sky-600 dark:text-sky-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    @if ($mItem['type'] === 'surat_keluar')
                                        <x-lucide-arrow-up-right class="size-3.5" />
                                        <span>Surat Keluar</span>
                                    @else
                                        <x-lucide-arrow-down-left class="size-3.5" />
                                        <span>Surat Masuk</span>
                                    @endif
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-foreground border border-border/50">
                                    <span class="size-1.5 rounded-full {{ $mItem['dot_color'] }}"></span>
                                    <span>{{ $mItem['status_label'] }}</span>
                                </span>
                            </div>

                            <div>
                                <span class="font-mono text-xs font-semibold text-primary block">{{ $mItem['number'] }}</span>
                                <p class="text-xs font-medium text-foreground mt-0.5 line-clamp-2 leading-snug">{{ $mItem['perihal'] }}</p>
                                <p class="text-[11px] text-muted-foreground mt-1 truncate">@ {{ $mItem['tag'] ?? $mItem['actor'] }}</p>
                            </div>

                            <div class="flex items-center justify-end gap-1.5 pt-1.5 border-t border-border/40">
                                <button
                                    type="button"
                                    wire:click="openPreview('{{ $mItem['type'] }}', {{ $mItem['id'] }})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md border border-border bg-background hover:bg-muted text-foreground cursor-pointer"
                                >
                                    <x-lucide-eye class="size-3" />
                                    <span>Pratinjau PDF</span>
                                </button>
                                <a
                                    href="{{ $mItem['type'] === 'surat_keluar' ? route('letters.show', $mItem['id']) : route('surat-masuk.show', $mItem['id']) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md bg-muted hover:bg-muted/80 text-foreground"
                                >
                                    <x-lucide-external-link class="size-3" />
                                    <span>Buka Detail</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL 1: DAFTAR SEMUA SURAT PADA TANGGAL TERTENTU (Gaya Day-List) --}}
    @if ($activeModal === 'day-list' && $moreModalDate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-xl rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-border pb-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                            <x-lucide-calendar class="size-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">
                                Agenda Surat: {{ \Carbon\Carbon::parse($moreModalDate)->translatedFormat('l, d F Y') }}
                            </h3>
                            <p class="text-xs text-muted-foreground">{{ count($moreModalItems) }} agenda terdaftar</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-border border border-border rounded-lg text-xs min-h-[150px]">
                    @forelse ($moreModalItems as $item)
                        <div class="p-3 hover:bg-muted/40 transition-colors flex items-center justify-between gap-3">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold',
                                        'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' => $item['type'] === 'surat_keluar',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' => $item['type'] === 'surat_masuk',
                                    ])>
                                        {{ $item['number'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[10px] text-muted-foreground">
                                        <span class="size-1.5 rounded-full {{ $item['dot_color'] }}"></span>
                                        <span>{{ $item['status_label'] }}</span>
                                    </span>
                                </div>
                                <p class="font-medium text-foreground line-clamp-1">
                                    {{ $item['perihal'] }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ $item['type'] === 'surat_keluar' ? 'Penerima' : 'Pengirim' }}: {{ $item['actor'] }}
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-1.5">
                                <x-ui.button
                                    type="button"
                                    size="xs"
                                    variant="outline"
                                    wire:click="openPreview('{{ $item['type'] }}', {{ $item['id'] }}, true)"
                                >
                                    <x-lucide-eye class="size-3 mr-1" />
                                    <span>Pratinjau PDF</span>
                                </x-ui.button>
                                @if ($item['type'] === 'surat_keluar')
                                    <x-ui.button
                                        as="a"
                                        href="{{ route('letters.show', $item['id'] ?? 0) }}"
                                        size="xs"
                                    >
                                        Buka
                                    </x-ui.button>
                                @else
                                    <x-ui.button
                                        as="a"
                                        href="{{ route('surat-masuk.show', $item['id'] ?? 0) }}"
                                        size="xs"
                                    >
                                        Buka
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-muted-foreground">
                            Tidak ada surat pada tanggal ini.
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-2 border-t border-border shrink-0">
                    <x-ui.button type="button" variant="outline" size="sm" wire:click="closeModal">
                        Tutup
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: PRATINJAU DOKUMEN PDF LEGA (Ukuran 80vh, Tanpa Tumpang Tindih) --}}
    @if ($activeModal === 'pdf-preview' && $previewData)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-5xl rounded-xl border border-border bg-background p-4 sm:p-6 shadow-2xl space-y-3 h-[85vh] flex flex-col overflow-hidden">
                {{-- Header Modal --}}
                <div class="flex items-center justify-between border-b border-border pb-3 shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        @if ($openedFromDayList)
                            <button
                                type="button"
                                wire:click="backToDayList"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline px-2 py-1 rounded hover:bg-muted transition-colors mr-1 cursor-pointer"
                                title="Kembali ke daftar surat tanggal"
                            >
                                <x-lucide-arrow-left class="size-3.5" />
                                <span>Kembali ke Daftar</span>
                            </button>
                            <x-ui.separator orientation="vertical" class="h-4" />
                        @endif

                        @if ($previewType === 'surat_keluar')
                            <div class="size-8 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 flex items-center justify-center border border-sky-300 dark:border-sky-800 shrink-0">
                                <x-lucide-file-text class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-foreground truncate">Pratinjau Surat Keluar: {{ $previewData->formatted_number ?? 'Draf' }}</h3>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $previewData->perihal }}</p>
                            </div>
                        @else
                            <div class="size-8 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 flex items-center justify-center border border-amber-300 dark:border-amber-800 shrink-0">
                                <x-lucide-inbox class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-foreground truncate">Pratinjau Surat Masuk: {{ $previewData->nomor_agenda }}</h3>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $previewData->perihal }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Tombol Aksi Kanan & Close --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($previewType === 'surat_keluar')
                            <x-ui.button
                                as="a"
                                href="{{ route('letters.download', $previewData->id) }}"
                                size="sm"
                                variant="outline"
                                class="gap-1.5 h-8 text-xs hidden sm:inline-flex"
                            >
                                <x-lucide-download class="size-3.5" />
                                <span>Unduh PDF</span>
                            </x-ui.button>
                            <x-ui.button
                                as="a"
                                href="{{ route('letters.show', $previewData->id) }}"
                                size="sm"
                                class="gap-1.5 h-8 text-xs"
                            >
                                <span>Buka Halaman Detail Surat</span>
                                <x-lucide-arrow-right class="size-3.5" />
                            </x-ui.button>
                        @else
                            @if (! empty($previewData->file_path))
                                <x-ui.button
                                    as="a"
                                    href="{{ route('surat-masuk.download', $previewData->id) }}"
                                    size="sm"
                                    variant="outline"
                                    class="gap-1.5 h-8 text-xs hidden sm:inline-flex"
                                >
                                    <x-lucide-download class="size-3.5" />
                                    <span>Unduh Dokumen</span>
                                </x-ui.button>
                            @endif
                            @if (! empty($previewData->document_url))
                                <x-ui.button
                                    as="a"
                                    href="{{ $previewData->document_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    size="sm"
                                    variant="outline"
                                    class="gap-1.5 h-8 text-xs text-sky-700 dark:text-sky-300"
                                >
                                    <x-lucide-external-link class="size-3.5" />
                                    <span>Buka Link Eksternal</span>
                                </x-ui.button>
                            @endif
                            <x-ui.button
                                as="a"
                                href="{{ route('surat-masuk.show', $previewData->id) }}"
                                size="sm"
                                class="gap-1.5 h-8 text-xs"
                            >
                                <span>Buka Halaman Detail Surat Masuk</span>
                                <x-lucide-arrow-right class="size-3.5" />
                            </x-ui.button>
                        @endif

                        <button
                            type="button"
                            wire:click="closeModal"
                            class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                        >
                            <x-lucide-x class="size-4" />
                        </button>
                    </div>
                </div>

                {{-- Bar Informasi Ringkas --}}
                <div class="bg-muted/30 border border-border/60 rounded-lg p-2.5 flex items-center justify-between text-xs shrink-0 flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <span class="text-muted-foreground">Status:</span>
                        @if ($previewType === 'surat_keluar')
                            <x-ui.status-badge :status="$previewData->status" />
                        @else
                            <span class="font-medium text-foreground capitalize">
                                {{ is_object($previewData->status) ? $previewData->status->label() : ucfirst(str_replace('_', ' ', (string) $previewData->status)) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-muted-foreground">
                        <span>Tanggal: </span>
                        <span class="font-medium text-foreground">
                            {{ $previewType === 'surat_keluar' ? ($previewData->letter_date?->translatedFormat('d F Y') ?? '—') : ($previewData->tanggal_surat?->translatedFormat('d F Y') ?? '—') }}
                        </span>
                    </div>
                    <div class="text-muted-foreground truncate max-w-xs">
                        <span>{{ $previewType === 'surat_keluar' ? 'Penerima' : 'Pengirim' }}: </span>
                        <span class="font-medium text-foreground">
                            {{ $previewType === 'surat_keluar' ? ($previewData->field_values['penerima'] ?? ($previewData->field_values['instansi'] ?? '—')) : $previewData->pengirim }}
                        </span>
                    </div>
                </div>

                {{-- Penampil PDF / Berkas Dokumen Lega (Flex-1 Iframe) --}}
                <div class="flex-1 border border-border rounded-lg overflow-hidden bg-muted/20 relative min-h-0">
                    @if ($previewType === 'surat_keluar')
                        <iframe
                            src="{{ route('letters.preview-pdf', $previewData->id) }}?v={{ time() }}"
                            class="w-full h-full border-0 rounded-lg"
                            title="Pratinjau PDF Dokumen"
                        ></iframe>
                    @else
                        @if (! empty($previewData->file_path))
                            @if ($previewData->isPdf())
                                <iframe
                                    src="{{ route('surat-masuk.preview', $previewData->id) }}?v={{ time() }}"
                                    class="w-full h-full border-0 rounded-lg"
                                    title="Pratinjau Dokumen PDF Surat Masuk"
                                ></iframe>
                            @elseif ($previewData->isImage())
                                <div class="w-full h-full flex items-center justify-center p-4 overflow-auto">
                                    <img
                                        src="{{ route('surat-masuk.preview', $previewData->id) }}?v={{ time() }}"
                                        alt="Pratinjau Dokumen"
                                        class="max-h-full max-w-full object-contain rounded-md shadow-md"
                                    />
                                </div>
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center p-6 text-center space-y-3">
                                    <x-lucide-file class="size-12 text-muted-foreground" />
                                    <div>
                                        <p class="font-semibold text-sm">{{ $previewData->file_name ?? 'Berkas Terlampir' }}</p>
                                        <p class="text-xs text-muted-foreground">Format berkas tidak mendukung pratinjau browser langsung.</p>
                                    </div>
                                    <x-ui.button as="a" href="{{ route('surat-masuk.download', $previewData->id) }}" size="sm">
                                        <x-lucide-download class="size-3.5 mr-1" />
                                        <span>Unduh Berkas</span>
                                    </x-ui.button>
                                </div>
                            @endif
                        @elseif (! empty($previewData->document_url))
                            <div class="w-full h-full flex flex-col items-center justify-center p-6 text-center space-y-4">
                                <div class="size-14 rounded-full bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 flex items-center justify-center border border-sky-300 dark:border-sky-800">
                                    <x-lucide-link class="size-7" />
                                </div>
                                <div class="max-w-md space-y-1">
                                    <h4 class="font-bold text-sm text-foreground">Dokumen Tersimpan di Tautan Eksternal</h4>
                                    <p class="text-xs text-muted-foreground truncate">{{ $previewData->document_url }}</p>
                                </div>
                                <x-ui.button
                                    as="a"
                                    href="{{ $previewData->document_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    size="default"
                                    class="gap-1.5"
                                >
                                    <x-lucide-external-link class="size-4" />
                                    <span>Buka Dokumen di Tab Baru (Google Drive / Cloud)</span>
                                </x-ui.button>
                            </div>
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center p-6 text-center space-y-2 text-muted-foreground">
                                <x-lucide-file-x class="size-10 opacity-50" />
                                <p class="text-xs">Tidak ada dokumen PDF atau tautan berkas yang tersimpan pada surat masuk ini.</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
