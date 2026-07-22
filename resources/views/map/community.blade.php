<x-app-layout>
    <x-slot name="header">Peta Komunitas</x-slot>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">
        <x-ui.hero badge="{{ $pins->count() }} titik" title="Peta Keamanan Komunitas"
                    subtitle="Titik dari semua pengguna. Tandai jalan sepi, gelap, rawan, rusak, atau banjir — bantu yang lain tetap aman." />

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- LEFT: filter + form + daftar --}}
            <div class="space-y-6">
                <div class="bg-surface border border-border rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-bold text-foreground text-sm">Filter</h3>
                        <select id="filter-category" class="rounded-xl border border-border bg-surface px-3 py-1.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua</option>
                            <option value="sepi">Jalan Sepi</option>
                            <option value="gelap">Penerangan Minim</option>
                            <option value="rawan">Rawan Kriminal</option>
                            <option value="rusak">Jalan Rusak</option>
                            <option value="banjir">Rawan Banjir</option>
                            <option value="momen">Momen</option>
                        </select>
                    </div>
                    <button id="btn-my-location" type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-primary/10 text-primary hover:bg-primary/20 transition">
                        <x-icon.navigation class="w-4 h-4"/> Tandai Lokasi Saya
                    </button>
                    <p class="text-xs text-muted-fg leading-relaxed">Atau klik di mana saja di peta untuk menandai titik di sana.</p>
                </div>

                {{-- Form tambah titik (inline, tersembunyi sampai ada lokasi dipilih) --}}
                <div id="add-form" class="hidden bg-surface border border-primary/30 rounded-2xl p-5 space-y-3">
                    <h3 class="font-heading font-bold text-foreground text-sm">Tandai Titik Baru</h3>
                    <p id="add-coords" class="text-xs text-muted-fg"></p>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Kategori</span>
                        <select id="f-category" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                            <option value="sepi">Jalan Sepi</option>
                            <option value="gelap">Penerangan Minim</option>
                            <option value="rawan">Rawan Kriminal</option>
                            <option value="rusak">Jalan Rusak</option>
                            <option value="banjir">Rawan Banjir</option>
                            <option value="momen">Momen</option>
                        </select>
                    </label>

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

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Berlaku waktu</span>
                        <select id="f-time" class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm">
                            <option value="kapanpun">Kapan pun</option>
                            <option value="siang">Siang</option>
                            <option value="malam">Malam</option>
                        </select>
                    </label>

                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-muted-fg">Foto (opsional)</span>
                        <input id="f-photo" type="file" accept="image/*"
                               class="w-full text-xs text-muted-fg file:mr-3 file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                    </label>

                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input id="f-anon" type="checkbox" class="rounded border-border text-primary focus:ring-primary/30">
                        Posting sebagai anonim
                    </label>

                    <p id="add-error" class="hidden text-xs text-accent"></p>

                    <div class="flex gap-2 pt-1">
                        <x-ui.button id="add-cancel" variant="outline" size="sm" type="button" class="flex-1 justify-center">Batal</x-ui.button>
                        <x-ui.button id="add-submit" variant="primary" size="sm" type="button" class="flex-1 justify-center">Tandai</x-ui.button>
                    </div>
                </div>

                <div class="bg-surface border border-border rounded-2xl overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-border bg-muted/40">
                        <h3 class="font-heading font-bold text-foreground text-sm">Titik Terbaru</h3>
                    </div>
                    <div id="pin-list" class="p-3 space-y-1 overflow-y-auto" style="max-height: 44vh"></div>
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

    @csrf
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map-common.js') }}?v={{ filemtime(public_path('js/map-common.js')) }}"></script>
    <script src="{{ asset('js/map-community.js') }}?v={{ filemtime(public_path('js/map-community.js')) }}"></script>
</x-app-layout>
