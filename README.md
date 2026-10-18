# Website FKIP UNIMOF

Website Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere. Aplikasi menyediakan halaman publik untuk informasi fakultas dan portal admin untuk mengelola konten.

## Fitur

- Informasi fakultas dan program studi
- Berita, artikel, agenda, dan galeri kegiatan
- Informasi dosen, fasilitas, beasiswa, alumni, dan prestasi
- Halaman unduhan, FAQ, dan kontak
- Portal admin untuk mengelola konten dan statistik

## Teknologi dan persyaratan

- PHP 8.2 atau lebih baru
- MySQL 5.7+ atau MariaDB yang kompatibel
- Apache 2.4+ dengan `mod_rewrite`
- Ekstensi PHP: `pdo_mysql`, `fileinfo`, `mbstring`, `xml`, dan `xmlwriter`

Konfigurasi `.htaccess` dalam proyek ditujukan untuk Apache. Jika menggunakan Nginx, aturan server yang setara perlu disiapkan terpisah.

## Menjalankan secara lokal dengan Laragon

Panduan instalasi lengkap tersedia di [`docs/INSTALLATION.md`](docs/INSTALLATION.md). Ringkasnya:

1. Jalankan Apache dan MySQL dari Laragon.
2. Letakkan repository di folder web Laragon, misalnya `C:\laragon\www\fkip-unimof`.
3. Buat database bernama `fkip`, lalu import `database/fkip.sql`.
4. Buat file `.env` di root proyek dan atur `APP_URL` serta konfigurasi database. Contoh variabelnya ada di panduan instalasi.
5. Buka alamat proyek sesuai `APP_URL`, misalnya `http://localhost/fkip-unimof`.

Jangan commit atau mengunggah `.env`. Untuk server produksi, gunakan kredensial database khusus aplikasi—jangan gunakan akun database `root`.

## Dokumentasi

- [Panduan instalasi dan deployment](docs/INSTALLATION.md)
- [Dokumentasi struktur database](docs/DATABASE.md)
- [Dokumentasi endpoint API](docs/API.md)
- [Panduan keamanan](docs/SECURITY.md)
- [Panduan operasional](docs/OPERATIONS.md)

## Keamanan

Laporkan temuan keamanan secara bertanggung jawab kepada pengelola repository. Hindari menyertakan data pribadi, kata sandi, token, atau rahasia lain dalam laporan publik.