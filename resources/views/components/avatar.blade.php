@props(['name' => '?', 'size' => 'md'])

@php
$initials = collect(explode(' ', trim($name)))
    ->filter()
    ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
    ->take(2)
    ->implode('');

$sizeClasses = match ($size) {
    'sm'    => 'w-7 h-7 text-xs',
    'lg'    => 'w-12 h-12 text-lg',
    default => 'w-9 h-9 text-sm',
};
@endphp

<div {{ $attributes->merge(['class' => "shrink-0 flex items-center justify-center rounded-full bg-primary-light text-primary font-bold $sizeClasses"]) }}>
    {{ $initials ?: '?' }}
</div>
