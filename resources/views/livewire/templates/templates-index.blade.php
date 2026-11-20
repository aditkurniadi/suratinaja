<div class="space-y-6">
    <x-ui.card>
        <x-ui.card-header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
            <div class="space-y-1">
                <x-ui.card-title>Library Master Template Word (.docx)</x-ui.card-title>
                <x-ui.card-description>
                    Buat desain surat di Microsoft Word (dengan kop mandiri maupun tanpa kop), gunakan variabel kustom &amp; tabel multi-kolom bebas, lalu unggah ke workspace {{ $workspace?->name }}.
                </x-ui.card-description>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="outline" size="sm" wire:click="$set('showGuideModal', true)">
                    <x-lucide-book-open class="size-4" />
                    <span>Panduan Variabel &amp; Tabel Word</span>
                </x-ui.button>
                @if ($role?->canWrite())
                    <x-ui.button variant="outline" size="sm" wire:click="$set('showUploadModal', true)">
                        <x-lucide-upload class="size-4" />
                        <span>Unggah .docx</span>
                    </x-ui.button>
                    <x-ui.button size="sm" wire:click="$set('showCreateModal', true)">
                        <x-lucide-file-pen-line class="size-4" />
                        <span>Buat di OnlyOffice</span>
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4 space-y-4">
            {{-- Tabs --}}
            <div class="inline-flex rounded-lg border border-border bg-muted p-1 text-xs">
                <button
                    type="button"
                    wire:click="$set('activeTab', 'workspace')"
                    @class([
                        'rounded-md px-3 py-1.5 font-medium transition-colors',
                        'bg-background text-foreground shadow-2xs' => $activeTab === 'workspace',
                        'text-muted-foreground hover:text-foreground' => $activeTab !== 'workspace',
                    ])
                >
                    Template Workspace ({{ $workspaceTemplates->count() }})
                </button>
                <button
                    type="button"
                    wire:click="$set('activeTab', 'global')"
                    @class([
                        'rounded-md px-3 py-1.5 font-medium transition-colors',
                        'bg-background text-foreground shadow-2xs' => $activeTab === 'global',
                        'text-muted-foreground hover:text-foreground' => $activeTab !== 'global',
                    ])
                >
                    Template Global Read-Only ({{ $globalTemplates->count() }})
                </button>
            </div>

            @php
                $list = $activeTab === 'workspace' ? $workspaceTemplates : $globalTemplates;
            @endphp

            @if ($list->isEmpty())
                <x-ui.empty-state
                    icon="file-code-2"
                    title="Belum ada template pada tab ini"
                    description="Unggah dokumen .docx buatan Anda sendiri dengan variabel kustom seperti ${keperluan} atau tabel ${peserta.nama}, atau duplikasi dari Template Global."
                >
                    @if ($activeTab === 'workspace' && $role?->canWrite())
                        <div class="flex items-center justify-center gap-2">
                            <x-ui.button size="sm" variant="outline" wire:click="$set('activeTab', 'global')">
                                Lihat Template Global
                            </x-ui.button>
                            <x-ui.button size="sm" wire:click="$set('showUploadModal', true)">
                                Unggah Template .docx
                            </x-ui.button>
                        </div>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3 min-w-[180px]">Nama Template</th>
                                <th class="py-2.5 px-3 w-[160px]">Mode Kop Surat</th>
                                <th class="py-2.5 px-3">Variabel &amp; Tabel Terdeteksi (${...})</th>
                                <th class="py-2.5 px-3 w-[60px] text-center hidden sm:table-cell">Versi</th>
                                <th class="py-2.5 px-3 text-right w-[180px] shrink-0">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($list as $tpl)
                                <tr class="hover:bg-muted/40">
                                    <td class="py-3 px-3">
                                        <div class="font-medium flex items-center gap-2">
                                            <span>{{ $tpl->name }}</span>
                                            @if ($tpl->is_deprecated)
                                                <x-ui.badge variant="outline" class="text-amber-600 border-amber-500/30 text-[10px]">Diarsipkan</x-ui.badge>
                                            @endif
                                        </div>
                                        @if ($tpl->description)
                                            <p class="text-xs text-muted-foreground mt-0.5">{{ $tpl->description }}</p>
                                        @endif
                                        <div class="mt-1 flex items-center gap-2">
                                            @php
                                                $usageLetters = $tpl->letters()->count();
                                                $usageBulk = $tpl->bulkJobs()->count();
                                            @endphp
                                            @if ($usageLetters > 0 || $usageBulk > 0)
                                                <span class="inline-flex items-center gap-1 text-[10px] text-muted-foreground bg-muted/80 px-2 py-0.5 rounded font-mono" title="Sudah digunakan oleh {{ $usageLetters }} surat & {{ $usageBulk }} job bulk.">
                                                    <x-lucide-file-text class="size-3" />
                                                    <span>{{ $usageLetters }} Surat Terdaftar</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded font-medium">
                                                    <x-lucide-check class="size-3" />
                                                    <span>Belum digunakan (Bisa Dihapus)</span>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex flex-col items-start gap-1">
                                            @if ($tpl->has_embedded_kop)
                                                <x-ui.badge variant="primary">Kop Mandiri (di Word)</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary">Kop Resmi Workspace</x-ui.badge>
                                            @endif
                                            @if (! $tpl->isGlobal() && $role?->canWrite())
                                                <button
                                                    type="button"
                                                    wire:click="toggleEmbeddedKop({{ $tpl->id }})"
                                                    class="text-[11px] text-primary hover:underline"
                                                >
                                                    Ubah ke {{ $tpl->has_embedded_kop ? 'Kop Workspace' : 'Kop Mandiri' }}
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($tpl->variables_schema ?? [] as $var)
                                                @if (($var['type'] ?? '') === 'repeater')
                                                    @php
                                                        $colCount = count($var['columns'] ?? []);
                                                        $colList = implode(', ', array_column($var['columns'] ?? [], 'name'));
                                                    @endphp
                                                    <span class="rounded border border-primary/40 bg-primary/5 px-1.5 py-0.5 font-mono text-[11px] text-foreground" title="Kolom: {{ $colList }}">
                                                        ${{ '{' . $var['name'] . '.*' . '}' }}
                                                        <span class="text-primary font-semibold">(tabel {{ $colCount }} kolom)</span>
                                                    </span>
                                                @else
                                                    <span class="rounded border border-border bg-muted/60 px-1.5 py-0.5 font-mono text-[11px]">
                                                        ${{ '{' . $var['name'] . '}' }}
                                                        <span class="text-muted-foreground">({{ $var['type'] }})</span>
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-xs font-mono text-center text-muted-foreground hidden sm:table-cell">
                                        v{{ $tpl->version }}
                                    </td>
                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            @if (! $tpl->isGlobal() && $role?->canWrite())
                                                <x-ui.button variant="outline" size="sm" :href="route('templates.edit', $tpl->id)" title="Edit Template &amp; Sisipkan Variabel di OnlyOffice">
                                                    <x-lucide-file-pen-line class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                    <span>Edit</span>
                                                </x-ui.button>
                                            @elseif ($tpl->isGlobal() && auth()->user()?->is_super_admin)
                                                <x-ui.button variant="outline" size="sm" :href="route('templates.edit', $tpl->id)" title="Edit Template Global di OnlyOffice">
                                                    <x-lucide-file-pen-line class="size-3.5 text-blue-600 dark:text-blue-400" />
                                                    <span>Edit</span>
                                                </x-ui.button>
                                            @else
                                                <x-ui.button variant="ghost" size="sm" :href="route('templates.edit', $tpl->id)" title="Pratinjau Template di OnlyOffice">
                                                    <x-lucide-eye class="size-3.5" />
                                                    <span class="hidden sm:inline">Lihat</span>
                                                </x-ui.button>
                                            @endif

                                            <x-ui.button variant="ghost" size="sm" :href="route('templates.download', $tpl->id)" title="Unduh Master Word">
                                                <x-lucide-download class="size-4" />
                                                <span class="hidden sm:inline">.docx</span>
                                            </x-ui.button>
                                            @if ($role?->canWrite())
                                                @if ($tpl->isGlobal())
                                                    <x-ui.button variant="outline" size="sm" wire:click="duplicateGlobal({{ $tpl->id }})">
                                                        Duplikasi
                                                    </x-ui.button>
                                                @else
                                                    @if ($usageLetters > 0 || $usageBulk > 0)
                                                        <button
                                                            type="button"
                                                            wire:click="deprecateWorkspaceTemplate({{ $tpl->id }})"
                                                            class="p-1.5 rounded-md text-muted-foreground hover:text-amber-600 hover:bg-amber-500/10 transition cursor-pointer"
                                                            title="{{ $tpl->is_deprecated ? 'Batal Arsipkan (Aktifkan Kembali)' : 'Arsipkan Template (Sembunyikan dari Pembuatan Surat)' }}"
                                                        >
                                                            <x-lucide-archive class="size-4" />
                                                        </button>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        wire:click="confirmDelete({{ $tpl->id }})"
                                                        class="p-1.5 rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition cursor-pointer"
                                                        title="Hapus Template"
                                                    >
                                                        <x-lucide-trash-2 class="size-4" />
                                                    </button>
                                                @endif
                                                <x-ui.button variant="primary" size="sm" :href="route('letters.create', ['template_id' => $tpl->id])">
                                                    Pakai Surat
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- Upload & Variable Preview Dialog --}}
    @if ($showUploadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="w-full max-w-xl rounded-xl border border-border bg-background p-6 shadow-xl my-8">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold">Unggah Master Template Word (.docx)</h3>
                    <button type="button" wire:click="$set('showUploadModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Sistem otomatis memindai variabel kustom <code class="font-mono">${nama_variabel}</code> dan tabel multi-kolom <code class="font-mono">${tabel.kolom}</code> di dalam dokumen Word Anda.
                </p>

                @if ($uploadError)
                    <div class="mt-3 rounded-lg border border-destructive/40 bg-destructive/10 p-3.5 space-y-2">
                        <div class="flex items-start gap-2">
                            <x-lucide-alert-circle class="size-4 text-destructive shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <p class="text-xs font-semibold text-destructive">Validasi Template Belum Berhasil</p>
                                <p class="text-xs text-foreground leading-relaxed">{{ $uploadError }}</p>
                            </div>
                        </div>
                        <div class="pt-1 flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="$set('showGuideModal', true)"
                                class="text-xs text-primary font-medium hover:underline inline-flex items-center gap-1"
                            >
                                <x-lucide-help-circle class="size-3.5" />
                                <span>Lihat Panduan &amp; Contoh Format Variabel Word</span>
                            </button>
                        </div>
                    </div>
                @endif

                <form wire:submit="saveTemplate" class="mt-4 space-y-4">
                    <div
                        x-data="{ isUploading: false }"
                        x-on:livewire-upload-start="isUploading = true"
                        x-on:livewire-upload-finish="isUploading = false"
                        x-on:livewire-upload-error="isUploading = false; $wire.set('uploadError', 'Unggah berkas gagal atau terputus. Pastikan file berformat .docx valid dan ukuran file di bawah 50 MB.');"
                    >
                        <x-ui.label for="templateFile">File Master Template (.docx)</x-ui.label>
                        <x-ui.input
                            id="templateFile"
                            type="file"
                            wire:model="templateFile"
                            accept=".docx"
                            class="mt-1.5"
                            x-on:change="$wire.set('detectedVariables', []); $wire.set('uploadError', null); $wire.set('templateName', '');"
                            required
                        />
                        <div x-show="isUploading" class="mt-2 flex items-center gap-2 text-xs text-primary font-medium">
                            <x-lucide-loader-2 class="size-4 animate-spin" />
                            <span>Sedang mengunggah dan memindai dokumen Word... Mohon tunggu sebentar.</span>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Maksimal ukuran file 50 MB (mendukung dokumen multi-halaman dengan tabel rundown dan lampiran).
                        </p>
                        <x-ui.form-error for="templateFile" />
                    </div>

                    <div>
                        <x-ui.label for="templateName">Nama Jenis Surat / Template</x-ui.label>
                        <x-ui.input id="templateName" wire:model="templateName" class="mt-1.5" placeholder="Mis. Surat Tugas / Surat Keputusan / Surat Dispensasi" required />
                        <x-ui.form-error for="templateName" />
                    </div>

                    <div>
                        <x-ui.label for="templateDescription">Deskripsi Singkat (Opsional)</x-ui.label>
                        <x-ui.input id="templateDescription" wire:model="templateDescription" class="mt-1.5" placeholder="Mis. Template surat dispensasi kuliah dengan tabel 4 kolom peserta" />
                    </div>

                    {{-- Pilihan Mode Kop Surat --}}
                    <div class="space-y-2">
                        <x-ui.label>Pengaturan Kop Surat pada File Word Ini</x-ui.label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => ! $hasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => $hasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="hasEmbeddedKop" value="0" class="text-primary focus:ring-primary" />
                                    <span class="text-xs font-semibold text-foreground">Belum Ada Kop (Pakai Kop Sistem)</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    File Word hanya berisi badan surat. Sistem akan otomatis menyisipkan Kop Resmi Workspace di bagian atas.
                                </p>
                            </label>

                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $hasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => ! $hasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="hasEmbeddedKop" value="1" class="text-primary focus:ring-primary" />
                                    <span class="text-xs font-semibold text-foreground">Sudah Ada Kop di Word (Mandiri)</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    File Word sudah memiliki desain kop surat sendiri. Sistem tidak akan menyisipkan kop agar tidak dobel.
                                </p>
                            </label>
                        </div>
                    </div>

                    @if (! empty($detectedVariables))
                        <div class="rounded-lg border border-border bg-muted/40 p-3 space-y-2">
                            <p class="text-xs font-semibold text-foreground">
                                Pratinjau Variabel &amp; Tabel Terdeteksi ({{ count($detectedVariables) }} item)
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($detectedVariables as $v)
                                    @if (($v['type'] ?? '') === 'repeater')
                                        <div class="w-full rounded border border-primary/30 bg-background p-2 text-xs space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-mono font-semibold text-primary">${{ '{' . $v['name'] . '.*' . '}' }}</span>
                                                <span class="text-[10px] uppercase font-medium bg-primary/10 text-primary px-1.5 py-0.5 rounded">
                                                    Tabel Dinamis ({{ count($v['columns'] ?? []) }} Kolom)
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-muted-foreground">
                                                Kolom:
                                                @foreach ($v['columns'] ?? [] as $col)
                                                    <code class="font-mono bg-muted px-1 py-0.5 rounded text-foreground">{{ $col['label'] }} (${{ '{' . $col['token'] . '}' }})</code>@if (! $loop->last), @endif
                                                @endforeach
                                            </p>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded border border-border bg-background px-2 py-0.5 font-mono text-xs">
                                            <span>${{ '{' . $v['name'] . '}' }}</span>
                                            <span class="text-[10px] uppercase text-muted-foreground">{{ $v['type'] }}</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @elseif ($templateFile && empty($uploadError))
                        <div class="rounded-lg border border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-950/20 p-3.5 space-y-2">
                            <div class="flex items-start gap-2">
                                <x-lucide-sparkles class="size-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Dokumen Belum Memiliki Variabel</p>
                                    <p class="text-xs text-foreground leading-relaxed">
                                        Dokumen Word ini belum memiliki tag variabel seperti <code class="font-mono text-foreground font-medium">${tujuan}</code> atau <code class="font-mono text-foreground font-medium">${tanggal}</code>. Dokumen akan langsung dibuka di <strong>Editor OnlyOffice</strong> setelah disimpan agar Anda dapat menyisipkan variabel dengan 1-klik.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between pt-2 border-t border-border">
                        <button
                            type="button"
                            wire:click="$set('showGuideModal', true)"
                            class="text-xs text-primary hover:underline inline-flex items-center gap-1"
                        >
                            <x-lucide-help-circle class="size-3.5" />
                            <span>Lihat cara penulisan variabel &amp; tabel di Word</span>
                        </button>
                        <div class="flex gap-2">
                            <x-ui.button variant="outline" wire:click="$set('showUploadModal', false)">Batal</x-ui.button>
                            <x-ui.button type="submit" class="gap-1.5" :disabled="!$templateFile">
                                @if (empty($detectedVariables) && $templateFile)
                                    <x-lucide-file-pen-line class="size-4" />
                                    <span>Unggah &amp; Buka di Editor</span>
                                @else
                                    <span>Simpan Template</span>
                                @endif
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Buat Template Baru Langsung di OnlyOffice --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="w-full max-w-xl rounded-xl border border-border bg-background p-6 shadow-xl my-8">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div>
                        <h3 class="text-base font-semibold">Buat Template Baru di OnlyOffice</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Desain template surat langsung di browser lengkap dengan panel 1-klik variabel sistem &amp; kustom.
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                @if ($createError)
                    <x-ui.alert variant="destructive" class="mt-3">
                        {{ $createError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="createInOnlyOffice" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="newTplName">Nama Template</x-ui.label>
                        <x-ui.input id="newTplName" wire:model="newTemplateName" class="mt-1.5" placeholder="Mis. Surat Tugas Panitia / Surat Undangan Rapat" required />
                        <x-ui.form-error for="newTemplateName" />
                    </div>

                    <div>
                        <x-ui.label for="newTplDesc">Deskripsi Singkat (Opsional)</x-ui.label>
                        <x-ui.input id="newTplDesc" wire:model="newTemplateDescription" class="mt-1.5" placeholder="Mis. Template surat undangan rapat koordinasi internal" />
                    </div>

                    <div class="space-y-2">
                        <x-ui.label>Mulai Dari</x-ui.label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $newStarterMode === 'starter',
                                    'border-border hover:bg-muted/40' => $newStarterMode !== 'starter',
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="newStarterMode" value="starter" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Kerangka Standar</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    Format surat resmi lengkap Times New Roman 12pt.
                                </p>
                            </label>

                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $newStarterMode === 'blank',
                                    'border-border hover:bg-muted/40' => $newStarterMode !== 'blank',
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="newStarterMode" value="blank" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Dokumen Kosong A4</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    Halaman kosong A4. Desain bebas dari nol di editor.
                                </p>
                            </label>

                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $newStarterMode === 'upload',
                                    'border-border hover:bg-muted/40' => $newStarterMode !== 'upload',
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="newStarterMode" value="upload" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Unggah Word Saya</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    Pilih file .docx Anda, langsung dibuka di OnlyOffice.
                                </p>
                            </label>
                        </div>
                    </div>

                    @if ($newStarterMode === 'upload')
                        <div>
                            <x-ui.label for="newTemplateFile">Pilih Berkas Word (.docx) <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input
                                id="newTemplateFile"
                                type="file"
                                wire:model="newTemplateFile"
                                accept=".docx"
                                class="mt-1.5"
                                required
                            />
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Dokumen Word akan diunggah dan langsung dibuka di OnlyOffice untuk penambahan variabel.
                            </p>
                            <x-ui.form-error for="newTemplateFile" />
                        </div>
                    @endif

                    <div class="space-y-2">
                        <x-ui.label>Mode Kop Surat</x-ui.label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => ! $newHasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => $newHasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="newHasEmbeddedKop" value="0" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Pakai Kop Resmi Workspace</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    Kop surat disisipkan otomatis oleh sistem saat surat dibuat.
                                </p>
                            </label>

                            <label
                                @class([
                                    'flex flex-col gap-1 rounded-lg border p-3 cursor-pointer transition-colors',
                                    'border-primary bg-primary/5 ring-1 ring-primary' => $newHasEmbeddedKop,
                                    'border-border hover:bg-muted/40' => ! $newHasEmbeddedKop,
                                ])
                            >
                                <div class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="newHasEmbeddedKop" value="1" class="text-primary focus:ring-primary" />
                                    <span class="font-semibold text-foreground">Desain Kop Sendiri di Word</span>
                                </div>
                                <p class="text-[11px] text-muted-foreground pl-5">
                                    Anda akan mendesain header kop surat langsung di dokumen OnlyOffice.
                                </p>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showCreateModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit" class="gap-1.5">
                            <x-lucide-file-pen-line class="size-4" />
                            <span>Buat &amp; Buka di OnlyOffice</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Guide / Cheatsheet Modal --}}
    @if ($showGuideModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-xl border border-border bg-background p-6 shadow-xl my-8 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div>
                        <h3 class="text-base font-semibold">Panduan Pembuatan Master Template di Microsoft Word</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Anda bebas mendesain jenis surat apa pun di Word dan menghubungkannya dengan form SuratinAja.
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showGuideModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="space-y-4 text-xs leading-relaxed">
                    <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-1.5">
                        <h4 class="font-semibold text-foreground text-sm">1. Variabel Kustom Bebas (Otomatis Jadi Input Form)</h4>
                        <p class="text-muted-foreground">
                            Ketik <code class="font-mono font-semibold text-foreground">${nama_variabel}</code> di mana saja pada dokumen Word. Saat diunggah, sistem otomatis mengubahnya menjadi kolom input di form pembuatan surat:
                        </p>
                        <ul class="list-disc pl-5 space-y-1 text-muted-foreground">
                            <li><code class="font-mono text-foreground">${keperluan}</code> &rarr; menjadi input teks <strong>Keperluan</strong></li>
                            <li><code class="font-mono text-foreground">${tempat_kegiatan}</code> &rarr; menjadi input teks <strong>Tempat Kegiatan</strong></li>
                            <li><code class="font-mono text-foreground">${tanggal_pelaksanaan}</code> atau <code class="font-mono text-foreground">${tgl_acara}</code> &rarr; awalan <code class="font-mono">tgl_</code> / <code class="font-mono">tanggal_</code> otomatis menjadi input pemilih <strong>Tanggal (Date Picker)</strong></li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-primary/30 bg-primary/5 p-3.5 space-y-2">
                        <h4 class="font-semibold text-foreground text-sm">2. Tabel Lampiran Multi-Kolom Kustom (3 Kolom, 4 Kolom, 5+ Kolom)</h4>
                        <p class="text-muted-foreground">
                            Buat tabel di Microsoft Word dengan berapa pun jumlah kolom yang Anda butuhkan. Pada baris isi (baris ke-2), gunakan format <code class="font-mono font-semibold text-foreground">${nama_tabel.nama_kolom}</code>:
                        </p>
                        <div class="space-y-2">
                            <p class="font-medium text-foreground">Contoh Tabel 4 Kolom (Daftar Peserta Dispensasi):</p>
                            <table class="w-full border-collapse border border-border text-[11px] bg-background">
                                <thead>
                                    <tr class="bg-muted">
                                        <th class="border border-border px-2 py-1 text-center">No</th>
                                        <th class="border border-border px-2 py-1 text-left">Nama Mahasiswa</th>
                                        <th class="border border-border px-2 py-1 text-center">NIM</th>
                                        <th class="border border-border px-2 py-1 text-left">Program Studi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-border px-2 py-1 text-center font-mono">${peserta.no}</td>
                                        <td class="border border-border px-2 py-1 font-mono">${peserta.nama}</td>
                                        <td class="border border-border px-2 py-1 text-center font-mono">${peserta.nim}</td>
                                        <td class="border border-border px-2 py-1 font-mono">${peserta.prodi}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-[11px] text-muted-foreground">
                                💡 <strong>Tips:</strong> Jika kolom pertama diberi nama <code class="font-mono">.no</code> atau <code class="font-mono">.nomor</code>, sistem akan otomatis mengisinya dengan nomor urut <code class="font-mono">1., 2., 3.</code> apabila dikosongkan. Baris tabel akan otomatis digandakan (*clone row*) sesuai jumlah baris yang Anda tambahkan di form!
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-1.5">
                        <h4 class="font-semibold text-foreground text-sm">3. Variabel Sistem &amp; Blok Tanda Tangan Dinamis</h4>
                        <p class="text-muted-foreground">
                            Variabel berikut diisi secara otomatis dari header surat dan pengaturan penandatangan (Drag &amp; Drop):
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                            <div class="rounded border border-border bg-background p-2">
                                <code class="font-mono font-semibold text-primary">${nomor_surat}</code>
                                <p class="text-[11px] text-muted-foreground">Nomor surat resmi yang dialokasikan sistem</p>
                            </div>
                            <div class="rounded border border-border bg-background p-2">
                                <code class="font-mono font-semibold text-primary">${tanggal_surat}</code>
                                <p class="text-[11px] text-muted-foreground">Tanggal surat (contoh: 18 November 2026)</p>
                            </div>
                            <div class="rounded border border-border bg-background p-2">
                                <code class="font-mono font-semibold text-primary">${kota_surat}</code>
                                <p class="text-[11px] text-muted-foreground">Kota penerbitan surat (contoh: Cilacap)</p>
                            </div>
                            <div class="rounded border border-border bg-background p-2">
                                <code class="font-mono font-semibold text-primary">${perihal}</code>
                                <p class="text-[11px] text-muted-foreground">Perihal / judul surat</p>
                            </div>
                            <div class="rounded border border-border bg-background p-2 sm:col-span-2">
                                <code class="font-mono font-semibold text-primary">${signature_block}</code>
                                <p class="text-[11px] text-muted-foreground">
                                    Ketik <code class="font-mono">${signature_block}</code> pada baris paragraf di bagian bawah surat. Sistem akan otomatis menggantinya dengan <strong>Tabel Tanda Tangan Dinamis (1, 2, 3, 4, 5+ penandatangan)</strong> lengkap dengan urutan kanan-kiri &amp; rata tengah ganjil sesuai pengaturan Drag &amp; Drop Anda!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-border">
                    <x-ui.button wire:click="$set('showGuideModal', false)">Mengerti &amp; Tutup</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Dialog Konfirmasi Hapus Template --}}
    @if ($showDeleteModal)
        <div
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                @click.outside="$wire.cancelDelete()"
                class="relative w-full max-w-md bg-card rounded-xl border border-border shadow-2xl p-6 space-y-5 my-8"
            >
                <div class="flex items-start gap-4">
                    <div class="size-11 rounded-full bg-destructive/10 text-destructive flex items-center justify-center shrink-0">
                        <x-lucide-alert-triangle class="size-5" />
                    </div>
                    <div class="space-y-1 min-w-0 flex-1">
                        <h3 class="text-base font-bold text-foreground">Hapus Master Template?</h3>
                        <p class="text-xs text-muted-foreground">
                            Anda akan menghapus template <strong class="text-foreground font-semibold">"{{ $deletingTemplateName }}"</strong> dari workspace ini.
                        </p>
                    </div>
                </div>

                @if ($deletingLetterCount > 0 || $deletingBulkCount > 0)
                    <div class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center gap-1.5 font-semibold text-amber-700 dark:text-amber-400">
                            <x-lucide-info class="size-4 shrink-0" />
                            <span>Pemberitahuan Riwayat Surat</span>
                        </div>
                        <p class="text-muted-foreground leading-relaxed">
                            Template ini terhubung dengan <strong class="text-foreground">{{ $deletingLetterCount }} surat</strong> dan <strong class="text-foreground">{{ $deletingBulkCount }} job bulk</strong>. Menghapus template akan melepaskan relasi template, namun seluruh arsip nomor surat dan dokumen yang telah dibuat <strong>tetap tersimpan 100% aman</strong>.
                        </p>
                    </div>
                @else
                    <p class="text-xs text-muted-foreground bg-muted/40 p-3 rounded-lg border border-border">
                        Template ini belum pernah digunakan oleh surat manapun. File master <code class="font-mono text-foreground">.docx</code> akan dihapus permanen dari server.
                    </p>
                @endif

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <x-ui.button
                        type="button"
                        variant="outline"
                        size="sm"
                        wire:click="cancelDelete"
                    >
                        Batal
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="destructive"
                        size="sm"
                        wire:click="deleteConfirmed"
                        class="gap-1.5"
                    >
                        <x-lucide-trash-2 class="size-4" />
                        <span>Ya, Hapus Permanen</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
