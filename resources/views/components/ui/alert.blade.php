@props([
    'variant' => 'default',
    'title' => null,
])

@php
    $styles = $variant === 'destructive'
        ? 'border-destructive/50 text-destructive bg-destructive/5'
        : 'border-border text-foreground bg-card';
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'relative w-full rounded-lg border p-4 text-sm '.$styles]) }}>
    <div class="flex items-start gap-3">
        @if ($variant === 'destructive')
            <x-lucide-circle-alert class="size-4 shrink-0 mt-0.5" />
        @else
            <x-lucide-info class="size-4 shrink-0 mt-0.5 text-muted-foreground" />
        @endif
        <div class="space-y-1 flex-1">
            @if ($title)
                <h5 class="font-medium leading-none tracking-tight">{{ $title }}</h5>
            @endif
            <div class="text-sm leading-relaxed opacity-90">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
