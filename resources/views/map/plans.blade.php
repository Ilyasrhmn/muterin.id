<x-app-layout>
    <x-slot name="header">Rencana Rute</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        {{-- Hero --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-r from-primary to-secondary shadow-lift p-6 sm:p-8">
            <div class="absolute inset-0 hero-grid opacity-60"></div>
            <div class="relative flex items-center justify-between gap-4">
                <div class="space-y-2">
                    <span class="inline-flex items-center gap-2 bg-white/15 text-white border border-white/20 font-bold uppercase tracking-[0.15em] text-[10px] px-3 py-1 rounded-full">
                        <x-icon.navigation class="w-3 h-3"/> {{ $plans->count() }} rencana
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Rencanakan Rute</h1>
                    <p class="text-white/80 text-sm max-w-lg">Klik beberapa titik di peta untuk menyusun rencana perjalanan, lalu simpan untuk dipakai nanti.</p>
                </div>
                <x-icon.navigation class="w-24 h-24 text-white/10 hidden sm:block"/>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-3">
                <div class="bg-surface border border-border rounded-2xl shadow-soft p-4 flex flex-wrap items-center gap-3">
                    <p class="text-sm text-muted-fg">Klik titik-titik di peta untuk menyusun jalur.</p>
                    <div class="flex items-center gap-2 ms-auto">
                        <x-ui.button id="reset-plan" variant="outline" size="sm" type="button">Reset</x-ui.button>
                        <x-ui.button id="save-plan" variant="primary" size="sm" type="button">
                            <x-icon.check class="w-4 h-4"/> Simpan Rencana
                        </x-ui.button>
                    </div>
                </div>
                <div class="rounded-[24px] overflow-hidden border border-border shadow-soft">
                    <div id="map" style="height: 54vh"></div>
                </div>
            </div>

            <div class="bg-surface border border-border rounded-[24px] shadow-soft overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground">Rencana Tersimpan</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Klik untuk pratinjau (merah putus-putus)</p>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 58vh">
                    @forelse ($plans as $plan)
                        <div class="p-4 rounded-2xl hover:bg-muted/60 transition flex items-center justify-between gap-3">
                            <button data-view-plan="{{ $plan->id }}" class="min-w-0 text-left flex-1">
                                <p class="font-bold text-sm text-foreground truncate">{{ $plan->name }}</p>
                                <p class="text-[11px] text-muted-fg mt-0.5">{{ count($plan->points_json) }} titik</p>
                            </button>
                            <button data-delete-plan="{{ $plan->id }}" class="p-1.5 rounded-token text-muted-fg hover:text-accent hover:bg-accent/10 transition shrink-0">
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

    <script type="application/json" id="plans-data">{!! $plans->map(fn ($p) => ['id' => $p->id, 'points_json' => $p->points_json])->toJson() !!}</script>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}"></script>
    <script src="{{ asset('js/map-plans.js') }}"></script>
</x-app-layout>
