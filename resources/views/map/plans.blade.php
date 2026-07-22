<x-app-layout>
    <x-slot name="header">Rencana Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $plans->count() }} rencana" title="Rencanakan Rute"
                    subtitle="Cari tempat atau klik di peta, lalu pilih titik awal & tujuan — rute jalan otomatis dihitung." />

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- LEFT: persistent planning panel + saved plans --}}
            <div class="space-y-6">
                <div class="bg-surface border border-border rounded-2xl p-5 space-y-4">
                    <h3 class="font-heading font-bold text-foreground text-sm">Rencana Perjalanan</h3>

                    {{-- Search --}}
                    <div>
                        <div class="bg-muted/50 rounded-xl border border-border flex items-center gap-2 px-3 py-2">
                            <x-icon.search class="w-4 h-4 text-muted-fg shrink-0"/>
                            <input id="search-input" type="text" placeholder="Cari tempat…" autocomplete="off"
                                   class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-fg">
                            <button id="search-btn" type="button" class="text-sm font-semibold text-primary hover:underline shrink-0">Cari</button>
                        </div>
                        <div id="search-results" class="mt-1 bg-surface rounded-xl shadow-soft border border-border overflow-hidden hidden"></div>
                    </div>

                    {{-- Titik Awal --}}
                    <div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-muted/50">
                            <span class="w-3 h-3 rounded-full bg-status-green shrink-0"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-fg">Titik Awal</p>
                                <p id="start-label" class="text-sm text-muted-fg truncate">Belum dipilih</p>
                            </div>
                            <button id="clear-start" type="button" class="hidden text-muted-fg hover:text-accent shrink-0 text-lg leading-none">&times;</button>
                        </div>
                        <button id="btn-current-location-start" type="button"
                                class="mt-2 w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-location-crosshairs"></i>
                            <span id="loc-start-text">Gunakan Lokasi Saya</span>
                            <i class="fas fa-spinner fa-spin hidden" id="loc-start-spinner"></i>
                        </button>
                    </div>

                    {{-- Titik Singgah (dynamic) --}}
                    <div id="via-list" class="space-y-2"></div>

                    {{-- Titik Tujuan --}}
                    <div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-muted/50">
                            <span class="w-3 h-3 rounded-full bg-accent shrink-0"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted-fg">Titik Tujuan</p>
                                <p id="end-label" class="text-sm text-muted-fg truncate">Belum dipilih</p>
                            </div>
                            <button id="clear-end" type="button" class="hidden text-muted-fg hover:text-accent shrink-0 text-lg leading-none">&times;</button>
                        </div>
                        <button id="btn-current-location-end" type="button"
                                class="mt-2 w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-location-crosshairs"></i>
                            <span id="loc-end-text">Gunakan Lokasi Saya</span>
                            <i class="fas fa-spinner fa-spin hidden" id="loc-end-spinner"></i>
                        </button>
                    </div>

                    {{-- Route summary --}}
                    <div id="route-summary" class="hidden flex items-center gap-4 p-3 rounded-xl bg-primary-soft border border-primary/20">
                        <div class="flex items-center gap-1.5">
                            <x-icon.route class="w-4 h-4 text-primary"/>
                            <span id="route-distance" class="font-heading font-bold text-primary text-sm"></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <x-icon.clock class="w-4 h-4 text-muted-fg"/>
                            <span id="route-duration" class="text-sm text-muted-fg"></span>
                        </div>
                    </div>

                    {{-- Peringatan titik komunitas di sepanjang rute --}}
                    <div id="community-warning" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <p class="text-sm font-semibold text-amber-800 flex items-center gap-1.5">
                            <x-icon.alert-triangle class="w-4 h-4"/> <span id="community-warning-text"></span>
                        </p>
                        <p class="text-xs text-amber-700 mt-0.5">Titik-titik ini ditampilkan di peta. Hati-hati di area tersebut.</p>
                    </div>

                    <p id="route-status" class="text-sm text-muted-fg"></p>

                    <div class="flex gap-2">
                        <x-ui.button id="reset-plan" variant="outline" size="sm" type="button" class="flex-1 justify-center">Reset</x-ui.button>
                        <x-ui.button id="save-plan" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan Rencana</x-ui.button>
                    </div>

                    <p class="text-xs text-muted-fg leading-relaxed">
                        Klik di mana saja di peta, lalu pilih apakah lokasi itu titik awal, singgah, atau tujuan.
                    </p>
                </div>

                <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-border bg-muted/40">
                        <h3 class="font-heading font-bold text-foreground text-sm">Rencana Tersimpan</h3>
                        <p class="text-xs text-muted-fg mt-0.5">Klik untuk pratinjau di peta</p>
                    </div>
                    <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 40vh">
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

            {{-- RIGHT: map --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 72vh"></div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json, 'route_geometry_json' => $p->route_geometry_json, 'start_label' => $p->start_label, 'end_label' => $p->end_label, 'distance_km' => $p->distance_km, 'duration_minutes' => $p->duration_minutes])->toJson() !!}</script>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-plans.js') }}?v={{ filemtime(public_path('js/map-plans.js')) }}"></script>
</x-app-layout>
