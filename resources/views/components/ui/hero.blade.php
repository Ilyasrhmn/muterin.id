@props(['badge' => null, 'title', 'subtitle' => null])
<div class="relative overflow-hidden rounded-[24px] bg-hero p-6 sm:p-8">
    <div class="absolute inset-0 hero-grid"></div>
    <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-3 max-w-xl">
            @if ($badge)
                <span class="inline-flex items-center gap-2 bg-white/10 text-teal-50 border border-white/15 font-bold uppercase tracking-[0.15em] text-[10px] px-3 py-1.5 rounded-full">
                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ $badge }}
                </span>
            @endif
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-teal-100/75 text-sm leading-relaxed">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($side)
            <div class="shrink-0">{{ $side }}</div>
        @endisset
    </div>
</div>
