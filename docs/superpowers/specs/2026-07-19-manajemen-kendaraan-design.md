# Desain: Manajemen Kendaraan (Amicta v3)

**Tanggal:** 2026-07-19
**Status:** Disetujui konsep (menunggu review spec) — **implementasi dikerjakan di model Sonnet 5, bukan Opus**

---

## 1. Tujuan

Melengkapi Amicta dari "pengingat oli" menjadi **sistem manajemen kendaraan** yang
benar-benar menyelesaikan masalah pemilik motor — analog dengan modul "Manajemen
Produksi" di Nutrio, tetapi untuk operasional kepemilikan kendaraan. Tanpa IoT;
semua berbasis data yang sudah dimiliki (odometer + trip GPS) plus input manual.

**Value proposition baru:** (a) tahu biaya sebenarnya kepemilikan motor, (b) tidak
pernah kelewat servis lewat prediksi berbasis pola riding sendiri, (c) rekam jejak
digital lengkap untuk menaikkan harga jual.

**Konteks & batasan:**
- Web app Laravel + Blade + Alpine + Tailwind (lanjutan codebase yang ada).
- **Dokumen/pajak berbasis tanggal (STNK, pajak, asuransi, SIM) TIDAK termasuk** —
  sesuai keputusan user, fokus km-based & event-based.
- Grounded ke produk nyata: Drivvo, Fuelio, Simply Auto (global); Otodiary, Otoklix
  (Indonesia). Fitur BBM, prediksi, riwayat servis detail semuanya divalidasi dari
  produk yang ada, bukan mengada-ada.

---

## 2. Ringkasan 6 Modul

| # | Modul | Tipe | Ringkas |
|---|-------|------|---------|
| 1 | Manajemen BBM | Baru (model + halaman) | Catat isi bensin, hitung km/liter & biaya/km |
| 2 | Prediksi Perawatan | Baru (service, tanpa tabel) | Prediksi tanggal item lewat batas dari rata-rata km/hari |
| 3 | Skor Kesehatan Motor | Baru (service) | Angka komposit 0–100 dari perawatan + efisiensi BBM |
| 4 | Pusat Perhatian | Baru (agregator) | Hub aksi: overdue, prediksi mepet, efisiensi turun |
| 5 | Riwayat Servis Detail | Perluasan model | Tambah bengkel, sparepart, foto nota |
| 6 | Laporan / Analytics | Baru (halaman) | TCO, biaya/km, tren pengeluaran & efisiensi |

---

## 3. Perubahan Model Data

### 3.1 Tabel baru: `fuel_logs`
```
id
motorcycle_id  (FK, cascade delete)
filled_at      (date)
odometer_km    (unsigned int)   -- pembacaan odometer saat isi
liters         (decimal 6,2)
total_cost     (unsigned int)   -- rupiah
is_full_tank   (boolean, default true) -- untuk kalkulasi konsumsi full-to-full
note           (string, nullable)
timestamps
```
Model `FuelLog` belongsTo `Motorcycle`. `Motorcycle hasMany fuelLogs`.

### 3.2 Perluasan `maintenance_logs` (migration ALTER)
Tambah kolom (semua nullable, aman untuk data lama):
```
workshop_name  (string, nullable)
parts          (string, nullable)   -- sparepart diganti, teks bebas
receipt_path   (string, nullable)   -- path foto nota di storage
```

### 3.3 Tidak ada perubahan model lain
Prediksi, skor kesehatan, action center, dan laporan semuanya **turunan** (dihitung
dari data yang ada) — tidak menambah tabel. Ponytail: jangan simpan yang bisa dihitung.

---

## 4. Modul 1 — Manajemen BBM

**Halaman baru** `/bbm` (sidebar "BBM"). Controller `FuelController` (index, store, destroy).

**Alur:**
1. User klik "Catat Isi Bensin" → form: motor, tanggal, odometer sekarang, liter,
   total biaya, tank penuh? (checkbox, default ya), catatan.
2. Simpan → jika `odometer_km` > `motorcycle.current_odometer_km`, update odometer
   motor (sinkronisasi odometer tanpa perlu trip GPS).
3. Tampilkan statistik + tabel riwayat isi + grafik efisiensi.

**Service baru `FuelStatsService`:**
- `consumptionSeries(Motorcycle)`: untuk tiap pasangan isi **full-to-full** berurutan,
  `km_per_liter = (odo_now - odo_prev) / liters_now`. Hanya dihitung bila kedua ujung
  `is_full_tank = true`. Kembalikan array {date, km_per_liter} untuk grafik.
- `averageKmPerLiter(Motorcycle)`: rata-rata dari series (null bila < 2 full tank).
- `costPerKm(Motorcycle)`: total_cost seluruh fuel_logs / total km ditempuh
  (odometer_terakhir − odometer_isi_pertama). Null bila data kurang.
- `latestKmPerLiter(Motorcycle)`: nilai terbaru (untuk deteksi penurunan).

**Edge case:** liter = 0 → tolak (validasi min 0.1). Hanya 1 isi / belum ada pasangan
full-to-full → efisiensi tampil "—" dengan pesan "butuh minimal 2x isi tank penuh".

**UI:** hero + 3 stat card (rata-rata km/liter, biaya/km, total biaya BBM) + form
(Alpine toggle) + tabel isi + grafik garis efisiensi (Chart.js, sudah tersedia).

---

## 5. Modul 2 — Prediksi Perawatan Cerdas

**Service baru `MaintenancePredictionService`** (tanpa tabel).

- `avgKmPerDay(Motorcycle)`: total `distance_km` trip dalam 30 hari terakhir / 30.
  Bila tidak ada trip 30 hari → fallback: (odometer_sekarang − odometer_awal) /
  umur-hari-sejak-motor-dibuat. Bila tetap 0/null → kembalikan null.
- `forItem(MaintenanceItem, currentOdometer, avgKmPerDay)`:
  `remaining_km = max(0, interval − used)`;
  `days_left = avgKmPerDay > 0 ? ceil(remaining_km / avgKmPerDay) : null`;
  `predicted_date = today + days_left`.
- Kembalikan `['days_left' => int|null, 'predicted_date' => Carbon|null]`.

**Integrasi (tanpa halaman baru):**
- Di **dashboard** tiap progress bar item diberi label kecil: *"~9 hari lagi"* (kuning
  bila ≤14 hari, merah bila sudah lewat / 0 hari).
- Di **detail motor** teks prediksi lebih jelas: *"Perkiraan lewat batas: 28 Jul 2026
  (±9 hari) berdasarkan rata-rata {X} km/hari."*
- Bila `avgKmPerDay` null → tampilkan "Belum cukup data trip untuk prediksi".

**Ponytail:** ini heuristik linear sederhana; knob 30-hari & fallback ditandai komentar
`ponytail:` sebagai parameter yang bisa dituning. Bukan ML — jujur ke user bahwa ini
estimasi dari rata-rata.

---

## 6. Modul 3 — Skor Kesehatan Motor

**Service baru `HealthScoreService`** — komposit 0–100 per motor.

**Formula MVP (didokumentasikan, tunable — ponytail calibration knob):**
```
skor = 100
untuk tiap maintenance item:
    persen = status percent (dari MaintenanceStatusService)
    jika persen > 100 (merah/overdue): skor -= 15
    elif persen >= 80 (kuning):        skor -= 5
    else:                              skor -= 0
// efisiensi BBM (bila ada data):
jika latestKmPerLiter < 0.85 * averageKmPerLiter: skor -= 10
skor = clamp(skor, 0, 100)
```
Band warna: ≥80 hijau ("Sehat"), 60–79 kuning ("Perlu perhatian"), <60 merah ("Butuh
servis").

**Integrasi:** kartu skor besar di **dashboard** (di dalam / sebelah hero, angka besar +
band warna + label). Analog "Skor Gizi 98/100" Nutrio. Juga tampil ringkas di kartu
tiap motor & detail motor.

**Edge case:** motor tanpa data BBM → komponen efisiensi dilewati (tidak menghukum).
Motor baru semua item hijau → skor 100.

---

## 7. Modul 4 — Pusat Perhatian (Action Center)

**Agregator** (di `DashboardController`, atau service `AttentionService`) yang
mengumpulkan aksi lintas semua motor user:
- Item perawatan **overdue** (merah) → "Segera servis {item} {motor}" → link detail motor.
- Item **diprediksi mepet** (days_left ≤ 14 dan belum overdue) → "{item} {motor} ~{n} hari
  lagi" → link detail.
- **Efisiensi BBM turun** (latest < 85% rata-rata) → "Konsumsi BBM {motor} turun, cek
  kondisi mesin" → link BBM.

Diurutkan: overdue dulu, lalu prediksi terdekat. Tiap entri: ikon, teks, tombol aksi.

**Integrasi:** kartu "Pusat Perhatian" di **dashboard** (gaya AI Action Center Nutrio —
border/aksen per severity). Bila kosong → state positif "Semua motor terkendali ✓".
Menggantikan peran notifikasi per-item yang tercecer (notify.js tetap ada untuk push
lokal, tapi hub ini jadi sumber utama in-app).

---

## 8. Modul 5 — Riwayat Servis Detail + Foto Nota

**Perluasan** flow "Tandai selesai" yang sudah ada (`MaintenanceController::complete`).

- Form tandai-selesai tambah field: **Nama Bengkel** (opsional), **Sparepart diganti**
  (opsional), **Foto Nota** (upload gambar, opsional).
- Validasi upload: `image`, maks 2 MB. Simpan ke `storage/app/public/receipts` via
  `store('receipts', 'public')`. Perlu `php artisan storage:link` (langkah setup).
- Tampilkan di **Biaya & Servis** (tabel) & **detail motor**: nama bengkel + sparepart,
  dan thumbnail nota yang bisa diklik (buka gambar penuh).
- Export PDF servis: sertakan kolom bengkel & sparepart (foto tidak diikutkan ke PDF —
  cukup teks, jaga ukuran).

**Edge case:** upload bukan gambar / >2MB → validasi tolak dengan pesan. Log lama tanpa
foto → tampil normal tanpa thumbnail.

---

## 9. Modul 6 — Laporan / Analytics Mendalam

**Halaman baru** `/laporan` (sidebar "Laporan"). Controller `ReportController` (invoke).

Agregasi (tanpa tabel baru), keseluruhan + filter per motor:
- **Total Cost of Ownership (TCO)** = total biaya BBM + total biaya servis.
- **Biaya per km** = TCO / total km ditempuh.
- **Tren pengeluaran bulanan**: bar chart bertumpuk (BBM vs servis) per bulan, 6–12 bulan.
- **Tren efisiensi BBM**: line chart km/liter dari `FuelStatsService::consumptionSeries`.
- Ringkasan stat card di atas (TCO, biaya/km, total BBM, total servis).

Semua chart pakai Chart.js (sudah dipakai di Biaya & Servis). Bila data kosong →
empty state ramah.

---

## 10. Struktur Navigasi (sidebar) Setelah Perubahan

```
Dashboard          (+ Skor Kesehatan, Pusat Perhatian, prediksi per item)
Motor Saya
Riding
BBM                (BARU)
Biaya & Servis     (diperkaya: bengkel/sparepart/foto)
Laporan            (BARU)
Peta Rute
Titik Saya
Rencana Rute
—— Sistem ——
Pengaturan
```
Prediksi, Skor Kesehatan, Pusat Perhatian TIDAK jadi menu sendiri — menyatu di Dashboard
& detail motor (tempat user memutuskan tindakan).

---

## 11. Komponen & Boundary

- **Model:** `FuelLog` (baru); `MaintenanceLog` (+3 kolom).
- **Services (murni hitung, mudah dites terpisah):**
  - `FuelStatsService` — konsumsi, biaya/km, efisiensi.
  - `MaintenancePredictionService` — avg km/hari, prediksi per item.
  - `HealthScoreService` — skor komposit.
  - `AttentionService` — daftar aksi (boleh digabung ke DashboardController bila kecil).
- **Controllers:** `FuelController`, `ReportController` (baru); `MaintenanceController`,
  `DashboardController` (diperluas).
- **Views:** `bbm/index`, `laporan/index` (baru); dashboard, motorcycles/show,
  history/index, history/export-pdf (diperluas).
- **Setup:** `storage:link` untuk foto nota.

Tiap service punya satu tanggung jawab & antarmuka jelas (input model + odometer/avg,
output array angka) → bisa dites tanpa HTTP.

---

## 12. Testing (fokus logika, untuk implementasi Sonnet)

- `FuelStatsService`: konsumsi full-to-full benar; abaikan partial fill; biaya/km; guard
  bagi nol.
- `MaintenancePredictionService`: days_left benar; null saat tak ada data; overdue → 0.
- `HealthScoreService`: skor di batas 79/80/100/101 & penalti efisiensi; clamp 0–100.
- Feature: simpan fuel log menaikkan odometer bila lebih tinggi; tidak menurunkan bila
  lebih rendah.
- Feature: tandai selesai dengan upload foto tersimpan & tervalidasi (tolak non-gambar).
- Semua 40 test lama harus tetap hijau.

---

## 13. Risiko

| Risiko | Mitigasi |
|--------|----------|
| Prediksi terasa "ML" padahal heuristik | Label jujur "estimasi dari rata-rata km/hari", bukan klaim AI |
| Kalkulasi konsumsi butuh disiplin full-tank | Checkbox tank penuh + pesan bila data kurang |
| Upload foto → keamanan/ukuran | Validasi image + maks 2MB, simpan di storage publik terpisah |
| Skor kesehatan sewenang-wenang | Formula didokumentasikan & ditandai tunable, bukan angka ajaib |
| Sidebar makin panjang (9+ item) | Urut logis; prediksi/skor/perhatian menyatu di dashboard, bukan menu baru |
