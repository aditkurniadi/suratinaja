<div class="space-y-6">
    {{-- Header Action --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-foreground">Buku Agenda Surat Masuk</h1>
            <p class="text-xs text-muted-foreground mt-0.5">
                Pencatatan arsip surat masuk, pengarsipan berkas digital, dan pelacakan disposisi tindak lanjut.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Dropdown Ekspor Agenda --}}
            <div x-data="{ open: false }" class="relative">
                <x-ui.button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="open = !open"
                    class="gap-1.5"
                >
                    <x-lucide-file-spreadsheet class="size-4 text-emerald-600 dark:text-emerald-400" />
                    <span>Ekspor Agenda</span>
                    <x-lucide-chevron-down class="size-3.5 text-muted-foreground" />
                </x-ui.button>
                <div
                    x-show="open"
                    @click.outside="open = false"
                    x-cloak
                    class="absolute right-0 mt-1 w-44 rounded-lg border border-border bg-popover p-1 shadow-lg z-20 text-xs"
                >
                    <a
                        href="{{ route('surat-masuk.export', array_filter(['format' => 'xlsx', 'status' => $status, 'sifat' => $sifat, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
                        class="flex items-center gap-2 px-2.5 py-1.5 rounded hover:bg-muted text-foreground transition-colors"
                    >
                        <x-lucide-table class="size-3.5 text-emerald-600" />
                        <span>Format Excel (.xlsx)</span>
                    </a>
                    <a
                        href="{{ route('surat-masuk.export', array_filter(['format' => 'csv', 'status' => $status, 'sifat' => $sifat, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
                        class="flex items-center gap-2 px-2.5 py-1.5 rounded hover:bg-muted text-foreground transition-colors"
                    >
                        <x-lucide-file-text class="size-3.5 text-blue-600" />
                        <span>Format CSV (.csv)</span>
                    </a>
                </div>
            </div>

            @can('surat_masuk.create')
                <x-ui.button as="a" href="{{ route('surat-masuk.create') }}" size="sm" class="gap-1.5">
                    <x-lucide-plus class="size-4" />
                    <span>Catat Surat Masuk</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Filter Toolbar Card --}}
    <x-ui.card>
        <x-ui.card-content class="p-3.5 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5">
                {{-- Search Input --}}
                <div class="relative lg:col-span-2">
                    <x-lucide-search class="size-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari no. agenda, no. surat pengirim, pengirim, perihal..."
                        class="w-full h-9 pl-9 pr-3 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>

                {{-- Filter Sifat --}}
                <div>
                    <select
                        wire:model.live="sifat"
                        class="w-full h-9 px-2.5 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="">Semua Sifat Surat</option>
                        @foreach ($sifatOptions as $sVal => $sLbl)
                            <option value="{{ $sVal }}">{{ $sLbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Status --}}
                <div>
                    <select
                        wire:model.live="status"
                        class="w-full h-9 px-2.5 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="">Semua Status</option>
                        @foreach ($statusOptions as $stVal => $stLbl)
                            <option value="{{ $stVal }}">{{ $stLbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Status Balasan --}}
                <div>
                    <select
                        wire:model.live="balasan"
                        class="w-full h-9 px-2.5 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="">Semua Balasan</option>
                        <option value="sudah">Sudah Dibalas</option>
                        <option value="belum">Belum Dibalas</option>
                    </select>
                </div>

                {{-- Rentang Tanggal Terima --}}
                <div class="flex items-center gap-1.5">
                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        title="Tanggal terima dari"
                        class="w-full h-9 px-2 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    <span class="text-xs text-muted-foreground">-</span>
                    <input
                        type="date"
                        wire:model.live="dateTo"
                        title="Tanggal terima sampai"
                        class="w-full h-9 px-2 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>
            </div>

            @if ($search !== '' || $status !== '' || $sifat !== '' || $balasan !== '' || $dateFrom !== '' || $dateTo !== '')
                <div class="flex items-center justify-between pt-1 border-t border-border/50 text-xs">
                    <span class="text-muted-foreground">Filter aktif diterapkan</span>
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="text-primary hover:underline font-medium cursor-pointer"
                    >
                        Reset Semua Filter
                    </button>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- Tabel Surat Masuk --}}
    <x-ui.card>
        {{-- Desktop Table View (md:) --}}
        <div class="hidden md:block overflow-x-auto">
            <table role="grid" class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-border bg-muted/40 font-semibold text-muted-foreground">
                        <th class="py-3 px-3.5">Agenda &amp; Nomor Pengirim</th>
                        <th class="py-3 px-3">Tanggal</th>
                        <th class="py-3 px-3">Pengirim &amp; Perihal</th>
                        <th class="py-3 px-3">Sifat &amp; Status</th>
                        <th class="py-3 px-3">Dokumen</th>
                        <th class="py-3 px-3">Disposisi</th>
                        <th class="py-3 px-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($suratMasuks as $sm)
                        <tr class="hover:bg-muted/30 transition-colors">
                            {{-- Agenda & No Surat Pengirim --}}
                            <td class="py-3 px-3.5 align-top">
                                <div class="space-y-0.5">
                                    <span class="font-mono font-bold text-foreground block">
                                        {{ $sm->nomor_agenda }}
                                    </span>
                                    <span class="text-muted-foreground block truncate max-w-[200px]" title="{{ $sm->nomor_surat_pengirim }}">
                                        {{ $sm->nomor_surat_pengirim }}
                                    </span>
                                </div>
                            </td>

                            {{-- Tanggal Surat & Diterima --}}
                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                <div class="space-y-0.5 text-[11px]">
                                    <div class="text-foreground" title="Tanggal Surat">
                                        <span class="text-muted-foreground">Surat:</span> {{ $sm->tanggal_surat?->format('d/m/Y') }}
                                    </div>
                                    <div class="text-muted-foreground" title="Tanggal Diterima">
                                        <span class="text-muted-foreground">Terima:</span> {{ $sm->tanggal_diterima?->format('d/m/Y') }}
                                    </div>
                                    @if ($sm->tanggal_acara_atau_deadline)
                                        <div class="text-amber-600 dark:text-amber-400 font-medium" title="Batas Waktu / Acara">
                                            <span>Batas:</span> {{ $sm->tanggal_acara_atau_deadline->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- Pengirim & Perihal --}}
                            <td class="py-3 px-3 align-top">
                                <div class="space-y-0.5 max-w-xs md:max-w-sm">
                                    <span class="font-semibold text-foreground block truncate" title="{{ $sm->pengirim }}">
                                        {{ $sm->pengirim }}
                                    </span>
                                    <a
                                        href="{{ route('surat-masuk.show', $sm->id) }}"
                                        class="text-primary hover:underline font-medium line-clamp-2 leading-snug"
                                        title="{{ $sm->perihal }}"
                                    >
                                        {{ $sm->perihal }}
                                    </a>
                                </div>
                            </td>

                            {{-- Sifat & Status --}}
                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                <div class="flex flex-col gap-1 items-start">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $sm->sifat_surat?->badgeClasses() }}">
                                        {{ $sm->sifat_surat?->label() }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium {{ $sm->status?->badgeClasses() }}">
                                        {{ $sm->status?->label() }}
                                    </span>
                                    @if ($sm->hasReply())
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20" title="Surat masuk telah memiliki surat balasan">
                                            <x-lucide-reply class="size-2.5" />
                                            <span>Dibalas</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground/80" title="Belum ada surat balasan">
                                            <x-lucide-minus class="size-2.5" />
                                            <span>Belum Dibalas</span>
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Dokumen (File & URL) --}}
                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                <div class="flex flex-col gap-1 text-[11px]">
                                    @if ($sm->hasFile())
                                        <a
                                            href="{{ route('surat-masuk.download', $sm->id) }}"
                                            class="inline-flex items-center gap-1 text-primary hover:underline"
                                            title="Unduh Berkas Lampiran"
                                        >
                                            <x-lucide-paperclip class="size-3" />
                                            <span class="truncate max-w-[100px]">{{ $sm->file_name ?: 'Berkas' }}</span>
                                        </a>
                                    @endif

                                    @if ($sm->hasExternalUrl())
                                        <a
                                            href="{{ $sm->document_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline"
                                            title="Buka Link Google Drive / Dokumen Luar"
                                        >
                                            <x-lucide-external-link class="size-3" />
                                            <span>Drive / Tautan</span>
                                        </a>
                                    @endif

                                    @if (! $sm->hasFile() && ! $sm->hasExternalUrl())
                                        <span class="text-muted-foreground">-</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Disposisi Summary --}}
                            <td class="py-3 px-3 align-top">
                                @php $dispCount = $sm->disposisis->count(); @endphp
                                @if ($dispCount > 0)
                                    @php $lastD = $sm->disposisis->first(); @endphp
                                    <div class="space-y-0.5 text-[11px] max-w-[160px]">
                                        <span class="font-medium text-foreground block truncate">
                                            {{ $lastD->targetDisplay() }}
                                        </span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold inline-block {{ $lastD->status_tindak_lanjut->badgeClasses() }}">
                                            {{ $lastD->status_tindak_lanjut->label() }}
                                        </span>
                                        @if ($dispCount > 1)
                                            <span class="text-[10px] text-muted-foreground block">+{{ $dispCount - 1 }} arahan lain</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[11px] text-muted-foreground">Belum ada</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-3.5 align-top text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button
                                        as="a"
                                        href="{{ route('surat-masuk.show', $sm->id) }}"
                                        size="xs"
                                        variant="outline"
                                        title="Buka Detail &amp; Disposisi"
                                    >
                                        <x-lucide-eye class="size-3" />
                                        <span class="hidden sm:inline">Detail</span>
                                    </x-ui.button>

                                    @can('surat_masuk.edit')
                                        <x-ui.button
                                            as="a"
                                            href="{{ route('surat-masuk.edit', $sm->id) }}"
                                            size="xs"
                                            variant="ghost"
                                            class="p-1"
                                            title="Ubah Surat Masuk"
                                        >
                                            <x-lucide-pencil class="size-3 text-muted-foreground" />
                                        </x-ui.button>
                                    @endcan

                                    @can('surat_masuk.delete')
                                        <x-ui.button
                                            type="button"
                                            size="xs"
                                            variant="ghost"
                                            class="p-1 text-destructive hover:text-destructive"
                                            wire:click="deleteSuratMasuk({{ $sm->id }})"
                                            wire:confirm="Yakin ingin menghapus arsip surat masuk ini?"
                                            title="Hapus"
                                        >
                                            <x-lucide-trash-2 class="size-3" />
                                        </x-ui.button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="size-10 rounded-full bg-muted flex items-center justify-center">
                                        <x-lucide-inbox class="size-5 text-muted-foreground" />
                                    </div>
                                    <p class="text-sm font-medium text-foreground">Tidak Ada Surat Masuk</p>
                                    <p class="text-xs text-muted-foreground max-w-sm">
                                        Belum ada surat masuk yang dicatat atau tidak cocok dengan filter pencarian.
                                    </p>
                                    @can('surat_masuk.create')
                                        <x-ui.button as="a" href="{{ route('surat-masuk.create') }}" size="sm" class="mt-2">
                                            Catat Surat Masuk Pertama
                                        </x-ui.button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Card List View (< md) --}}
        <div class="block md:hidden">
            @if ($suratMasuks->count() > 0)
                <div class="divide-y divide-border">
                    @foreach ($suratMasuks as $sm)
                        <div class="p-4 space-y-2.5 hover:bg-muted/20 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="font-mono text-xs font-bold text-foreground block">
                                        {{ $sm->nomor_agenda }}
                                    </span>
                                    <span class="text-[11px] text-muted-foreground block truncate max-w-[190px]" title="{{ $sm->nomor_surat_pengirim }}">
                                        {{ $sm->nomor_surat_pengirim }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $sm->sifat_surat?->badgeClasses() }}">
                                        {{ $sm->sifat_surat?->label() }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium {{ $sm->status?->badgeClasses() }}">
                                        {{ $sm->status?->label() }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <span class="font-semibold text-xs text-foreground block truncate" title="{{ $sm->pengirim }}">
                                    {{ $sm->pengirim }}
                                </span>
                                <a
                                    href="{{ route('surat-masuk.show', $sm->id) }}"
                                    class="text-primary hover:underline font-medium text-xs leading-snug line-clamp-2 mt-0.5 block"
                                >
                                    {{ $sm->perihal }}
                                </a>
                            </div>

                            <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1.5 border-t border-border/40">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span>Terima: {{ $sm->tanggal_diterima?->format('d/m/Y') }}</span>
                                    @if ($sm->hasReply())
                                        <span>&bull;</span>
                                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Dibalas</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-ui.button
                                        as="a"
                                        href="{{ route('surat-masuk.show', $sm->id) }}"
                                        size="xs"
                                        variant="outline"
                                    >
                                        <x-lucide-eye class="size-3" />
                                        <span>Detail</span>
                                    </x-ui.button>
                                    @can('surat_masuk.edit')
                                        <x-ui.button
                                            as="a"
                                            href="{{ route('surat-masuk.edit', $sm->id) }}"
                                            size="xs"
                                            variant="ghost"
                                            class="p-1"
                                        >
                                            <x-lucide-pencil class="size-3 text-muted-foreground" />
                                        </x-ui.button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-12 text-center text-muted-foreground">
                    <div class="flex flex-col items-center justify-center space-y-2 p-4">
                        <div class="size-10 rounded-full bg-muted flex items-center justify-center">
                            <x-lucide-inbox class="size-5 text-muted-foreground" />
                        </div>
                        <p class="text-sm font-medium text-foreground">Tidak Ada Surat Masuk</p>
                        <p class="text-xs text-muted-foreground max-w-xs">
                            Belum ada surat masuk yang dicatat atau tidak cocok dengan filter pencarian.
                        </p>
                        @can('surat_masuk.create')
                            <x-ui.button as="a" href="{{ route('surat-masuk.create') }}" size="sm" class="mt-2">
                                Catat Surat Masuk Pertama
                            </x-ui.button>
                        @endcan
                    </div>
                </div>
            @endif
        </div>

        @if ($suratMasuks->hasPages())
            <div class="p-3 border-t border-border">
                {{ $suratMasuks->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
