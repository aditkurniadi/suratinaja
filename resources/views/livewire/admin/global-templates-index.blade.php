<div class="space-y-6">
    @if ($errorMessage)
        <x-ui.alert variant="destructive" title="Gagal Menyimpan Template Global">
            {{ $errorMessage }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <x-ui.card class="lg:col-span-5">
            <x-ui.card-header class="border-b border-border pb-4">
                <x-ui.card-title>Unggah Master Template Global</x-ui.card-title>
                <x-ui.card-description>
                    Template global dapat dipakai atau diduplikasi oleh seluruh workspace tetapi tidak dapat diedit oleh workspace (FR-11).
                </x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4">
                <form wire:submit="uploadGlobal" class="space-y-4">
                    <div>
                        <x-ui.label for="global-name">Nama Template Global</x-ui.label>
                        <x-ui.input id="global-name" wire:model="name" class="mt-1.5" required placeholder="Mis. Standar Undangan Resmi Kampus" />
                    </div>
                    <div>
                        <x-ui.label for="global-desc">Deskripsi</x-ui.label>
                        <x-ui.input id="global-desc" wire:model="description" class="mt-1.5" />
                    </div>
                    <div>
                        <x-ui.label for="global-file">File .docx</x-ui.label>
                        <x-ui.input id="global-file" type="file" wire:model="file" accept=".docx" class="mt-1.5" required />
                    </div>
                    <x-ui.button type="submit" class="w-full">Terbitkan Template Global</x-ui.button>
                </form>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card class="lg:col-span-7">
            <x-ui.card-header class="border-b border-border pb-4">
                <x-ui.card-title>Daftar Master Template Global</x-ui.card-title>
                <x-ui.card-description>Kelola versi dan status aktif/deprecated.</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-4">
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3">Nama</th>
                                <th class="py-2.5 px-3">Versi</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($templates as $tpl)
                                <tr>
                                    <td class="py-2.5 px-3 font-medium">{{ $tpl->name }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs">v{{ $tpl->version }}</td>
                                    <td class="py-2.5 px-3">
                                        <x-ui.badge :variant="$tpl->is_deprecated ? 'outline' : 'default'">
                                            {{ $tpl->is_deprecated ? 'Deprecated' : 'Aktif' }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="py-2.5 px-3 text-right space-x-1">
                                        <x-ui.button variant="outline" size="sm" wire:click="$set('versionTargetId', {{ $tpl->id }})">
                                            Versi Baru
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" wire:click="toggleDeprecate({{ $tpl->id }})">
                                            {{ $tpl->is_deprecated ? 'Aktifkan' : 'Deprecate' }}
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($versionTargetId)
                    <form wire:submit="uploadNewVersion" class="mt-4 rounded-lg border border-border p-4 space-y-3">
                        <p class="text-xs font-semibold">Unggah Versi Baru untuk Template #{{ $versionTargetId }}</p>
                        <x-ui.input type="file" wire:model="versionFile" accept=".docx" required />
                        <div class="flex justify-end gap-2">
                            <x-ui.button variant="outline" size="sm" wire:click="$set('versionTargetId', null)">Batal</x-ui.button>
                            <x-ui.button type="submit" size="sm">Simpan Versi Baru</x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>
</div>
