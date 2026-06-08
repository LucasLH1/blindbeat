@props(['type' => 'text'])

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'w-full rounded-xl border border-border bg-white px-4 py-2.5 text-sm text-ink placeholder:text-muted transition focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary']) }}
>
