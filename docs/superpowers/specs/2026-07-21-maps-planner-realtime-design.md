# Google-Maps-Style Route Planner + Geocoding Search + Styled Dialogs + Real-Time Trip Persistence  Design Spec

## Latar Belakang

Sesudah ronde routing sebelumnya (`2026-07-21-realtime-routing`), Peta Rencana sudah bisa menggambar rute jalan asli lewat OpenRouteService, dan halaman Riding sudah menampilkan peta live. Tapi masih ada gap dibanding referensi Google Maps / Kurviger / Ridelink (screenshot user):

1. **Tidak ada pencarian tempat.** User cuma bisa klik di peta; tidak bisa mengetik nama tempat untuk menuju ke sana.
2. **Popup input pakai dialog browser bawaan.** "Nama rencana rute?", "Hapus titik ini?", dll. muncul sebagai popup abu-abu browser (`window.prompt`/`confirm`), tidak sesuai style aplikasi.
3. **Klik titik langsung jadi waypoint tanpa konfirmasi/info.** Referensi (Google Maps, Ridelink screenshot 3 & 5) menampilkan panel informasi lokasi dulu (nama/alamat) dengan tombol konfirmasi "jadikan titik awal/tujuan".
4. **Tidak ada panel ringkasan rute.** Setelah rute dihitung, referensi (screenshot 4) menampilkan panel dengan label titik awal, titik tujuan, jarak, dan durasi  mirip panel arah Google Maps.
5. **Riding belum tersimpan real-time.** GPS dilacak real-time di layar, tapi trip hanya disimpan ke server saat user menekan "Selesai". Kalau HP crash / browser tertutup di tengah perjalanan, seluruh data trip hilang. Ini berbeda dari Strava (yang menyimpan/sync selama ride).

## Keputusan dari Sesi Brainstorming

- **Search:** pakai ORS Geocoding, dipicu saat submit (Enter / tombol cari), bukan tiap ketikan  hemat kuota ORS (2000/hari, dibagi dengan routing).
- **Model waypoint:** titik awal + titik tujuan sebagai peran utama (tombol eksplisit "Jadikan Titik Awal" / "Jadikan Titik Tujuan"), plus kemampuan menambah titik singgah (via-point) di antaranya  persis alur Google Maps.
- **Info panel:** klik di peta → reverse-geocode → tampilkan nama/alamat lokasi + koordinat + tombol konfirmasi.
- **Modal:** ganti SEMUA popup browser bawaan (Peta Rencana, Titik Saya, BBM) dengan satu komponen dialog reusable ber-style.
- **Real-time riding:** ya, tambahkan checkpoint auto-save selama perjalanan + recovery kalau ada trip yang tidak sempat diselesaikan.
- **Warna & pola:** ikut token Muterin yang sudah ada (primary teal, dst.), pola proxy backend seperti `RouteService`, interaktivitas pakai vanilla JS / Alpine (tidak menambah dependency baru selain yang sudah dipakai: Leaflet, Alpine, GSAP).

## Cakupan & Batas Sub-Proyek

Spec ini mencakup 4 bagian. Saat penulisan plan, bagian ini akan dipecah menjadi **2 plan berurutan** agar tiap plan tidak terlalu besar:

- **Plan 1  "Maps Planner Google-Style"**: Part A (Dialog), Part B (Geocoding), Part C (Planner UX). Tiga bagian ini saling bergantung (Planner memakai Dialog untuk simpan-nama, dan Geocoding untuk search + reverse).
- **Plan 2  "Real-Time Trip Persistence"**: Part D. Subsistem terpisah (infrastruktur trip), tidak menyentuh Planner.

---

## Part A  Komponen Dialog Reusable (app-wide)

### Tujuan
Menggantikan `window.prompt()`, `window.confirm()`, `window.alert()` di seluruh aplikasi dengan modal ber-style yang konsisten, dengan API async yang bisa dipanggil dari file JS biasa (`public/js/*.js`) maupun handler inline Blade.

### Komponen
- **Blade partial**: `resources/views/components/ui/dialog.blade.php`  markup modal (backdrop + kartu) yang tersembunyi secara default (`x-cloak`/`hidden`), disertakan sekali di `resources/views/layouts/app.blade.php` tepat sebelum `</body>` sehingga tersedia di semua halaman terautentikasi.
- **JS controller**: `public/js/dialog.js`  mengekspos `window.MuterinDialog` dengan:
  - `MuterinDialog.confirm(message, {confirmText, cancelText, danger}) : Promise<boolean>`  resolve `true` jika user menekan konfirmasi, `false` jika batal/backdrop/Esc.
  - `MuterinDialog.prompt(message, {label, placeholder, defaultValue, confirmText, extra}) : Promise<string|object|null>`  mode dasar: satu field teks; resolve string input jika dikonfirmasi (di-`trim`, tidak boleh kosong → tombol konfirmasi disabled saat kosong), `null` jika batal. Jika opsi `extra: {label, placeholder}` diberikan, modal menampilkan field kedua opsional (textarea) dan resolve `{value, extra}` (extra boleh string kosong). Ini dipakai halaman Titik Saya (judul wajib + catatan opsional) dalam satu modal.
  - `MuterinDialog.alert(message) : Promise<void>`  satu tombol OK.
- Modal mendukung: tutup via tombol Batal, klik backdrop, tombol Esc; fokus otomatis ke input saat mode prompt; Enter di input = konfirmasi.
- Styling: token Muterin (`bg-surface`, `rounded-2xl`, `border-border`, tombol `x-ui.button` variant `primary`/`outline`/`accent`). Varian `danger: true` pakai tombol merah (accent) untuk aksi hapus.

### Rollout (ganti native → MuterinDialog)
- `public/js/map-plans.js`: `prompt('Nama rencana rute?')` → `MuterinDialog.prompt(...)`; `confirm('Hapus rencana ini?')` → `MuterinDialog.confirm(...)`; `alert(...)` → `MuterinDialog.alert(...)`.
- `public/js/map-pins.js`: dua `prompt` berurutan (judul, lalu catatan) diganti satu `MuterinDialog.prompt('Judul titik?', {extra: {label:'Catatan (opsional)', placeholder:'...'}})` yang mengembalikan `{value, extra}`; `confirm('Hapus titik ini?')` → `MuterinDialog.confirm(...)`.
- `resources/views/bbm/index.blade.php`: form hapus `onsubmit="return confirm(...)"` → tombol yang memanggil `MuterinDialog.confirm(...)` lalu submit form jika true.

### Testing
Komponen dialog murni frontend (tidak ada logic backend). Diverifikasi manual di browser oleh controller. Tidak ada unit test PHP baru untuk Part A.

---

## Part B  Geocoding (Search + Reverse), Backend Proxy

### `GeocodingService` (baru)  `app/Services/GeocodingService.php`
Pure PHP service, method-injected (pola sama seperti `RouteService`). API key ORS via `config('services.ors.key')` (sudah ada).

- `search(string $query, ?float $focusLat = null, ?float $focusLng = null): array`
  - Panggil `GET https://api.openrouteservice.org/geocode/search` dengan `text=<query>`, `size=5`, dan jika `focusLat/focusLng` ada → `focus.point.lat` / `focus.point.lon` (biar hasil terdekat dari titik fokus muncul lebih dulu, ala Google Maps).
  - Header `Authorization: <key>`, `Http::timeout(8)`.
  - Return array of `['label' => string, 'lat' => float, 'lng' => float]` dari `features[].properties.label` dan `features[].geometry.coordinates` ([lng,lat] → dikonversi ke lat/lng). Array kosong jika tidak ada hasil (bukan exception  "tidak ketemu" itu hasil valid, bukan error).
  - Jika HTTP gagal (API down/quota) → lempar `App\Exceptions\GeocodingException` dengan pesan Indonesia: `"Gagal mencari lokasi. Coba lagi sebentar."`
- `reverse(float $lat, float $lng): array`
  - Panggil `GET .../geocode/reverse` dengan `point.lat`, `point.lon`, `size=1`.
  - Return `['label' => string, 'lat' => float, 'lng' => float]` dari feature pertama; jika tidak ada feature → `['label' => 'Lokasi tanpa nama', 'lat' => $lat, 'lng' => $lng]` (fallback tetap valid supaya panel info bisa tampil dengan koordinat walau tak ada nama).
  - Jika HTTP gagal → lempar `GeocodingException` dengan pesan yang sama.

### `App\Exceptions\GeocodingException` (baru)
Kelas exception kosong seperti `RouteNotFoundException`.

### Endpoint (dalam grup `auth`, di `routes/web.php`)
- `GET /map/geocode/search?q=<query>&lat=<focusLat>&lng=<focusLng>` → `MapController::geocodeSearch()`
  - Validasi: `q` required|string|min:2; `lat`/`lng` nullable|numeric.
  - Return JSON: `{ results: [ {label, lat, lng}, ... ] }` (200); atau `{ error: "..." }` (422) saat `GeocodingException`.
- `GET /map/geocode/reverse?lat=<lat>&lng=<lng>` → `MapController::geocodeReverse()`
  - Validasi: `lat` required|numeric|between:-90,90; `lng` required|numeric|between:-180,180.
  - Return JSON: `{ label, lat, lng }` (200); atau `{ error: "..." }` (422).

### Testing
- `tests/Unit/GeocodingServiceTest.php`  `Http::fake()`: search sukses (parse label + konversi lng,lat→lat,lng, urutan koordinat dicek eksplisit), search kosong (return `[]`, tidak throw), search HTTP-fail (throw `GeocodingException`), reverse sukses, reverse tanpa feature (fallback label), reverse HTTP-fail (throw).
- `tests/Feature/MapTest.php` (extend)  endpoint `/map/geocode/search` & `/map/geocode/reverse`: auth required, validasi (q min 2; lat/lng range), sukses (via `Http::fake()`), 422 saat gagal.

---

## Part C  Peta Rencana ala Google Maps

Rombak `public/js/map-plans.js` (full rewrite) + `resources/views/map/plans.blade.php`. Backend planner (`storePlan`, `previewRoute`) tidak berubah  sudah menyimpan geometry/distance/duration dari ronde sebelumnya.

### Layout halaman
Peta memenuhi area utama. Di atas peta: **search box** (input + tombol cari). Panel-panel (info lokasi, ringkasan rute) muncul sebagai kartu melayang (overlay) di atas peta, bukan kolom terpisah  mendekati Google Maps. Daftar "Rencana Tersimpan" tetap di panel samping/bawah seperti sekarang.

### Alur interaksi
1. **Search**: ketik nama tempat → Enter / tombol cari → `GET /map/geocode/search?q=...&lat=<center>&lng=<center>` → dropdown hasil (label). Klik hasil → peta `flyTo` ke koordinat → tampilkan **panel info lokasi** untuk titik itu.
2. **Klik peta**: `GET /map/geocode/reverse?lat=&lng=` → tampilkan **panel info lokasi**: nama/alamat (label) + koordinat + dua tombol: **"Jadikan Titik Awal"** dan **"Jadikan Titik Tujuan"**. Saat menunggu reverse, panel menampilkan "Memuat lokasi...".
3. **Set titik awal** → marker hijau + label titik awal tersimpan. **Set titik tujuan** → marker merah + label tersimpan.
4. Begitu awal + tujuan lengkap → otomatis panggil `POST /map/route` (yang sudah ada) dengan urutan `[awal, ...via, tujuan]` → gambar rute jalan asli → tampilkan **panel ringkasan rute**.
5. **Panel ringkasan rute** (mirip screenshot 4 / Google Maps): baris titik awal (label), baris titik tujuan (label), total jarak (`X km`), total durasi (`Y jam Z menit`), tombol **Simpan Rencana** dan **Reset**. Simpan → `MuterinDialog.prompt('Nama rencana rute?')` → `POST /map/plans` dengan `points` (urutan awal→via→tujuan), `route_geometry`, `distance_km`, `duration_minutes`.
6. **Titik singgah (via)**: setelah rute ada, tombol "Tambah Titik Singgah" mengaktifkan mode; klik berikutnya (via reverse-geocode + panel info) menambah via-point yang disisipkan sebelum titik tujuan; rute dihitung ulang lewat semua titik berurutan. (Titik singgah ditandai marker kuning.)
7. **Guard race condition**: pemanggilan `/map/route` memakai sequence guard yang sama seperti ronde sebelumnya (response basi tidak menimpa yang baru).
8. **Reset**: hapus semua titik/marker/rute/panel, kembali ke keadaan awal.
9. **Melihat rencana tersimpan**: klik item → gambar `route_geometry_json` tersimpan (tidak memanggil ORS ulang), tampilkan panel ringkasan read-only (label pakai koordinat titik awal/akhir dari `points_json`, atau "Titik 1 / Titik N" jika reverse-geocode tidak disimpan  lihat catatan di bawah). Hapus → `MuterinDialog.confirm(...)`.

### Menyimpan label titik (opsional, keputusan)
Agar panel ringkasan rencana tersimpan bisa menampilkan nama titik (bukan cuma koordinat), tabel `route_plans` mendapat kolom nullable `start_label` dan `end_label` (string). Diisi dari label reverse-geocode/search saat menyimpan. Kalau kosong (mis. plan lama), UI fallback ke "Titik Awal"/"Titik Tujuan". Migration + `$fillable` ditambahkan; `storePlan` menerima dan menyimpan dua field ini (nullable, tidak wajib agar tidak memecah test lama).

### Testing
- Frontend murni untuk interaksi  diverifikasi manual di browser oleh controller (dengan panggilan ORS asli).
- `storePlan` extend: test bahwa `start_label`/`end_label` tersimpan saat dikirim, dan plan tetap bisa disimpan tanpa keduanya (backward-compat).

---

## Part D  Real-Time Trip Persistence

### Skema
Tambah kolom `status` ke tabel `trips`: `enum('recording','completed')`, default `'completed'` (agar semua trip lama otomatis dianggap selesai). Migration + `$fillable` + (opsional) cast.

### Alur
1. **Mulai (`start()` di `trip-recorder.js`)**: setelah izin GPS OK, `POST /trips/start` (endpoint baru) dengan `motorcycle_id` → membuat trip draft `status='recording'`, `distance_km=0`, `path_json=[]`, `started_at=now()`, `ended_at=null` → return `trip_id`. Simpan `trip_id` di state JS.
2. **Selama jalan**: setiap ~10 detik (interval, hanya jika ada titik baru sejak checkpoint terakhir), `PATCH /trips/{id}/checkpoint` dengan `distance_km`, `duration_seconds`, `path` terkini. Odometer **tidak** disentuh di sini. Checkpoint gagal (offline sesaat) tidak menghentikan rekaman  dicoba lagi di interval berikutnya.
3. **Selesai (`stop()`)**: `PATCH /trips/{id}/finish` dengan data final → set `status='completed'`, `ended_at=now()` → **update odometer SEKALI** di sini via `OdometerService` (pola sama seperti `TripController::store` sekarang). Redirect ke dashboard.
4. **Recovery**: saat halaman Riding dimuat, controller mengecek apakah user punya trip `status='recording'` (nyangkut). Jika ada, tampilkan banner: "Ada perjalanan belum selesai (X km, direkam <waktu>)" dengan dua tombol: **Selesaikan** (`PATCH /trips/{id}/finish` dengan data terakhir yang tersimpan → completed + odometer update) dan **Buang** (`DELETE /trips/{id}` → hapus draft). Keduanya lewat `MuterinDialog.confirm`.
5. **Peta Rute** (`MapController::routesPage` + `data`): filter hanya `status='completed'` agar draft yang sedang berjalan / nyangkut tidak muncul sebagai rute selesai.

### Endpoint baru (`TripController`, dalam grup `auth`)
- `POST /trips/start` → `start()`: validasi `motorcycle_id`; cek ownership; buat draft; return `{trip_id}`.
- `PATCH /trips/{trip}/checkpoint` → `checkpoint()`: cek ownership; validasi `distance_km`, `duration_seconds`, `path`; update draft (tidak sentuh odometer); hanya berlaku jika `status='recording'`.
- `PATCH /trips/{trip}/finish` → `finish()`: cek ownership; validasi data final; set completed + `ended_at` + update odometer sekali; idempotent-guard: jika sudah `completed`, jangan update odometer lagi (hindari dobel hitung).
- `DELETE /trips/{trip}` → `destroy()`: cek ownership; hanya boleh hapus jika `status='recording'` (draft); hapus.
- Endpoint lama `POST /trips` (`store`) dipertahankan atau di-deprecate? → **Diganti**: alur baru start→checkpoint→finish menggantikannya. `store()` lama dihapus dan test lama yang memakainya diperbarui ke alur baru. (Test `TripTest`/`TripControllerTest` yang menguji pembuatan trip + odometer diperbarui untuk memakai `finish`.)

### Odometer  pencegahan dobel hitung
Hanya `finish()` yang memanggil `OdometerService::record()`. `checkpoint()` tidak pernah. `finish()` mengecek `status` sebelum update: jika sudah `completed`, no-op untuk odometer. Ini memastikan satu perjalanan hanya menaikkan odometer satu kali, meski `finish` terpanggil dua kali (mis. dari tombol Selesai lalu dari banner recovery).

### `trip-recorder.js` (modifikasi)
Logic GPS/Haversine/peta live yang sudah ada dipertahankan. Yang ditambah: panggilan `start` → simpan `trip_id`; interval checkpoint; `stop` memanggil `finish` (bukan `store`). Logic perhitungan jarak tidak berubah.

### Testing
- `tests/Feature/TripTest.php` / `TripControllerTest.php` (perbarui + tambah): `start` membuat draft `recording` tanpa mengubah odometer; `checkpoint` memperbarui path/distance tanpa mengubah odometer; `finish` menandai completed + menaikkan odometer sekali; `finish` kedua kali tidak menaikkan odometer lagi (idempotent); `destroy` hanya menghapus draft `recording`; ownership 403 untuk semua; Peta Rute hanya menampilkan `completed`.
- Recovery banner (frontend) diverifikasi manual di browser.

---

## Error Handling (ringkasan)
- Geocoding gagal (API down/quota) → pesan Indonesia jelas di UI (`MuterinDialog.alert` atau status inline), tidak diam-diam.
- Search tanpa hasil → tampilkan "Tidak ada hasil" (bukan error).
- Reverse tanpa nama → fallback "Lokasi tanpa nama" + koordinat, tetap bisa dijadikan titik.
- Checkpoint gagal saat offline → tidak menghentikan rekaman; dicoba lagi; data tetap di memori sampai checkpoint berikutnya berhasil atau user menekan Selesai.
- Odometer tidak pernah dobel dihitung (guard `status` di `finish`).

## Yang Sengaja Tidak Dikerjakan
- Tidak ada autocomplete search-as-you-type (dipicu submit demi hemat kuota).
- Tidak ada "Prefer Curvy" / "Avoid Highways/Tolls" toggle (fitur Kurviger berbayar; di luar scope  ORS profil `cycling-regular` sudah menghindari tol/jalan bebas-motor secara default).
- Tidak ada import/export GPX.
- Tidak ada data POI/event di panel info (itu data proprietary Ridelink; kita hanya reverse-geocode alamat).
- Tidak ada live-sharing posisi ke user lain saat riding  untuk sekarang real-time hanya untuk persistence milik sendiri. **Catatan roadmap:** fitur sosial internal ala Strava (berbagi rute/ride ke sesama pengguna Muterin, feed, dsb.) direncanakan sebagai pengembangan bertahap di masa depan; desain persistence trip di Part D sengaja dibuat bersih (satu trip = satu record `completed` dengan path lengkap) supaya jadi fondasi yang siap dibangun untuk fitur sosial nanti, tanpa harus dibangun sekarang.
- Tidak ada peta live "follow" untuk Peta Rute (tetap galeri riwayat trip `completed`).

## Risiko
- **Kuota ORS** kini dipakai 3 layanan (routing + geocode search + reverse). Search dipicu submit dan reverse hanya saat klik/pilih titik, jadi pemakaian wajar tetap jauh di bawah 2000/hari untuk satu user dev. Kalau jadi masalah nyata → tuning terpisah (mis. cache reverse per-koordinat, rate limit sisi Muterin).
- **Checkpoint interval 10 detik** adalah tuning-knob (`ponytail:`), bukan hukum  di device asli bisa disesuaikan dengan trade-off keandalan vs jumlah request.
- **Reverse-geocode di Indonesia** kadang kurang detail (Pelias/OSM coverage bervariasi). Fallback "Lokasi tanpa nama" memastikan alur tidak macet.
