@props(['hover' => false])
<div {{ $attributes->merge(['class' => 'bg-surface border border-border rounded-token shadow-soft p-5 '.($hover ? 'transition duration-200 hover:shadow-lift hover:-translate-y-0.5' : '')]) }}>
    {{ $slot }}
</div>
