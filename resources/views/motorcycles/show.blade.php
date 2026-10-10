<x-app-layout>
    <x-slot name="header">{{ $motorcycle->nickname }}</x-slot>

    <div class="mtn-page mtn-records-page space-y-6">
        <div class="flex items-center justify-between bg-surface border border-border rounded-lg  p-5">
            <div class="flex items-center gap-3">
                <div class="size-11 rounded-md bg-primary/10 text-primary flex items-center justify-center">
                    <x-icon.motorcycle class="w-6 h-6"/>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-heading text-xl font-bold text-foreground">{{ $motorcycle->nickname }}</h1>
                        @if ($motorcycle->plat_nomor)
                            <span class="text-[11px] font-bold font-heading tracking-wider text-foreground bg-muted px-2 py-0.5 rounded-md border border-border">{{ $motorcycle->plat_nomor }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-muted-fg tabular-nums">{{ $motorcycle->brand }} {{ $motorcycle->model }} &middot; {{ number_format($motorcycle->current_odometer_km) }} km</p>
                </div>
            </div>
            <x-ui.button variant="outline" size="sm" href="{{ route('motorcycles.edit', $motorcycle) }}"><x-icon.pencil class="w-4 h-4"/>Edit</x-ui.button>
        </div>

        <div class="mtn-workspace">
        <section><h2 class="mtn-section-title mb-4">Perawatan motor</h2>
        <div data-reveal-group class="mtn-panel">
            @foreach ($items as $i)
                <section data-reveal x-data="{ open: false }" class="mtn-record mtn-maintenance-record space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-foreground">{{ $i['item']->name }}</span>
                        <span class="text-muted-fg">{{ $i['status']['used'] }} / {{ $i['item']->interval_km }} km ({{ $i['status']['percent'] }}%)</span>
                    </div>
                    <x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />

                    @if ($i['status']['color'] === 'red')
                        <x-ui.button variant="accent" size="sm" href="https://www.google.com/maps/search/bengkel+motor+terdekat/" target="_blank" rel="noopener">
                            <x-icon.map-pin class="w-4 h-4"/> Cari Bengkel Terdekat
                        </x-ui.button>
                    @endif

                    <button @click="open = !open" :aria-expanded="open.toString()" type="button" class="inline-flex items-center gap-2 text-sm text-primary font-medium hover:underline">
                        <x-icon.check class="w-4 h-4"/>
                        Tandai "{{ $i['item']->name }}" selesai
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('maintenance.complete', $i['item']) }}" enctype="multipart/form-data" class="mtn-form-grid pt-4 border-t border-border">
                        @csrf
                        <x-ui.input name="cost" label="Biaya (opsional)" type="number" placeholder="0" />
                        <x-ui.input name="workshop_name" label="Nama Bengkel (opsional)" placeholder="Bengkel Jaya Motor" />
                        <x-ui.input name="parts" label="Sparepart diganti (opsional)" placeholder="Oli Federal 0.8L, filter oli" />
                        <x-ui.photo-picker id="receipt-{{ $i['item']->id }}" name="receipt" label="Foto Nota (opsional)" />
                        <x-ui.input name="serviced_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                        <x-ui.button variant="primary" size="sm" type="submit">
                            <x-icon.check class="w-4 h-4"/> Simpan
                        </x-ui.button>
                    </form>
                </section>
            @endforeach
        </div></section>
        <aside class="space-y-6">        <div x-data="{ open: false }" class="mtn-panel">
            <button type="button" id="update-km" @click="open = !open" :aria-expanded="open.toString()" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                <x-icon.gauge class="w-4 h-4"/>Update KM
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('odometer.store') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="motorcycle_id" value="{{ $motorcycle->id }}">
                <x-ui.input name="reading_km" label="Odometer sekarang (km)" type="number" required />
                <x-ui.input name="recorded_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-ui.button variant="primary" size="sm" type="submit">Simpan</x-ui.button>
            </form>
        </div>

        @if (count($documentItems))
            <div class="mtn-context">
                <div class="p-5 border-b border-border bg-muted/40 flex items-center gap-2">
                    <x-icon.calendar class="w-4 h-4 text-primary"/>
                    <h3 class="mtn-section-title">Dokumen</h3>
                </div>
                <div class="p-3 space-y-1">
                    @foreach ($documentItems as $doc)
                        <div class="mtn-record flex items-center justify-between gap-3 py-4">
                            <span class="text-sm text-foreground">{{ $doc['label'] }}</span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg
                                {{ $doc['color'] === 'red' ? 'bg-red-50 text-red-600' : ($doc['color'] === 'yellow' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                @if ($doc['days_left'] < 0)
                                    Lewat {{ abs($doc['days_left']) }} hari
                                @elseif ($doc['days_left'] === 0)
                                    Jatuh tempo hari ini
                                @else
                                    {{ $doc['days_left'] }} hari lagi
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

</aside>
        </div>
    </div>
</x-app-layout>
