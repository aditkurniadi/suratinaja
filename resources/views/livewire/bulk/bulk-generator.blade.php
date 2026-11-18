<div class="space-y-6">
    @if ($errorMessage)
        <x-ui.alert variant="destructive" title="Bulk Generation Gagal">
            {{ $errorMessage }}
        </x-ui.alert>
    @endif

    {{-- Two-Pane Bulk Composer (DESIGN.md §6) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Pane: Configuration + Recipient Input (Manual / CSV-XLSX) --}}
        <x-ui.card class="lg:col-span-7">
            <x-ui.card-header class="border-b border-border pb-4">
                <x-ui.card-title>Bulk Generation (Mailing) — {{ $workspace?->name }}</x-ui.card-title>
                <x-ui.card-description>
                    Buat puluhan surat sekaligus dalam satu dokumen .docx utuh multi-halaman dengan nomor berurutan otomatis (DEC-003, FR-05).
                </x-ui.card-description>
            </x-ui.card-header>

            <x-ui.card-content class="pt-5 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-ui.label for="bulk-tpl">Template Surat (.docx)</x-ui.label>
                        <x-ui.select id="bulk-tpl" wire:model.live="templateId" class="mt-1.5">
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">[{{ $tpl->scope->label() }}] {{ $tpl->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div>
                        <x-ui.label for="bulk-perihal">Perihal Surat</x-ui.label>
                        <x-ui.input id="bulk-perihal" wire:model="perihal" class="mt-1.5" placeholder="Mis. Undangan Seminar OASIS 2026" />
                    </div>

                    <div>
                        <x-ui.label for="bulk-date">Tanggal Surat</x-ui.label>
                        <x-ui.input id="bulk-date" type="date" wire:model="letterDate" class="mt-1.5" />
                    </div>
                </div>

                @if (! empty($editableFields))
                    <x-ui.separator />
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Variabel Bersama (Shared Variables)
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($editableFields as $field)
                                <div>
                                    <x-ui.label class="text-xs">{{ $field['label'] }} (${{ '{' . $field['name'] . '}' }})</x-ui.label>
                                    <x-ui.input
                                        type="{{ ($field['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                        wire:model="sharedVariables.{{ $field['name'] }}"
                                        class="mt-1"
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
                                    'rounded-md px-3 py-1.5 font-medium transition-colors',
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
                                    'rounded-md px-3 py-1.5 font-medium transition-colors',
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
                                <span>Tambah Penerima</span>
                            </x-ui.button>
                        @endif
                    </div>

                    @if ($inputTab === 'manual')
                        <div class="space-y-2">
                            @foreach ($manualRows as $idx => $row)
                                <div class="grid grid-cols-12 gap-2 items-center" wire:key="m-row-{{ $idx }}">
                                    <div class="col-span-6">
                                        <x-ui.input
                                            wire:model.live="manualRows.{{ $idx }}.penerima"
                                            placeholder="Nama & Gelar Penerima (${penerima})"
                                        />
                                    </div>
                                    <div class="col-span-5">
                                        <x-ui.input
                                            wire:model.live="manualRows.{{ $idx }}.tempat_tujuan"
                                            placeholder="Instansi / Tujuan (${tempat_tujuan})"
                                        />
                                    </div>
                                    <div class="col-span-1 flex justify-end">
                                        <x-ui.button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            wire:click="removeManualRow({{ $idx }})"
                                            aria-label="Hapus penerima"
                                        >
                                            <x-lucide-trash-2 class="size-4 text-destructive" />
                                        </x-ui.button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-border p-4 space-y-3">
                            <x-ui.label for="recipientsFile">Unggah File Penerima (.csv atau .xlsx)</x-ui.label>
                            <x-ui.input id="recipientsFile" type="file" wire:model="recipientsFile" accept=".csv,.xlsx,.xls" />
                            <p class="text-xs text-muted-foreground">
                                Gunakan baris pertama sebagai header kolom sesuai nama variabel template, misal: <code class="font-mono">penerima,tempat_tujuan</code> atau <code class="font-mono">nama,alamat</code>.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-2 border-t border-border">
                    <x-ui.button wire:click="startBulk">
                        <x-lucide-layers class="size-4" />
                        <span>Jalankan Bulk Generate ({{ count($queueItems) }} Penerima)</span>
                    </x-ui.button>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Right Pane: Numbered Queue Preview + Per-Row Validation Errors --}}
        <x-ui.card class="lg:col-span-5">
            <x-ui.card-header class="border-b border-border pb-4">
                <x-ui.card-title>Pratinjau Antrean Penerima ({{ count($queueItems) }})</x-ui.card-title>
                <x-ui.card-description>
                    Setiap baris akan mendapat 1 halaman surat dengan nomor berurutan.
                </x-ui.card-description>
            </x-ui.card-header>

            <x-ui.card-content class="pt-4 space-y-4">
                @if (! empty($uploadFailedRows))
                    <x-ui.alert variant="destructive" title="Baris Dilewati ({{ count($uploadFailedRows) }} baris tidak valid — AC-05-03)">
                        <ul class="list-disc pl-4 space-y-0.5 text-xs mt-1">
                            @foreach ($uploadFailedRows as $fail)
                                <li>Baris #{{ $fail['row'] }}: {{ implode(', ', $fail['errors']) }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                <div class="max-h-80 overflow-y-auto divide-y divide-border rounded-lg border border-border text-xs">
                    @forelse ($queueItems as $idx => $item)
                        <div class="flex items-center justify-between px-3 py-2">
                            <div class="min-w-0">
                                <span class="font-mono text-muted-foreground mr-2">#{{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="font-medium text-foreground">
                                    {{ $item['penerima'] ?? $item['nama'] ?? reset($item) }}
                                </span>
                            </div>
                            <span class="text-muted-foreground truncate ml-2">
                                {{ $item['tempat_tujuan'] ?? $item['alamat'] ?? '' }}
                            </span>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted-foreground">
                            Belum ada penerima dalam antrean.
                        </div>
                    @endforelse
                </div>
            </x-ui.card-content>
        </x-ui.card>
    </div>

    {{-- Bulk Job Queue History with Live Polling --}}
    <x-ui.card wire:poll.3s>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title>Riwayat &amp; Status Job Bulk Generation</x-ui.card-title>
            <x-ui.card-description>
                Pantau progres antrean dan unduh satu file .docx utuh multi-halaman setelah selesai.
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            @if ($jobs->isEmpty())
                <p class="text-xs text-muted-foreground">Belum ada riwayat job bulk generation di workspace ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3">Job ID</th>
                                <th class="py-2.5 px-3">Perihal</th>
                                <th class="py-2.5 px-3">Penerima</th>
                                <th class="py-2.5 px-3">Progres</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3 text-right">Unduh (.docx)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($jobs as $job)
                                <tr>
                                    <td class="py-2.5 px-3 font-mono text-xs">{{ $job->id }}</td>
                                    <td class="py-2.5 px-3 font-medium">{{ $job->perihal }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs">
                                        {{ $job->processed_count }} / {{ $job->recipient_count }}
                                    </td>
                                    <td class="py-2.5 px-3 w-44">
                                        <x-ui.progress :value="$job->progressPercent()" />
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <x-ui.status-badge :status="$job->status" />
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        @if ($job->output_path)
                                            <x-ui.button size="sm" variant="outline" :href="route('bulk.download', $job->id)">
                                                <x-lucide-download class="size-3.5" />
                                                <span>Unduh .docx ({{ $job->recipient_count }} hal)</span>
                                            </x-ui.button>
                                        @else
                                            <span class="text-xs text-muted-foreground">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>
</div>
