@props(['variant' => 'neutral'])
@php
    $map = [
        'green'   => 'bg-status-green/15 text-status-green',
        'yellow'  => 'bg-status-yellow/15 text-[#B45309]',
        'red'     => 'bg-status-red/15 text-status-red',
        'neutral' => 'bg-muted text-muted-fg',
    ][$variant];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold $map"]) }}>
    {{ $slot }}
</span>
