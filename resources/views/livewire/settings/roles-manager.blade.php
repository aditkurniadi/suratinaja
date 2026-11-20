<div class="space-y-6">
    {{-- Header dengan tombol Tambah Role --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-foreground">Role & Hak Akses Workspace</h2>
            <p class="text-xs text-muted-foreground mt-0.5">
                Kelola peran pengguna dan atur batasan akses fitur di dalam workspace {{ $workspace->name }}.
            </p>
        </div>

        @can('role.manage')
            <x-ui.button type="button" wire:click="openCreateModal" size="sm" class="gap-1.5 shrink-0">
                <x-lucide-shield-plus class="size-4" />
                <span>Tambah Role Kustom</span>
            </x-ui.button>
        @endcan
    </div>

    {{-- Daftar Role Card Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($roles as $r)
            @php
                $isDefault = in_array($r->name, [
                    \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_ADMIN,
                    \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_SECRETARY,
                    \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_CHAIRMAN,
                    \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_MEMBER,
                ], true);
            @endphp
            <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between shadow-2xs space-y-3">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-sm text-foreground truncate" title="{{ $r->name }}">
                            {{ $r->name }}
                        </span>
                        @if ($isDefault)
                            <x-ui.badge variant="secondary" class="text-[10px] shrink-0">Bawaan</x-ui.badge>
                        @else
                            <x-ui.badge variant="outline" class="text-[10px] border-primary/30 text-primary shrink-0">Kustom</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 text-xs text-muted-foreground pt-1">
                        <span class="inline-flex items-center gap-1">
                            <x-lucide-users class="size-3.5" />
                            <span>{{ $r->users_count }} anggota</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <x-lucide-key-round class="size-3.5" />
                            <span>{{ $r->permissions_count }} izin</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-1 pt-2 border-t border-border">
                    @can('role.manage')
                        <x-ui.button
                            type="button"
                            size="xs"
                            variant="outline"
                            wire:click="openEditModal({{ $r->id }})"
                            class="gap-1"
                        >
                            <x-lucide-settings-2 class="size-3" />
                            <span>Atur Akses</span>
                        </x-ui.button>

                        @if (! $isDefault && $r->users_count === 0)
                            <x-ui.button
                                type="button"
                                size="xs"
                                variant="destructive"
                                wire:click="deleteRole({{ $r->id }})"
                                wire:confirm="Hapus role '{{ $r->name }}' dari workspace?"
                                class="p-1.5"
                                title="Hapus Role"
                            >
                                <x-lucide-trash-2 class="size-3" />
                            </x-ui.button>
                        @endif
                    @endcan
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal Tambah / Edit Matriks Permission Role --}}
    @if ($showRoleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-3xl max-h-[90vh] flex flex-col rounded-xl border border-border bg-background shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5 bg-muted/20 shrink-0">
                    <div>
                        <h3 class="text-base font-semibold text-foreground">
                            {{ $editingRoleId ? 'Atur Hak Akses: ' . $roleName : 'Buat Role Kustom Baru' }}
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Centang hak akses fitur yang dapat dilakukan oleh role ini.
                        </p>
                    </div>
                    <button type="button" wire:click="$set('showRoleModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <form wire:submit="saveRole" class="flex-1 flex flex-col min-h-0">
                    <div class="p-5 overflow-y-auto space-y-5 flex-1 min-h-0">
                        <div>
                            <x-ui.label for="roleNameInput">Nama Role</x-ui.label>
                            <x-ui.input
                                id="roleNameInput"
                                wire:model="roleName"
                                placeholder="Mis. Staf Humas, Bendahara Kegiatan..."
                                class="mt-1.5"
                                :disabled="$editingRoleId && $roleName === \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_ADMIN"
                                required
                            />
                            <x-ui.form-error for="roleName" />
                        </div>

                        {{-- Matriks Checkbox per Kelompok Modul --}}
                        <div class="space-y-4">
                            <label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground block">
                                Matriks Hak Akses Fitur
                            </label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($permissionGroups as $gKey => $g)
                                    @php
                                        $gPermKeys = array_keys($g['permissions']);
                                        $gSelectedCount = count(array_intersect($gPermKeys, $selectedPermissions));
                                        $allSelected = count($gPermKeys) > 0 && $gSelectedCount === count($gPermKeys);
                                    @endphp
                                    <div class="rounded-lg border border-border bg-card p-3 space-y-2.5">
                                        <div class="flex items-center justify-between gap-2 border-b border-border/60 pb-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-semibold text-xs text-foreground">{{ $g['label'] }}</span>
                                                <span class="text-[10px] text-muted-foreground font-mono">({{ $gSelectedCount }}/{{ count($gPermKeys) }})</span>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="toggleGroup('{{ $gKey }}')"
                                                class="text-[11px] font-medium text-primary hover:underline cursor-pointer"
                                            >
                                                {{ $allSelected ? 'Batal Semua' : 'Pilih Semua' }}
                                            </button>
                                        </div>

                                        <div class="space-y-1.5">
                                            @foreach ($g['permissions'] as $pKey => $pLabel)
                                                <label class="flex items-start gap-2 p-1 rounded hover:bg-muted/50 cursor-pointer text-xs">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $pKey }}"
                                                        wire:model="selectedPermissions"
                                                        class="mt-0.5 rounded border-border text-primary focus:ring-primary size-3.5"
                                                    />
                                                    <div class="min-w-0">
                                                        <span class="font-mono text-[11px] text-foreground font-medium block truncate">{{ $pKey }}</span>
                                                        <span class="text-[10px] text-muted-foreground block leading-tight">{{ $pLabel }}</span>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-border bg-muted/10 shrink-0">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showRoleModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan Role &amp; Izin</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
