@props(['name', 'label', 'type' => 'text', 'value' => '', 'placeholder' => '', 'helper' => null, 'required' => false])
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-foreground">
        {{ $label }} @if ($required)<span class="text-accent">*</span>@endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'w-full rounded-token border border-border bg-surface px-3.5 py-2.5 text-foreground placeholder:text-muted-fg/60 focus:border-primary focus:ring-2 focus:ring-primary/30 transition']) }}>
    @if ($helper)<p class="text-xs text-muted-fg">{{ $helper }}</p>@endif
    @error($name)<p class="text-xs text-accent">{{ $message }}</p>@enderror
</div>
