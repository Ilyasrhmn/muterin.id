<x-marketing-layout>

    {{-- Hero --}}
    <section class="relative overflow-hidden pt-32 pb-20 md:pt-40 md:pb-28">
        <div data-parallax class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>
        <div data-parallax class="absolute top-40 -left-24 w-72 h-72 rounded-full bg-accent/10 blur-3xl pointer-events-none"></div>

        <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-2 gap-12 items-center relative">
            <div>
                <h1 data-reveal class="text-4xl md:text-5xl font-heading font-bold leading-tight text-foreground">
                    Rawat motor tanpa lupa,<br class="hidden md:block"> berbasis <span class="text-primary">jarak tempuh asli</span>.
                </h1>
                <p data-reveal class="mt-5 text-lg text-muted-fg max-w-lg">
                    Amicta merekam perjalananmu lewat GPS dan otomatis mengingatkan kapan oli, ban, aki, atau servis rutin motor perlu diganti — bukan tebak-tebakan.
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
                        <p class="font-heading font-semibold">Beat Merah</p>
                        <x-ui.badge variant="yellow">Mendekati batas</x-ui.badge>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Oli Mesin</span><span>2.100 / 2.500 km</span></div>
                            <x-ui.progress :percent="84" color="yellow"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Ban</span><span>4.200 / 12.000 km</span></div>
                            <x-ui.progress :percent="35" color="green"/>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span>Aki</span><span>3.000 / 15.000 km</span></div>
                            <x-ui.progress :percent="20" color="green"/>
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
                <p data-reveal class="text-sm text-muted-fg">Sulit dilacak kapan terakhir ganti apa, di km berapa, biayanya.</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Stats strip --}}
    <section class="bg-primary text-white py-14">
        <div data-reveal-group class="max-w-6xl mx-auto px-4 grid grid-cols-3 gap-6 text-center">
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="4">0</span></p>
                <p class="text-sm text-white/80 mt-1">Komponen dipantau</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="100">0</span>%</p>
                <p class="text-sm text-white/80 mt-1">Berbasis km asli via GPS</p>
            </div>
            <div data-reveal>
                <p class="text-4xl font-heading font-bold"><span data-countup="0">0</span></p>
                <p class="text-sm text-white/80 mt-1">Odometer manual dicatat</p>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="fitur" class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-4">Semua yang kamu butuh, satu aplikasi</h2>
        <p data-reveal class="text-center text-muted-fg max-w-xl mx-auto mb-12">Dari pencatatan otomatis sampai peta rute pribadi.</p>
        <div data-reveal-group class="grid md:grid-cols-3 gap-6">
            @php
                $features = [
                    ['icon' => 'gauge', 'title' => 'Trip Recording GPS', 'desc' => 'Nyalakan sebelum jalan, jarak tempuh terhitung otomatis lewat GPS.'],
                    ['icon' => 'wrench', 'title' => 'Status Warna Perawatan', 'desc' => 'Hijau, kuning, merah — tahu kapan harus servis sekali lihat.'],
                    ['icon' => 'motorcycle', 'title' => 'Multi-Motor', 'desc' => 'Kelola beberapa motor sekaligus dalam satu akun.'],
                    ['icon' => 'wallet', 'title' => 'Catatan Biaya', 'desc' => 'Setiap servis bisa dicatat biayanya, terlihat total per motor.'],
                    ['icon' => 'map-pin', 'title' => 'Peta Pribadi', 'desc' => 'Tandai jalan rawan, sepi, atau momen perjalananmu di peta.'],
                    ['icon' => 'bell', 'title' => 'Pengingat Otomatis', 'desc' => 'Notifikasi begitu status mendekati atau melewati batas aman.'],
                ];
            @endphp
            @foreach ($features as $f)
                <x-ui.card hover data-reveal>
                    <div class="w-11 h-11 rounded-token bg-primary/10 text-primary flex items-center justify-center mb-4">
                        <x-dynamic-component :component="'icon.'.$f['icon']" class="w-6 h-6"/>
                    </div>
                    <p class="font-heading font-semibold mb-1">{{ $f['title'] }}</p>
                    <p class="text-sm text-muted-fg">{{ $f['desc'] }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="max-w-6xl mx-auto px-4 py-16 md:py-24">
        <h2 data-reveal class="text-2xl md:text-3xl font-heading font-bold text-center mb-12">Cara kerjanya</h2>
        <div data-reveal-group class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['n' => '1', 't' => 'Daftar motor', 'd' => 'Masukkan data motor & odometer awal, sekali saja.'],
                ['n' => '2', 't' => 'Nyalakan saat riding', 'd' => 'Tekan mulai sebelum jalan, selesai saat sampai.'],
                ['n' => '3', 't' => 'Dapat pengingat', 'd' => 'Amicta kasih tahu kapan waktunya servis.'],
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

    {{-- CTA --}}
    <section class="max-w-6xl mx-auto px-4 pb-24">
        <div data-reveal class="bg-primary rounded-token px-8 py-14 text-center text-white">
            <h2 class="text-2xl md:text-3xl font-heading font-bold mb-3">Siap motor kamu selalu prima?</h2>
            <p class="text-white/85 mb-8 max-w-md mx-auto">Gratis, tanpa kartu kredit. Daftar sekarang dan tambahkan motor pertamamu.</p>
            <x-ui.button variant="accent" size="lg" href="{{ route('register') }}">Buat Akun Gratis</x-ui.button>
        </div>
    </section>

</x-marketing-layout>
