<x-app-layout>
    <x-slot name="header">Tambah Motor</x-slot>
    <div class="max-w-lg mx-auto p-4 sm:p-6 lg:p-8">
        <x-ui.card>
            <form method="POST" action="{{ route('motorcycles.store') }}" class="space-y-5">
                @csrf
                <x-ui.input name="nickname" label="Nama/Nickname" :value="old('nickname')" placeholder="Beat Merah" required />
                <x-ui.input name="plat_nomor" label="Plat Nomor" :value="old('plat_nomor')" placeholder="B 3421 XYZ" required />
                <x-ui.input name="brand" label="Merk" :value="old('brand')" placeholder="Honda" />
                <x-ui.input name="model" label="Tipe" :value="old('model')" placeholder="Beat" />
                <x-ui.input name="year" label="Tahun" type="number" :value="old('year')" placeholder="2022" />
                <x-ui.input name="initial_odometer_km" label="Odometer saat ini (km)" type="number" :value="old('initial_odometer_km', 0)" required />

                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Riwayat Awal (opsional) — motor bekas?
                    </button>
                    <p x-show="!open" class="text-xs text-muted-fg mt-1">Kosongkan kalau motor baru / belum pernah diservis.</p>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-2 gap-4 mt-3">
                        <x-ui.input name="oli_last_km" label="Oli terakhir diganti di km" type="number" placeholder="cth. 10500" />
                        <x-ui.input name="ban_last_km" label="Ban terakhir diganti di km" type="number" placeholder="cth. 3000" />
                        <x-ui.input name="aki_last_km" label="Aki terakhir diganti di km" type="number" placeholder="cth. 500" />
                        <x-ui.input name="servis_last_km" label="Servis rutin terakhir di km" type="number" placeholder="cth. 9000" />
                    </div>
                </div>

                <div x-data="{ open: false }" class="border-t border-border pt-4">
                    <button type="button" @click="open = !open" class="text-sm text-primary font-medium hover:underline">
                        Dokumen Kendaraan (opsional)
                    </button>
                    <div x-show="open" x-cloak class="grid sm:grid-cols-3 gap-4 mt-3">
                        <x-ui.input name="stnk_due_date" label="Jatuh Tempo Pajak STNK" type="date" />
                        <x-ui.input name="plat_due_date" label="Jatuh Tempo Ganti Plat (5th)" type="date" />
                        <x-ui.input name="insurance_due_date" label="Jatuh Tempo Asuransi" type="date" />
                    </div>
                </div>

                <x-ui.button variant="primary" type="submit" class="w-full">Simpan</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
