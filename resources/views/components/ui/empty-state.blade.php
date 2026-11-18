@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12 px-6 text-center']) }}>
    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
        <x-dynamic-component :component="'lucide-' . $icon" class="size-5" />
    </div>
    <h3 class="text-sm font-semibold text-foreground">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-xs text-muted-foreground">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
