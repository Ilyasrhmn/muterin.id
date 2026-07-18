<x-app-layout>
    <x-slot name="header">Titik Saya</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $pins->count() }} titik" title="Titik Pribadi di Peta"
                    subtitle="Tandai jalan rawan, jalan sepi, atau momen perjalanan. Klik di peta untuk menambah titik." />

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-3">
                <div class="bg-surface border border-border rounded-2xl p-4 flex flex-wrap items-center gap-3">
                    <label class="text-sm font-semibold text-foreground flex items-center gap-2">
                        Kategori titik:
                        <select id="pin-category" class="rounded-xl border border-border bg-surface px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="hazard">Jalan Rawan</option>
                            <option value="quiet">Jalan Sepi</option>
                            <option value="moment">Momen</option>
                        </select>
                    </label>
                    <p class="text-xs text-muted-fg">Lalu klik lokasi di peta untuk menandai.</p>
                    <div class="flex items-center gap-3 ms-auto text-xs text-muted-fg">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-accent"></span> Rawan</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Sepi</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-primary"></span> Momen</span>
                    </div>
                </div>
                <div class="rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 54vh"></div>
                </div>
            </div>

            <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Daftar Titik</h3>
                </div>
                <div class="p-3 space-y-1 overflow-y-auto" style="max-height: 58vh">
                    @php $catLabel = ['hazard' => 'Rawan', 'quiet' => 'Sepi', 'moment' => 'Momen']; @endphp
                    @forelse ($pins as $pin)
                        <div class="p-3 rounded-xl hover:bg-muted/60 transition flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-sm text-foreground truncate">{{ $pin->title }}</p>
                                <p class="text-[11px] text-muted-fg mt-0.5">{{ $catLabel[$pin->category] ?? $pin->category }}@if($pin->note) &middot; {{ $pin->note }}@endif</p>
                            </div>
                            <button data-delete-pin="{{ $pin->id }}" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition shrink-0">
                                <x-icon.trash class="w-4 h-4"/>
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <x-icon.map-pin class="w-10 h-10 text-muted-fg mx-auto mb-3"/>
                            <p class="text-sm text-muted-fg">Belum ada titik. Klik di peta untuk menambah.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}"></script>
    <script src="{{ asset('js/map-pins.js') }}"></script>
</x-app-layout>
