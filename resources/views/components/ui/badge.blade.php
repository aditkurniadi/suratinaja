@props(['variant' => 'default'])

@php
    $variants = [
        'default' => 'border-transparent bg-primary text-primary-foreground',
        'secondary' => 'border-transparent bg-secondary text-secondary-foreground',
        'outline' => 'border-border text-foreground bg-transparent',
        'destructive' => 'border-transparent bg-destructive/15 text-destructive border-destructive/30',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium transition-colors '.($variants[$variant] ?? $variants['default']),
]) }}>
    {{ $slot }}
</span>
