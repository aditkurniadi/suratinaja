<div class="space-y-6">
    <x-ui.card>
        <x-ui.card-header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
            <div class="space-y-1">
                <x-ui.card-title>Library Template .docx</x-ui.card-title>
                <x-ui.card-description>
                    Gunakan template global (read-only) atau unggah master template .docx khusus workspace {{ $workspace?->name }} (DEC-001).
                </x-ui.card-description>
            </div>
            @if ($role?->canWrite())
                <x-ui.button size="sm" wire:click="$set('showUploadModal', true)">
                    <x-lucide-upload class="size-4" />
                    <span>Unggah Template .docx</span>
                </x-ui.button>
            @endif
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
                    description="Unggah dokumen .docx dengan placeholder variabel seperti ${nama_kegiatan} atau duplikasi dari Template Global."
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
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3">Nama Template</th>
                                <th class="py-2.5 px-3">Scope</th>
                                <th class="py-2.5 px-3">Variabel Terdeteksi (${...})</th>
                                <th class="py-2.5 px-3 hidden sm:table-cell">Versi</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($list as $tpl)
                                <tr class="hover:bg-muted/40">
                                    <td class="py-3 px-3">
                                        <div class="font-medium flex items-center gap-2">
                                            <span>{{ $tpl->name }}</span>
                                            @if ($tpl->is_deprecated)
                                                <x-ui.badge variant="outline">Deprecated</x-ui.badge>
                                            @endif
                                        </div>
                                        @if ($tpl->description)
                                            <p class="text-xs text-muted-foreground mt-0.5">{{ $tpl->description }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3">
                                        <x-ui.badge :variant="$tpl->isGlobal() ? 'secondary' : 'outline'">
                                            {{ $tpl->scope->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($tpl->variables_schema ?? [] as $var)
                                                <span class="rounded border border-border bg-muted/60 px-1.5 py-0.5 font-mono text-[11px]">
                                                    ${{ '{' . $var['name'] . '}' }}
                                                    <span class="text-muted-foreground">({{ $var['type'] }})</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-xs font-mono text-muted-foreground hidden sm:table-cell">
                                        v{{ $tpl->version }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <x-ui.button variant="ghost" size="sm" :href="route('templates.download', $tpl->id)">
                                                Unduh
                                            </x-ui.button>
                                            @if ($role?->canWrite())
                                                @if ($tpl->isGlobal())
                                                    <x-ui.button variant="outline" size="sm" wire:click="duplicateGlobal({{ $tpl->id }})">
                                                        Duplikasi ke Workspace
                                                    </x-ui.button>
                                                @else
                                                    <x-ui.button variant="outline" size="sm" :href="route('letters.create', ['template_id' => $tpl->id])">
                                                        Pakai
                                                    </x-ui.button>
                                                @endif
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

    {{-- 2-step Upload & Variable Preview Dialog (AC-03-01) --}}
    @if ($showUploadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-xl border border-border bg-background p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold">Unggah Template .docx Workspace</h3>
                    <button type="button" wire:click="$set('showUploadModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Sistem otomatis memindai variabel <code class="font-mono">${...}</code> di dalam dokumen sebelum disimpan.
                </p>

                @if ($uploadError)
                    <x-ui.alert variant="destructive" title="Validasi Template Gagal" class="mt-3">
                        {{ $uploadError }}
                    </x-ui.alert>
                @endif

                <form wire:submit="saveTemplate" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="templateFile">File Master Template (.docx)</x-ui.label>
                        <x-ui.input id="templateFile" type="file" wire:model="templateFile" accept=".docx" class="mt-1.5" required />
                        <x-ui.form-error for="templateFile" />
                    </div>

                    <div>
                        <x-ui.label for="templateName">Nama Template</x-ui.label>
                        <x-ui.input id="templateName" wire:model="templateName" class="mt-1.5" placeholder="Mis. Surat Undangan Rapat Koordinasi" required />
                        <x-ui.form-error for="templateName" />
                    </div>

                    <div>
                        <x-ui.label for="templateDescription">Deskripsi Singkat (Opsional)</x-ui.label>
                        <x-ui.input id="templateDescription" wire:model="templateDescription" class="mt-1.5" placeholder="Digunakan untuk undangan kegiatan internal/eksternal" />
                    </div>

                    @if (! empty($detectedVariables))
                        <div class="rounded-lg border border-border bg-muted/40 p-3 space-y-2">
                            <p class="text-xs font-semibold text-foreground">
                                Pratinjau Variabel Terdeteksi ({{ count($detectedVariables) }} variabel)
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($detectedVariables as $v)
                                    <span class="inline-flex items-center gap-1 rounded border border-border bg-background px-2 py-0.5 font-mono text-xs">
                                        <span>${{ '{' . $v['name'] . '}' }}</span>
                                        <span class="text-[10px] uppercase text-muted-foreground">{{ $v['type'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="outline" wire:click="$set('showUploadModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit" :disabled="empty($detectedVariables)">
                            Simpan Template
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
