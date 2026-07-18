# Desain: Motor Maintenance Tracker (Amicta)

**Tanggal:** 2026-07-19
**Status:** Disetujui (menunggu review spec sebelum implementasi)

---

## 1. Ringkasan & Value Proposition

Aplikasi web untuk pengendara motor individu yang **otomatis menghitung jarak
tempuh tiap motor lewat GPS selama riding**, lalu memberi tahu secara proaktif
kapan tiap komponen (oli, ban, aki, servis rutin) perlu diganti — berdasarkan
akumulasi km riil, bukan tebak-tebakan atau harus baca odometer manual.

**Masalah:** Pengendara sering lupa/tidak tahu kapan harus ganti oli/ban/aki
karena patokannya km di odometer yang jarang dicek.

**Pembeda:** Trip recording GPS otomatis + manajemen perawatan multi-motor +
peta perjalanan (riwayat rute, pin momen & jalan rawan). Ini yang membuat
produk terasa sebagai **Sistem Informasi Manajemen**, bukan sekadar pengingat
oli.

**Target user:** Pengendara individu (B2C). Satu akun bisa punya banyak motor.

---

## 2. Konteks & Constraint

- **Platform:** Web app (wajib, sesuai track lomba "SIM & pengembangan web").
- **Deliverable lomba:** Prototype/MVP fungsional yang bisa didemokan.
- **Timeline:** < 2 minggu → wajib ada pembagian Core vs Stretch.
- **Tech stack:**
  - Backend: **Laravel** (auth, REST API, database MySQL/SQLite).
  - Frontend: **Blade + Alpine.js** (tanpa build step berat; GPS & peta murni
    client-side JS).
  - Peta: **Leaflet.js + OpenStreetMap** (gratis, tanpa API key/billing).
  - GPS distance: dihitung client-side dengan haversine antar titik
    `navigator.geolocation.watchPosition`, lalu di-POST ke Laravel.

---

## 3. Scope Tiering

### Core (WAJIB jalan pas demo)
1. Auth: register/login (Laravel Breeze), profil dasar.
2. Manajemen motor: CRUD motor, pilih motor aktif.
3. Item perawatan per motor: 4 item default berbasis km (Oli, Ban, Aki,
   Servis Rutin), interval bisa diedit, tandai selesai + biaya opsional.
4. Trip recording: pilih motor aktif → Mulai/Selesai → GPS hitung jarak →
   tambah ke odometer. Jalur GPS ikut disimpan.
5. Dashboard & status: progress bar tiap item, badge hijau/kuning/merah.
6. Riwayat: riwayat trip & riwayat perawatan (+ biaya).
7. In-app alert badge (ringan, masuk Core).

### Stretch (kalau waktu masih ada)
8. Peta Perjalanan (satu komponen Leaflet, 3 fungsi):
   a. Riwayat rute: gambar jalur GPS trip di peta.
   b. Planner sederhana: klik beberapa titik → simpan sebagai rencana rute
      (tanpa routing engine / preferensi jalan — hanya menghubungkan titik).
   c. CRUD pin Momen / Jalan Rawan / Sepi (lat-lng + kategori + icon +
      catatan).
9. Push notification browser saat item transisi ke kuning/merah.
10. Tombol "Cari Bengkel Terdekat" (deep-link Google Maps) saat status merah.
11. Export riwayat servis ke PDF.
12. Auto-detect motion sebagai pengganti tombol manual start/stop.

### DICORET (di luar scope, sengaja tidak dibangun)
- Item perawatan berbasis tanggal (pajak STNK, asuransi).
- Route planner dengan routing engine pihak ketiga (ala Kurviger/RideLink).
- Fitur sosial (feed, follow, leaderboard).
- Geofencing / fleet management (konteks B2B, bukan produk ini).

---

## 4. Model Data

```
users
  id, name, email, password, timestamps

motorcycles
  id, user_id (FK), nickname, brand, model, year,
  initial_odometer_km, current_odometer_km, is_active (bool), timestamps

maintenance_items
  id, motorcycle_id (FK), name, interval_km,
  last_service_odometer_km, timestamps
  // 4 baris default dibuat otomatis saat motor ditambahkan

maintenance_logs
  id, maintenance_item_id (FK), serviced_at_odometer_km,
  cost (nullable), serviced_at (date), note (nullable), timestamps

trips
  id, motorcycle_id (FK), distance_km, duration_seconds,
  path_json (nullable, array of [lat,lng]), started_at, ended_at, timestamps

map_pins   // Stretch
  id, user_id (FK), category (enum: moment|hazard|quiet),
  lat, lng, title, note (nullable), timestamps

route_plans   // Stretch
  id, user_id (FK), name, points_json (array of [lat,lng]), timestamps
```

**Status perawatan (dihitung, tidak disimpan):**
`km_terpakai = current_odometer_km - last_service_odometer_km`
`persen = km_terpakai / interval_km * 100`
- Hijau: < 80%
- Kuning: 80–100%
- Merah: > 100%

---

## 5. Alur Utama

**Trip recording:**
1. User buka halaman "Riding", pilih motor aktif.
2. Klik "Mulai Perjalanan" → JS mulai `watchPosition`, akumulasi jarak
   haversine tiap update posisi, tampilkan jarak & durasi live.
3. Auto-stop bila posisi tidak berubah > N menit (idle safety net).
4. Klik "Selesai" → POST {distance_km, duration, path} ke Laravel.
5. Laravel: buat record `trips`, tambah `distance_km` ke
   `motorcycles.current_odometer_km`.
6. Redirect ke dashboard, status perawatan ter-update otomatis.

**Tandai perawatan selesai:**
1. Dari dashboard/detail motor, klik "Tandai Selesai" pada item (mis. Oli).
2. Isi biaya (opsional) + tanggal.
3. Laravel: buat `maintenance_logs`, set
   `maintenance_items.last_service_odometer_km = current_odometer_km`.
4. Progress bar item reset ke hijau.

---

## 6. Struktur Komponen (Laravel)

- **Controllers:** Auth (Breeze), MotorcycleController, MaintenanceController,
  TripController, DashboardController. Stretch: MapPinController,
  RoutePlanController.
- **Models + relasi:** User hasMany Motorcycle; Motorcycle hasMany
  MaintenanceItem, Trip; MaintenanceItem hasMany MaintenanceLog.
- **Service:** `MaintenanceStatusService` — satu tempat hitung persen & warna
  status (dipakai dashboard, detail motor, notifikasi). Menghindari duplikasi
  logika status di banyak tempat.
- **Views (Blade):** layout + halaman dashboard, motor (list/form/detail),
  riding (trip recorder + Alpine/JS), riwayat, peta (Leaflet, Stretch).

---

## 7. Error Handling & Edge Case

- Izin GPS ditolak → tampilkan pesan jelas, trip tidak bisa dimulai.
- Sinyal GPS hilang di tengah trip → tetap akumulasi dari titik terakhir yang
  valid; abaikan lonjakan jarak tidak wajar (mis. > X km antar 1 update =
  outlier, di-skip).
- User lupa "Selesai" → idle auto-stop mencegah odometer melonjak.
- Interval km item = 0 atau kosong → validasi, tidak boleh 0 (hindari bagi nol).
- Hapus motor → cascade hapus item, trip, log terkait (soft-delete opsional).

---

## 8. Testing (minimal, sesuai timeline)

- Feature test: buat motor → 4 item default otomatis tercipta.
- Feature test: selesai trip → odometer bertambah sesuai distance.
- Unit test: `MaintenanceStatusService` menghasilkan warna benar di batas
  79%/80%/100%/101%.
- Manual test: alur GPS di device asli (tidak bisa di-unit-test).

---

## 9. Risiko

| Risiko | Mitigasi |
|--------|----------|
| GPS background di browser terbatas (tab harus foreground) | Manual start/stop, layar tetap nyala saat riding; jelaskan batasan di pitch |
| Fitur peta (Stretch) makan waktu | Dipisah tegas dari Core; demo tetap aman tanpa peta |
| Akurasi jarak GPS meleset | Filter outlier + kalibrasi manual odometer bila perlu |
| Push notification tidak konsisten antar browser/OS | Tetap Stretch; in-app badge sebagai fallback Core |
