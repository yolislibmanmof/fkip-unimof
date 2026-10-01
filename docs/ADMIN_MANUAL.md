
### Kredensial Default

| Field    | Nilai                |
|----------|----------------------|
| Username | admin                |
| Password | admin123             |
| Role     | Super Admin          |

> ⚠️ **WAJIB:** Ganti password default segera setelah login pertama!

### Fitur Keamanan Login

| Fitur | Keterangan |
|-------|------------|
| Rate Limiting | Maksimal 5 percobaan gagal per jam |
| CSRF Protection | Token otomatis di setiap form |
| 2FA Support | Verifikasi dua langkah dengan OTP |
| Password Meter | Indikator kekuatan password |
| Caps Lock Detection | Peringatan saat Caps Lock aktif |
| Block Timer | Countdown saat akun diblokir |

### Cara Mengaktifkan 2FA

1. Login ke admin panel
2. Buka menu Pengaturan → Profil
3. Klik "Aktifkan 2FA"
4. Scan QR code dengan aplikasi authenticator (Google Authenticator / Authy)
5. Masukkan kode 6 digit untuk verifikasi
6. 2FA aktif

---

## Dashboard

### Statistik Real-time

Setelah login, Komandan akan melihat:

| Widget | Keterangan |
|--------|------------|
| Total Pengunjung | Pengunjung hari ini dan bulan ini |
| Total Berita | Jumlah berita published, draft, archived |
| Program Studi | Jumlah prodi aktif |
| Total Dosen | Jumlah dosen aktif |
| Sparkline Chart | Tren 7 hari terakhir |

### Activity Heatmap

- Visualisasi aktivitas login admin selama 30 hari
- Warna hijau tua = aktivitas tinggi
- Warna hijau muda = aktivitas sedang
- Warna abu-abu = tidak ada aktivitas

### Quick Actions

| Tombol | Fungsi |
|--------|--------|
| Tambah Berita | Buat berita baru |
| Kelola Prodi | Manajemen program studi |
| Tambah Agenda | Buat agenda baru |
| Upload Galeri | Upload foto kegiatan |
| Pengaturan | Buka pengaturan website |

### Fitur Produktivitas

| Fitur | Shortcut | Keterangan |
|-------|----------|------------|
| Pomodoro Timer | - | Timer 25 menit untuk fokus |
| Goal Tracker | - | Pantau target harian |
| Quick Notes | - | Catatan auto-save di browser |
| Focus Mode | - | Sembunyikan distraksi |
| Command Palette | Ctrl+K | Navigasi cepat |
| Dark Mode | - | Toggle tema gelap |

---

## Manajemen Konten

### Berita (berita.php)

**Akses:** Admin → Berita

| Aksi | Cara |
|------|------|
| Tambah | Klik "Tambah Berita", isi form, simpan |
| Edit | Klik ikon pensil pada baris berita |
| Hapus | Klik ikon tempat sampah, konfirmasi |
| Publish | Ubah status Draft menjadi Published |
| Arsip | Ubah status menjadi Archived |

**Field Form Berita:**

| Field | Type | Required | Keterangan |
|-------|------|----------|------------|
| Judul | Text | Ya | Judul berita |
| Konten | Rich Text | Ya | Isi berita |
| Excerpt | Textarea | Tidak | Ringkasan singkat |
| Kategori | Select | Ya | Akademik/Pengumuman/Prestasi/Kegiatan/Riset/Umum |
| Gambar | File Upload | Tidak | Maks 2MB, JPG/PNG/WEBP/GIF |
| Penulis | Text | Tidak | Nama penulis |
| Status | Select | Ya | Draft/Published/Archived |
| Tags | Text | Tidak | Tag dipisah koma |

### Program Studi (program.php)

**Akses:** Admin → Program Studi

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Kode | Kode prodi (PMAT, PFIS, PBIO, dll) |
| Nama | Nama lengkap prodi |
| Singkatan | Singkatan prodi |
| Jenjang | D3/S1/S2/S3 |
| Akreditasi | Unggul/Baik Sekali/Baik/Terakreditasi |
| Ketua Prodi | Nama ketua prodi |
| Visi | Visi prodi |
| Misi | Misi prodi |
| Kurikulum | Info kurikulum |
| Prospek Kerja | Prospek kerja lulusan |
| Logo | Upload logo prodi |
| Banner | Upload banner prodi |

### Dosen (dosen.php)

**Akses:** Admin → Dosen

**Field Form:**

| Field | Keterangan |
|-------|------------|
| NIDN | Nomor Induk Dosen Nasional |
| Nama | Nama lengkap dosen |
| Gelar Depan | Dr., Prof., dll |
| Gelar Belakang | M.Pd., M.Si., dll |
| Program Studi | Pilih dari dropdown |
| Jabatan Fungsional | Asisten Ahli/Lektor/Lektor Kepala/Guru Besar |
| Pendidikan Terakhir | S2/S3 |
| Email | Email dosen |
| Bidang Keahlian | Area keahlian |
| Foto | Upload foto dosen |
| Google Scholar | Link profil |
| Scopus | Link profil |

### Agenda (agenda.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Judul | Nama agenda |
| Tanggal Mulai | Tanggal mulai |
| Tanggal Selesai | Tanggal selesai (opsional) |
| Jenis | Ujian/Libur/Seminar/Wisuda/PMB/Umum |
| Deskripsi | Detail agenda |
| Lokasi | Tempat pelaksanaan |
| Status | Aktif/Selesai/Dibatalkan |

### Galeri (galeri.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Judul | Judul foto |
| Deskripsi | Deskripsi foto |
| Gambar | Upload foto (required) |
| Kategori | Kategori foto |
| Tanggal | Tanggal kegiatan |
| Status | Published/Draft |

### Video (video.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Judul | Judul video |
| Deskripsi | Deskripsi video |
| Video URL | Link YouTube/Vimeo |
| Kategori | Pilih dari video_kategori |
| Thumbnail | Upload thumbnail |
| Durasi | Durasi video (mm:ss) |
| Tags | Tag video |
| Status | Draft/Published/Archived |

### Download Center (download.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Judul | Nama dokumen |
| Kategori | Akademik/PMB/Formulir/Pedoman/Lainnya |
| Deskripsi | Deskripsi dokumen |
| File | Upload file (PDF/DOC/ZIP) |
| Tags | Tag dokumen |
| Status | Aktif/Non-Aktif |

### Prestasi (prestasi.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Judul | Judul prestasi |
| Mahasiswa | Nama mahasiswa |
| Program Studi | Pilih prodi |
| Tingkat | Universitas/Wilayah/Nasional/Internasional |
| Juara | Juara 1/2/3, dll |
| Lomba | Nama lomba |
| Tahun | Tahun prestasi |
| Foto | Upload foto |

### Alumni (alumni.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Nama | Nama alumni |
| Program Studi | Pilih prodi |
| Tahun Lulus | Tahun lulus |
| Pekerjaan | Pekerjaan saat ini |
| Perusahaan | Tempat kerja |
| Lokasi | Kota lokasi kerja |
| Foto | Upload foto |
| LinkedIn | Link profil LinkedIn |
| Testimoni | Testimoni alumni |
| Prestasi | Prestasi selama kuliah |

### Beasiswa (beasiswa.php)

**Field Form:**

| Field | Keterangan |
|-------|------------|
| Nama | Nama beasiswa |
| Jenis | Prestasi Akademik/KIP Kuliah/Muhammadiyah/Talent Scouting/Lainnya |
| Sumber | Sumber dana |
| Nominal | Nominal beasiswa |
| Syarat | Persyaratan |
| Deadline | Tanggal deadline |
| Status | Terbuka/Tertutup |

### Kontak (kontak.php)

**Kelola pesan masuk dari form kontak publik.**

| Status | Keterangan |
|--------|------------|
| Baru | Pesan belum dibaca |
| Dibaca | Pesan sudah dibaca |
| Dibalas | Pesan sudah dibalas |
| Arsip | Pesan diarsipkan |
| Prioritas | Pesan prioritas tinggi |

### Pengaturan Lainnya

| Menu | Fungsi |
|------|--------|
| Fasilitas | Kelola sarana prasarana |
| Akreditasi | Kelola status akreditasi |
| Kerjasama | Kelola mitra kerjasama |
| Jurnal | Kelola jurnal ilmiah |
| Blog | Kelola artikel blog dosen |
| FAQ | Kelola pertanyaan umum |
| Testimoni | Kelola testimoni |
| Riset | Kelola penelitian |
| Statistik | Update angka statistik |

---

## Pengaturan Sistem

### Akses: admin/pengaturan.php

### Tab Identitas

| Field | Contoh Nilai |
|-------|-------------|
| Nama Fakultas | Fakultas Keguruan dan Ilmu Pendidikan |
| Nama Universitas | Universitas Muhammadiyah Maumere |
| Singkatan | FKIP UNIMOF |
| Alamat | Jl. Bhayangkara No.1, Maumere, Sikka, NTT |
| Telepon | (0382) 21234 |
| Email | fkip@unimof.ac.id |
| Website | www.fkip-unimof.ac.id |
| Deskripsi | Deskripsi singkat fakultas |

### Tab Media Sosial

| Field | Contoh Nilai |
|-------|-------------|
| Instagram | https://instagram.com/fkipunimof |
| Facebook | https://facebook.com/fkipunimof |
| YouTube | https://youtube.com/@fkipunimof |
| WhatsApp | 6281234567890 |

### Tab PMB

| Field | Contoh Nilai |
|-------|-------------|
| Gelombang | Gelombang 1 |
| Deadline | 2026-01-31 |
| Biaya Pendaftaran | 300000 |
| Rekening Bank | Bank NTT - 1234567890 a.n. FKIP UNIMOF |

### Tab Hero Video

| Field | Keterangan |
|-------|------------|
| Upload Video | File MP4/WEBM, maks 50MB |
| Upload Poster | Gambar poster video |
| Aktifkan | Toggle on/off |

### Tab Live Chat

| Field | Keterangan |
|-------|------------|
| Enable | Toggle on/off |
| Greeting | Pesan sambutan |
| Posisi | Kanan/Kiri |
| Warna | Warna widget |

### Tab Sistem

| Field | Keterangan |
|-------|------------|
| Maintenance Mode | Aktifkan mode pemeliharaan |
| Pesan Maintenance | Pesan saat maintenance |
| Enable Registration | Aktifkan registrasi |
| Timezone | Zona waktu |
| Format Tanggal | Format tampilan tanggal |

### Tab Logo dan Favicon

| Field | Keterangan |
|-------|------------|
| Upload Logo | PNG/JPG/SVG/WEBP, maks 2MB |
| Upload Favicon | ICO/PNG/SVG, maks 1MB |

Logo otomatis terdeteksi di header dan preloader.

---

## Keamanan

### Tips Keamanan Admin

1. Ganti password secara berkala (minimal 3 bulan sekali)
2. Aktifkan 2FA untuk lapisan keamanan tambahan
3. Jangan share kredensial login
4. Logout setelah selesai menggunakan panel admin
5. Periksa log login secara berkala
6. Gunakan HTTPS untuk akses admin panel
7. Backup database secara rutin

### Role Admin

| Role | Hak Akses |
|------|-----------|
| superadmin | Akses penuh semua fitur + kelola admin |
| admin | Akses konten, tanpa kelola admin |

---

**Last Updated:** October 2026
**Version:** 2.0.0
**Author:** FKIP UNIMOF Development Team