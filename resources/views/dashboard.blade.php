<x-app-layout>
    <x-slot name="header">Beranda</x-slot>
    @php
        $activeRow = $dashboard->first(fn ($row) => $row['motor']->is_active) ?? $dashboard->first();
        $activeMotor = $activeRow['motor'] ?? null;
        $nextService = $activeRow ? $activeRow['items']->sortBy(fn ($i) => $i['status']['remaining'])->first() : null;
    @endphp
    <div class="mtn-dashboard">
        <div class="mtn-dashboard-title">
            <div><h1>Catatan perjalananmu.</h1><p>{{ $kpi['motor_count'] }} motor. Satu tempat untuk merawat semuanya.</p></div>
            <x-ui.button href="{{ route($activeMotor ? 'riding' : 'motorcycles.create') }}">
                <x-icon.navigation class="w-5 h-5"/>{{ $activeMotor ? 'Mulai perjalanan' : 'Tambahkan motor pertama' }}
            </x-ui.button>
        </div>
        @if ($nextService)
            <section class="mtn-priority" aria-labelledby="next-service-title">
                <div>
                    <p>Perawatan berikutnya · {{ $activeMotor->nickname }}</p>
                    <h2 id="next-service-title">{{ $nextService['item']->name }},
                        @if ($nextService['status']['remaining'] <= 0) sudah waktunya servis.
                        @else {{ number_format($nextService['status']['remaining']) }} km lagi.
                        @endif
                    </h2>
                    <p>Catat penggantian setelah servis agar pengingat tetap akurat.</p>
                </div>
                <x-ui.button variant="outline" href="{{ route('motorcycles.show', $activeMotor) }}"><x-icon.wrench class="w-4 h-4"/>Catat servis</x-ui.button>
            </section>
        @endif
        <div x-data="{ show: false }" x-show="show" x-cloak
             @mtn:install-available.window="if (!localStorage.getItem('mtn_install_dismissed')) show = true"
             @mtn:install-done.window="show = false" class="flex flex-wrap items-center gap-4 border border-border rounded-token p-4">
            <div class="flex-1 min-w-0"><p class="font-semibold">Pasang Muterin di layar utama</p><p class="text-sm text-muted-fg">Akses catatan motormu dari HP.</p></div>
            <button type="button" @click="window.mtnInstallApp()" class="min-h-11 px-4 text-primary font-semibold">Pasang aplikasi</button>
            <button type="button" @click="localStorage.setItem('mtn_install_dismissed', '1'); show = false" class="min-h-11 px-4 text-muted-fg">Nanti saja</button>
        </div>
        <dl class="mtn-metrics">
            <div><dt>Motor terdaftar</dt><dd>{{ $kpi['motor_count'] }}</dd></div>
            <div><dt>Total odometer</dt><dd>{{ number_format($kpi['total_km']) }} km</dd></div>
            <div><dt>Perawatan perlu perhatian</dt><dd>{{ $kpi['attention'] }}</dd></div>
            <div><dt>Total biaya servis</dt><dd>Rp{{ number_format($kpi['total_cost'], 0, ',', '.') }}</dd></div>
        </dl>
        <div class="mtn-dashboard-workspace"><section aria-labelledby="motors-title">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 id="motors-title" class="text-2xl font-extrabold">Motor saya</h2>
                <a href="{{ route('motorcycles.create') }}" class="min-h-11 inline-flex items-center text-sm font-semibold text-primary hover:underline">Tambah motor</a>
            </div>
            @forelse ($dashboard as $row)
                @php $needsAttention = $row['items']->contains(fn ($i) => $i['status']['color'] !== 'green'); @endphp
                <article class="mtn-motor-record">
                    <div class="mtn-motor-head">
                        <div class="flex items-center gap-4 min-w-0">
                            <x-icon.motorcycle class="w-8 h-8 shrink-0"/>
                            <div class="min-w-0"><h3>{{ $row['motor']->nickname }}</h3><p>{{ $row['motor']->brand }} {{ $row['motor']->model }} · {{ $row['motor']->plat_nomor }}</p></div>
                        </div>
                        <div><p class="!text-foreground !text-lg font-bold tabular-nums">{{ number_format($row['motor']->current_odometer_km) }} km</p><a href="{{ route('motorcycles.show', $row['motor']) }}#update-km" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary hover:underline"><x-icon.gauge class="w-4 h-4"/>Update KM</a></div>
                        <div class="text-sm {{ $needsAttention ? 'text-amber-800' : 'text-primary' }}"><p>{{ $needsAttention ? 'Perlu perhatian' : 'Servis masih aman' }}</p><p>Skor {{ $row['health']['score'] }} · {{ $row['health']['label'] }}</p></div>
                    </div>
                    <div class="mtn-maintenance-grid">
                        @foreach ($row['items'] as $i)
                            <div data-item-id="{{ $i['item']->id }}" data-item-name="{{ $i['item']->name }}" data-color="{{ $i['status']['color'] }}" class="space-y-2">
                                <p class="text-sm font-semibold">{{ $i['item']->name }}</p><p class="text-xs text-muted-fg tabular-nums">{{ $i['status']['used'] }} / {{ $i['item']->interval_km }} km</p>
                                <x-ui.progress :percent="$i['status']['percent']" :color="$i['status']['color']" />
                                @if ($i['prediction']['days_left'] !== null)
                                    <p class="text-xs text-muted-fg">@if ($i['prediction']['days_left'] === 0) Sudah waktunya servis
                                        @else Estimasi {{ $i['prediction']['days_left'] }} hari lagi ({{ $i['prediction']['predicted_date']->translatedFormat('d M Y') }}) @endif</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('motorcycles.show', $row['motor']) }}" class="inline-flex min-h-11 items-center mt-4 text-sm text-primary font-semibold hover:underline">Detail &amp; tandai servis</a>
                </article>
            @empty
                <div class="py-12 space-y-4"><p class="text-lg font-bold">Belum ada motor</p><p class="text-muted-fg">Tambahkan motor pertamamu untuk mulai memantau kilometer dan perawatan.</p><x-ui.button href="{{ route('motorcycles.create') }}">Tambah motor</x-ui.button></div>
            @endforelse
        </section>

        </div>
    </div>

</x-app-layout>
