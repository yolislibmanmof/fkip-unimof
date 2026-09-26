-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 26, 2026 at 08:33 AM
-- Server version: 5.7.39
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fkip`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('superadmin','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$HZABzARqxulnUeaA54n6EuAhXHvu1eWLxxWA5h5Y1VQtshRiiWVA2', 'admin@fkip-unimof.ac.id', 'Super Admin', 'superadmin', '2026-09-26 04:37:32', '2026-09-25 01:33:39');

-- --------------------------------------------------------

--
-- Table structure for table `agenda`
--

CREATE TABLE `agenda` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `jenis` enum('ujian','libur','seminar','wisuda','pmb','umum') COLLATE utf8mb4_unicode_ci DEFAULT 'umum',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Aktif','Selesai','Dibatalkan') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agenda`
--

INSERT INTO `agenda` (`id`, `judul`, `tanggal_mulai`, `tanggal_selesai`, `jenis`, `deskripsi`, `lokasi`, `status`, `created_at`) VALUES
(1, 'Ujian Tengah Semester Ganjil', '2026-10-09', '2026-10-21', 'ujian', 'Ujian tengah semester ganjil', NULL, 'Aktif', '2026-09-25 01:52:08'),
(2, 'Seminar Nasional Pendidikan', '2026-10-25', NULL, 'seminar', 'Seminar nasional di aula FKIP', NULL, 'Aktif', '2026-09-25 01:52:08'),
(3, 'Wisuda Periode I', '2026-11-24', NULL, 'wisuda', 'Wisuda sarjana periode I', NULL, 'Aktif', '2026-09-25 01:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `akreditasi`
--

CREATE TABLE `akreditasi` (
  `id` int(11) NOT NULL,
  `nama_prodi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `badan_akreditasi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'BAN-PT',
  `peringkat` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_sk` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_terbit` date DEFAULT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `sertifikat_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Aktif','Kadaluarsa','Proses') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `akreditasi`
--

INSERT INTO `akreditasi` (`id`, `nama_prodi`, `badan_akreditasi`, `peringkat`, `nomor_sk`, `tanggal_terbit`, `tanggal_berlaku`, `sertifikat_file`, `status`, `created_at`) VALUES
(1, 'Pendidikan Matematika', 'BAN-PT', 'Unggul', '1234/SK/BAN-PT/Akred/S/2023', '2023-01-15', '2028-01-15', NULL, 'Aktif', '2026-09-25 16:14:09'),
(2, 'Pendidikan Biologi', 'BAN-PT', 'Unggul', '1235/SK/BAN-PT/Akred/S/2023', '2023-02-20', '2028-02-20', NULL, 'Aktif', '2026-09-25 16:14:09'),
(3, 'Pendidikan Bahasa Inggris', 'LAMDIK', 'Baik Sekali', '1236/SK/LAMDIK/Akred/S/2022', '2022-05-10', '2027-05-10', NULL, 'Aktif', '2026-09-25 16:14:09');

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `tahun_lulus` int(11) DEFAULT NULL,
  `pekerjaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perusahaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lokasi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `testimonial` text COLLATE utf8mb4_unicode_ci,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `testimoni` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `prestasi` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumni`
--

INSERT INTO `alumni` (`id`, `nama`, `program_studi_id`, `tahun_lulus`, `pekerjaan`, `perusahaan`, `lokasi`, `testimonial`, `foto`, `linkedin`, `created_at`, `testimoni`, `status`, `prestasi`) VALUES
(1, 'Yohanes Berchmans', 1, 2020, 'Guru Matematika', 'SMA Negeri 1 Maumere', 'Maumere, NTT', NULL, NULL, NULL, '2026-09-25 12:58:36', 'FKIP UNIMOF membentuk saya menjadi pendidik yang kompeten dan berkarakter.', 'Aktif', 'Juara 1 Olimpiade Matematika Tingkat Provinsi 2023'),
(2, 'Maria Klarissa', 3, 2019, 'Peneliti Biologi', 'LIPI', 'Jakarta', NULL, NULL, NULL, '2026-09-25 12:58:36', 'Pengalaman kuliah di FKIP UNIMOF sangat berkesan. Dosen-dosen yang supportive.', 'Aktif', 'Medali Emas PIMNAS 2022'),
(3, 'Petrus Kleden', 5, 2021, 'Guru Bahasa Inggris', 'SMP Negeri 2 Ende', 'Ende, NTT', NULL, NULL, NULL, '2026-09-25 12:58:36', 'Saya bangga menjadi alumni FKIP UNIMOF. Ilmu yang didapat sangat aplikatif.', 'Aktif', 'Guru Berprestasi Tingkat Kabupaten 2024'),
(4, 'Agnes Doa', 7, 2018, 'Wirausaha', 'Doa Education Center', 'Maumere, NTT', NULL, NULL, NULL, '2026-09-25 12:58:36', 'FKIP UNIMOF mengajarkan jiwa entrepreneurship. Sekarang saya punya lembaga bimbingan belajar sendiri.', 'Aktif', 'Founder Education Center dengan 200+ siswa'),
(5, 'Dominggus Tefa', 2, 2022, 'Lanjut Studi', 'Universitas Gadjah Mada', 'Yogyakarta', NULL, NULL, NULL, '2026-09-25 12:58:36', 'Beasiswa berkat prestasi di FKIP UNIMOF membawa saya ke S2 di UGM.', 'Aktif', 'Penerima Beasiswa LPDP 2023');

-- --------------------------------------------------------

--
-- Table structure for table `beasiswa`
--

CREATE TABLE `beasiswa` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` enum('Prestasi Akademik','KIP Kuliah','Muhammadiyah','Talent Scouting','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Lainnya',
  `sumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nominal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `syarat` text COLLATE utf8mb4_unicode_ci,
  `deadline` date DEFAULT NULL,
  `status` enum('Terbuka','Tertutup') COLLATE utf8mb4_unicode_ci DEFAULT 'Terbuka',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `beasiswa`
--

INSERT INTO `beasiswa` (`id`, `nama`, `jenis`, `sumber`, `nominal`, `syarat`, `deadline`, `status`, `created_at`) VALUES
(1, 'Beasiswa Prestasi Akademik', 'Prestasi Akademik', 'Yayasan Muhammadiyah', '100% SPP + Uang Pangkal', 'Rata-rata rapor minimal 85, Juara 1-3 tingkat Kabupaten/Provinsi', '2026-08-30', 'Terbuka', '2026-09-25 15:13:56'),
(2, 'Beasiswa KIP Kuliah', 'KIP Kuliah', 'Kemdikbudristek', '100% SPP + Uang Saku', 'Memiliki KIP, penghasilan orang tua maksimal Rp 4.000.000/bulan', '2026-07-15', 'Terbuka', '2026-09-25 15:13:56');

-- --------------------------------------------------------

--
-- Table structure for table `berita`
--

CREATE TABLE `berita` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `konten` longtext COLLATE utf8mb4_unicode_ci,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `gambar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori` enum('Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum') COLLATE utf8mb4_unicode_ci DEFAULT 'Umum',
  `penulis` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `status` enum('Draft','Published','Archived') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `is_featured` tinyint(4) DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `berita`
--

INSERT INTO `berita` (`id`, `judul`, `slug`, `konten`, `excerpt`, `gambar`, `kategori`, `penulis`, `views`, `status`, `is_featured`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 'Pengenalan Kehidupan Kampus Mahasiswa Baru FKIP UNIMOF 2026', 'pengenalan-kehidupan-kampus-mahasiswa-baru-fkip-unimof-2026', '<p>Mahasiswa baru Universitas Muhammadiyah Maumere Tahun 2026 mendapatkan kesempatan mengenal lebih dekat Fakultas Keguruan dan Ilmu Pendidikan beserta delapan program studinya.</p>', 'Mahasiswa baru UNIMOF mendapatkan pembekalan awal kehidupan akademik di FKIP.', NULL, 'Akademik', 'Humas FKIP', 1, 'Published', 1, '2026-09-25 01:52:08', '2026-09-25 01:52:08', '2026-09-25 02:59:56'),
(2, 'FKIP UNIMOF Raih Akreditasi Unggul untuk Tiga Program Studi', 'fkip-unimof-raih-akreditasi-unggul-untuk-tiga-program-studi', '<p>Tiga program studi FKIP resmi meraih predikat Unggul dari BAN-PT setelah melalui proses asesmen lapangan yang ketat.</p>', 'Tiga program studi FKIP UNIMOF resmi meraih predikat Unggul dari BAN-PT.', NULL, 'Prestasi', 'Humas FKIP', 0, 'Published', 1, '2026-09-25 01:52:08', '2026-09-25 01:52:08', '2026-09-25 01:52:08'),
(3, 'Seminar Nasional Pendidikan: Guru Merdeka di Era Digital', 'seminar-nasional-pendidikan-guru-merdeka-di-era-digital', '<p>FKIP menyelenggarakan seminar nasional bersama pakar pendidikan dari berbagai universitas di Indonesia Timur.</p>', 'FKIP UNIMOF menyelenggarakan seminar nasional pendidikan era digital.', NULL, 'Kegiatan', 'Humas FKIP', 1, 'Published', 0, '2026-09-25 01:52:08', '2026-09-25 01:52:08', '2026-09-26 02:47:17');

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `id` int(11) NOT NULL,
  `nidn` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gelar_depan` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gelar_belakang` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `jabatan_fungsional` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pendidikan_terakhir` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bidang_keahlian` text COLLATE utf8mb4_unicode_ci,
  `publikasi` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aktif','Cuti','Pensiun') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id`, `nidn`, `nama`, `gelar_depan`, `gelar_belakang`, `program_studi_id`, `jabatan_fungsional`, `pendidikan_terakhir`, `email`, `telepon`, `foto`, `bidang_keahlian`, `publikasi`, `status`, `created_at`) VALUES
(1, '0001018501', 'Budi Santoso', 'Dr.', 'M.Pd.', 1, 'Lektor Kepala', 'S3 Pendidikan Matematika', 'budi@unimof.ac.id', NULL, NULL, NULL, NULL, 'Aktif', '2026-09-25 01:52:08'),
(2, '0002038702', 'Siti Aminah', 'Dr.', 'M.Si.', 3, 'Lektor', 'S3 Biologi', 'siti@unimof.ac.id', NULL, NULL, NULL, NULL, 'Aktif', '2026-09-25 01:52:08'),
(3, '0003059003', 'Andi Pratama', '', 'M.Pd.', 5, 'Asisten Ahli', 'S2 Pendidikan Bahasa Inggris', 'andi@unimof.ac.id', NULL, NULL, NULL, NULL, 'Aktif', '2026-09-25 01:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `downloads`
--

CREATE TABLE `downloads` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('Akademik','PMB','Formulir','Pedoman','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Lainnya',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `downloads_count` int(11) DEFAULT '0',
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fasilitas`
--

CREATE TABLE `fasilitas` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('Laboratorium','Ruang Kelas','Perpustakaan','Fasilitas Umum','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Lainnya',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `gambar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kapasitas` int(11) DEFAULT '0',
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fasilitas`
--

INSERT INTO `fasilitas` (`id`, `nama`, `kategori`, `deskripsi`, `gambar`, `kapasitas`, `status`, `created_at`) VALUES
(1, 'Laboratorium Microteaching', 'Laboratorium', 'Ruang praktik mengajar dengan rekaman video 4K dan one-way mirror.', NULL, 30, 'Aktif', '2026-09-25 15:13:56'),
(2, 'Perpustakaan Digital', 'Perpustakaan', 'Akses ke 10.000+ e-book dan jurnal internasional (Scopus/WoS).', NULL, 200, 'Aktif', '2026-09-25 15:13:56'),
(3, 'Ruang Kelas Smart TV', 'Ruang Kelas', 'Dilengkapi Smart TV 65 inch dan koneksi internet fiber optic.', NULL, 40, 'Aktif', '2026-09-25 15:13:56');

-- --------------------------------------------------------

--
-- Table structure for table `jurnal`
--

CREATE TABLE `jurnal` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `penerbit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `akreditasi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Belum Terakreditasi',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jurnal`
--

INSERT INTO `jurnal` (`id`, `nama`, `penerbit`, `issn`, `akreditasi`, `url`, `deskripsi`, `status`, `created_at`) VALUES
(1, 'Jurnal Pendidikan FKIP', 'FKIP UNIMOF', '1234-5678', 'Sinta 3', 'https://jurnal.unimof.ac.id', 'Jurnal ilmiah bidang pendidikan dan pengajaran.', 'Aktif', '2026-09-25 15:13:56'),
(2, 'Maumere Science Review', 'FKIP UNIMOF', '8765-4321', 'Sinta 4', 'https://msr.unimof.ac.id', 'Jurnal sains dan teknologi terapan.', 'Aktif', '2026-09-25 15:13:56');

-- --------------------------------------------------------

--
-- Table structure for table `kerjasama`
--

CREATE TABLE `kerjasama` (
  `id` int(11) NOT NULL,
  `nama_institusi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `negara` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Indonesia',
  `jenis` enum('Universitas','Industri','Pemerintah','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Lainnya',
  `bentuk_kerjasama` text COLLATE utf8mb4_unicode_ci,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kerjasama`
--

INSERT INTO `kerjasama` (`id`, `nama_institusi`, `negara`, `jenis`, `bentuk_kerjasama`, `tanggal_mulai`, `tanggal_selesai`, `logo`, `status`, `created_at`) VALUES
(1, 'Universiti Malaya', 'Malaysia', 'Universitas', 'Pertukaran Mahasiswa & Riset Bersama', NULL, NULL, NULL, 'Aktif', '2026-09-25 16:14:09'),
(2, 'Sakarya University', 'Turki', 'Universitas', 'Joint Research & Faculty Exchange', NULL, NULL, NULL, 'Aktif', '2026-09-25 16:14:09'),
(3, 'Dinas Pendidikan Kab. Sikka', 'Indonesia', 'Pemerintah', 'Program Magang & Praktik Mengajar', NULL, NULL, NULL, 'Aktif', '2026-09-25 16:14:09'),
(4, 'PT. Telkom Indonesia', 'Indonesia', 'Industri', 'Digital Literacy Training & Internship', NULL, NULL, NULL, 'Aktif', '2026-09-25 16:14:09');

-- --------------------------------------------------------

--
-- Table structure for table `kontak`
--

CREATE TABLE `kontak` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telepon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subjek` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Baru','Dibaca','Dibalas') COLLATE utf8mb4_unicode_ci DEFAULT 'Baru',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL,
  `nama_key` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nilai` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `nama_key`, `nilai`, `updated_at`) VALUES
(1, 'nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan', '2026-09-25 01:37:54'),
(2, 'nama_universitas', 'Universitas Muhammadiyah Maumere', '2026-09-25 01:37:54'),
(3, 'singkatan', 'FKIP UNIMOF', '2026-09-25 01:37:54'),
(4, 'alamat', 'Jl. Bhayangkara No.1, Maumere, Sikka, NTT', '2026-09-25 01:37:54'),
(5, 'telepon', '(0382) 21234', '2026-09-25 01:37:54'),
(6, 'email', 'fkip@unimof.ac.id', '2026-09-25 01:37:54'),
(7, 'website', 'www.fkip-unimof.ac.id', '2026-09-25 01:37:54');

-- --------------------------------------------------------

--
-- Table structure for table `prestasi`
--

CREATE TABLE `prestasi` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mahasiswa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `tingkat` enum('Universitas','Wilayah','Nasional','Internasional') COLLATE utf8mb4_unicode_ci DEFAULT 'Nasional',
  `juara` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lomba` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tahun` int(11) DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prestasi`
--

INSERT INTO `prestasi` (`id`, `judul`, `mahasiswa`, `program_studi_id`, `tingkat`, `juara`, `lomba`, `tahun`, `deskripsi`, `foto`, `created_at`) VALUES
(1, 'Juara 1 Olimpiade Matematika Nasional', 'Yohanes Berchmans', 1, 'Nasional', 'Juara 1', 'Olimpiade Matematika Nasional', 2025, NULL, NULL, '2026-09-25 01:52:08'),
(2, 'Medali Emas Pekan Ilmiah Mahasiswa Nasional', 'Maria Klarissa', 3, 'Nasional', 'Juara 1', 'PIMNAS', 2025, NULL, NULL, '2026-09-25 01:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `program_studi`
--

CREATE TABLE `program_studi` (
  `id` int(11) NOT NULL,
  `kode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `singkatan` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenjang` enum('D3','S1','S2','S3') COLLATE utf8mb4_unicode_ci DEFAULT 'S1',
  `akreditasi` enum('Unggul','Baik Sekali','Baik','Terakreditasi') COLLATE utf8mb4_unicode_ci DEFAULT 'Terakreditasi',
  `ketua_prodi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nip_kaprodi` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `visi` text COLLATE utf8mb4_unicode_ci,
  `misi` text COLLATE utf8mb4_unicode_ci,
  `kurikulum` text COLLATE utf8mb4_unicode_ci,
  `prospek_kerja` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_dosen` int(11) DEFAULT '0',
  `jumlah_mahasiswa` int(11) DEFAULT '0',
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `urutan` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `program_studi`
--

INSERT INTO `program_studi` (`id`, `kode`, `nama`, `nama_en`, `singkatan`, `jenjang`, `akreditasi`, `ketua_prodi`, `nip_kaprodi`, `deskripsi`, `visi`, `misi`, `kurikulum`, `prospek_kerja`, `logo`, `banner`, `jumlah_dosen`, `jumlah_mahasiswa`, `status`, `urutan`, `created_at`, `updated_at`) VALUES
(1, 'PMAT', 'Pendidikan Matematika', NULL, 'PMAT', 'S1', 'Unggul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 1, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(2, 'PFIS', 'Pendidikan Fisika', NULL, 'PFIS', 'S1', 'Baik Sekali', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 2, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(3, 'PBIO', 'Pendidikan Biologi', NULL, 'PBIO', 'S1', 'Unggul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 3, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(4, 'PKIM', 'Pendidikan Kimia', NULL, 'PKIM', 'S1', 'Baik Sekali', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 4, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(5, 'PBIN', 'Pendidikan Bahasa dan Sastra Inggris', NULL, 'PBSI', 'S1', 'Unggul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 5, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(6, 'BSIND', 'Bahasa dan Sastra Indonesia', NULL, 'BSIND', 'S1', 'Baik Sekali', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 6, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(7, 'PEKO', 'Pendidikan Ekonomi', NULL, 'PEKO', 'S1', 'Unggul', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 7, '2026-09-25 01:33:39', '2026-09-25 01:33:39'),
(8, 'PKN', 'Pendidikan Kewarganegaraan', NULL, 'PKN', 'S1', 'Baik Sekali', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Aktif', 8, '2026-09-25 01:33:39', '2026-09-25 01:33:39');

-- --------------------------------------------------------

--
-- Table structure for table `riset`
--

CREATE TABLE `riset` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `abstrak` text COLLATE utf8mb4_unicode_ci,
  `ketua_id` int(11) DEFAULT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Umum',
  `jenis` enum('Publikasi','Hibah','Pengabdian','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Publikasi',
  `tahun` year(4) NOT NULL,
  `status` enum('Draft','Published','Archived') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `jurnal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anggota` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `riset`
--

INSERT INTO `riset` (`id`, `judul`, `deskripsi`, `abstrak`, `ketua_id`, `program_studi_id`, `kategori`, `jenis`, `tahun`, `status`, `jurnal`, `doi`, `anggota`, `created_at`, `updated_at`) VALUES
(1, 'Implementasi Kurikulum Merdeka di Sekolah Dasar NTT', 'Penelitian tentang penerapan kurikulum merdeka di wilayah NTT dengan pendekatan kualitatif.', NULL, NULL, NULL, 'Pendidikan', 'Publikasi', '2025', 'Published', 'Jurnal Pendidikan Indonesia', NULL, NULL, '2026-09-25 12:56:09', '2026-09-25 12:56:09'),
(2, 'Pengaruh Media Pembelajaran Digital terhadap Motivasi Belajar', 'Studi eksperimental pengaruh media digital terhadap motivasi belajar mahasiswa FKIP.', NULL, NULL, NULL, 'Teknologi Pendidikan', 'Publikasi', '2024', 'Published', 'Jurnal Teknologi Pendidikan', NULL, NULL, '2026-09-25 12:56:09', '2026-09-25 12:56:09'),
(3, 'Pengembangan Model Pembelajaran Berbasis Kearifan Lokal', 'Riset pengembangan model pembelajaran yang mengintegrasikan kearifan lokal Maumere.', NULL, NULL, NULL, 'Pendidikan', 'Hibah', '2025', 'Published', NULL, NULL, NULL, '2026-09-25 12:56:09', '2026-09-25 12:56:09'),
(4, 'Pemberdayaan Guru SD melalui Pelatihan Literasi Digital', 'Program pengabdian masyarakat untuk meningkatkan literasi digital guru SD.', NULL, NULL, NULL, 'Pengabdian', 'Pengabdian', '2024', 'Published', NULL, NULL, NULL, '2026-09-25 12:56:09', '2026-09-25 12:56:09');

-- --------------------------------------------------------

--
-- Table structure for table `statistik`
--

CREATE TABLE `statistik` (
  `id` int(11) NOT NULL,
  `total_mahasiswa` int(11) DEFAULT '0',
  `total_dosen` int(11) DEFAULT '0',
  `total_prodi` int(11) DEFAULT '8',
  `total_penelitian` int(11) DEFAULT '0',
  `total_alumni` int(11) DEFAULT '0',
  `tahun_ajaran` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `statistik`
--

INSERT INTO `statistik` (`id`, `total_mahasiswa`, `total_dosen`, `total_prodi`, `total_penelitian`, `total_alumni`, `tahun_ajaran`, `updated_at`) VALUES
(1, 1250, 68, 8, 45, 3200, '2025/2026', '2026-09-25 01:51:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `akreditasi`
--
ALTER TABLE `akreditasi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_studi_id` (`program_studi_id`);

--
-- Indexes for table `beasiswa`
--
ALTER TABLE `beasiswa`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `berita`
--
ALTER TABLE `berita`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nidn` (`nidn`),
  ADD KEY `program_studi_id` (`program_studi_id`);

--
-- Indexes for table `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fasilitas`
--
ALTER TABLE `fasilitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jurnal`
--
ALTER TABLE `jurnal`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kerjasama`
--
ALTER TABLE `kerjasama`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kontak`
--
ALTER TABLE `kontak`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_key` (`nama_key`);

--
-- Indexes for table `prestasi`
--
ALTER TABLE `prestasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_studi_id` (`program_studi_id`);

--
-- Indexes for table `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `riset`
--
ALTER TABLE `riset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_studi_id` (`program_studi_id`);

--
-- Indexes for table `statistik`
--
ALTER TABLE `statistik`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `akreditasi`
--
ALTER TABLE `akreditasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `beasiswa`
--
ALTER TABLE `beasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `berita`
--
ALTER TABLE `berita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `downloads`
--
ALTER TABLE `downloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fasilitas`
--
ALTER TABLE `fasilitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jurnal`
--
ALTER TABLE `jurnal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kerjasama`
--
ALTER TABLE `kerjasama`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `kontak`
--
ALTER TABLE `kontak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `prestasi`
--
ALTER TABLE `prestasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `program_studi`
--
ALTER TABLE `program_studi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `riset`
--
ALTER TABLE `riset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `statistik`
--
ALTER TABLE `statistik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumni`
--
ALTER TABLE `alumni`
  ADD CONSTRAINT `alumni_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prestasi`
--
ALTER TABLE `prestasi`
  ADD CONSTRAINT `prestasi_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `riset`
--
ALTER TABLE `riset`
  ADD CONSTRAINT `riset_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
