<div class="space-y-6">
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
                    Kelola akses anggota dan tetapkan peran (Admin, Sekretaris, atau Ketua read-only).
                </x-ui.card-description>
            </div>
            <x-ui.button size="sm" wire:click="$set('showInviteModal', true)">
                <x-lucide-user-plus class="size-4" />
                <span>Undang Anggota</span>
            </x-ui.button>
        </x-ui.card-header>

        <x-ui.card-content class="pt-4 space-y-6">
            <div class="overflow-x-auto">
                <table role="grid" class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs font-medium text-muted-foreground">
                            <th class="py-2.5 px-3">Nama</th>
                            <th class="py-2.5 px-3">Email</th>
                            <th class="py-2.5 px-3">Peran</th>
                            <th class="py-2.5 px-3 hidden sm:table-cell">Bergabung</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($members as $member)
                            <tr>
                                <td class="py-2.5 px-3 font-medium">{{ $member->user?->name }}</td>
                                <td class="py-2.5 px-3 text-muted-foreground">{{ $member->user?->email }}</td>
                                <td class="py-2.5 px-3">
                                    <select
                                        wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                        aria-label="Peran {{ $member->user?->name }}"
                                    >
                                        @foreach (\App\Enums\WorkspaceRole::cases() as $roleCase)
                                            <option value="{{ $roleCase->value }}" @selected($member->role === $roleCase)>
                                                {{ $roleCase->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-3 text-xs text-muted-foreground hidden sm:table-cell">
                                    {{ $member->joined_at?->format('d M Y') }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <x-ui.button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        wire:click="revoke({{ $member->id }})"
                                    >
                                        Cabut Akses
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invitations->isNotEmpty())
                <x-ui.separator />
                <div class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Undangan Pending</h3>
                    <div class="divide-y divide-border rounded-lg border border-border">
                        @foreach ($invitations as $inv)
                            <div class="flex items-center justify-between px-3 py-2 text-xs">
                                <div>
                                    <span class="font-medium">{{ $inv->email }}</span>
                                    <span class="text-muted-foreground ml-2">({{ $inv->role->label() }})</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('invitations.accept', $inv->token) }}" class="underline text-muted-foreground hover:text-foreground">
                                        Link Undangan
                                    </a>
                                    <button type="button" wire:click="revokeInvitation({{ $inv->id }})" class="text-destructive hover:underline">
                                        Batalkan
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    @if ($showInviteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold">Undang Anggota ke {{ $workspace->name }}</h3>
                    <button type="button" wire:click="$set('showInviteModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <form wire:submit="invite" class="mt-4 space-y-4">
                    <div>
                        <x-ui.label for="invite-email">Alamat Email</x-ui.label>
                        <x-ui.input id="invite-email" type="email" wire:model="inviteEmail" class="mt-1.5" placeholder="anggota@kampus.ac.id" required />
                        <x-ui.form-error for="inviteEmail" />
                    </div>
                    <div>
                        <x-ui.label for="invite-role">Peran di Workspace</x-ui.label>
                        <x-ui.select id="invite-role" wire:model="inviteRole" class="mt-1.5">
                            <option value="secretary">Sekretaris (Buat, Edit, Bulk, Revisi)</option>
                            <option value="chairman">Ketua (Monitoring Read-Only)</option>
                            <option value="admin">Admin Workspace (Akses Penuh)</option>
                        </x-ui.select>
                        <x-ui.form-error for="inviteRole" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="outline" wire:click="$set('showInviteModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Kirim Undangan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
