<x-app-layout>
    <x-slot name="header">Peta Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $trips->count() }} perjalanan" title="Peta Rute Perjalanan"
                    subtitle="Semua jalur GPS dari perjalanan yang pernah kamu rekam, tergambar di satu peta." />

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-2xl overflow-hidden border border-border">
                <div id="map" style="height: 60vh"></div>
            </div>

            <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Daftar Perjalanan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Jalur ditampilkan di peta</p>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 52vh">
                    @forelse ($trips as $t)
                        <div class="p-3 rounded-xl hover:bg-muted/60 transition">
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
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-routes.js') }}?v={{ filemtime(public_path('js/map-routes.js')) }}"></script>
</x-app-layout>
