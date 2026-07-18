@props(['hover' => false])
<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-2xl p-5 '.($hover ? 'transition duration-200 hover:shadow-soft' : '')]) }}>
    {{ $slot }}
</div>
