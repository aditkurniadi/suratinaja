<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between gap-4 border-b border-border pb-4">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-foreground">
                {{ $suratMasukId ? 'Ubah Surat Masuk' : 'Catat Surat Masuk Baru' }}
            </h1>
            <p class="text-xs text-muted-foreground mt-0.5">
                Masukkan informasi surat yang diterima dan lampirkan berkas atau link dokumen.
            </p>
        </div>

        <x-ui.button
            as="a"
            href="{{ $suratMasukId ? route('surat-masuk.show', $suratMasukId) : route('surat-masuk.index') }}"
            @click.prevent="window.SuratinAjaNav ? window.SuratinAjaNav.goBack('{{ $suratMasukId ? route('surat-masuk.show', $suratMasukId) : route('surat-masuk.index') }}') : (window.location.href = '{{ $suratMasukId ? route('surat-masuk.show', $suratMasukId) : route('surat-masuk.index') }}')"
            variant="outline"
            size="sm"
            class="gap-1.5 cursor-pointer"
            title="Kembali ke halaman sebelumnya"
            aria-label="Kembali ke halaman sebelumnya"
        >
            <x-lucide-arrow-left class="size-4" />
            <span>Kembali</span>
        </x-ui.button>
    </div>

    @if ($calendarContextNote)
        <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 text-xs text-blue-800 dark:text-blue-300 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-lucide-calendar class="size-4 text-blue-600 shrink-0" />
                <span>{{ $calendarContextNote }}</span>
            </div>
            <button type="button" wire:click="$set('calendarContextNote', null)" class="text-blue-500 hover:text-blue-700 text-base leading-none">&times;</button>
        </div>
    @endif

    @error('document_source')
        <x-ui.alert variant="destructive" title="Dokumen Belum Dilampirkan">
            {{ $message }}
        </x-ui.alert>
    @enderror

    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: Identitas Surat & Agenda --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border pb-3">
                <x-ui.card-title class="text-sm font-semibold flex items-center gap-2">
                    <x-lucide-hash class="size-4 text-primary" />
                    <span>Identitas Surat &amp; Agenda</span>
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <x-ui.label for="nomorAgenda">Nomor Agenda Surat Masuk</x-ui.label>
                    <x-ui.input
                        id="nomorAgenda"
                        wire:model="nomorAgenda"
                        class="mt-1 font-mono font-medium"
                        placeholder="SM-001/2026"
                        required
                    />
                    <p class="text-[11px] text-muted-foreground mt-1">Nomor urut buku agenda otomatis per tahun untuk arsip LPJ.</p>
                    <x-ui.form-error for="nomorAgenda" />
                </div>

                <div>
                    <x-ui.label for="nomorSuratPengirim">Nomor Surat Asli (Pengirim)</x-ui.label>
                    <x-ui.input
                        id="nomorSuratPengirim"
                        wire:model="nomorSuratPengirim"
                        class="mt-1 font-mono font-medium"
                        placeholder="Mis. 045/B/HIMATRIS/XI/2026 atau No: 124.5/UN32/TU"
                        required
                    />
                    <p class="text-[11px] text-muted-foreground mt-1">Nomor bebas persis sesuai yang tertera di surat pengirim.</p>
                    <x-ui.form-error for="nomorSuratPengirim" />
                </div>

                <div>
                    <x-ui.label for="tanggalSurat">Tanggal Tertera di Surat</x-ui.label>
                    <x-ui.input
                        id="tanggalSurat"
                        type="date"
                        wire:model="tanggalSurat"
                        class="mt-1"
                        required
                    />
                    <x-ui.form-error for="tanggalSurat" />
                </div>

                <div>
                    <x-ui.label for="tanggalDiterima">Tanggal Diterima Organisasi</x-ui.label>
                    <x-ui.input
                        id="tanggalDiterima"
                        type="date"
                        wire:model="tanggalDiterima"
                        class="mt-1"
                        required
                    />
                    <x-ui.form-error for="tanggalDiterima" />
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Section 2: Konten, Pengirim & Urgensi --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border pb-3">
                <x-ui.card-title class="text-sm font-semibold flex items-center gap-2">
                    <x-lucide-file-text class="size-4 text-primary" />
                    <span>Konten &amp; Pengirim</span>
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4 space-y-4 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="pengirim">Instansi / Organisasi Pengirim</x-ui.label>
                        <x-ui.input
                            id="pengirim"
                            wire:model="pengirim"
                            class="mt-1 font-medium"
                            placeholder="Mis. BEM Politeknik, BAAK, Himpunan Mahasiswa..."
                            required
                        />
                        <x-ui.form-error for="pengirim" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-ui.label for="sifatSurat">Sifat Surat</x-ui.label>
                            <x-ui.select id="sifatSurat" wire:model="sifatSurat" class="mt-1 font-medium">
                                @foreach ($sifatOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.form-error for="sifatSurat" />
                        </div>

                        <div>
                            <x-ui.label for="status">Status Surat</x-ui.label>
                            <x-ui.select id="status" wire:model="status" class="mt-1 font-medium">
                                @foreach ($statusOptions as $sVal => $sLbl)
                                    <option value="{{ $sVal }}">{{ $sLbl }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.form-error for="status" />
                        </div>
                    </div>
                </div>

                <div>
                    <x-ui.label for="perihal">Perihal Surat</x-ui.label>
                    <x-ui.input
                        id="perihal"
                        wire:model="perihal"
                        class="mt-1 font-medium"
                        placeholder="Mis. Undangan Rapat Koordinasi, Permohonan Delegasi Peserta..."
                        required
                    />
                    <x-ui.form-error for="perihal" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="tanggalAcaraDeadline">Tanggal Pelaksanaan / Batas Waktu (Opsional)</x-ui.label>
                        <x-ui.input
                            id="tanggalAcaraDeadline"
                            type="date"
                            wire:model="tanggalAcaraDeadline"
                            class="mt-1"
                        />
                        <p class="text-[11px] text-muted-foreground mt-1">Gunakan untuk menandai hari H acara atau batas konfirmasi.</p>
                        <x-ui.form-error for="tanggalAcaraDeadline" />
                    </div>

                    <div>
                        <x-ui.label for="ringkasan">Ringkasan / Catatan Isi (Opsional)</x-ui.label>
                        <textarea
                            id="ringkasan"
                            wire:model="ringkasan"
                            rows="2"
                            placeholder="Catatan ringkas isi surat atau arahan awal..."
                            class="w-full mt-1 px-3 py-2 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <x-ui.form-error for="ringkasan" />
                    </div>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Section: Tautan Surat Balasan / Relasi (Opsional) --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border pb-3">
                <x-ui.card-title class="text-sm font-semibold flex items-center gap-2">
                    <x-lucide-reply class="size-4 text-emerald-600" />
                    <span>Surat Keluar Balasan (Opsional)</span>
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4 space-y-4 text-xs">
                <p class="text-muted-foreground">
                    Jika surat masuk ini merupakan balasan dari surat keluar yang kita kirimkan, atau jika Anda sudah menyiapkan surat keluar balasan, tautkan di sini.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <x-ui.label>Surat Keluar Terkait di Workspace Ini</x-ui.label>
                        @if ($selectedLetter)
                            <div class="p-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-white dark:bg-card space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        {{ $selectedLetter->formatted_number ?? 'Draf' }}
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="openSelectLetterModal"
                                            class="text-xs text-primary hover:underline cursor-pointer"
                                        >
                                            Ganti
                                        </button>
                                        <span class="text-muted-foreground text-xs">•</span>
                                        <button
                                            type="button"
                                            wire:click="removeSuratKeluar"
                                            class="text-xs text-destructive hover:underline cursor-pointer"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                                <p class="text-foreground font-medium line-clamp-2">
                                    {{ $selectedLetter->perihal }}
                                </p>
                            </div>
                        @else
                            <div>
                                <x-ui.button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    wire:click="openSelectLetterModal"
                                    class="w-full justify-start gap-2 border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300"
                                >
                                    <x-lucide-search class="size-3.5" />
                                    <span>Cari &amp; Pilih Surat Keluar...</span>
                                </x-ui.button>
                            </div>
                        @endif
                        <x-ui.form-error for="suratKeluarId" />
                    </div>

                    <div>
                        <x-ui.label for="nomorSuratBalasan">Nomor Surat Balasan Manual / Eksternal</x-ui.label>
                        <x-ui.input
                            id="nomorSuratBalasan"
                            wire:model="nomorSuratBalasan"
                            placeholder="Mis. 012/HIMATRIS/VI/2026 atau No. Resi"
                            class="mt-1"
                        />
                        <x-ui.form-error for="nomorSuratBalasan" />
                    </div>
                </div>

                <div>
                    <x-ui.label for="catatanBalasan">Catatan Tindak Lanjut Balasan</x-ui.label>
                    <textarea
                        id="catatanBalasan"
                        wire:model="catatanBalasan"
                        rows="2"
                        placeholder="Mis. Draf balasan sudah diajukan ke Ketua, atau balasan telah dikonfirmasi via WA panitia."
                        class="w-full mt-1 px-3 py-2 rounded-md border border-input bg-background text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                    ></textarea>
                    <x-ui.form-error for="catatanBalasan" />
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Section 3: Akses Dokumen (Upload Berkas & Link Drive Fleksibel) --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border pb-3">
                <div class="flex items-center justify-between">
                    <x-ui.card-title class="text-sm font-semibold flex items-center gap-2">
                        <x-lucide-paperclip class="size-4 text-primary" />
                        <span>Dokumen Surat Masuk</span>
                    </x-ui.card-title>
                    <span class="text-[11px] text-muted-foreground font-medium">
                        * Minimal isi salah satu (Upload atau Link)
                    </span>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4 space-y-5 text-xs">
                {{-- Opsi A: Upload File Lokal --}}
                <div class="space-y-2">
                    <x-ui.label for="fileInput">1. Unggah Berkas Arsip (PDF, Gambar JPG/PNG, DOCX - Maks 20 MB)</x-ui.label>

                    @if ($existingFilePath)
                        <div class="flex items-center justify-between p-2.5 rounded-lg border border-border bg-muted/30">
                            <div class="flex items-center gap-2">
                                <x-lucide-file class="size-4 text-primary" />
                                <span class="font-medium text-foreground">{{ $existingFileName ?: basename($existingFilePath) }}</span>
                                <span class="text-[10px] text-muted-foreground">(Berkas tersimpan)</span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">Pilih file baru di bawah jika ingin mengganti</span>
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <input
                            type="file"
                            id="fileInput"
                            wire:model="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.docx"
                            class="block w-full text-xs text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                        />
                    </div>
                    <div wire:loading wire:target="file" class="text-[11px] text-primary flex items-center gap-1.5">
                        <div class="size-3 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <span>Mengunggah berkas...</span>
                    </div>
                    <x-ui.form-error for="file" />
                </div>

                <div class="relative flex py-1 items-center">
                    <div class="flex-grow border-t border-border"></div>
                    <span class="flex-shrink mx-4 text-[10px] font-semibold text-muted-foreground uppercase">DAN / ATAU</span>
                    <div class="flex-grow border-t border-border"></div>
                </div>

                {{-- Opsi B: Link Eksternal Google Drive / Cloud --}}
                <div class="space-y-1">
                    <x-ui.label for="documentUrl">2. Tautan Dokumen Luar (Google Drive, Dropbox, Cloud Storage, dsb.)</x-ui.label>
                    <div class="relative">
                        <x-lucide-link class="size-4 text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <x-ui.input
                            id="documentUrl"
                            type="url"
                            wire:model="documentUrl"
                            placeholder="https://drive.google.com/file/d/... atau https://dropbox.com/..."
                            class="pl-9 text-xs"
                        />
                    </div>
                    <p class="text-[11px] text-muted-foreground mt-1">
                        Sangat berguna untuk berkas scan resolusi tinggi di Google Drive panitia / fakultas.
                    </p>
                    <x-ui.form-error for="documentUrl" />
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Footer Submit --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <x-ui.button
                as="a"
                href="{{ $suratMasukId ? route('surat-masuk.show', $suratMasukId) : route('surat-masuk.index') }}"
                variant="outline"
            >
                Batal
            </x-ui.button>
            <x-ui.button type="submit" wire:loading.attr="disabled" class="gap-1.5">
                <x-lucide-save class="size-4" />
                <span>{{ $suratMasukId ? 'Perbarui Surat Masuk' : 'Simpan Surat Masuk' }}</span>
            </x-ui.button>
        </div>
    </form>

    {{-- MODAL PENCARIAN SURAT KELUAR --}}
    @if ($showSelectLetterModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-xl rounded-xl border border-border bg-background p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-border pb-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <x-lucide-reply class="size-4 text-emerald-600" />
                        <h3 class="text-base font-semibold text-foreground">Cari Surat Keluar Terkait</h3>
                    </div>
                    <button type="button" wire:click="closeSelectLetterModal" class="text-muted-foreground hover:text-foreground cursor-pointer">
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
                        Pilih surat keluar yang berhubungan dengan surat masuk ini.
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
                                @if ((int) $suratKeluarId === (int) $letter->id)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 px-2 py-1 rounded bg-emerald-50 dark:bg-emerald-950/50">
                                        <x-lucide-check class="size-3" />
                                        <span>Terpilih</span>
                                    </span>
                                @else
                                    <x-ui.button
                                        type="button"
                                        size="xs"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                        wire:click="selectSuratKeluar({{ $letter->id }})"
                                    >
                                        Pilih
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
                    <x-ui.button type="button" variant="outline" size="xs" wire:click="closeSelectLetterModal">
                        Tutup
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
