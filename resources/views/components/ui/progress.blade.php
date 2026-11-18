@props(['value' => 0])

@php
    $pct = max(0, min(100, (int) $value));
@endphp

<div
    role="progressbar"
    aria-valuemin="0"
    aria-valuemax="100"
    aria-valuenow="{{ $pct }}"
    {{ $attributes->merge(['class' => 'relative h-2 w-full overflow-hidden rounded-full bg-secondary']) }}
>
    <div
        class="h-full bg-primary transition-all duration-300"
        style="width: {{ $pct }}%"
    ></div>
</div>
