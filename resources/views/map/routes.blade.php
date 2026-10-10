<x-app-layout>
    <x-slot name="header">Peta Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="mtn-page mtn-map-page mtn-map-routes space-y-6">
        <header class="mtn-map-header"><div><p class="mtn-map-eyebrow">Riwayat perjalanan</p><h1>Peta Rute Perjalanan</h1><p class="mtn-map-description">Semua jalur GPS dari perjalanan yang pernah kamu rekam, tergambar di satu peta.</p></div><span class="mtn-map-count">{{ $trips->count() }} perjalanan</span></header>

        <div class="mtn-map-workspace">
            <div class="mtn-map-surface mtn-map-frame">
                <div id="map" style="height: 60vh"></div>
            </div>

            <div class="mtn-panel mtn-map-controls mtn-map-list-panel flex flex-col">
                <div class="mtn-context mtn-map-list-heading">
                    <h3 class="mtn-section-title">Daftar Perjalanan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Jalur ditampilkan di peta</p>
                </div>
                <div class="p-3 space-y-1 mtn-scroll-list">
                    @forelse ($trips as $t)
                        <div class="mtn-record p-3 hover:bg-muted/60 transition">
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
    @include('partials.map-provider')
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-routes.js') }}?v={{ filemtime(public_path('js/map-routes.js')) }}"></script>
</x-app-layout>
