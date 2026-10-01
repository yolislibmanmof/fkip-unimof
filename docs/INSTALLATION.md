# 📖 Panduan Instalasi Lengkap - FKIP UNIMOF

Dokumen ini berisi panduan instalasi step-by-step untuk Website FKIP UNIMOF.

---

## Daftar Isi

1. [Persiapan Server](#persiapan-server)
2. [Instalasi di Ubuntu/Debian](#instalasi-di-ubuntudebian)
3. [Instalasi di CentOS/RHEL](#instalasi-di-centosrhel)
4. [Instalasi di XAMPP Windows](#instalasi-di-xampp-windows)
5. [Konfigurasi Database](#konfigurasi-database)
6. [Verifikasi Instalasi](#verifikasi-instalasi)
7. [Troubleshooting Instalasi](#troubleshooting-instalasi)

---

## Persiapan Server

### Minimum Requirements

| Komponen  | Minimum         | Recommended     |
|-----------|-----------------|-----------------|
| CPU       | 2 Core          | 4 Core          |
| RAM       | 2 GB            | 4 GB            |
| Storage   | 10 GB HDD       | 20 GB SSD       |
| Bandwidth | 1 TB/bulan      | Unlimited       |
| OS        | Ubuntu 20.04    | Ubuntu 22.04    |

### Software Requirements

| Software    | Versi Minimum | Keterangan              |
|-------------|---------------|-------------------------|
| Apache      | 2.4+          | Web Server              |
| PHP         | 8.2+          | Bahasa Pemrograman      |
| MySQL       | 5.7+          | Database Server         |
| Git         | 2.30+         | Version Control         |
| OpenSSL     | 1.1+          | SSL/TLS Support         |

### PHP Extensions yang Dibutuhkan

```text
- PDO_MySQL    → Koneksi database
- mbstring     → Multi-byte string handling
- openssl      → Encryption & SSL
- json         → JSON processing
- fileinfo     → MIME type detection
- gd           → Image processing
- curl         → HTTP requests
- zip          → Archive handling
- xml          → XML processing