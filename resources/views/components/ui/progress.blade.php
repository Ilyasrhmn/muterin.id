@props(['percent' => 0, 'color' => 'green'])
@php $bg = ['green' => 'bg-status-green', 'yellow' => 'bg-status-yellow', 'red' => 'bg-status-red'][$color]; @endphp
<div class="w-full bg-muted rounded-full h-2 overflow-hidden" role="progressbar" aria-valuenow="{{ (int) min(100, $percent) }}" aria-valuemin="0" aria-valuemax="100">
    <div class="{{ $bg }} h-2 rounded-full transition-[width] duration-700 ease-out" style="width: {{ min(100, $percent) }}%"></div>
</div>
