<div class="space-y-6">
    {{-- Header Sambutan & Quick Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-border">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-foreground">
                Ringkasan Persuratan — {{ $workspace?->name }}
            </h1>
            <p class="text-xs text-muted-foreground mt-0.5">
                Pusat pemantauan administrasi surat keluar, surat masuk, dan alur disposisi aktif.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button variant="outline" size="sm" :href="route('calendar.index')" class="gap-1.5 text-xs">
                <x-lucide-calendar class="size-3.5" />
                <span>Kalender</span>
            </x-ui.button>
            <x-ui.button variant="outline" size="sm" :href="route('surat-masuk.index')" class="gap-1.5 text-xs">
                <x-lucide-inbox class="size-3.5" />
                <span>Surat Masuk</span>
            </x-ui.button>
            @if ($role?->canWrite())
                <x-ui.button size="sm" :href="route('letters.create')" class="gap-1.5 text-xs">
                    <x-lucide-plus class="size-3.5" />
                    <span>Buat Surat</span>
                </x-ui.button>
            @endif
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- 4 KPI CARDS UTAMA (SURAT KELUAR, SURAT MASUK, PERLU TINDAKAN, BULAN INI) --}}
    {{-- ======================================================== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Card 1: Surat Keluar --}}
        <x-ui.card class="hover:border-primary/40 transition-colors">
            <a href="{{ route('letters.index') }}" class="p-5 block space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Surat Keluar</p>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400">
                        <x-lucide-files class="size-4" />
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-foreground">{{ $keluarCounts['total'] }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1 text-[11px] font-mono">
                    <span class="px-1.5 py-0.5 rounded bg-muted text-muted-foreground">Draft: {{ $keluarCounts['draft'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold">TTD: {{ $keluarCounts['menunggu_ttd'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">Selesai: {{ $keluarCounts['selesai'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-destructive/10 text-destructive">Hangus: {{ $keluarCounts['hangus'] }}</span>
                </div>
            </a>
        </x-ui.card>

        {{-- Card 2: Surat Masuk --}}
        <x-ui.card class="hover:border-primary/40 transition-colors">
            <a href="{{ route('surat-masuk.index') }}" class="p-5 block space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Surat Masuk</p>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                        <x-lucide-inbox class="size-4" />
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-foreground">{{ $masukCounts['total'] }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1 text-[11px] font-mono">
                    <span class="px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-600 dark:text-sky-400">Baru: {{ $masukCounts['baru'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold">Tunggu Disp: {{ $masukCounts['menunggu_disposisi'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">Didisposisi: {{ $masukCounts['didisposisi'] }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">Selesai: {{ $masukCounts['selesai'] }}</span>
                </div>
            </a>
        </x-ui.card>

        {{-- Card 3: Perlu Tindakan Segera --}}
        <x-ui.card @class([
            'transition-colors',
            'border-amber-400 dark:border-amber-600/60 bg-amber-500/5' => $actionNeededCount > 0,
        ])>
            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Perlu Tindakan</p>
                    <div @class([
                        'flex h-8 w-8 items-center justify-center rounded-lg border',
                        'border-amber-300 dark:border-amber-700 bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' => $actionNeededCount > 0,
                        'border-border bg-muted/40 text-muted-foreground' => $actionNeededCount === 0,
                    ])>
                        <x-lucide-alert-circle class="size-4" />
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-foreground">{{ $actionNeededCount }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-1 text-[11px]">
                    <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 font-medium">
                        TTD Basah: {{ $keluarCounts['menunggu_ttd'] }}
                    </span>
                    <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 font-medium">
                        Disposisi: {{ $masukCounts['baru'] + $masukCounts['menunggu_disposisi'] }}
                    </span>
                    @if ($userActiveDisposisis->count() > 0)
                        <span class="px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-700 dark:text-rose-300 font-semibold">
                            Tugas Saya: {{ $userActiveDisposisis->count() }}
                        </span>
                    @endif
                </div>
            </div>
        </x-ui.card>

        {{-- Card 4: Surat Bulan Ini --}}
        <x-ui.card class="hover:border-primary/40 transition-colors">
            <a href="{{ route('calendar.index') }}" class="p-5 block space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Bulan Ini ({{ now()->format('M Y') }})</p>
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-calendar class="size-4" />
                    </div>
                </div>
                <div>
                    <p class="text-2xl font-bold tracking-tight text-foreground">{{ $thisMonthKeluar + $thisMonthMasuk }}</p>
                </div>
                <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                    <span>Keluar: <strong class="text-foreground">{{ $thisMonthKeluar }}</strong></span>
                    <span>•</span>
                    <span>Masuk: <strong class="text-foreground">{{ $thisMonthMasuk }}</strong></span>
                    <span>•</span>
                    <span class="text-primary hover:underline">Buka Kalender &rarr;</span>
                </div>
            </a>
        </x-ui.card>
    </div>

    {{-- ======================================================== --}}
    {{-- GRID 2 KOLOM: PERHATIAN SEGERA & AGENDA vs AKTIVITAS TERBARU --}}
    {{-- ======================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- KOLOM KIRI: PERLU PERHATIAN SEGERA & AGENDA TERDEKAT --}}
        <div class="space-y-6">
            {{-- Widget 1: Perlu Perhatian Segera --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border pb-3 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-lucide-clock class="size-4 text-amber-500" />
                        <x-ui.card-title class="text-sm font-semibold">Perlu Perhatian Segera</x-ui.card-title>
                    </div>
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 font-semibold">
                        {{ $actionNeededCount }} Hal
                    </span>
                </x-ui.card-header>

                <x-ui.card-content class="p-0 divide-y divide-border text-xs">
                    {{-- Disposisi Ditugaskan ke User --}}
                    @foreach ($userActiveDisposisis as $disp)
                        <div class="p-3.5 hover:bg-muted/30 transition-colors flex items-start justify-between gap-3 bg-rose-500/5">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                        Tugas Disposisi Anda
                                    </span>
                                    <span class="text-muted-foreground text-[11px] truncate">
                                        {{ $disp->suratMasuk?->nomor_agenda }}
                                    </span>
                                </div>
                                <p class="font-medium text-foreground line-clamp-1">
                                    {{ $disp->suratMasuk?->perihal }}
                                </p>
                                <p class="text-[11px] text-muted-foreground line-clamp-1">
                                    Instruksi: "{{ $disp->instruksi ?: 'Tindak lanjuti dokumen ini' }}"
                                </p>
                            </div>
                            <x-ui.button
                                as="a"
                                href="{{ route('surat-masuk.show', $disp->surat_masuk_id) }}"
                                size="xs"
                                variant="outline"
                                class="shrink-0"
                            >
                                Buka
                            </x-ui.button>
                        </div>
                    @endforeach

                    {{-- Surat Keluar Menunggu TTD --}}
                    @foreach ($lettersNeedingSignature as $ltr)
                        <div class="p-3.5 hover:bg-muted/30 transition-colors flex items-start justify-between gap-3">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                        Menunggu TTD
                                    </span>
                                    <span class="font-mono text-[11px] text-foreground font-medium truncate">
                                        {{ $ltr->formatted_number ?? 'Draf' }}
                                    </span>
                                </div>
                                <p class="font-medium text-foreground line-clamp-1">
                                    {{ $ltr->perihal }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Dibuat: {{ $ltr->author?->name ?? '—' }} ({{ $ltr->letter_date?->format('d/m/Y') }})
                                </p>
                            </div>
                            <x-ui.button
                                as="a"
                                href="{{ route('letters.show', $ltr->id) }}"
                                size="xs"
                                variant="outline"
                                class="shrink-0"
                            >
                                Periksa
                            </x-ui.button>
                        </div>
                    @endforeach

                    {{-- Surat Masuk Belum Didisposisi --}}
                    @foreach ($suratMasukPendingDisposisi as $sm)
                        <div class="p-3.5 hover:bg-muted/30 transition-colors flex items-start justify-between gap-3">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300">
                                        Surat Masuk {{ is_object($sm->status) ? $sm->status->label() : ucfirst(str_replace('_', ' ', (string) $sm->status)) }}
                                    </span>
                                    <span class="font-mono text-[11px] text-foreground font-medium truncate">
                                        {{ $sm->nomor_agenda }}
                                    </span>
                                </div>
                                <p class="font-medium text-foreground line-clamp-1">
                                    {{ $sm->perihal }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Pengirim: {{ $sm->pengirim }}
                                </p>
                            </div>
                            <x-ui.button
                                as="a"
                                href="{{ route('surat-masuk.show', $sm->id) }}"
                                size="xs"
                                variant="outline"
                                class="shrink-0"
                            >
                                Disposisi
                            </x-ui.button>
                        </div>
                    @endforeach

                    @if ($actionNeededCount === 0)
                        <div class="p-6 text-center text-muted-foreground">
                            <x-lucide-check-circle-2 class="size-6 text-emerald-500 mx-auto mb-1.5" />
                            <p class="font-medium text-foreground">Semua Administrasi Tertangani</p>
                            <p class="text-[11px]">Tidak ada surat menunggu tanda tangan atau disposisi pending saat ini.</p>
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

            {{-- Widget 2: Agenda & Jadwal Terdekat --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border pb-3 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-lucide-calendar-days class="size-4 text-primary" />
                        <x-ui.card-title class="text-sm font-semibold">Agenda &amp; Jadwal Terdekat</x-ui.card-title>
                    </div>
                    <a href="{{ route('calendar.index') }}" class="text-xs text-primary hover:underline font-medium">
                        Buka Kalender &rarr;
                    </a>
                </x-ui.card-header>

                <x-ui.card-content class="p-0 divide-y divide-border text-xs">
                    @forelse ($upcomingAgendas as $agenda)
                        <div class="p-3.5 hover:bg-muted/30 transition-colors flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex flex-col items-center justify-center size-10 rounded-lg border border-border bg-muted/40 shrink-0 text-center">
                                    <span class="text-[9px] uppercase font-bold text-muted-foreground leading-none">
                                        {{ \Carbon\Carbon::parse($agenda->tanggal_acara_atau_deadline)->format('M') }}
                                    </span>
                                    <span class="text-sm font-extrabold text-foreground leading-tight">
                                        {{ \Carbon\Carbon::parse($agenda->tanggal_acara_atau_deadline)->format('d') }}
                                    </span>
                                </div>
                                <div class="min-w-0 space-y-0.5">
                                    <p class="font-medium text-foreground truncate">
                                        {{ $agenda->perihal }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        Dari: {{ $agenda->pengirim }} • Agenda: <span class="font-mono">{{ $agenda->nomor_agenda }}</span>
                                    </p>
                                </div>
                            </div>
                            <x-ui.button
                                as="a"
                                href="{{ route('surat-masuk.show', $agenda->id) }}"
                                size="xs"
                                variant="ghost"
                                class="shrink-0"
                            >
                                Detail
                            </x-ui.button>
                        </div>
                    @empty
                        <div class="p-6 text-center text-muted-foreground">
                            <x-lucide-calendar-x-2 class="size-6 text-muted-foreground/50 mx-auto mb-1.5" />
                            <p class="font-medium text-foreground">Tidak Ada Jadwal Mendatang</p>
                            <p class="text-[11px]">Belum ada surat masuk dengan tanggal kegiatan/deadline yang dicatat.</p>
                        </div>
                    @endforelse
                </x-ui.card-content>
            </x-ui.card>
        </div>

        {{-- KOLOM KANAN: AKTIVITAS TERBARU (STREAM SURAT KELUAR & MASUK) --}}
        <div>
            <x-ui.card>
                <x-ui.card-header class="border-b border-border pb-3 flex flex-row items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-lucide-activity class="size-4 text-primary" />
                        <x-ui.card-title class="text-sm font-semibold">Aktivitas Persuratan Terbaru</x-ui.card-title>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('letters.index') }}" class="text-muted-foreground hover:text-foreground hover:underline">
                            Daftar Surat
                        </a>
                        <span>•</span>
                        <a href="{{ route('surat-masuk.index') }}" class="text-muted-foreground hover:text-foreground hover:underline">
                            Surat Masuk
                        </a>
                    </div>
                </x-ui.card-header>

                <x-ui.card-content class="p-0 divide-y divide-border text-xs">
                    @forelse ($activities as $act)
                        <div class="p-3.5 hover:bg-muted/30 transition-colors flex items-center justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                @if ($act['type'] === 'keluar')
                                    <div class="size-8 rounded-lg flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 mt-0.5" title="Surat Keluar">
                                        <x-lucide-send class="size-3.5" />
                                    </div>
                                @else
                                    <div class="size-8 rounded-lg flex items-center justify-center shrink-0 border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 mt-0.5" title="Surat Masuk">
                                        <x-lucide-inbox class="size-3.5" />
                                    </div>
                                @endif
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-[11px] font-semibold text-foreground">
                                            {{ $act['number'] }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground">
                                            {{ $act['type'] === 'keluar' ? 'Surat Keluar' : 'Surat Masuk' }}
                                        </span>
                                    </div>
                                    <p class="font-medium text-foreground line-clamp-1">
                                        <a href="{{ $act['url'] }}" class="hover:underline">
                                            {{ $act['perihal'] }}
                                        </a>
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ $act['type'] === 'keluar' ? 'Dibuat oleh' : 'Pengirim' }}: {{ $act['actor'] }} • {{ $act['created_at']?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                            <x-ui.button
                                as="a"
                                href="{{ $act['url'] }}"
                                size="xs"
                                variant="ghost"
                                class="shrink-0"
                            >
                                Detail
                            </x-ui.button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-muted-foreground">
                            <x-lucide-file-text class="size-8 mx-auto mb-2 text-muted-foreground/40" />
                            <p class="font-medium text-foreground">Belum Ada Aktivitas</p>
                            <p class="text-[11px]">Surat keluar atau surat masuk yang dibuat akan tampil di sini.</p>
                        </div>
                    @endforelse
                </x-ui.card-content>
            </x-ui.card>
        </div>
    </div>
</div>
