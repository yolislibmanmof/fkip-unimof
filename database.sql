-- Database: fkip
CREATE DATABASE IF NOT EXISTS fkip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fkip;

-- Tabel Admin
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('superadmin','admin') DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default admin (password: Admin@123)
INSERT INTO admins (username, password, email, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkJt0rEe0oUz90ZOJr2t7QYjKx7v8Q5L9V8mP6wN3kQ6lYq', 'admin@fkip-unimof.ac.id', 'Super Admin', 'superadmin');

-- Tabel Program Studi
CREATE TABLE program_studi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) UNIQUE,
    nama VARCHAR(100) NOT NULL,
    nama_en VARCHAR(100),
    singkatan VARCHAR(10),
    jenjang ENUM('D3','S1','S2','S3') DEFAULT 'S1',
    akreditasi ENUM('Unggul','Baik Sekali','Baik','Terakreditasi') DEFAULT 'Terakreditasi',
    ketua_prodi VARCHAR(100),
    nip_kaprodi VARCHAR(30),
    deskripsi TEXT,
    visi TEXT,
    misi TEXT,
    kurikulum TEXT,
    prospek_kerja TEXT,
    logo VARCHAR(255),
    banner VARCHAR(255),
    jumlah_dosen INT DEFAULT 0,
    jumlah_mahasiswa INT DEFAULT 0,
    status ENUM('Aktif','Non-Aktif') DEFAULT 'Aktif',
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO program_studi (kode, nama, singkatan, akreditasi, urutan) VALUES
('PMAT', 'Pendidikan Matematika', 'PMAT', 'Unggul', 1),
('PFIS', 'Pendidikan Fisika', 'PFIS', 'Baik Sekali', 2),
('PBIO', 'Pendidikan Biologi', 'PBIO', 'Unggul', 3),
('PKIM', 'Pendidikan Kimia', 'PKIM', 'Baik Sekali', 4),
('PBIN', 'Pendidikan Bahasa dan Sastra Inggris', 'PBSI', 'Unggul', 5),
('BSIND', 'Bahasa dan Sastra Indonesia', 'BSIND', 'Baik Sekali', 6),
('PEKO', 'Pendidikan Ekonomi', 'PEKO', 'Unggul', 7),
('PKN', 'Pendidikan Kewarganegaraan', 'PKN', 'Baik Sekali', 8);

-- Tabel Dosen
CREATE TABLE dosen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nidn VARCHAR(30) UNIQUE,
    nama VARCHAR(100) NOT NULL,
    gelar_depan VARCHAR(30),
    gelar_belakang VARCHAR(50),
    program_studi_id INT,
    jabatan_fungsional VARCHAR(50),
    pendidikan_terakhir VARCHAR(100),
    email VARCHAR(100),
    telepon VARCHAR(20),
    foto VARCHAR(255),
    bidang_keahlian TEXT,
    publikasi TEXT,
    status ENUM('Aktif','Cuti','Pensiun') DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_studi_id) REFERENCES program_studi(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabel Berita
CREATE TABLE berita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    konten LONGTEXT,
    excerpt TEXT,
    gambar VARCHAR(255),
    kategori ENUM('Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum') DEFAULT 'Umum',
    penulis VARCHAR(100),
    views INT DEFAULT 0,
    status ENUM('Draft','Published','Archived') DEFAULT 'Draft',
    is_featured TINYINT DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Prestasi
CREATE TABLE prestasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    mahasiswa VARCHAR(255),
    program_studi_id INT,
    tingkat ENUM('Universitas','Wilayah','Nasional','Internasional') DEFAULT 'Nasional',
    juara VARCHAR(50),
    lomba VARCHAR(255),
    tahun INT,
    deskripsi TEXT,
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_studi_id) REFERENCES program_studi(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabel Alumni
CREATE TABLE alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    program_studi_id INT,
    tahun_lulus INT,
    pekerjaan VARCHAR(255),
    perusahaan VARCHAR(255),
    lokasi VARCHAR(100),
    testimonial TEXT,
    foto VARCHAR(255),
    linkedin VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_studi_id) REFERENCES program_studi(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabel Agenda/Kalender
CREATE TABLE agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE,
    jenis ENUM('ujian','libur','seminar','wisuda','pmb','umum') DEFAULT 'umum',
    deskripsi TEXT,
    lokasi VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Kontak/Pesan
CREATE TABLE kontak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telepon VARCHAR(20),
    subjek VARCHAR(255),
    pesan TEXT,
    status ENUM('Baru','Dibaca','Dibalas') DEFAULT 'Baru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Pengaturan Website
CREATE TABLE pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_key VARCHAR(50) UNIQUE,
    nilai TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO pengaturan (nama_key, nilai) VALUES
('nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan'),
('nama_universitas', 'Universitas Muhammadiyah Maumere'),
('singkatan', 'FKIP UNIMOF'),
('alamat', 'Jl. Bhayangkara No.1, Maumere, Sikka, NTT'),
('telepon', '(0382) 21234'),
('email', 'fkip@unimof.ac.id', 'website', 'www.fkip-nimof.ac.id');

-- Tabel Statistik (untuk cache)
CREATE TABLE statistik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_mahasiswa INT DEFAULT 0,
    total_dosen INT DEFAULT 0,
    total_prodi INT DEFAULT 8,
    total_penelitian INT DEFAULT 0,
    total_alumni INT DEFAULT 0,
    tahun_ajaran VARCHAR(20),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO statistik (total_mahasiswa, total_dosen, total_prodi, total_penelitian, total_alumni, tahun_ajaran)
VALUES (1250, 68, 8, 45, 3200, '2025/2026');

-- Index untuk performa
CREATE INDEX idx_berita_status ON berita(status);
CREATE INDEX idx_berita_published ON berita(published_at);
CREATE INDEX idx_prestasi_tahun ON prestasi(tahun);