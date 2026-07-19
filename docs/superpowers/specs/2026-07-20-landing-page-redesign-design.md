# Landing Page Redesign — Design Spec

## Latar Belakang

Landing page Amicta saat ini (`resources/views/landing.blade.php`) sudah punya struktur dasar (hero, problem, stats, fitur, cara kerja, CTA) tapi:

1. **Kontennya basi.** Copy masih berbunyi seolah GPS trip adalah cara utama mencatat km ("Amicta merekam perjalananmu lewat GPS..."), padahal sejak v4 (odometer backbone), sumber pencatatan km yang utama justru manual/servis/BBM — GPS trip cuma salah satu dari empat sumber. Grid fitur cuma 6 kotak generik dan tidak menyebut sama sekali modul-modul besar yang sudah dibangun: Skor Kesehatan, Pusat Perhatian, Dokumen Kendaraan, Pengeluaran Lain, Laporan biaya.
2. **Nav & footer sangat tipis.** Nav cuma Masuk/Daftar tanpa link ke section manapun. Footer cuma satu baris teks.
3. **Tidak ada bukti visual produk.** Tidak ada preview dashboard, tidak ada FAQ, tidak ada penjelasan cara kerja yang mencerminkan alur nyata (onboarding motor bekas, dsb).
4. **Angka statistik di stats strip tidak akurat/menyesatkan** — "100% berbasis GPS" dan "0 odometer manual" bertentangan langsung dengan pivot v4.

Referensi desain: Nutrio (`C:\Users\Ilyas Nur Rohman\Documents\BI-Hackathon`, khususnya `packages/modules/src/landing/*.tsx`) — dipakai untuk pola STRUKTUR & INTERAKSI (scroll reveal, card fitur expandable dengan stack-scroll, preview dashboard interaktif, FAQ accordion, footer dengan wordmark raksasa), **bukan** palet warnanya (indigo/dark). Riset konten dari kompetitor (Otodiary, Drivvo, Fuelio) dipakai untuk memvalidasi bahwa fitur Amicta sudah setara/lebih lengkap dari produk sejenis — tanpa menyebut nama kompetitor di landing page.

## Keputusan Desain (dari sesi brainstorming)

- **Warna:** tetap keluarga teal (primary `#0F766E`, hero `#134E4A`) — boleh lebih ekspresif dari dashboard (gradasi teal→hero pada background hero, misalnya), tapi tidak keluar dari keluarga warna ini. Tidak ada indigo/warna lain.
- **Tidak ada section perbandingan vs kompetitor** — landing page fokus menjelaskan produk sendiri.
- **Preview dashboard interaktif** — pakai Alpine.js, bisa switch antar 2 motor (data mendekati demo seeder: Beat Ilyas berstatus "Perhatian" skor 75, NMAX Kantor "Aman" skor 100), bukan gambar statis.
- **Tidak ada dependency baru.** Semua interaksi (expand card, switch motor, FAQ accordion, mobile nav) pakai Alpine.js (`x-data`) yang sudah dipakai di seluruh app. Animasi scroll/reveal/countup pakai infrastruktur GSAP ScrollTrigger yang sudah ada (`resources/js/reveal.js`, atribut `data-reveal`, `data-reveal-group`, `data-countup`, `data-parallax`).
- **Tidak ada klaim angka adopsi palsu** (mis. "2 juta pengguna" ala Drivvo) — produk belum punya user base nyata. Stats strip diganti jadi klaim kapabilitas yang jujur dan bisa diverifikasi dari kode (jumlah modul, jumlah sumber pencatatan km, dst).

## Struktur Halaman (urutan section)

1. Nav (layout, bukan section halaman)
2. Hero
3. Problem (Masalah)
4. Stats strip
5. Fitur (4 pilar expandable)
6. Preview Dashboard Interaktif
7. Cara Kerja
8. FAQ
9. CTA
10. Footer (layout)

## 1. Nav (`resources/views/layouts/marketing.blade.php`)

Perluas nav yang sekarang (masih `x-data="{ scrolled: false }"`, transisi warna saat scroll — dipertahankan) dengan:

- Link tengah (desktop, hidden di mobile): `#fitur`, `#cara-kerja`, `#faq` — smooth-scroll native (`scroll-behavior: smooth` di `<html>`, cek dulu apakah sudah ada; kalau belum, tambahkan di CSS).
- Mobile: tombol hamburger (`x-icon.menu` yang sudah ada) yang toggle panel dropdown berisi link yang sama + tombol Masuk/Daftar, pakai `x-data="{ mobileOpen: false }"` terpisah dari `scrolled`.
- Tombol Masuk/Daftar yang sudah ada dipertahankan persis.

## 2. Hero

- Headline baru: **"Rawat motor tanpa lupa, berbasis km yang benar-benar akurat."** — hilangkan penekanan tunggal pada GPS.
- Subheadline baru: **"Amicta mencatat jarak tempuh motormu dari sumber mana saja — manual, isi bensin, servis, atau riding — lalu otomatis mengingatkan kapan oli, ban, aki, atau servis rutin perlu diganti."**
- Background: ganti dua blur circle (`data-parallax`) yang sekarang jadi wash gradasi solid dari `primary` ke `hero` di belakang teks (`bg-gradient-to-br from-primary to-hero`, opacity rendah, posisi absolute, masih dalam keluarga teal — ini satu-satunya gradient di seluruh halaman selain tombol CTA utama).
- Card preview di kanan: **dipertahankan strukturnya** (card motor + 3 progress bar), tapi datanya diselaraskan dengan demo seeder yang sudah ada (nama "Beat Ilyas", status kuning/hijau sesuai proporsi asli) supaya konsisten dengan apa yang akan dilihat user beneran setelah daftar.
- CTA button tetap 2: "Mulai Gratis" (accent) dan "Lihat Fitur" (outline, scroll ke `#fitur`).

## 3. Problem

Pertahankan 3-card grid yang sekarang tapi perkuat copy dengan insight dari riset kompetitor (tanpa menyebut nama produk):

- **Lupa ganti oli** — "Patokannya cuma perasaan atau waktu, bukan jarak tempuh riil."
- **Odometer jarang dicek** — "Harus buka motor & baca angka manual tiap mau tahu status."
- **Tidak ada riwayat servis** *(copy diperkuat)* — "Motor dengan riwayat servis lengkap dan tercatat rapi punya nilai jual lebih tinggi saat dijual — tapi kebanyakan orang gak pernah mencatatnya dari awal."

## 4. Stats Strip

Ganti 3 angka yang sekarang jadi klaim kapabilitas (dihitung dari kode, bukan fabrikasi):

- `4` — "Sumber pencatatan km" (manual, BBM, servis, riding)
- `9` — "Modul lengkap dalam satu aplikasi"
- `0` — "Tebak-tebakan jadwal servis"

(Format tetap pakai `data-countup` seperti sekarang.)

## 5. Fitur — 4 Pilar Expandable

Ganti grid 6-kotak statis dengan 4 card pilar yang bisa di-expand (pola: judul+ringkasan+3 feature-pill selalu terlihat; klik "Lihat detail" expand jadi list lengkap poin fitur — mirip pola "Tandai selesai" toggle yang sudah dipakai di halaman motor detail, bukan komponen baru).

Struktur data (di dalam `@php` blok landing.blade.php, sama pola seperti array `$features` yang sudah ada):

```php
$pillars = [
    [
        'id' => 'pantau',
        'icon' => 'gauge',
        'title' => 'Pantau Kondisi Motor',
        'summary' => 'Odometer akurat dari sumber mana saja, status warna tiap komponen, dan skor kesehatan motor dalam satu angka.',
        'points' => [
            'Odometer Backbone — km selalu update dari input manual, isi bensin, servis, atau riding, satu sumber kebenaran.',
            'Status warna per komponen (hijau/kuning/merah) untuk oli, ban, aki, servis rutin.',
            'Prediksi hari tersisa sebelum servis, berbasis rata-rata jarak harianmu.',
            'Skor Kesehatan Motor 0-100, ringkasan sekali lihat.',
            'Kelola beberapa motor sekaligus dalam satu akun.',
        ],
    ],
    [
        'id' => 'perhatian',
        'icon' => 'bell',
        'title' => 'Jangan Ada yang Kelewat',
        'summary' => 'Semua yang butuh perhatianmu — servis, dokumen, sampai efisiensi BBM yang aneh — muncul di satu tempat.',
        'points' => [
            'Pusat Perhatian menyatukan semua pengingat jadi satu daftar prioritas.',
            'Reminder jatuh tempo Pajak STNK, Ganti Plat 5 Tahun, dan Asuransi.',
            'Peringatan otomatis kalau efisiensi BBM tercatat gak masuk akal (indikasi salah input).',
        ],
    ],
    [
        'id' => 'biaya',
        'icon' => 'wallet',
        'title' => 'Kontrol Biaya Penuh',
        'summary' => 'Dari isi bensin sampai premi asuransi tahunan, semua pengeluaran motor kecatat dan terlihat totalnya.',
        'points' => [
            'Catat isi bensin, hitung efisiensi km/liter otomatis.',
            'Riwayat servis lengkap dengan nama bengkel, part yang diganti, dan foto nota.',
            'Pengeluaran Lain — asuransi, parkir, cuci motor, aksesoris, dll.',
            'Laporan Biaya Kepemilikan (TCO): total, biaya per km, tren bulanan.',
        ],
    ],
    [
        'id' => 'riding',
        'icon' => 'route',
        'title' => 'Riding & Peta Pribadi',
        'summary' => 'Rekam perjalananmu lewat GPS dan tandai titik-titik penting di peta pribadimu.',
        'points' => [
            'Trip recording GPS — nyalakan sebelum jalan, jarak terhitung otomatis.',
            'Peta rute — lihat kembali jalur yang pernah dilalui.',
            'Peta titik — tandai lokasi penting (bengkel langganan, jalan rawan, dll).',
            'Peta rencana — rencanakan rute sebelum berangkat.',
        ],
    ],
];
```

Rendering: `data-reveal-group` grid 2 kolom (desktop) / 1 kolom (mobile), tiap card `x-data="{ open: false }"`, `x-ui.card` sebagai wrapper, icon+judul+summary selalu tampil, 3 feature-pill kecil (badge) dari `points` pertama, tombol "Lihat detail →" yang toggle `open` untuk menampilkan sisa `points` sebagai list bercentang (pakai `x-icon.check`).

## 6. Preview Dashboard Interaktif

Section baru, background `bg-muted` (bukan gradient), grid 2 kolom: teks kiri, mockup card kanan (pola sama seperti `DashboardPreview.tsx` Nutrio: teks+bullet di satu sisi, mockup interaktif di sisi lain).

- Teks kiri: judul "Lihat sendiri tampilannya", 3 bullet singkat (status warna, skor kesehatan, pusat perhatian), tombol CTA "Coba Sekarang" ke `/register`.
- Mockup kanan: `x-data="{ motor: 'beat' }"`, dua tombol tab "Beat Ilyas" / "NMAX Kantor" yang toggle `motor`. Di bawahnya, card yang menampilkan (via `x-show="motor === 'beat'"` / `x-show="motor === 'nmax'"`) data mendekati demo seeder:
  - **Beat Ilyas**: Skor 75 (badge kuning "Perhatian"), Oli Mesin 106% (merah, "Sudah lewat batas"), Ban 87%, Aki 80%.
  - **NMAX Kantor**: Skor 100 (badge hijau "Aman"), Oli Mesin 16%, Ban 51%, Aki 41%, Servis Rutin 65% — nilai persis sesuai `DemoDataSeeder`, bukan dibulatkan jadi "semua hijau" biar preview ini konsisten dengan apa yang user lihat beneran setelah daftar.
- Mockup dibungkus "browser chrome" sederhana (3 dot + nama motor di title bar), sama pola seperti card hero yang sudah ada, cuma diperbesar dan diberi 2 tab.

## 7. Cara Kerja

Update 3 langkah, hilangkan penekanan tunggal pada GPS:

1. **Daftar motor** — "Masukkan data motor & odometer awal. Motor bekas? Isi riwayat servis terakhir sekali saja biar prediksi langsung akurat."
2. **Catat km dari mana saja** — "Manual, pas isi bensin, pas servis, atau nyalakan GPS pas riding — bebas pilih, semua otomatis nyambung."
3. **Amicta yang mantau** — "Status warna, skor kesehatan, dan Pusat Perhatian otomatis update, kamu tinggal cek kalau ada notifikasi."

## 8. FAQ Accordion

Section baru, `max-w-3xl mx-auto`, daftar pertanyaan (`x-data="{ open: null }"`, klik toggle index):

1. **Apakah saya harus selalu pakai GPS?** — Tidak. GPS cuma salah satu dari 4 cara mencatat km — manual, isi bensin, dan servis juga otomatis update odometer.
2. **Bisa buat lebih dari satu motor?** — Bisa, kelola semua motor dalam satu akun, gampang pindah motor aktif.
3. **Amicta gratis?** — Gratis, tanpa kartu kredit, daftar langsung bisa dipakai.
4. **Motor saya bekas, riwayat servisnya udah lama, gimana?** — Ada form "Riwayat Awal" opsional pas daftar motor — isi terakhir ganti oli/ban/aki/servis di km berapa, prediksi langsung akurat dari hari pertama.
5. **Data saya aman?** — Data motor & riwayatnya cuma bisa diakses dari akunmu sendiri, gak dibagikan ke pihak lain.

## 9. CTA

Pertahankan struktur yang sekarang (card solid `bg-hero`, bukan `bg-primary` biar lebih gelap/tegas — cek kontras teks putih di atasnya), tambahkan baris trust badge kecil di bawah tombol: "Gratis selamanya" · "Tanpa kartu kredit" · "Setup di bawah 2 menit" (pakai `x-icon.check` kecil, teks putih/80).

## 10. Footer (`resources/views/layouts/marketing.blade.php`)

Rombak dari 1 baris jadi:

- Baris atas: 2 kolom (desktop) — kiri: logo+nama+deskripsi singkat; kanan: 2 kelompok link ("Navigasi": Fitur/Cara Kerja/FAQ, "Akun": Masuk/Daftar).
- Baris bawah: wordmark "AMICTA" besar (CSS `text-[clamp(3rem,15vw,10rem)] font-heading font-black text-primary/10`, bukan gambar, bukan animasi kompleks — cukup elemen statis besar sebagai penutup visual, konsisten dengan prinsip "no gradient, no template AI" — ini tipografi murni, bukan efek).
- Copyright line kecil di paling bawah, dipertahankan dari yang sekarang.

## File yang Terdampak

- Modify: `resources/views/landing.blade.php` (rewrite penuh)
- Modify: `resources/views/layouts/marketing.blade.php` (nav + footer)
- Create: `resources/views/components/icon/chevron-down.blade.php` (satu-satunya icon baru, dipakai untuk toggle expand pillar card & FAQ)
- Modify (mungkin): `resources/css/app.css` — tambah `scroll-behavior: smooth` di `html` kalau belum ada.
- Tidak ada perubahan backend/controller — halaman ini fully static content, `Route::get('/', ...)` yang sudah ada tidak berubah.

## Yang Sengaja Tidak Dikerjakan

- Tidak ada video background (Nutrio pakai video hero) — tidak ada asset video, dan akan menambah kompleksitas build tanpa manfaat jelas untuk produk tahap ini.
- Tidak ada section perbandingan vs kompetitor (sudah diputuskan skip di brainstorming).
- Tidak ada testimonial/social proof (belum ada user real, memalsukan testimoni melanggar prinsip kejujuran konten).
- Tidak ada dark-mode khusus landing page — ikut tema terang yang sudah jadi standar seluruh app.
- Tidak ada A/B test atau analytics tracking baru — di luar scope redesain visual/konten.

## Risiko

- **Kontras teks di atas gradient hero baru** — perlu dicek manual (bukan cuma dari deskripsi) bahwa teks putih/foreground masih WCAG AA compliant di atas gradasi `primary→hero`, khususnya di ujung gradient yang lebih terang.
- **Copy pillar terlalu panjang saat expand di mobile** — perlu dicek bahwa card tidak jadi terlalu tinggi/janggal di layar kecil; kalau iya, points bisa di-scroll internal (`max-h-* overflow-y-auto`) alih-alih membatasi jumlah poin.
