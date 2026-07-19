# Desain: Odometer Backbone & Dokumen Kendaraan (Amicta v4)

**Tanggal:** 2026-07-19
**Status:** Disetujui konsep (menunggu review spec) — **implementasi dikerjakan di model Sonnet 5, bukan Opus**

---

## 1. Latar Belakang & Masalah

Analisa kritis terhadap Amicta v3 (fitur BBM, prediksi, skor kesehatan, Pusat Perhatian, laporan)
menemukan cacat fondasi: **seluruh akurasi produk bertumpu pada odometer, dan odometer
satu-satunya sumbernya adalah GPS trip browser** — yang secara teknis tidak reliable
(mati kalau tab ditutup/layar terkunci, umum terjadi saat riding beneran).

Riset ke produk pembanding nyata — [Drivvo](https://drivvo.com/en/) dan
[Fuelio](https://play.google.com/store/apps/details?id=com.kajda.fuelio) — mengonfirmasi:
**standar industri kategori ini adalah input odometer manual** di titik-titik alami
(saat isi bensin, saat servis, atau langsung), dengan GPS sebagai pelengkap opsional,
bukan sumber utama. Amicta membalik urutan ini, sehingga:

- Prediksi hari (`MaintenancePredictionService`) sering `null` atau meleset karena data trip jarang
- Skor kesehatan ikut salah karena diturunkan dari status yang basi
- Motor bekas yang didaftarkan langsung dianggap "baru diservis semua" (0% terpakai) — padahal motor bekas 12.000km jelas tidak fresh
- Tidak ada validasi odometer mundur atau input BBM yang mustahil (km/liter 200+)

Riset kedua ke [Otodiary](http://www.otodiary.com/) (produk lokal Indonesia) mengonfirmasi
gap lain: **reminder berbasis tanggal (pajak STNK tahunan, ganti plat 5-tahunan, asuransi)**
adalah standar produk lokal yang belum dimiliki Amicta — dan ini pain point yang dialami
hampir semua pemilik kendaraan di Indonesia (denda pajak progresif, risiko tilang),
bukan cuma edge case.

Tinjauan langsung ke tangkapan layar Otodiary (toggle "isi penuh tangki", metode "full to
full" untuk efisiensi, jadwal gabungan km+tanggal) mengonfirmasi arah desain di dokumen ini
sudah selaras dengan standar produk tersebut — dan menyingkap satu gap tambahan: **Total
Cost of Ownership Amicta sekarang cuma menjumlahkan BBM + servis, padahal pemilik motor
riil juga punya pengeluaran lain (asuransi, parkir, cuci motor, aksesoris) yang belum
tertampung** — angka TCO di halaman Laporan jadi under-count, bukan cuma "kurang fitur".

**Prinsip pemecahan:** mekanik inti (odometer, logging) mengikuti standar industri persis —
tidak ada ruang untuk kreativitas di sini. Diferensiasi Amicta ditaruh di presentasi &
intelligence (dashboard, skor, prediksi) yang sudah dibangun di atasnya, bukan di plumbing dasarnya.

---

## 2. Ringkasan Perubahan

| # | Bagian | Tipe |
|---|--------|------|
| 1 | `OdometerReading` model + migration | Baru |
| 2 | `OdometerService` — satu jalur tulis odometer | Baru (service) |
| 3 | Alihkan semua penulis odometer lewat service ini | Perluasan (Fuel/Trip/Motorcycle controllers) |
| 4 | Tombol "Update KM" manual (dashboard + detail motor) | Baru (UI + controller) |
| 5 | Onboarding motor bekas — km terakhir servis per item | Perluasan (form tambah motor) |
| 6 | Validasi (odometer mundur, km/liter janggal) | Perluasan |
| 7 | Prediksi baca dari `OdometerReading`, bukan `Trip` | Perluasan (`MaintenancePredictionService`) |
| 8 | Dokumen Kendaraan (STNK/plat/asuransi) — 3 kolom + Pusat Perhatian | Baru |
| 9 | Pengeluaran Lain (asuransi/parkir/cuci/aksesoris) — masuk ke TCO & Laporan | Baru |

---

## 3. Model Data

### 3.1 Tabel baru: `odometer_readings`
```
id
motorcycle_id   (FK, cascade delete)
reading_km      (unsigned int)
recorded_at     (date)
source          (enum: manual, fuel, trip, initial)
note            (string, nullable)
timestamps
```
Model `OdometerReading` belongsTo `Motorcycle`. `Motorcycle hasMany odometerReadings`.

### 3.2 Kolom baru di `motorcycles` (dokumen, migration ALTER)
```
stnk_due_date       (date, nullable)
plat_due_date       (date, nullable)   -- ganti plat / STNK 5 tahunan
insurance_due_date  (date, nullable)
```
Semua opsional — motor tanpa data dokumen tetap berfungsi normal, section ini tidak wajib diisi.
Dokumen tidak butuh tabel riwayat terpisah — event-nya jarang (1x/5x setahun), cukup satu
tanggal jatuh tempo yang di-update manual saat user bayar/perpanjang.

### 3.3 Tabel baru: `other_expenses`
```
id
motorcycle_id   (FK, cascade delete)
category        (enum: asuransi, parkir, cuci_motor, aksesoris, lain_lain)
amount          (unsigned int)
expense_date    (date)
note            (string, nullable)
timestamps
```
Model `OtherExpense` belongsTo `Motorcycle`. `Motorcycle hasMany otherExpenses`.
Tidak berdampak ke odometer — murni pencatatan biaya, tidak seperti BBM/servis yang
sekaligus jadi sumber `OdometerReading`.

---

## 4. `OdometerService` — Satu Jalur Tulis Tunggal

```
record(Motorcycle $motor, int $km, Carbon $date, string $source, ?string $note = null): OdometerReading
```
- **Menolak dengan `ValidationException`** jika `$km` lebih rendah dari `current_odometer_km`
  saat ini — odometer mundur adalah data rusak yang akan merusak kalkulasi `avgKmPerDay()`
  (delta negatif), jadi ditolak tegas di titik tulis tunggal ini, bukan dibiarkan masuk
  dan diabaikan diam-diam. Ini mengganti perilaku `FuelController` sekarang yang cuma
  skip update tanpa menolak — perilaku baru lebih ketat dan konsisten di semua sumber.
- Bila lolos validasi: selalu mencatat satu baris `OdometerReading`, dan meng-update
  `motorcycles.current_odometer_km` ke `$km` (yang pasti lebih tinggi/sama, karena sudah lolos validasi).

```
avgKmPerDay(Motorcycle $motor): ?float
```
- Dihitung dari `OdometerReading` pertama ke terakhir dalam window yang sama seperti
  `MaintenancePredictionService` sekarang (30 hari terakhir, fallback ke rata-rata seumur
  hidup bila data 30-hari kosong) — **logika window & fallback yang sudah ada dipertahankan**,
  cuma sumber datanya diganti dari `Trip` ke `OdometerReading` (jauh lebih rapat karena BBM +
  manual + trip semua nyumbang titik data).

**Titik yang dialihkan untuk menulis odometer lewat service ini** (bukan langsung
`$motor->update(['current_odometer_km' => ...])`):
- `MotorcycleController::store()` — catat reading awal (`source: initial`) saat motor didaftarkan.
- `FuelController::store()` — ganti update odometer langsung dengan `OdometerService::record(..., source: 'fuel')`.
- `TripController` (endpoint simpan trip) — `OdometerService::record(..., source: 'trip')`.
- **Baru:** `OdometerReadingController::store()` — `OdometerService::record(..., source: 'manual')`.

---

## 5. Update KM Manual (fitur baru)

Tombol aksi cepat "Update KM" muncul di:
- Kartu motor di dashboard (ikon kecil di pojok kartu)
- Halaman detail motor (tombol utama, sejajar tombol Edit)

Klik membuka form kecil (mengikuti pola Alpine toggle yang sudah dipakai di form "tandai
selesai"): input km baru + tanggal (default hari ini) + catatan opsional. Submit ke
`OdometerReadingController::store()`.

---

## 6. Onboarding Motor Bekas

Form Tambah Motor mendapat section opsional baru **"Riwayat Awal (opsional)"**, muncul
setelah field odometer, berisi 4 input angka opsional: "Oli terakhir diganti di km berapa?",
"Ban", "Aki", "Servis Rutin" — masing-masing default kosong (artinya: pakai perilaku lama,
dianggap baru diservis saat odometer saat ini).

Di `MotorcycleController::store()`, setelah motor dibuat (yang otomatis membuat 4
`MaintenanceItem` via `Motorcycle::booted()`), untuk tiap field yang diisi user, update
`last_service_odometer_km` item terkait ke nilai yang diinput — merepresentasikan kondisi
riil motor bekas, bukan asumsi "baru semua".

---

## 7. Validasi

- **Odometer tidak boleh mundur** — ditegakkan satu kali di `OdometerService::record()`,
  bukan tersebar di tiap controller (menghapus duplikasi logika yang sekarang ada di
  `FuelController` sendirian).
- **Efisiensi BBM janggal**: bila hasil km/liter satu entri > 60 (batas wajar motor),
  tampilkan pesan info non-blocking ("Angka ini terlihat tidak biasa, cek kembali odometer
  atau jumlah liter") — tidak menolak submit, karena edge case nyata (misal motor matic
  irit banget) tetap harus bisa dicatat.

---

## 8. Dokumen Kendaraan

**Form tambah/edit motor**: section opsional "Dokumen Kendaraan" — 3 input tanggal
(Pajak STNK Tahunan, Ganti Plat/STNK 5 Tahun, Asuransi), semua nullable.

**Detail motor**: card baru "Dokumen" (gaya sama seperti card item perawatan), tiap baris
terisi menampilkan nama dokumen + teks hitung-mundur ("18 hari lagi" / "Sudah lewat 3 hari")
+ badge warna: hijau (>30 hari), kuning (≤30 hari), merah (lewat tanggal). Dokumen yang
tanggalnya kosong tidak ditampilkan (bukan dipaksa muncul dengan status kosong).

**`AttentionService`**: tambah pengecekan dokumen per motor (merah bila lewat, kuning bila
≤30 hari), digabung ke daftar item yang sama dengan perawatan — urutan tetap merah dulu.
Contoh teks: *"Segera bayar Pajak STNK — Beat Ilyas, jatuh tempo 3 hari lagi"*.

**Update setelah bayar**: user edit motor, ganti tanggal ke periode berikutnya secara manual
— tidak ada sistem log riwayat pembayaran (di luar scope, event terlalu jarang untuk
butuh riwayat detail seperti servis).

---

## 9. Pengeluaran Lain

**Terintegrasi ke halaman "Biaya & Servis" yang sudah ada** (bukan halaman baru terpisah) —
tombol "+ Catat Pengeluaran Lain" di samping tombol export PDF yang sudah ada. Form:
kategori (dropdown: Asuransi, Parkir, Cuci Motor, Aksesoris, Lain-lain), motor, jumlah,
tanggal, catatan opsional.

**Tabel riwayat pengeluaran** di halaman yang sama diperluas — baris `OtherExpense`
digabung bersama baris `MaintenanceLog` (servis), dibedakan lewat kolom "Jenis"/ikon
kategori, tetap satu tabel tunggal yang bisa dicari (search yang sudah ada tetap jalan
karena sudah berbasis teks konten baris, bukan tipe data spesifik).

**Donut chart alokasi** yang sudah ada diperluas — kategori pengeluaran lain masuk sebagai
irisan tambahan di samping Oli/Ban/Aki/Servis Rutin.

**Halaman Laporan**: `ReportController` menambah `$otherExpenseCost` ke perhitungan TCO
(`$tco = $totalFuelCost + $totalServiceCost + $totherExpenseCost`), dan tren bulanan
(chart batang bertumpuk) mendapat dataset ketiga "Lainnya" di samping BBM & Servis yang
sudah ada.

Tidak menyentuh odometer, prediksi, atau skor kesehatan — murni memperkaya sisi biaya.

---

## 10. Yang Sengaja Tidak Dikerjakan (dan kenapa)

- **Backfill riwayat `OdometerReading` dari data lama** (fuel log/trip lama) — di-skip.
  Belum ada user produksi selain akun demo; membangun script backfill untuk nol user riil
  adalah over-engineering. Skema baru berlaku mulai sekarang; seeder demo diupdate agar
  tetap konsisten menampilkan fitur ini.
- **Riwayat pembayaran dokumen** (kapan bayar pajak tahun-tahun sebelumnya) — di luar
  scope, lihat §8.
- **Notifikasi push/email untuk dokumen** — cukup nyatu ke Pusat Perhatian in-app yang
  sudah ada, tidak menambah kanal baru.
- **Dropdown SPBU / jenis BBM (Pertamax dll)** di form isi bensin — tidak menyelesaikan
  masalah nyata untuk kalkulasi biaya/efisiensi, cuma menambah friksi input tanpa payoff
  jelas. Bisa ditambah nanti kalau ada permintaan konkret.
- **Reminder dual-trigger (km ATAU bulan) per item perawatan** — Otodiary punya ini di
  level tiap item, tapi mengadopsinya berarti merombak `MaintenanceItem` yang sudah dipakai
  luas (status, prediksi, skor kesehatan, dashboard). Pusat Perhatian yang sudah ada sudah
  menggabungkan sinyal km-based (perawatan) dan tanggal-based (dokumen) di **level daftar**,
  menangkap inti masalah tanpa membongkar fondasi item perawatan. Kandidat kuat untuk fase
  berikutnya, bukan fase ini.

---

## 11. Risiko

| Risiko | Mitigasi |
|--------|----------|
| Motor lama (dibuat sebelum fitur ini) tidak punya `OdometerReading` sama sekali → prediksi tetap `null` sampai ada reading baru | Perilaku ini sudah ditangani `MaintenancePredictionService` (kembalikan `null` dengan anggun, UI sudah menampilkan "belum cukup data") — tidak ada regresi, cuma belum membaik sampai user input reading baru |
| User isi tanggal dokumen di masa lalu (typo) | Validasi `after_or_equal:today` opsional dilonggarkan — dokumen yang sudah lewat tetap valid untuk dicatat (justru itu skenario paling penting untuk ditampilkan merah), jadi TIDAK divalidasi sebagai error, hanya divalidasi format tanggal |
| Kolom dokumen menambah kompleksitas form tambah motor | Semua opsional & collapsible, tidak mengganggu alur cepat tambah motor untuk user yang tidak mau isi sekarang |
| Tabel gabungan servis+pengeluaran-lain di halaman Biaya & Servis jadi ambigu secara UI | Kolom "Jenis"/ikon kategori membedakan tiap baris secara visual; donut chart tetap memecah per kategori, bukan digabung jadi satu irisan besar |
