<x-app-layout>
    <x-slot name="header">Biaya & Servis</x-slot>

    <div class="mtn-page mtn-records-page space-y-6">

        <x-ui.hero badge="Cost Monitoring" title="Biaya & Servis Motor"
                    subtitle="Pantau pengeluaran perawatan semua motormu, mulai dari oli, ban, aki, sampai servis rutin, tercatat rapi di satu tempat.">
            <x-slot:side>
                <x-ui.button variant="white" href="{{ route('history.export') }}">Unduh PDF</x-ui.button>
                <form method="GET" action="{{ route('history') }}" class="flex items-center gap-2">
                    <label for="history-period" class="sr-only">Periode pengeluaran</label>
                    <select id="history-period" name="period" class="mtn-input" onchange="this.form.requestSubmit()">
                        <option value="all" @selected($period === 'all')>Semua waktu</option>
                        <option value="7" @selected($period === '7')>7 hari terakhir</option>
                        <option value="30" @selected($period === '30')>30 hari terakhir</option>
                    </select>
                    <noscript><x-ui.button type="submit">Terapkan</x-ui.button></noscript>
                </form>
            </x-slot:side>
        </x-ui.hero>

        {{-- Stat cards --}}
        <div data-reveal-group class="mtn-summary mtn-summary-three">
            <div data-reveal class="mtn-summary-item">
                <div class="size-10 rounded-md bg-white text-primary flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Pengeluaran</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalCost }}">{{ number_format($totalCost) }}</span></p>
            </div>
            <div data-reveal class="mtn-summary-item">
                <div class="size-10 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Pengeluaran Bulan Ini</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $thisMonthCost }}">{{ number_format($thisMonthCost) }}</span></p>
            </div>
            <div data-reveal class="mtn-summary-item">
                <div class="size-10 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Rata-rata / Servis</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $avgCost }}">{{ number_format($avgCost) }}</span></p>
            </div>
        </div>

        <div class="mtn-workspace mtn-history-workspace">
            {{-- Expense table --}}
            <div x-data="{ search: '' }" class="mtn-panel mtn-table-panel">
                <div class="p-5 border-b border-border bg-muted/40 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="mtn-section-title">Riwayat Pengeluaran</h3>
                        <p class="text-xs text-muted-fg mt-0.5">Detail catatan servis motormu.</p>
                    </div>
                    <div class="relative">
                        <x-icon.search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-fg"/>
                        <input type="text" aria-label="Cari riwayat pengeluaran" x-model="search" placeholder="Cari…"
                               class="h-9 w-40 sm:w-48 pl-9 pr-3 rounded-md bg-surface border border-border text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition outline-none">
                    </div>
                </div>

                <div class="overflow-x-auto mtn-history-scroll">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] font-bold text-muted-fg uppercase tracking-widest border-b border-border">
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-5 py-3">Motor</th>
                                <th class="px-5 py-3">Item</th>
                                <th class="px-5 py-3 text-right">KM</th>
                                <th class="px-5 py-3 text-right">Biaya</th>
                                <th class="px-5 py-3">Nota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse ($logs as $log)
                                <tr x-show="search === '' || ($el.textContent || '').toLowerCase().includes(search.toLowerCase())"
                                    class="hover:bg-muted/40 transition">
                                    <td class="px-5 py-3 text-muted-fg tabular-nums whitespace-nowrap">{{ $log->serviced_at->format('d M Y') }}</td>
                                    <td class="px-5 py-3 font-medium text-foreground whitespace-nowrap">{{ $log->item->motorcycle->nickname }}</td>
                                    <td class="px-5 py-3 text-foreground whitespace-nowrap">
                                        {{ $log->item->name }}
                                        @if ($log->workshop_name)
                                            <span class="block text-[11px] text-muted-fg font-normal">{{ $log->workshop_name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-muted-fg tabular-nums whitespace-nowrap">{{ number_format($log->serviced_at_odometer_km) }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-foreground tabular-nums whitespace-nowrap">Rp{{ number_format($log->cost) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($log->receipt_path)
                                            <a href="{{ asset('storage/'.$log->receipt_path) }}" target="_blank" rel="noopener" class="inline-block size-8 rounded-lg overflow-hidden border border-border">
                                                <img src="{{ asset('storage/'.$log->receipt_path) }}" alt="Nota" class="w-full h-full object-cover">
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-muted-fg">Belum ada riwayat servis.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Allocation donut chart --}}
            <div class="mtn-panel mtn-allocation">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="mtn-section-title">Alokasi Pengeluaran</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Berdasarkan jenis perawatan.</p>
                </div>
                <div class="p-5 mtn-allocation-body">
                    @if ($breakdown->isEmpty())
                        <p class="text-sm text-muted-fg text-center py-10">Belum ada data biaya.</p>
                    @else
                        <div id="allocation-chart" class="mtn-allocation-chart" role="img" aria-label="Diagram alokasi pengeluaran per jenis perawatan"></div>
                        <div class="mtn-allocation-details space-y-2">
                            @php $colors = ['#086B59', '#2563EB', '#D97706', '#64748B', '#DC2626']; @endphp
                            @foreach ($breakdown as $name => $cost)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2 text-foreground font-medium">
                                        <span class="size-2.5 rounded-full" style="background:{{ $colors[$loop->index % count($colors)] }}"></span>
                                        {{ $name }}
                                    </span>
                                    <span class="text-muted-fg tabular-nums">Rp{{ number_format($cost) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>


        <div class="mtn-panel mtn-other-expenses-panel">
            <div class="p-5 flex items-center justify-between gap-3 border-b border-border bg-muted/40">
                <h3 class="mtn-section-title">Pengeluaran Lain</h3>
                <x-ui.button variant="outline" type="button" x-data x-on:click="$dispatch('open-modal', 'add-other-expense')">
                    <x-icon.plus class="w-4 h-4" />
                    Tambah
                </x-ui.button>
            </div>
            <div class="p-3 space-y-1 mtn-history-scroll">
                @forelse ($otherExpenses as $expense)
                    <div class="mtn-record flex items-center justify-between gap-4 p-3">
                        <div>
                            <p class="text-sm font-medium text-foreground">{{ $expense->category }} · {{ $expense->motorcycle->nickname }}</p>
                            <p class="text-[11px] text-muted-fg">{{ $expense->expense_date->format('d M Y') }}</p>
                            @if ($expense->note)
                                <p class="text-[11px] text-muted-fg mt-0.5">{{ $expense->note }}</p>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-foreground tabular-nums">Rp{{ number_format($expense->amount) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted-fg p-3">Belum ada pengeluaran lain.</p>
                @endforelse
            </div>
        </div>
        </div>
    </div>

    <x-modal name="add-other-expense" maxWidth="lg" labelledby="add-other-expense-title" focusable>
        <div class="p-6">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-primary">Catatan baru</p>
                    <h2 id="add-other-expense-title" class="mt-1 text-xl font-bold text-foreground">Tambah Pengeluaran Lain</h2>
                    <p class="mt-1 text-sm text-muted-fg">Simpan biaya di luar perawatan motor.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'add-other-expense')" aria-label="Tutup form" class="mtn-icon-action shrink-0">
                    <x-icon.x class="w-6 h-6" aria-hidden="true" />
                </button>
            </div>
            <form method="POST" action="{{ route('other-expenses.store') }}" class="grid gap-4">
                @csrf
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Motor</span>
                    <select name="motorcycle_id" required class="w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (auth()->user()->motorcycles as $m)
                            <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Kategori</span>
                    <select name="category" required class="w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (auth()->user()->expenseCategories()->orderBy('name')->get() as $category)
                            <option value="{{ $category->name }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="amount" label="Jumlah (Rp)" type="number" required />
                <x-ui.input name="expense_date" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Deskripsi (opsional)</span>
                    <textarea name="note" rows="3" maxlength="255" placeholder="mis. Parkir di Mall X, dekat pintu utara"
                              class="w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" type="button" x-on:click="$dispatch('close-modal', 'add-other-expense')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit">Simpan Pengeluaran</x-ui.button>
                </div>
            </form>
        </div>
    </x-modal>

    @if ($breakdown->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
        <script>
            new ApexCharts(document.getElementById('allocation-chart'), {
                series: {!! json_encode($breakdown->values()) !!},
                labels: {!! json_encode($breakdown->keys()) !!},
                chart: { type: 'pie', height: 200, parentHeightOffset: 0 },
                colors: ['#086B59', '#2563EB', '#D97706', '#64748B', '#DC2626'],
                legend: { show: false },
                dataLabels: { enabled: false },
                tooltip: { y: { formatter: (val) => 'Rp' + val.toLocaleString('id-ID') } },
            }).render();
        </script>
    @endif
</x-app-layout>
