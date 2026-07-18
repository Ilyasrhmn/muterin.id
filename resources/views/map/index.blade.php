<x-app-layout>
    <x-slot name="header"><h2 class="text-xl">Peta Perjalanan</h2></x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="max-w-6xl mx-auto p-4 md:p-6 space-y-3">
        <x-ui.card class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm font-medium text-foreground">
                Mode:
                <select id="mode" class="rounded-token border border-border bg-surface px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 transition">
                    <option value="view">Lihat</option>
                    <option value="moment">+ Momen</option>
                    <option value="hazard">+ Jalan Rawan</option>
                    <option value="quiet">+ Jalan Sepi</option>
                    <option value="plan">+ Titik Rencana</option>
                </select>
            </label>
            <x-ui.button id="save-plan" variant="primary" size="sm" type="button">Simpan Rencana</x-ui.button>

            <div class="flex items-center gap-3 ms-auto text-xs text-muted-fg">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background:blue"></span> Momen</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background:red"></span> Rawan</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background:green"></span> Sepi</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background:purple"></span> Rute</span>
            </div>
        </x-ui.card>

        <div id="map" style="height: 65vh" class="rounded-token border border-border overflow-hidden"></div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}"></script>
</x-app-layout>
