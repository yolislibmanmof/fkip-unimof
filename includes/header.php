<?php
// includes/header.php - Public Website Header (EXTREME MULTIMATE VERSION)
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Beranda';
$page_description = $page_description ?? 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere - Mencetak pendidik profesional berkarakter Islami untuk Indonesia Timur';

// Logika pintar untuk highlight menu dropdown (LENGKAP dengan modul baru)
$is_profil = in_array($current_page, ['about.php', 'dosen.php', 'fasilitas.php', 'prestasi.php', 'akreditasi.php', 'kerjasama.php']);
$is_akademik = in_array($current_page, ['program.php', 'riset.php', 'jurnal.php']);
$is_mahasiswa = in_array($current_page, ['pmb.php', 'beasiswa.php', 'alumni.php']);
$is_info = in_array($current_page, ['berita.php', 'agenda.php', 'download.php', 'kontak.php']);
?>
<!DOCTYPE html>
<html lang="id" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($page_description) ?>">
    <meta name="keywords" content="FKIP, UNIMOF, Universitas Muhammadiyah Maumere, Pendidikan, Kampus, Maumere">
    <meta name="author" content="FKIP UNIMOF">
    <meta name="theme-color" content="#0a6847">
    
    <title><?= sanitize($page_title) ?> | FKIP UNIMOF</title>
    
    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= time() ?>">
    
    <!-- Critical Inline CSS for Navbar (Ensures perfect look immediately) -->
    <style>
        .header-premium {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .header-premium.scrolled {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        [data-theme="dark"] .header-premium {
            background: rgba(15, 23, 42, 0.9);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        [data-theme="dark"] .header-premium.scrolled {
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .navbar-container {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 0; transition: padding 0.4s ease;
        }
        .header-premium.scrolled .navbar-container { padding: 0.75rem 0; }
        
        .logo-premium {
            display: flex; align-items: center; gap: 0.75rem;
            text-decoration: none; color: inherit;
        }
        .logo-icon-premium {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, #0a6847, #16a34a);
            color: white; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900;
            transition: transform 0.3s ease;
        }
        .logo-premium:hover .logo-icon-premium { transform: scale(1.05) rotate(-5deg); }
        .logo-text-premium { display: flex; flex-direction: column; }
        .logo-title-premium {
            font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 800;
            color: #0a6847; line-height: 1.2;
        }
        [data-theme="dark"] .logo-title-premium { color: #10b981; }
        .logo-subtitle-premium {
            font-size: 0.75rem; font-weight: 600; color: #64748b;
            letter-spacing: 0.05em; text-transform: uppercase;
        }
        
        .nav-menu-premium {
            display: flex; list-style: none; gap: 0.5rem; margin: 0; padding: 0;
        }
        .nav-link-premium {
            display: flex; align-items: center; gap: 0.25rem;
            padding: 0.5rem 1rem; color: #334155; text-decoration: none;
            font-weight: 600; font-size: 0.9rem; border-radius: 8px;
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .nav-link-premium { color: #cbd5e1; }
        .nav-link-premium:hover, .nav-link-premium.active {
            color: #0a6847; background: rgba(10, 104, 71, 0.08);
        }
        [data-theme="dark"] .nav-link-premium:hover, [data-theme="dark"] .nav-link-premium.active {
            color: #10b981; background: rgba(16, 185, 129, 0.1);
        }
        .arrow-premium { font-size: 0.7rem; transition: transform 0.3s ease; }
        .nav-dropdown-premium:hover .arrow-premium { transform: rotate(180deg); }
        
        .dropdown-menu-premium {
            position: absolute; top: 100%; left: 0;
            background: white; border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-width: 240px; padding: 0.5rem; list-style: none;
            opacity: 0; visibility: hidden; transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001;
        }
        [data-theme="dark"] .dropdown-menu-premium {
            background: #1e293b; border-color: rgba(255,255,255,0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .nav-dropdown-premium { position: relative; }
        .nav-dropdown-premium:hover .dropdown-menu-premium {
            opacity: 1; visibility: visible; transform: translateY(0);
        }
        .dropdown-menu-premium li a {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1rem; color: #475569; text-decoration: none;
            font-size: 0.85rem; font-weight: 500; border-radius: 8px;
            transition: all 0.2s ease;
        }
        [data-theme="dark"] .dropdown-menu-premium li a { color: #cbd5e1; }
        .dropdown-menu-premium li a:hover {
            background: rgba(10, 104, 71, 0.08); color: #0a6847; transform: translateX(4px);
        }
        [data-theme="dark"] .dropdown-menu-premium li a:hover {
            background: rgba(16, 185, 129, 0.1); color: #10b981;
        }
        
        .nav-actions-premium { display: flex; align-items: center; gap: 0.75rem; }
        .theme-toggle-premium {
            width: 40px; height: 40px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.1);
            background: transparent; cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 1.1rem; transition: all 0.3s ease;
        }
        [data-theme="dark"] .theme-toggle-premium { border-color: rgba(255,255,255,0.1); color: #fbbf24; }
        .theme-toggle-premium:hover { background: rgba(0,0,0,0.05); transform: rotate(15deg); }
        
        .btn-portal-premium {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.25rem; background: #0a6847; color: white;
            border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.85rem;
            transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(10, 104, 71, 0.2);
        }
        .btn-portal-premium:hover { background: #084d35; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(10, 104, 71, 0.3); }
        
        .nav-toggle-premium {
            display: none; flex-direction: column; gap: 5px; background: none;
            border: none; cursor: pointer; padding: 0.5rem; z-index: 1002;
        }
        .nav-toggle-premium span {
            display: block; width: 24px; height: 2px; background: #0f172a;
            border-radius: 2px; transition: all 0.3s ease;
        }
        [data-theme="dark"] .nav-toggle-premium span { background: #f1f5f9; }
        .nav-toggle-premium.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .nav-toggle-premium.active span:nth-child(2) { opacity: 0; }
        .nav-toggle-premium.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

        /* Preloader */
        .preloader {
            position: fixed; inset: 0; background: #ffffff; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        [data-theme="dark"] .preloader { background: #0f172a; }
        .preloader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader { text-align: center; }
        .loader-logo {
            font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: 900;
            color: #0a6847; margin-bottom: 1rem; animation: pulse 1.5s infinite;
        }
        [data-theme="dark"] .loader-logo { color: #10b981; }
        .loader-bar {
            width: 120px; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden;
        }
        [data-theme="dark"] .loader-bar { background: #1e293b; }
        .loader-bar::after {
            content: ''; display: block; width: 40%; height: 100%; background: #0a6847;
            border-radius: 4px; animation: loading 1s ease-in-out infinite;
        }
        [data-theme="dark"] .loader-bar::after { background: #10b981; }
        @keyframes loading { 0% { transform: translateX(-100%); } 100% { transform: translateX(350%); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .nav-menu-premium {
                position: fixed; top: 0; right: -100%; width: 85%; max-width: 320px; height: 100vh;
                background: white; flex-direction: column; padding: 5rem 1.5rem 1.5rem;
                box-shadow: -10px 0 30px rgba(0,0,0,0.1); transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y: auto; z-index: 999;
            }
            [data-theme="dark"] .nav-menu-premium { background: #0f172a; box-shadow: -10px 0 30px rgba(0,0,0,0.3); }
            .nav-menu-premium.open { right: 0; }
            .nav-dropdown-premium { width: 100%; }
            .dropdown-menu-premium {
                position: static; opacity: 1; visibility: visible; transform: none;
                box-shadow: none; border: none; background: rgba(0,0,0,0.03);
                display: none; padding-left: 1rem; margin-top: 0.5rem;
            }
            [data-theme="dark"] .dropdown-menu-premium { background: rgba(255,255,255,0.03); }
            .nav-dropdown-premium.open .dropdown-menu-premium { display: block; }
            .nav-toggle-premium { display: flex; }
        }
    </style>
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

                <!-- Menu Utama -->
                <ul class="nav-menu-premium" id="navMenu">
                    <li>
                        <a href="<?= base_url() ?>" class="nav-link-premium <?= $current_page === 'index.php' ? 'active' : '' ?>">
                            Beranda
                        </a>
                    </li>
                    
                    <!-- Dropdown: Profil -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_profil ? 'active' : '' ?>" onclick="toggleMobileDropdown(event, this)">
                            Profil <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('about.php') ?>">🏛️ Tentang Kami</a></li>
                            <li><a href="<?= base_url('dosen.php') ?>">👨‍🏫 Dosen & Pengajar</a></li>
                            <li><a href="<?= base_url('fasilitas.php') ?>">🏢 Fasilitas & Lab</a></li>
                            <li><a href="<?= base_url('prestasi.php') ?>">🏆 Prestasi Mahasiswa</a></li>
                            <li><a href="<?= base_url('akreditasi.php') ?>">🏅 Akreditasi</a></li>
                            <li><a href="<?= base_url('kerjasama.php') ?>">🤝 Kerjasama & Mitra</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Akademik -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_akademik ? 'active' : '' ?>" onclick="toggleMobileDropdown(event, this)">
                            Akademik <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('program.php') ?>">🎓 Program Studi</a></li>
                            <li><a href="<?= base_url('riset.php') ?>">🔬 Pusat Riset</a></li>
                            <li><a href="<?= base_url('jurnal.php') ?>">📚 Jurnal Ilmiah</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Kemahasiswaan -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_mahasiswa ? 'active' : '' ?>" onclick="toggleMobileDropdown(event, this)">
                            Kemahasiswaan <span class="arrow-premium">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium">
                            <li><a href="<?= base_url('pmb.php') ?>">📝 Penerimaan Mahasiswa Baru</a></li>
                            <li><a href="<?= base_url('beasiswa.php') ?>">💰 Program Beasiswa</a></li>
                            <li><a href="<?= base_url('alumni.php') ?>">🎓 Jejaring Alumni</a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Informasi -->
                    <li class="nav-dropdown-premium">
                        <a href="#" class="nav-link-premium <?= $is_info ? 'active' : '' ?>" onclick="toggleMobileDropdown(event, this)">
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

    <!-- Spacer for fixed header to prevent content jump -->
    <div style="height: 80px;"></div>

    <main id="main">