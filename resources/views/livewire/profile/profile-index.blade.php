<div class="space-y-6 max-w-3xl">
    {{-- Notifikasi Error Global --}}
    @if ($errorMessage)
        <x-ui.alert variant="destructive">
            <x-lucide-alert-triangle class="size-4 shrink-0" />
            <div class="text-xs font-medium">{{ $errorMessage }}</div>
        </x-ui.alert>
    @endif

    {{-- KARTU 1: Informasi Profil --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-lg bg-primary/10 text-primary">
                    <x-lucide-user class="size-5" />
                </div>
                <div>
                    <x-ui.card-title>Informasi Profil</x-ui.card-title>
                    <x-ui.card-description>Perbarui nama lengkap dan alamat email akun Anda.</x-ui.card-description>
                </div>
            </div>
        </x-ui.card-header>
        <x-ui.card-content class="pt-5">
            @if ($profileSuccessMessage)
                <div class="mb-4 p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>{{ $profileSuccessMessage }}</span>
                </div>
            @endif

            <form wire:submit="updateProfile" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="profile-name">Nama Lengkap</x-ui.label>
                        <x-ui.input id="profile-name" wire:model="name" class="mt-1.5" required placeholder="Nama Anda" />
                        <x-ui.form-error for="name" />
                    </div>
                    <div>
                        <x-ui.label for="profile-email">Alamat Email</x-ui.label>
                        <x-ui.input id="profile-email" type="email" wire:model="email" class="mt-1.5" required placeholder="email@organisasi.id" />
                        <x-ui.form-error for="email" />
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" size="sm" class="gap-1.5">
                        <x-lucide-save class="size-4" />
                        <span>Simpan Profil</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>

    {{-- KARTU 2: Ubah Kata Sandi --}}
    <x-ui.card>
        <x-ui.card-header class="border-b border-border pb-4">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <x-lucide-key-round class="size-5" />
                </div>
                <div>
                    <x-ui.card-title>Ubah Kata Sandi</x-ui.card-title>
                    <x-ui.card-description>Gunakan kata sandi acak yang panjang untuk menjaga keamanan akun Anda.</x-ui.card-description>
                </div>
            </div>
        </x-ui.card-header>
        <x-ui.card-content class="pt-5">
            @if ($passwordSuccessMessage)
                <div class="mb-4 p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-xs font-medium flex items-center gap-2">
                    <x-lucide-check-circle-2 class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <span>{{ $passwordSuccessMessage }}</span>
                </div>
            @endif

            <form wire:submit="updatePassword" class="space-y-4">
                <div>
                    <x-ui.label for="current-password">Kata Sandi Saat Ini</x-ui.label>
                    <div class="mt-1.5 max-w-md">
                        <x-ui.password-input
                            id="current-password"
                            wire:model="current_password"
                            placeholder="Ketik kata sandi saat ini"
                            required
                        />
                    </div>
                    <x-ui.form-error for="current_password" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="new-password">Kata Sandi Baru</x-ui.label>
                        <div class="mt-1.5">
                            <x-ui.password-input
                                id="new-password"
                                wire:model="password"
                                placeholder="Minimal 8 karakter"
                                required
                            />
                        </div>
                        <x-ui.form-error for="password" />
                    </div>
                    <div>
                        <x-ui.label for="new-password-confirmation">Konfirmasi Kata Sandi Baru</x-ui.label>
                        <div class="mt-1.5">
                            <x-ui.password-input
                                id="new-password-confirmation"
                                wire:model="password_confirmation"
                                placeholder="Ulangi kata sandi baru"
                                required
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <x-ui.button type="submit" size="sm" class="gap-1.5">
                        <x-lucide-lock class="size-4" />
                        <span>Perbarui Kata Sandi</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>

    {{-- KARTU 3: Zona Bahaya (Hapus Akun) --}}
    <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-5 space-y-4">
        <div class="flex items-center gap-2.5">
            <div class="p-2 rounded-lg bg-destructive/10 text-destructive">
                <x-lucide-alert-triangle class="size-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-destructive">Zona Bahaya Akun</h3>
                <p class="text-xs text-muted-foreground">Tindakan ini permanen dan akan menghapus identitas akun Anda.</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-2 border-t border-destructive/20">
            <div>
                <p class="text-sm font-semibold text-foreground">Hapus Akun Saya</p>
                <p class="text-xs text-muted-foreground">
                    Menghapus profil Anda dan mencabut seluruh hak akses Anda di setiap workspace secara permanen.
                </p>
            </div>
            <x-ui.button
                type="button"
                variant="destructive"
                size="sm"
                wire:click="openDeleteModal"
                class="shrink-0 gap-1.5"
            >
                <x-lucide-user-x class="size-4" />
                <span>Hapus Akun</span>
            </x-ui.button>
        </div>
    </div>

    {{-- MODAL KONFIRMASI HAPUS AKUN --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-border bg-background shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-3.5 bg-destructive/10">
                    <div class="flex items-center gap-2 text-destructive">
                        <x-lucide-alert-triangle class="size-5 shrink-0" />
                        <h3 class="text-base font-bold">Konfirmasi Hapus Akun</h3>
                    </div>
                    <button type="button" wire:click="$set('showDeleteModal', false)" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <x-lucide-x class="size-4" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <p class="text-muted-foreground">
                        Apakah Anda yakin ingin menghapus akun Anda secara permanen? Seluruh keanggotaan Anda di workspace akan dicabut.
                    </p>

                    <div class="space-y-2">
                        <x-ui.label for="delete-password">Masukkan Kata Sandi Anda untuk Mengonfirmasi:</x-ui.label>
                        <x-ui.password-input
                            id="delete-password"
                            wire:model="delete_password"
                            placeholder="Kata sandi akun Anda"
                            autofocus
                        />
                        <x-ui.form-error for="delete_password" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3 bg-muted/20">
                    <x-ui.button type="button" variant="outline" wire:click="$set('showDeleteModal', false)">
                        Batal
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="destructive"
                        wire:click="deleteAccount"
                        wire:loading.attr="disabled"
                        class="gap-1.5"
                    >
                        <x-lucide-user-x class="size-4" wire:loading.remove wire:target="deleteAccount" />
                        <x-lucide-loader-2 class="size-4 animate-spin" wire:loading wire:target="deleteAccount" />
                        <span>Hapus Akun Saya</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
