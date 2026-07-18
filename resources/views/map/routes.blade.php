<x-app-layout>
    <x-slot name="header">Peta Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        {{-- Hero --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-r from-secondary to-primary shadow-lift p-6 sm:p-8">
            <div class="absolute inset-0 hero-grid opacity-60"></div>
            <div class="relative flex items-center justify-between gap-4">
                <div class="space-y-2">
                    <span class="inline-flex items-center gap-2 bg-white/15 text-white border border-white/20 font-bold uppercase tracking-[0.15em] text-[10px] px-3 py-1 rounded-full">
                        <x-icon.route class="w-3 h-3"/> {{ $trips->count() }} perjalanan
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Peta Rute Perjalanan</h1>
                    <p class="text-white/80 text-sm max-w-lg">Semua jalur GPS dari perjalanan yang pernah kamu rekam, tergambar di satu peta.</p>
                </div>
                <x-icon.route class="w-24 h-24 text-white/10 hidden sm:block"/>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-[24px] overflow-hidden border border-border shadow-soft">
                <div id="map" style="height: 60vh"></div>
            </div>

            <div class="bg-surface border border-border rounded-[24px] shadow-soft overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground">Daftar Perjalanan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Jalur ditampilkan biru di peta</p>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 52vh">
                    @forelse ($trips as $t)
                        <div class="p-4 rounded-2xl hover:bg-muted/60 transition">
                            <div class="flex items-center justify-between">
                                <p class="font-bold text-sm text-foreground">{{ $t->motorcycle->nickname }}</p>
                                <span class="text-sm font-bold text-primary tabular-nums">{{ $t->distance_km }} km</span>
                            </div>
                            <p class="text-[11px] text-muted-fg mt-1 tabular-nums">{{ $t->ended_at?->format('d M Y H:i') }} &middot; {{ gmdate('H:i:s', $t->duration_seconds) }}</p>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <x-icon.route class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                            <p class="text-sm text-muted-fg">Belum ada perjalanan terekam.</p>
                            <a href="{{ route('riding') }}" class="text-sm text-primary font-semibold hover:underline">Mulai riding &rarr;</a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}"></script>
    <script src="{{ asset('js/map-routes.js') }}"></script>
</x-app-layout>
