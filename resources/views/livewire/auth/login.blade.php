<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold">Masuk ke akun Anda</h2>
        <p class="text-xs text-muted-foreground mt-0.5">Masukkan kredensial untuk mengakses workspace persuratan.</p>
    </div>

    @if (session('info'))
        <div class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
            {{ session('info') }}
        </div>
    @endif

    @if (session('status'))
        <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="authenticate" class="space-y-4">
        <div>
            <x-ui.label for="email">Email</x-ui.label>
            <x-ui.input id="email" type="email" wire:model="email" class="mt-1.5" required autofocus autocomplete="username" placeholder="sekretaris@himatris.org" />
            <x-ui.form-error for="email" />
        </div>

        <div>
            <x-ui.label for="password">Kata Sandi</x-ui.label>
            <x-ui.password-input id="password" wire:model="password" class="mt-1.5" required autocomplete="current-password" placeholder="••••••••" />
            <x-ui.form-error for="password" />
        </div>

        <div class="flex items-center justify-between text-xs">
            <label class="inline-flex items-center gap-2 text-muted-foreground">
                <input type="checkbox" wire:model="remember" class="rounded border-input" />
                <span>Ingat saya</span>
            </label>
            @if (config('suratinaja.allow_public_registration'))
                <a href="{{ route('register') }}" class="font-medium text-foreground hover:underline">
                    Belum punya akun? Daftar
                </a>
            @endif
        </div>

        <x-ui.button type="submit" class="w-full">
            <span>Masuk</span>
        </x-ui.button>
    </form>

    @if (config('suratinaja.demo.enabled'))
        <div class="relative my-4">
            <div class="absolute inset-0 flex items-center">
                <span class="w-full border-t border-border"></span>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">atau eksplorasi</span>
            </div>
        </div>

        <button
            type="button"
            wire:click="loginDemo"
            wire:loading.attr="disabled"
            class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-muted/60 px-4 py-2.5 text-xs font-semibold text-foreground hover:bg-muted hover:border-foreground/30 transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
        >
            <x-lucide-sparkles class="w-4 h-4 text-amber-500 shrink-0" />
            <span>Masuk Akun Demo (Hanya Lihat)</span>
        </button>
    @endif
</div>
