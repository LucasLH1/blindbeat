@props([
    'variant' => 'primary',
    'size'    => 'md',
    'href'    => null,
    'type'    => 'button',
])

@php
$sizeClasses = match ($size) {
    'sm'    => 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
    'lg'    => 'px-7 py-3.5 text-base rounded-2xl gap-2.5',
    default => 'px-5 py-2.5 text-sm rounded-xl gap-2',
};

$variantClasses = match ($variant) {
    'secondary' => 'bg-white border border-border text-ink hover:bg-zinc-50 focus:ring-primary/20',
    'ghost'     => 'bg-transparent text-muted hover:bg-zinc-50 focus:ring-primary/20',
    'danger'    => 'bg-error text-white hover:bg-red-600 focus:ring-error/30',
    default     => 'bg-primary text-white hover:bg-primary-dark focus:ring-primary/30 shadow-[0_4px_15px_rgba(124,92,191,0.4)] hover:shadow-[0_6px_20px_rgba(124,92,191,0.55)]',
};

$base = 'inline-flex items-center justify-center font-semibold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none whitespace-nowrap';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $sizeClasses $variantClasses"]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$base $sizeClasses $variantClasses"]) }}>
        {{ $slot }}
    </button>
@endif
