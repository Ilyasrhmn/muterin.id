<x-app-layout>
    <x-slot name="header">Laporan</x-slot>

    <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <x-ui.hero badge="Cost Report" title="Laporan Biaya Kepemilikan"
                    subtitle="Total biaya BBM + servis semua motormu, biaya per km, dan tren pengeluaran bulanan." />

        <div data-reveal-group class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div data-reveal class="bg-primary-soft border border-primary/15 rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-white text-primary flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Cost of Ownership</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $tco }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Biaya per KM</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">
                    @if ($costPerKm) Rp <span data-countup="{{ $costPerKm }}">0</span> @else  @endif
                </p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <x-icon.droplet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total BBM</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalFuelCost }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Servis</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalServiceCost }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Pengeluaran Lain</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalOtherCost }}">0</span></p>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Tren Pengeluaran Bulanan</h3>
                <p class="text-xs text-muted-fg mt-0.5">BBM vs servis, 6 bulan terakhir.</p>
            </div>
            <div class="p-5">
                @if ($trend->sum('fuel') + $trend->sum('service') + $trend->sum('other') === 0)
                    <p class="text-sm text-muted-fg text-center py-10">Belum ada data pengeluaran.</p>
                @else
                    <canvas id="trend-chart" height="220" role="img" aria-label="Grafik tren pengeluaran bulanan BBM dan servis"></canvas>
                @endif
            </div>
        </div>

        @if ($efficiencySeries->flatten(1)->isNotEmpty())
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Tren Efisiensi BBM</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Km per liter dari tiap pengisian tank penuh.</p>
                </div>
                <div class="p-5">
                    <canvas id="efficiency-chart" height="220" role="img" aria-label="Grafik tren efisiensi bahan bakar per motor"></canvas>
                </div>
            </div>
        @endif
    </div>

    @if ($trend->sum('fuel') + $trend->sum('service') + $trend->sum('other') > 0 || $efficiencySeries->flatten(1)->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            @if ($trend->sum('fuel') + $trend->sum('service') + $trend->sum('other') > 0)
            new Chart(document.getElementById('trend-chart'), {
                type: 'bar',
                data: {
                    labels: {!! json_encode($trend->pluck('month')) !!},
                    datasets: [
                        { label: 'BBM', data: {!! json_encode($trend->pluck('fuel')) !!}, backgroundColor: '#0F766E' },
                        { label: 'Servis', data: {!! json_encode($trend->pluck('service')) !!}, backgroundColor: '#D97706' },
                        { label: 'Lainnya', data: {!! json_encode($trend->pluck('other')) !!}, backgroundColor: '#64748B' },
                    ],
                },
                options: { scales: { x: { stacked: true }, y: { stacked: true } } },
            });
            @endif

            @if ($efficiencySeries->flatten(1)->isNotEmpty())
            @php
                // ponytail: align each series to the shared label list here (in PHP) so the
                // Chart.js config below stays plain JSON  no per-dataset lookup logic in JS.
                $efficiencyAligned = $efficiencySeries->map(
                    fn ($series) => $efficiencyLabels->map(
                        fn ($date) => optional(collect($series)->firstWhere('date', $date))['km_per_liter']
                    )->values()
                );
            @endphp
            new Chart(document.getElementById('efficiency-chart'), {
                type: 'line',
                data: {
                    labels: {!! json_encode($efficiencyLabels) !!},
                    datasets: [
                        @php $palette = ['#0F766E', '#D97706', '#2563EB', '#DB2777', '#7C3AED']; @endphp
                        @foreach ($efficiencyAligned as $name => $data)
                            @if (count($efficiencySeries[$name]))
                            {
                                label: {!! json_encode($name) !!},
                                data: {!! json_encode($data) !!},
                                borderColor: {!! json_encode($palette[$loop->index % count($palette)]) !!},
                                backgroundColor: {!! json_encode($palette[$loop->index % count($palette)].'33') !!},
                                tension: 0.4,
                                spanGaps: true,
                                fill: true,
                                pointRadius: 3,
                            },
                            @endif
                        @endforeach
                    ],
                },
                options: { scales: { x: { type: 'category' } } },
            });
            @endif
        </script>
    @endif
</x-app-layout>
