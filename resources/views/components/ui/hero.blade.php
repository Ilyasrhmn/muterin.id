@props(['badge' => null, 'title', 'subtitle' => null])
<div class="mtn-page-heading">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-3 min-w-0 flex-1">
            @if ($badge)
                <p class="text-sm text-muted-fg font-medium">
                    {{ $badge }}
                </p>
            @endif
            <h1 class="text-3xl sm:text-4xl font-extrabold text-foreground tracking-tight">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-muted-fg text-sm leading-relaxed">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($side)
            <div class="flex flex-wrap gap-2">{{ $side }}</div>
        @endisset
    </div>
</div>
