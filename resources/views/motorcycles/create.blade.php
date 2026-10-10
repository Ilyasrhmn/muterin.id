<x-app-layout>
    <x-slot name="header">Tambah Motor</x-slot>
    <div class="mtn-page mtn-records-page space-y-6">
        <x-ui.hero title="Tambah Motor" subtitle="Identitas, odometer, dan dokumen kendaraan." />
        <div class="mtn-workspace">
        <section class="mtn-panel">
            <h2 class="mtn-section-title mb-5">Identitas kendaraan</h2>
            <form method="POST" action="{{ route('motorcycles.store') }}" class="space-y-5">
                @csrf
                <div class="mtn-form-grid">
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname')" placeholder="Beat Merah" required />
                <x-ui.input name="plat_nomor" label="Plat Nomor" :value="old('plat_nomor')" placeholder="B 3421 XYZ" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand')" placeholder="Honda" />
                <x-ui.input name="model" label="Tipe" :value="old('model')" placeholder="Beat" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year')" placeholder="2022" />
                <x-ui.input name="initial_odometer_km" label="Odometer saat ini (km)" type="number" :value="old('initial_odometer_km', 0)" required />

                </div>

                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="text-sm text-primary font-medium hover:underline">
                        Riwayat Awal (opsional)  motor bekas?
                    </button>
                    <p x-show="!open" class="text-xs text-muted-fg mt-1">Kosongkan kalau motor baru / belum pernah diservis.</p>
                    <div x-show="open" x-cloak class="mtn-form-grid mt-3">
                        <x-ui.input name="oli_last_km" label="Oli terakhir diganti di km" type="number" placeholder="cth. 10500" />
                        <x-ui.input name="ban_last_km" label="Ban terakhir diganti di km" type="number" placeholder="cth. 3000" />
                        <x-ui.input name="aki_last_km" label="Aki terakhir diganti di km" type="number" placeholder="cth. 500" />
                        <x-ui.input name="servis_last_km" label="Servis rutin terakhir di km" type="number" placeholder="cth. 9000" />
                    </div>
                </div>

                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="mtn-form-grid mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" />
                    </div>
                </div>

                <x-ui.button variant="primary" type="submit" class="w-full">Simpan</x-ui.button>
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
