# Sidebar Grup Lipat (Nutrio-style) — Design Spec

**Tanggal:** 2026-07-23
**Status:** Approved (brainstorming)
**Scope:** Restrukturisasi navigasi sidebar jadi section yang bisa dilipat (collapsible), meniru pola Nutrio, dengan UI calm. Satu file utama: `resources/views/layouts/navigation.blade.php`.

## Tujuan

Nav sidebar sekarang datar (10 item sejajar) — makin banyak fitur makin panjang & ramai. Kelompokkan fitur serumpun ke dalam **section yang bisa dilipat** dengan nama domain (ala Nutrio "Monitoring & Kepatuhan"), sambil menjaga fitur utama tetap langsung terlihat.

## Prinsip

- Hanya kelompok dengan **≥2 halaman serumpun** yang dilipat. Halaman tunggal tetap datar (nggak masuk akal melipat 1 item).
- Fitur utama (**Riding**) tetap datar.
- UI **calm**: kontras rendah, banyak ruang, tanpa border berat; header grup redup (slate-500), chevron kecil opasitas rendah; state aktif berupa "pill" lembut (`bg-primary-soft` + `text-primary`).

## Struktur Final

```
Dashboard              [datar]  icon: gauge
Motor Saya             [datar]  icon: motorcycle
Riding                 [datar]  icon: play   (fitur utama)

Perawatan & Biaya      [grup ▾] icon: wallet
   ├ Biaya & Servis            icon: wallet     route: history
   ├ BBM                       icon: droplet    route: bbm.index
   └ Laporan                   icon: bar-chart  route: laporan

Peta & Navigasi        [grup ▾] icon: map
   ├ Peta Rute                 icon: route          route: map.routes
   ├ Titik Saya                icon: map-pin        route: map.saved
   ├ Peta Komunitas            icon: alert-triangle route: map.community
   └ Rencana Rute              icon: navigation     route: map.plans

─── SISTEM ───  (footer, tetap seperti sekarang)
Pengaturan             icon: wrench   route: profile.edit
```

Dua grup lipat: **"Perawatan & Biaya"** (3 item) dan **"Peta & Navigasi"** (4 item).

## Perilaku

- **Framework:** pakai **Alpine.js** (app sudah memuatnya lewat `x-data` di `layouts/app.blade.php`) — tidak perlu JS custom baru.
- **Auto-expand grup aktif:** saat halaman dimuat, grup yang memuat rute aktif **otomatis terbuka**; grup lain mulai tertutup. Jadi user selalu lihat posisinya. Ditentukan server-side via `request()->routeIs(...)` untuk set nilai awal `x-data`.
- **Toggle independen:** tiap grup buka/tutup sendiri (bukan accordion ketat) — user boleh buka lebih dari satu. Lebih simpel & sesuai Nutrio.
- **Header grup:** baris berisi `icon + nama + chevron`. Chevron `chevron-right` saat tertutup, muter jadi mengarah bawah saat terbuka (`rotate-90`, transisi halus). Klik header = toggle.
- **Animasi buka-tutup:** halus. Gunakan pola yang sudah terbukti stabil di app (hindari `x-transition` + `x-cloak` barengan yang pernah bikin bug — pakai `x-show` + `x-collapse` bila plugin tersedia, atau transisi tinggi CSS sederhana). `// ponytail:` kalau `x-collapse` tak ada, cukup `x-show` polos tanpa animasi tinggi — fungsional dulu.
- **State aktif anak:** anak yang aktif dapat pill `bg-primary-soft text-primary font-bold` (sama seperti gaya aktif sekarang). Anak diindentasi (mis. `pl-9`) dengan ikon sedikit lebih kecil — konsisten Nutrio.
- **Header grup saat salah satu anaknya aktif:** emphasis halus (mis. `text-foreground font-semibold`), tidak seheboh pill anak — biar tetap calm.

## Icon grup

- **Perawatan & Biaya:** `wallet` (tema pengeluaran). Anak "Biaya & Servis" juga `wallet` — pengulangan kecil ini dapat diterima; kalau mau beda, header boleh pakai ikon lain yang tersedia.
- **Peta & Navigasi:** `map`.

(Komponen ikon tersedia di `resources/views/components/icon/`: `gauge, motorcycle, play, wallet, droplet, bar-chart, route, map-pin, alert-triangle, navigation, map, wrench` — semua sudah ada.)

## Di luar cakupan (YAGNI)

- Menyimpan state expand/collapse manual ke localStorage (auto-expand-aktif sudah cukup).
- Mode sidebar collapsed jadi ikon-saja (rail).
- Reorder drag-and-drop item nav.
- Perubahan pada footer "Sistem"/user-card (tetap apa adanya).

## Testing

Perubahan murni tampilan (satu file Blade + Alpine), tidak ada logika backend. Verifikasi manual: tiap grup buka/tutup; grup yang memuat halaman aktif terbuka otomatis saat load; anak aktif ter-highlight; tidak ada item nav yang hilang; footer Sistem utuh. Full test suite tetap hijau (tidak ada file PHP tersentuh).
