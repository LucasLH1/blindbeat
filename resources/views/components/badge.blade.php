@props(['variant' => 'muted'])

@php
$classes = match ($variant) {
    'primary' => 'bg-primary-light text-primary',
    'success' => 'bg-green-100 text-green-700',
    'error'   => 'bg-red-100 text-red-600',
    'warning' => 'bg-amber-100 text-amber-700',
    default   => 'bg-zinc-100 text-zinc-600',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold $classes"]) }}>
    {{ $slot }}
</span>
