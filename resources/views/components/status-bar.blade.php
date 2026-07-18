@props(['item', 'status'])
@php
    $bg = ['green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', 'red' => 'bg-red-500'][$status['color']];
@endphp
<div class="space-y-1" data-item-id="{{ $item->id }}" data-item-name="{{ $item->name }}" data-color="{{ $status['color'] }}">
    <div class="flex justify-between text-sm">
        <span>{{ $item->name }}</span>
        <span>{{ $status['used'] }} / {{ $item->interval_km }} km ({{ $status['percent'] }}%)</span>
    </div>
    <div class="w-full bg-gray-200 rounded h-2">
        <div class="{{ $bg }} h-2 rounded" style="width: {{ min(100, $status['percent']) }}%"></div>
    </div>
</div>
