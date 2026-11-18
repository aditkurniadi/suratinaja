<div class="space-y-6">
    <x-ui.card>
        <x-ui.card-header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
            <div class="space-y-1">
                <x-ui.card-title>Daftar Surat — {{ $workspace?->name }}</x-ui.card-title>
                <x-ui.card-description>
                    Seluruh surat terisolasi pada workspace aktif. Gunakan filter status atau cari nomor/perihal (FR-08).
                </x-ui.card-description>
            </div>
            @if ($role?->canWrite())
                <div class="flex items-center gap-2">
                    <x-ui.button variant="outline" size="sm" :href="route('bulk.index')">
                        <x-lucide-layers class="size-4" />
                        <span>Bulk Generate</span>
                    </x-ui.button>
                    <x-ui.button size="sm" :href="route('letters.create')">
                        <x-lucide-plus class="size-4" />
                        <span>Buat Surat</span>
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card-header>

        <x-ui.card-content class="pt-4 space-y-4">
            {{-- Filter toolbar --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <x-lucide-search class="size-4 text-muted-foreground absolute left-3 top-2.5" />
                    <x-ui.input
                        wire:model.live.debounce.250ms="search"
                        class="pl-9"
                        placeholder="Cari nomor surat (mis. 012/HIMATRIS) atau perihal..."
                    />
                </div>
                <div class="w-full sm:w-52">
                    <x-ui.select wire:model.live="status" aria-label="Filter Status Surat">
                        <option value="">Semua Status</option>
                        @foreach (\App\Enums\LetterStatus::cases() as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>

            @if ($letters->isEmpty())
                <x-ui.empty-state
                    icon="files"
                    title="Belum ada surat yang cocok"
                    description="Tidak ada surat yang sesuai dengan filter pencarian atau belum ada surat yang dibuat di workspace ini."
                >
                    @if ($role?->canWrite())
                        <x-ui.button size="sm" :href="route('letters.create')">
                            <x-lucide-plus class="size-4" />
                            <span>Buat Surat Pertama</span>
                        </x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3">Nomor Surat</th>
                                <th class="py-2.5 px-3">Perihal</th>
                                <th class="py-2.5 px-3 hidden md:table-cell">Template</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3 hidden sm:table-cell">Tanggal</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($letters as $letter)
                                <tr class="hover:bg-muted/40 transition-colors">
                                    <td class="py-2.5 px-3 font-mono text-[13px] font-medium">
                                        <span @class(['line-through text-muted-foreground' => $letter->status === \App\Enums\LetterStatus::Dibatalkan])>
                                            {{ $letter->formatted_number ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <a href="{{ route('letters.show', $letter->id) }}" class="font-medium hover:underline">
                                            {{ $letter->perihal }}
                                        </a>
                                        @if ($letter->version > 1)
                                            <span class="ml-1 rounded border border-border px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                                                v{{ $letter->version }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground hidden md:table-cell">
                                        @if ($letter->is_manual_number)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-600 dark:text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded">
                                                <x-lucide-external-link class="size-3" />
                                                <span>Dokumen Eksternal</span>
                                            </span>
                                        @else
                                            {{ $letter->template?->name ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <x-ui.status-badge :status="$letter->status" />
                                    </td>
                                    <td class="py-2.5 px-3 text-xs text-muted-foreground hidden sm:table-cell">
                                        {{ $letter->letter_date?->format('d M Y') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($role?->canWrite())
                                                @if ($letter->status === \App\Enums\LetterStatus::Draft)
                                                    <x-ui.button
                                                        variant="outline"
                                                        size="sm"
                                                        wire:click="transition({{ $letter->id }}, 'menunggu_ttd')"
                                                    >
                                                        Ajukan TTD
                                                    </x-ui.button>
                                                @elseif ($letter->status === \App\Enums\LetterStatus::MenungguTtd)
                                                    <x-ui.button
                                                        variant="outline"
                                                        size="sm"
                                                        wire:click="transition({{ $letter->id }}, 'selesai')"
                                                    >
                                                        Tandai Selesai
                                                    </x-ui.button>
                                                @endif
                                            @endif
                                            <x-ui.button variant="ghost" size="sm" :href="route('letters.show', $letter->id)">
                                                Detail
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-2">
                    {{ $letters->links() }}
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>
</div>
