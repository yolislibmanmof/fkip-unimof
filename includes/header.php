<?php
// includes/header.php - Public Website Header (EXTREME MULTIMATE VERSION)

// =====================================================
// MAINTENANCE MODE ENFORCEMENT (hanya sisi publik)
// Admin login & folder /admin/ selalu bypass
// =====================================================
if (!defined('MAINTENANCE_BYPASS')) {
    $mm_admin_login = !empty($_SESSION['admin_id']) || !empty($_SESSION['admin_username']);
    $mm_admin_area  = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false);

    if (!$mm_admin_login && !$mm_admin_area) {
        $mm_active  = false;
        $mm_message = 'Website sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.';
        try {
            $mm_active  = (get_setting('maintenance_mode', '0') === '1');
            $mm_message = get_setting('maintenance_message', $mm_message) ?: $mm_message;
        } catch (Exception $e) {}

        if ($mm_active) {
            http_response_code(503);
            header('Retry-After: 3600');
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex">
            <title>Maintenance — FKIP UNIMOF</title>
            <style>
                *{margin:0;padding:0;box-sizing:border-box}
                body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#064e34,#0a6847 50%,#065f46);color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;text-align:center}
                .box{max-width:560px}
                .icon{font-size:4rem;margin-bottom:1rem;animation:float 3s ease-in-out infinite}
                @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
                h1{font-size:2rem;margin-bottom:.75rem}
                p{opacity:.9;line-height:1.7;margin-bottom:1.5rem;white-space:pre-line}
                .badge{display:inline-block;padding:.4rem 1rem;border:1px solid rgba(255,255,255,.35);border-radius:999px;font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
            </style>
            </head>
            <body>
                <div class="box">
                    <div class="icon">🛠️</div>
                    <h1>Sedang Dalam Pemeliharaan</h1>
                    <p><?= htmlspecialchars($mm_message, ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="badge">FKIP UNIMOF • <?= date('Y') ?></span>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

// =====================================================
// CONTEXT & LOGIC
// =====================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Beranda';
$page_description = $page_description ?? 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere - Mencetak pendidik profesional berkarakter Islami untuk Indonesia Timur';
$page_keywords = $page_keywords ?? 'FKIP, UNIMOF, Universitas Muhammadiyah Maumere, Pendidikan, Kampus, Maumere, NTT';
$page_image = $page_image ?? (get_active_logo_url() ?? asset('images/og-default.jpg'));

// ===== AUTO-DETECT LOGO & FAVICON DARI UPLOAD ADMIN =====
// Sync dengan admin/pengaturan.php yang upload ke assets/images/
function get_active_logo_url() {
    $extensions = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
    foreach ($extensions as $ext) {
        $path = __DIR__ . '/../assets/images/logo.' . $ext;
        if (file_exists($path)) {
            return base_url('assets/images/logo.' . $ext);
        }
    }
    return null; // Tidak ada logo → pakai monogram
}

function get_active_favicon_url() {
    $extensions = ['ico', 'png', 'svg'];
    foreach ($extensions as $ext) {
        $path = __DIR__ . '/../assets/images/favicon.' . $ext;
        if (file_exists($path)) {
            return base_url('assets/images/favicon.' . $ext);
        }
    }
    return base_url('favicon.svg'); // Fallback default
}

$active_logo_url = get_active_logo_url();
$active_favicon_url = get_active_favicon_url();

// Logika pintar untuk highlight menu dropdown (LENGKAP dengan modul baru)
$is_profil = in_array($current_page, ['about.php', 'dosen.php', 'fasilitas.php', 'prestasi.php', 'akreditasi.php', 'kerjasama.php']);
$is_akademik = in_array($current_page, ['program.php', 'riset.php', 'jurnal.php']);
$is_mahasiswa = in_array($current_page, ['pmb.php', 'beasiswa.php', 'alumni.php']);
$is_info = in_array($current_page, ['berita.php', 'agenda.php', 'download.php', 'kontak.php', 'faq.php', 'galeri.php', 'blog.php', 'blog-detail.php', 'video.php']);

// Page type detection (untuk breadcrumb & schema.org)
$page_type = 'WebPage';
if (in_array($current_page, ['berita-detail.php', 'berita.php'])) $page_type = 'Article';
if ($current_page === 'program-detail.php') $page_type = 'Course';
if ($current_page === 'tentang.php') $page_type = 'AboutPage';
if ($current_page === 'kontak.php') $page_type = 'ContactPage';

// Smart breadcrumb (auto-detect)
$breadcrumb_trail = [
    ['name' => 'Beranda', 'url' => base_url()],
];
$crumb_map = [
    'about.php' => [['name' => 'Tentang Kami', 'url' => base_url('about.php')]],
    'program.php' => [['name' => 'Program Studi', 'url' => base_url('program.php')]],
    'program-detail.php' => [
        ['name' => 'Program Studi', 'url' => base_url('program.php')],
        ['name' => $page_title ?? 'Detail', 'url' => ''],
    ],
    'berita.php' => [['name' => 'Berita', 'url' => base_url('berita.php')]],
    'berita-detail.php' => [
        ['name' => 'Berita', 'url' => base_url('berita.php')],
        ['name' => $page_title ?? 'Detail', 'url' => ''],
    ],
    'blog.php' => [
        ['name' => 'Ide & Wawasan', 'url' => base_url('blog.php')],
    ],
    'blog-detail.php' => [
        ['name' => 'Ide & Wawasan', 'url' => base_url('blog.php')],
        ['name' => $page_title ?? 'Detail', 'url' => ''],
    ],
    'video.php' => [
        ['name' => 'Video & Podcast', 'url' => base_url('video.php')],
    ],
    'dosen.php' => [
        ['name' => 'Profil', 'url' => base_url('about.php')],
        ['name' => 'Dosen', 'url' => base_url('dosen.php')],
    ],
    'fasilitas.php' => [
        ['name' => 'Profil', 'url' => base_url('about.php')],
        ['name' => 'Fasilitas', 'url' => base_url('fasilitas.php')],
    ],
    'prestasi.php' => [
        ['name' => 'Profil', 'url' => base_url('about.php')],
        ['name' => 'Prestasi', 'url' => base_url('prestasi.php')],
    ],
    'akreditasi.php' => [
        ['name' => 'Profil', 'url' => base_url('about.php')],
        ['name' => 'Akreditasi', 'url' => base_url('akreditasi.php')],
    ],
    'kerjasama.php' => [
        ['name' => 'Profil', 'url' => base_url('about.php')],
        ['name' => 'Kerjasama', 'url' => base_url('kerjasama.php')],
    ],
    'riset.php' => [
        ['name' => 'Akademik', 'url' => base_url('program.php')],
        ['name' => 'Pusat Riset', 'url' => base_url('riset.php')],
    ],
    'jurnal.php' => [
        ['name' => 'Akademik', 'url' => base_url('program.php')],
        ['name' => 'Jurnal', 'url' => base_url('jurnal.php')],
    ],
    'pmb.php' => [['name' => 'PMB', 'url' => base_url('pmb.php')]],
    'beasiswa.php' => [
        ['name' => 'Kemahasiswaan', 'url' => base_url('pmb.php')],
        ['name' => 'Beasiswa', 'url' => base_url('beasiswa.php')],
    ],
    'alumni.php' => [
        ['name' => 'Kemahasiswaan', 'url' => base_url('pmb.php')],
        ['name' => 'Alumni', 'url' => base_url('alumni.php')],
    ],
    'agenda.php' => [['name' => 'Agenda', 'url' => base_url('agenda.php')]],
    'download.php' => [['name' => 'Download', 'url' => base_url('download.php')]],
    'kontak.php' => [['name' => 'Kontak', 'url' => base_url('kontak.php')]],
    'faq.php' => [['name' => 'FAQ', 'url' => base_url('faq.php')]],
    'galeri.php' => [['name' => 'Galeri', 'url' => base_url('galeri.php')]],
];
if (isset($crumb_map[$current_page])) {
    $breadcrumb_trail = array_merge($breadcrumb_trail, $crumb_map[$current_page]);
}

// Smart top bar message (dari DB jika ada, fallback statis)
$topbar_msg = null;
try {
    $tb_cols = $pdo->query("SHOW COLUMNS FROM `pengaturan`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('key', $tb_cols) && in_array('value', $tb_cols)) {
        $stmt = $pdo->prepare("SELECT value FROM pengaturan WHERE `key` = ?");
        $stmt->execute(['topbar_message']);
        $topbar_msg = $stmt->fetchColumn();
    }
} catch (Exception $e) {}
if (empty($topbar_msg)) {
    $topbar_msg = '🔥 Penerimaan Mahasiswa Baru 2026/2027 Gelombang 1 Telah Dibuka! Daftar sekarang sebelum kuota habis.';
}

// Notifikasi pengumuman (dari DB)
$notif_count = 0;
$notif_items = [];
try {
    $pn_cols = $pdo->query("SHOW COLUMNS FROM `pengumuman`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('status', $pn_cols)) {
        $notif_items = $pdo->query("SELECT * FROM pengumuman WHERE status='Aktif' ORDER BY tanggal DESC LIMIT 5")->fetchAll();
        $notif_count = count($notif_items);
    }
} catch (Exception $e) {}

// Canonical URL
$canonical_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'fkip-unimof.test') . $_SERVER['REQUEST_URI'];

// Theme detection
$default_theme = 'light';
?>
<!DOCTYPE html>
<html lang="id" dir="ltr" data-theme="<?= $default_theme ?>" data-page="<?= $current_page ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <!-- SEO -->
    <title><?= sanitize($page_title) ?> | FKIP UNIMOF</title>
    <meta name="description" content="<?= sanitize($page_description) ?>">
    <meta name="keywords" content="<?= sanitize($page_keywords) ?>">
    <meta name="author" content="FKIP UNIMOF">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#0a6847" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <link rel="canonical" href="<?= $canonical_url ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= $page_type === 'Article' ? 'article' : 'website' ?>">
    <meta property="og:title" content="<?= sanitize($page_title) ?> | FKIP UNIMOF">
    <meta property="og:description" content="<?= sanitize($page_description) ?>">
    <meta property="og:url" content="<?= $canonical_url ?>">
    <meta property="og:image" content="<?= $page_image ?>">
    <meta property="og:site_name" content="FKIP UNIMOF">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= sanitize($page_title) ?> | FKIP UNIMOF">
    <meta name="twitter:description" content="<?= sanitize($page_description) ?>">
    <meta name="twitter:image" content="<?= $page_image ?>">

<!-- ✅ Favicon - AUTO-DETECT dari upload admin (assets/images/favicon.*) -->
<link rel="icon" href="<?= $active_favicon_url ?>">
<link rel="shortcut icon" href="<?= $active_favicon_url ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $active_favicon_url ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $active_favicon_url ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $active_favicon_url ?>">
<link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <!-- OPSIONAL: aktifkan setelah file PNG tersedia (untuk iOS/legacy)
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('images/favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('images/apple-touch-icon.png') ?>">
    -->

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://unpkg.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">

    <!-- Resource Hints (prefetch halaman populer) -->
    <link rel="prefetch" href="<?= base_url('program.php') ?>">
    <link rel="prefetch" href="<?= base_url('pmb.php') ?>">
    <link rel="prefetch" href="<?= base_url('berita.php') ?>">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= time() ?>">

    <!-- Schema.org JSON-LD (Organization + BreadcrumbList) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "FKIP UNIMOF",
        "alternateName": "Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere",
        "url": "<?= base_url() ?>",
        "logo": "<?= $active_logo_url ?? asset('images/logo.png') ?>",
        "description": <?= json_encode($page_description) ?>,
        "address": {
            "@type": "PostalAddress",
            "streetAddress": <?= json_encode(get_setting('alamat', 'Jl. Bhayangkara No.1')) ?>,
            "addressLocality": "Maumere",
            "addressRegion": "Nusa Tenggara Timur",
            "addressCountry": "ID"
        },
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": <?= json_encode(get_setting('telepon', '(0382) 21234')) ?>,
            "email": <?= json_encode(get_setting('email', 'fkip@unimof.ac.id')) ?>,
            "contactType": "Admissions"
        },
        "sameAs": [
            "<?= get_setting('facebook_url', get_setting('facebook', '')) ?>",
            "<?= get_setting('instagram_url', get_setting('instagram', '')) ?>",
            "<?= get_setting('youtube_url', get_setting('youtube', '')) ?>"
        ]
    }
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": <?= json_encode(array_map(function($i, $c) {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['url'] ?: null,
            ];
        }, array_keys($breadcrumb_trail), $breadcrumb_trail)) ?>
    }
    </script>

    <!-- Critical Inline CSS (EXTREME MULTIMATE) -->
<style>
/* =====================================================
   CSS VARIABLES (Design Tokens)
   ===================================================== */
:root {
    /* Brand Colors */
    --primary: #0a6847;
    --primary-light: #10b981;
    --primary-dark: #064e34;
    --accent: #f59e0b;

    /* Backgrounds */
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --bg-tertiary: #f1f5f9;

    /* Text */
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;

    /* Borders */
    --border: #e2e8f0;

    /* Shadows */
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);

    /* Radius */
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;

    /* Layout */
    --header-height: 80px;

    /* Fonts */
    --font-display: 'Playfair Display', Georgia, serif;
    --font-body: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* ===== DARK MODE ===== */
[data-theme="dark"] {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #64748b;
    --border: #334155;
}

/* ===== BASE ===== */
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-body);
    color: var(--text-primary);
    background: var(--bg-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* =====================================================
   SKIP TO CONTENT (Accessibility)
   ===================================================== */
.skip-link {
    position: fixed; top: -100px; left: 1rem; z-index: 10000;
    background: var(--primary); color: white;
    padding: 0.75rem 1.25rem; border-radius: 0 0 8px 8px;
    text-decoration: none; font-weight: 700; font-size: 0.9rem;
    box-shadow: var(--shadow-lg);
    transition: top 0.3s ease;
}
.skip-link:focus { top: 0; }

/* =====================================================
   TOP BAR (Announcement Strip)
   ===================================================== */
.topbar-premium {
    background: linear-gradient(90deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
    color: white; padding: 0.5rem 0; font-size: 0.85rem;
    position: relative; z-index: 999; overflow: hidden;
}
.topbar-premium::before {
    content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    animation: topbarShimmer 8s infinite;
}
@keyframes topbarShimmer { to { left: 100%; } }
.topbar-premium.dismissed { display: none; }
.topbar-inner {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
}
.topbar-message {
    flex: 1; display: flex; align-items: center; gap: 0.5rem;
    overflow: hidden; white-space: nowrap;
}
.topbar-message span { animation: topbarSlide 25s linear infinite; display: inline-block; }
@keyframes topbarSlide {
    0% { transform: translateX(100%); }
    100% { transform: translateX(-100%); }
}
.topbar-message:hover span { animation-play-state: paused; }
.topbar-close {
    background: rgba(255,255,255,0.2); border: none; color: white;
    width: 28px; height: 28px; border-radius: 50%; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; transition: all 0.2s;
    flex-shrink: 0;
}
.topbar-close:hover { background: rgba(255,255,255,0.35); transform: rotate(90deg); }
.topbar-cta {
    color: #fef3c7; font-weight: 700; text-decoration: none;
    padding: 0.25rem 0.75rem; border-radius: 999px;
    background: rgba(255,255,255,0.15); transition: all 0.2s;
    flex-shrink: 0;
}
.topbar-cta:hover { background: rgba(255,255,255,0.25); color: white; }

/* =====================================================
   HEADER / NAVBAR (EXTREME)
   ===================================================== */
.header-premium {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.header-premium.scrolled {
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.header-premium.nav-hidden { transform: translateY(-100%); }
[data-theme="dark"] .header-premium {
    background: rgba(15, 23, 42, 0.92);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
[data-theme="dark"] .header-premium.scrolled {
    background: rgba(15, 23, 42, 0.96);
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.navbar-container {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 0; transition: padding 0.4s ease;
}
.header-premium.scrolled .navbar-container { padding: 0.6rem 0; }

/* ===== LOGO (Monogram Fallback) ===== */
.logo-premium {
    display: flex; align-items: center; gap: 0.75rem;
    text-decoration: none; color: inherit;
}
.logo-icon-premium {
    width: 45px; height: 45px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
    position: relative; overflow: hidden;
}
.logo-icon-premium::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.3) 50%, transparent 60%);
    transform: translateX(-100%); transition: transform 0.6s;
}
.logo-premium:hover .logo-icon-premium { transform: scale(1.05) rotate(-5deg); }
.logo-premium:hover .logo-icon-premium::before { transform: translateX(100%); }

/* ===== LOGO IMAGE (dari upload admin - PATCH #5) ===== */
.logo-img-premium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
    padding: 4px;
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    flex-shrink: 0;
    position: relative;
}
.logo-img-premium img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
.logo-premium:hover .logo-img-premium {
    transform: scale(1.05) rotate(-5deg);
}
[data-theme="dark"] .logo-img-premium {
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
@media (max-width: 640px) {
    .logo-img-premium { width: 38px; height: 38px; padding: 3px; }
}

/* ===== LOGO TEXT ===== */
.logo-text-premium { display: flex; flex-direction: column; }
.logo-title-premium {
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;
    color: var(--primary); line-height: 1.2;
}
[data-theme="dark"] .logo-title-premium { color: var(--primary-light); }
.logo-subtitle-premium {
    font-size: 0.7rem; font-weight: 600; color: var(--text-muted);
    letter-spacing: 0.05em; text-transform: uppercase;
}

/* ===== NAV MENU ===== */
.nav-menu-premium {
    display: flex; list-style: none; gap: 0.25rem; margin: 0; padding: 0;
}
.nav-link-premium {
    display: flex; align-items: center; gap: 0.35rem;
    padding: 0.6rem 1rem; color: var(--text-secondary); text-decoration: none;
    font-weight: 600; font-size: 0.9rem; border-radius: var(--radius-md);
    transition: all 0.3s ease; position: relative;
}
.nav-link-premium::after {
    content: ''; position: absolute; bottom: 0; left: 50%;
    width: 0; height: 2px; background: var(--primary);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-50%);
}
.nav-link-premium:hover { color: var(--primary); background: rgba(10, 104, 71, 0.06); }
.nav-link-premium:hover::after { width: 60%; }
.nav-link-premium.active {
    color: var(--primary); background: rgba(10, 104, 71, 0.1);
    font-weight: 700;
}
.nav-link-premium.active::after { width: 70%; background: var(--accent); }
[data-theme="dark"] .nav-link-premium { color: var(--text-secondary); }
[data-theme="dark"] .nav-link-premium:hover,
[data-theme="dark"] .nav-link-premium.active {
    color: var(--primary-light); background: rgba(16, 185, 129, 0.1);
}
.arrow-premium {
    font-size: 0.65rem; transition: transform 0.3s ease;
    margin-left: 0.25rem;
}
.nav-dropdown-premium:hover .arrow-premium { transform: rotate(180deg); }

/* ===== MEGA MENU DROPDOWN ===== */
.dropdown-menu-premium {
    position: absolute; top: 100%; left: 0;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    min-width: 280px; padding: 0.5rem; list-style: none;
    opacity: 0; visibility: hidden; transform: translateY(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001;
    margin-top: 0.5rem;
}
[data-theme="dark"] .dropdown-menu-premium {
    background: #1e293b; border-color: rgba(255,255,255,0.05);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.nav-dropdown-premium { position: relative; }
.nav-dropdown-premium:hover .dropdown-menu-premium {
    opacity: 1; visibility: visible; transform: translateY(0);
}
.dropdown-menu-premium li a {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.7rem 1rem; color: var(--text-secondary); text-decoration: none;
    font-size: 0.88rem; font-weight: 500; border-radius: var(--radius-md);
    transition: all 0.2s ease; position: relative;
}
.dropdown-menu-premium li a::before {
    content: ''; position: absolute; left: 0; top: 50%;
    width: 3px; height: 0; background: var(--primary);
    transform: translateY(-50%); transition: height 0.3s;
    border-radius: 0 2px 2px 0;
}
.dropdown-menu-premium li a:hover {
    background: rgba(10, 104, 71, 0.08); color: var(--primary);
    transform: translateX(4px); padding-left: 1.25rem;
}
.dropdown-menu-premium li a:hover::before { height: 70%; }
[data-theme="dark"] .dropdown-menu-premium li a { color: var(--text-secondary); }
[data-theme="dark"] .dropdown-menu-premium li a:hover {
    background: rgba(16, 185, 129, 0.1); color: var(--primary-light);
}
.dropdown-icon {
    width: 34px; height: 34px; border-radius: 10px;
    background: rgba(10, 104, 71, 0.1);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0; transition: all 0.3s;
}
.dropdown-menu-premium li a:hover .dropdown-icon {
    background: var(--primary); color: white; transform: scale(1.05);
}
.dropdown-label { display: flex; flex-direction: column; }
.dropdown-label strong { font-weight: 700; color: var(--text-primary); font-size: 0.9rem; }
[data-theme="dark"] .dropdown-label strong { color: var(--text-primary); }
.dropdown-label small { color: var(--text-muted); font-size: 0.72rem; margin-top: 0.15rem; }
.dropdown-badge {
    margin-left: auto; padding: 0.15rem 0.5rem; border-radius: 999px;
    font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; flex-shrink: 0;
}
.badge-hot { background: #fee2e2; color: #dc2626; }
.badge-new { background: #dcfce7; color: #16a34a; }

/* ===== NAV ACTIONS ===== */
.nav-actions-premium {
    display: flex; align-items: center; gap: 0.5rem;
}
.icon-btn-premium {
    width: 40px; height: 40px; border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.08);
    background: transparent; cursor: pointer; display: flex; align-items: center;
    justify-content: center; font-size: 1.1rem; transition: all 0.3s ease;
    position: relative; color: var(--text-secondary);
}
[data-theme="dark"] .icon-btn-premium {
    border-color: rgba(255,255,255,0.1); color: var(--text-secondary);
}
.icon-btn-premium:hover {
    background: rgba(0,0,0,0.05); transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}
[data-theme="dark"] .icon-btn-premium:hover { background: rgba(255,255,255,0.05); }
.icon-btn-premium[data-tooltip]:hover::after {
    content: attr(data-tooltip); position: absolute; top: 120%; left: 50%;
    transform: translateX(-50%); padding: 0.35rem 0.65rem;
    background: var(--text-primary); color: var(--bg-primary);
    border-radius: 6px; font-size: 0.72rem; font-weight: 600;
    white-space: nowrap; pointer-events: none; z-index: 1002;
}
.notif-badge {
    position: absolute; top: -4px; right: -4px;
    background: #ef4444; color: white; font-size: 0.65rem;
    font-weight: 800; min-width: 18px; height: 18px;
    border-radius: 999px; display: flex; align-items: center;
    justify-content: center; padding: 0 0.35rem;
    border: 2px solid var(--bg-primary);
    animation: notifPulse 2s infinite;
}
@keyframes notifPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
    50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
}

.btn-portal-premium {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-radius: 10px; text-decoration: none;
    font-weight: 700; font-size: 0.85rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(10, 104, 71, 0.25);
    position: relative; overflow: hidden;
}
.btn-portal-premium::before {
    content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.6s;
}
.btn-portal-premium:hover::before { left: 100%; }
.btn-portal-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(10, 104, 71, 0.35);
}

/* ===== MOBILE TOGGLE ===== */
.nav-toggle-premium {
    display: none; flex-direction: column; gap: 5px; background: none;
    border: none; cursor: pointer; padding: 0.5rem; z-index: 1002;
    position: relative;
}
.nav-toggle-premium span {
    display: block; width: 24px; height: 2px;
    background: var(--text-primary); border-radius: 2px;
    transition: all 0.3s ease;
}
.nav-toggle-premium.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
.nav-toggle-premium.active span:nth-child(2) { opacity: 0; }
.nav-toggle-premium.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

/* ===== MOBILE MENU OVERLAY ===== */
.mobile-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px); z-index: 998;
    opacity: 0; visibility: hidden; transition: all 0.3s;
}
.mobile-overlay.show { opacity: 1; visibility: visible; }

/* =====================================================
   BREADCRUMB
   ===================================================== */
.breadcrumb {
    display: flex; flex-wrap: wrap; gap: 0.35rem;
    align-items: center; font-size: 0.85rem;
    color: var(--text-muted); margin-bottom: 1.5rem;
}
.breadcrumb a {
    color: var(--text-secondary); text-decoration: none;
    transition: color 0.2s; padding: 0.15rem 0;
}
.breadcrumb a:hover { color: var(--primary); text-decoration: underline; }
.breadcrumb span.separator { color: var(--text-muted); padding: 0 0.25rem; }
.breadcrumb span.current { color: var(--text-primary); font-weight: 600; }

/* =====================================================
   PRELOADER (EXTREME) - Logo Upload + FU Monogram
   ===================================================== */
.preloader {
    position: fixed; inset: 0; background: var(--bg-primary); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}
.preloader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
.loader { text-align: center; padding: 2rem; }

/* Logo Box (fallback monogram) */
.loader-logo {
    width: 96px; height: 96px; margin: 0 auto 1.75rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 22px;
    display: flex; align-items: center; justify-content: center;
    position: relative; overflow: hidden;
    box-shadow: 0 10px 30px rgba(10, 104, 71, 0.3);
    animation: loaderFloat 2s ease-in-out infinite;
}

/* Shine effect - z-index 1 (di bawah logo) */
.loader-logo::before {
    content: ''; position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
    animation: loaderShine 2s linear infinite;
    z-index: 1; pointer-events: none;
}

/* Inner glow ring */
.loader-logo::after {
    content: ''; position: absolute; inset: 0; border-radius: 22px;
    box-shadow: inset 0 0 20px rgba(255,255,255,0.15);
    z-index: 1; pointer-events: none;
}

/* Huruf "FU" fallback - z-index 2 (di atas shine) */
.loader-letter {
    position: relative; z-index: 2;
    font-family: var(--font-display);
    font-size: 2.5rem; font-weight: 900;
    letter-spacing: -0.03em; line-height: 1;
    color: #ffffff;
    display: flex; align-items: center; justify-content: center;
    width: 100%; height: 100%;
    text-shadow: 0 2px 4px rgba(0,0,0,0.25), 0 0 20px rgba(255,255,255,0.3);
}

/* Logo Image dari upload admin - PATCH #6 */
.loader-logo-img {
    width: 64px;
    height: 64px;
    object-fit: contain;
    position: relative;
    z-index: 2;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
    animation: loaderLogoSpin 2s ease-in-out infinite;
}
[data-theme="dark"] .loader-logo-img {
    background: rgba(255, 255, 255, 0.95);
    padding: 8px;
    border-radius: 16px;
    width: 72px;
    height: 72px;
}
.loader-logo-img[src$=".svg"] { object-fit: contain; }

@keyframes loaderLogoSpin {
    0%, 100% {
        transform: scale(1) rotate(0deg);
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
    }
    50% {
        transform: scale(1.05) rotate(5deg);
        filter: drop-shadow(0 4px 8px rgba(10, 104, 71, 0.4));
    }
}

@keyframes loaderFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
@keyframes loaderShine {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(100%) rotate(45deg); }
}

/* Progress Bar Preloader */
.loader-bar {
    width: 200px; height: 4px; background: var(--bg-tertiary);
    border-radius: 4px; overflow: hidden; position: relative;
    margin: 0 auto;
}
.loader-bar::after {
    content: ''; display: block; width: 40%; height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px; animation: loading 1s ease-in-out infinite;
}
@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(350%); }
}

/* Teks Preloader (kontras tinggi) */
.loader-text {
    margin-top: 1.25rem;
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    position: relative;
    padding: 0 1rem;
}
.loader-text::before {
    content: ''; display: inline-block;
    width: 24px; height: 2px;
    background: var(--primary);
    vertical-align: middle;
    margin-right: 0.75rem;
    border-radius: 2px;
}
.loader-text::after {
    content: ''; display: inline-block;
    width: 24px; height: 2px;
    background: var(--primary);
    vertical-align: middle;
    margin-left: 0.75rem;
    border-radius: 2px;
}
[data-theme="dark"] .loader-text { color: #f1f5f9; }

/* =====================================================
   SEARCH MODAL (EXTREME)
   ===================================================== */
.search-modal {
    position: fixed; inset: 0; z-index: 9998;
    background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px);
    display: none; align-items: flex-start; justify-content: center;
    padding: 8rem 1rem 1rem;
}
.search-modal.show { display: flex; animation: fadeIn 0.25s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.search-box {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 640px; box-shadow: 0 30px 80px rgba(0,0,0,0.4);
    overflow: hidden; border: 1px solid var(--border);
    animation: searchPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes searchPop {
    from { transform: translateY(-20px) scale(0.95); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.search-input-wrap {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
}
.search-input-wrap svg { color: var(--text-muted); flex-shrink: 0; }
.search-input {
    flex: 1; border: none; background: transparent; outline: none;
    font-size: 1.1rem; font-family: inherit; color: var(--text-primary);
    font-weight: 500;
}
.search-input::placeholder { color: var(--text-muted); }
.search-kbd {
    background: var(--bg-tertiary); padding: 0.2rem 0.5rem;
    border-radius: 4px; font-size: 0.7rem; font-family: monospace;
    color: var(--text-muted); border: 1px solid var(--border);
}
.search-results { padding: 1rem; max-height: 400px; overflow-y: auto; }
.search-group { margin-bottom: 1rem; }
.search-group-title {
    font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.1em;
    padding: 0.5rem 0.75rem;
}
.search-result-item {
    display: flex; align-items: center; gap: 0.85rem;
    padding: 0.75rem; border-radius: var(--radius-md);
    cursor: pointer; transition: all 0.2s; text-decoration: none;
    color: var(--text-primary);
}
.search-result-item:hover, .search-result-item.active {
    background: var(--bg-secondary); transform: translateX(4px);
}
.search-result-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--bg-secondary); display: flex;
    align-items: center; justify-content: center; flex-shrink: 0;
}
.search-result-text { flex: 1; }
.search-result-text strong { display: block; font-size: 0.92rem; }
.search-result-text small { color: var(--text-muted); font-size: 0.78rem; }

/* =====================================================
   NOTIFICATION PANEL
   ===================================================== */
.notif-panel {
    position: fixed; top: 0; right: -400px; width: 100%; max-width: 400px;
    height: 100vh; background: var(--bg-primary); z-index: 9997;
    box-shadow: -10px 0 30px rgba(0,0,0,0.15);
    transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column;
}
.notif-panel.show { right: 0; }
.notif-header {
    padding: 1.5rem; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.notif-header h3 { margin: 0; font-size: 1.25rem; }
.notif-close {
    background: var(--bg-secondary); border: none; width: 36px; height: 36px;
    border-radius: 50%; cursor: pointer; font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); transition: all 0.2s;
}
.notif-close:hover { background: var(--bg-tertiary); transform: rotate(90deg); }
.notif-list { flex: 1; overflow-y: auto; padding: 1rem; }
.notif-item {
    padding: 1rem; border-radius: var(--radius-md); margin-bottom: 0.75rem;
    border: 1px solid var(--border); transition: all 0.2s;
}
.notif-item:hover { background: var(--bg-secondary); }
.notif-item-title { font-weight: 700; margin-bottom: 0.35rem; }
.notif-item-meta {
    font-size: 0.78rem; color: var(--text-muted);
    display: flex; gap: 0.5rem; align-items: center;
}

/* =====================================================
   RESPONSIVE MOBILE
   ===================================================== */
@media (max-width: 1024px) {
    .nav-menu-premium {
        position: fixed; top: 0; right: -100%; width: 85%; max-width: 340px; height: 100vh;
        background: var(--bg-primary); flex-direction: column;
        padding: 5rem 1.5rem 1.5rem;
        box-shadow: -10px 0 30px rgba(0,0,0,0.15);
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto; z-index: 999;
    }
    .nav-menu-premium.open { right: 0; }
    .nav-menu-premium li { width: 100%; }
    .nav-link-premium { padding: 0.85rem 1rem; width: 100%; }
    .nav-link-premium::after { display: none; }
    .nav-dropdown-premium { width: 100%; }
    .dropdown-menu-premium {
        position: static; opacity: 1; visibility: visible; transform: none;
        box-shadow: none; border: none; background: var(--bg-secondary);
        display: none; padding-left: 0.5rem; margin-top: 0.5rem;
        border-radius: var(--radius-md);
    }
    .nav-dropdown-premium.open .dropdown-menu-premium { display: block; }
    .nav-toggle-premium { display: flex; }

    .topbar-inner { flex-wrap: wrap; }
    .topbar-message { flex: 1 1 100%; order: 1; }
    .topbar-cta { order: 2; }
    .topbar-close { order: 3; }

    .icon-btn-premium[data-tooltip]:hover::after { display: none; }
}

@media (max-width: 640px) {
    .btn-portal-premium span { display: none; }
    .btn-portal-premium { padding: 0.6rem 0.85rem; }
    .logo-subtitle-premium { display: none; }
    .logo-title-premium { font-size: 1.1rem; }
    .logo-img-premium { width: 38px; height: 38px; padding: 3px; }
}
</style>
</head>
<body>
    <!-- Skip to Content (Accessibility) -->
    <a href="#main" class="skip-link">Langsung ke konten utama</a>

<!-- Preloader - AUTO-DETECT LOGO dari upload admin -->
<div class="preloader" id="preloader" aria-hidden="true">
    <div class="loader" role="status" aria-label="Memuat halaman">
        <div class="loader-logo">
            <?php if ($active_logo_url): ?>
                <!-- Logo dari upload admin (assets/images/logo.*) -->
                <img src="<?= $active_logo_url ?>" alt="FKIP UNIMOF" class="loader-logo-img">
            <?php else: ?>
                <!-- Fallback: Monogram "FU" jika belum ada logo upload -->
                <span class="loader-letter">FU</span>
            <?php endif; ?>
        </div>
        <div class="loader-bar"></div>
        <div class="loader-text">WELCOME TO FKIP UNIMOF</div>
    </div>
</div>

    <!-- ===== TOP BAR (Announcement) ===== -->
    <?php if (!empty($topbar_msg)): ?>
    <div class="topbar-premium" id="topbarPremium">
        <div class="container topbar-inner">
            <div class="topbar-message">
                <span><?= sanitize($topbar_msg) ?></span>
            </div>
            <a href="<?= base_url('pmb.php') ?>" class="topbar-cta">Daftar Sekarang →</a>
            <button class="topbar-close" id="topbarClose" aria-label="Tutup pengumuman">✕</button>
        </div>
    </div>
    <?php endif; ?>

<!-- ===== PREMIUM HEADER ===== -->
<header class="header-premium" id="header">
    <nav class="navbar-premium" aria-label="Navigasi utama">
        <div class="container navbar-container">
            <!-- Logo - AUTO-DETECT dari upload admin -->
            <a href="<?= base_url() ?>" class="logo-premium" aria-label="FKIP UNIMOF - Beranda">
                <?php if ($active_logo_url): ?>
                    <!-- Logo dari upload admin (assets/images/logo.*) -->
                    <div class="logo-img-premium" aria-hidden="true">
                        <img src="<?= $active_logo_url ?>" alt="Logo FKIP UNIMOF">
                    </div>
                <?php else: ?>
                    <!-- Fallback: Monogram "F" jika belum ada logo upload -->
                    <div class="logo-icon-premium" aria-hidden="true"><span>F</span></div>
                <?php endif; ?>
                <div class="logo-text-premium">
                    <span class="logo-title-premium">FKIP UNIMOF</span>
                    <span class="logo-subtitle-premium">Mencerdaskan Bangsa</span>
                </div>
            </a>

                <!-- Menu Utama -->
                <ul class="nav-menu-premium" id="navMenu" role="menubar">
                    <li role="none">
                        <a href="<?= base_url() ?>" class="nav-link-premium <?= $current_page === 'index.php' ? 'active' : '' ?>" role="menuitem">
                            🏠 Beranda
                        </a>
                    </li>

                    <!-- Dropdown: Profil -->
                    <li class="nav-dropdown-premium" role="none">
                        <a href="#" class="nav-link-premium <?= $is_profil ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" onclick="toggleMobileDropdown(event, this)">
                            Profil <span class="arrow-premium" aria-hidden="true">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium" role="menu">
                            <li role="none"><a href="<?= base_url('about.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🏛️</span>
                                <span class="dropdown-label">
                                    <strong>Tentang Kami</strong>
                                    <small>Visi, Misi, Sejarah</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('dosen.php') ?>" role="menuitem">
                                <span class="dropdown-icon">👨‍🏫</span>
                                <span class="dropdown-label">
                                    <strong>Dosen & Pengajar</strong>
                                    <small>Tim akademis profesional</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('fasilitas.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🏢</span>
                                <span class="dropdown-label">
                                    <strong>Fasilitas & Lab</strong>
                                    <small>Sarana modern & lengkap</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('prestasi.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🏆</span>
                                <span class="dropdown-label">
                                    <strong>Prestasi Mahasiswa</strong>
                                    <small>Hall of Fame</small>
                                </span>
                                <span class="dropdown-badge badge-hot">Hot</span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('akreditasi.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🏅</span>
                                <span class="dropdown-label">
                                    <strong>Akreditasi</strong>
                                    <small>BAN-PT & LAMDIK</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('kerjasama.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🤝</span>
                                <span class="dropdown-label">
                                    <strong>Kerjasama & Mitra</strong>
                                    <small>Nasional & Internasional</small>
                                </span>
                            </a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Akademik -->
                    <li class="nav-dropdown-premium" role="none">
                        <a href="#" class="nav-link-premium <?= $is_akademik ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" onclick="toggleMobileDropdown(event, this)">
                            Akademik <span class="arrow-premium" aria-hidden="true">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium" role="menu">
                            <li role="none"><a href="<?= base_url('program.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🎓</span>
                                <span class="dropdown-label">
                                    <strong>Program Studi</strong>
                                    <small>8 prodi unggulan</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('riset.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🔬</span>
                                <span class="dropdown-label">
                                    <strong>Pusat Riset</strong>
                                    <small>Penelitian pendidikan</small>
                                </span>
                                <span class="dropdown-badge badge-new">New</span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('jurnal.php') ?>" role="menuitem">
                                <span class="dropdown-icon">📚</span>
                                <span class="dropdown-label">
                                    <strong>Jurnal Ilmiah</strong>
                                    <small>SINTA & Scopus</small>
                                </span>
                            </a></li>
                        </ul>
                    </li>

                    <!-- Dropdown: Kemahasiswaan -->
                    <li class="nav-dropdown-premium" role="none">
                        <a href="#" class="nav-link-premium <?= $is_mahasiswa ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" onclick="toggleMobileDropdown(event, this)">
                            Kemahasiswaan <span class="arrow-premium" aria-hidden="true">▾</span>
                        </a>
                        <ul class="dropdown-menu-premium" role="menu">
                            <li role="none"><a href="<?= base_url('pmb.php') ?>" role="menuitem">
                                <span class="dropdown-icon">📝</span>
                                <span class="dropdown-label">
                                    <strong>Pendaftaran (PMB)</strong>
                                    <small>2026/2027 Gel. 1</small>
                                </span>
                                <span class="dropdown-badge badge-hot">Buka</span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('beasiswa.php') ?>" role="menuitem">
                                <span class="dropdown-icon">💰</span>
                                <span class="dropdown-label">
                                    <strong>Program Beasiswa</strong>
                                    <small>KIP-K & Muhammadiyah</small>
                                </span>
                            </a></li>
                            <li role="none"><a href="<?= base_url('alumni.php') ?>" role="menuitem">
                                <span class="dropdown-icon">🎓</span>
                                <span class="dropdown-label">
                                    <strong>Jejaring Alumni</strong>
                                    <small>Ikatan & karir</small>
                                </span>
                            </a></li>
                        </ul>
                    </li>

<!-- Dropdown: Informasi -->
<li class="nav-dropdown-premium" role="none">
    <a href="#" class="nav-link-premium <?= $is_info ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" onclick="toggleMobileDropdown(event, this)">
        Informasi <span class="arrow-premium" aria-hidden="true">▾</span>
    </a>
    <ul class="dropdown-menu-premium" role="menu">
        <li role="none"><a href="<?= base_url('berita.php') ?>" role="menuitem">
            <span class="dropdown-icon">📰</span>
            <span class="dropdown-label">
                <strong>Berita Terkini</strong>
                <small>Kabar kampus terbaru</small>
            </span>
        </a></li>

        <li role="none"><a href="<?= base_url('blog.php') ?>" role="menuitem">
            <span class="dropdown-icon">💡</span>
            <span class="dropdown-label">
                <strong>Ide &amp; Wawasan</strong>
                <small>Artikel &amp; opini dosen</small>
            </span>
            <span class="dropdown-badge badge-new">New</span>
        </a></li>

        <li role="none"><a href="<?= base_url('video.php') ?>" role="menuitem">
            <span class="dropdown-icon">🎬</span>
            <span class="dropdown-label">
                <strong>Video &amp; Podcast</strong>
                <small>Konten multimedia kampus</small>
            </span>
            <span class="dropdown-badge badge-new">New</span>
        </a></li>

        <li role="none"><a href="<?= base_url('agenda.php') ?>" role="menuitem">
            <span class="dropdown-icon">📅</span>
            <span class="dropdown-label">
                <strong>Agenda &amp; Kalender</strong>
                <small>Jadwal akademik</small>
            </span>
        </a></li>

        <li role="none"><a href="<?= base_url('download.php') ?>" role="menuitem">
            <span class="dropdown-icon">📥</span>
            <span class="dropdown-label">
                <strong>Download Center</strong>
                <small>Formulir &amp; dokumen</small>
            </span>
        </a></li>

        <li role="none"><a href="<?= base_url('faq.php') ?>" role="menuitem">
            <span class="dropdown-icon">❓</span>
            <span class="dropdown-label">
                <strong>FAQ</strong>
                <small>Pertanyaan umum</small>
            </span>
        </a></li>

        <li role="none"><a href="<?= base_url('galeri.php') ?>" role="menuitem">
            <span class="dropdown-icon">📸</span>
            <span class="dropdown-label">
                <strong>Galeri</strong>
                <small>Dokumentasi kegiatan</small>
            </span>
        </a></li>

        <li role="none"><a href="<?= base_url('kontak.php') ?>" role="menuitem">
            <span class="dropdown-icon">✉️</span>
            <span class="dropdown-label">
                <strong>Hubungi Kami</strong>
                <small>Kontak &amp; lokasi</small>
            </span>
        </a></li>
    </ul>
</li>

                <!-- Aksi Kanan -->
                <div class="nav-actions-premium">
                    <!-- Search Button -->
                    <button class="icon-btn-premium" id="searchTrigger" aria-label="Cari" data-tooltip="Cari (/)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>

                    <!-- Notification Button -->
                    <button class="icon-btn-premium" id="notifTrigger" aria-label="Notifikasi" data-tooltip="Notifikasi">
                        🔔
                        <?php if ($notif_count > 0): ?>
                        <span class="notif-badge" id="notifBadge"><?= $notif_count ?></span>
                        <?php endif; ?>
                    </button>

                    <!-- Theme Toggle -->
                    <button class="icon-btn-premium" id="themeToggle" aria-label="Toggle dark mode" data-tooltip="Mode gelap (D)">
                        <span class="theme-icon-premium">🌙</span>
                    </button>

                    <!-- Portal Button -->
                    <a href="<?= base_url('admin/login.php') ?>" class="btn-portal-premium" aria-label="Login portal">
                        <span>🔐</span> <span>Portal</span>
                    </a>

                    <!-- Mobile Toggle -->
                    <button class="nav-toggle-premium" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Search Modal -->
    <div class="search-modal" id="searchModal" role="dialog" aria-modal="true" aria-label="Pencarian">
        <div class="search-box">
            <div class="search-input-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" class="search-input" id="globalSearchInput" placeholder="Cari program studi, berita, dosen..." autocomplete="off" aria-label="Input pencarian">
                <span class="search-kbd">ESC</span>
            </div>
            <div class="search-results" id="searchResults">
                <div class="search-group">
                    <div class="search-group-title">Pencarian Cepat</div>
                    <a href="<?= base_url('pmb.php') ?>" class="search-result-item">
                        <div class="search-result-icon">📝</div>
                        <div class="search-result-text">
                            <strong>Daftar PMB 2026/2027</strong>
                            <small>Pendaftaran mahasiswa baru</small>
                        </div>
                    </a>
                    <a href="<?= base_url('program.php') ?>" class="search-result-item">
                        <div class="search-result-icon">🎓</div>
                        <div class="search-result-text">
                            <strong>Program Studi</strong>
                            <small>Lihat 8 prodi unggulan</small>
                        </div>
                    </a>
                    <a href="<?= base_url('beasiswa.php') ?>" class="search-result-item">
                        <div class="search-result-icon">💰</div>
                        <div class="search-result-text">
                            <strong>Beasiswa</strong>
                            <small>KIP-K, Muhammadiyah, Prestasi</small>
                        </div>
                    </a>
                    <a href="<?= base_url('kontak.php') ?>" class="search-result-item">
                        <div class="search-result-icon">📞</div>
                        <div class="search-result-text">
                            <strong>Kontak & Lokasi</strong>
                            <small>Hubungi kami</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Panel -->
    <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifikasi">
        <div class="notif-header">
            <h3>🔔 Notifikasi</h3>
            <button class="notif-close" id="notifClose" aria-label="Tutup notifikasi">✕</button>
        </div>
        <div class="notif-list">
            <?php if (empty($notif_items)): ?>
                <div style="text-align:center; padding:3rem 1rem; color: var(--text-muted);">
                    <div style="font-size:3rem; margin-bottom:0.5rem;">📭</div>
                    <p>Belum ada notifikasi baru</p>
                </div>
            <?php else: ?>
                <?php foreach ($notif_items as $n): ?>
                <div class="notif-item">
                    <div class="notif-item-title"><?= sanitize($n['judul'] ?? 'Pengumuman') ?></div>
                    <div class="notif-item-meta">
                        <span>📅 <?= date('d M Y', strtotime($n['tanggal'] ?? 'now')) ?></span>
                        <?php if (!empty($n['kategori'])): ?>
                        <span>• <?= sanitize($n['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Spacer untuk fixed header (auto-adjusted via JS jika topbar ada) -->
    <div id="headerSpacer" style="height: 80px;"></div>

    <main id="main">

    <script>
    // =====================================================
    // HEADER EXTREME MULTIMATE SCRIPT
    // =====================================================
    (function() {
        'use strict';

        // ===== 1. TOP BAR DISMISS =====
        const topbar = document.getElementById('topbarPremium');
        const topbarClose = document.getElementById('topbarClose');
        if (topbar && localStorage.getItem('fkip_topbar_dismissed') === 'true') {
            topbar.classList.add('dismissed');
        }
        if (topbarClose) {
            topbarClose.addEventListener('click', () => {
                topbar.classList.add('dismissed');
                localStorage.setItem('fkip_topbar_dismissed', 'true');
                adjustHeaderSpacer();
            });
        }

        // ===== 2. HEADER SPACER ADJUSTMENT =====
        function adjustHeaderSpacer() {
            const header = document.getElementById('header');
            const spacer = document.getElementById('headerSpacer');
            if (!header || !spacer) return;
            const topbarVisible = topbar && !topbar.classList.contains('dismissed');
            const topbarHeight = topbarVisible ? topbar.offsetHeight : 0;
            const headerHeight = header.offsetHeight;
            spacer.style.height = (headerHeight + topbarHeight) + 'px';

            // Adjust header top position
            if (topbarVisible) {
                header.style.top = topbarHeight + 'px';
            } else {
                header.style.top = '0';
            }
        }
        window.addEventListener('load', adjustHeaderSpacer);
        window.addEventListener('resize', adjustHeaderSpacer);

        // ===== 3. MOBILE DROPDOWN TOGGLE =====
        window.toggleMobileDropdown = function(e, el) {
            if (window.innerWidth > 1024) return;
            e.preventDefault();
            e.stopPropagation();
            const dropdown = el.closest('.nav-dropdown-premium');
            if (dropdown) dropdown.classList.toggle('open');
        };

        // ===== 4. MOBILE MENU OVERLAY SYNC =====
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function syncMenuState(isOpen) {
            if (mobileOverlay) mobileOverlay.classList.toggle('show', isOpen);
            if (navToggle) {
                navToggle.classList.toggle('active', isOpen);
                navToggle.setAttribute('aria-expanded', isOpen);
            }
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', () => {
                const willOpen = !navMenu.classList.contains('open');
                navMenu.classList.toggle('open');
                syncMenuState(willOpen);
            });
        }
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', () => {
                if (navMenu) navMenu.classList.remove('open');
                syncMenuState(false);
            });
        }

        // ===== 5. SEARCH MODAL =====
        const searchTrigger = document.getElementById('searchTrigger');
        const searchModal = document.getElementById('searchModal');
        const searchInput = document.getElementById('globalSearchInput');

        function openSearch() {
            if (searchModal) {
                searchModal.classList.add('show');
                setTimeout(() => searchInput && searchInput.focus(), 100);
            }
        }
        function closeSearch() {
            if (searchModal) searchModal.classList.remove('show');
        }

        if (searchTrigger) searchTrigger.addEventListener('click', openSearch);
        if (searchModal) {
            searchModal.addEventListener('click', (e) => {
                if (e.target === searchModal) closeSearch();
            });
        }

        // ===== 6. NOTIFICATION PANEL =====
        const notifTrigger = document.getElementById('notifTrigger');
        const notifPanel = document.getElementById('notifPanel');
        const notifClose = document.getElementById('notifClose');
        const mobileOverlay2 = document.getElementById('mobileOverlay');

        function toggleNotif(show) {
            if (!notifPanel) return;
            notifPanel.classList.toggle('show', show);
            if (mobileOverlay) mobileOverlay.classList.toggle('show', show);
            // Clear badge
            if (show) {
                const badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
            }
        }
        if (notifTrigger) notifTrigger.addEventListener('click', () => toggleNotif(!notifPanel.classList.contains('show')));
        if (notifClose) notifClose.addEventListener('click', () => toggleNotif(false));

        // ===== 7. GLOBAL KEYBOARD SHORTCUTS =====
        document.addEventListener('keydown', (e) => {
            const tag = document.activeElement?.tagName;
            const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag);

            // Escape: close semua modal
            if (e.key === 'Escape') {
                closeSearch();
                toggleNotif(false);
                if (navMenu && navMenu.classList.contains('open')) {
                    navMenu.classList.remove('open');
                    syncMenuState(false);
                }
            }

            if (isTyping) return;

            // / untuk search
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                openSearch();
            }
            // N untuk notifikasi
            if (e.key.toLowerCase() === 'n' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                if (notifPanel) toggleNotif(!notifPanel.classList.contains('show'));
            }
        });

        // ===== 8. SMART SCROLL HIDE/SHOW =====
        // Hanya sembunyikan navbar saat scroll DOWN (kecuali di atas topbar)
        const header = document.getElementById('header');
        let lastScrollY = window.pageYOffset;
        let ticking = false;

        function updateHeader() {
            const scrollY = window.pageYOffset;
            if (!header) return;

            // Jangan sembunyikan di dekat top (untuk topbar visibility)
            if (scrollY < 100) {
                header.classList.remove('nav-hidden');
                lastScrollY = scrollY;
                ticking = false;
                return;
            }

            if (scrollY > lastScrollY && scrollY > 200) {
                // Scroll down → sembunyikan
                header.classList.add('nav-hidden');
                if (topbar && !topbar.classList.contains('dismissed')) {
                    topbar.classList.add('dismissed');
                    localStorage.setItem('fkip_topbar_dismissed', 'true');
                }
            } else if (scrollY < lastScrollY) {
                // Scroll up → tampilkan
                header.classList.remove('nav-hidden');
            }

            lastScrollY = scrollY;
            ticking = false;
        }

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(updateHeader);
                ticking = true;
            }
        }, { passive: true });

        console.log('%c🎓 Header EXTREME MULTIMATE', 'color:#0a6847;font-size:12px;font-weight:bold');
        console.log('%cShortcuts: / (cari) • N (notif) • D (dark) • Esc (tutup)', 'color:#64748b;font-size:11px');
    })();
    </script>
</body>
</html>