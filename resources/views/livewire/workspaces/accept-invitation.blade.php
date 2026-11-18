<div class="space-y-4">
    <div>
        <h2 class="text-base font-semibold">Undangan Bergabung ke Workspace</h2>
        <p class="text-xs text-muted-foreground mt-1">
            Anda diundang ke workspace <strong class="text-foreground">{{ $invitation->workspace->name }}</strong>
            sebagai <strong class="text-foreground">{{ $invitation->role->label() }}</strong>.
        </p>
    </div>

    <x-ui.form-error for="token" />

    @if ($invitation->isPending())
        <x-ui.button wire:click="accept" class="w-full">
            Terima Undangan &amp; Masuk Workspace
        </x-ui.button>
    @else
        <x-ui.alert variant="destructive" title="Undangan tidak aktif">
            Undangan ini sudah digunakan atau telah kedaluwarsa.
        </x-ui.alert>
    @endif
</div>
