<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 rounded-xl text-sm flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">&times;</button>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Master Template Kop Amplop (.docx)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Kustomisasi tata letak kop amplop, logo instansi, dan posisi tujuan penerima langsung di <strong>OnlyOffice</strong> secara visual.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div x-data="{ openStarter: false }" class="relative">
                <button @click="openStarter = !openStarter" type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-xl text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                    <svg class="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Buat dari Starter
                    <svg class="w-4 h-4 ml-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openStarter" @click.away="openStarter = false" x-cloak class="absolute right-0 mt-2 w-72 rounded-xl shadow-lg bg-white dark:bg-slate-800 ring-1 ring-black ring-opacity-5 z-20 p-2 space-y-1">
                    <button wire:click="createStarter('dl')" @click="openStarter = false" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-slate-700 dark:text-slate-200 flex flex-col">
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">Amplop Standar DL (No. 90)</span>
                        <span class="text-slate-400 text-[11px]">230 mm x 110 mm (Landscape) - Ukuran umum surat</span>
                    </button>
                    <button wire:click="createStarter('c5')" @click="openStarter = false" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-slate-700 dark:text-slate-200 flex flex-col">
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">Amplop Sedang C5</span>
                        <span class="text-slate-400 text-[11px]">229 mm x 162 mm (Landscape) - Muat kertas A4 lipat dua</span>
                    </button>
                    <button wire:click="createStarter('a4')" @click="openStarter = false" class="w-full text-left px-3 py-2 text-xs rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/40 text-slate-700 dark:text-slate-200 flex flex-col">
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">Amplop Cokelat / Map A4</span>
                        <span class="text-slate-400 text-[11px]">210 mm x 297 mm (Portrait) - Amplop berkas tebal</span>
                    </button>
                </div>
            </div>

            <button wire:click="$set('showUploadModal', true)" type="button" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Unggah File .docx
            </button>
        </div>
    </div>

    <!-- Variable Syntax Reference Box -->
    <div class="bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl p-5">
        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-300 flex items-center gap-2 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Variabel Placeholder untuk Template Amplop
        </h3>
        <p class="text-xs text-indigo-700 dark:text-indigo-400 mb-3">
            Gunakan format sintaks <code>${variabel}</code> di dalam file <code>.docx</code> amplop Anda. Sistem akan otomatis mengganti variabel ini saat mencetak amplop:
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 text-xs">
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${kepada}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Nama penerima</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${instansi}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Instansi / Organisasi</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${alamat}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Alamat lengkap</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${tujuan}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Kota / Tempat tujuan</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${nomor_surat}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Nomor surat keluar</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-2 rounded-lg border border-indigo-100 dark:border-indigo-900">
                <code class="text-indigo-600 dark:text-indigo-400 font-mono font-bold">${perihal}</code>
                <p class="text-[11px] text-slate-500 mt-0.5">Perihal surat</p>
            </div>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($templates as $tmpl)
            <div class="bg-white dark:bg-slate-800 border {{ $tmpl->is_default ? 'border-emerald-500 ring-2 ring-emerald-500/20 shadow-md' : 'border-slate-200 dark:border-slate-700' }} rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between mb-3 gap-2">
                        @if ($tmpl->is_default)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                ★ Template Aktif (Default)
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ strtoupper($tmpl->preset_size) }}
                            </span>
                        @endif
                        <span class="text-[11px] text-slate-400 font-mono">v{{ $tmpl->version }}</span>
                    </div>

                    <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">
                        {{ $tmpl->name }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Ukuran: <strong class="text-slate-700 dark:text-slate-300">{{ $tmpl->sizeLabel() }}</strong>
                    </p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                        Dimodifikasi {{ $tmpl->updated_at->diffForHumans() }}
                    </p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2 flex-wrap">
                    <a href="{{ route('settings.envelopes.edit', $tmpl) }}" class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit di OnlyOffice
                    </a>

                    <div class="flex items-center gap-2">
                        @if (! $tmpl->is_default)
                            <button
                                wire:click="setDefault({{ $tmpl->id }})"
                                type="button"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 hover:border-emerald-300 transition"
                                title="Jadikan template ini sebagai default"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Pilih Jadi Default</span>
                            </button>
                        @endif
                        <button wire:click="deleteTemplate({{ $tmpl->id }})" wire:confirm="Hapus template amplop ini?" title="Hapus template" class="text-xs text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 dark:hover:bg-red-950 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700">
                <svg class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Belum ada template amplop</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Buat otomatis dari starter preset DL / C5 atau unggah file .docx Anda.</p>
                <div class="mt-4">
                    <button wire:click="createStarter('dl')" class="inline-flex items-center px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition">
                        Buat Starter DL Standar Sekarang
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Upload Modal -->
    @if ($showUploadModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Unggah Master Template (.docx)</h3>
                    <button wire:click="$set('showUploadModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form wire:submit.prevent="uploadTemplate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Template</label>
                        <input type="text" wire:model="templateName" placeholder="Contoh: Kop Amplop Resmi 2026" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        @error('templateName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ukuran Kertas</label>
                        <select wire:model="paperSize" class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="dl">DL / No. 90 (230 x 110 mm)</option>
                            <option value="c5">C5 (229 x 162 mm)</option>
                            <option value="a4">A4 / Map (210 x 297 mm)</option>
                            <option value="custom">Kustom</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pilih File .docx</label>
                        <input type="file" wire:model="docxFile" accept=".docx" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        @error('docxFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" wire:click="$set('showUploadModal', false)" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-800">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition disabled:opacity-50">
                            Simpan & Buka Editor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
