<x-app-layout>
    <x-slot name="header">Riding</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8" id="riding-app">
        @if ($motorcycles->isEmpty())
            <x-ui.card class="text-center py-12">
                <x-icon.motorcycle class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                <p class="text-muted-fg mb-4">Belum ada motor. Tambahkan dulu sebelum mulai riding.</p>
                <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                    <x-icon.plus class="w-4 h-4"/> Tambah Motor
                </x-ui.button>
            </x-ui.card>
        @else
            <x-ui.card class="space-y-6">
                <label class="block space-y-1.5">
                    <span class="text-sm font-medium text-foreground">Pilih motor</span>
                    <select id="motor-select" class="w-full rounded-token border border-border bg-surface px-3.5 py-2.5 text-foreground focus:border-primary focus:ring-2 focus:ring-primary/30 transition">
                        @foreach ($motorcycles as $m)
                            <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }} ({{ number_format($m->current_odometer_km) }} km)</option>
                        @endforeach
                    </select>
                </label>

                <div id="ride-map" class="rounded-token overflow-hidden border border-border" style="height: 40vh"></div>

                <div class="text-center py-10 rounded-token bg-muted">
                    <p class="text-6xl font-heading font-bold text-primary tabular-nums"><span id="distance">0.00</span></p>
                    <p class="text-sm text-muted-fg mt-1">km</p>
                    <p class="text-muted-fg mt-3 tabular-nums"><span id="duration">00:00</span></p>
                </div>

                <button id="start-btn" class="w-full inline-flex items-center justify-center gap-2 py-3.5 bg-primary text-white rounded-token text-lg font-heading font-semibold hover:bg-primary-hover transition cursor-pointer">
                    <x-icon.play class="w-5 h-5"/> Mulai Perjalanan
                </button>
                <button id="stop-btn" class="w-full hidden inline-flex items-center justify-center gap-2 py-3.5 bg-accent text-white rounded-token text-lg font-heading font-semibold hover:bg-accent-hover transition cursor-pointer">
                    <x-icon.stop class="w-5 h-5"/> Selesai Perjalanan
                </button>
                <p id="gps-msg" class="text-sm text-accent text-center"></p>
            </x-ui.card>
        @endif
    </div>
    @csrf
    @if ($motorcycles->isNotEmpty())
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map-common.js') }}"></script>
        <script src="{{ asset('js/trip-recorder.js') }}"></script>
    @endif
</x-app-layout>
