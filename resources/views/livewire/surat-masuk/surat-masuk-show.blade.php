<div class="space-y-6">
    {{-- Header Action Bar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-border pb-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('surat-masuk.index') }}" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs font-medium">
                    <x-lucide-arrow-left class="size-3.5" />
                    <span>Daftar Surat Masuk</span>
                </a>
                <span class="text-muted-foreground">/</span>
                <span class="font-mono font-bold text-sm text-foreground">{{ $suratMasuk->nomor_agenda }}</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $suratMasuk->sifat_surat?->badgeClasses() }}">
                    {{ $suratMasuk->sifat_surat?->label() }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-medium {{ $suratMasuk->status?->badgeClasses() }}">
                    {{ $suratMasuk->status?->label() }}
                </span>
            </div>
            <h1 class="text-lg font-bold tracking-tight text-foreground">{{ $suratMasuk->perihal }}</h1>
            <p class="text-xs text-muted-foreground">
                Dari: <strong class="text-foreground">{{ $suratMasuk->pengirim }}</strong> &bull; Nomor Surat: <code class="font-mono text-xs bg-muted px-1.5 py-0.5 rounded">{{ $suratMasuk->nomor_surat_pengirim }}</code>
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Tombol Cepat: Buat Surat Balasan --}}
            @can('surat_keluar.create')
                <x-ui.button
                    as="a"
                    href="{{ route('letters.create', [
                        'reply_to_sm' => $suratMasuk->id,
                        'perihal' => 'Balasan: ' . $suratMasuk->perihal,
                        'instansi' => $suratMasuk->pengirim,
                    ]) }}"
                    size="sm"
                    class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                    title="Buat draft surat keluar balasan untuk pengirim ini"
                >
                    <x-lucide-reply class="size-4" />
                    <span>Buat Surat Balasan</span>
                </x-ui.button>
            @endcan

            @can('disposisi.create')
                <x-ui.button type="button" size="sm" wire:click="openDisposisiModal" class="gap-1.5">
                    <x-lucide-send class="size-4" />
                    <span>Beri Disposisi</span>
                </x-ui.button>
            @endcan

            @can('surat_masuk.edit')
                <x-ui.button as="a" href="{{ route('surat-masuk.edit', $suratMasuk->id) }}" variant="outline" size="sm" class="gap-1.5">
                    <x-lucide-pencil class="size-3.5" />
                    <span>Ubah</span>
                </x-ui.button>
            @endcan

            @can('surat_masuk.delete')
                <x-ui.button
                    type="button"
                    variant="destructive"
                    size="sm"
                    wire:click="deleteSuratMasuk"
                    wire:confirm="Yakin ingin menghapus seluruh arsip dan riwayat disposisi surat masuk ini?"
                    class="gap-1.5"
                >
                    <x-lucide-trash-2 class="size-3.5" />
                    <span class="hidden sm:inline">Hapus</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Main Grid: Kiri (Metadata + Pratinjau Dokumen), Kanan (Timeline Disposisi) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kiri (2 Kolom): Metadata & Pratinjau Dokumen --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Card Metadata Ringkas --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border py-3 px-4">
                    <x-ui.card-title class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Rincian Informasi Surat
                    </x-ui.card-title>
                </x-ui.card-header>
                <x-ui.card-content class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Tanggal Surat:</span>
                        <span class="font-medium text-foreground">{{ $suratMasuk->tanggal_surat?->translatedFormat('d F Y') ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Tanggal Diterima:</span>
                        <span class="font-medium text-foreground">{{ $suratMasuk->tanggal_diterima?->translatedFormat('d F Y') ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Tanggal Batas / Acara:</span>
                        <span class="font-medium {{ $suratMasuk->tanggal_acara_atau_deadline ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-foreground' }}">
                            {{ $suratMasuk->tanggal_acara_atau_deadline?->translatedFormat('d F Y') ?? '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Dicatat Oleh:</span>
                        <span class="font-medium text-foreground">{{ $suratMasuk->createdBy?->name ?? 'Sistem' }}</span>
                    </div>

                    @if ($suratMasuk->ringkasan)
                        <div class="col-span-2 sm:col-span-4 pt-2 border-t border-border">
                            <span class="text-muted-foreground block text-[11px] mb-0.5">Ringkasan / Catatan Isi:</span>
                            <p class="text-foreground leading-relaxed whitespace-pre-line">{{ $suratMasuk->ringkasan }}</p>
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

            {{-- Card Surat Keluar Balasan --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border py-3 px-4 flex flex-row items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-lucide-reply class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <x-ui.card-title class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Surat Keluar Balasan / Tindak Lanjut
                        </x-ui.card-title>
                    </div>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        @can('surat_keluar.create')
                            <x-ui.button
                                as="a"
                                href="{{ route('letters.create', [
                                    'reply_to_sm' => $suratMasuk->id,
                                    'perihal' => 'Balasan: ' . $suratMasuk->perihal,
                                    'instansi' => $suratMasuk->pengirim,
                                ]) }}"
                                size="xs"
                                class="gap-1 bg-emerald-600 hover:bg-emerald-700 text-white"
                                title="Buat draf surat keluar balasan baru"
                            >
                                <x-lucide-plus class="size-3" />
                                <span>Buat Balasan</span>
                            </x-ui.button>
                        @endcan

                        <x-ui.button
                            type="button"
                            size="xs"
                            variant="outline"
                            wire:click="openLinkSuratKeluarModal"
                            class="gap-1"
                            title="Hubungkan dengan surat keluar yang sudah ada di workspace"
                        >
                            <x-lucide-link-2 class="size-3" />
                            <span>Tautkan Surat</span>
                        </x-ui.button>

                        <x-ui.button
                            type="button"
                            size="xs"
                            variant="ghost"
                            wire:click="openManualBalasanModal"
                            class="gap-1 text-muted-foreground hover:text-foreground"
                            title="Catat nomor surat balasan manual/eksternal"
                        >
                            <x-lucide-pencil class="size-3" />
                            <span>Balasan Manual</span>
                        </x-ui.button>
                    </div>
                </x-ui.card-header>

                <x-ui.card-content class="p-4 space-y-3">
                    @php
                        $linkedList = $suratMasuk->replyLetters;
                        if ($suratMasuk->linkedSuratKeluar && ! $linkedList->contains('id', $suratMasuk->linkedSuratKeluar->id)) {
                            $linkedList = $linkedList->push($suratMasuk->linkedSuratKeluar);
                        }
                    @endphp

                    @if ($linkedList->count() > 0)
                        <div class="space-y-2">
                            @foreach ($linkedList as $repLetter)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-lg border border-border bg-muted/20 hover:bg-muted/40 transition-colors">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono font-bold text-xs text-foreground">
                                                {{ $repLetter->formatted_number ?? 'Draf Surat' }}
                                            </span>
                                            <x-ui.status-badge :status="$repLetter->status" />
                                            @if ($repLetter->is_manual_number)
                                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-600 border border-blue-500/20">Eksternal</span>
                                            @endif
                                        </div>
                                        <p class="text-xs font-medium text-foreground truncate">{{ $repLetter->perihal }}</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            Tanggal: {{ $repLetter->letter_date?->translatedFormat('d M Y') ?? '—' }} &bull; Oleh: {{ $repLetter->author?->name ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <x-ui.button as="a" href="{{ route('letters.show', $repLetter->id) }}" size="xs" variant="outline" class="gap-1">
                                            <x-lucide-external-link class="size-3" />
                                            <span>Lihat Surat</span>
                                        </x-ui.button>
                                        <x-ui.button
                                            type="button"
                                            size="xs"
                                            variant="ghost"
                                            wire:click="unlinkLetter({{ $repLetter->id }})"
                                            wire:confirm="Lepas tautan surat keluar ini dari surat masuk?"
                                            class="p-1 text-muted-foreground hover:text-destructive"
                                            title="Lepas Tautan"
                                        >
                                            <x-lucide-x class="size-3.5" />
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($suratMasuk->nomor_surat_balasan) || ! empty($suratMasuk->catatan_balasan))
                        <div class="p-3 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/60 dark:bg-amber-950/30 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-amber-900 dark:text-amber-200 text-[11px] uppercase tracking-wider">Catatan Balasan Manual:</span>
                                <button type="button" wire:click="openManualBalasanModal" class="text-[11px] text-amber-700 dark:text-amber-300 underline hover:text-amber-900">Ubah</button>
                            </div>
                            @if ($suratMasuk->nomor_surat_balasan)
                                <p class="text-foreground"><strong>Nomor Surat Balasan:</strong> <code class="font-mono">{{ $suratMasuk->nomor_surat_balasan }}</code></p>
                            @endif
                            @if ($suratMasuk->catatan_balasan)
                                <p class="text-muted-foreground whitespace-pre-line">{{ $suratMasuk->catatan_balasan }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($linkedList->count() === 0 && empty($suratMasuk->nomor_surat_balasan) && empty($suratMasuk->catatan_balasan))
                        <div class="py-6 text-center text-xs text-muted-foreground space-y-2">
                            <x-lucide-reply class="size-7 mx-auto text-muted-foreground/50" />
                            <p class="font-medium text-foreground">Belum Ada Surat Balasan</p>
                            <p class="max-w-md mx-auto text-[11px]">
                                Surat masuk ini belum ditandai memiliki balasan. Anda dapat membuat surat balasan baru di sistem atau menautkan surat keluar yang sudah ada.
                            </p>
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

            {{-- Card Pratinjau Dokumen --}}
            <x-ui.card class="overflow-hidden">
                <x-ui.card-header class="border-b border-border py-3 px-4 flex flex-row items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-lucide-paperclip class="size-4 text-primary" />
                        <x-ui.card-title class="text-sm font-semibold">Berkas &amp; Tautan Dokumen</x-ui.card-title>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($suratMasuk->hasFile())
                            <x-ui.button
                                as="a"
                                href="{{ route('surat-masuk.download', $suratMasuk->id) }}"
                                size="xs"
                                variant="outline"
                                class="gap-1"
                            >
                                <x-lucide-download class="size-3" />
                                <span>Unduh ({{ $suratMasuk->file_name ?: 'Berkas' }})</span>
                            </x-ui.button>
                        @endif

                        @if ($suratMasuk->hasExternalUrl())
                            <x-ui.button
                                as="a"
                                href="{{ $suratMasuk->document_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                size="xs"
                                class="gap-1 bg-blue-600 hover:bg-blue-700 text-white"
                            >
                                <x-lucide-external-link class="size-3" />
                                <span>Buka Google Drive</span>
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card-header>

                <x-ui.card-content class="p-0 bg-muted/10">
                    @if ($suratMasuk->hasFile())
                        @if ($suratMasuk->isPdf())
                            <div class="w-full h-[640px] border-0 relative bg-muted/30">
                                <iframe
                                    src="{{ route('surat-masuk.preview', $suratMasuk->id) }}#toolbar=1"
                                    class="w-full h-full border-0 block"
                                    title="Pratinjau PDF Surat Masuk"
                                ></iframe>
                            </div>
                        @elseif ($suratMasuk->isImage())
                            <div class="p-6 flex flex-col items-center justify-center bg-muted/20">
                                <img
                                    src="{{ route('surat-masuk.preview', $suratMasuk->id) }}"
                                    alt="Pratinjau Dokumen"
                                    class="max-h-[640px] max-w-full rounded-lg shadow-md border border-border object-contain"
                                />
                            </div>
                        @else
                            {{-- DOCX or binary document --}}
                            <div class="p-10 flex flex-col items-center justify-center text-center space-y-3">
                                <div class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                    <x-lucide-file-text class="size-6" />
                                </div>
                                <h4 class="text-sm font-semibold text-foreground">{{ $suratMasuk->file_name ?: 'Dokumen Terlampir' }}</h4>
                                <p class="text-xs text-muted-foreground max-w-sm">
                                    Berkas format Microsoft Word / dokumen biner tidak mendukung pratinjau browser langsung. Silakan klik tombol di bawah untuk mengunduh.
                                </p>
                                <x-ui.button as="a" href="{{ route('surat-masuk.download', $suratMasuk->id) }}" size="sm" class="gap-1.5">
                                    <x-lucide-download class="size-4" />
                                    <span>Unduh File Dokumen</span>
                                </x-ui.button>
                            </div>
                        @endif
                    @elseif ($suratMasuk->hasExternalUrl())
                        {{-- Only has external Drive link --}}
                        <div class="p-12 flex flex-col items-center justify-center text-center space-y-3">
                            <div class="size-12 rounded-full bg-blue-500/10 text-blue-600 flex items-center justify-center">
                                <x-lucide-cloud class="size-6" />
                            </div>
                            <h4 class="text-sm font-semibold text-foreground">Dokumen Tersimpan di Google Drive / Tautan Luar</h4>
                            <p class="text-xs text-muted-foreground max-w-md">
                                Surat masuk ini tidak diunggah ke storage internal, melainkan ditautkan langsung ke dokumen luar:
                            </p>
                            <code class="font-mono text-xs bg-muted px-3 py-1.5 rounded break-all max-w-md">{{ $suratMasuk->document_url }}</code>
                            <x-ui.button as="a" href="{{ $suratMasuk->document_url }}" target="_blank" rel="noopener noreferrer" size="sm" class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white mt-2">
                                <x-lucide-external-link class="size-4" />
                                <span>Buka di Google Drive (Tab Baru)</span>
                            </x-ui.button>
                        </div>
                    @else
                        <div class="p-8 text-center text-xs text-muted-foreground">
                            Tidak ada berkas atau link dokumen yang dilampirkan.
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        </div>

        {{-- Kanan (1 Kolom): Panel Timeline Disposisi --}}
        <div class="space-y-4">
            <x-ui.card>
                <x-ui.card-header class="border-b border-border py-3 px-4 flex flex-row items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-lucide-send class="size-4 text-primary" />
                        <x-ui.card-title class="text-sm font-semibold">Timeline Disposisi</x-ui.card-title>
                    </div>

                    @can('disposisi.create')
                        <x-ui.button type="button" size="xs" wire:click="openDisposisiModal" class="gap-1">
                            <x-lucide-plus class="size-3" />
                            <span>Beri</span>
                        </x-ui.button>
                    @endcan
                </x-ui.card-header>

                <x-ui.card-content class="p-4 space-y-4">
                    @forelse ($disposisis as $d)
                        <div class="rounded-xl border border-border bg-card p-3.5 space-y-2.5 shadow-2xs relative">
                            {{-- Target & Sifat Disposisi --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="text-[10px] text-muted-foreground uppercase font-semibold block">Tujuan Disposisi:</span>
                                    <span class="font-semibold text-xs text-foreground block truncate">
                                        {{ $d->targetDisplay() }}
                                    </span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold shrink-0 {{ $d->status_tindak_lanjut->badgeClasses() }}">
                                    {{ $d->status_tindak_lanjut->label() }}
                                </span>
                            </div>

                            {{-- Instruksi --}}
                            <div class="text-xs bg-muted/40 p-2.5 rounded-lg border border-border/50 text-foreground space-y-1">
                                <span class="text-[10px] text-muted-foreground block font-medium">Instruksi Arahan:</span>
                                <p class="whitespace-pre-line leading-relaxed">{{ $d->instruksi }}</p>
                            </div>

                            {{-- Info Meta Batas Waktu & Pemberi --}}
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1">
                                <div>
                                    @if ($d->batas_waktu)
                                        <span class="text-amber-600 dark:text-amber-400 font-medium">
                                            Batas: {{ $d->batas_waktu->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span>Batas: -</span>
                                    @endif
                                </div>
                                <div>
                                    <span>Dari: {{ $d->dariUser?->name }}</span>
                                </div>
                            </div>

                            {{-- Catatan Tindak Lanjut jika sudah diisi --}}
                            @if ($d->catatan_tindak_lanjut)
                                <div class="text-xs bg-emerald-500/5 p-2.5 rounded-lg border border-emerald-500/20 text-foreground space-y-0.5">
                                    <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 block">Laporan Tindak Lanjut:</span>
                                    <p class="whitespace-pre-line text-[11px] leading-relaxed text-muted-foreground">{{ $d->catatan_tindak_lanjut }}</p>
                                    @if ($d->diselesaikan_pada)
                                        <span class="text-[9px] text-muted-foreground block pt-0.5">Selesai pada: {{ $d->diselesaikan_pada->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Aksi Disposisi --}}
                            <div class="flex items-center justify-end gap-1.5 pt-2 border-t border-border">
                                @can('disposisi.update_status')
                                    <x-ui.button
                                        type="button"
                                        size="xs"
                                        variant="outline"
                                        wire:click="openTindakLanjutModal({{ $d->id }})"
                                        class="gap-1 text-[11px] h-7"
                                    >
                                        <x-lucide-check-circle-2 class="size-3 text-primary" />
                                        <span>Update Tindak Lanjut</span>
                                    </x-ui.button>
                                @endcan

                                @can('disposisi.delete')
                                    <x-ui.button
                                        type="button"
                                        size="xs"
                                        variant="ghost"
                                        class="p-1 text-destructive hover:text-destructive h-7"
                                        wire:click="deleteDisposisi({{ $d->id }})"
                                        wire:confirm="Hapus instruksi disposisi ini?"
                                        title="Hapus"
                                    >
                                        <x-lucide-trash-2 class="size-3" />
                                    </x-ui.button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted-foreground space-y-2">
                            <x-lucide-clipboard-list class="size-8 mx-auto text-muted-foreground/60" />
                            <p class="font-medium text-foreground">Belum Ada Disposisi</p>
                            <p class="text-[11px] text-muted-foreground max-w-xs mx-auto">
                                Surat masuk ini belum memiliki arahan tindak lanjut kepada anggota atau divisi.
                            </p>
                            @can('disposisi.create')
                                <x-ui.button type="button" size="xs" wire:click="openDisposisiModal" class="gap-1 mt-1">
                                    <x-lucide-send class="size-3" />
                                    <span>Beri Disposisi Pertama</span>
                                </x-ui.button>
                            @endcan
                        </div>
                    @endforelse
                </x-ui.card-content>
            </x-ui.card>
        </div>
    </div>

    {{-- MODAL 1: Beri Disposisi Baru --}}
    @if ($showDisposisiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-semibold text-foreground">Beri Arahan Disposisi</h3>
                    <button type="button" wire:click="$set('showDisposisiModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                @error('disposisiTarget')
                    <x-ui.alert variant="destructive">{{ $message }}</x-ui.alert>
                @enderror

                <form wire:submit="saveDisposisi" class="space-y-4 text-xs">
                    <div>
                        <x-ui.label for="disposisiKepadaUserId">Diteruskan Kepada Anggota</x-ui.label>
                        <select
                            id="disposisiKepadaUserId"
                            wire:model="disposisiKepadaUserId"
                            class="w-full mt-1.5 h-9 px-2.5 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-primary"
                        >
                            <option value="">-- Pilih Anggota Penerima (Opsional) --</option>
                            @foreach ($workspaceUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-border"></div>
                        <span class="flex-shrink mx-2 text-[10px] font-semibold text-muted-foreground">ATAU SEBUTKAN DIVISI</span>
                        <div class="flex-grow border-t border-border"></div>
                    </div>

                    <div>
                        <x-ui.label for="disposisiTujuanDivisi">Nama Divisi / Bidang Tujuan</x-ui.label>
                        <x-ui.input
                            id="disposisiTujuanDivisi"
                            wire:model="disposisiTujuanDivisi"
                            placeholder="Mis. Divisi Humas &amp; Publikasi, Biro Keuangan..."
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <x-ui.label for="disposisiBatasWaktu">Batas Waktu Tindak Lanjut (Opsional)</x-ui.label>
                        <x-ui.input
                            id="disposisiBatasWaktu"
                            type="date"
                            wire:model="disposisiBatasWaktu"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <x-ui.label for="disposisiInstruksi">Petunjuk / Arahan Tindak Lanjut</x-ui.label>
                        <textarea
                            id="disposisiInstruksi"
                            wire:model="disposisiInstruksi"
                            rows="3"
                            placeholder="Tuliskan arahan tugas, mis. Mohon siapkan surat balasan, hadiri rapat koordinasi, siapkan delegasi 2 orang..."
                            class="w-full mt-1 px-3 py-2 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-primary"
                            required
                        ></textarea>
                        <x-ui.form-error for="disposisiInstruksi" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showDisposisiModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Kirim Disposisi</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 2: Update Tindak Lanjut --}}
    @if ($showTindakLanjutModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-semibold text-foreground">Update Status Tindak Lanjut</h3>
                    <button type="button" wire:click="$set('showTindakLanjutModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <form wire:submit="saveTindakLanjut" class="space-y-4 text-xs">
                    <div>
                        <x-ui.label for="tindakLanjutStatus">Status Tindak Lanjut Saat Ini</x-ui.label>
                        <x-ui.select id="tindakLanjutStatus" wire:model="tindakLanjutStatus" class="mt-1.5 font-medium">
                            @foreach (\App\Enums\StatusDisposisi::cases() as $stCase)
                                <option value="{{ $stCase->value }}">{{ $stCase->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div>
                        <x-ui.label for="tindakLanjutCatatan">Catatan / Laporan Hasil Tindak Lanjut</x-ui.label>
                        <textarea
                            id="tindakLanjutCatatan"
                            wire:model="tindakLanjutCatatan"
                            rows="3"
                            placeholder="Mis. Delegasi telah ditetapkan (Ahmad &amp; Rina), konfirmasi kehadiran sudah dikirim via WA, surat balasan dibuat..."
                            class="w-full mt-1.5 px-3 py-2 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-primary"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showTindakLanjutModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Pembaruan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 3: Tautkan Surat Keluar --}}
    @if ($showLinkSuratKeluarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-xl rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-border pb-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <x-lucide-link-2 class="size-4 text-emerald-600" />
                        <h3 class="text-base font-semibold text-foreground">Tautkan Surat Keluar Balasan</h3>
                    </div>
                    <button type="button" wire:click="$set('showLinkSuratKeluarModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="shrink-0 space-y-2 text-xs">
                    <div class="relative">
                        <x-lucide-search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                        <x-ui.input
                            type="search"
                            wire:model.live.debounce.300ms="searchSuratKeluar"
                            placeholder="Cari nomor surat atau perihal surat keluar..."
                            class="pl-9 text-xs"
                            autofocus
                        />
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Pilih surat keluar yang dibuat sebagai balasan atas surat masuk ini.
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-border border border-border rounded-lg min-h-[200px]">
                    @forelse ($availableLetters as $letter)
                        <div class="p-3 hover:bg-muted/40 transition-colors flex items-center justify-between gap-3 text-xs">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-primary/10 text-primary">
                                        {{ $letter->formatted_number ?? 'Draf' }}
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded border border-border text-muted-foreground">
                                        {{ $letter->status->label() }}
                                    </span>
                                    @if ($letter->letter_date)
                                        <span class="text-[10px] text-muted-foreground">
                                            {{ $letter->letter_date->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-foreground font-medium line-clamp-1">
                                    {{ $letter->perihal }}
                                </p>
                                @if ($letter->author)
                                    <p class="text-[10px] text-muted-foreground">
                                        Dibuat oleh: {{ $letter->author->name }}
                                    </p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                @if ((int) $suratMasuk->surat_keluar_id === (int) $letter->id || (int) $letter->surat_masuk_id === (int) $suratMasuk->id)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 px-2 py-1 rounded bg-emerald-50 dark:bg-emerald-950/50">
                                        <x-lucide-check class="size-3" />
                                        <span>Terkait</span>
                                    </span>
                                @else
                                    <x-ui.button
                                        type="button"
                                        size="xs"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                                        wire:click="selectAndSaveLinkSuratKeluar({{ $letter->id }})"
                                    >
                                        <x-lucide-link class="size-3" />
                                        <span>Tautkan</span>
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-muted-foreground">
                            <x-lucide-file-text class="size-8 mx-auto mb-2 text-muted-foreground/50" />
                            <p>Tidak ada surat keluar yang sesuai dengan kata kunci pencarian.</p>
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-border shrink-0">
                    <span class="text-[11px] text-muted-foreground">
                        Menampilkan hingga 20 surat keluar terbaru.
                    </span>
                    <x-ui.button type="button" variant="outline" size="xs" wire:click="$set('showLinkSuratKeluarModal', false)">
                        Tutup
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 4: Catat Balasan Manual --}}
    @if ($showManualBalasanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <x-lucide-pencil class="size-4 text-amber-600" />
                        <h3 class="text-base font-semibold text-foreground">Catat Balasan Manual / Eksternal</h3>
                    </div>
                    <button type="button" wire:click="$set('showManualBalasanModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <form wire:submit="saveManualBalasan" class="space-y-4 text-xs">
                    <div>
                        <x-ui.label for="nomorSuratBalasan">Nomor Surat Balasan (Jika Ada)</x-ui.label>
                        <x-ui.input
                            id="nomorSuratBalasan"
                            wire:model="nomorSuratBalasan"
                            placeholder="Mis. 045/PAN-OASIS/VI/2026 atau No. Resi Pos"
                            class="mt-1"
                        />
                        <x-ui.form-error for="nomorSuratBalasan" />
                    </div>

                    <div>
                        <x-ui.label for="catatanBalasan">Catatan Cara / Tanggal Dibalas</x-ui.label>
                        <textarea
                            id="catatanBalasan"
                            wire:model="catatanBalasan"
                            rows="3"
                            placeholder="Mis. Balasan telah dikirim via email sekretariat pada 22 Juni 2026 atau konfirmasi via WhatsApp."
                            class="w-full mt-1 px-3 py-2 rounded-md border border-input bg-background text-xs focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <x-ui.form-error for="catatanBalasan" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showManualBalasanModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Catatan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
