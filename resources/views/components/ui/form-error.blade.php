@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'text-xs font-medium text-destructive mt-1']) }}>
        {{ $message }}
    </p>
@enderror
