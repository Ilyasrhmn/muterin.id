@props(['label', 'value', 'suffix' => ''])
<x-ui.card {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
    @isset($icon)
        <div class="shrink-0 w-11 h-11 rounded-token bg-primary/10 text-primary flex items-center justify-center">
            {{ $icon }}
        </div>
    @endisset
    <div>
        <p class="text-sm text-muted-fg">{{ $label }}</p>
        <p class="text-2xl font-heading font-bold text-foreground">
            <span data-countup="{{ $value }}">0</span>{{ $suffix }}
        </p>
    </div>
</x-ui.card>
