# Pembayaran Midtrans Muterin

Integrasi menggunakan Snap HTTP API melalui Laravel HTTP Client. Tidak memerlukan paket Composer tambahan. Harga ditentukan server melalui `config/subscription.php`: Rp19.000 bulanan dan Rp190.000 tahunan. Nilai ini merupakan harga awal proyek, bukan biaya dari Midtrans.

## Konfigurasi Sandbox

1. Buat akun merchant di Midtrans dan pilih lingkungan Sandbox di dashboard.
2. Buka Settings > Access Keys. Masukkan kunci **Sandbox** ke `.env` lokal atau environment variables Railway. Jangan masukkan kunci ke Git atau chat.

```dotenv
MIDTRANS_SERVER_KEY=isi_server_key_sandbox
MIDTRANS_CLIENT_KEY=isi_client_key_sandbox
MIDTRANS_IS_PRODUCTION=false
```

3. Jalankan `php artisan optimize:clear` dan `php artisan migrate`. Pastikan `APP_URL` sesuai alamat publik aplikasi untuk URL callback.
4. Di Settings > Configuration Midtrans, atur Payment Notification URL ke `https://DOMAIN-APLIKASI/midtrans/notification`. URL ini harus dapat dijangkau Midtrans. Localhost tidak dapat menerima webhook; gunakan deployment HTTPS atau tunnel publik untuk pengujian webhook.
5. Masuk ke Muterin, buka menu akun > **Upgrade paket**, pilih durasi Pro Bulanan atau Tahunan pada popup, lalu klik **Pilih Pro**. Snap terbuka bila kedua kunci telah dikonfigurasi. Tautan `/langganan` lama membuka popup yang sama.
6. Gunakan alat dan metode pembayaran uji resmi Sandbox, bukan kartu sungguhan. Di localhost, **Cek status pembayaran** tetap berfungsi karena Laravel meminta status langsung ke Midtrans. Webhook produksi harus tetap disiapkan agar konfirmasi berjalan tanpa kunjungan pengguna.

## Perilaku transaksi

- Pemilihan paket berlangsung dalam popup, bukan halaman tersendiri. Pembayaran membutuhkan login; setelah login/daftar popup dibuka kembali dengan durasi terpilih. Alur lengkap dan tahap trial/recurring ada di [SUBSCRIPTION-FLOW.md](SUBSCRIPTION-FLOW.md).
- Order disimpan sebelum meminta token. Kegagalan membuat token menghasilkan order gagal dan pesan aman, tanpa menampilkan respons debug provider.
- Client Key ditampilkan di frontend sesuai mekanisme Snap; Server Key hanya digunakan backend untuk Basic Auth dan pemeriksaan SHA-512.
- Callback browser hanya memicu permintaan status server. Status dari browser tidak mengaktifkan paket.
- Webhook mengecek tanda tangan, jumlah, mata uang dan lingkungan, lalu mengambil status langsung dari Midtrans. Field status/fraud tidak tercakup dalam signature Midtrans, sehingga payload webhook tidak menjadi sumber status akhir. Hanya settlement atau capture yang diterima sistem deteksi fraud yang dapat mencatat pembayaran lunas. Kegagalan API status mengembalikan HTTP 503 agar Midtrans mengirim ulang notifikasi.
- Duplikat konfirmasi tidak memperpanjang paket lagi. Konfirmasi pending yang terlambat tidak membatalkan transaksi lunas.
- Pembayaran selanjutnya menambah periode setelah masa paket lunas sebelumnya berakhir. Satu bulan/tahun menggunakan kalender dan menangani tanggal akhir bulan/tahun kabisat.
- Bagian **Pembayaran** di popup hanya menampilkan transaksi pemilik akun pada lingkungan yang sedang aktif. Tautan `/langganan/pembayaran` lama membuka bagian ini di aplikasi. Full refund menandai transaksi dikembalikan; partial refund tidak mengubah masa paket. Refund dilakukan dari Midtrans, bukan aplikasi.
- Tidak ada auto-debit, trial aktif, prorata, pergantian paket di tengah periode, atau pembatasan fitur. Harga checkout dibayar langsung untuk periode yang dipilih. Model paket Gratis/Pro di halaman harga masih rancangan pembagian fitur.
- Riwayat bisa diperiksa ulang secara manual. Belum ada job rekonsiliasi berkala. Snap yang ditutup dapat dibuka ulang dengan tombol yang sama atau **Lanjutkan pembayaran** pada order pending di popup. Periksa status terlebih dahulu jika sesi pembayaran telah kedaluwarsa.

## Produksi

Gunakan kunci **Production**, set `MIDTRANS_IS_PRODUCTION=true`, dan gunakan domain HTTPS publik dengan webhook yang terdaftar. Pastikan akun merchant telah disetujui, harga sudah final, serta pembayaran/refund sudah diuji sebelum menerima uang nyata. Transaksi Sandbox dan Production dipisahkan; kunci berbeda tidak dapat dipakai memeriksa order lama di lingkungan lain. Integrasi memakai pembayaran sekali per periode, bukan Midtrans recurring subscription.

## Verifikasi

```sh
php artisan test --compact --filter=MidtransPaymentTest
```

Pengujian memakai respons API yang disimulasikan: tidak menghubungi merchant sungguhan. Mencakup nominal server, otorisasi pemilik, signature salah, jumlah salah, fraud challenge, duplikasi settlement, penambahan periode dan kegagalan provider. Pengujian Snap end-to-end membutuhkan kunci merchant Sandbox sendiri.

Referensi: [Snap integration](https://docs.midtrans.com/docs/snap-snap-integration-guide), [webhook](https://docs.midtrans.com/docs/https-notification-webhooks), [transaction status](https://docs.midtrans.com/reference/get-transaction-status), [production mode](https://docs.midtrans.com/docs/switching-to-production-mode).
