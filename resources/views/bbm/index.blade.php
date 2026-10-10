<x-app-layout>
    <x-slot name="header">BBM</x-slot>

    <div class="mtn-page mtn-records-page space-y-6">

        <x-ui.hero badge="{{ $logs->total() }} catatan isi" title="Manajemen BBM"
                    subtitle="Catat tiap isi bensin untuk tahu konsumsi (km/liter) dan biaya per km motormu.">
            <x-slot:side>
                <x-ui.button type="button" x-data @click="$dispatch('open-modal', 'add-fuel')"><x-icon.plus class="w-5 h-5"/>Tambah catatan BBM</x-ui.button>
            </x-slot:side>
        </x-ui.hero>

        <div class="space-y-6">
        <section>
        {{-- History table --}}
        <div class="mtn-panel mtn-table-panel">
            <div class="p-5 border-b border-border bg-muted/40">
                <h3 class="mtn-section-title">Riwayat Isi Bensin</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-bold text-muted-fg uppercase tracking-widest border-b border-border">
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Motor</th>
                            <th class="px-5 py-3 text-right">Odometer</th>
                            <th class="px-5 py-3 text-right">Liter</th>
                            <th class="px-5 py-3 text-right">Biaya</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-muted/40 transition">
                                <td class="px-5 py-3 text-muted-fg tabular-nums whitespace-nowrap">{{ $log->filled_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 font-medium text-foreground whitespace-nowrap">{{ $log->motorcycle->nickname }}</td>
                                <td class="px-5 py-3 text-right text-muted-fg tabular-nums">{{ number_format($log->odometer_km) }}</td>
                                <td class="px-5 py-3 text-right text-muted-fg tabular-nums">{{ $log->liters }}</td>
                                <td class="px-5 py-3 text-right font-bold text-foreground tabular-nums">Rp{{ number_format($log->total_cost) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('bbm.destroy', $log) }}" data-confirm="Hapus catatan ini?">
                                        @csrf @method('DELETE')
                                        <button type="submit" aria-label="Hapus catatan isi bensin" class="p-1.5 rounded-lg text-muted-fg hover:text-accent hover:bg-accent/10 transition">
                                            <x-icon.trash class="w-4 h-4"/>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-muted-fg">Belum ada catatan isi bensin.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :items="$logs" />
        </div>        </section>
        <aside class="space-y-6">
<section class="mtn-context"><h2 class="mtn-section-title">Efisiensi per motor</h2>        {{-- Per-motor efficiency stats --}}
        <div data-reveal-group class="mtn-efficiency-list">
            @forelse ($motorStats as $ms)
                <div data-reveal class="mtn-record py-4">
                    <p class="font-heading font-bold text-foreground text-sm mb-3">{{ $ms['motor']->nickname }}</p>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.15em]">Rata-rata</p>
                            <p class="text-xl font-heading font-extrabold text-foreground tabular-nums">
                                {{ $ms['avg_km_per_liter'] ?? '—' }}<span class="text-sm font-normal text-muted-fg">{{ $ms['avg_km_per_liter'] ? ' km/l' : '' }}</span>
                            </p>
                        </div>
                        <div class="size-10 rounded-md bg-primary-soft text-primary flex items-center justify-center">
                            <x-icon.droplet class="w-5 h-5"/>
                        </div>
                    </div>
                    <p class="text-xs text-muted-fg mt-3 tabular-nums">
                        Biaya/km: {{ $ms['cost_per_km'] ? 'Rp'.number_format($ms['cost_per_km']) : '—' }}
                    </p>
                    @if (!$ms['avg_km_per_liter'])
                        <p class="text-[11px] text-muted-fg mt-1">Butuh minimal 2x isi tank penuh untuk hitung efisiensi.</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-muted-fg col-span-full">Belum ada motor.</p>
            @endforelse
        </div>

</section>
        </aside>
        </div>
    </div>
    <x-modal name="add-fuel" :show="$errors->any() && old('_form') === 'add-fuel'" maxWidth="2xl" labelledby="add-fuel-title" focusable>
        <div class="p-6">
            <div class="flex items-center justify-between gap-4"><h2 id="add-fuel-title" class="mtn-section-title">Tambah catatan BBM</h2><button type="button" @click="$dispatch('close')" class="mtn-icon-action" aria-label="Tutup formulir BBM"><x-icon.x class="w-5 h-5"/></button></div>
            <form method="POST" action="{{ route('bbm.store') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="_form" value="add-fuel">
                <div class="mtn-form-grid">
                <label class="space-y-1.5">
                    <span class="block text-sm font-medium text-foreground">Motor</span>
                    <select name="motorcycle_id" required class="w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        @foreach ($motorcycles as $m)
                            <option value="{{ $m->id }}" @selected(old('motorcycle_id', $motorcycles->firstWhere('is_active', true)?->id ?? $motorcycles->first()?->id) == $m->id)>{{ $m->nickname }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input name="filled_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-ui.input name="odometer_km" label="Odometer saat isi (km)" type="number" required />
                <x-ui.input name="liters" label="Jumlah liter" type="number" step="0.1" required />
                <x-ui.input name="total_cost" label="Total biaya (Rp)" type="number" required />
                </div>
                <input type="hidden" name="is_full_tank" value="0">
                <label class="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" name="is_full_tank" value="1" @checked(old('is_full_tank', 1)) class="rounded border-border text-primary focus:ring-primary/30">
                    Isi tank penuh (full tank)
                </label>
                <div class="flex justify-end gap-3">
                    <x-ui.button variant="outline" type="button" @click="$dispatch('close')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit"><x-icon.check class="w-4 h-4"/>Simpan catatan</x-ui.button>
                </div>
            </form>
        </div>
    </x-modal>
    <script>
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (form.dataset.confirmed === 'yes') return;
                e.preventDefault();
                window.MuterinDialog.confirm(form.dataset.confirm, { danger: true, confirmText: 'Hapus' }).then((ok) => {
                    if (ok) {
                        form.dataset.confirmed = 'yes';
                        form.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>
