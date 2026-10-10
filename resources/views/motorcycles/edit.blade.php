<x-app-layout>
    <x-slot name="header">Edit Motor</x-slot>
    <div class="mtn-page mtn-records-page space-y-6">
        <x-ui.hero title="Edit Motor" subtitle="Identitas, odometer, dan dokumen kendaraan." />
        <div class="mtn-workspace">
        <section class="mtn-panel">
            <h2 class="mtn-section-title mb-5">Identitas kendaraan</h2>
            <form method="POST" action="{{ route('motorcycles.update', $motorcycle) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="mtn-form-grid">
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname', $motorcycle->nickname)" required />
                <x-ui.input name="plat_nomor" label="Plat Nomor" :value="old('plat_nomor', $motorcycle->plat_nomor)" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand', $motorcycle->brand)" />
                <x-ui.input name="model" label="Tipe" :value="old('model', $motorcycle->model)" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year', $motorcycle->year)" />
                <x-ui.input name="initial_odometer_km" label="Odometer awal (km)" type="number" :value="old('initial_odometer_km', $motorcycle->initial_odometer_km)" required />

                </div>

                <div x-data="{ open: true }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="mtn-form-grid mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" :value="old('stnk_due_date', $motorcycle->stnk_due_date?->toDateString())" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" :value="old('plat_due_date', $motorcycle->plat_due_date?->toDateString())" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" :value="old('insurance_due_date', $motorcycle->insurance_due_date?->toDateString())" />
                    </div>
                </div>

                <x-ui.button variant="primary" type="submit" class="w-full">Simpan Perubahan</x-ui.button>
            </form>
        </section>
        <aside class="mtn-context space-y-5">
            <div><h2 class="mtn-section-title">Catatan kendaraan</h2><p class="text-sm text-muted-fg mt-2">Gunakan nama yang mudah dikenali untuk membedakan catatan setiap motor.</p></div>
            <div><h3 class="mtn-section-title">Odometer</h3><p class="text-sm text-muted-fg mt-2">Masukkan angka kilometer dari panel motor. Catatan ini menjadi acuan perawatan dan perjalanan.</p></div>
            <div><h3 class="mtn-section-title">Dokumen</h3><p class="text-sm text-muted-fg mt-2">Tambahkan tanggal jatuh tempo untuk memantau pajak, plat, dan asuransi.</p></div>
            <x-ui.button variant="outline" href="{{ route('motorcycles.index') }}">Kembali ke Motor Saya</x-ui.button>
        </aside>
        </div>
    </div>
</x-app-layout>
