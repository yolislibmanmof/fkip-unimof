-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2026 at 01:08 PM
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
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_secret` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `role`, `status`, `last_login`, `last_ip`, `last_user_agent`, `two_factor_enabled`, `two_factor_secret`, `created_at`) VALUES
(1, 'admin', '$2y$10$HZABzARqxulnUeaA54n6EuAhXHvu1eWLxxWA5h5Y1VQtshRiiWVA2', 'admin@fkip-unimof.ac.id', 'Super Admin', 'superadmin', 'Active', '2026-10-01 11:57:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 0, NULL, '2026-09-25 01:33:39');

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_logs`
--

CREATE TABLE `admin_login_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_login_logs`
--

INSERT INTO `admin_login_logs` (`id`, `admin_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'success', '2026-09-27 02:45:32'),
(2, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'success', '2026-09-27 02:45:42'),
(3, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'success', '2026-09-27 08:41:52'),
(4, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'success', '2026-09-27 10:28:07'),
(5, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'success', '2026-09-27 12:38:08'),
(6, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 00:12:18'),
(7, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 00:50:49'),
(8, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 00:51:45'),
(9, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 00:57:12'),
(10, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 02:10:35'),
(11, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 02:41:29'),
(12, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 03:18:29'),
(13, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 03:47:23'),
(14, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 03:57:03'),
(15, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:03:44'),
(16, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:12:23'),
(17, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:37:24'),
(18, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:51:21'),
(19, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:52:06'),
(20, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:53:08'),
(21, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 04:53:33'),
(22, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 05:04:14'),
(23, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 05:08:24'),
(24, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 05:20:16'),
(25, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 05:21:19'),
(26, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 06:58:51'),
(27, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 07:07:21'),
(28, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 10:19:27'),
(29, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 11:24:58'),
(30, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'success', '2026-10-01 11:57:58');

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
(2, 'Pendidikan Biologi', 'BAN-PT', 'Unggul', '1235/SK/BAN-PT/Akred/S/2023', '2023-02-20', '2028-02-20', NULL, 'Aktif', '2026-09-25 16:14:09');

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

INSERT INTO `alumni` (`id`, `nama`, `program_studi_id`, `tahun_lulus`, `pekerjaan`, `perusahaan`, `lokasi`, `foto`, `linkedin`, `created_at`, `testimoni`, `status`, `prestasi`) VALUES
(1, 'Yohanes Berchmans', 1, 2020, 'Guru Matematika', 'SMA Negeri 1 Maumere', 'Maumere, NTT', NULL, NULL, '2026-09-25 12:58:36', 'FKIP UNIMOF membentuk saya menjadi pendidik yang kompeten dan berkarakter.', 'Aktif', 'Juara 1 Olimpiade Matematika Tingkat Provinsi 2023'),
(2, 'Maria Klarissa', 3, 2019, 'Peneliti Biologi', 'LIPI', 'Jakarta', NULL, NULL, '2026-09-25 12:58:36', 'Pengalaman kuliah di FKIP UNIMOF sangat berkesan. Dosen-dosen yang supportive.', 'Aktif', 'Medali Emas PIMNAS 2022'),
(3, 'Petrus Kleden', 5, 2021, 'Guru Bahasa Inggris', 'SMP Negeri 2 Ende', 'Ende, NTT', NULL, NULL, '2026-09-25 12:58:36', 'Saya bangga menjadi alumni FKIP UNIMOF. Ilmu yang didapat sangat aplikatif.', 'Aktif', 'Guru Berprestasi Tingkat Kabupaten 2024'),
(5, 'Dominggus Tefa', 2, 2022, 'Lanjut Studi', 'Universitas Gadjah Mada', 'Yogyakarta', NULL, NULL, '2026-09-25 12:58:36', 'Beasiswa berkat prestasi di FKIP UNIMOF membawa saya ke S2 di UGM.', 'Aktif', 'Penerima Beasiswa LPDP 2023');

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
(1, 'Beasiswa Prestasi Akademik', 'Prestasi Akademik', 'Yayasan Muhammadiyah', '100% SPP + Uang Pangkal', 'Rata-rata rapor minimal 85, Juara 1-3 tingkat Kabupaten/Provinsi', '2026-08-30', 'Tertutup', '2026-09-25 15:13:56');

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
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `berita`
--

INSERT INTO `berita` (`id`, `judul`, `slug`, `konten`, `excerpt`, `gambar`, `kategori`, `penulis`, `views`, `status`, `is_featured`, `tags`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 'Pengenalan Kehidupan Kampus Mahasiswa Baru FKIP UNIMOF 2026', 'pengenalan-kehidupan-kampus-mahasiswa-baru-fkip-unimof-2026', '<p>Mahasiswa baru Universitas Muhammadiyah Maumere Tahun 2026 mendapatkan kesempatan mengenal lebih dekat Fakultas Keguruan dan Ilmu Pendidikan beserta delapan program studinya.</p>', 'Mahasiswa baru UNIMOF mendapatkan pembekalan awal kehidupan akademik di FKIP di lingkungan kampus.', NULL, 'Akademik', 'Humas FKIP', 2, 'Published', 1, '', '2026-09-25 01:52:08', '2026-09-25 01:52:08', '2026-10-01 06:59:45'),
(2, 'FKIP UNIMOF Raih Akreditasi Unggul untuk Tiga Program Studi', 'fkip-unimof-raih-akreditasi-unggul-untuk-tiga-program-studi', '<p>Tiga program studi FKIP resmi meraih predikat Unggul dari BAN-PT setelah melalui proses asesmen lapangan yang ketat.</p>', 'Tiga program studi FKIP UNIMOF resmi meraih predikat Unggul dari BAN-PT.', NULL, 'Prestasi', 'Humas FKIP', 0, 'Published', 1, NULL, '2026-09-25 01:52:08', '2026-09-25 01:52:08', '2026-09-25 01:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `blog_artikel`
--

CREATE TABLE `blog_artikel` (
  `id` int(11) NOT NULL,
  `dosen_id` int(11) DEFAULT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `konten` longtext COLLATE utf8mb4_unicode_ci,
  `gambar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori` enum('AI & Teknologi','Tips Riset','Pendidikan','Pengabdian','Opini') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendidikan',
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `likes` int(11) DEFAULT '0',
  `reading_time` int(11) DEFAULT '5' COMMENT 'dalam menit',
  `is_featured` tinyint(1) DEFAULT '0',
  `status` enum('Draft','Published','Archived') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_artikel`
--

INSERT INTO `blog_artikel` (`id`, `dosen_id`, `program_studi_id`, `judul`, `slug`, `excerpt`, `konten`, `gambar`, `kategori`, `tags`, `views`, `likes`, `reading_time`, `is_featured`, `status`, `published_at`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'Peran AI dalam Pendidikan Matematika di Era Digital', 'peran-ai-pendidikan-matematika', 'Bagaimana artificial intelligence mengubah cara kita mengajar matematika di abad 21.', '<p>Artificial Intelligence telah membuka peluang baru dalam pendidikan matematika...</p>', NULL, 'AI & Teknologi', 'AI, pendidikan, matematika', 0, 0, 8, 1, 'Published', '2026-09-27 08:12:38', '2026-09-27 08:12:38', '2026-09-27 08:12:38'),
(2, 2, 3, 'Tips Menulis Proposal Riset untuk Mahasiswa S1', 'tips-proposal-riset-s1', 'Panduan praktis menulis proposal riset yang baik untuk mahasiswa semester akhir.', '<p>Menulis proposal riset adalah keterampilan penting bagi mahasiswa...</p>', NULL, 'Tips Riset', 'riset, proposal, skripsi', 1, 0, 6, 1, 'Published', '2026-09-27 08:12:38', '2026-09-27 08:12:38', '2026-09-27 08:29:32'),
(3, NULL, NULL, 'Pentingnya Kearifan Lokal dalam Pembelajaran Modern', 'kearifan-lokal-pembelajaran', 'Mengintegrasikan budaya lokal NTT dalam kurikulum pendidikan modern.', '<p>Kearifan lokal adalah kekayaan yang harus dilestarikan...</p>', NULL, 'Pendidikan', 'budaya, NTT, kurikulum', 0, 0, 5, 0, 'Published', '2026-09-27 08:12:38', '2026-09-27 08:12:38', '2026-09-27 08:12:38');

-- --------------------------------------------------------

--
-- Table structure for table `blog_komentar`
--

CREATE TABLE `blog_komentar` (
  `id` int(11) NOT NULL,
  `artikel_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL COMMENT 'untuk reply',
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `komentar` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Approved','Spam') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `google_scholar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopus` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `riwayat_pendidikan` text COLLATE utf8mb4_unicode_ci,
  `penelitian` text COLLATE utf8mb4_unicode_ci,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Aktif','Cuti','Pensiun') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id`, `nidn`, `nama`, `gelar_depan`, `gelar_belakang`, `program_studi_id`, `jabatan_fungsional`, `pendidikan_terakhir`, `email`, `telepon`, `foto`, `bidang_keahlian`, `publikasi`, `google_scholar`, `scopus`, `riwayat_pendidikan`, `penelitian`, `bio`, `status`, `created_at`) VALUES
(2, '0002038702', 'Siti Aminah', 'Dr.', 'M.Si.', 3, 'Lektor', 'S3 Biologi', 'siti@unimof.ac.id', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Aktif', '2026-09-25 01:52:08'),
(3, '0003059003', 'Andi Pratama', '', 'M.Pd.', 5, 'Asisten Ahli', 'S2 Pendidikan Bahasa Inggris', 'andi@unimof.ac.id', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Aktif', '2026-09-25 01:52:08');

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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `kategori` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Umum',
  `pertanyaan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `jawaban` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int(11) DEFAULT '0',
  `status` enum('Aktif','Nonaktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faq`
--

INSERT INTO `faq` (`id`, `kategori`, `pertanyaan`, `jawaban`, `urutan`, `status`, `created_at`) VALUES
(1, 'PMB', 'Kapan batas waktu pendaftaran Gelombang 1?', 'Pendaftaran Gelombang 1 dibuka 1 Oktober 2025 hingga 31 Januari 2026.', 1, 'Aktif', '2026-09-27 00:40:06'),
(2, 'PMB', 'Apakah ada tes masuk?', 'Ya, Tes Potensi Akademik (TPA): Verbal, Numerik, Logika. Jalur prestasi bisa diganti wawancara.', 2, 'Aktif', '2026-09-27 00:40:06'),
(3, 'Akademik', 'Berapa lama masa studi S1?', 'Masa studi normal S1 adalah 8 semester (4 tahun). Maksimal 14 semester.', 3, 'Aktif', '2026-09-27 00:40:06'),
(4, 'Beasiswa', 'Apa saja jenis beasiswa yang tersedia?', 'Beasiswa Prestasi Akademik, KIP Kuliah, Beasiswa Muhammadiyah, dan Talent Scouting.', 4, 'Aktif', '2026-09-27 00:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `fasilitas`
--

CREATE TABLE `fasilitas` (
  `id` int(11) NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` enum('Laboratorium','Ruang Kelas','Perpustakaan','Fasilitas Umum','Lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'Lainnya',
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '?',
  `gambar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kapasitas` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gedung` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lantai` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jam_operasional` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fasilitas`
--

INSERT INTO `fasilitas` (`id`, `nama`, `kategori`, `deskripsi`, `icon`, `gambar`, `kapasitas`, `status`, `created_at`, `lokasi`, `gedung`, `lantai`, `jam_operasional`, `kontak`, `tags`) VALUES
(1, 'Laboratorium Microteaching', 'Laboratorium', 'Ruang praktik mengajar dengan rekaman video 4K dan one-way mirror.', '🎬', NULL, '30', 'Aktif', '2026-09-25 15:13:56', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Ruang Kelas Smart TV', 'Ruang Kelas', 'Dilengkapi Smart TV 65 inch dan koneksi internet fiber optic.', '🏫', NULL, '40', 'Aktif', '2026-09-25 15:13:56', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `galeri`
--

CREATE TABLE `galeri` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `gambar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Umum',
  `tanggal` date DEFAULT NULL,
  `status` enum('Published','Draft') COLLATE utf8mb4_unicode_ci DEFAULT 'Published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `eissn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `focus_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Indonesia',
  `email_kontak` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jurnal`
--

INSERT INTO `jurnal` (`id`, `nama`, `penerbit`, `issn`, `akreditasi`, `url`, `deskripsi`, `status`, `created_at`, `eissn`, `focus_area`, `frequency`, `language`, `email_kontak`) VALUES
(2, 'Maumere Science Review', 'FKIP UNIMOF', '8765-4321', 'Sinta 4', 'https://msr.unimof.ac.id', 'Jurnal sains dan teknologi terapan.', 'Aktif', '2026-09-25 15:13:56', NULL, NULL, NULL, 'Indonesia', NULL);

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
  `status` enum('Baru','Dibaca','Dibalas','Arsip','Prioritas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baru',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `catatan_admin` text COLLATE utf8mb4_unicode_ci
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
(1, 'nama_fakultas', 'Fakultas Keguruan dan Ilmu Pendidikan', '2026-10-01 03:20:09'),
(2, 'nama_universitas', 'Universitas Muhammadiyah Maumere', '2026-10-01 03:20:09'),
(3, 'singkatan', 'FKIP UNIMOF', '2026-10-01 03:20:10'),
(4, 'alamat', 'Jl. Bhayangkara No.1, Maumere, Sikka, NTT', '2026-10-01 03:20:10'),
(5, 'telepon', '(0382) 21234', '2026-10-01 03:20:10'),
(6, 'email', 'fkip@unimof.ac.id', '2026-10-01 03:20:10'),
(7, 'website', 'www.fkip-unimof.ac.id', '2026-10-01 03:20:10'),
(8, 'whatsapp_number', '6281234567890', '2026-09-27 00:40:06'),
(9, 'instagram_url', 'https://instagram.com/fkipunimof', '2026-09-27 00:40:06'),
(10, 'facebook_url', 'https://facebook.com/fkipunimof', '2026-09-27 00:40:06'),
(11, 'youtube_url', 'https://youtube.com/@fkipunimof', '2026-09-27 00:40:06'),
(12, 'pmb_gelombang', 'Gelombang 1', '2026-09-27 00:40:06'),
(13, 'pmb_deadline', '2026-01-31', '2026-09-27 00:40:06'),
(14, 'biaya_pendaftaran', '300000', '2026-09-27 00:40:06'),
(15, 'rekening_bank', 'Bank NTT - 1234567890 a.n. FKIP UNIMOF', '2026-09-27 00:40:06'),
(16, 'live_chat_enabled', '1', '2026-09-27 08:12:39'),
(17, 'live_chat_greeting', 'Halo 👋 Ada yang bisa kami bantu?', '2026-09-27 08:12:39'),
(18, 'live_chat_position', 'right', '2026-09-27 08:12:39'),
(19, 'live_chat_color', '#25D366', '2026-09-27 08:12:39'),
(20, 'maintenance_mode', '0', '2026-10-01 03:57:15'),
(21, 'maintenance_message', 'Website sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.', '2026-10-01 03:57:15'),
(22, 'enable_registration', '1', '2026-10-01 03:57:15'),
(23, 'timezone', 'Asia/Makassar', '2026-10-01 03:57:15'),
(24, 'date_format', 'd M Y', '2026-10-01 03:57:15'),
(32, 'deskripsi', 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere — Mencetak pendidik profesional berkarakter Islami untuk Indonesia Timur.', '2026-10-01 03:20:10'),
(33, 'hero_video', 'assets/video/hero.mp4', '2026-10-01 04:52:39'),
(34, 'hero_video_poster', '', '2026-10-01 02:02:11'),
(35, 'hero_video_active', '1', '2026-10-01 02:02:11');

-- --------------------------------------------------------

--
-- Table structure for table `pengunjung`
--

CREATE TABLE `pengunjung` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `halaman` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visited_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `nidn_kaprodi` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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

INSERT INTO `program_studi` (`id`, `kode`, `nama`, `nama_en`, `singkatan`, `jenjang`, `akreditasi`, `ketua_prodi`, `nidn_kaprodi`, `deskripsi`, `visi`, `misi`, `kurikulum`, `prospek_kerja`, `logo`, `banner`, `jumlah_dosen`, `jumlah_mahasiswa`, `status`, `urutan`, `created_at`, `updated_at`) VALUES
(1, 'PMAT', 'Pendidikan Matematika', '', 'PMAT', 'S1', 'Terakreditasi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, 5, 'Aktif', 1, '2026-09-25 01:33:39', '2026-10-01 03:20:10'),
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
(1, 1250, 25, 8, 450, 3200, '2025/2026', '2026-10-01 12:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `testimoni`
--

CREATE TABLE `testimoni` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint(1) DEFAULT '5',
  `status` enum('Published','Draft') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `testimoni`
--

INSERT INTO `testimoni` (`id`, `nama`, `jabatan`, `foto`, `pesan`, `rating`, `status`, `created_at`) VALUES
(1, 'Ahmad Fauzi, S.Pd.', 'Alumni 2020 - Guru SMA Negeri 1 Maumere', NULL, 'FKIP UNIMOF memberikan fondasi kuat untuk karir saya sebagai pendidik. Dosen-dosennya kompeten dan peduli.', 5, 'Published', '2026-09-27 00:40:06'),
(4, 'Antonius Yulianus', 'Kepala LPM', 'uploads/testimoni/testi_1790855554_b0c21311.png', 'FKIP merupakan rumah kami yang sangat berharga !', 5, 'Published', '2026-10-01 11:52:34');

-- --------------------------------------------------------

--
-- Table structure for table `video`
--

CREATE TABLE `video` (
  `id` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `program_studi_id` int(11) DEFAULT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'YouTube/Vimeo/embed URL',
  `video_type` enum('youtube','vimeo','local','other') COLLATE utf8mb4_unicode_ci DEFAULT 'youtube',
  `duration` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'contoh: 10:45',
  `duration_seconds` int(11) DEFAULT NULL,
  `views` int(11) DEFAULT '0',
  `is_podcast` tinyint(1) DEFAULT '0' COMMENT '1 = podcast, 0 = video',
  `is_featured` tinyint(1) DEFAULT '0',
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Draft','Published','Archived') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `video`
--

INSERT INTO `video` (`id`, `kategori_id`, `program_studi_id`, `judul`, `slug`, `deskripsi`, `thumbnail`, `video_url`, `video_type`, `duration`, `duration_seconds`, `views`, `is_podcast`, `is_featured`, `tags`, `status`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Kuliah Umum: Masa Depan Pendidikan Indonesia', 'kuliah-umum-masa-depan-pendidikan', 'Prof. Dr. Ahmad Fauzi membahas masa depan pendidikan Indonesia.', NULL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', '45:30', 2730, 0, 0, 1, NULL, 'Published', '2026-09-27 08:12:39', '2026-09-27 08:12:39', '2026-09-27 08:12:39'),
(2, 2, NULL, 'Tutorial: Membuat Presentasi yang Menarik', 'tutorial-presentasi-menarik', 'Tips membuat slide presentasi yang engaging untuk mahasiswa.', NULL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', '12:15', 735, 0, 0, 1, NULL, 'Published', '2026-09-27 08:12:39', '2026-09-27 08:12:39', '2026-09-27 08:12:39'),
(3, 3, NULL, 'Podcast #1: Menjadi Guru Hebat di Era Digital', 'podcast-01-guru-hebat', 'Diskusi santai tentang tantangan guru di era digital.', NULL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', '35:20', 2120, 0, 1, 0, NULL, 'Published', '2026-09-27 08:12:39', '2026-09-27 08:12:39', '2026-09-27 08:12:39'),
(4, 4, NULL, 'Pengenalan Kampus FKIP UNIMOF 2026', 'pengenalan-kampus-2026', 'Video tour kampus untuk calon mahasiswa baru.', NULL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', '08:45', 525, 2, 0, 1, NULL, 'Published', '2026-09-27 08:12:39', '2026-09-27 08:12:39', '2026-10-01 08:20:27');

-- --------------------------------------------------------

--
-- Table structure for table `video_kategori`
--

CREATE TABLE `video_kategori` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '?',
  `urutan` int(11) DEFAULT '0',
  `status` enum('Aktif','Non-Aktif') COLLATE utf8mb4_unicode_ci DEFAULT 'Aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `video_kategori`
--

INSERT INTO `video_kategori` (`id`, `nama`, `slug`, `deskripsi`, `icon`, `urutan`, `status`, `created_at`) VALUES
(1, 'Kuliah Umum', 'kuliah-umum', 'Rekaman kuliah umum dan guest lecture', '🎓', 1, 'Aktif', '2026-09-27 08:12:38'),
(2, 'Tutorial', 'tutorial', 'Video tutorial dan how-to untuk mahasiswa', '📚', 2, 'Aktif', '2026-09-27 08:12:38'),
(3, 'Podcast', 'podcast', 'Podcast audio dari dosen dan mahasiswa', '🎙️', 3, 'Aktif', '2026-09-27 08:12:38'),
(4, 'Kegiatan Kampus', 'kegiatan-kampus', 'Dokumentasi kegiatan dan event kampus', '🎉', 4, 'Aktif', '2026-09-27 08:12:38'),
(5, 'Promosi', 'promosi', 'Video promosi program studi dan PMB', '📢', 5, 'Aktif', '2026-09-27 08:12:38');

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
-- Indexes for table `admin_login_logs`
--
ALTER TABLE `admin_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_created` (`created_at`);

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
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_views` (`views`);

--
-- Indexes for table `blog_artikel`
--
ALTER TABLE `blog_artikel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_dosen` (`dosen_id`),
  ADD KEY `idx_prodi` (`program_studi_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_published` (`published_at`),
  ADD KEY `idx_kategori` (`kategori`),
  ADD KEY `idx_views_likes` (`views`,`likes`);

--
-- Indexes for table `blog_komentar`
--
ALTER TABLE `blog_komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_artikel` (`artikel_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_parent` (`parent_id`);

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
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fasilitas`
--
ALTER TABLE `fasilitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `galeri`
--
ALTER TABLE `galeri`
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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_key` (`nama_key`);

--
-- Indexes for table `pengunjung`
--
ALTER TABLE `pengunjung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visited_at` (`visited_at`),
  ADD KEY `idx_halaman` (`halaman`),
  ADD KEY `idx_ip_date` (`ip_address`,`visited_at`);

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
  ADD KEY `program_studi_id` (`program_studi_id`),
  ADD KEY `fk_riset_ketua` (`ketua_id`);

--
-- Indexes for table `statistik`
--
ALTER TABLE `statistik`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `testimoni`
--
ALTER TABLE `testimoni`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `video`
--
ALTER TABLE `video`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_kategori` (`kategori_id`),
  ADD KEY `idx_prodi` (`program_studi_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_published` (`published_at`);

--
-- Indexes for table `video_kategori`
--
ALTER TABLE `video_kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_login_logs`
--
ALTER TABLE `admin_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

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
-- AUTO_INCREMENT for table `blog_artikel`
--
ALTER TABLE `blog_artikel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blog_komentar`
--
ALTER TABLE `blog_komentar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `fasilitas`
--
ALTER TABLE `fasilitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `galeri`
--
ALTER TABLE `galeri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `pengunjung`
--
ALTER TABLE `pengunjung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `testimoni`
--
ALTER TABLE `testimoni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `video`
--
ALTER TABLE `video`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `video_kategori`
--
ALTER TABLE `video_kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumni`
--
ALTER TABLE `alumni`
  ADD CONSTRAINT `alumni_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_artikel`
--
ALTER TABLE `blog_artikel`
  ADD CONSTRAINT `fk_blog_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blog_prodi` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blog_komentar`
--
ALTER TABLE `blog_komentar`
  ADD CONSTRAINT `blog_komentar_ibfk_1` FOREIGN KEY (`artikel_id`) REFERENCES `blog_artikel` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `fk_riset_ketua` FOREIGN KEY (`ketua_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `riset_ibfk_1` FOREIGN KEY (`program_studi_id`) REFERENCES `program_studi` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `video`
--
ALTER TABLE `video`
  ADD CONSTRAINT `video_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `video_kategori` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
