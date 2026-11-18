<div class="max-w-[720px] mx-auto">
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <x-ui.card-title>
                {{ $letterId ? 'Edit Draft Surat' : 'Buat Draft Surat Baru' }}
            </x-ui.card-title>
            <x-ui.card-description>
                @if ($letterId)
                    Surat berstatus Draft dapat diedit bebas tanpa alasan revisi dan mempertahankan nomor surat aktif.
                @else
                    Pilih template .docx dan lengkapi variabel. Nomor surat akan dialokasikan otomatis sesuai pola workspace <code class="font-mono text-foreground">{{ $workspace?->number_pattern }}</code>.
                @endif
            </x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="pt-6 space-y-6">
            @if ($errorMessage)
                <x-ui.alert variant="destructive" title="Validasi Gagal">
                    {{ $errorMessage }}
                </x-ui.alert>
            @endif

            <form wire:submit="save" class="space-y-6">
                {{-- Section 1: Metadata Surat --}}
                <div class="space-y-4">
                    <h3 class="text-base font-medium">1. Template &amp; Metadata Surat</h3>

                    <div>
                        <x-ui.label for="templateId">Template Dokumen (.docx)</x-ui.label>
                        <x-ui.select id="templateId" wire:model.live="templateId" class="mt-1.5" :disabled="(bool) $letterId">
                            <option value="">— Pilih Template —</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">
                                    [{{ $tpl->scope->label() }}] {{ $tpl->name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.form-error for="templateId" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <x-ui.label for="perihal">Perihal Surat (${perihal})</x-ui.label>
                            <x-ui.input id="perihal" wire:model="perihal" class="mt-1.5" placeholder="Mis. Undangan Rapat Koordinasi HIMATRIS" required />
                            <x-ui.form-error for="perihal" />
                        </div>

                        <div>
                            <x-ui.label for="letterDate">Tanggal Surat (${tanggal_surat})</x-ui.label>
                            <x-ui.input id="letterDate" type="date" wire:model="letterDate" class="mt-1.5" required />
                            <x-ui.form-error for="letterDate" />
                        </div>
                    </div>
                </div>

                <x-ui.separator />

                {{-- Section 2: Dynamic Variables from Template --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium">2. Variabel Isi Dokumen</h3>
                        @if ($selectedTemplate)
                            <span class="text-xs text-muted-foreground font-mono">
                                {{ count($editableFields) }} field dinamis
                            </span>
                        @endif
                    </div>

                    @if (! $selectedTemplate)
                        <p class="text-xs text-muted-foreground">Pilih template terlebih dahulu untuk menampilkan form variabel.</p>
                    @elseif (empty($editableFields))
                        <p class="text-xs text-muted-foreground">Template ini hanya menggunakan variabel sistem (nomor, tanggal, perihal).</p>
                    @else
                        @foreach ($editableFields as $field)
                            @php
                                $fname = $field['name'];
                                $ftype = $field['type'] ?? 'text';
                            @endphp

                            @if ($ftype === 'repeater')
                                <div class="rounded-lg border border-border p-4 space-y-3 bg-muted/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium">{{ $field['label'] }}</p>
                                            <p class="text-xs text-muted-foreground font-mono">Tabel repeater: ${{ '{' . $fname . '.*' . '}' }} (opsional)</p>
                                        </div>
                                        <x-ui.button type="button" variant="outline" size="sm" wire:click="addRepeaterRow('{{ $fname }}')">
                                            <x-lucide-plus class="size-3.5" />
                                            <span>Tambah Baris</span>
                                        </x-ui.button>
                                    </div>

                                    @foreach ($values[$fname] ?? [] as $rowIdx => $rowVals)
                                        <div class="grid grid-cols-12 gap-2 items-end" wire:key="rep-{{ $fname }}-{{ $rowIdx }}">
                                            @foreach ($field['columns'] ?? [] as $col)
                                                <div class="col-span-5">
                                                    <x-ui.label class="text-xs">{{ $col['label'] }}</x-ui.label>
                                                    <x-ui.input
                                                        wire:model="values.{{ $fname }}.{{ $rowIdx }}.{{ $col['name'] }}"
                                                        class="mt-1"
                                                        placeholder="{{ $col['label'] }}"
                                                    />
                                                </div>
                                            @endforeach
                                            <div class="col-span-2 flex justify-end">
                                                <x-ui.button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    wire:click="removeRepeaterRow('{{ $fname }}', {{ $rowIdx }})"
                                                    aria-label="Hapus baris"
                                                >
                                                    <x-lucide-trash-2 class="size-4 text-destructive" />
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($ftype === 'date')
                                <div>
                                    <div class="flex items-center justify-between">
                                        <x-ui.label for="var-{{ $fname }}">{{ $field['label'] }}</x-ui.label>
                                        <code class="font-mono text-[11px] text-muted-foreground">${{ '{' . $fname . '}' }}</code>
                                    </div>
                                    <x-ui.input
                                        id="var-{{ $fname }}"
                                        type="date"
                                        wire:model="values.{{ $fname }}"
                                        class="mt-1.5"
                                        required
                                    />
                                </div>
                            @else
                                <div>
                                    <div class="flex items-center justify-between">
                                        <x-ui.label for="var-{{ $fname }}">{{ $field['label'] }}</x-ui.label>
                                        <code class="font-mono text-[11px] text-muted-foreground">${{ '{' . $fname . '}' }}</code>
                                    </div>
                                    <x-ui.input
                                        id="var-{{ $fname }}"
                                        wire:model="values.{{ $fname }}"
                                        class="mt-1.5"
                                        placeholder="Masukkan {{ strtolower($field['label']) }}"
                                        required
                                    />
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <x-ui.button variant="outline" :href="route('letters.index')">Batal</x-ui.button>
                    <x-ui.button type="submit">
                        <x-lucide-sparkles class="size-4" />
                        <span>{{ $letterId ? 'Simpan Perubahan Draft' : 'Generate Surat & Nomor' }}</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>
</div>
