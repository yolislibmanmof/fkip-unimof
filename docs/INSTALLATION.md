# Panduan Instalasi FKIP UNIMOF

Panduan untuk menjalankan website FKIP UNIMOF secara lokal dengan Laragon dan menyiapkan server Apache untuk deployment.

## Daftar isi

1. [Persyaratan](#persyaratan)
2. [Instalasi lokal dengan Laragon](#instalasi-lokal-dengan-laragon)
3. [Menyiapkan database](#menyiapkan-database)
4. [Konfigurasi aplikasi](#konfigurasi-aplikasi)
5. [Menjalankan dan memverifikasi](#menjalankan-dan-memverifikasi)
6. [Deployment produksi](#deployment-produksi)
7. [Troubleshooting](#troubleshooting)

## Persyaratan

- PHP 8.2 atau lebih baru
- MySQL 5.7+ atau MariaDB yang kompatibel
- Apache 2.4+ dengan `mod_rewrite` aktif
- Ekstensi PHP:
  - `pdo_mysql`
  - `fileinfo`
  - `mbstring`
  - `xml`
  - `xmlwriter` untuk generator sitemap
- Browser modern

Untuk pengembangan lokal di Windows, gunakan Laragon 8.2 atau versi yang menyediakan PHP 8.2+ dan MySQL/MariaDB.

## Instalasi lokal dengan Laragon

1. Jalankan Laragon dan pastikan layanan Apache serta MySQL aktif.
2. Letakkan repository di folder web Laragon, biasanya:

   ```text
   C:\laragon\www\fkip-unimof
   ```

   Jika menggunakan Git:

   ```powershell
   cd C:\laragon\www
   git clone https://github.com/yolislibmanmof/fkip-unimof.git
   ```

3. Pastikan folder `assets` dapat ditulis oleh Apache. Folder berikut mungkin perlu ditulis oleh aplikasi saat upload:

   ```text
   assets\uploads
   assets\downloads
   assets\images
   assets\dosen
   ```

   Berikan izin tulis seperlunya untuk akun yang menjalankan Apache. Jangan memberi izin tulis ke semua folder aplikasi.

4. Buka phpMyAdmin dari menu Laragon atau kunjungi alamat phpMyAdmin yang disediakan instalasi Laragon.

## Menyiapkan database

1. Buat database baru dengan nama `fkip` dan collation `utf8mb4_unicode_ci`.
2. Pilih database tersebut di phpMyAdmin.
3. Import file:

   ```text
   database/fkip.sql
   ```

4. Pastikan proses import selesai tanpa error dan tabel aplikasi sudah terbentuk.

> File SQL merupakan skrip setup aplikasi. Jangan meletakkan salinan database produksi atau dump yang berisi data pengguna, log, maupun kredensial di repository publik.

## Konfigurasi aplikasi

Buat file `.env` di root proyek—sejajar dengan `index.php`—berdasarkan contoh berikut:

```dotenv
APP_ENV=local
APP_URL=http://localhost/fkip-unimof
APP_TIMEZONE=Asia/Makassar

DB_HOST=localhost
DB_NAME=fkip
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

APP_NAME=FKIP UNIMOF
APP_VERSION=2.0.0

SESSION_COOKIE_SECURE=
```

Sesuaikan nilai database dengan konfigurasi Laragon Anda. Jika instalasi Laragon memakai password MySQL, isi `DB_PASS` dengan password lokal tersebut.

File `.env` berisi konfigurasi lokal dan rahasia:

- Jangan commit atau push `.env`.
- Pastikan `.env` tercantum di `.gitignore`.
- Jangan menggunakan kredensial `root` untuk server produksi.
- Jangan menyalin password atau API key produksi ke dokumentasi atau repository.

Jika konfigurasi koneksi database berada di `includes/config.php` dan aplikasi Anda belum memuat nilai `.env`, ikuti mekanisme konfigurasi yang aktif di versi proyek yang sedang Anda jalankan.

## Menjalankan dan memverifikasi

1. Pastikan Apache dan MySQL tetap aktif di Laragon.
2. Buka:

   ```text
   http://localhost/fkip-unimof/
   ```

3. Verifikasi halaman publik, navigasi, gambar, dan halaman detail konten.
4. Verifikasi koneksi database dengan membuka halaman yang membaca data dari database.
5. Uji halaman admin dan operasi konten menggunakan akun admin yang sudah Anda siapkan.
6. Uji upload gambar dan dokumen dengan file contoh yang tidak mengandung data sensitif.
7. Periksa sitemap:

   ```text
   http://localhost/fkip-unimof/sitemap.php
   ```

   Sitemap memerlukan ekstensi PHP `xmlwriter`. Jika ekstensi tidak aktif, aktifkan melalui konfigurasi PHP Laragon lalu restart Apache.

8. Periksa log Apache/PHP Laragon bila halaman menampilkan error. Jangan mengaktifkan tampilan detail error untuk pengguna di server publik.

## Deployment produksi

Gunakan checklist berikut sebelum mempublikasikan aplikasi:

- Gunakan HTTPS dan domain produksi yang benar pada `APP_URL`.
- Jalankan aplikasi dengan `APP_ENV=production`.
- Buat user database khusus aplikasi dengan hak minimum yang diperlukan.
- Gunakan password database dan API key baru yang disimpan di konfigurasi server, bukan Git.
- Pastikan `display_errors` dinonaktifkan dan error dicatat ke log di luar akses publik.
- Pastikan cookie sesi menggunakan `Secure` di bawah HTTPS, serta tetap `HttpOnly` dan `SameSite`.
- Pastikan folder upload tidak menjalankan PHP atau skrip lain.
- Batasi izin tulis hanya pada direktori yang benar-benar dipakai untuk upload dan runtime.
- Jangan biarkan `.env`, dump database, log debug, backup, atau file sementara tersedia melalui web.
- Aturan `.htaccess` hanya berlaku pada Apache. Jika memakai Nginx, konfigurasi blokir file dan nonaktifkan eksekusi skrip di direktori upload pada konfigurasi Nginx.
- Setelah instalasi, ganti password admin awal dan verifikasi akun yang memiliki akses admin.
- Siapkan backup database dan file upload, serta uji proses pemulihannya.

`robots.txt` dan aturan crawler bukan kontrol akses. Halaman admin, API, dan file sensitif tetap harus dilindungi oleh autentikasi dan konfigurasi server.

## Troubleshooting

### Koneksi database gagal

- Pastikan MySQL aktif di Laragon.
- Pastikan database `fkip` sudah dibuat dan file `database/fkip.sql` sudah diimpor.
- Cocokkan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` dengan pengaturan lokal Anda.

### Halaman menampilkan HTTP 500

- Periksa log Apache dan PHP Laragon.
- Pastikan PHP 8.2+ aktif.
- Pastikan ekstensi yang dibutuhkan aplikasi aktif.
- Untuk sitemap, pastikan `xmlwriter` aktif.

### Upload gagal

- Pastikan file memenuhi batas ukuran upload PHP dan batas ukuran aplikasi.
- Pastikan `fileinfo` aktif.
- Pastikan direktori upload ada dan dapat ditulis oleh Apache.
- Periksa `upload_max_filesize` dan `post_max_size` pada konfigurasi PHP, lalu restart Apache jika nilainya berubah.

### URL atau rewrite tidak bekerja

- Pastikan `mod_rewrite` aktif di Apache.
- Pastikan konfigurasi Apache mengizinkan aturan `.htaccess` (`AllowOverride`).
- Periksa bahwa proyek dibuka menggunakan URL dan direktori yang sesuai dengan `APP_URL`.

### Aplikasi menampilkan data lama setelah perubahan pengaturan

- Periksa nilai konfigurasi yang aktif dan koneksi database.
- Jika PHP OPcache aktif di lingkungan lokal, restart Apache dari Laragon.