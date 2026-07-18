@props(['variant' => 'primary', 'size' => 'md', 'href' => null])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-token transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:opacity-50 disabled:pointer-events-none cursor-pointer';
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover shadow-soft',
        'accent'  => 'bg-accent text-white hover:bg-accent-hover shadow-soft',
        'outline' => 'border border-border bg-surface text-foreground hover:bg-muted',
        'ghost'   => 'text-primary hover:bg-primary/10',
    ][$variant];
    $sizes = ['sm' => 'text-sm px-3 py-2', 'md' => 'text-sm px-4 py-2.5', 'lg' => 'text-base px-6 py-3'][$size];
    $classes = "$base $variants $sizes";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
