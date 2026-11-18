<div class="space-y-6">
    {{-- Master/Detail Split-Pane (40% Left Metadata & Actions / 60% Right Preview & Audit Trail) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Pane (40% = 5 cols) --}}
        <x-ui.card class="lg:col-span-5">
            <x-ui.card-header class="border-b border-border pb-4 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <x-ui.status-badge :status="$letter->status" />
                        @if ($letter->is_manual_number)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                Dokumen Eksternal / Drive
                            </span>
                        @endif
                    </div>
                    <span class="font-mono text-xs text-muted-foreground">Versi v{{ $letter->version }}</span>
                </div>
                <x-ui.card-title class="break-words">{{ $letter->perihal }}</x-ui.card-title>
                <p class="font-mono text-sm font-semibold text-foreground">
                    {{ $letter->formatted_number ?? '—' }}
                </p>
            </x-ui.card-header>

            <x-ui.card-content class="pt-4 space-y-5">
                <dl class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <dt class="text-muted-foreground">Template</dt>
                        <dd class="font-medium mt-0.5">{{ $letter->template?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Tanggal Surat</dt>
                        <dd class="font-medium mt-0.5">{{ $letter->letter_date?->translatedFormat('d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Dibuat Oleh</dt>
                        <dd class="font-medium mt-0.5">{{ $letter->author?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Waktu TTD Basah</dt>
                        <dd class="font-medium mt-0.5">{{ $letter->signed_at ? $letter->signed_at->format('d M Y H:i') : 'Belum ditandatangani' }}</dd>
                    </div>
                </dl>

                @if ($letter->cancel_reason)
                    <x-ui.alert variant="destructive" title="Alasan Pembatalan">
                        {{ $letter->cancel_reason }}
                    </x-ui.alert>
                @endif

                <x-ui.separator />

                {{-- Variable values summary --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nilai Variabel (${...})</h3>
                    <div class="rounded-lg border border-border divide-y divide-border text-xs">
                        @forelse (collect($letter->field_values ?? [])->reject(fn ($v, $k) => str_starts_with((string) $k, '_')) as $k => $v)
                            <div class="flex items-start justify-between gap-3 px-3 py-2">
                                <code class="font-mono text-muted-foreground">${{ '{' . $k . '}' }}</code>
                                <div class="text-right font-medium break-all">
                                    @if (is_array($v))
                                        <span>{{ count($v) }} baris lampiran</span>
                                    @else
                                        <span>{{ $v ?: '—' }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-2 text-muted-foreground">Tidak ada variabel tambahan.</div>
                        @endforelse
                    </div>
                </div>

                @php
                    $letterSigners = is_array($letter->field_values['_signers'] ?? null) && count($letter->field_values['_signers']) > 0
                        ? $letter->field_values['_signers']
                        : ($workspace ? $workspace->defaultSigners() : []);
                @endphp
                @if (count($letterSigners) > 0)
                    <div class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Penandatangan (TTD)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            @foreach ($letterSigners as $sig)
                                <div class="rounded-lg border border-border p-2.5 space-y-0.5 bg-muted/20">
                                    <p class="text-muted-foreground">{{ $sig['position'] ?? '' }}</p>
                                    <p class="font-semibold underline">{{ $sig['name'] ?? '' }}</p>
                                    @if (!empty($sig['id_number']))
                                        <p class="font-mono text-[11px] text-muted-foreground">{{ trim(($sig['id_label'] ?? '') . ' ' . $sig['id_number']) }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-ui.separator />

                {{-- Action buttons --}}
                <div class="flex flex-wrap gap-2">
                    @if ($letter->link_drive)
                        <x-ui.button as="a" href="{{ $letter->link_drive }}" target="_blank" size="sm" class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white">
                            <x-lucide-external-link class="size-4" />
                            <span>Buka di Google Drive</span>
                        </x-ui.button>
                    @endif

                    @if (! $letter->is_manual_number)
                        <x-ui.button size="sm" variant="outline" :href="route('letters.download', $letter->id)">
                            <x-lucide-download class="size-4" />
                            <span>Unduh .docx</span>
                        </x-ui.button>

                        <x-ui.button size="sm" variant="outline" :href="route('letters.preview-pdf', ['letter' => $letter->id, 'download' => 1])">
                            <x-lucide-file-down class="size-4" />
                            <span>Unduh .pdf</span>
                        </x-ui.button>
                    @endif

                    @if ($role?->canWrite())
                        @if ($letter->status === \App\Enums\LetterStatus::Draft)
                            <x-ui.button size="sm" wire:click="transition('menunggu_ttd')">
                                <x-lucide-send class="size-4" />
                                <span>Ajukan TTD</span>
                            </x-ui.button>
                            <x-ui.button size="sm" variant="outline" :href="route('letters.edit', $letter->id)">
                                <x-lucide-pencil class="size-4" />
                                <span>Edit Draft</span>
                            </x-ui.button>
                        @elseif ($letter->status === \App\Enums\LetterStatus::MenungguTtd)
                            <x-ui.button size="sm" wire:click="transition('selesai')">
                                <x-lucide-check-check class="size-4" />
                                <span>Tandai Selesai (TTD Basah)</span>
                            </x-ui.button>
                        @endif

                        @if ($letter->status !== \App\Enums\LetterStatus::Dibatalkan)
                            <x-ui.button size="sm" variant="secondary" wire:click="$set('showReviseModal', true)">
                                <x-lucide-git-branch class="size-4" />
                                <span>Revisi Surat (DEC-005)</span>
                            </x-ui.button>
                        @endif

                        <x-ui.button size="sm" variant="outline" wire:click="duplicate">
                            <x-lucide-copy class="size-4" />
                            <span>Duplikasi</span>
                        </x-ui.button>

                        @if ($letter->status !== \App\Enums\LetterStatus::Dibatalkan)
                            <x-ui.button size="sm" variant="outline" :href="route('bulk.index', ['from_letter' => $letter->id])">
                                <x-lucide-layers class="size-4" />
                                <span>Buat Surat Masal (Bulk)</span>
                            </x-ui.button>
                        @endif

                        @if ($letter->status !== \App\Enums\LetterStatus::Dibatalkan)
                            <x-ui.button size="sm" variant="destructive" wire:click="$set('showCancelModal', true)">
                                <x-lucide-ban class="size-4" />
                                <span>Batalkan</span>
                            </x-ui.button>
                        @endif
                    @endif
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Right Pane (60% = 7 cols): History + PDF Preview --}}
        <x-ui.card class="lg:col-span-7">
            <x-ui.card-header class="flex flex-row items-center justify-between border-b border-border pb-3">
                <div class="inline-flex rounded-lg border border-border bg-muted p-1 text-xs">
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'history')"
                        @class([
                            'rounded-md px-3 py-1.5 font-medium transition-colors',
                            'bg-background text-foreground shadow-2xs' => $activeRightTab === 'history',
                            'text-muted-foreground hover:text-foreground' => $activeRightTab !== 'history',
                        ])
                    >
                        Riwayat Nomor &amp; Audit ({{ $letter->numbers->count() }})
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'preview')"
                        @class([
                            'rounded-md px-3 py-1.5 font-medium transition-colors',
                            'bg-background text-foreground shadow-2xs' => $activeRightTab === 'preview',
                            'text-muted-foreground hover:text-foreground' => $activeRightTab !== 'preview',
                        ])
                    >
                        Pratinjau Dokumen (PDF)
                    </button>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="pt-4 space-y-6">
                @if ($activeRightTab === 'preview')
                    @if ($letter->is_manual_number)
                        <div class="rounded-xl border border-border p-8 text-center space-y-4 bg-muted/10">
                            <div class="mx-auto size-12 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <x-lucide-external-link class="size-6" />
                            </div>
                            <div class="space-y-1">
                                <h3 class="text-base font-semibold text-foreground">Surat Eksternal (Ambil Nomor)</h3>
                                <p class="text-xs text-muted-foreground max-w-md mx-auto">
                                    Dokumen ini dibuat di luar sistem SuratinAja dengan nomor urut resmi yang terdaftar dan tercatat di database.
                                </p>
                            </div>
                            @if ($letter->link_drive)
                                <div class="pt-2">
                                    <x-ui.button as="a" href="{{ $letter->link_drive }}" target="_blank" class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white">
                                        <x-lucide-external-link class="size-4" />
                                        <span>Buka Dokumen di Google Drive</span>
                                    </x-ui.button>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Pratinjau Dokumen PDF (A4 — Margin Normal 2,54 cm)</span>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('letters.preview-pdf', ['letter' => $letter->id, 'download' => 1]) }}" class="underline hover:text-foreground font-medium">
                                        Unduh PDF
                                    </a>
                                    <a href="{{ route('letters.preview-pdf', $letter->id) }}" target="_blank" class="underline hover:text-foreground">
                                        Buka di Tab Baru
                                    </a>
                                </div>
                            </div>
                            <iframe
                                src="{{ route('letters.preview-pdf', $letter->id) }}"
                                class="w-full h-[560px] rounded-lg border border-border bg-muted/30"
                                title="Pratinjau PDF Surat"
                            ></iframe>
                        </div>
                    @endif
                @else
                    {{-- Number chain (AKTIF & HANGUS) --}}
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Rantai Nomor Surat (DEC-005)
                        </h3>
                        <div class="divide-y divide-border rounded-lg border border-border">
                            @foreach ($letter->numbers as $num)
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 text-xs">
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <span @class([
                                                'font-mono text-sm font-semibold',
                                                'line-through text-muted-foreground' => $num->status === \App\Enums\NumberStatus::Hangus,
                                            ])>
                                                {{ $num->formatted_number }}
                                            </span>
                                            <x-ui.status-badge :status="$num->status" />
                                        </div>
                                        @if ($num->revision_reason)
                                            <p class="text-muted-foreground">
                                                Catatan/Alasan: <span class="text-foreground">{{ $num->revision_reason }}</span>
                                            </p>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-muted-foreground font-mono">
                                        {{ $num->issued_at?->format('d M Y H:i') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Revisions log --}}
                    @if ($letter->revisions->isNotEmpty())
                        <div class="space-y-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Riwayat Revisi
                            </h3>
                            <div class="space-y-2">
                                @foreach ($letter->revisions as $rev)
                                    <div class="rounded-lg border border-border p-3 text-xs space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-semibold">
                                                Versi v{{ $rev->version }} —
                                                {{ $rev->mode === 'new_number' ? 'Ganti Nomor Baru (Lama Hangus)' : 'Pertahankan Nomor Lama' }}
                                            </span>
                                            <span class="text-muted-foreground">{{ $rev->created_at?->format('d M Y H:i') }}</span>
                                        </div>
                                        <p class="text-muted-foreground">
                                            Alasan: <span class="text-foreground font-medium">{{ $rev->reason }}</span>
                                        </p>
                                        <p class="text-[11px] text-muted-foreground">
                                            Oleh {{ $rev->actor?->name ?? '—' }} · Nomor:
                                            <code class="font-mono">{{ $rev->oldNumber?->formatted_number }}</code>
                                            →
                                            <code class="font-mono text-foreground">{{ $rev->newNumber?->formatted_number }}</code>
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Activity timeline --}}
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Log Aktivitas
                        </h3>
                        <ol class="space-y-2 text-xs">
                            @foreach ($letter->activities as $act)
                                <li class="flex items-center justify-between rounded-md border border-border px-3 py-2">
                                    <div>
                                        <span class="font-medium">{{ $act->label() }}</span>
                                        <span class="text-muted-foreground">oleh {{ $act->actor?->name ?? 'Sistem' }}</span>
                                    </div>
                                    <span class="font-mono text-[11px] text-muted-foreground">
                                        {{ $act->created_at?->format('d M Y H:i') }}
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    {{-- Revise Modal (DEC-005) --}}
    @if ($showReviseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold">Revisi Surat Bernomor (DEC-005)</h3>
                    <button type="button" wire:click="$set('showReviseModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                @if ($revisionError)
                    <x-ui.alert variant="destructive" class="mt-3">
                        {{ $revisionError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="submitRevision" class="mt-4 space-y-4">
                    <div class="space-y-2">
                        <x-ui.label>Kebijakan Nomor Surat</x-ui.label>
                        <label class="flex items-start gap-2.5 rounded-lg border border-border p-3 text-xs cursor-pointer">
                            <input type="radio" wire:model="useNewNumber" value="1" class="mt-0.5" />
                            <div>
                                <p class="font-semibold text-foreground">Gunakan Nomor Baru (Nomor lama menjadi HANGUS)</p>
                                <p class="text-muted-foreground">Pilih bila surat lama sudah terlanjur keluar/tercetak dan nomor lama tidak boleh dipakai ulang.</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-2.5 rounded-lg border border-border p-3 text-xs cursor-pointer">
                            <input type="radio" wire:model="useNewNumber" value="0" class="mt-0.5" />
                            <div>
                                <p class="font-semibold text-foreground">Pertahankan Nomor Saat Ini ({{ $letter->formatted_number }})</p>
                                <p class="text-muted-foreground">Pilih bila hanya memperbaiki isi dokumen dengan nomor yang sama; revisi tetap tercatat di audit.</p>
                            </div>
                        </label>
                    </div>

                    <div>
                        <x-ui.label for="revisionReason">Alasan Revisi (Wajib — FR-07)</x-ui.label>
                        <x-ui.textarea
                            id="revisionReason"
                            wire:model="revisionReason"
                            class="mt-1.5"
                            placeholder="Jelaskan alasan revisi, misal: Perubahan tanggal pelaksanaan kegiatan..."
                        />
                    </div>

                    <div>
                        <x-ui.label for="revisionPerihal">Perihal</x-ui.label>
                        <x-ui.input id="revisionPerihal" wire:model="revisionPerihal" class="mt-1.5" />
                    </div>

                    @foreach ($editableFields as $field)
                        @if (($field['type'] ?? 'text') !== 'repeater')
                            <div>
                                <x-ui.label>{{ $field['label'] }} (${{ '{' . $field['name'] . '}' }})</x-ui.label>
                                <x-ui.input
                                    type="{{ ($field['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                    wire:model="revisionVariables.{{ $field['name'] }}"
                                    class="mt-1"
                                />
                            </div>
                        @endif
                    @endforeach

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="outline" wire:click="$set('showReviseModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Revisi</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Cancel Letter AlertDialog --}}
    @if ($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-xl">
                <h3 class="text-base font-semibold text-destructive">Batalkan Surat &amp; Hanguskan Nomor</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Nomor <code class="font-mono text-foreground">{{ $letter->formatted_number }}</code> akan berstatus <strong>HANGUS</strong> secara permanen dan surat tetap tersimpan di riwayat audit.
                </p>

                @if ($cancelError)
                    <x-ui.alert variant="destructive" class="mt-3">
                        {{ $cancelError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="submitCancel" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="cancelReason">Alasan Pembatalan (Wajib)</x-ui.label>
                        <x-ui.textarea id="cancelReason" wire:model="cancelReason" class="mt-1.5" placeholder="Jelaskan alasan pembatalan surat..." />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="outline" wire:click="$set('showCancelModal', false)">Tutup</x-ui.button>
                        <x-ui.button variant="destructive" type="submit">Ya, Batalkan Surat</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
