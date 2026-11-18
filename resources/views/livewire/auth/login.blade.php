<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold">Masuk ke akun Anda</h2>
        <p class="text-xs text-muted-foreground mt-0.5">Masukkan kredensial untuk mengakses workspace persuratan.</p>
    </div>

    <form wire:submit="authenticate" class="space-y-4">
        <div>
            <x-ui.label for="email">Email</x-ui.label>
            <x-ui.input id="email" type="email" wire:model="email" class="mt-1.5" required autofocus autocomplete="username" placeholder="sekretaris@himatris.org" />
            <x-ui.form-error for="email" />
        </div>

        <div>
            <x-ui.label for="password">Kata Sandi</x-ui.label>
            <x-ui.input id="password" type="password" wire:model="password" class="mt-1.5" required autocomplete="current-password" />
            <x-ui.form-error for="password" />
        </div>

        <div class="flex items-center justify-between text-xs">
            <label class="inline-flex items-center gap-2 text-muted-foreground">
                <input type="checkbox" wire:model="remember" class="rounded border-input" />
                <span>Ingat saya</span>
            </label>
            <a href="{{ route('register') }}" class="font-medium text-foreground hover:underline">
                Belum punya akun? Daftar
            </a>
        </div>

        <x-ui.button type="submit" class="w-full">
            <span>Masuk</span>
        </x-ui.button>
    </form>
</div>
