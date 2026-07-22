# Peta Komunitas (Community Safety Map) — Design Spec

**Tanggal:** 2026-07-22
**Status:** Approved (brainstorming)
**Subsistem:** 1 dari 2 (spec ke-2 = "Titik Saya" jadi saved-list + momen privat, dikerjakan terpisah)

## Tujuan

Peta keamanan berbasis komunitas: setiap pengguna Amicta bisa menandai titik di
peta (jalan sepi, gelap, rawan, rusak, banjir, atau momen) lengkap dengan foto &
deskripsi, dan **semua pengguna lain** bisa melihatnya. Kasus inti: pengendara
(terutama perempuan) sering diarahkan navigasi ke jalan sepi/rawan tanpa tahu
sebelumnya — fitur ini meng-crowdsource informasi itu supaya bisa dihindari.

## Yang membedakan dari kompetitor

1. **Integrasi ke Peta Rencana** — saat user merencanakan rute, sistem memperingatkan
   kalau rute melewati titik komunitas ("Rutemu lewat 2 titik jalan sepi"). Google
   Maps/Waze tidak menyambungkan data keamanan komunitas ke planner-nya sendiri.
2. **Sistem "Masih di sini?"** — titik bisa dikonfirmasi user lain, membangun
   kepercayaan dan membersihkan info basi secara otomatis.
3. **Konteks waktu + lapor anonim** — titik bisa spesifik ke waktu (siang/malam) dan
   pelapor bisa menyembunyikan identitasnya, langsung menjawab isu keamanan.

## Batasan Global

- Tanpa infrastruktur berbayar. Foto disimpan di **disk lokal** (`storage/app/public`
  + `storage:link`). Peta = OpenStreetMap (gratis, sudah dipakai). Tidak ada
  WebSocket/broadcasting.
- Model "real-time" = **fresh-on-load + auto-refresh polling ~30 detik**, bukan
  live-push. (`// ponytail:` interval 30s adalah tuning-knob.)
- Ikuti pola yang sudah ada: service-class untuk logika non-trivial, `MapController`
  untuk endpoint, `public/js/*.js` dengan cache-bust `?v=filemtime`, komponen
  `AmictaDialog` untuk semua dialog (tidak ada `alert/confirm/prompt` native),
  `L.popup()` untuk menu klik di peta.
- Titik komunitas **terpisah total** dari `map_pins` (privat). Tabel & halaman baru.
- Semua string UI berbahasa Indonesia, konsisten dengan halaman lain.

---

## Arsitektur

Halaman publik baru `/peta/komunitas` (`map.community`). Titik disimpan di tabel
`community_pins`; konfirmasi di `community_pin_confirmations`. Logika non-trivial
(kelayakan tampil titik, kedekatan titik ke rute) diisolasi di satu service
`CommunityPinService`. Peta Rencana yang sudah ada memanggil satu endpoint baru
untuk mengambil titik komunitas yang dekat dengan rute yang baru dihitung.

### Unit & tanggung jawab

| Unit | Tanggung jawab |
|---|---|
| `community_pins` (migration) | Skema titik komunitas |
| `community_pin_confirmations` (migration) | Skema suara "masih di sini?" |
| `CommunityPin` (model) | Relasi + scope `visible()` |
| `CommunityPinConfirmation` (model) | Relasi |
| `CommunityPinService` | `visiblePins()`, `confirm()`, `nearRoute()` |
| `CommunityController` | Halaman + endpoint JSON (index, store, confirm, near-route, destroy) |
| `map-community.js` | UI peta: tambah titik, popup kartu, filter, auto-refresh |
| `map/community.blade.php` | Layout halaman |
| `map-plans.js` (modifikasi) | Panggil `near-route`, tampilkan peringatan |

---

## Data Model

### `community_pins`

```php
Schema::create('community_pins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('category', ['sepi', 'gelap', 'rawan', 'rusak', 'banjir', 'momen']);
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('photo_path')->nullable();
    $table->enum('time_context', ['siang', 'malam', 'kapanpun'])->default('kapanpun');
    $table->boolean('is_anonymous')->default(false);
    $table->integer('confirm_count')->default(0); // signed: "masih" - "udah nggak", bisa negatif
    $table->timestamps();
});
```

### `community_pin_confirmations`

```php
Schema::create('community_pin_confirmations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('community_pin_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->boolean('still_there'); // true = masih, false = udah nggak
    $table->timestamps();
    $table->unique(['community_pin_id', 'user_id']); // 1 user 1 suara
});
```

**`confirm_count`** = cache jumlah `still_there=true` dikurangi `still_there=false`.
Diperbarui setiap ada konfirmasi masuk/berubah, supaya query peta tidak berat.

### Aturan kelayakan tampil (`visible()` scope / `visiblePins()`)

Titik disembunyikan dari peta (tetap di DB) bila **usianya > 30 hari DAN
`confirm_count < 0`** (lebih banyak yang bilang "udah nggak" daripada "masih").
Semua titik ≤ 30 hari selalu tampil. Murni query, tidak ada cron/job.
`// ponytail:` ambang 30 hari & aturan meredup adalah tuning-knob; naikkan/ubah
kalau perlu.

---

## Alur & UI

### Halaman `/peta/komunitas`

Layout mirip `map/pins.blade.php` (peta besar kiri + panel daftar kanan) supaya
konsisten:

- **Toolbar atas:** dropdown filter kategori (Semua / Sepi / Gelap / Rawan / Rusak /
  Banjir / Momen), tombol "Lokasi Saya", legenda warna kategori.
- **Peta:** semua titik komunitas yang layak tampil, sebagai `L.circleMarker`
  berwarna per kategori.
- **Panel kanan:** daftar titik terbaru (judul, kategori, penandai/anonim, jumlah
  konfirmasi).

### Menambah titik

1. User klik lokasi di peta (atau "Lokasi Saya") → `L.popup()` "Tandai lokasi ini?".
2. Klik "Tandai" → buka dialog `AmictaDialog` form (perlu varian form multi-field):
   kategori, judul, deskripsi, konteks waktu, checkbox "Posting anonim", input foto
   (opsional).
3. Submit → `POST /peta/komunitas` (multipart, karena ada file). Server simpan,
   simpan foto ke `storage/app/public/community`, kembalikan pin JSON.
4. Titik langsung muncul di peta + panel.

> Catatan implementasi: `AmictaDialog` saat ini mendukung teks/prompt. Form
> multi-field + upload foto **tidak** dipaksakan lewat `AmictaDialog` kalau bikin
> komponennya jadi rumit. Boleh pakai panel/form inline di halaman (bukan dialog
> native) — keputusan final di tahap plan. Yang wajib: tidak ada `alert/confirm/prompt`
> native browser.

### Popup kartu titik (klik/hover marker)

Menampilkan: foto (kalau ada), judul, kategori (badge), deskripsi, konteks waktu,
**"Ditandai oleh <nama>"** atau **"Ditandai oleh pengguna anonim"**, jumlah
konfirmasi ("Dikonfirmasi 12 orang"), dan dua tombol **"Masih di sini"** /
**"Udah nggak"**. Tombol hapus hanya muncul untuk pemilik titik.

### Konfirmasi "Masih di sini?"

- Klik salah satu tombol → `POST /peta/komunitas/{pin}/confirm` body `{ still_there: bool }`.
- Server `updateOrCreate` konfirmasi (unique per user), lalu hitung ulang
  `confirm_count`. Kembalikan `confirm_count` baru. UI perbarui angka.

### Auto-refresh

`map-community.js` fetch `GET /peta/komunitas/data` (JSON semua titik layak tampil)
saat load dan tiap ~30 detik; render ulang marker yang berubah. `// ponytail:` polling
sederhana; ganti ke WebSocket hanya kalau benar-benar perlu.

### Integrasi Peta Rencana (pembeda utama)

Di `map-plans.js`, setelah rute berhasil dihitung (`maybeRoute` sukses), POST
geometry rute ke `POST /peta/komunitas/near-route` body `{ geometry: [[lat,lng],...] }`.
Server (`CommunityPinService::nearRoute`) kembalikan titik komunitas layak tampil
yang jaraknya ≤ 300m dari rute. UI tampilkan banner peringatan di panel Peta Rencana:
*"⚠️ Rutemu lewat N titik komunitas (2 sepi, 1 rawan). Lihat di peta?"* dan plot
titik-titik itu di peta rencana.

**Algoritma kedekatan** (`CommunityPinService::nearRoute`): untuk tiap titik, hitung
jarak Haversine minimum dari titik ke **vertex terdekat** pada polyline rute; kalau
≤ 300m, titik dianggap dekat rute. `// ponytail:` pakai jarak ke vertex terdekat
(bukan titik-ke-segmen) karena geometry ORS rapat (~tiap beberapa meter), jadi
aproksimasi ini cukup; naikkan ke titik-ke-segmen kalau ternyata meleset. Ambang
300m juga tuning-knob.

---

## Endpoint

| Method | URI | Nama | Fungsi |
|---|---|---|---|
| GET | `/peta/komunitas` | `map.community` | Halaman |
| GET | `/peta/komunitas/data` | `map.community.data` | JSON titik layak tampil (auto-refresh) |
| POST | `/peta/komunitas` | `map.community.store` | Tambah titik (multipart, foto opsional) |
| POST | `/peta/komunitas/{pin}/confirm` | `map.community.confirm` | Konfirmasi masih/nggak |
| POST | `/peta/komunitas/near-route` | `map.community.near-route` | Titik dekat rute |
| DELETE | `/peta/komunitas/{pin}` | `map.community.destroy` | Hapus titik (pemilik saja) |

Semua di grup `auth` yang sama dengan route peta lain.

### Validasi `store`

```php
'category'     => 'required|in:sepi,gelap,rawan,rusak,banjir,momen',
'lat'          => 'required|numeric|between:-90,90',
'lng'          => 'required|numeric|between:-180,180',
'title'        => 'required|string|max:255',
'description'  => 'nullable|string|max:2000',
'time_context' => 'required|in:siang,malam,kapanpun',
'is_anonymous' => 'boolean',
'photo'        => 'nullable|image|max:4096', // 4MB, disk lokal
```

### Validasi `confirm`

```php
'still_there' => 'required|boolean',
```

### Validasi `near-route`

```php
'geometry'     => 'required|array|min:2',
'geometry.*'   => 'required|array|size:2',
'geometry.*.0' => 'required|numeric|between:-90,90',
'geometry.*.1' => 'required|numeric|between:-180,180',
```

## Otorisasi

- `store`/`confirm`: user login mana pun.
- `destroy`: hanya pemilik (`abort_unless($pin->user_id === auth()->id(), 403)`),
  pola sama dengan `destroyPin`.
- Foto ditampilkan lewat symlink publik `storage:link` (URL `/storage/community/...`).

## Penanganan Error

- Upload foto gagal/format salah → 422 dengan pesan validasi Laravel; UI tampilkan
  pesan di form.
- `near-route` dipanggil di background dari Peta Rencana; kalau gagal, **diam-diam
  dilewati** (jangan ganggu alur perencanaan rute yang sudah jalan). `// ponytail:`
  fitur peringatan bersifat tambahan, kegagalannya non-fatal.
- Konfirmasi ganda oleh user yang sama → `updateOrCreate` (ganti suara lama), bukan
  duplikat.

## Testing

Feature test (`tests/Feature/CommunityPinTest.php`):
- Titik tersimpan + foto ke disk (pakai `Storage::fake`).
- Titik terlihat oleh user lain (bukan cuma pembuat).
- `destroy` oleh non-pemilik → 403.
- Anonim menyembunyikan nama di payload `data`.
- Konfirmasi: 1 user 1 suara (`updateOrCreate`), `confirm_count` benar setelah
  campuran "masih"/"udah nggak".
- Aturan kelayakan tampil: titik > 30 hari dengan `confirm_count < 0` tidak muncul;
  titik baru selalu muncul.

Unit test (`tests/Unit/CommunityPinNearRouteTest.php`):
- `nearRoute`: titik ≤ 300m dari polyline masuk; titik > 300m tidak; polyline
  kosong/1 titik ditangani aman.

## Di luar cakupan (YAGNI — sengaja ditunda)

Heatmap zona, gamifikasi/badge kontributor, live-push WebSocket, komentar/thread per
titik, banyak foto per titik, moderasi admin. Semua bisa ditambah belakangan tanpa
membongkar desain ini.
