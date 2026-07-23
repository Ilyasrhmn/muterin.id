# Real-Time Riding Map + Road-Following Route Planner  Design Spec

## Latar Belakang

Muterin punya 3 fitur peta: Peta Rute (riwayat trip GPS), Peta Titik (pin lokasi), dan Peta Rencana (rencana rute). Dua gap ditemukan lewat perbandingan dengan referensi kompetitor (Ridelink/Kurviger):

1. **Halaman Riding (rekam perjalanan)**  GPS tracking-nya sebenarnya *sudah* real-time (`navigator.geolocation.watchPosition` di `public/js/trip-recorder.js`, jarak dihitung live pakai Haversine formula), tapi **tidak ada peta sama sekali** yang ditampilkan saat riding  cuma angka jarak & durasi. User gak bisa lihat posisinya di peta atau jalur yang sudah dilewati secara visual selagi jalan.

2. **Peta Rencana (`resources/views/map/plans.blade.php` + `public/js/map-plans.js`)**  saat ini cuma menyambungkan titik-titik yang diklik user dengan garis lurus (`L.polyline`), mengabaikan bentuk jalan asli sama sekali. Referensi Kurviger/Ridelink menghitung rute yang benar-benar mengikuti jalan lewat routing engine.

## Keputusan dari Sesi Brainstorming

- **Routing engine**: OpenRouteService (ORS), profil `cycling-regular` (paling dekat dengan karakteristik motor  menghindari jalan tol/bebas-motor, dibanding profil mobil biasa). API key disimpan di `.env` sebagai `ORS_API_KEY`, sudah didaftarkan user.
- **Arsitektur panggilan API**: backend proxy (Approach A)  Laravel yang manggil ORS di server side, API key tidak pernah dikirim ke browser. Endpoint baru `POST /map/route` dipakai baik untuk preview live (sebelum simpan) maupun saat menyimpan rencana.
- **Halaman Riding**: cukup tambah peta live (posisi sekarang + jalur yang sudah dilewati, update otomatis). Tidak ada perubahan lain (kecepatan/elevasi eksplisit di-skip  di luar scope, YAGNI).
- **Peta Rute** (riwayat trip) dan **Peta Titik** (pin lokasi)  **tidak berubah**. Peta Rute sudah menampilkan jalur GPS asli yang direkam (bukan garis lurus buatan), jadi sudah sesuai. Peta Titik tidak melibatkan routing sama sekali.

## Arsitektur

### 1. Konfigurasi

`config/services.php`  tambah entri:
```php
'ors' => [
    'key' => env('ORS_API_KEY'),
],
```
Mengikuti pola yang sudah dipakai untuk Postmark/Resend/AWS di file yang sama.

### 2. `RouteService` (baru)  `app/Services/RouteService.php`

Pure PHP service, method-injected seperti service lain di project ini (`OdometerService`, `HealthScoreService`, dst).

```
route(array $waypoints): array
```
- Input: `$waypoints`  array minimal 2 elemen, tiap elemen `[lat, lng]` (float).
- Panggil ORS Directions API (`POST https://api.openrouteservice.org/v2/directions/cycling-regular/geojson`), header `Authorization: <key dari config>`, body `{"coordinates": [[lng,lat], [lng,lat], ...]}` (ORS pakai urutan lng,lat  kebalikan dari konvensi lat,lng yang dipakai project ini, jadi service ini yang menangani konversi, konsumen tetap pakai lat,lng).
- Response ORS berupa GeoJSON LineString  service mengekstrak `geometry.coordinates` (dikonversi balik ke `[lat,lng]`), `properties.summary.distance` (meter → km), `properties.summary.duration` (detik → menit dibulatkan).
- Return: `['geometry' => [[lat,lng], ...], 'distance_km' => float, 'duration_minutes' => int]`.
- Timeout request 8 detik (`Http::timeout(8)`)  routing call yang menggantung tidak boleh membuat request pengguna menggantung lama.
- Kalau ORS mengembalikan error (rute tidak ditemukan antar titik, request tidak valid, quota habis, timeout)  lempar `App\Exceptions\RouteNotFoundException` dengan pesan yang bisa ditampilkan langsung ke user (bukan pesan teknis ORS mentah).

### 3. Endpoint baru

`POST /map/route` (dalam grup `auth` middleware, sejalan dengan route map lain) → `MapController::previewRoute()`.

```php
public function previewRoute(Request $request, RouteService $routing)
{
    $data = $request->validate([
        'waypoints' => 'required|array|min:2',
        'waypoints.*' => 'required|array|size:2',
        'waypoints.*.0' => 'required|numeric|between:-90,90',
        'waypoints.*.1' => 'required|numeric|between:-180,180',
    ]);

    try {
        return response()->json($routing->route($data['waypoints']));
    } catch (RouteNotFoundException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

Endpoint ini **stateless**  tidak menyimpan apa pun, murni proxy+kalkulasi. Dipakai baik untuk preview (tiap kali user nambah titik) maupun sesaat sebelum simpan (untuk mendapatkan geometry final yang disimpan).

### 4. Data model  `route_plans`

Migration baru, tambah 3 kolom nullable ke tabel yang sudah ada:
```php
Schema::table('route_plans', function (Blueprint $table) {
    $table->json('route_geometry_json')->nullable()->after('points_json');
    $table->decimal('distance_km', 8, 2)->nullable()->after('route_geometry_json');
    $table->unsignedInteger('duration_minutes')->nullable()->after('distance_km');
});
```
`points_json` (waypoint yang diklik user) tetap seperti sekarang  tidak diubah, tetap jadi input untuk re-routing kalau suatu saat dibutuhkan. `route_geometry_json` menyimpan hasil ORS supaya membuka rencana tersimpan **tidak perlu panggil ORS lagi** (hemat kuota harian, lebih cepat, tetap bisa diakses walau ORS sedang down).

`RoutePlan` model  tambah 3 kolom ke `$fillable` dan cast `route_geometry_json` sebagai `array`.

### 5. `MapController::storePlan()`  dimodifikasi

Frontend sudah punya geometry+distance+duration dari hasil preview (lihat bagian 6)  `storePlan()` **tidak memanggil ORS lagi**. Validasi payload menambah field `route_geometry_json` (array of `[lat,lng]`, required), `distance_km` (numeric, required), `duration_minutes` (integer, required), lalu simpan langsung bersama `points_json`. Ini menghindari pemanggilan ORS ganda (sekali saat preview, sekali lagi saat simpan) untuk request yang sama.

### 6. Peta Rencana  alur baru (`map-plans.js` dirombak)

- Klik pertama di peta → marker hijau (titik awal), tidak ada request apa pun (masih butuh minimal 2 titik).
- Klik kedua → marker merah (titik tujuan) → otomatis panggil `POST /map/route` dengan 2 waypoint ini → gambar `route_geometry_json` hasilnya sebagai polyline (ganti pola garis lurus yang sekarang).
- Klik ketiga dst → titik tambahan (via-point) di antara urutan → route dihitung ulang lewat semua titik berurutan (ORS Directions API native mendukung banyak coordinate dalam satu request, jadi ini gratis dari sisi implementasi  tidak perlu logic tambahan).
- Loading state: saat menunggu response ORS, tombol/area peta dikasih indikator singkat (`Menghitung rute...`)  request biasanya < 1 detik tapi tetap perlu ada, jangan biarkan UI diam tanpa feedback.
- Error state: kalau ORS gagal (rute gak ditemukan / API down), tampilkan pesan error jelas di atas peta, **jangan** diam-diam fallback ke garis lurus (user harus tahu itu bukan rute asli kalau memang gagal).
- Tombol "Simpan Rencana"  sama seperti sekarang, tapi payload sekarang juga menyertakan geometry+distance+duration yang sudah dihitung (dari state terakhir di frontend), jadi `storePlan()` tidak perlu manggil ORS ulang saat simpan (sudah dipanggil sewaktu preview)  **koreksi dari desain awal**: bukan "panggil ORS lagi saat simpan", tapi kirim hasil preview terakhir sebagai bagian dari payload simpan, backend cukup validasi bentuknya lalu simpan langsung. Ini menghemat 1 panggilan ORS per simpan.
- Melihat rencana tersimpan (`data-view-plan`)  gambar `route_geometry_json` yang sudah tersimpan (tidak panggil ORS lagi sama sekali).

### 7. Halaman Riding  peta live

`resources/views/riding/index.blade.php`  tambah elemen peta (`<div id="ride-map" style="height: 40vh"></div>`) di atas tampilan angka jarak/durasi yang sudah ada, plus include Leaflet CSS/JS (pola sama seperti halaman map lain).

`public/js/trip-recorder.js`  modifikasi minimal, tanpa mengubah logic GPS/distance yang sudah benar:
- Saat `start()`: inisialisasi `window.MuterinMap.init('ride-map')`, buat polyline kosong + marker posisi.
- Di dalam `onPos(pos)` (yang sudah dipanggil tiap update GPS): tambah `marker.setLatLng(p)`, `polyline.addLatLng(p)`, `map.panTo(p)`  3 baris tambahan, tidak mengubah logic distance/haversine yang sudah ada.
- Saat halaman pertama load (sebelum tekan "Mulai"): peta tetap muncul, centered ke lokasi user kalau browser sudah kasih izin GPS, atau default center kalau belum.

## Error Handling

- ORS timeout/down/quota habis → pesan Indonesia jelas: *"Gagal menghitung rute jalan. Coba lagi sebentar."*  ditampilkan di UI, tidak fallback diam-diam ke garis lurus.
- Waypoint tidak valid (di luar rentang lat/lng, kurang dari 2 titik) → validasi Laravel standar, 422 dengan pesan field-level.
- GPS ditolak di halaman Riding → sudah ditangani (`onErr` yang sudah ada), tidak berubah  peta live cuma aktif kalau GPS aktif, sejalan dengan behavior sekarang.

## Testing

- `tests/Unit/RouteServiceTest.php`  `Http::fake()` untuk mock response ORS sukses (assert geometry/distance/duration ter-parse benar, termasuk konversi lng,lat→lat,lng) dan gagal (assert `RouteNotFoundException` dilempar).
- `tests/Feature/MapControllerTest.php` (extend)  test endpoint `/map/route`: auth required, validasi waypoints, response sukses (pakai `Http::fake()`), response 422 saat routing gagal.
- `storePlan()`  extend test yang sudah ada untuk memastikan `route_geometry_json`/`distance_km`/`duration_minutes` tersimpan dari payload.
- Peta live di halaman Riding  murni frontend, tidak ada logic backend baru, diverifikasi manual di browser (tidak ada unit test baru untuk ini, sejalan dengan bagian JS lain di project yang juga tidak diuji otomatis).

## Yang Sengaja Tidak Dikerjakan

- Tidak ada address/nama-tempat search (geocoding)  user tetap klik langsung di peta seperti sekarang, cuma hasilnya sekarang road-following. Menambah search box adalah fitur terpisah, di luar scope brainstorming ini.
- Tidak ada info kecepatan/elevasi real-time di halaman Riding  sudah diputuskan skip saat brainstorming.
- Peta Rute (riwayat trip) dan Peta Titik (pin lokasi) tidak disentuh  sudah sesuai kebutuhan, tidak melibatkan routing engine.
- Tidak self-host routing server  di luar skala project saat ini (lihat perbandingan approach di sesi brainstorming).
- Tidak ada retry otomatis kalau ORS gagal  cukup pesan error yang jelas, user bisa coba klik ulang.

## Risiko

- **Kuota ORS 2.000 request/hari**  dengan pola "panggil sekali per klik titik baru saat preview, tidak dipanggil lagi saat lihat rencana tersimpan," penggunaan wajar seharusnya jauh di bawah limit. Tidak ada rate-limiting tambahan di sisi Muterin untuk saat ini  kalau nanti jadi masalah nyata, itu tuning terpisah.
- **Profil `cycling-regular` bukan profil motor asli**  ORS tidak punya profil motor, ini adalah pendekatan terbaik yang tersedia (dikonfirmasi saat brainstorming), bukan solusi sempurna.
