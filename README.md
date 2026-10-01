# 🎓 Website FKIP UnIMOF - Fakultas Keguruan dan Ilmu Pendidikan

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

Website resmi Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere - Platform digital modern untuk informasi akademik, berita, dan layanan mahasiswa.

## 🌟 Fitur Unggulan

### 🎯 Frontend (Publik)
- ✅ **Hero Video Background** - Video latar belakang dinamis dengan kontrol audio
- ✅ **Dark Mode** - Tema gelap/terang dengan auto-detect preferensi sistem
- ✅ **Responsive Design** - Optimal di semua perangkat (desktop, tablet, mobile)
- ✅ **Live Search** - Pencarian global dengan shortcut keyboard (/)
- ✅ **Reading Progress** - Progress bar saat membaca artikel
- ✅ **Animated Counters** - Statistik dengan animasi count-up
- ✅ **Testimonial Carousel** - Slider testimoni alumni
- ✅ **Interactive Calendar** - Agenda akademik dengan timeline
- ✅ **Social Sharing** - Bagikan konten ke berbagai platform
- ✅ **Command Palette** - Navigasi cepat dengan Ctrl+K

### 🔐 Admin Dashboard
- ✅ **Two-Factor Authentication (2FA)** - Keamanan berlapis dengan OTP
- ✅ **Real-time Analytics** - Statistik pengunjung dengan grafik interaktif
- ✅ **Activity Heatmap** - Visualisasi aktivitas 30 hari terakhir
- ✅ **Content Management** - CRUD untuk berita, agenda, program studi, dll
- ✅ **File Upload Manager** - Upload gambar & dokumen dengan validasi
- ✅ **Pomodoro Timer** - Timer produktivitas terintegrasi
- ✅ **Goal Tracker** - Pantau target harian dengan progress bar
- ✅ **Quick Notes** - Catatan otomatis tersimpan di browser
- ✅ **Focus Mode** - Mode konsentrasi tanpa gangguan
- ✅ **Dark Mode Admin** - Tema gelap untuk panel admin

### 🛡️ Keamanan
- ✅ **CSRF Protection** - Token keamanan untuk semua form
- ✅ **SQL Injection Prevention** - PDO prepared statements
- ✅ **XSS Protection** - Sanitasi output dengan htmlspecialchars
- ✅ **Rate Limiting** - Proteksi brute force (5 percobaan/jam)
- ✅ **Password Hashing** - Argon2ID/BCRYPT encryption
- ✅ **HTTP Security Headers** - X-Frame-Options, XSS-Protection, dll
- ✅ **Session Security** - HttpOnly & Secure cookies
- ✅ **File Upload Validation** - MIME type checking server-side

## 💻 Persyaratan Sistem

### Server Requirements
- **Web Server:** Apache 2.4+ atau Nginx 1.18+
- **PHP:** 8.2 atau lebih baru
- **MySQL:** 5.7 atau MariaDB 10.3+
- **Extensions PHP:**
  - PDO_MySQL
  - mbstring
  - openssl
  - json
  - fileinfo
  - gd (untuk manipulasi gambar)
  - curl (untuk API external)
  - zip (untuk backup)

### Recommended Stack
- **OS:** Ubuntu 20.04 LTS / CentOS 8 / Debian 11
- **RAM:** Minimal 2GB (4GB recommended)
- **Storage:** Minimal 10GB free space
- **SSL Certificate:** Required untuk production (Let's Encrypt)

## 🚀 Instalasi Cepat

### Metode 1: Git Clone (Recommended)

```bash
git clone https://github.com/yolislibmanmof/fkip-unimof.git
cd fkip-unimof
chmod -R 755 .
chmod -R 775 uploads/ logs/ assets/
mysql -u root -p fkip < database/fkip_database.sql
