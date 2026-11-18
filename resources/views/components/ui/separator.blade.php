@props(['orientation' => 'horizontal'])

<div
    role="separator"
    {{ $attributes->merge([
        'class' => $orientation === 'vertical'
            ? 'shrink-0 bg-border w-[1px] h-4'
            : 'shrink-0 bg-border h-[1px] w-full',
    ]) }}
></div>
