<x-app-layout>
    <x-slot name="header">Rencana Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $plans->count() }} rencana" title="Rencanakan Rute"
                    subtitle="Cari tempat atau klik di peta, pilih titik awal & tujuan — rute jalan otomatis dihitung." />

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="relative rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 62vh"></div>

                    <div class="absolute top-3 left-3 right-3 z-[1000] max-w-md">
                        <div class="bg-surface rounded-xl shadow-lift border border-border flex items-center gap-2 px-3 py-2">
                            <x-icon.search class="w-4 h-4 text-muted-fg shrink-0"/>
                            <input id="search-input" type="text" placeholder="Cari tempat (mis. Bintaro Plaza)…"
                                   class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-fg" autocomplete="off">
                            <button id="search-btn" type="button" class="text-sm font-semibold text-primary hover:underline shrink-0">Cari</button>
                        </div>
                        <div id="search-results" class="mt-1 bg-surface rounded-xl shadow-lift border border-border overflow-hidden hidden"></div>
                    </div>

                    <div id="info-panel" class="absolute bottom-3 left-3 right-3 z-[1000] max-w-md bg-surface rounded-xl shadow-lift border border-border p-4 hidden">
                        <p id="info-label" class="font-heading font-semibold text-sm text-foreground"></p>
                        <p id="info-coords" class="text-[11px] text-muted-fg mt-0.5 tabular-nums"></p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button id="btn-set-start" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-status-green/15 text-status-green hover:bg-status-green/25 transition">Jadikan Titik Awal</button>
                            <button id="btn-set-end" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-accent/15 text-accent hover:bg-accent/25 transition">Jadikan Titik Tujuan</button>
                            <button id="btn-add-via" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-amber-500/15 text-amber-600 hover:bg-amber-500/25 transition hidden">Tambah Titik Singgah</button>
                        </div>
                    </div>

                    <div id="route-panel" class="absolute bottom-3 left-3 right-3 z-[1000] max-w-md bg-surface rounded-xl shadow-lift border border-border p-4 hidden">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-status-green shrink-0"></span>
                                <span id="route-start-label" class="text-sm text-foreground truncate"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-accent shrink-0"></span>
                                <span id="route-end-label" class="text-sm text-foreground truncate"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-border">
                            <p class="text-sm"><span id="route-distance" class="font-heading font-bold text-foreground"></span></p>
                            <p class="text-sm text-muted-fg"><span id="route-duration"></span></p>
                            <div class="ms-auto flex gap-2">
                                <button id="reset-plan" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg border border-border text-foreground hover:bg-muted transition">Reset</button>
                                <button id="save-plan" type="button" class="text-xs font-semibold px-3 py-2 rounded-lg bg-primary text-white hover:bg-primary-hover transition">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <p id="route-status" class="text-sm text-muted-fg mt-2"></p>
            </div>

            <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Rencana Tersimpan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Klik untuk pratinjau di peta</p>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 62vh">
                    @forelse ($plans as $plan)
                        <div class="p-3 rounded-xl hover:bg-muted/60 transition flex items-center justify-between gap-3">
                            <button data-view-plan="{{ $plan->id }}" class="min-w-0 text-left flex-1">
                                <p class="font-bold text-sm text-foreground truncate">{{ $plan->name }}</p>
                                <p class="text-[11px] text-muted-fg mt-0.5">
                                    @if ($plan->distance_km)
                                        {{ $plan->distance_km }} km &middot; {{ $plan->duration_minutes }} menit
                                    @else
                                        {{ count($plan->points_json) }} titik
                                    @endif
                                </p>
                            </button>
                            <button data-delete-plan="{{ $plan->id }}" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition shrink-0">
                                <x-icon.trash class="w-4 h-4"/>
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <x-icon.navigation class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                            <p class="text-sm text-muted-fg">Belum ada rencana rute.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json, 'route_geometry_json' => $p->route_geometry_json, 'start_label' => $p->start_label, 'end_label' => $p->end_label, 'distance_km' => $p->distance_km, 'duration_minutes' => $p->duration_minutes])->toJson() !!}</script>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}"></script>
    <script src="{{ asset('js/map-plans.js') }}"></script>
</x-app-layout>
