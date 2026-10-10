<x-app-layout>
    <x-slot name="header">Titik Saya</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="mtn-page mtn-map-page mtn-map-saved space-y-6">
        <header class="mtn-map-header"><div><p class="mtn-map-eyebrow">Koleksi pribadi</p><h1>Titik Saya</h1><p class="mtn-map-description">Simpan tempat ke dalam list-mu sendiri: favorit, mau ke sana, bengkel langganan, atau list bikinanmu. Klik di peta untuk menyimpan.</p></div><span class="mtn-map-count">Tempat tersimpan</span></header>

        <div class="mtn-map-workspace">
            {{-- LEFT: list manager + daftar tempat (below the map on mobile, since the map is the primary surface there) --}}
            <div class="mtn-map-controls space-y-5">
                {{-- Manajer list --}}
                <div class="mtn-panel space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="mtn-section-title">List Saya</h3>
                        <button id="btn-new-list" type="button" class="text-xs font-semibold text-primary hover:underline">+ Buat List</button>
                    </div>
                    <div id="list-manager" class="space-y-1"></div>

                    {{-- Form buat/edit list (tersembunyi) --}}
                    <div id="list-form" class="hidden border-t border-border pt-3 space-y-2">
                        <input id="lf-name" aria-label="Nama list" type="text" maxlength="255" placeholder="Nama list"
                               class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <div>
                            <p class="text-xs font-semibold text-muted-fg mb-1">Icon</p>
                            <div id="lf-icons" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-muted-fg mb-1">Warna</p>
                            <div id="lf-colors" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <p id="lf-error" class="hidden text-xs text-accent"></p>
                        <div class="flex gap-2">
                            <x-ui.button id="lf-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                            <x-ui.button id="lf-save" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan</x-ui.button>
                        </div>
                    </div>
                </div>

                {{-- Form tambah tempat (tersembunyi sampai lokasi dipilih) --}}
                <div id="place-form" class="hidden mtn-panel mtn-map-editor space-y-3">
                    <h3 class="mtn-section-title">Simpan Tempat</h3>
                    <p id="pf-coords" class="text-xs text-muted-fg"></p>
                    <input id="pf-title" aria-label="Nama tempat" type="text" maxlength="255" placeholder="Nama tempat"
                           class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <textarea id="pf-desc" aria-label="Catatan tempat" rows="2" maxlength="2000" placeholder="Catatan (opsional)"
                              class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Simpan ke list</span>
                        <div id="pf-list"></div>
                    </div>
                    <div id="pf-photo-wrap">
                        <x-ui.photo-picker id="pf-photo" name="photo" />
                    </div>
                    <p id="pf-error" class="hidden text-xs text-accent"></p>
                    <div class="flex gap-2">
                        <x-ui.button id="pf-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="pf-save" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan</x-ui.button>
                    </div>
                </div>

                {{-- Daftar tempat --}}
                <div class="mtn-panel mtn-map-list-panel flex flex-col">
                    <div class="mtn-context mtn-map-list-heading">
                        <h3 class="mtn-section-title">Tempat Tersimpan</h3>
                    </div>
                    <div id="place-list" class="p-3 space-y-1 mtn-scroll-list"></div>
                </div>
            </div>

            {{-- RIGHT: map (first on mobile) --}}
            <div class="mtn-map-surface">
                <div class="mtn-map-frame">
                    <x-ui.map-search locate />
                    <div id="map" style="height: 64vh"></div>
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @include('partials.map-provider')
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-saved.js') }}?v={{ filemtime(public_path('js/map-saved.js')) }}"></script>
</x-app-layout>
