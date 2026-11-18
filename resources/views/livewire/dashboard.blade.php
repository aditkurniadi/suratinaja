<div class="space-y-6">
    {{-- Stat cards (Dokploy overview style, derived from real workspace counts) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <div class="p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Draft Surat</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $counts['draft'] }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/40">
                    <x-lucide-file-pen-line class="size-4 text-muted-foreground" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Menunggu TTD Basah</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $counts['menunggu_ttd'] }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/40">
                    <x-lucide-clock class="size-4 text-amber-500" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Surat Selesai</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $counts['selesai'] }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/40">
                    <x-lucide-circle-check class="size-4 text-emerald-500" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Nomor Hangus (Audit)</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ $counts['hangus'] }}</p>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/40">
                    <x-lucide-circle-x class="size-4 text-red-500" />
                </div>
            </div>
        </x-ui.card>
    </div>

    {{-- Recent letters card --}}
    <x-ui.card>
        <x-ui.card-header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
            <div class="space-y-1">
                <x-ui.card-title>Surat Terbaru — {{ $workspace?->name }}</x-ui.card-title>
                <x-ui.card-description>
                    Pola nomor aktif: <code class="font-mono text-xs text-foreground">{{ $workspace?->number_pattern }}</code>
                </x-ui.card-description>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button variant="outline" size="sm" :href="route('letters.index')">
                    Lihat Semua
                </x-ui.button>
                @if ($role?->canWrite())
                    <x-ui.button size="sm" :href="route('letters.create')">
                        <x-lucide-plus class="size-4" />
                        <span>Buat Surat</span>
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4">
            @if ($recentLetters->isEmpty())
                <x-ui.empty-state
                    icon="file-text"
                    title="Belum ada surat di workspace ini"
                    description="Mulai dengan memilih template .docx dan mengisi variabel untuk menghasilkan surat bernomor otomatis."
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
                            @foreach ($recentLetters as $letter)
                                <tr class="hover:bg-muted/40 transition-colors">
                                    <td class="py-2.5 px-3 font-mono text-[13px] font-medium">
                                        {{ $letter->formatted_number ?? '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 font-medium">
                                        <a href="{{ route('letters.show', $letter->id) }}" class="hover:underline">
                                            {{ $letter->perihal }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground hidden md:table-cell">
                                        {{ $letter->template?->name ?? '—' }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <x-ui.status-badge :status="$letter->status" />
                                    </td>
                                    <td class="py-2.5 px-3 text-xs text-muted-foreground hidden sm:table-cell">
                                        {{ $letter->letter_date?->format('d M Y') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <x-ui.button variant="ghost" size="sm" :href="route('letters.show', $letter->id)">
                                            Detail
                                        </x-ui.button>
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
