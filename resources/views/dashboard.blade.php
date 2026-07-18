<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php $activeMotor = optional($dashboard->firstWhere(fn ($r) => $r['motor']->is_active))['motor'] ?? optional($dashboard->first())['motor']; @endphp

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

        <x-ui.hero :badge="$activeMotor ? $activeMotor->nickname.' aktif' : 'Belum ada motor aktif'"
                   title="Halo, {{ Auth::user()->name }}"
                   subtitle="Pantau kondisi perawatan motormu berdasarkan jarak tempuh asli, dihitung otomatis dari GPS saat riding.">
            @if ($activeMotor)
                <x-slot:side>
                    <div class="flex bg-black/15 backdrop-blur-md rounded-2xl border border-white/10 p-4 gap-6">
                        <div>
                            <p class="text-[10px] font-bold text-teal-200 uppercase tracking-[0.15em]">Odometer</p>
                            <p class="text-white font-bold mt-1 tabular-nums">{{ number_format($activeMotor->current_odometer_km) }} km</p>
                        </div>
                        <div class="w-px bg-white/15"></div>
                        <div>
                            <p class="text-[10px] font-bold text-teal-200 uppercase tracking-[0.15em]">Motor</p>
                            <p class="text-white font-bold mt-1">{{ $activeMotor->brand ?: $activeMotor->nickname }}</p>
                        </div>
                    </div>
                </x-slot:side>
            @endif
        </x-ui.hero>

        {{-- Stat cards --}}
        <div data-reveal-group class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-reveal class="bg-primary-soft border border-primary/15 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="size-10 rounded-xl bg-white text-primary flex items-center justify-center">
                        <x-icon.motorcycle class="w-5 h-5"/>
                    </div>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Motor</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight"><span data-countup="{{ $kpi['motor_count'] }}">0</span></p>
            </div>

            @php
                $stats = [
                    ['label' => 'Total KM Ditempuh', 'value' => $kpi['total_km'], 'suffix' => ' km', 'icon' => 'gauge', 'chip' => 'bg-blue-50 text-blue-600'],
                    ['label' => 'Perlu Perhatian', 'value' => $kpi['attention'], 'suffix' => '', 'icon' => 'bell', 'chip' => 'bg-amber-50 text-amber-600'],
                    ['label' => 'Total Biaya Servis', 'value' => $kpi['total_cost'], 'suffix' => '', 'prefix' => 'Rp ', 'icon' => 'wallet', 'chip' => 'bg-primary-soft text-primary'],
                ];
            @endphp
            @foreach ($stats as $s)
                <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="size-10 rounded-xl flex items-center justify-center {{ $s['chip'] }}">
                            <x-dynamic-component :component="'icon.'.$s['icon']" class="w-5 h-5"/>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">{{ $s['label'] }}</p>
                    <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">{{ $s['prefix'] ?? '' }}<span data-countup="{{ $s['value'] }}">0</span>{{ $s['suffix'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Motor cards --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-heading font-bold text-base text-foreground">Status Perawatan Motor</h2>
                <a href="{{ route('motorcycles.create') }}" class="text-sm font-semibold text-primary hover:underline">Tambah motor</a>
            </div>

            <div data-reveal-group class="grid gap-4 lg:grid-cols-2">
                @forelse ($dashboard as $row)
                    @php $needsAttention = $row['items']->contains(fn ($i) => $i['status']['color'] !== 'green'); @endphp
                    <div data-reveal class="bg-surface border border-border rounded-2xl overflow-hidden">
                        <div class="flex items-center justify-between p-5 border-b border-border bg-muted/40">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-primary-soft text-primary flex items-center justify-center">
                                    <x-icon.motorcycle class="w-5 h-5"/>
                                </div>
                                <div>
                                    <p class="font-heading font-bold text-foreground text-sm">{{ $row['motor']->nickname }}</p>
                                    <p class="text-[11px] text-muted-fg tabular-nums">{{ $row['motor']->plat_nomor }} &middot; {{ number_format($row['motor']->current_odometer_km) }} km</p>
                                </div>
                            </div>
                            @if ($needsAttention)
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-lg bg-red-50 text-red-600">Perhatian</span>
                            @else
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700">Aman</span>
                            @endif
                        </div>
                        <div class="p-5 space-y-3">
                            @foreach ($row['items'] as $i)
                                <div data-item-id="{{ $i['item']->id }}" data-item-name="{{ $i['item']->name }}" data-color="{{ $i['status']['color'] }}" class="space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="font-medium text-foreground">{{ $i['item']->name }}</span>
                                        <span class="text-muted-fg tabular-nums">{{ $i['status']['used'] }} / {{ $i['item']->interval_km }} km</span>
                                    </div>
                                    <x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />
                                </div>
                            @endforeach
                            <a href="{{ route('motorcycles.show', $row['motor']) }}" class="inline-flex items-center gap-1 text-sm text-primary font-semibold hover:underline pt-1">
                                Detail &amp; tandai servis <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2 bg-surface border border-border rounded-2xl text-center py-14">
                        <div class="size-14 rounded-2xl bg-primary-soft text-primary flex items-center justify-center mx-auto mb-4">
                            <x-icon.motorcycle class="w-7 h-7"/>
                        </div>
                        <p class="font-heading font-semibold text-foreground mb-1">Belum ada motor</p>
                        <p class="text-sm text-muted-fg mb-5">Tambahkan motor pertamamu untuk mulai memantau.</p>
                        <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">Tambah Motor</x-ui.button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script src="{{ asset('js/notify.js') }}"></script>
</x-app-layout>
