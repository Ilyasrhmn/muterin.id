<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Peta Perjalanan</h2></x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <div class="p-4 space-y-2">
        <div class="flex gap-2 text-sm items-center">
            <label>Mode:
                <select id="mode" class="border rounded p-1">
                    <option value="view">Lihat</option>
                    <option value="moment">+ Momen</option>
                    <option value="hazard">+ Jalan Rawan</option>
                    <option value="quiet">+ Jalan Sepi</option>
                    <option value="plan">+ Titik Rencana</option>
                </select>
            </label>
            <button id="save-plan" class="px-2 py-1 bg-blue-600 text-white rounded">Simpan Rencana</button>
        </div>
        <div id="map" style="height: 70vh" class="rounded border"></div>
    </div>
    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}"></script>
</x-app-layout>
