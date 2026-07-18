<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php $activeMotor = optional($dashboard->firstWhere(fn ($r) => $r['motor']->is_active))['motor'] ?? optional($dashboard->first())['motor']; @endphp

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

        {{-- Hero banner --}}
        <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-primary via-primary to-secondary shadow-lift">
            <div class="absolute inset-0 hero-grid opacity-60"></div>
            <div class="absolute -right-6 -top-8 text-white/10 pointer-events-none">
                <x-icon.motorcycle class="w-48 h-48"/>
            </div>
            <div class="relative p-6 sm:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-3">
                    <span class="inline-flex items-center gap-2 bg-white/15 text-white border border-white/20 font-bold uppercase tracking-[0.15em] text-[10px] px-3 py-1 rounded-full backdrop-blur-sm">
                        <span class="size-1.5 rounded-full bg-emerald-soft animate-pulse"></span>
                        {{ $activeMotor ? $activeMotor->nickname . ' aktif' : 'Belum ada motor aktif' }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Halo, {{ Auth::user()->name }} 👋</h1>
                    <p class="text-white/80 text-sm max-w-lg leading-relaxed">
                        Pantau kondisi perawatan motormu berdasarkan jarak tempuh asli. Semua terhitung otomatis dari GPS saat kamu riding.
                    </p>
                </div>
                @if ($activeMotor)
                    <div class="flex bg-black/15 backdrop-blur-md rounded-2xl border border-white/10 p-4 gap-6 shrink-0">
                        <div>
                            <p class="text-[10px] font-bold text-white/70 uppercase tracking-[0.15em]">Odometer</p>
                            <p class="text-white font-bold mt-1 tabular-nums">{{ number_format($activeMotor->current_odometer_km) }} km</p>
                        </div>
                        <div class="w-px bg-white/15"></div>
                        <div>
                            <p class="text-[10px] font-bold text-white/70 uppercase tracking-[0.15em]">Motor</p>
                            <p class="text-white font-bold mt-1">{{ $activeMotor->brand ?: $activeMotor->nickname }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Bento stats --}}
        <div data-reveal-group class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $stats = [
                    ['label' => 'Total Motor', 'value' => $kpi['motor_count'], 'suffix' => '', 'icon' => 'motorcycle', 'chip' => 'bg-blue-50 text-primary'],
                    ['label' => 'Total KM Ditempuh', 'value' => $kpi['total_km'], 'suffix' => ' km', 'icon' => 'gauge', 'chip' => 'bg-cyan-50 text-secondary'],
                    ['label' => 'Perlu Perhatian', 'value' => $kpi['attention'], 'suffix' => '', 'icon' => 'bell', 'chip' => 'bg-amber-50 text-status-yellow'],
                    ['label' => 'Total Biaya Servis', 'value' => $kpi['total_cost'], 'suffix' => '', 'icon' => 'wallet', 'chip' => 'bg-emerald-50 text-emerald-soft', 'prefix' => 'Rp '],
                ];
            @endphp
            @foreach ($stats as $s)
                <div data-reveal class="bg-surface border border-border rounded-2xl p-5 shadow-soft hover:-translate-y-0.5 hover:shadow-lift transition duration-300">
                    <div class="size-10 rounded-xl flex items-center justify-center mb-4 {{ $s['chip'] }}">
                        <x-dynamic-component :component="'icon.'.$s['icon']" class="w-5 h-5"/>
                    </div>
                    <p class="text-[10px] font-bold text-muted-fg uppercase tracking-[0.15em]">{{ $s['label'] }}</p>
                    <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">{{ $s['prefix'] ?? '' }}<span data-countup="{{ $s['value'] }}">0</span>{{ $s['suffix'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Motor cards --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-heading font-bold text-lg text-foreground">Status Perawatan Motor</h2>
                <a href="{{ route('motorcycles.create') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                    <x-icon.plus class="w-4 h-4"/> Tambah
                </a>
            </div>

            <div data-reveal-group class="grid gap-4 lg:grid-cols-2">
                @forelse ($dashboard as $row)
                    @php $needsAttention = $row['items']->contains(fn ($i) => $i['status']['color'] !== 'green'); @endphp
                    <div data-reveal class="bg-surface border border-border rounded-2xl shadow-soft hover:-translate-y-0.5 hover:shadow-lift transition duration-300 overflow-hidden">
                        <div class="flex items-center justify-between p-5 border-b border-border bg-muted/40">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                    <x-icon.motorcycle class="w-5 h-5"/>
                                </div>
                                <div>
                                    <p class="font-heading font-bold text-foreground">{{ $row['motor']->nickname }}</p>
                                    <p class="text-[11px] text-muted-fg tabular-nums">{{ number_format($row['motor']->current_odometer_km) }} km</p>
                                </div>
                            </div>
                            @if ($needsAttention)
                                <x-ui.badge variant="red">Perlu perhatian</x-ui.badge>
                            @else
                                <x-ui.badge variant="green">Aman</x-ui.badge>
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
                                Detail & tandai servis <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2 bg-surface border border-border rounded-2xl shadow-soft text-center py-14">
                        <div class="size-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-4">
                            <x-icon.motorcycle class="w-7 h-7"/>
                        </div>
                        <p class="font-heading font-semibold text-foreground mb-1">Belum ada motor</p>
                        <p class="text-sm text-muted-fg mb-5">Tambahkan motor pertamamu untuk mulai memantau.</p>
                        <x-ui.button variant="primary" href="{{ route('motorcycles.create') }}">
                            <x-icon.plus class="w-4 h-4"/> Tambah Motor
                        </x-ui.button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script src="{{ asset('js/notify.js') }}"></script>
</x-app-layout>
