# 🗄️ Dokumentasi Database - FKIP UNIMOF

Dokumen ini berisi dokumentasi lengkap struktur database Website FKIP UNIMOF.

---

## Informasi Umum

| Parameter | Nilai |
|-----------|-------|
| Database | fkip |
| Engine | InnoDB |
| Charset | utf8mb4 |
| Collation | utf8mb4_unicode_ci |
| Total Tabel | 26 |
| MySQL Version | 5.7+ |

---

## Daftar Semua Tabel

| No | Tabel | Deskripsi | Records |
|----|-------|-----------|---------|
| 1 | admins | Data administrator sistem | 1 |
| 2 | admin_login_logs | Log aktivitas login admin | 30 |
| 3 | agenda | Kalender akademik | 3 |
| 4 | akreditasi | Status akreditasi prodi | 2 |
| 5 | alumni | Database alumni | 4 |
| 6 | beasiswa | Informasi beasiswa | 1 |
| 7 | berita | Artikel dan berita kampus | 2 |
| 8 | blog_artikel | Blog post dari dosen | 3 |
| 9 | blog_komentar | Komentar blog | 0 |
| 10 | dosen | Data dosen dan pengajar | 2 |
| 11 | downloads | File unduhan | 0 |
| 12 | faq | Pertanyaan umum | 4 |
| 13 | fasilitas | Sarana dan prasarana | 2 |
| 14 | galeri | Foto kegiatan | 0 |
| 15 | jurnal | Jurnal ilmiah | 1 |
| 16 | kerjasama | Mitra kerjasama | 3 |
| 17 | kontak | Pesan dari form kontak | 0 |
| 18 | pengaturan | Konfigurasi sistem | 35 |
| 19 | pengunjung | Log pengunjung website | 0 |
| 20 | prestasi | Prestasi mahasiswa | 1 |
| 21 | program_studi | Program studi FKIP | 8 |
| 22 | riset | Penelitian dan publikasi | 3 |
| 23 | statistik | Statistik website | 1 |
| 24 | testimoni | Testimoni alumni | 2 |
| 25 | video | Video dan podcast | 4 |
| 26 | video_kategori | Kategori video | 5 |

---

## Struktur Tabel Detail

### Tabel admins

| Kolom | Tipe | Null | Default | Keterangan |
|-------|------|------|---------|------------|
| id | int(11) | NO | AUTO_INCREMENT | Primary Key |
| username | varchar(50) | NO | - | Username (UNIQUE) |
| password | varchar(255) | NO | - | Hash Argon2ID/BCRYPT |
| email | varchar(100) | NO | - | Email admin |
| full_name | varchar(100) | YES | NULL | Nama lengkap |
| role | enum | YES | admin | superadmin/admin |
| status | enum | NO | Active | Active/Inactive |
| last_login | timestamp | YES | NULL | Waktu login terakhir |
| last_ip | varchar(45) | YES | NULL | IP terakhir |
| last_user_agent | varchar(255) | YES | NULL | Browser terakhir |
| two_factor_enabled | tinyint(1) | NO | 0 | Status 2FA |
| two_factor_secret | varchar(16) | YES | NULL | Secret key 2FA |
| created_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu dibuat |

### Tabel berita

| Kolom | Tipe | Null | Default | Keterangan |
|-------|------|------|---------|------------|
| id | int(11) | NO | AUTO_INCREMENT | Primary Key |
| judul | varchar(255) | NO | - | Judul berita |
| slug | varchar(255) | NO | - | URL slug (UNIQUE) |
| konten | longtext | YES | NULL | Isi berita (HTML) |
| excerpt | text | YES | NULL | Ringkasan |
| gambar | varchar(255) | YES | NULL | Path gambar |
| kategori | enum | YES | Umum | Kategori berita |
| penulis | varchar(100) | YES | NULL | Nama penulis |
| views | int(11) | YES | 0 | Jumlah tampilan |
| status | enum | YES | Draft | Draft/Published/Archived |
| is_featured | tinyint(4) | YES | 0 | Featured di halaman utama |
| tags | varchar(255) | YES | NULL | Tag berita |
| published_at | timestamp | YES | NULL | Waktu publikasi |
| created_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu dibuat |
| updated_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu diperbarui |

### Tabel program_studi

| Kolom | Tipe | Null | Default | Keterangan |
|-------|------|------|---------|------------|
| id | int(11) | NO | AUTO_INCREMENT | Primary Key |
| kode | varchar(10) | YES | NULL | Kode prodi (UNIQUE) |
| nama | varchar(100) | NO | - | Nama program studi |
| nama_en | varchar(100) | YES | NULL | Nama Bahasa Inggris |
| singkatan | varchar(10) | YES | NULL | Singkatan |
| jenjang | enum | YES | S1 | D3/S1/S2/S3 |
| akreditasi | enum | YES | Terakreditasi | Unggul/Baik Sekali/Baik |
| ketua_prodi | varchar(100) | YES | NULL | Nama ketua prodi |
| nidn_kaprodi | varchar(30) | YES | NULL | NIDN ketua prodi |
| deskripsi | text | YES | NULL | Deskripsi prodi |
| visi | text | YES | NULL | Visi prodi |
| misi | text | YES | NULL | Misi prodi |
| kurikulum | text | YES | NULL | Info kurikulum |
| prospek_kerja | text | YES | NULL | Prospek kerja |
| logo | varchar(255) | YES | NULL | Path logo |
| banner | varchar(255) | YES | NULL | Path banner |
| jumlah_dosen | int(11) | YES | 0 | Jumlah dosen |
| jumlah_mahasiswa | int(11) | YES | 0 | Jumlah mahasiswa |
| status | enum | YES | Aktif | Aktif/Non-Aktif |
| urutan | int(11) | YES | 0 | Urutan tampilan |
| created_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu dibuat |
| updated_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu diperbarui |

### Tabel dosen

| Kolom | Tipe | Null | Default | Keterangan |
|-------|------|------|---------|------------|
| id | int(11) | NO | AUTO_INCREMENT | Primary Key |
| nidn | varchar(30) | YES | NULL | NIDN (UNIQUE) |
| nama | varchar(100) | NO | - | Nama dosen |
| gelar_depan | varchar(30) | YES | NULL | Gelar depan |
| gelar_belakang | varchar(50) | YES | NULL | Gelar belakang |
| program_studi_id | int(11) | YES | NULL | FK ke program_studi |
| jabatan_fungsional | varchar(50) | YES | NULL | Jabatan fungsional |
| pendidikan_terakhir | varchar(100) | YES | NULL | Pendidikan terakhir |
| email | varchar(100) | YES | NULL | Email dosen |
| telepon | varchar(20) | YES | NULL | Nomor telepon |
| foto | varchar(255) | YES | NULL | Path foto |
| bidang_keahlian | text | YES | NULL | Bidang keahlian |
| publikasi | text | YES | NULL | Daftar publikasi |
| google_scholar | varchar(255) | YES | NULL | Link Google Scholar |
| scopus | varchar(255) | YES | NULL | Link Scopus |
| riwayat_pendidikan | text | YES | NULL | Riwayat pendidikan |
| penelitian | text | YES | NULL | Daftar penelitian |
| bio | text | YES | NULL | Biografi singkat |
| status | enum | YES | Aktif | Aktif/Cuti/Pensiun |
| created_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu dibuat |

### Tabel pengaturan

| Kolom | Tipe | Null | Default | Keterangan |
|-------|------|------|---------|------------|
| id | int(11) | NO | AUTO_INCREMENT | Primary Key |
| nama_key | varchar(50) | YES | NULL | Key pengaturan (UNIQUE) |
| nilai | text | YES | NULL | Value pengaturan |
| updated_at | timestamp | YES | CURRENT_TIMESTAMP | Waktu diperbarui |

**Daftar Key Pengaturan:**

| Key | Contoh Nilai | Deskripsi |
|-----|-------------|-----------|
| nama_fakultas | Fakultas Keguruan dan Ilmu Pendidikan | Nama fakultas |
| nama_universitas | Universitas Muhammadiyah Maumere | Nama universitas |
| singkatan | FKIP UNIMOF | Singkatan |
| alamat | Jl. Bhayangkara No.1, Maumere | Alamat kampus |
| telepon | (0382) 21234 | Nomor telepon |
| email | fkip@unimof.ac.id | Email resmi |
| website | www.fkip-unimof.ac.id | URL website |
| whatsapp_number | 6281234567890 | Nomor WhatsApp |
| instagram_url | https://instagram.com/fkipunimof | Link Instagram |
| facebook_url | https://facebook.com/fkipunimof | Link Facebook |
| youtube_url | https://youtube.com/@fkipunimof | Link YouTube |
| pmb_gelombang | Gelombang 1 | Gelombang PMB |
| pmb_deadline | 2026-01-31 | Deadline PMB |
| biaya_pendaftaran | 300000 | Biaya pendaftaran |
| rekening_bank | Bank NTT - 1234567890 | Rekening pembayaran |
| maintenance_mode | 0 | Mode pemeliharaan |
| maintenance_message | Website sedang dalam pemeliharaan | Pesan maintenance |
| hero_video | assets/video/hero.mp4 | Path video hero |
| hero_video_active | 1 | Status video hero |
| enable_registration | 1 | Aktifkan registrasi |
| timezone | Asia/Makassar | Zona waktu |
| date_format | d M Y | Format tanggal |

---

## Relasi Antar Tabel (Foreign Keys)

| Tabel Anak | Kolom | Tabel Induk | Kolom Induk | On Delete |
|------------|-------|-------------|-------------|-----------|
| alumni | program_studi_id | program_studi | id | SET NULL |
| blog_artikel | dosen_id | dosen | id | SET NULL |
| blog_artikel | program_studi_id | program_studi | id | SET NULL |
| blog_komentar | artikel_id | blog_artikel | id | CASCADE |
| dosen | program_studi_id | program_studi | id | SET NULL |
| prestasi | program_studi_id | program_studi | id | SET NULL |
| riset | ketua_id | dosen | id | SET NULL |
| riset | program_studi_id | program_studi | id | SET NULL |
| video | kategori_id | video_kategori | id | SET NULL |

---

## Cara Import Database

### Via Command Line

```bash
mysql -u root -p fkip < database/fkip_database.sql