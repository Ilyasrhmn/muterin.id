@props(['variant' => 'primary', 'size' => 'md', 'href' => null])
@php
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 font-heading font-semibold rounded-token transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:opacity-50 disabled:pointer-events-none cursor-pointer';
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover',
        'accent'  => 'bg-accent text-white hover:bg-accent-hover',
        'outline' => 'border border-border bg-surface text-foreground hover:bg-muted',
        'ghost'   => 'text-primary hover:bg-primary-soft',
        'white'   => 'bg-white text-primary hover:bg-muted border border-border',
        'whiteOutline' => 'bg-surface text-primary border border-border hover:bg-muted',
    ][$variant];
    $sizes = ['sm' => 'text-sm px-3 py-2', 'md' => 'text-sm px-4 py-2.5', 'lg' => 'text-base px-6 py-3'][$size];
    $classes = "$base $variants $sizes";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
