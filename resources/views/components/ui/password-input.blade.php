@props(['disabled' => false])

<div x-data="{ showPassword: false }" class="relative w-full">
    <input
        :type="showPassword ? 'text' : 'password'"
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge([
            'class' => 'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 pr-10 text-sm shadow-2xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
        ]) }}
    />
    <button
        type="button"
        @click="showPassword = !showPassword"
        tabindex="-1"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-1 rounded-md transition-colors cursor-pointer select-none"
        :title="showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'"
        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'"
    >
        <x-lucide-eye x-show="!showPassword" class="size-4" />
        <x-lucide-eye-off x-show="showPassword" class="size-4" style="display: none;" />
    </button>
</div>
