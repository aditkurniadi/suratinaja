<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold">Daftar Akun Baru</h2>
        <p class="text-xs text-muted-foreground mt-0.5">Buat akun dan workspace organisasi Anda.</p>
    </div>

    <form wire:submit="register" class="space-y-3.5">
        <div>
            <x-ui.label for="name">Nama Lengkap</x-ui.label>
            <x-ui.input id="name" wire:model="name" class="mt-1" required autofocus />
            <x-ui.form-error for="name" />
        </div>

        <div>
            <x-ui.label for="email">Email</x-ui.label>
            <x-ui.input id="email" type="email" wire:model="email" class="mt-1" required />
            <x-ui.form-error for="email" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <x-ui.label for="password">Kata Sandi</x-ui.label>
                <x-ui.password-input id="password" wire:model="password" class="mt-1" required placeholder="Minimal 8 karakter" />
                <x-ui.form-error for="password" />
            </div>
            <div>
                <x-ui.label for="password_confirmation">Konfirmasi Sandi</x-ui.label>
                <x-ui.password-input id="password_confirmation" wire:model="password_confirmation" class="mt-1" required placeholder="Ulangi kata sandi" />
            </div>
        </div>

        <x-ui.separator class="my-2" />

        <div>
            <x-ui.label for="workspace_name">Nama Workspace Organisasi (Opsional)</x-ui.label>
            <x-ui.input id="workspace_name" wire:model="workspace_name" class="mt-1" placeholder="Mis. HIMATRIS" />
            <x-ui.form-error for="workspace_name" />
        </div>

        <div>
            <x-ui.label for="workspace_code">Kode Penomoran ({kode})</x-ui.label>
            <x-ui.input id="workspace_code" wire:model="workspace_code" class="mt-1 font-mono" placeholder="Mis. HIMATRIS" />
        </div>

        <x-ui.button type="submit" class="w-full">
            Daftar &amp; Masuk
        </x-ui.button>

        <div class="text-center text-xs text-muted-foreground">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-medium text-foreground hover:underline">Masuk di sini</a>
        </div>
    </form>
</div>
