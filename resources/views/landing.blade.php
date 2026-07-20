<x-marketing-layout>

    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 md:pt-40 md:pb-28">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-hero/10 pointer-events-none"></div>
        <div data-parallax class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>
        <div data-parallax class="absolute top-40 -left-24 w-72 h-72 rounded-full bg-hero/10 blur-3xl pointer-events-none"></div>

        <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-2 gap-12 items-center relative">
            <div>
                <h1 data-reveal class="text-4xl md:text-5xl font-heading font-bold leading-tight text-foreground">
                    Rawat motor tanpa lupa,<br class="hidden md:block"> berbasis <span class="text-primary">km yang benar-benar akurat</span>.
                </h1>
                <p data-reveal class="mt-5 text-lg text-muted-fg max-w-lg">
                    Amicta mencatat jarak tempuh motormu dari sumber mana saja — manual, isi bensin, servis, atau riding — lalu otomatis mengingatkan kapan oli, ban, aki, atau servis rutin perlu diganti.
                </p>
                <div data-reveal class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">
                        <x-icon.play class="w-4 h-4"/> Mulai Gratis
                    </x-ui.button>
                    <x-ui.button variant="outline" size="lg" href="#fitur">Lihat Fitur</x-ui.button>
                </div>
            </div>

            <div data-reveal class="relative">
                <x-ui.card class="shadow-lift">
                    <div class="flex items-center justify-between mb-4">
                        <p class="font-heading font-semibold">Beat Ilyas</p>
                        <x-ui.badge variant="yellow">Perlu Perhatian</x-ui.badge>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>2.650 / 2.500 km</span></div>
                            <x-ui.progress :percent="106" color="red"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>10.450 / 12.000 km</span></div>
                            <x-ui.progress :percent="87" color="yellow"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>11.950 / 15.000 km</span></div>
                            <x-ui.progress :percent="80" color="yellow"/>
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </section>

    {{-- Problem --}}
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">
            Masalah yang setiap pengendara motor kenal
        </h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-6">
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.bell class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Lupa ganti oli</p>
                <p data-reveal class="text-sm text-muted-fg">Patokannya cuma perasaan atau waktu, bukan jarak tempuh riil.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.gauge class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Odometer jarang dicek</p>
                <p data-reveal class="text-sm text-muted-fg">Harus buka motor & baca angka manual tiap mau tahu status.</p>
            </x-ui.card>
            <x-ui.card>
                <div data-reveal class="w-11 h-11 rounded-token bg-accent/10 text-accent flex items-center justify-center mb-4">
                    <x-icon.wrench class="w-6 h-6"/>
                </div>
                <p data-reveal class="font-heading font-semibold mb-1">Tidak ada riwayat servis</p>
                <p data-reveal class="text-sm text-muted-fg">Motor dengan riwayat servis lengkap dan tercatat rapi punya nilai jual lebih tinggi saat dijual — tapi kebanyakan orang gak pernah mencatatnya dari awal.</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="bg-primary text-white py-14">
        <div data-reveal-group class="max-w-6xl mx-auto px-4 grid grid-cols-3 gap-6 text-center">
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="4">0</span></p>
                <p class="text-sm text-white/80 mt-1">Sumber pencatatan km</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="9">0</span></p>
                <p class="text-sm text-white/80 mt-1">Modul lengkap dalam satu aplikasi</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="0">0</span></p>
                <p class="text-sm text-white/80 mt-1">Tebak-tebakan jadwal servis</p>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="fitur" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-4">Semua yang kamu butuh, satu aplikasi</h2>
        <p data-reveal class="text-center text-muted-fg max-w-xl mx-auto mb-12">Empat pilar yang menyelesaikan masalah nyata pemilik motor — bukan sekadar pengingat.</p>
        <div data-reveal-group class="grid md:grid-cols-2 gap-6">
            @php
                $pillars = [
                    [
                        'icon' => 'gauge',
                        'title' => 'Pantau Kondisi Motor',
                        'summary' => 'Odometer akurat dari sumber mana saja, status warna tiap komponen, dan skor kesehatan motor dalam satu angka.',
                        'pills' => ['Odometer Backbone', 'Status Warna', 'Skor Kesehatan'],
                        'points' => [
                            'Odometer Backbone — km selalu update dari input manual, isi bensin, servis, atau riding, satu sumber kebenaran.',
                            'Status warna per komponen (hijau/kuning/merah) untuk oli, ban, aki, servis rutin.',
                            'Prediksi hari tersisa sebelum servis, berbasis rata-rata jarak harianmu.',
                            'Skor Kesehatan Motor 0-100, ringkasan sekali lihat.',
                            'Kelola beberapa motor sekaligus dalam satu akun.',
                        ],
                    ],
                    [
                        'icon' => 'bell',
                        'title' => 'Jangan Ada yang Kelewat',
                        'summary' => 'Semua yang butuh perhatianmu — servis, dokumen, sampai efisiensi BBM yang aneh — muncul di satu tempat.',
                        'pills' => ['Pusat Perhatian', 'Dokumen Kendaraan', 'Efisiensi BBM'],
                        'points' => [
                            'Pusat Perhatian menyatukan semua pengingat jadi satu daftar prioritas.',
                            'Reminder jatuh tempo Pajak STNK, Ganti Plat 5 Tahun, dan Asuransi.',
                            'Peringatan otomatis kalau efisiensi BBM tercatat gak masuk akal (indikasi salah input).',
                        ],
                    ],
                    [
                        'icon' => 'wallet',
                        'title' => 'Kontrol Biaya Penuh',
                        'summary' => 'Dari isi bensin sampai premi asuransi tahunan, semua pengeluaran motor kecatat dan terlihat totalnya.',
                        'pills' => ['BBM & Efisiensi', 'Riwayat Servis', 'Laporan TCO'],
                        'points' => [
                            'Catat isi bensin, hitung efisiensi km/liter otomatis.',
                            'Riwayat servis lengkap dengan nama bengkel, part yang diganti, dan foto nota.',
                            'Pengeluaran Lain — asuransi, parkir, cuci motor, aksesoris, dll.',
                            'Laporan Biaya Kepemilikan (TCO): total, biaya per km, tren bulanan.',
                        ],
                    ],
                    [
                        'icon' => 'route',
                        'title' => 'Riding & Peta Pribadi',
                        'summary' => 'Rekam perjalananmu lewat GPS dan tandai titik-titik penting di peta pribadimu.',
                        'pills' => ['GPS Trip', 'Peta Rute', 'Peta Titik'],
                        'points' => [
                            'Trip recording GPS — nyalakan sebelum jalan, jarak terhitung otomatis.',
                            'Peta rute — lihat kembali jalur yang pernah dilalui.',
                            'Peta titik — tandai lokasi penting (bengkel langganan, jalan rawan, dll).',
                            'Peta rencana — rencanakan rute sebelum berangkat.',
                        ],
                    ],
                ];
            @endphp
            @foreach ($pillars as $pillar)
                <div data-reveal x-data="{ open: false }">
                    <x-ui.card>
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 shrink-0 rounded-token bg-primary/10 text-primary flex items-center justify-center">
                                <x-dynamic-component :component="'icon.'.$pillar['icon']" class="w-6 h-6"/>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-heading font-semibold mb-1">{{ $pillar['title'] }}</p>
                                <p class="text-sm text-muted-fg mb-3">{{ $pillar['summary'] }}</p>
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    @foreach ($pillar['pills'] as $pill)
                                        <x-ui.badge variant="neutral">{{ $pill }}</x-ui.badge>
                                    @endforeach
                                </div>
                                <button type="button" @click="open = !open" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                                    <span x-text="open ? 'Sembunyikan detail' : 'Lihat detail'"></span>
                                    <x-icon.chevron-down class="w-4 h-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''"/>
                                </button>
                                <ul x-show="open" x-cloak class="mt-3 space-y-2">
                                    @foreach ($pillar['points'] as $point)
                                        <li class="flex items-start gap-2 text-sm text-muted-fg">
                                            <x-icon.check class="w-4 h-4 text-status-green shrink-0 mt-0.5"/>
                                            <span>{{ $point }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </x-ui.card>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Dashboard Preview --}}
    <section class="bg-muted py-16 md:py-24">
        <div class="max-w-6xl mx-auto px-4 grid lg:grid-cols-5 gap-12 items-center">
            <div data-reveal class="lg:col-span-2">
                <h2 class="text-2xl md:text-3xl font-heading font-bold mb-4">Lihat sendiri tampilannya</h2>
                <ul class="space-y-4 mb-8">
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Status warna tiap komponen, sekali lihat langsung paham.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Skor Kesehatan Motor merangkum semua jadi satu angka.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <x-icon.check class="w-5 h-5 text-status-green shrink-0 mt-0.5"/>
                        <span class="text-sm text-muted-fg">Pusat Perhatian ngasih tau kalau ada yang mendesak.</span>
                    </li>
                </ul>
                <x-ui.button variant="primary" size="lg" href="{{ route('register') }}">Coba Sekarang</x-ui.button>
            </div>

            <div data-reveal class="lg:col-span-3" x-data="{ motor: 'beat' }">
                <div class="bg-surface border border-border rounded-2xl shadow-lift overflow-hidden">
                    <div class="border-b border-border px-5 py-3 flex items-center justify-between">
                        <div class="flex gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                        </div>
                        <div class="flex gap-1 bg-muted p-1 rounded-lg">
                            <button type="button" @click="motor = 'beat'" :class="motor === 'beat' ? 'bg-surface shadow-sm text-primary' : 'text-muted-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">Beat Ilyas</button>
                            <button type="button" @click="motor = 'nmax'" :class="motor === 'nmax' ? 'bg-surface shadow-sm text-primary' : 'text-muted-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">NMAX Kantor</button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div x-show="motor === 'beat'">
                            <div class="flex items-center justify-between mb-4">
                                <p class="font-heading font-semibold">Beat Ilyas</p>
                                <x-ui.badge variant="yellow">Perhatian &middot; Skor 75</x-ui.badge>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>106%</span></div>
                                    <x-ui.progress :percent="106" color="red"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>87%</span></div>
                                    <x-ui.progress :percent="87" color="yellow"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>80%</span></div>
                                    <x-ui.progress :percent="80" color="yellow"/>
                                </div>
                            </div>
                        </div>
                        <div x-show="motor === 'nmax'" x-cloak>
                            <div class="flex items-center justify-between mb-4">
                                <p class="font-heading font-semibold">NMAX Kantor</p>
                                <x-ui.badge variant="green">Aman &middot; Skor 100</x-ui.badge>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>16%</span></div>
                                    <x-ui.progress :percent="16" color="green"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>51%</span></div>
                                    <x-ui.progress :percent="51" color="yellow"/>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>41%</span></div>
                                    <x-ui.progress :percent="41" color="green"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="cara-kerja" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Cara kerjanya</h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['n' => '1', 't' => 'Daftar motor', 'd' => 'Masukkan data motor & odometer awal. Motor bekas? Isi form Riwayat Awal sekali saja biar prediksi langsung akurat.'],
                ['n' => '2', 't' => 'Catat km dari mana saja', 'd' => 'Manual, pas isi bensin, pas servis, atau nyalakan GPS pas riding — bebas pilih, semua otomatis nyambung.'],
                ['n' => '3', 't' => 'Amicta yang mantau', 'd' => 'Status warna, skor kesehatan, dan Pusat Perhatian otomatis update, kamu tinggal cek kalau ada notifikasi.'],
            ] as $step)
                <div data-reveal class="text-center">
                    <div class="w-12 h-12 rounded-full bg-primary text-white font-heading font-bold flex items-center justify-center mx-auto mb-4">
                        {{ $step['n'] }}
                    </div>
                    <p class="font-heading font-semibold mb-1">{{ $step['t'] }}</p>
                    <p class="text-sm text-muted-fg">{{ $step['d'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="max-w-3xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Pertanyaan yang sering ditanyakan</h2>
        <div data-reveal-group class="space-y-3" x-data="{ open: null }">
            @php
                $faqs = [
                    ['q' => 'Apakah saya harus selalu pakai GPS?', 'a' => 'Tidak. GPS cuma salah satu dari 4 cara mencatat km — manual, isi bensin, dan servis juga otomatis update odometer.'],
                    ['q' => 'Bisa buat lebih dari satu motor?', 'a' => 'Bisa, kelola semua motor dalam satu akun, gampang pindah motor aktif.'],
                    ['q' => 'Amicta gratis?', 'a' => 'Gratis, tanpa kartu kredit, daftar langsung bisa dipakai.'],
                    ['q' => 'Motor saya bekas, riwayat servisnya udah lama, gimana?', 'a' => 'Ada form "Riwayat Awal" opsional pas daftar motor — isi terakhir ganti oli/ban/aki/servis di km berapa, prediksi langsung akurat dari hari pertama.'],
                    ['q' => 'Data saya aman?', 'a' => 'Data motor & riwayatnya cuma bisa diakses dari akunmu sendiri, gak dibagikan ke pihak lain.'],
                ];
            @endphp
            @foreach ($faqs as $i => $faq)
                <div data-reveal class="bg-surface border border-border rounded-2xl overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full flex items-center justify-between gap-4 p-5 text-left">
                        <span class="font-heading font-semibold text-sm">{{ $faq['q'] }}</span>
                        <x-icon.chevron-down class="w-4 h-4 text-muted-fg shrink-0 transition-transform" x-bind:class="open === {{ $i }} ? 'rotate-180' : ''"/>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-5 pb-5 text-sm text-muted-fg">
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="max-w-6xl mx-auto px-4 pb-24">
        <div data-reveal class="bg-hero rounded-token px-8 py-14 text-center text-white">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-3">Siap motor kamu selalu prima?</h2>
            <p class="text-white/85 mb-8 max-w-md mx-auto">Gratis, tanpa kartu kredit. Daftar sekarang dan tambahkan motor pertamamu.</p>
            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">Buat Akun Gratis</x-ui.button>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mt-8 text-xs font-medium text-white/70">
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Gratis selamanya</span>
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Tanpa kartu kredit</span>
                <span class="flex items-center gap-1.5"><x-icon.check class="w-3.5 h-3.5"/> Setup di bawah 2 menit</span>
            </div>
        </div>
    </section>

</x-marketing-layout>
