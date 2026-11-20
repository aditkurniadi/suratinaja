<div class="space-y-6">
    @if (! $invitation || ! $invitation->isPending())
        <div class="rounded-xl border border-destructive/30 bg-destructive/10 p-6 text-center space-y-3">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-destructive/20 text-destructive">
                <x-lucide-alert-circle class="size-6" />
            </div>
            <h2 class="text-base font-bold text-foreground">Tautan Undangan Tidak Berlaku</h2>
            <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                Undangan ini mungkin sudah pernah digunakan, dibatalkan oleh admin, atau telah melewati batas masa aktif (7 hari).
            </p>
            <div class="pt-2">
                <x-ui.button as="a" href="{{ route('login') }}" variant="outline" size="sm">
                    Kembali ke Halaman Masuk
                </x-ui.button>
            </div>
        </div>
    @else
        {{-- Header Info Undangan --}}
        <div class="text-center space-y-2">
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20 shadow-2xs">
                <x-lucide-building-2 class="size-6" />
            </div>
            <h2 class="text-lg font-bold text-foreground">Undangan Bergabung</h2>
            <p class="text-xs text-muted-foreground">
                Anda diundang untuk bergabung ke workspace:
            </p>
            <div class="inline-block px-3 py-1.5 rounded-lg bg-card border border-border font-semibold text-sm text-foreground shadow-2xs">
                {{ $invitation->workspace->name }}
            </div>
            <div class="text-xs text-muted-foreground pt-1">
                Peran yang diberikan:
                <span class="font-semibold text-primary px-2 py-0.5 rounded bg-primary/10">
                    {{ $invitation->getRoleDisplayName() }}
                </span>
            </div>
        </div>

        <x-ui.form-error for="token" />

        {{-- Skenario A: Pengguna Sudah Login --}}
        @if ($isLoggedIn)
            <div class="rounded-xl border border-border bg-card p-4 space-y-3 text-xs">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <x-lucide-user-check class="size-4 text-emerald-500" />
                    <span>Anda saat ini masuk sebagai:</span>
                </div>
                <div class="p-2.5 rounded-lg bg-muted/40 font-medium text-foreground flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-sm">{{ auth()->user()->name }}</p>
                        <p class="text-muted-foreground text-[11px]">{{ auth()->user()->email }}</p>
                    </div>
                    <span class="text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold px-2 py-0.5 rounded border border-emerald-500/20">
                        Aktif
                    </span>
                </div>

                <x-ui.button wire:click="accept" class="w-full gap-2 mt-2">
                    <x-lucide-check class="size-4" />
                    <span>Terima Undangan & Masuk Workspace</span>
                </x-ui.button>
            </div>

        {{-- Skenario B: Pengguna Belum Login & Email Sudah Terdaftar --}}
        @elseif ($isRegisteredUser)
            <div class="rounded-xl border border-border bg-card p-5 space-y-4 text-center">
                <div class="mx-auto flex size-10 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                    <x-lucide-log-in class="size-5" />
                </div>
                <div class="space-y-1 text-xs">
                    <p class="font-semibold text-foreground text-sm">Akun Ditemukan</p>
                    <p class="text-muted-foreground">
                        Email <strong class="text-foreground">{{ $invitation->email }}</strong> sudah terdaftar di SuratinAja. Silakan masuk untuk menerima undangan ini.
                    </p>
                </div>

                <x-ui.button
                    as="a"
                    href="{{ route('login', ['redirect' => route('invitations.accept', ['token' => $token])]) }}"
                    class="w-full gap-2"
                >
                    <x-lucide-log-in class="size-4" />
                    <span>Masuk ke Akun Anda</span>
                </x-ui.button>
            </div>

        {{-- Skenario C: Pengguna Baru (Registrasi Cepat Khusus Undangan) --}}
        @else
            <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                <div class="text-center space-y-1">
                    <h3 class="text-sm font-semibold text-foreground">Lengkapi Pendaftaran Anda</h3>
                    <p class="text-xs text-muted-foreground">
                        Isi nama dan kata sandi untuk membuat akun dan langsung bergabung.
                    </p>
                </div>

                <form wire:submit="registerAndAccept" class="space-y-3.5 text-xs">
                    <div>
                        <x-ui.label for="invite-email">Alamat Email (Terkunci)</x-ui.label>
                        <div class="relative mt-1">
                            <x-ui.input
                                id="invite-email"
                                type="email"
                                value="{{ $invitation->email }}"
                                disabled
                                class="bg-muted/60 pr-8 text-muted-foreground cursor-not-allowed"
                            />
                            <x-lucide-lock class="size-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        </div>
                    </div>

                    <div>
                        <x-ui.label for="user-name">Nama Lengkap</x-ui.label>
                        <x-ui.input
                            id="user-name"
                            type="text"
                            wire:model="name"
                            placeholder="Nama Lengkap Anda"
                            class="mt-1"
                            required
                        />
                        <x-ui.form-error for="name" />
                    </div>

                    <div>
                        <x-ui.label for="user-password">Kata Sandi Baru</x-ui.label>
                        <x-ui.input
                            id="user-password"
                            type="password"
                            wire:model="password"
                            placeholder="Minimal 8 karakter"
                            class="mt-1"
                            required
                        />
                        <x-ui.form-error for="password" />
                    </div>

                    <div>
                        <x-ui.label for="user-password-confirmation">Konfirmasi Kata Sandi</x-ui.label>
                        <x-ui.input
                            id="user-password-confirmation"
                            type="password"
                            wire:model="password_confirmation"
                            placeholder="Ulangi kata sandi"
                            class="mt-1"
                            required
                        />
                    </div>

                    <div class="pt-2">
                        <x-ui.button type="submit" class="w-full gap-2">
                            <x-lucide-user-plus class="size-4" />
                            <span>Daftar &amp; Terima Undangan</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
