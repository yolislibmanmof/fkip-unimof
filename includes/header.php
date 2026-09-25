<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Beranda';

// Logika pintar untuk highlight menu dropdown
$is_profil = in_array($current_page, ['about.php', 'dosen.php', 'prestasi.php']);
$is_akademik = in_array($current_page, ['program.php', 'riset.php']);
$is_mahasiswa = in_array($current_page, ['pmb.php', 'alumni.php']);
$is_info = in_array($current_page, ['berita.php', 'agenda.php', 'download.php', 'kontak.php']);
?>
<!DOCTYPE html>
<html lang="id" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($page_description ?? 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere') ?>">
    <title><?= sanitize($page_title) ?> | FKIP UNIMOF</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <meta name="theme-color" content="#0a6847">
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="loader">
            <div class="loader-logo">FKIP</div>
            <div class="loader-bar"></div>
        </div>
    </div>

    <!-- ===== PREMIUM NAVBAR ===== -->
    <header class="header-premium" id="header">
        <nav class="navbar-premium">
            <div class="container navbar-container">
                <!-- Logo -->
                <a href="<?= base_url() ?>" class="logo-premium">
                    <div class="logo-icon-premium"><span>F</span></div>
                    <div class="logo-text-premium">
                        <span class="logo-title-premium">FKIP UNIMOF</span>
                        <span class="logo-subtitle-premium">Mencerdaskan Bangsa</span>
                    </div>
                </a>

                <!-- Menu Utama (Dikelompokkan agar rapi & tidak penuh) -->
                <ul class="nav-menu-premium" id="navMenu">
                    <li>
                        <a href="<?= base_url() ?>" class="nav-link-premium <?= $current_page === 'index.php' ? 'active' : '' ?>">
                            Beranda
                        </a>
                    </li>
                    
                    <!-- Dropdown: Profil -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_profil ? 'active' : '' ?>">
                            Profil <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('about.php') ?>">🏛️ Tentang Kami</a></li>
                            <li><a href="<?= base_url('dosen.php') ?>">👨‍🏫 Dosen & Pengajar</a></li>
                            <li><a href="<?= base_url('prestasi.php') ?>">🏆 Prestasi Mahasiswa</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Akademik -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_akademik ? 'active' : '' ?>">
                            Akademik <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('program.php') ?>">🎓 Program Studi</a></li>
                            <li><a href="<?= base_url('riset.php') ?>">🔬 Pusat Riset</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Kemahasiswaan (BARU) -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_mahasiswa ? 'active' : '' ?>">
                            Kemahasiswaan <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('pmb.php') ?>">📝 Penerimaan Mahasiswa Baru</a></li>
                            <li><a href="<?= base_url('alumni.php') ?>">🎓 Jejaring Alumni</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Informasi (Dilengkapi) -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_info ? 'active' : '' ?>">
                            Informasi <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('berita.php') ?>">📰 Berita Terkini</a></li>
                            <li><a href="<?= base_url('agenda.php') ?>">📅 Agenda & Kalender</a></li>
                            <li><a href="<?= base_url('download.php') ?>">📥 Download Center</a></li>
                            <li><a href="<?= base_url('kontak.php') ?>">✉️ Hubungi Kami</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Aksi Kanan -->
                <div class="nav-actions-premium">
                    <button class="theme-toggle-premium" id="themeToggle" aria-label="Toggle dark mode">
                        <span class="theme-icon-premium">🌙</span>
                    </button>
                    <a href="<?= base_url('admin/login.php') ?>" class="btn-portal-premium">
                        <span>🔐</span> Portal
                    </a>
                    <button class="nav-toggle-premium" id="navToggle" aria-label="Toggle menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <main id="main">