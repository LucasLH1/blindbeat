@props(['padding' => 'md'])

@php
$p = match ($padding) {
    'sm'    => 'p-4',
    'lg'    => 'p-8',
    'none'  => 'p-0',
    default => 'p-6',
};
@endphp

<div {{ $attributes->merge(['class' => "bg-white rounded-2xl border border-border shadow-card $p"]) }}>
    {{ $slot }}
</div>
