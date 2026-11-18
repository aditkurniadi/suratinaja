@props(['status'])

@php
    use App\Enums\BulkJobStatus;
    use App\Enums\LetterStatus;
    use App\Enums\NumberStatus;

    $enum = $status;
    if (is_string($status)) {
        $enum = LetterStatus::tryFrom($status)
            ?? NumberStatus::tryFrom($status)
            ?? BulkJobStatus::tryFrom($status);
    }

    $label = $enum ? $enum->label() : ucfirst((string) $status);
    $dot = $enum ? $enum->dot() : 'bg-zinc-400';
    $icon = $enum ? $enum->icon() : 'circle';
    $strikethrough = $enum === LetterStatus::Dibatalkan || $enum === NumberStatus::Hangus;
@endphp

<span
    aria-label="Status: {{ $label }}"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1.5 rounded-md border border-border bg-background/60 px-2.5 py-0.5 text-xs font-medium text-foreground',
    ]) }}
>
    <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
    <x-dynamic-component :component="'lucide-' . $icon" class="size-3.5 text-muted-foreground" />
    <span @class(['line-through text-muted-foreground' => $strikethrough])>{{ $label }}</span>
</span>
