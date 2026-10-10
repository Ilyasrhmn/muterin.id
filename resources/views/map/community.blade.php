<x-app-layout>
    <x-slot name="header">Peta Komunitas</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="mtn-page mtn-map-page mtn-map-community space-y-6">
        <header class="mtn-map-header"><div><p class="mtn-map-eyebrow">Kondisi jalan bersama</p><h1>Peta Keamanan Komunitas</h1><p class="mtn-map-description">Titik dari semua pengguna. Tandai jalan sepi, gelap, rawan, rusak, atau banjir untuk bantu yang lain tetap aman.</p></div><span class="mtn-map-count">{{ $pins->count() }} titik</span></header>

        <div class="mtn-map-workspace">
            {{-- LEFT: filter + form + daftar (below the map on mobile, since the map is the primary surface there) --}}
            <div class="mtn-map-controls space-y-5">
                <div class="mtn-panel space-y-3">
                    <h3 class="mtn-section-title">Titik komunitas</h3>
                    <div class="mtn-map-personal grid grid-cols-3 gap-2" role="group" aria-label="Filter personal">
                        <button type="button" data-scope="favorited" class="scope-chip flex flex-col items-center gap-1 py-2 rounded-xl border border-border text-muted-fg hover:bg-muted transition text-[11px] font-semibold">
                            <i class="fas fa-heart"></i> Favorit
                        </button>
                        <button type="button" data-scope="liked" class="scope-chip flex flex-col items-center gap-1 py-2 rounded-xl border border-border text-muted-fg hover:bg-muted transition text-[11px] font-semibold">
                            <i class="fas fa-thumbs-up"></i> Like
                        </button>
                        <button type="button" data-scope="saved" class="scope-chip flex flex-col items-center gap-1 py-2 rounded-xl border border-border text-muted-fg hover:bg-muted transition text-[11px] font-semibold">
                            <i class="fas fa-bookmark"></i> Tersimpan
                        </button>
                    </div>
                    <div class="mtn-map-category"><p class="text-xs font-semibold text-muted-fg mb-2">Kategori kondisi jalan</p><div id="filter-category"></div></div>

                    <p class="text-xs text-muted-fg leading-relaxed">Atau klik di mana saja di peta untuk menandai titik di sana.</p>
                </div>

                {{-- Form tambah titik (inline, tersembunyi sampai ada lokasi dipilih) --}}
                <div id="add-form" class="hidden mtn-panel mtn-map-editor space-y-3">
                    <h3 class="mtn-section-title">Tandai Titik Baru</h3>
                    <p id="add-coords" class="text-xs text-muted-fg"></p>

                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Kategori</span>
                        <div id="f-category"></div>
                    </div>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Judul</span>
                        <input id="f-title" type="text" maxlength="255" placeholder="mis. Jalan sepi & gelap"
                               class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Deskripsi (opsional)</span>
                        <textarea id="f-description" rows="2" maxlength="2000" placeholder="Ceritakan kondisinya…"
                                  class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    </label>

                    <label id="f-time-wrap" class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Berlaku waktu</span>
                        <select id="f-time" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                            <option value="kapanpun">Kapan pun</option>
                            <option value="siang">Siang</option>
                            <option value="malam">Malam</option>
                        </select>
                    </label>

                    <x-ui.photo-picker id="f-photo" name="photo" />

                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input id="f-anon" type="checkbox" class="rounded border-border text-primary focus:ring-primary/30">
                        Posting sebagai anonim
                    </label>

                    <p id="add-error" class="hidden text-xs text-accent"></p>

                    <div class="flex gap-2 pt-1">
                        <x-ui.button id="add-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="add-submit" variant="primary" size="sm" type="button" class="flex-1 justify-center gap-2">
                            <span class="mtn-loader hidden" id="add-submit-spinner" style="font-size:13px"></span>
                            <span id="add-submit-text">Tandai</span>
                        </x-ui.button>
                    </div>
                </div>

                <div class="mtn-panel mtn-map-list-panel flex flex-col">
                    <div class="mtn-context mtn-map-list-heading">
                        <h3 class="mtn-section-title">Titik Terbaru</h3>
                    </div>
                    <div id="pin-list" class="p-3 space-y-1 mtn-scroll-list"></div>
                </div>
            </div>

            {{-- RIGHT: map (first on mobile) --}}
            <div class="mtn-map-surface">

                <div class="mtn-map-frame">
                    <x-ui.map-search locate />
                    <div id="map" style="height: 68vh"></div>
                </div>
            </div>
        </div>
    </div>

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @include('partials.map-provider')
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-community.js') }}?v={{ filemtime(public_path('js/map-community.js')) }}"></script>
</x-app-layout>
