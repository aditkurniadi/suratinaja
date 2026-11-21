<div class="space-y-6">
    {{-- Tabs Navigasi Sub-Menu --}}
    <div class="flex items-center gap-2 border-b border-border pb-2 text-sm font-medium">
        <button
            type="button"
            wire:click="$set('activeTab', 'members')"
            @class([
                'inline-flex items-center gap-2 px-3 py-1.5 rounded-md transition-colors cursor-pointer',
                'bg-primary text-primary-foreground font-semibold shadow-2xs' => $activeTab === 'members',
                'text-muted-foreground hover:text-foreground hover:bg-muted' => $activeTab !== 'members',
            ])
        >
            <x-lucide-users class="size-4" />
            <span>Anggota Workspace</span>
            <span class="rounded-full bg-background/20 px-1.5 py-0.2 text-xs">{{ $members->count() }}</span>
        </button>

        @can('role.view')
            <button
                type="button"
                wire:click="$set('activeTab', 'roles')"
                @class([
                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-md transition-colors cursor-pointer',
                    'bg-primary text-primary-foreground font-semibold shadow-2xs' => $activeTab === 'roles',
                    'text-muted-foreground hover:text-foreground hover:bg-muted' => $activeTab !== 'roles',
                ])
            >
                <x-lucide-shield-check class="size-4" />
                <span>Role &amp; Hak Akses</span>
                <span class="rounded-full bg-background/20 px-1.5 py-0.2 text-xs">{{ $workspaceRoles->count() }}</span>
            </button>
        @endcan
    </div>

    @if ($activeTab === 'roles')
        <livewire:settings.roles-manager />
    @else
        @if ($lastInviteUrl)
            <x-ui.alert title="Tautan Undangan Siap Dibagikan">
                <div class="mt-1 flex flex-col sm:flex-row sm:items-center gap-2">
                    <code class="font-mono text-xs bg-muted px-2 py-1 rounded break-all">{{ $lastInviteUrl }}</code>
                </div>
            </x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.card-header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-4">
                <div class="space-y-1">
                    <x-ui.card-title>Anggota Workspace — {{ $workspace->name }}</x-ui.card-title>
                    <x-ui.card-description>
                        Kelola akses anggota dan tetapkan peran (Admin, Sekretaris, Ketua, atau Role Kustom).
                    </x-ui.card-description>
                </div>
                @can('member.invite')
                    <x-ui.button size="sm" wire:click="$set('showInviteModal', true)" class="gap-1.5 shrink-0">
                        <x-lucide-user-plus class="size-4" />
                        <span>Undang Anggota</span>
                    </x-ui.button>
                @endcan
            </x-ui.card-header>

            <x-ui.card-content class="pt-4 space-y-6">
                <div class="overflow-x-auto">
                    <table role="grid" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                                <th class="py-2.5 px-3">Nama</th>
                                <th class="py-2.5 px-3">Email</th>
                                <th class="py-2.5 px-3">Peran (Role)</th>
                                <th class="py-2.5 px-3 hidden sm:table-cell">Bergabung</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($members as $member)
                                @php
                                    $userRole = $member->user?->roles->first();
                                    $currentRoleName = $userRole?->name ?? match ($member->role?->value) {
                                        'admin' => \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_ADMIN,
                                        'chairman' => \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_CHAIRMAN,
                                        default => \App\Services\Authorization\WorkspacePermissionRegistrar::ROLE_SECRETARY,
                                    };
                                @endphp
                                <tr>
                                    <td class="py-2.5 px-3 font-medium">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $member->user?->name }}</span>
                                            @if ($member->user?->is_super_admin)
                                                <span class="rounded bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                                    Super Admin
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-muted-foreground">{{ $member->user?->email }}</td>
                                    <td class="py-2.5 px-3">
                                        @can('member.assign_role')
                                            <select
                                                wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                                class="h-8 rounded-md border border-input bg-background px-2 text-xs focus:ring-1 focus:ring-primary"
                                                aria-label="Peran {{ $member->user?->name }}"
                                            >
                                                @foreach ($workspaceRoles as $wRole)
                                                    <option value="{{ $wRole->name }}" @selected($currentRoleName === $wRole->name)>
                                                        {{ $wRole->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="rounded bg-muted px-2 py-1 text-xs font-medium">
                                                {{ $currentRoleName }}
                                            </span>
                                        @endcan
                                    </td>
                                    <td class="py-2.5 px-3 text-xs text-muted-foreground hidden sm:table-cell">
                                        {{ $member->joined_at?->format('d M Y') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        @can('member.remove')
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-lg text-xs font-semibold text-destructive hover:bg-destructive/10 transition cursor-pointer"
                                                @click="$store.confirmModal.show({
                                                    title: 'Cabut Akses Anggota?',
                                                    message: 'Yakin ingin mencabut akses {{ addslashes($member->user?->name) }} dari workspace ini?',
                                                    confirmText: 'Ya, Cabut Akses'
                                                }).then(ok => ok && $wire.revoke({{ $member->id }}))"
                                            >
                                                Cabut Akses
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($lastInviteUrl)
                    <div class="mb-4 rounded-xl border border-primary/30 bg-primary/5 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <span class="font-bold text-foreground flex items-center gap-1.5">
                                <x-lucide-check-circle-2 class="size-4 text-emerald-500" />
                                <span>Undangan Berhasil Dibuat</span>
                            </span>
                            <p class="text-muted-foreground truncate">
                                Tautan undangan: <code class="bg-muted px-1 py-0.5 rounded font-mono">{{ $lastInviteUrl }}</code>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="navigator.clipboard.writeText('{{ $lastInviteUrl }}'); $dispatch('toast', {type: 'success', title: 'Tautan disalin', description: 'Tautan undangan disalin ke clipboard.'})"
                                class="px-2.5 py-1.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors cursor-pointer flex items-center gap-1.5"
                            >
                                <x-lucide-copy class="size-3.5" />
                                <span>Salin Link</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$set('lastInviteUrl', null)"
                                class="p-1 rounded text-muted-foreground hover:text-foreground"
                            >
                                <x-lucide-x class="size-4" />
                            </button>
                        </div>
                    </div>
                @endif

                @if ($invitations->isNotEmpty())
                    <x-ui.separator />
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Undangan Tertunda (Pending Invitations)</h3>
                            <span class="text-[11px] text-muted-foreground">{{ $invitations->count() }} undangan</span>
                        </div>
                        <div class="rounded-lg border border-border overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th class="py-2.5 px-3 font-semibold">Alamat Email</th>
                                        <th class="py-2.5 px-3 font-semibold">Peran</th>
                                        <th class="py-2.5 px-3 font-semibold hidden sm:table-cell">Batas Aktif</th>
                                        <th class="py-2.5 px-3 font-semibold hidden md:table-cell">Pengundang</th>
                                        <th class="py-2.5 px-3 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($invitations as $inv)
                                        <tr class="hover:bg-muted/30 transition-colors">
                                            <td class="py-2.5 px-3 font-medium">{{ $inv->email }}</td>
                                            <td class="py-2.5 px-3">
                                                <span class="rounded bg-primary/10 text-primary px-2 py-0.5 text-[11px] font-semibold">
                                                    {{ $inv->getRoleDisplayName() }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-muted-foreground hidden sm:table-cell">
                                                {{ $inv->expires_at?->diffForHumans() }}
                                            </td>
                                            <td class="py-2.5 px-3 text-muted-foreground hidden md:table-cell">
                                                {{ $inv->inviter?->name ?? '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        @click="navigator.clipboard.writeText('{{ route('invitations.accept', $inv->token) }}'); $dispatch('toast', {type: 'info', title: 'Tautan disalin', description: 'Tautan undangan disalin ke clipboard.'})"
                                                        class="px-2 py-1 rounded bg-muted hover:bg-accent text-foreground text-[11px] font-medium transition-colors cursor-pointer"
                                                        title="Salin Tautan Undangan"
                                                    >
                                                        Salin Link
                                                    </button>
                                                    @can('member.invite')
                                                        <button
                                                            type="button"
                                                            wire:click="resendInvitation({{ $inv->id }})"
                                                            class="px-2 py-1 rounded bg-muted hover:bg-accent text-foreground text-[11px] font-medium transition-colors cursor-pointer"
                                                            title="Kirim Ulang Email"
                                                        >
                                                            Kirim Ulang
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="$store.confirmModal.show({
                                                                title: 'Batalkan Undangan?',
                                                                message: 'Yakin ingin membatalkan undangan untuk {{ addslashes($inv->email) }}?',
                                                                confirmText: 'Ya, Batalkan'
                                                            }).then(ok => ok && $wire.revokeInvitation({{ $inv->id }}))"
                                                            class="px-2 py-1 rounded hover:bg-destructive/10 text-destructive text-[11px] font-medium transition-colors cursor-pointer"
                                                            title="Batalkan Undangan"
                                                        >
                                                            Batalkan
                                                        </button>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        @if ($showInviteModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
                <div class="w-full max-w-md rounded-xl border border-border bg-background shadow-xl flex flex-col max-h-[90dvh] overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-5 py-4 shrink-0">
                        <h3 class="text-base font-semibold">Undang Anggota ke {{ $workspace->name }}</h3>
                        <button type="button" wire:click="$set('showInviteModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                            <x-lucide-x class="size-4" />
                        </button>
                    </div>

                    <form wire:submit="invite" class="flex flex-col flex-1 min-h-0 overflow-y-auto">
                        <div class="p-5 space-y-4 flex-1">
                            <div>
                                <x-ui.label for="invite-email">Alamat Email</x-ui.label>
                                <x-ui.input id="invite-email" type="email" wire:model="inviteEmail" class="mt-1.5" placeholder="anggota@kampus.ac.id" required />
                                <x-ui.form-error for="inviteEmail" />
                            </div>
                            <div>
                                <x-ui.label for="invite-role">Peran di Workspace</x-ui.label>
                                <x-ui.select id="invite-role" wire:model="inviteRole" class="mt-1.5">
                                    @foreach ($workspaceRoles as $wRole)
                                        <option value="{{ $wRole->name }}">
                                            {{ $wRole->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.form-error for="inviteRole" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 px-5 py-3 border-t border-border bg-muted/20 shrink-0">
                            <x-ui.button variant="outline" type="button" wire:click="$set('showInviteModal', false)">Batal</x-ui.button>
                            <x-ui.button type="submit">Kirim Undangan</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif
</div>
