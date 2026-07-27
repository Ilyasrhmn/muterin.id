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
                    <div id="trend-chart" role="img" aria-label="Grafik tren pengeluaran bulanan BBM dan servis"></div>
                @endif
            </div>
        </div>

        @if ($efficiencySeries->flatten(1)->isNotEmpty() || $efficiencyPending->isNotEmpty())
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Tren Efisiensi BBM</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Km per liter dari tiap pengisian tank penuh.</p>
                </div>
                <div class="p-5">
                    @if ($efficiencySeries->flatten(1)->isNotEmpty())
                        <div id="efficiency-chart" role="img" aria-label="Grafik tren efisiensi bahan bakar per motor"></div>
                    @else
                        <p class="text-sm text-muted-fg text-center py-6">Belum ada motor dengan data efisiensi yang cukup.</p>
                    @endif

                    @if ($efficiencyPending->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach ($efficiencyPending as $p)
                                <div class="flex items-start gap-2.5 text-xs bg-amber-50 text-amber-800 rounded-xl px-3 py-2.5">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <span><strong>{{ $p['nickname'] }}</strong> perlu {{ max(1, 2 - $p['full_tank_count']) }} pengisian penuh lagi sebelum efisiensi BBM bisa dihitung.</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($trend->sum('fuel') + $trend->sum('service') + $trend->sum('other') > 0)
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
        <script>
            new ApexCharts(document.getElementById('trend-chart'), {
                series: [
                    { name: 'BBM', data: {!! json_encode($trend->pluck('fuel')) !!} },
                    { name: 'Servis', data: {!! json_encode($trend->pluck('service')) !!} },
                    { name: 'Lainnya', data: {!! json_encode($trend->pluck('other')) !!} },
                ],
                chart: { type: 'bar', height: 260, stacked: true, toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                colors: ['#0F766E', '#D97706', '#64748B'],
                xaxis: { categories: {!! json_encode($trend->pluck('month')) !!} },
                yaxis: { labels: { formatter: (val) => 'Rp' + val.toLocaleString('id-ID') } },
                dataLabels: { enabled: false },
                legend: { position: 'bottom' },
            }).render();
        </script>
    @endif

    @if ($efficiencySeries->flatten(1)->isNotEmpty())
        @if ($trend->sum('fuel') + $trend->sum('service') + $trend->sum('other') === 0)
            <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
        @endif
        <script>
            @php $palette = ['#0F766E', '#D97706', '#2563EB', '#DB2777', '#7C3AED']; @endphp
            new ApexCharts(document.getElementById('efficiency-chart'), {
                series: [
                    @foreach ($efficiencySeries as $name => $points)
                        @if (count($points))
                        {
                            name: {!! json_encode($name) !!},
                            data: {!! json_encode(collect($points)->map(fn ($p) => ['x' => $p['date'], 'y' => $p['km_per_liter']])->values()) !!},
                        },
                        @endif
                    @endforeach
                ],
                chart: { type: 'line', height: 260, toolbar: { show: false } },
                colors: {!! json_encode($palette) !!},
                stroke: { curve: 'straight', width: 2.5 },
                markers: { size: 6, hover: { size: 10 } },
                dataLabels: { enabled: false },
                grid: { clipMarkers: false },
                tooltip: { theme: 'dark' },
                xaxis: { type: 'datetime' },
                yaxis: { tickAmount: 2, labels: { formatter: (val) => val + ' km/l' } },
                legend: { position: 'bottom' },
            }).render();
        </script>
    @endif
</x-app-layout>
