<div class="space-y-6 pt-1 sm:pt-2">
    {{-- Header Dashboard Bulk --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <x-lucide-layers class="size-5 text-primary" />
                <span>Bulk Generation (Mailing)</span>
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Kelola antrean pencetakan massal surat ke dalam satu dokumen .docx multi-halaman dengan nomor urut resmi otomatis.
            </p>
        </div>
        @if ($canCreate)
            <div class="flex items-center gap-2">
                <x-ui.button type="button" wire:click="openBulkModal" class="gap-1.5 shadow-sm">
                    <x-lucide-plus class="size-4" />
                    <span>Buat Bulk Generation Baru</span>
                </x-ui.button>
            </div>
        @endif
    </div>

    @if ($errorMessage && ! $showBulkModal)
        <x-ui.alert variant="destructive" title="Bulk Generation Gagal">
            {{ $errorMessage }}
        </x-ui.alert>
    @endif

    {{-- Bulk Job Queue History with Live Polling (Full Width Dashboard) --}}
    @php
        $shouldPoll = ($hasActiveJobs ?? false) && $previewModalType === null && ! $showBulkModal;
    @endphp
    @if ($shouldPoll)
        <div wire:poll.3s class="hidden" aria-hidden="true"></div>
    @endif
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4 flex flex-row items-center justify-between">
            <div>
                <x-ui.card-title class="flex items-center gap-2">
                    <x-lucide-history class="size-4 text-primary" />
                    <span>Riwayat &amp; Status Job Bulk Generation</span>
                </x-ui.card-title>
                <x-ui.card-description>
                    Pantau progres pemrosesan antrean dan unduh file .docx utuh multi-halaman setelah selesai.
                </x-ui.card-description>
            </div>
            @if ($hasActiveJobs ?? false)
                <span class="text-xs text-muted-foreground flex items-center gap-1 font-mono" title="Memantau antrean yang sedang diproses...">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Auto-refresh antrean...</span>
                </span>
            @else
                <span class="text-xs text-muted-foreground flex items-center gap-1 font-mono" title="Semua job dalam antrean telah selesai diproses">
                    <span class="size-2 rounded-full bg-muted-foreground/40"></span>
                    <span>Semua job selesai</span>
                </span>
            @endif
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            @if ($jobs->isEmpty())
                <div class="py-12 text-center space-y-3">
                    <div class="mx-auto size-12 rounded-full bg-muted/50 flex items-center justify-center text-muted-foreground">
                        <x-lucide-layers class="size-6" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">Belum ada riwayat job bulk generation</p>
                        <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                            Mulai buat puluhan surat undangan atau sertifikat sekaligus dengan satu kali klik.
                        </p>
                    </div>
                    @if ($canCreate)
                        <x-ui.button type="button" wire:click="openBulkModal" variant="outline" size="sm" class="mt-2 gap-1.5">
                            <x-lucide-plus class="size-3.5" />
                            <span>Mulai Bulk Generate</span>
                        </x-ui.button>
                    @endif
                </div>
            @else
                {{-- Desktop Table View (lg:) --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-semibold text-muted-foreground">
                                <th class="pb-3 pr-4 font-mono">Job ID</th>
                                <th class="pb-3 pr-4">Template &amp; Perihal</th>
                                <th class="pb-3 pr-4">Jumlah &amp; Rentang Nomor</th>
                                <th class="pb-3 pr-4">Status</th>
                                <th class="pb-3 pr-4">Waktu Dibuat</th>
                                <th class="pb-3 text-right">Aksi Dokumen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($jobs as $job)
                                <tr wire:key="job-{{ $job->id }}" class="hover:bg-muted/30 transition-colors">
                                    <td class="py-3 pr-4 font-mono text-xs text-muted-foreground">#{{ substr($job->id, 0, 8) }}</td>
                                    <td class="py-3 pr-4">
                                        <div class="font-medium text-foreground text-xs flex items-center gap-1.5">
                                            <x-lucide-file-text class="size-3.5 text-primary shrink-0" />
                                            <span>{{ $job->template?->name ?? 'Template' }}</span>
                                        </div>
                                        <div class="text-[11px] text-muted-foreground truncate max-w-[220px] mt-0.5">
                                            {{ $job->perihal ?? ($job->payload['perihal'] ?? '—') }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4 font-mono text-xs">
                                        <div>
                                            <span class="font-semibold text-foreground">{{ $job->processed_count }}</span>
                                            <span class="text-muted-foreground">/ {{ $job->recipient_count }} surat</span>
                                        </div>
                                        <div class="text-[11px] text-primary font-medium mt-0.5">
                                            {{ $job->numberRange() }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        @if ($job->status === \App\Enums\BulkJobStatus::Queued || $job->status === 'queued' || $job->status?->value === 'queued')
                                            <x-ui.badge variant="secondary" class="gap-1 text-[11px]">
                                                <x-lucide-clock class="size-3" />
                                                <span>Antrean</span>
                                            </x-ui.badge>
                                        @elseif ($job->status === \App\Enums\BulkJobStatus::Processing || $job->status === 'processing' || $job->status?->value === 'processing')
                                            <x-ui.badge variant="outline" class="gap-1 text-primary border-primary/30 bg-primary/10 text-[11px]">
                                                <x-lucide-loader-2 class="size-3 animate-spin" />
                                                <span>Memproses...</span>
                                            </x-ui.badge>
                                        @elseif ($job->status === \App\Enums\BulkJobStatus::Done || $job->status === 'done' || $job->status?->value === 'done' || $job->status === 'completed')
                                            <x-ui.badge variant="default" class="gap-1 bg-emerald-600 hover:bg-emerald-600 text-white text-[11px]">
                                                <x-lucide-check-circle-2 class="size-3" />
                                                <span>Selesai</span>
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge variant="destructive" class="gap-1 text-[11px]" title="{{ $job->error ?? $job->error_message }}">
                                                <x-lucide-x-circle class="size-3" />
                                                <span>Gagal</span>
                                            </x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-muted-foreground">
                                        {{ $job->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3 text-right">
                                        @php
                                            $isDone = in_array($job->status, [\App\Enums\BulkJobStatus::Done, 'done', 'completed'], true) || ($job->status?->value ?? '') === 'done';
                                        @endphp
                                        @if ($isDone)
                                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                                <button
                                                    type="button"
                                                    wire:click="openBatchPdfPreview('{{ $job->id }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openBatchPdfPreview('{{ $job->id }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition shadow-2xs cursor-pointer disabled:opacity-60"
                                                    title="Pratinjau seluruh surat batch ini dalam 1 file PDF utuh"
                                                >
                                                    <x-lucide-eye wire:loading.remove wire:target="openBatchPdfPreview('{{ $job->id }}')" class="size-3.5" />
                                                    <span wire:loading wire:target="openBatchPdfPreview('{{ $job->id }}')" class="size-3.5 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin"></span>
                                                    <span wire:loading.remove wire:target="openBatchPdfPreview('{{ $job->id }}')">Pratinjau 1 PDF Utuh</span>
                                                    <span wire:loading wire:target="openBatchPdfPreview('{{ $job->id }}')">Memproses PDF...</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="openBatchEnvelopePreview('{{ $job->id }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="openBatchEnvelopePreview('{{ $job->id }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition shadow-2xs cursor-pointer disabled:opacity-60"
                                                    title="Pratinjau seluruh amplop penerima batch ini dalam 1 PDF"
                                                >
                                                    <x-lucide-mail wire:loading.remove wire:target="openBatchEnvelopePreview('{{ $job->id }}')" class="size-3.5" />
                                                    <span wire:loading wire:target="openBatchEnvelopePreview('{{ $job->id }}')" class="size-3.5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                                                    <span wire:loading.remove wire:target="openBatchEnvelopePreview('{{ $job->id }}')">Pratinjau Semua Amplop</span>
                                                    <span wire:loading wire:target="openBatchEnvelopePreview('{{ $job->id }}')">Memproses Amplop...</span>
                                                </button>
                                                <a
                                                    href="{{ route('bulk.download', $job) }}"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg border border-border bg-background hover:bg-muted text-foreground transition"
                                                    title="Unduh berkas .docx massal"
                                                >
                                                    <x-lucide-download class="size-3.5" />
                                                    <span>Unduh .docx</span>
                                                </a>
                                                <a
                                                    href="{{ route('letters.index', ['bulk_job_id' => $job->id]) }}"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg border border-border bg-background hover:bg-muted text-foreground transition"
                                                    title="Lihat daftar surat individual"
                                                >
                                                    <x-lucide-list class="size-3.5" />
                                                </a>
                                            </div>
                                        @elseif ($job->status === \App\Enums\BulkJobStatus::Failed || $job->status === 'failed' || $job->status?->value === 'failed')
                                            <span class="text-xs text-destructive" title="{{ $job->error ?? $job->error_message }}">
                                                {{ Str::limit($job->error ?? $job->error_message ?? 'Gagal', 30) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-muted-foreground italic flex items-center justify-end gap-1">
                                                <x-lucide-loader-2 class="size-3 animate-spin text-primary" />
                                                <span>Sedang diproses...</span>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Card List View (< lg) --}}
                <div class="block lg:hidden divide-y divide-border -mx-4 sm:-mx-6">
                    @foreach ($jobs as $job)
                        @php
                            $isDone = in_array($job->status, [\App\Enums\BulkJobStatus::Done, 'done', 'completed'], true) || ($job->status?->value ?? '') === 'done';
                        @endphp
                        <div wire:key="job-card-{{ $job->id }}" class="p-4 space-y-3 hover:bg-muted/20 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="font-mono text-xs font-semibold text-muted-foreground block">
                                        #{{ substr($job->id, 0, 8) }}
                                    </span>
                                    <div class="font-semibold text-xs text-foreground flex items-center gap-1.5 mt-0.5">
                                        <x-lucide-file-text class="size-3.5 text-primary shrink-0" />
                                        <span class="truncate">{{ $job->template?->name ?? 'Template' }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    @if ($job->status === \App\Enums\BulkJobStatus::Queued || $job->status === 'queued' || $job->status?->value === 'queued')
                                        <x-ui.badge variant="secondary" class="gap-1 text-[11px]">
                                            <x-lucide-clock class="size-3" />
                                            <span>Antrean</span>
                                        </x-ui.badge>
                                    @elseif ($job->status === \App\Enums\BulkJobStatus::Processing || $job->status === 'processing' || $job->status?->value === 'processing')
                                        <x-ui.badge variant="outline" class="gap-1 text-primary border-primary/30 bg-primary/10 text-[11px]">
                                            <x-lucide-loader-2 class="size-3 animate-spin" />
                                            <span>Memproses...</span>
                                        </x-ui.badge>
                                    @elseif ($job->status === \App\Enums\BulkJobStatus::Done || $job->status === 'done' || $job->status?->value === 'done' || $job->status === 'completed')
                                        <x-ui.badge variant="default" class="gap-1 bg-emerald-600 text-white text-[11px]">
                                            <x-lucide-check-circle-2 class="size-3" />
                                            <span>Selesai</span>
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge variant="destructive" class="gap-1 text-[11px]">
                                            <x-lucide-x-circle class="size-3" />
                                            <span>Gagal</span>
                                        </x-ui.badge>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-1 text-xs">
                                <p class="text-foreground font-medium line-clamp-2">
                                    {{ $job->perihal ?? ($job->payload['perihal'] ?? '—') }}
                                </p>
                                <div class="flex items-center gap-2 text-muted-foreground font-mono text-[11px]">
                                    <span>{{ $job->processed_count }}/{{ $job->recipient_count }} surat</span>
                                    <span>&bull;</span>
                                    <span class="text-primary font-medium">{{ $job->numberRange() }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-border/40 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[11px] text-muted-foreground">
                                    {{ $job->created_at->format('d/m/Y H:i') }}
                                </span>

                                @if ($isDone)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button
                                            type="button"
                                            wire:click="openBatchPdfPreview('{{ $job->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="openBatchPdfPreview('{{ $job->id }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition shadow-2xs cursor-pointer disabled:opacity-60"
                                        >
                                            <x-lucide-eye wire:loading.remove wire:target="openBatchPdfPreview('{{ $job->id }}')" class="size-3.5" />
                                            <span wire:loading wire:target="openBatchPdfPreview('{{ $job->id }}')" class="size-3.5 border-2 border-emerald-600 border-t-transparent rounded-full animate-spin"></span>
                                            <span wire:loading.remove wire:target="openBatchPdfPreview('{{ $job->id }}')">Pratinjau PDF</span>
                                            <span wire:loading wire:target="openBatchPdfPreview('{{ $job->id }}')">Memproses...</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="openBatchEnvelopePreview('{{ $job->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="openBatchEnvelopePreview('{{ $job->id }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition shadow-2xs cursor-pointer disabled:opacity-60"
                                        >
                                            <x-lucide-mail wire:loading.remove wire:target="openBatchEnvelopePreview('{{ $job->id }}')" class="size-3.5" />
                                            <span wire:loading wire:target="openBatchEnvelopePreview('{{ $job->id }}')" class="size-3.5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                                            <span wire:loading.remove wire:target="openBatchEnvelopePreview('{{ $job->id }}')">Amplop</span>
                                            <span wire:loading wire:target="openBatchEnvelopePreview('{{ $job->id }}')">Memproses...</span>
                                        </button>
                                        <a
                                            href="{{ route('bulk.download', $job) }}"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg border border-border bg-background hover:bg-muted text-foreground transition"
                                        >
                                            <x-lucide-download class="size-3.5" />
                                            <span>.docx</span>
                                        </a>
                                        <a
                                            href="{{ route('letters.index', ['bulk_job_id' => $job->id]) }}"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg border border-border bg-background hover:bg-muted text-foreground transition"
                                            title="Daftar Surat"
                                        >
                                            <x-lucide-list class="size-3.5" />
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- MODAL DIALOG FORM BULK GENERATION --}}
    <div
        x-data="{ open: @entangle('showBulkModal') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/75 backdrop-blur-xs p-4 sm:p-6 flex items-center justify-center"
        role="dialog"
        aria-modal="true"
    >
        <div
            @click.outside="$wire.closeBulkModal()"
            class="relative w-full max-w-4xl bg-card rounded-xl border border-border shadow-2xl flex flex-col max-h-[88vh] overflow-hidden my-auto"
        >
                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-border px-6 py-4 bg-muted/30 shrink-0">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-foreground flex items-center gap-2">
                            <x-lucide-layers class="w-4 h-4 text-primary shrink-0" />
                            <span>Formulir Bulk Generation (Mailing)</span>
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Workspace: <span class="font-medium text-foreground">{{ $workspace?->name }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeBulkModal"
                        class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer transition-colors"
                        title="Tutup Modal"
                    >
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                {{-- Modal Body (Scrollable) --}}
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    @if ($errorMessage)
                        <x-ui.alert variant="destructive" title="Bulk Generation Gagal">
                            {{ $errorMessage }}
                        </x-ui.alert>
                    @endif

                    @if ($errors->any())
                        <x-ui.alert variant="destructive" title="Formulir Belum Lengkap">
                            <ul class="list-disc pl-4 space-y-0.5 text-xs mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    @endif

                    {{-- Opsi: Salin dari Surat yang Sudah Ada --}}
                    @if (! empty($recentLetters) && count($recentLetters) > 0)
                        <div class="rounded-lg border border-border/80 bg-muted/20 p-3.5 space-y-2">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="bulk-source-letter" class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <x-lucide-copy class="size-3.5 text-primary" />
                                    <span>Salin dari Surat yang Ada (Opsional)</span>
                                </x-ui.label>
                                @if ($sourceLetterId)
                                    <button
                                        type="button"
                                        wire:click="$set('sourceLetterId', null)"
                                        class="text-[11px] text-muted-foreground hover:text-foreground underline cursor-pointer"
                                    >
                                        Reset Pilihan
                                    </button>
                                @endif
                            </div>
                            <x-ui.select id="bulk-source-letter" wire:model.live="sourceLetterId" class="mt-1 text-xs">
                                <option value="">— Buat baru dari template (tanpa salin surat) —</option>
                                @foreach ($recentLetters as $rl)
                                    <option value="{{ $rl->id }}">
                                        {{ $rl->formatted_number ?? '#' . $rl->id }} — {{ $rl->perihal }} ({{ $rl->template?->name ?? 'Template' }})
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    @endif

                    {{-- Form Baris 1: Template, Perihal, Tanggal --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <x-ui.label for="bulk-tpl">Template Dokumen (.docx) <span class="text-destructive">*</span></x-ui.label>
                                @if ($selectedTemplate)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-primary bg-primary/10 px-2 py-0.5 rounded">
                                        <x-lucide-file-text class="size-3" />
                                        <span>{{ $selectedTemplate->name }}</span>
                                    </span>
                                @endif
                            </div>
                            <x-ui.select id="bulk-tpl" wire:model.live="templateId" class="mt-1.5">
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}">[{{ $tpl->scope->label() }}] {{ $tpl->name }}</option>
                                @endforeach
                            </x-ui.select>
                            @error('templateId')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="bulk-perihal">Hal / Perihal Surat (${perihal}) <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="bulk-perihal" wire:model.live.debounce.300ms="perihal" class="mt-1.5" placeholder="Mis. Undangan Seminar OASIS 2026" />
                            @error('perihal')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-ui.label for="bulk-date">Tanggal Surat <span class="text-destructive">*</span></x-ui.label>
                            <x-ui.input id="bulk-date" type="date" wire:model.live="letterDate" class="mt-1.5" />
                            @error('letterDate')
                                <p class="text-xs text-destructive font-medium mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Format & Kategori Penomoran --}}
                    <div class="rounded-lg border border-border bg-muted/20 p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <x-lucide-hash class="size-3.5 text-primary" />
                                <span>Format &amp; Kategori Penomoran Surat Massal</span>
                            </p>
                            @if ($liveNumberPreview)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-primary/10 border border-primary/20 text-xs font-mono font-semibold text-primary">
                                    <span>Pratinjau Nomor:</span>
                                    <span>{{ $liveNumberPreview }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-ui.label for="bulk-kategori" class="text-xs">Jenis Surat {kategori}</x-ui.label>
                                <x-ui.select id="bulk-kategori" wire:model.live="kategori" class="mt-1 text-xs">
                                    @foreach ($letterTypes as $lt)
                                        <option value="{{ $lt['code'] }}">[{{ $lt['code'] }}] {{ $lt['name'] }}</option>
                                    @endforeach
                                    <option value="custom">— Kustom (Ketik Kode Baru) —</option>
                                </x-ui.select>
                                @if ($kategori === 'custom')
                                    <x-ui.input
                                        wire:model.live.debounce.300ms="customKategori"
                                        class="mt-1.5 text-xs font-mono uppercase"
                                        placeholder="Ketik kode (misal: UND, DISP)..."
                                    />
                                @endif
                            </div>

                            <div>
                                <x-ui.label for="bulk-parameter" class="text-xs">Lingkup / Parameter {parameter}</x-ui.label>
                                <x-ui.select id="bulk-parameter" wire:model.live="parameter" class="mt-1 text-xs">
                                    @foreach ($letterParameters as $lp)
                                        <option value="{{ $lp['code'] }}">
                                            {{ $lp['code'] !== '' ? $lp['code'] . ' — ' . $lp['label'] : $lp['label'] }}
                                        </option>
                                    @endforeach
                                    <option value="custom">— Kustom —</option>
                                </x-ui.select>
                                @if ($parameter === 'custom')
                                    <x-ui.input
                                        wire:model.live.debounce.300ms="customParameter"
                                        class="mt-1.5 text-xs font-mono"
                                        placeholder="Ketik parameter (misal: -int, /Eks)..."
                                    />
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Selector: Variabel Multi-Isi (Berbeda Tiap Surat) --}}
                    @if (! empty($allEditableFields))
                        <div class="rounded-lg border border-border bg-muted/30 p-3.5 space-y-2">
                            <div>
                                <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                    <x-lucide-sliders-horizontal class="size-3.5 text-primary" />
                                    <span>Pilih Variabel yang Berbeda Tiap Surat (Multi-isi / Per-Penerima)</span>
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Klik untuk mengaktifkan variabel yang nilainya berbeda untuk setiap penerima. Variabel yang tidak dicentang otomatis menjadi <strong>Variabel Bersama</strong> (diisi 1x untuk semua).
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach ($allEditableFields as $f)
                                    @php
                                        $isVarying = in_array($f['name'], $varyingFieldNames, true);
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleVaryingField('{{ $f['name'] }}')"
                                        wire:key="toggle-varying-{{ $f['name'] }}"
                                        @class([
                                            'inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition-colors cursor-pointer',
                                            'bg-primary text-primary-foreground shadow-2xs' => $isVarying,
                                            'bg-background hover:bg-accent text-foreground border border-border' => ! $isVarying,
                                        ])
                                    >
                                        @if ($isVarying)
                                            <x-lucide-check class="w-3.5 h-3.5 shrink-0" />
                                        @else
                                            <x-lucide-plus class="w-3.5 h-3.5 shrink-0 text-muted-foreground" />
                                        @endif
                                        <span>{{ $f['label'] }} (${{ '{' . $f['name'] . '}' }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Variabel Bersama (Sama untuk semua surat) --}}
                    @if (! empty($sharedFields))
                        <div class="rounded-lg border border-border bg-card p-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Variabel Bersama (Sama untuk Seluruh Surat)
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Nilai berikut akan diterapkan otomatis ke semua surat yang dibuat.
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($sharedFields as $field)
                                    <div wire:key="shared-field-{{ $field['name'] }}">
                                        <x-ui.label class="text-xs">{{ $field['label'] }} (${{ '{' . $field['name'] . '}' }})</x-ui.label>
                                        <x-ui.input
                                            type="{{ ($field['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                            wire:model.live.debounce.300ms="sharedVariables.{{ $field['name'] }}"
                                            class="mt-1"
                                            placeholder="Isi {{ $field['label'] }}..."
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-ui.separator />

                    {{-- Recipient Input Tabs --}}
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="inline-flex rounded-lg border border-border bg-muted p-1 text-xs">
                                <button
                                    type="button"
                                    wire:click="$set('inputTab', 'manual')"
                                    @class([
                                        'rounded-md px-3 py-1.5 font-medium transition-colors cursor-pointer',
                                        'bg-background text-foreground shadow-2xs' => $inputTab === 'manual',
                                        'text-muted-foreground hover:text-foreground' => $inputTab !== 'manual',
                                    ])
                                >
                                    Input Manual
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('inputTab', 'upload')"
                                    @class([
                                        'rounded-md px-3 py-1.5 font-medium transition-colors cursor-pointer',
                                        'bg-background text-foreground shadow-2xs' => $inputTab === 'upload',
                                        'text-muted-foreground hover:text-foreground' => $inputTab !== 'upload',
                                    ])
                                >
                                    Upload CSV / XLSX
                                </button>
                            </div>

                            @if ($inputTab === 'manual')
                                <x-ui.button type="button" variant="outline" size="sm" wire:click="addManualRow">
                                    <x-lucide-plus class="size-3.5" />
                                    <span>Tambah Baris Penerima</span>
                                </x-ui.button>
                            @endif
                        </div>

                        @if ($inputTab === 'manual')
                            <div class="space-y-2">
                                {{-- Header Kolom Dinamis (Flex responsif menyesuaikan jumlah field) --}}
                                @if (! empty($varyingFields))
                                    <div class="flex items-center gap-2 text-[11px] font-semibold text-muted-foreground px-2 pb-1.5 border-b border-border">
                                        <div class="w-8 shrink-0 text-center font-mono">#</div>
                                        @foreach ($varyingFields as $vIdx => $vf)
                                            <div class="flex-1 min-w-0">
                                                {{ $vf['label'] }} (${{ '{' . $vf['name'] . '}' }})
                                            </div>
                                        @endforeach
                                        <div class="w-10 shrink-0 text-right pr-1">Aksi</div>
                                    </div>
                                @endif

                                @foreach ($manualRows as $idx => $row)
                                    <div class="flex items-center gap-2 px-1" wire:key="m-row-{{ $idx }}">
                                        <div class="w-8 shrink-0 text-center font-mono text-xs text-muted-foreground">
                                            {{ $idx + 1 }}
                                        </div>
                                        @foreach ($varyingFields as $vIdx => $vf)
                                            <div class="flex-1 min-w-0" wire:key="m-cell-{{ $idx }}-{{ $vf['name'] }}">
                                                <x-ui.input
                                                    wire:model.live.debounce.300ms="manualRows.{{ $idx }}.{{ $vf['name'] }}"
                                                    placeholder="{{ $vf['label'] }}..."
                                                    class="text-xs w-full"
                                                />
                                            </div>
                                        @endforeach
                                        <div class="w-10 shrink-0 text-right">
                                            @if (count($manualRows) > 1)
                                                <button
                                                    type="button"
                                                    wire:click="removeManualRow({{ $idx }})"
                                                    class="p-1.5 text-muted-foreground hover:text-destructive cursor-pointer rounded"
                                                    title="Hapus Baris"
                                                >
                                                    <x-lucide-trash-2 class="size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Upload Tab --}}
                            <div class="rounded-lg border-2 border-dashed border-border p-6 text-center space-y-3">
                                <x-lucide-upload-cloud class="size-8 mx-auto text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium text-foreground">Pilih file spreadsheet penerima</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">Mendukung file .csv, .xlsx, .xls (maksimal 500 baris)</p>
                                </div>
                                <input
                                    type="file"
                                    wire:model="recipientsFile"
                                    accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    class="text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                                />
                                @error('recipientsFile')
                                    <p class="text-xs text-destructive font-medium">{{ $message }}</p>
                                @enderror

                                @if (! empty($uploadedRows))
                                    <div class="pt-3 border-t border-border text-left">
                                        <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            ✓ {{ count($uploadedRows) }} baris data berhasil dimuat dari file.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Pratinjau Antrean Penerima --}}
                    <div class="rounded-lg border border-border bg-card p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <x-lucide-list-ordered class="size-3.5 text-primary" />
                                <span>Ringkasan Antrean Surat ({{ count($queueItems) }} Penerima)</span>
                            </p>
                            <span class="text-[11px] text-muted-foreground">
                                Tiap baris akan dibuatkan 1 surat dengan nomor berurutan
                            </span>
                        </div>

                        <div class="max-h-52 overflow-y-auto divide-y divide-border rounded-lg border border-border text-xs">
                            @forelse ($queueItems as $idx => $item)
                                @php
                                    $primaryKey = $varyingFieldNames[0] ?? array_key_first($item);
                                    $secondaryKey = $varyingFieldNames[1] ?? (array_keys($item)[1] ?? null);
                                    $primaryVal = $item[$primaryKey] ?? $item['tujuan'] ?? $item['penerima'] ?? $item['nama'] ?? reset($item);
                                    $secondaryVal = $secondaryKey ? ($item[$secondaryKey] ?? null) : null;
                                    if (! $secondaryVal) {
                                        $secondaryVal = $item['instansi'] ?? $item['tempat_tujuan'] ?? $item['alamat'] ?? '';
                                    }
                                @endphp
                                <div class="flex items-center justify-between px-3 py-2">
                                    <div class="min-w-0">
                                        <span class="font-mono text-muted-foreground mr-2">#{{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="font-medium text-foreground">
                                            {{ $primaryVal ?: '—' }}
                                        </span>
                                    </div>
                                    @if ($secondaryVal)
                                        <span class="text-muted-foreground truncate ml-2">
                                            {{ $secondaryVal }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-center text-muted-foreground">
                                    Belum ada penerima dalam antrean.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Modal Footer (Sticky) --}}
                <div class="border-t border-border px-6 py-4 bg-muted/30 shrink-0 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                        <span>Total: <strong class="text-foreground">{{ count($queueItems) }} Surat</strong></span>
                        @if ($liveNumberPreview)
                            <span class="text-border">|</span>
                            <span>Mulai nomor: <strong class="font-mono text-primary">{{ $liveNumberPreview }}</strong></span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 justify-end">
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="closeBulkModal">
                            Batal
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            wire:click="startBulk"
                            wire:loading.attr="disabled"
                            size="sm"
                            class="gap-1.5"
                            :disabled="count($queueItems) === 0"
                        >
                            <span wire:loading.remove wire:target="startBulk" class="inline-flex items-center gap-1.5">
                                <x-lucide-play class="size-3.5 fill-current" />
                                <span>Jalankan Bulk Generate ({{ count($queueItems) }} Penerima)</span>
                            </span>
                            <span wire:loading wire:target="startBulk" class="inline-flex items-center gap-1.5">
                                <x-lucide-loader-2 class="size-3.5 animate-spin" />
                                <span>Memproses Surat...</span>
                            </span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>

    {{-- MODAL PREVIEW PDF BATCH & ENVELOPES (PREVIEW-FIRST GLOBAL) --}}
    @if ($previewModalType !== null)
        <div
            x-data
            @keydown.escape.window="$wire.closePreviewModal()"
            @click.self="$wire.closePreviewModal()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            role="dialog"
            aria-modal="true"
        >
            <div
                @click.outside="$wire.closePreviewModal()"
                class="w-full max-w-5xl rounded-2xl border border-border bg-background p-5 shadow-2xl flex flex-col h-[88vh]"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-border pb-3 shrink-0 gap-3">
                    <div class="flex items-center gap-2.5">
                        <div @class([
                            'p-2 rounded-xl',
                            'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' => $previewModalType === 'full_pdf',
                            'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' => $previewModalType === 'envelopes',
                        ])>
                            @if ($previewModalType === 'full_pdf')
                                <x-lucide-file-text class="size-5" />
                            @else
                                <x-lucide-mail class="size-5" />
                            @endif
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                                <span>{{ $previewTitle }}</span>
                                @if ($previewModalType === 'envelopes' && $previewEnvelopeTemplateId && $envelopeTemplates->find($previewEnvelopeTemplateId))
                                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300">
                                        {{ strtoupper($envelopeTemplates->find($previewEnvelopeTemplateId)->preset_size) }}
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                @if ($previewModalType === 'full_pdf')
                                    Seluruh surat dari batch ini digabungkan berurutan dalam satu file PDF utuh.
                                @else
                                    Seluruh kop amplop penerima batch ini siap cetak dengan nomor &amp; instansi otomatis.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Dropdown Switcher Ukuran Amplop (Jika mode amplop & > 1 template) --}}
                        @if ($previewModalType === 'envelopes' && $envelopeTemplates->count() > 1)
                            <div class="flex items-center gap-1.5 mr-2">
                                <label for="bulk_env_template" class="text-xs text-muted-foreground whitespace-nowrap">Ukuran:</label>
                                <select
                                    id="bulk_env_template"
                                    wire:change="changeBatchEnvelopeTemplate($event.target.value)"
                                    class="text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 font-medium text-foreground focus:ring-1 focus:ring-primary focus:outline-hidden"
                                >
                                    @foreach ($envelopeTemplates as $tmpl)
                                        <option value="{{ $tmpl->id }}" @selected($tmpl->id == $previewEnvelopeTemplateId)>
                                            {{ $tmpl->name }} ({{ strtoupper($tmpl->preset_size) }})
                                            {{ $tmpl->is_default ? '★' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($previewDownloadUrl)
                            <a
                                href="{{ $previewDownloadUrl }}"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-xs"
                            >
                                <x-lucide-download class="size-3.5" />
                                <span>Unduh PDF</span>
                            </a>
                        @endif

                        @if ($previewUrl)
                            <a
                                href="{{ $previewUrl }}"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-border bg-background hover:bg-muted text-foreground transition"
                            >
                                <x-lucide-external-link class="size-3.5 text-blue-600" />
                                <span class="hidden sm:inline">Tab Baru</span>
                            </a>
                        @endif

                        <button
                            type="button"
                            wire:click="closePreviewModal"
                            class="text-muted-foreground hover:text-foreground text-lg ml-1 leading-none cursor-pointer p-1"
                            title="Tutup Modal (Esc)"
                            aria-label="Tutup Modal Pratinjau"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 pt-3 relative">
                    {{-- Loading overlay saat render / merger berlangsung --}}
                    <div wire:loading wire:target="openBatchPdfPreview, openBatchEnvelopePreview, changeBatchEnvelopeTemplate" class="absolute inset-0 flex flex-col items-center justify-center bg-background/85 backdrop-blur-xs z-10 space-y-3 rounded-lg">
                        <div class="size-8 border-3 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <div class="text-center">
                            <p class="text-sm font-semibold text-foreground">Sedang memproses &amp; menggabungkan dokumen PDF...</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Mohon tunggu beberapa saat hingga seluruh halaman siap ditampilkan.</p>
                        </div>
                    </div>

                    @if ($previewUrl)
                        <iframe
                            src="{{ $previewUrl }}"
                            class="w-full h-full rounded-xl border border-border bg-muted/20"
                            title="{{ $previewTitle }}"
                        ></iframe>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
