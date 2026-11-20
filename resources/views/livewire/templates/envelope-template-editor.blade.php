<div class="h-screen flex flex-col bg-slate-100 dark:bg-slate-900 overflow-hidden" x-data="{ showVars: false }">
    <!-- Top Bar -->
    <header class="h-14 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-4 flex items-center justify-between z-10">
        <div class="flex items-center gap-3">
            <a
                href="{{ route('settings.envelopes') }}"
                @click.prevent="window.SuratinAjaNav ? window.SuratinAjaNav.goBack('{{ route('settings.envelopes') }}') : (window.location.href = '{{ route('settings.envelopes') }}')"
                class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                title="Kembali ke halaman sebelumnya"
                aria-label="Kembali ke halaman sebelumnya"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-0.5 rounded-md font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                    {{ strtoupper($envelopeTemplate->preset_size) }}
                </span>
                <h1 class="text-sm font-bold text-slate-800 dark:text-white truncate max-w-xs md:max-w-md">
                    {{ $envelopeTemplate->name }}
                </h1>
                <span class="text-[11px] text-slate-400 hidden sm:inline">&bull; v{{ $envelopeTemplate->version }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button @click="showVars = !showVars" type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:hover:bg-indigo-900 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition">
                <svg class="w-4 h-4 mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                Variabel Amplop
            </button>
            <a
                href="{{ route('settings.envelopes') }}"
                @click.prevent="window.SuratinAjaNav ? window.SuratinAjaNav.goBack('{{ route('settings.envelopes') }}') : (window.location.href = '{{ route('settings.envelopes') }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 transition cursor-pointer"
            >
                Selesai & Tutup
            </a>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="flex-1 relative flex overflow-hidden">
        <!-- OnlyOffice Editor Container -->
        <div class="flex-1 h-full relative" id="onlyoffice-envelope-editor-container">
            <div id="onlyoffice-envelope-editor" class="w-full h-full"></div>
        </div>

        <!-- Variable Slide-over panel -->
        <div x-show="showVars" x-cloak class="w-80 bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 p-4 overflow-y-auto space-y-4 shadow-xl z-20">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Variabel Penerima Amplop</h3>
                <button @click="showVars = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Salin teks tag variabel berikut lalu tempelkan (paste) langsung ke dokumen Word amplop di OnlyOffice:
            </p>

            <div class="space-y-2 text-xs">
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${kepada}</div>
                        <div class="text-[11px] text-slate-500">Nama Lengkap Penerima</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${kepada}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${instansi}</div>
                        <div class="text-[11px] text-slate-500">Instansi / Lembaga</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${instansi}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${alamat}</div>
                        <div class="text-[11px] text-slate-500">Alamat Lengkap Tujuan</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${alamat}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${tujuan}</div>
                        <div class="text-[11px] text-slate-500">Kota / Wilayah Tujuan</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${tujuan}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${nomor_surat}</div>
                        <div class="text-[11px] text-slate-500">Nomor Surat Keluar</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${nomor_surat}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${perihal}</div>
                        <div class="text-[11px] text-slate-500">Perihal / Hal Surat</div>
                    </div>
                    <button type="button" @click="navigator.clipboard.writeText('${perihal}')" class="px-2 py-1 text-[10px] font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md hover:bg-slate-50 text-slate-600">Salin</button>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300 space-y-1">
                <span class="font-bold">Tips Penataan:</span>
                <p>Kop instansi diletakkan di sudut kiri atas. Kolom penerima (Kepada Yth) umumnya diletakkan di area kanan bawah atau tengah kanan menggunakan tabel tanpa garis atau kotak teks.</p>
            </div>
        </div>
    </div>

    <!-- OnlyOffice Script Initialization -->
    <script src="{{ $docServerUrl }}/web-apps/apps/api/documents/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const config = @js($editorConfig);
            if (window.DocsAPI && window.DocsAPI.DocEditor) {
                new DocsAPI.DocEditor("onlyoffice-envelope-editor", config);
            } else {
                console.warn("OnlyOffice Document Server API not available.");
            }
        });
    </script>
</div>
