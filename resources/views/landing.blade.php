<x-marketing-layout>
    <section class="lp-wrap lp-hero" aria-labelledby="lp-title">
        <div class="lp-hero-copy">
            <p class="lp-kicker">Buku servis digital untuk motor sehari-hari</p>
            <h1 id="lp-title">Jalan terus.<br><span>Rawat tepat waktunya.</span></h1>
            <p class="lp-lead">Muterin mencatat km yang benar-benar akurat dari manual, isi bensin, servis, atau riding. Jadwal perawatan, riwayat, dan biaya motormu tersimpan di satu tempat.</p>
            <div class="lp-actions"><a class="lp-button" href="{{ route('subscription') }}">Lihat paket Muterin</a><a class="lp-text-link" href="#cara-kerja">Lihat cara kerjanya</a></div>
            <p class="lp-note">Pilihan Gratis, Pro Bulanan, dan Pro Tahunan. Trial 7 hari masih dalam pengembangan.</p>
        </div>
        <figure class="lp-book">
            <figcaption class="lp-book-caption">Lihat sendiri tampilannya <span>Demo · data contoh</span></figcaption>
            <div class="lp-book-page">
                <div class="lp-book-heading"><span>Buku servis / 01</span><strong>Beat Street</strong></div>
                <p class="lp-note">Odometer saat ini</p><p class="lp-odometer">6.400 <span>km</span></p>
                <div class="lp-service"><span>Servis berikutnya · Oli mesin</span><strong>350 <small>km lagi</small></strong><p>Siapkan jadwal bengkel sebelum waktunya tiba.</p></div>
                <dl class="lp-demo-list"><div><dt>Oli mesin</dt><dd>Segera dijadwalkan</dd></div><div><dt>Ban &amp; aki</dt><dd>Pantau kondisi</dd></div><div><dt>Skor kesehatan contoh</dt><dd>Skor 75</dd></div></dl>
                <details class="lp-demo-details"><summary>Lihat motor kedua: NMAX Kantor</summary><p>Demo · Skor 100. Contoh motor dengan status perawatan aman. Kamu bisa berpindah motor aktif dalam satu akun.</p></details>
            </div>
            <p class="lp-book-foot">Catatan yang ikut jalan, bukan tercecer di laci.</p>
        </figure>
    </section>
    <section id="fitur" class="lp-wrap lp-maintenance" aria-labelledby="lp-maintenance-title">
        <div class="lp-section-intro"><p class="lp-kicker">01 / Perawatan</p><h2 id="lp-maintenance-title">Ingat perjalanan.<br>Bukan tanggal ganti oli.</h2><p>Oli, ban, aki, dan servis rutin punya jarak perawatan masing-masing. Lihat statusnya tanpa mengandalkan ingatan.</p><p>Riwayat servis yang rapi membantu menjelaskan kondisi motor saat dijual. Motor yang terawat bisa punya nilai jual lebih tinggi.</p></div>
        <div class="lp-features">
            @php
                $pillars = [
                    [
                        'icon' => 'gauge',
                        'title' => 'Pantau Kondisi Motor',
                        'summary' => 'Odometer akurat dari sumber mana saja, status warna tiap komponen, dan skor kesehatan motor dalam satu angka.',
                        'pills' => ['Odometer Backbone', 'Status Warna', 'Skor Kesehatan'],
                        'points' => [
                            'Odometer Backbone: km selalu update dari input manual, isi bensin, servis, atau riding, satu sumber kebenaran.',
                            'Status warna per komponen (hijau/kuning/merah) untuk oli, ban, aki, servis rutin.',
                            'Prediksi hari tersisa sebelum servis, berbasis rata-rata jarak harianmu.',
                            'Skor Kesehatan Motor 0-100, ringkasan sekali lihat.',
                            'Kelola beberapa motor sekaligus dalam satu akun.',
                        ],
                    ],
                    [
                        'icon' => 'bell',
                        'title' => 'Jangan Ada yang Kelewat',
                        'summary' => 'Semua yang butuh perhatianmu, mulai dari servis, dokumen, sampai efisiensi BBM yang aneh, muncul di satu tempat.',
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
                            'Pengeluaran Lain: asuransi, parkir, cuci motor, aksesoris, dll.',
                            'Laporan Biaya Kepemilikan (TCO): total, biaya per km, tren bulanan.',
                        ],
                    ],
                    [
                        'icon' => 'route',
                        'title' => 'Riding & Peta Pribadi',
                        'summary' => 'Rekam perjalananmu lewat GPS dan tandai titik-titik penting di peta pribadimu.',
                        'pills' => ['GPS Trip', 'Peta Rute', 'Peta Titik'],
                        'points' => [
                            'Trip recording GPS: nyalakan sebelum jalan, jarak terhitung otomatis.',
                            'Peta rute: lihat kembali jalur yang pernah dilalui.',
                            'Peta titik: tandai lokasi penting (bengkel langganan, jalan rawan, dll).',
                            'Peta rencana: rencanakan rute sebelum berangkat.',
                        ],
                    ],
                ];
            @endphp
            @foreach ($pillars as $pillar)
                <details class="lp-feature" @if ($loop->first) open @endif>
                    <summary>{{ $pillar['title'] }}</summary>
                    <div class="lp-detail-body"><p>{{ $pillar['summary'] }}</p><p class="lp-feature-index">{{ implode(' / ', $pillar['pills']) }}</p><ul>@foreach ($pillar['points'] as $point)<li>{{ $point }}</li>@endforeach</ul></div>
                </details>
            @endforeach
        </div>
    </section>
    <section class="lp-costs" aria-labelledby="lp-cost-title"><div class="lp-wrap lp-cost-layout">
        <figure class="lp-receipt"><figcaption>Catatan pengeluaran <span>Demo · bukan transaksi asli</span></figcaption><p class="lp-receipt-title">Satu motor. Semua biayanya.</p><dl><div><dt>Isi bensin</dt><dd>Rp30.000</dd></div><div><dt>Ganti oli</dt><dd>Rp55.000</dd></div><div><dt>Parkir</dt><dd>Rp5.000</dd></div><div class="lp-receipt-total"><dt>Total contoh</dt><dd>Rp90.000</dd></div></dl><p>Simpan nama bengkel, part yang diganti, dan foto nota pada riwayat servis.</p></figure>
        <div><p class="lp-kicker">02 / Pengeluaran</p><h2 id="lp-cost-title">Bensin, bengkel,<br>dan biaya di antaranya.</h2><p>Catat isi bensin untuk menghitung efisiensi km/liter. Tambahkan asuransi, parkir, cuci motor, dan aksesoris agar pengeluaran kecil ikut terlihat.</p><p>Laporan Biaya Kepemilikan (TCO) merangkum total, biaya per km, dan tren bulanan. Peringatan input BBM membantu menemukan angka yang tidak masuk akal.</p><a class="lp-text-link" href="{{ route('register') }}">Mulai catat pengeluaran </a></div>
    </div></section>
    <section class="lp-wrap lp-community" aria-labelledby="lp-community-title"><p class="lp-kicker">03 / Riding &amp; komunitas</p><div class="lp-community-body"><h2 id="lp-community-title">Jalan bareng.<br>Catatan tetap pribadi.</h2><div><p>Mau berangkat sendiri atau riding bersama komunitas, siapkan rute sebelum jalan. Rekam perjalanan lewat GPS, lihat kembali jalurnya, dan tandai bengkel langganan atau jalan rawan di peta pribadimu.</p><p>GPS adalah pilihan. Odometer juga terhubung dengan input manual, isi bensin, dan servis. Kamu tetap bisa merawat motor tanpa merekam setiap perjalanan.</p><a class="lp-text-link" href="{{ route('register') }}">Siapkan motor untuk perjalanan berikutnya</a></div></div></section>
    <section id="cara-kerja" class="lp-wrap lp-how" aria-labelledby="lp-how-title"><div class="lp-section-intro"><p class="lp-kicker">Mulai dari angka di motormu</p><h2 id="lp-how-title">Buka buku servis<br>pertamamu.</h2><p>Modul lengkap dalam satu aplikasi, dari kondisi motor sampai peta perjalanan.</p></div><ol class="lp-steps"><li><h3>Daftar motor</h3><p>Masukkan data motor dan odometer awal. Motor bekas? Isi Riwayat Awal untuk mencatat terakhir ganti oli, ban, aki, dan servis.</p></li><li><h3>Catat km dari mana saja</h3><p>Sumber pencatatan km: manual, isi bensin, servis, atau GPS saat riding. Semuanya terhubung ke odometer yang sama.</p></li><li><h3>Muterin yang mantau</h3><p>Status warna, Skor Kesehatan Motor, prediksi hari tersisa, dan Pusat Perhatian membantu menentukan prioritas. Termasuk Pajak STNK, Ganti Plat 5 Tahun, dan Asuransi.</p></li></ol></section>
    <section id="harga" class="lp-wrap lp-pricing" aria-labelledby="pricing-title">
        <div class="lp-section-intro"><p class="lp-kicker">Pilih paketmu</p><h2 id="pricing-title">Catatan dasar atau<br>paket Pro untuk motormu.</h2><p>Lihat rancangan fitur serta pilihan bulanan dan tahunan.</p><p class="lp-note">Pembayaran {{ config('services.midtrans.production') ? 'melalui Midtrans' : 'Sandbox tersedia untuk pengujian' }}. Trial dan pembatasan fitur belum aktif.</p></div>
        <a class="lp-button" href="{{ route('subscription') }}">Lihat paket</a>
    </section>
    <section id="faq" class="lp-wrap lp-faq" aria-labelledby="lp-faq-title"><div><p class="lp-kicker">Sebelum mulai</p><h2 id="lp-faq-title">Pertanyaan yang sering ditanyakan</h2></div><div>
        @php
            $faqs = [
                    ['q' => 'Apakah saya harus selalu pakai GPS?', 'a' => 'Tidak. GPS cuma salah satu dari 4 cara mencatat km, karena manual, isi bensin, dan servis juga otomatis update odometer.'],
                    ['q' => 'Bisa buat lebih dari satu motor?', 'a' => 'Bisa, kelola semua motor dalam satu akun, gampang pindah motor aktif.'],
                    ['q' => 'Bagaimana trial dan langganannya?', 'a' => 'Rancangan paket menyediakan Gratis dan Pro Bulanan atau Tahunan. Checkout Midtrans membeli paket langsung tanpa perpanjangan otomatis. Trial 7 hari dan pembatasan fitur belum aktif.'],
                    ['q' => 'Motor saya bekas, riwayat servisnya udah lama, gimana?', 'a' => 'Ada form "Riwayat Awal" opsional pas daftar motor: isi terakhir ganti oli/ban/aki/servis di km berapa, prediksi langsung akurat dari hari pertama.'],
                    ['q' => 'Data saya aman?', 'a' => 'Data motor & riwayatnya cuma bisa diakses dari akunmu sendiri, gak dibagikan ke pihak lain.'],
                ];
        @endphp
        @foreach ($faqs as $faq)<details class="lp-feature"><summary>{{ $faq['q'] }}</summary><div class="lp-detail-body"><p>{{ $faq['a'] }}</p></div></details>@endforeach
        <details class="lp-feature"><summary>Bisa dipasang di layar beranda?</summary><div class="lp-detail-body"><p>Bisa. Muterin mendukung instalasi sebagai aplikasi web. Pilih Install saat browser menawarkan, atau gunakan menu Tambahkan ke Layar Utama pada browser yang mendukung.</p><div x-data="{ available: false }" @mtn:install-available.window="available = true" @mtn:install-done.window="available = false"><button class="lp-button" type="button" x-show="available" x-cloak @click="window.mtnInstallApp()">Install Muterin</button></div></div></details>
    </div></section>
    <section class="lp-wrap lp-closing"><div><p class="lp-kicker">Motor dipakai. Perawatan dicatat.</p><h2>Perjalanan berikutnya,<br>lebih siap.</h2><p>Mulai dari motor yang kamu pakai hari ini.</p><p class="lp-note">Siapkan odometer dan riwayat servis untuk mengisi catatan pertamamu.</p></div><a class="lp-button" href="{{ route('register') }}">Tambahkan motormu</a></section>
</x-marketing-layout>
