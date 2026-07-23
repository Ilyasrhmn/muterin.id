<x-app-layout>
    <x-slot name="header">Biaya & Servis</x-slot>

    <div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <x-ui.hero badge="Cost Monitoring" title="Biaya & Servis Motor"
                    subtitle="Pantau pengeluaran perawatan semua motormu  oli, ban, aki, servis rutin  tercatat rapi di satu tempat.">
            <x-slot:side>
                <x-ui.button variant="white" href="{{ route('history.export') }}">Unduh PDF</x-ui.button>
                <x-ui.button variant="white" type="button" x-data @click="$dispatch('open-expense-form')">Catat Pengeluaran Lain</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        {{-- Stat cards --}}
        <div data-reveal-group class="grid sm:grid-cols-3 gap-4">
            <div data-reveal class="bg-primary-soft border border-primary/15 rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-white text-primary flex items-center justify-center mb-4">
                    <x-icon.wallet class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Total Pengeluaran</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $totalCost }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Pengeluaran Bulan Ini</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $thisMonthCost }}">0</span></p>
            </div>
            <div data-reveal class="bg-surface border border-border rounded-2xl p-5">
                <div class="size-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-5 h-5"/>
                </div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Rata-rata / Servis</p>
                <p class="text-2xl font-heading font-extrabold text-foreground mt-1 tracking-tight">Rp <span data-countup="{{ $avgCost }}">0</span></p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            {{-- Allocation donut chart --}}
            <div class="bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40">
                    <h3 class="font-heading font-bold text-foreground text-sm">Alokasi Pengeluaran</h3>
                    <p class="text-xs text-muted-fg mt-0.5">Berdasarkan jenis perawatan.</p>
                </div>
                <div class="p-5">
                    @if ($breakdown->isEmpty())
                        <p class="text-sm text-muted-fg text-center py-10">Belum ada data biaya.</p>
                    @else
                        <canvas id="allocation-chart" height="220" role="img" aria-label="Diagram alokasi pengeluaran per jenis perawatan"></canvas>
                        <div class="mt-4 space-y-2">
                            @php $colors = ['#0F766E', '#2563EB', '#D97706', '#64748B', '#DC2626']; @endphp
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

            {{-- Expense table --}}
            <div x-data="{ search: '' }" class="lg:col-span-2 bg-surface border border-border rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-border bg-muted/40 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-heading font-bold text-foreground text-sm">Riwayat Pengeluaran</h3>
                        <p class="text-xs text-muted-fg mt-0.5">Detail catatan servis motormu.</p>
                    </div>
                    <div class="relative">
                        <x-icon.search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-fg"/>
                        <input type="text" x-model="search" placeholder="Cari…"
                               class="h-9 w-40 sm:w-48 pl-9 pr-3 rounded-full bg-surface border border-border text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition outline-none">
                    </div>
                </div>

                <div class="overflow-x-auto">
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
        </div>

        <div x-data="{ open: false }" @open-expense-form.window="open = true" x-cloak class="bg-surface border border-border rounded-2xl overflow-hidden">
            <button @click="open = !open" type="button" class="w-full p-5 flex items-center justify-between text-left border-b border-border bg-muted/40">
                <h3 class="font-heading font-bold text-foreground text-sm">Pengeluaran Lain</h3>
                <span class="text-primary text-sm font-semibold" x-text="open ? 'Tutup' : 'Tambah'"></span>
            </button>
            <form x-show="open" method="POST" action="{{ route('other-expenses.store') }}" class="p-5 grid sm:grid-cols-2 gap-4 border-b border-border">
                @csrf
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Motor</span>
                    <select name="motorcycle_id" required class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (auth()->user()->motorcycles as $m)
                            <option value="{{ $m->id }}" @selected($m->is_active)>{{ $m->nickname }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Kategori</span>
                    <select name="category" required class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach (\App\Models\OtherExpense::CATEGORY_LABELS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="amount" label="Jumlah (Rp)" type="number" required />
                <x-ui.input name="expense_date" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <div class="sm:col-span-2">
                    <x-ui.button variant="primary" type="submit">Simpan</x-ui.button>
                </div>
            </form>
            <div class="p-3 space-y-1 max-h-72 overflow-y-auto">
                @forelse ($otherExpenses as $expense)
                    <div class="flex items-center justify-between p-3 rounded-xl hover:bg-muted/40">
                        <div>
                            <p class="text-sm font-medium text-foreground">{{ \App\Models\OtherExpense::CATEGORY_LABELS[$expense->category] }}  {{ $expense->motorcycle->nickname }}</p>
                            <p class="text-[11px] text-muted-fg">{{ $expense->expense_date->format('d M Y') }}</p>
                        </div>
                        <span class="text-sm font-bold text-foreground tabular-nums">Rp{{ number_format($expense->amount) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted-fg p-3">Belum ada pengeluaran lain.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($breakdown->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('allocation-chart'), {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($breakdown->keys()) !!},
                    datasets: [{
                        data: {!! json_encode($breakdown->values()) !!},
                        backgroundColor: ['#0F766E', '#2563EB', '#D97706', '#64748B', '#DC2626'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    cutout: '68%',
                    plugins: { legend: { display: false } },
                },
            });
        </script>
    @endif
</x-app-layout>
