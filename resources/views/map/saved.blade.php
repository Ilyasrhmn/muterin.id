<x-app-layout>
    <x-slot name="header">Titik Saya</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="Tempat tersimpan" title="Titik Saya"
                    subtitle="Simpan tempat ke dalam list-mu sendiri: favorit, mau ke sana, bengkel langganan, atau list bikinanmu. Klik di peta untuk menyimpan." />

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- LEFT: list manager + daftar tempat (below the map on mobile, since the map is the primary surface there) --}}
            <div class="space-y-6 order-2 lg:order-none">
                {{-- Manajer list --}}
                <div class="bg-surface border border-border rounded-2xl p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-bold text-foreground text-sm">List Saya</h3>
                        <button id="btn-new-list" type="button" class="text-xs font-semibold text-primary hover:underline">+ Buat List</button>
                    </div>
                    <div id="list-manager" class="space-y-1"></div>

                    {{-- Form buat/edit list (tersembunyi) --}}
                    <div id="list-form" class="hidden border-t border-border pt-3 space-y-2">
                        <input id="lf-name" type="text" maxlength="255" placeholder="Nama list"
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
                <div id="place-form" class="hidden bg-surface border border-primary/30 rounded-2xl p-5 space-y-3">
                    <h3 class="font-heading font-bold text-foreground text-sm">Simpan Tempat</h3>
                    <p id="pf-coords" class="text-xs text-muted-fg"></p>
                    <input id="pf-title" type="text" maxlength="255" placeholder="Nama tempat"
                           class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    <textarea id="pf-desc" rows="2" maxlength="2000" placeholder="Catatan (opsional)"
                              class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Simpan ke list</span>
                        <div id="pf-list"></div>
                    </div>
                    <label id="pf-photo-wrap" class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Foto (opsional)</span>
                        <input id="pf-photo" type="file" accept="image/*"
                               class="w-full text-xs text-muted-fg file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                    </label>
                    <p id="pf-error" class="hidden text-xs text-accent"></p>
                    <div class="flex gap-2">
                        <x-ui.button id="pf-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="pf-save" variant="primary" size="sm" type="button" class="flex-1 justify-center">Simpan</x-ui.button>
                    </div>
                </div>

                {{-- Daftar tempat --}}
                <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-border bg-muted/40">
                        <h3 class="font-heading font-bold text-foreground text-sm">Tempat Tersimpan</h3>
                    </div>
                    <div id="place-list" class="p-3 space-y-1 overflow-y-auto" style="max-height: 40vh"></div>
                </div>
            </div>

            {{-- RIGHT: map (first on mobile) --}}
            <div class="lg:col-span-2 order-1 lg:order-none">
                <div class="bg-surface border border-border rounded-2xl p-3 mb-3 space-y-3">
                    <div class="flex items-center gap-3">
                        <button id="btn-my-location" type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-primary/10 text-primary hover:bg-primary/20 transition">
                            <i class="fas fa-location-crosshairs"></i> Lokasi Saya
                        </button>
                        <p class="text-xs text-muted-fg">atau klik di mana saja di peta untuk menyimpan tempat.</p>
                    </div>
                    <div class="relative">
                        <input id="search-location" type="text" placeholder="Cari lokasi..."
                               class="w-full rounded-xl border border-border bg-surface pl-10 pr-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-fg text-sm"></i>
                        <div id="search-results" class="hidden absolute top-full left-0 right-0 mt-1 bg-surface border border-border rounded-xl shadow-lg max-h-64 overflow-y-auto z-10"></div>
                    </div>
                </div>
                <div class="rounded-2xl overflow-hidden border border-border">
                    <div id="map" style="height: 64vh"></div>
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-saved.js') }}?v={{ filemtime(public_path('js/map-saved.js')) }}"></script>
</x-app-layout>
