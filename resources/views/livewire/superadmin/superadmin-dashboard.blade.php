<div class="space-y-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-border pb-5">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                    <x-lucide-shield-check class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">Panel Super Admin</h1>
                    <p class="text-xs text-muted-foreground mt-0.5">Pemantauan metrik global sistem dan tata kelola pengguna terpusat.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button wire:click="openCreateModal" class="gap-1.5 text-xs">
                <x-lucide-user-plus class="w-4 h-4" />
                <span>Tambah Pengguna</span>
            </x-ui.button>
        </div>
    </div>

    {{-- Ringkasan Global (Stats Grid) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card Total Users --}}
        <div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">Total Pengguna</span>
                <span class="rounded-md bg-blue-500/10 p-1.5 text-blue-600 dark:text-blue-400">
                    <x-lucide-users class="w-4 h-4" />
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight">{{ number_format($globalStats['totalUsers']) }}</span>
                <span class="text-[11px] text-muted-foreground">akun terdaftar</span>
            </div>
            <div class="mt-2.5 flex items-center gap-3 text-[11px] text-muted-foreground border-t border-border/60 pt-2">
                <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $globalStats['activeUsers'] }} aktif</span>
                <span>•</span>
                <span class="text-red-600 dark:text-red-400 font-medium">{{ $globalStats['suspendedUsers'] }} suspended</span>
                <span>•</span>
                <span class="text-purple-600 dark:text-purple-400 font-medium">{{ $globalStats['demoUsers'] }} demo</span>
            </div>
        </div>

        {{-- Card Total Workspaces --}}
        <div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">Total Workspace</span>
                <span class="rounded-md bg-amber-500/10 p-1.5 text-amber-600 dark:text-amber-400">
                    <x-lucide-building-2 class="w-4 h-4" />
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight">{{ number_format($globalStats['totalWorkspaces']) }}</span>
                <span class="text-[11px] text-muted-foreground">organisasi</span>
            </div>
            <div class="mt-2.5 text-[11px] text-muted-foreground border-t border-border/60 pt-2">
                <a href="{{ route('admin.workspaces.index') }}" class="font-medium text-primary hover:underline flex items-center gap-1">
                    <span>Kelola seluruh workspace</span>
                    <x-lucide-arrow-right class="w-3 h-3" />
                </a>
            </div>
        </div>

        {{-- Card Surat Keluar --}}
        <div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">Total Surat Keluar</span>
                <span class="rounded-md bg-emerald-500/10 p-1.5 text-emerald-600 dark:text-emerald-400">
                    <x-lucide-file-text class="w-4 h-4" />
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight">{{ number_format($globalStats['totalLetters']) }}</span>
                <span class="text-[11px] text-muted-foreground">dokumen</span>
            </div>
            <div class="mt-2.5 text-[11px] text-muted-foreground border-t border-border/60 pt-2">
                Agregat seluruh workspace
            </div>
        </div>

        {{-- Card Surat Masuk --}}
        <div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-muted-foreground">Total Surat Masuk</span>
                <span class="rounded-md bg-indigo-500/10 p-1.5 text-indigo-600 dark:text-indigo-400">
                    <x-lucide-inbox class="w-4 h-4" />
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight">{{ number_format($globalStats['totalSuratMasuk']) }}</span>
                <span class="text-[11px] text-muted-foreground">arsip tercatat</span>
            </div>
            <div class="mt-2.5 text-[11px] text-muted-foreground border-t border-border/60 pt-2">
                Agregat seluruh disposisi
            </div>
        </div>
    </div>

    {{-- Workspace Aktif & Ringkasan --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-foreground">Workspace Aktif</h2>
                <p class="text-xs text-muted-foreground">Daftar workspace terbaru dan volume pengguna di dalamnya.</p>
            </div>
            <a href="{{ route('admin.workspaces.index') }}" class="text-xs font-medium text-primary hover:underline">
                Lihat Semua Workspace &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse ($activeWorkspaces as $ws)
                <div class="rounded-lg border border-border bg-card p-3.5 hover:border-foreground/30 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-xs font-bold text-foreground line-clamp-1">{{ $ws->name }}</h3>
                                <p class="text-[11px] text-muted-foreground font-mono mt-0.5">{{ $ws->number_code ?: $ws->slug }}</p>
                            </div>
                            <button
                                type="button"
                                wire:click="switchToWorkspace({{ $ws->id }})"
                                class="shrink-0 text-[11px] font-semibold text-primary hover:underline"
                            >
                                Buka &rarr;
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-[11px] text-muted-foreground border-t border-border/60 pt-2">
                        <span class="inline-flex items-center gap-1">
                            <x-lucide-users class="w-3.5 h-3.5 text-muted-foreground" />
                            <span>{{ $ws->members_count }} Anggota</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <x-lucide-files class="w-3.5 h-3.5 text-muted-foreground" />
                            <span>{{ $ws->letters_count }} Surat</span>
                        </span>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-lg border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                    Belum ada workspace yang dibuat.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Manajemen Pengguna --}}
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-foreground">Manajemen Pengguna</h2>
                <p class="text-xs text-muted-foreground">Pantau, cari, nonaktifkan, atau atur ulang kata sandi pengguna.</p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <div class="w-full sm:w-64">
                    <x-ui.input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama atau email..."
                        class="text-xs"
                    />
                </div>
                <select
                    wire:model.live="statusFilter"
                    class="rounded-lg border border-input bg-background px-3 py-1.5 text-xs font-medium text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option value="all">Semua Status</option>
                    <option value="active">Aktif Saja</option>
                    <option value="suspended">Suspended Saja</option>
                    <option value="demo">Akun Demo</option>
                </select>
            </div>
        </div>

        <x-ui.card>
            <x-ui.card-content class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-left font-semibold text-muted-foreground">
                                <th class="px-4 py-3">Nama & Email</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Tipe Akses</th>
                                <th class="px-4 py-3">Workspace Terdaftar</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($users as $u)
                                <tr class="hover:bg-muted/20 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-foreground">{{ $u->name }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ $u->email }}</div>
                                    </td>
                                    <td class="px-3 py-3">
                                        @if ($u->isSuspended())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-600 dark:text-red-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                Suspended
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Aktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-1 flex-wrap">
                                            @if ($u->is_super_admin)
                                                <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary">
                                                    Super Admin
                                                </span>
                                            @endif
                                            @if ($u->is_demo)
                                                <span class="rounded bg-purple-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-purple-600 dark:text-purple-400">
                                                    Demo Read-Only
                                                </span>
                                            @endif
                                            @if (! $u->is_super_admin && ! $u->is_demo)
                                                <span class="text-muted-foreground text-[11px]">Pengguna Biasa</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($u->workspaces->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($u->workspaces as $w)
                                                    <span class="rounded-md border border-border bg-muted/40 px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                                        {{ $w->name }} ({{ $w->pivot->role }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted-foreground text-[11px] italic">Belum ada workspace</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Tombol Toggle Status (Suspend / Aktifkan) --}}
                                            @if ($u->id !== auth()->id() && $u->email !== config('suratinaja.super_admin.email'))
                                                <button
                                                    type="button"
                                                    @click="$store.confirmModal.show({
                                                        title: '{{ $u->isSuspended() ? 'Aktifkan Kembali Akun?' : 'Nonaktifkan (Suspend) Akun?' }}',
                                                        message: 'Yakin ingin {{ $u->isSuspended() ? 'mengaktifkan kembali' : 'menonaktifkan (suspend)' }} akun {{ addslashes($u->name) }}?',
                                                        confirmText: '{{ $u->isSuspended() ? 'Ya, Aktifkan' : 'Ya, Suspend' }}',
                                                        confirmVariant: '{{ $u->isSuspended() ? 'warning' : 'destructive' }}'
                                                    }).then(ok => ok && $wire.toggleUserStatus({{ $u->id }}))"
                                                    class="inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] font-medium transition-colors cursor-pointer {{ $u->isSuspended() ? 'text-emerald-600 hover:bg-emerald-500/10' : 'text-red-600 hover:bg-red-500/10' }}"
                                                    title="{{ $u->isSuspended() ? 'Aktifkan Akun' : 'Suspend Akun' }}"
                                                >
                                                    @if ($u->isSuspended())
                                                        <x-lucide-user-check class="w-3.5 h-3.5" />
                                                        <span>Aktifkan</span>
                                                    @else
                                                        <x-lucide-user-x class="w-3.5 h-3.5" />
                                                        <span>Suspend</span>
                                                    @endif
                                                </button>
                                            @endif

                                            {{-- Tombol Reset Password --}}
                                            <button
                                                type="button"
                                                wire:click="openResetPasswordModal({{ $u->id }})"
                                                class="inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] font-medium text-foreground hover:bg-muted transition-colors"
                                                title="Reset Kata Sandi"
                                            >
                                                <x-lucide-key-round class="w-3.5 h-3.5 text-muted-foreground" />
                                                <span>Reset Pass</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                        Tidak ada pengguna yang cocok dengan filter atau kata kunci.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="p-3 border-t border-border">
                        {{ $users->links() }}
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    {{-- Modal Tambah Pengguna Baru --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-sm font-bold text-foreground">Tambah Pengguna Baru</h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit="createUser" class="space-y-3 text-xs">
                    <div>
                        <x-ui.label for="newName">Nama Lengkap</x-ui.label>
                        <x-ui.input id="newName" wire:model="newName" class="mt-1" required placeholder="Contoh: Rian Anggara" />
                        <x-ui.form-error for="newName" />
                    </div>

                    <div>
                        <x-ui.label for="newEmail">Email</x-ui.label>
                        <x-ui.input id="newEmail" type="email" wire:model="newEmail" class="mt-1" required placeholder="rian@organisasi.id" />
                        <x-ui.form-error for="newEmail" />
                    </div>

                    <div>
                        <x-ui.label for="newPassword">Kata Sandi Awal (Min. 8 Karakter)</x-ui.label>
                        <x-ui.password-input id="newPassword" wire:model="newPassword" class="mt-1" required placeholder="Minimal 8 karakter" />
                        <x-ui.form-error for="newPassword" />
                    </div>

                    <div class="border-t border-border pt-3 space-y-3">
                        <div>
                            <x-ui.label for="newWorkspaceId">Assign ke Workspace (Opsional)</x-ui.label>
                            <select
                                id="newWorkspaceId"
                                wire:model="newWorkspaceId"
                                class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">-- Tanpa Workspace Awal --</option>
                                @foreach ($availableWorkspaces as $ws)
                                    <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                                @endforeach
                            </select>
                            <x-ui.form-error for="newWorkspaceId" />
                        </div>

                        @if ($newWorkspaceId)
                            <div>
                                <x-ui.label for="newWorkspaceRole">Peran dalam Workspace</x-ui.label>
                                <select
                                    id="newWorkspaceRole"
                                    wire:model="newWorkspaceRole"
                                    class="mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                                    @foreach ($roleOptions as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                <x-ui.form-error for="newWorkspaceRole" />
                            </div>
                        @endif

                        <label class="inline-flex items-center gap-2 pt-1">
                            <input type="checkbox" wire:model="newIsSuperAdmin" class="rounded border-input" />
                            <span class="font-medium text-foreground">Jadikan Super Admin Sistem</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showCreateModal', false)">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit">
                            Simpan Pengguna
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal Reset Password --}}
    @if ($showResetPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-xl border border-border bg-card p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-sm font-bold text-foreground">Reset Kata Sandi</h3>
                    <button type="button" wire:click="$set('showResetPasswordModal', false)" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-xs text-muted-foreground">
                    Atur ulang kata sandi baru untuk <strong class="text-foreground">{{ $resetUserName }}</strong>.
                </div>

                <form wire:submit="resetUserPassword" class="space-y-4 text-xs">
                    <div>
                        <x-ui.label for="resetPassword">Kata Sandi Baru</x-ui.label>
                        <x-ui.password-input id="resetPassword" wire:model="resetPassword" class="mt-1" required placeholder="Minimal 8 karakter" />
                        <x-ui.form-error for="resetPassword" />
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                        <x-ui.button type="button" variant="outline" wire:click="$set('showResetPasswordModal', false)">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit">
                            Simpan Kata Sandi
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
