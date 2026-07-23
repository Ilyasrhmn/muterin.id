# Titik Saya → Tempat Tersimpan + List (Saved Places)  Design Spec

**Tanggal:** 2026-07-22
**Status:** Approved (brainstorming)
**Subsistem:** 2 dari 2 (subsistem 1 = Peta Komunitas, sudah selesai)

## Tujuan

Ubah "Titik Saya" dari pin berkategori (rawan/sepi/momen) menjadi **tempat
tersimpan pribadi ala Google Maps**: user menyimpan tempat ke dalam **list**
(Favorit, Mau ke sana, Bengkel Langganan, atau list bikinan sendiri), tiap tempat
bisa punya foto + deskripsi (momen pribadi). List bisa dikustomisasi dengan
**icon + warna** pilihan user. Semua privat  cuma pemiliknya yang lihat.

## Latar belakang keputusan

Kategori keamanan (rawan/sepi) secara konsep pindah ke **Peta Komunitas** yang
sudah dibangun (data keamanan lebih berguna kalau dibagikan). Sisi privat fokus
ke "ngabadiin momen & menyimpan tempat"  makanya modelnya jadi saved-places +
list, bukan kategori. Satu tempat = satu list (bukan many-to-many) demi
kesederhanaan.

## Batasan Global

- Tanpa layanan berbayar. Foto → disk lokal (`storage/app/public/places` via disk
  `public` + `storage:link`). Peta OSM (sudah ada).
- Semua privat: setiap query dibatasi ke `auth()->id()`. Tidak ada sharing.
- Satu tempat = tepat satu list (`place_list_id` foreign key, bukan pivot).
- Icon marker pakai teknik `L.divIcon` Font Awesome yang sama dengan Peta Komunitas
  (`public/js/map-community.js`).
- Tidak ada `alert/confirm/prompt` native  pakai `window.MuterinDialog`. Form
  tambah/edit pakai panel inline (karena ada upload foto), konsisten dengan Peta
  Komunitas.
- Peta auto-`fitTo` ke tempat tersimpan saat load (menghindari bug marker
  tak-kelihatan yang sudah pernah terjadi & diperbaiki di Peta Komunitas).
- Cache-bust semua `public/js/*.js` baru dengan `?v={{ filemtime(...) }}`.
- Test gaya PHPUnit class-based (lihat `tests/Feature/MapTest.php`), `RefreshDatabase`,
  `User::factory()`.
- Semua string UI Bahasa Indonesia.

---

## Arsitektur

Halaman `/peta/titik` (tetap URL yang sama) di-rework total: dilayani
`SavedPlaceController` yang baru, memakai dua tabel baru (`place_lists`,
`saved_places`) dan model `PlaceList` / `SavedPlace`. Sistem `map_pins` lama
dipensiunkan: datanya dimigrasikan ke `saved_places`, lalu model/tabel/route/JS/
test-nya dihapus. Panel kiri persisten (manajer list + daftar tempat) + peta kanan,
konsisten dengan Peta Komunitas & Peta Rencana. Logika non-trivial (memastikan list
default ada, payload) diisolasi di `SavedPlaceController` + satu helper kecil di
model `PlaceList`.

### Unit & tanggung jawab

| Unit | Tanggung jawab |
|---|---|
| `place_lists` (migration) | Skema list |
| `saved_places` (migration) | Skema tempat tersimpan |
| migrasi data + drop `map_pins` (migration) | Pindahkan pin lama, retire tabel lama |
| `PlaceList` (model) | Relasi + `ensureDefaultsFor(User)` + konstanta palet |
| `SavedPlace` (model) | Relasi |
| `SavedPlaceController` | Halaman + endpoint list & tempat |
| `map-saved.js` | UI peta: manajer list, tambah/edit tempat, marker, hover, filter |
| `map/saved.blade.php` | Layout halaman (menggantikan `map/pins.blade.php`) |

---

## Data Model

### `place_lists`

```php
Schema::create('place_lists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('icon')->default('fa-bookmark'); // Font Awesome, dari palet
    $table->string('color', 9)->default('#0F766E');  // hex
    $table->boolean('is_default')->default(false);   // list bawaan, tak bisa dihapus
    $table->timestamps();
});
```

### `saved_places`

```php
Schema::create('saved_places', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('place_list_id')->constrained()->cascadeOnDelete();
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('photo_path')->nullable();
    $table->timestamps();
});
```

### List bawaan

Dibuat lazily via `PlaceList::ensureDefaultsFor($user)` dipanggil di
`SavedPlaceController::index()` (pakai `firstOrCreate` supaya idempotent, tidak
menyentuh alur registrasi):

| name | icon | color |
|---|---|---|
| Favorit | `fa-star` | `#F59E0B` |
| Mau ke sana | `fa-flag` | `#0EA5E9` |
| Bengkel Langganan | `fa-wrench` | `#0F766E` |

`// ponytail:` daftar & atribut list default = tuning-knob.

### Palet icon (untuk kustomisasi list)

Konstanta `PlaceList::ICONS`  array whitelist nama Font Awesome yang boleh dipilih
(validasi `in:`), supaya user tidak bisa menyuntik class sembarangan:

```
fa-star, fa-flag, fa-heart, fa-bookmark, fa-wrench, fa-mug-hot, fa-house,
fa-camera, fa-road, fa-mountain, fa-utensils, fa-gas-pump, fa-location-dot
```

(Semua icon list default  `fa-star`, `fa-flag`, `fa-wrench`  wajib termasuk di
palet ini supaya list default bisa diedit tanpa gagal validasi.)

`color` divalidasi sebagai hex (`regex:/^#[0-9A-Fa-f]{6}$/`).

### Aturan hapus list

- List `is_default=true` **tidak bisa dihapus** (endpoint tolak dengan 422).
- List custom bisa dihapus → **cascade delete** semua `saved_places` di dalamnya
  (FK `cascadeOnDelete`), didahului dialog konfirmasi
  ("Hapus list ini beserta N tempat di dalamnya?"). `// ponytail:` cascade =
  pilihan simpel; ganti ke "pindah ke Favorit" kalau perlu.

### Migrasi `map_pins` lama

Migration data: untuk tiap `map_pins` yang ada, pastikan list default user ada,
lalu buat `saved_places` di list **Favorit** (`title`→`title`, `note`→`description`,
`lat`/`lng` disalin; kategori dibuang). Setelah data disalin, `map_pins` di-`drop`.
Retire kode terkait (model, `map-pins.js`, route `map.pins.*`, key `pins` di
`MapController::data()`, `User::mapPins()`, 3 test pin di `MapTest`) dilakukan
sebagai langkah plan, bukan migration.

---

## UI & Alur

Layout: panel kiri persisten + peta kanan (`map/saved.blade.php`).

**Panel kiri:**
- **Manajer List:** daftar list (icon + warna + nama + jumlah tempat). Klik list →
  filter marker & daftar ke list itu. Tombol **"+ Buat List"** → form: nama, pilih
  icon dari palet, pilih warna. Tiap list custom punya tombol edit (ubah
  nama/icon/warna) & hapus.
- **Daftar Tempat:** tempat dari list terpilih (atau semua). Tiap baris: icon list,
  judul, nama list. Klik → peta `setView` ke tempat + buka kartu.

**Tambah tempat:**
1. Klik peta (atau "Lokasi Saya") → `L.popup()` "Simpan tempat di sini?".
2. Klik → form inline: judul, deskripsi (opsional), foto (opsional), **pilih list**
   (dropdown list + opsi cepat pilih salah satu).
3. Submit → `POST /peta/titik` (multipart). Marker langsung muncul pakai icon+warna
   list.

**Marker & kartu:**
- Marker `L.divIcon` Font Awesome pakai **icon + warna list**-nya.
- **Hover** → kartu ringkas (foto + judul + nama list).
- **Klik** → kartu penuh: foto, judul, deskripsi, badge list, tombol **Edit**
  (ubah judul/deskripsi/pindah list) & **Hapus** (dengan konfirmasi).

**Fit-to-pins on load:** peta auto-`fitTo` ke semua tempat tersimpan saat load
(sekali, lalu user bebas pan/zoom).

---

## Endpoint

Semua dalam grup `auth`, semua owner-scoped.

| Method | URI | Nama | Fungsi |
|---|---|---|---|
| GET | `/peta/titik` | `map.saved` | Halaman (ensure default lists) |
| GET | `/peta/titik/data` | `map.saved.data` | JSON `{ lists, places }` |
| POST | `/peta/titik/lists` | `map.saved.lists.store` | Buat list (name, icon, color) |
| PATCH | `/peta/titik/lists/{list}` | `map.saved.lists.update` | Edit list (name/icon/color) |
| DELETE | `/peta/titik/lists/{list}` | `map.saved.lists.destroy` | Hapus list custom (cascade) |
| POST | `/peta/titik` | `map.saved.store` | Tambah tempat (multipart, foto opsional) |
| PATCH | `/peta/titik/{place}` | `map.saved.update` | Edit tempat (title/desc/list) |
| DELETE | `/peta/titik/{place}` | `map.saved.destroy` | Hapus tempat |

### Payload `data`

```json
{
  "lists": [
    { "id": 1, "name": "Favorit", "icon": "fa-star", "color": "#F59E0B",
      "is_default": true, "place_count": 3 }
  ],
  "places": [
    { "id": 10, "place_list_id": 1, "lat": -7.77, "lng": 110.41,
      "title": "Kafe favorit", "description": "kopinya enak",
      "photo_url": "/storage/places/xxx.jpg",
      "list_name": "Favorit", "list_icon": "fa-star", "list_color": "#F59E0B" }
  ]
}
```

### Validasi

**list store/update:**
```php
'name'  => 'required|string|max:255',
'icon'  => ['required', Rule::in(PlaceList::ICONS)],
'color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
```

**place store:**
```php
'place_list_id' => 'required|exists:place_lists,id', // + cek kepemilikan
'lat'           => 'required|numeric|between:-90,90',
'lng'           => 'required|numeric|between:-180,180',
'title'         => 'required|string|max:255',
'description'   => 'nullable|string|max:2000',
'photo'         => 'nullable|image|max:4096',
```

**place update:** `title`, `description`, `place_list_id` (semua tervalidasi &
list-nya milik user). Foto tidak diubah lewat edit (YAGNI; bisa ditambah nanti).

## Otorisasi

- Semua endpoint: resource harus milik `auth()->id()` (`abort_unless(... === auth()->id(), 403)`),
  pola sama dengan `destroyPin`/`destroyPlan` lama.
- `place_list_id` di store/update wajib dicek milik user (mencegah nyimpen ke list
  orang lain).
- Hapus list `is_default` → 422 ("List bawaan tidak bisa dihapus").
- Foto tampil lewat `storage:link` (`/storage/places/...`).

## Penanganan Error

- Upload foto gagal/format salah → 422 pesan validasi; UI tampilkan di form.
- Hapus foto lama dari disk saat tempat dihapus (sama pola Peta Komunitas `destroy`).
- Migrasi data `map_pins`: kalau tabel `map_pins` sudah tidak ada (fresh install),
  migration data harus aman (cek `Schema::hasTable`).

## Testing

Feature test (`tests/Feature/SavedPlaceTest.php`):
- `index` memastikan 3 list default dibuat sekali (idempotent  buka 2x tetap 3).
- Buat list custom (icon dari palet, warna hex) tersimpan.
- Icon di luar palet → 422; warna non-hex → 422.
- Hapus list default → 422; hapus list custom → cascade menghapus tempat di dalamnya.
- Tambah tempat + foto → tersimpan di disk (`Storage::fake`), payload `data` benar.
- `place_count` per list benar.
- Simpan tempat ke `place_list_id` milik user lain → ditolak (403/422).
- Edit tempat (pindah list, ubah judul) tersimpan.
- Non-pemilik tak bisa hapus/edit tempat/list → 403.
- Payload `data` hanya berisi milik user yang login (bukan user lain).

Data-migration test (`tests/Feature/MapPinsMigrationTest.php` atau di dalam
SavedPlaceTest): jika ada baris `map_pins` sebelum migrasi, setelah migrasi
muncul sebagai `saved_places` di list Favorit dengan title/description benar.
(Catatan implementer: menguji migration bisa via membuat baris sebelum drop; kalau
sulit di test env, verifikasi manual didokumentasikan.)

## Di luar cakupan (YAGNI  sengaja ditunda)

Berbagi list ke user lain, many-to-many tempat↔list, ubah foto saat edit, urutan
drag-and-drop, ekspor/impor, pencarian tempat (geocoding) di halaman ini (sudah ada
di Peta Rencana), rating/visited status. Semua bisa ditambah tanpa membongkar desain.
