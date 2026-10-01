<?php
// admin/includes/header.php - SUPER EXTREME ULTIMATE EDITION
require_login();

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$active = $active_menu ?? 'dashboard';
$heading = $page_heading ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [['Dashboard', null]];

// Time-based greeting
$hour = (int)date('H');
if ($hour < 12) $greeting = 'Selamat Pagi';
elseif ($hour < 15) $greeting = 'Selamat Siang';
elseif ($hour < 18) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';

// Comprehensive notifications
$notifications = [
    'kontak_baru' => 0,
    'berita_draft' => 0,
    'alumni_pending' => 0,
    'agenda_soon' => 0,
    'beasiswa_deadline' => 0,
    'riset_pending' => 0,
];

try {
    $notifications['kontak_baru'] = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Baru'")->fetchColumn();
    $notifications['berita_draft'] = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
    
    // Check if tables exist before querying
    try {
        $notifications['alumni_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Pending'")->fetchColumn();
    } catch (Exception $e) { $notifications['alumni_pending'] = 0; }
    
    try {
        $notifications['agenda_soon'] = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status='Aktif'")->fetchColumn();
    } catch (Exception $e) { $notifications['agenda_soon'] = 0; }
    
    try {
        $notifications['beasiswa_deadline'] = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status='Terbuka'")->fetchColumn();
    } catch (Exception $e) { $notifications['beasiswa_deadline'] = 0; }
    
    try {
        $notifications['riset_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Draft'")->fetchColumn();
    } catch (Exception $e) { $notifications['riset_pending'] = 0; }
    
} catch (Exception $e) {}

$total_notifications = array_sum($notifications);

// Recent pages (from session)
if (!isset($_SESSION['recent_pages'])) $_SESSION['recent_pages'] = [];

// Enhanced menu with metadata
$menu_groups = [
    'Utama' => [
        'dashboard' => ['📊', 'Dashboard', 'dashboard.php', 'Overview & statistik', 'analytics'],
    ],
    'Konten' => [
        'berita' => ['📰', 'Kelola Berita', 'berita.php', 'CRUD artikel', 'content'],
        'blog' => ['💡', 'Ide & Wawasan', 'blog.php', 'Artikel & opini dosen', 'content'],
        'video' => ['🎬', 'Video & Podcast', 'video.php', 'Konten multimedia', 'media'],
        'agenda' => ['📆', 'Agenda & Acara', 'agenda.php', 'Jadwal kegiatan', 'events'],
        'download' => ['📥', 'Download Center', 'download.php', 'Kelola file', 'files'],
        'galeri' => ['📸', 'Galeri Foto', 'galeri.php', 'Dokumentasi kegiatan', 'media'],
        'faq' => ['❓', 'FAQ', 'faq.php', 'Pertanyaan umum', 'content'],
    ],
    'Akademik' => [
        'prodi' => ['🎓', 'Program Studi', 'program.php', 'Kelola data prodi', 'academic'],
        'dosen' => ['👨‍🏫', 'Data Dosen', 'dosen.php', 'Profil pengajar', 'people'],
        'riset' => ['🔬', 'Riset & Jurnal', 'riset.php', 'Publikasi ilmiah', 'research'],
        'jurnal' => ['📚', 'Jurnal Ilmiah', 'jurnal.php', 'Kelola jurnal', 'academic'],
        'fasilitas' => ['🏢', 'Fasilitas & Lab', 'fasilitas.php', 'Sarana prasarana', 'facilities'],
        'akreditasi'=> ['🏅', 'Akreditasi', 'akreditasi.php', 'Data akreditasi', 'academic'],
    ],
    'Kemahasiswaan' => [
        'prestasi' => ['🏆', 'Prestasi', 'prestasi.php', 'Pencapaian mahasiswa', 'achievements'],
        'alumni' => ['🎓', 'Data Alumni', 'alumni.php', 'Jejaring lulusan', 'people'],
        'beasiswa' => ['💰', 'Beasiswa', 'beasiswa.php', 'Program beasiswa', 'financial'],
    ],
    'Lainnya' => [
    'kerjasama' => ['🤝', 'Kerjasama', 'kerjasama.php', 'Mitra institusi', 'partners'],
    'kontak' => ['✉️', 'Pesan Masuk', 'kontak.php', 'Inbox pengunjung', 'communication'],
    'testimoni' => ['💬', 'Testimoni', 'testimoni.php', 'Suara mahasiswa & alumni', 'content'],
    'statistik' => ['📊', 'Statistik', 'statistik.php', 'Angka & metrik fakultas', 'analytics'],
    'pengaturan'=> ['⚙️', 'Pengaturan', 'pengaturan.php', 'Konfigurasi', 'settings'],
    ]
];

// Pinned favorites (stored in localStorage via JS)
$pinned_menus = ['dashboard', 'berita', 'kontak'];

// Quick create items
$quick_create = [
    ['📰', 'Berita Baru', 'berita-form.php', 'content'],
    ['💡', 'Artikel Blog', 'blog-form.php', 'content'],
    ['📆', 'Agenda Baru', 'agenda-form.php', 'events'],
    ['📥', 'Upload File', 'download-form.php', 'files'],
    ['🎬', 'Video Baru', 'video-form.php', 'media'],
    ['🔬', 'Riset Baru', 'riset-form.php', 'research'],
];
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a6847">
<script>
(function(){
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    var t='light';
    try{ t=localStorage.getItem('admin-theme')||'light'; }catch(e){}
    if(['light','dark','midnight','emerald'].indexOf(t)<0) t='light';
    document.documentElement.setAttribute('data-theme',t);
})();
</script>
<title><?= sanitize($heading) ?> - Admin FKIP UNIMOF</title>

<!-- Preload Critical Resources -->
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" as="style">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('admin/assets/css/admin-style.css') ?>">

<style>
/* ========================================
   SUPER EXTREME ULTIMATE ADMIN STYLES
   ======================================== */

:root {
    /* Color System */
    --primary: #0a6847;
    --primary-light: #16a34a;
    --primary-dark: #064e34;
    --accent: #0a6847;
    
    /* Light Theme */
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --bg-tertiary: #f1f5f9;
    --bg-elevated: #ffffff;
    
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #64748b;
    --text-disabled: #cbd5e1;
    
    --border: #e2e8f0;
    --border-strong: #cbd5e1;
    
    /* Sidebar */
    --sidebar-bg: linear-gradient(180deg, #0a6847 0%, #064e34 100%);
    --sidebar-width: 280px;
    --sidebar-collapsed-width: 72px;
    
    /* Glass Effects */
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.6);
    --glass-blur: blur(20px);
    
    /* Shadows */
    --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.10);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.10);
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.10);
    --shadow-glow: 0 0 40px rgba(10,104,71,0.3);
    
    /* Radii */
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-2xl: 20px;
    --radius-full: 999px;
    
    /* Typography */
    --font-body: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-display: 'Georgia', 'Times New Roman', serif;
    --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
    
    /* Spacing */
    --space-xs: 0.25rem;
    --space-sm: 0.5rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --space-xl: 2rem;
    --space-2xl: 3rem;
    
    /* Transitions */
    --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    
    /* Z-Index Scale */
    --z-sidebar: 100;
    --z-overlay: 99;
    --z-topbar: 90;
    --z-dropdown: 200;
    --z-modal: 500;
    --z-toast: 600;
    --z-command: 700;
}

/* Dark Theme */
[data-theme="dark"] {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --bg-elevated: #1e293b;
    
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --text-disabled: #475569;
    
    --border: #334155;
    --border-strong: #475569;
    
    --glass-bg: rgba(15, 23, 42, 0.85);
    --glass-border: rgba(255, 255, 255, 0.08);
}

/* Midnight Theme */
[data-theme="midnight"] {
    --bg-primary: #020617;
    --bg-secondary: #0f172a;
    --bg-tertiary: #1e293b;
    --bg-elevated: #0f172a;
    
    --text-primary: #e2e8f0;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    
    --border: #1e293b;
    --border-strong: #334155;
    
    --glass-bg: rgba(2, 6, 23, 0.9);
    --sidebar-bg: linear-gradient(180deg, #020617 0%, #0a0f1e 100%);
}

/* Emerald Theme */
[data-theme="emerald"] {
    --primary: #059669;
    --primary-light: #10b981;
    --primary-dark: #047857;
    --sidebar-bg: linear-gradient(180deg, #059669 0%, #047857 100%);
}

/* ========================================
   BASE STYLES
   ======================================== */
* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

html {
    scroll-behavior: smooth;
}

body { 
    font-family: var(--font-body); 
    background: var(--bg-secondary); 
    color: var(--text-primary); 
    overflow-x: hidden;
    transition: background-color 0.3s ease, color 0.3s ease;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Focus visible for accessibility */
:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/* ========================================
   LAYOUT
   ======================================== */
.dashboard { 
    display: flex; 
    min-height: 100vh; 
}

/* ========================================
   SIDEBAR
   ======================================== */
.sidebar { 
    width: var(--sidebar-width); 
    background: var(--sidebar-bg); 
    color: #fff; 
    position: sticky; 
    top: 0; 
    height: 100vh; 
    overflow-y: auto; 
    overflow-x: hidden; 
    flex-shrink: 0; 
    box-shadow: 4px 0 32px rgba(0,0,0,0.2); 
    z-index: var(--z-sidebar); 
    transition: width 0.3s var(--ease-out), transform 0.3s var(--ease-out);
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { 
    background: rgba(255,255,255,0.2); 
    border-radius: 10px;
}
.sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.4); }

/* Sidebar Header */
.sidebar-header { 
    padding: 1.5rem; 
    border-bottom: 1px solid rgba(255,255,255,0.1); 
    background: rgba(0,0,0,0.2); 
    backdrop-filter: var(--glass-blur);
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 60%);
    animation: rotateGlow 30s linear infinite;
    pointer-events: none;
}

@keyframes rotateGlow { 
    from { transform: rotate(0deg); } 
    to { transform: rotate(360deg); } 
}

.sidebar-header h2 { 
    font-size: 1.25rem; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    font-weight: 800; 
    letter-spacing: -0.02em;
    position: relative;
    z-index: 1;
}

.sidebar-header p { 
    font-size: 0.7rem; 
    opacity: 0.7; 
    margin-top: 0.25rem; 
    font-weight: 500;
    position: relative;
    z-index: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Sidebar Search */
.sidebar-search {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    flex-shrink: 0;
}

.sidebar-search-input {
    width: 100%;
    padding: 0.6rem 0.75rem 0.6rem 2.25rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: var(--radius-md);
    color: #fff;
    font-size: 0.85rem;
    font-family: inherit;
    transition: all 0.2s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.5)' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 0.75rem center;
}

.sidebar-search-input::placeholder { color: rgba(255,255,255,0.4); }
.sidebar-search-input:focus { 
    outline: none; 
    background: rgba(255,255,255,0.15); 
    border-color: rgba(255,255,255,0.3);
}

/* Sidebar Menu */
.sidebar-menu { 
    list-style: none; 
    padding: 0.75rem;
    flex: 1;
    overflow-y: auto;
}

.menu-group {
    margin-bottom: 0.5rem;
}

.menu-label { 
    font-size: 0.65rem; 
    text-transform: uppercase; 
    letter-spacing: 0.15em; 
    color: rgba(255,255,255,0.35); 
    font-weight: 700; 
    padding: 0.75rem 0.75rem 0.4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    transition: color 0.2s;
}

.menu-label:hover { color: rgba(255,255,255,0.6); }

.menu-label .collapse-icon {
    font-size: 0.6rem;
    transition: transform 0.3s var(--ease-out);
}

.menu-group.collapsed .menu-label .collapse-icon {
    transform: rotate(-90deg);
}

.menu-group.collapsed .menu-items {
    display: none;
}

.menu-items {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.sidebar-menu li { 
    list-style: none;
}

.sidebar-menu a { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    padding: 0.7rem 0.75rem; 
    color: rgba(255,255,255,0.7); 
    text-decoration: none; 
    font-size: 0.875rem; 
    font-weight: 500; 
    border-radius: var(--radius-md); 
    transition: all 0.2s var(--ease-out); 
    position: relative; 
    border: 1px solid transparent;
}

.sidebar-menu .menu-icon { 
    font-size: 1.15rem; 
    width: 24px; 
    text-align: center; 
    flex-shrink: 0;
    transition: transform 0.2s;
}

.sidebar-menu .menu-text { 
    flex: 1; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    white-space: nowrap;
    overflow: hidden;
}

.sidebar-menu .menu-name {
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-menu a:hover { 
    background: rgba(255,255,255,0.1); 
    color: #fff; 
    transform: translateX(3px);
}

.sidebar-menu a:hover .menu-icon {
    transform: scale(1.1);
}

.sidebar-menu a.active { 
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%); 
    color: #fff; 
    font-weight: 600;
    border-color: rgba(255,255,255,0.15);
}

.sidebar-menu a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 50%;
    background: #fff;
    border-radius: 0 3px 3px 0;
    box-shadow: 0 0 10px rgba(255,255,255,0.5);
}

/* Pinned indicator */
.sidebar-menu a .pin-icon {
    font-size: 0.7rem;
    opacity: 0;
    transition: opacity 0.2s;
    cursor: pointer;
    padding: 0.25rem;
}

.sidebar-menu a:hover .pin-icon { opacity: 0.7; }
.sidebar-menu a:hover .pin-icon:hover { opacity: 1; }
.sidebar-menu a.pinned .pin-icon { opacity: 0.9; color: #fbbf24; }

/* Notification badge */
.notification-badge {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff; 
    font-size: 0.65rem; 
    font-weight: 700; 
    min-width: 20px; 
    height: 20px; 
    border-radius: var(--radius-full); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 0 6px;
    border: 2px solid rgba(255,255,255,0.2);
    box-shadow: 0 2px 8px rgba(239,68,68,0.4);
    animation: pulseBadge 2s infinite;
}

@keyframes pulseBadge {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
    70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Sidebar Divider */
.sidebar-divider {
    height: 1px; 
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
    margin: 0.75rem 0.75rem;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}

.sidebar-menu a.logout {
    color: #fca5a5;
    border: 1px solid rgba(252,165,165,0.15);
}

.sidebar-menu a.logout:hover {
    background: rgba(252,165,165,0.12);
    border-color: rgba(252,165,165,0.3);
    color: #fff;
}

/* Sidebar collapse toggle */
.sidebar-toggle {
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
    color: var(--text-secondary);
    box-shadow: var(--shadow-md);
    transition: all 0.2s;
    z-index: 101;
}

.sidebar-toggle:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Collapsed sidebar */
.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar.collapsed .sidebar-header p,
.sidebar.collapsed .sidebar-search,
.sidebar.collapsed .menu-label,
.sidebar.collapsed .menu-text,
.sidebar.collapsed .pin-icon {
    display: none;
}

.sidebar.collapsed .sidebar-header h2 {
    justify-content: center;
    font-size: 1.5rem;
}

.sidebar.collapsed .sidebar-menu a {
    justify-content: center;
    padding: 0.75rem;
}

.sidebar.collapsed .sidebar-menu .menu-icon {
    font-size: 1.25rem;
    width: auto;
}

/* ========================================
   MAIN CONTENT
   ======================================== */
.main { 
    flex: 1; 
    padding: var(--space-xl); 
    min-width: 0;
    transition: padding 0.3s var(--ease-out); 
}

/* ========================================
   TOP BAR
   ======================================== */
.top-bar { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: var(--space-xl); 
    padding: 0.75rem 1.25rem; 
    background: var(--glass-bg); 
    backdrop-filter: var(--glass-blur); 
    border: 1px solid var(--glass-border); 
    border-radius: var(--radius-xl); 
    box-shadow: var(--shadow-lg); 
    position: sticky; 
    top: 1rem; 
    z-index: var(--z-topbar); 
    flex-wrap: wrap; 
    gap: 0.75rem; 
}

.top-bar-left { 
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

#mobileMenuBtn {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-primary);
    padding: 0.25rem;
}

.page-title-area h1 { 
    font-size: 1.25rem; 
    font-weight: 800; 
    margin-bottom: 0.125rem; 
    letter-spacing: -0.02em; 
    color: var(--text-primary); 
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.breadcrumb { 
    display: flex; 
    gap: 0.4rem; 
    font-size: 0.75rem; 
    color: var(--text-muted); 
    align-items: center; 
    font-weight: 500; 
}

.breadcrumb a { 
    color: var(--text-muted); 
    text-decoration: none; 
    transition: color 0.2s; 
}

.breadcrumb a:hover { color: var(--primary); }

.breadcrumb span:not(:last-child) { color: var(--text-disabled); }
.breadcrumb span:last-child { color: var(--text-secondary); font-weight: 600; }

.top-bar-right { 
    display: flex; 
    gap: 0.5rem; 
    align-items: center; 
}

/* Quick Create Button */
.quick-create-wrap { position: relative; }

.quick-create-btn {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border: none;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
    transition: all 0.2s;
    font-size: 1.25rem;
    color: white;
}

.quick-create-btn:hover {
    transform: translateY(-2px) rotate(90deg);
    box-shadow: 0 8px 20px rgba(10,104,71,0.4);
}

.quick-create-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 240px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s var(--ease-out);
    z-index: var(--z-dropdown);
    padding: 0.5rem;
}

.quick-create-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.quick-create-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--radius-md);
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: background 0.15s;
}

.quick-create-item:hover { background: var(--bg-secondary); }

.quick-create-item .qc-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* Theme Toggle */
.theme-toggle-wrap { position: relative; }

.theme-toggle {
    width: 40px;
    height: 40px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: var(--shadow-xs);
    transition: all 0.2s;
    font-size: 1.1rem;
}

.theme-toggle:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.theme-toggle .sun-icon { display: none; color: #f59e0b; }
.theme-toggle .moon-icon { display: block; color: var(--text-secondary); }

[data-theme="dark"] .theme-toggle .sun-icon,
[data-theme="midnight"] .theme-toggle .sun-icon { display: block; }
[data-theme="dark"] .theme-toggle .moon-icon,
[data-theme="midnight"] .theme-toggle .moon-icon { display: none; }

.theme-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 220px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s var(--ease-out);
    z-index: var(--z-dropdown);
    padding: 0.5rem;
}

.theme-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.theme-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: background 0.15s;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-primary);
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}

.theme-option:hover { background: var(--bg-secondary); }
.theme-option.active { background: var(--bg-tertiary); }

.theme-option .theme-preview {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 2px solid var(--border);
}

.theme-option[data-theme="light"] .theme-preview { background: linear-gradient(135deg, #fff, #f8fafc); }
.theme-option[data-theme="dark"] .theme-preview { background: linear-gradient(135deg, #1e293b, #0f172a); }
.theme-option[data-theme="midnight"] .theme-preview { background: linear-gradient(135deg, #0f172a, #020617); }
.theme-option[data-theme="emerald"] .theme-preview { background: linear-gradient(135deg, #10b981, #059669); }

/* Notification */
.notification-wrap { position: relative; }

.notification-btn { 
    width: 40px; 
    height: 40px; 
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: var(--radius-md); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    box-shadow: var(--shadow-xs); 
    transition: all 0.2s; 
    font-size: 1.1rem; 
    color: var(--text-primary);
    position: relative;
}

.notification-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: var(--shadow-md); 
}

.notification-btn .notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    font-size: 0.6rem;
    border: 2px solid var(--bg-primary);
}

.notification-dropdown { 
    position: absolute; 
    top: calc(100% + 8px); 
    right: 0; 
    width: 380px; 
    max-height: 500px;
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: var(--radius-xl); 
    box-shadow: var(--shadow-xl); 
    opacity: 0; 
    visibility: hidden; 
    transform: translateY(-8px); 
    transition: all 0.2s var(--ease-out); 
    z-index: var(--z-dropdown); 
    overflow: hidden; 
    display: flex;
    flex-direction: column;
}

.notification-dropdown.show { 
    opacity: 1; 
    visibility: visible; 
    transform: translateY(0); 
}

.notif-header { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid var(--border); 
    font-weight: 700; 
    font-size: 0.9rem; 
    display: flex; 
    justify-content: space-between;
    align-items: center;
    color: var(--text-primary);
    flex-shrink: 0;
}

.notif-header .clear-btn {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    cursor: pointer;
    background: none;
    border: none;
    font-family: inherit;
    transition: color 0.2s;
}

.notif-header .clear-btn:hover { color: var(--primary); }

.notif-list {
    overflow-y: auto;
    flex: 1;
    max-height: 400px;
}

.notif-item { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    gap: 0.75rem; 
    align-items: flex-start; 
    transition: background 0.15s; 
    cursor: pointer; 
    text-decoration: none; 
    color: var(--text-primary);
}

.notif-item:hover { background: var(--bg-secondary); }
.notif-item:last-child { border-bottom: none; }

.notif-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.notif-icon.blue { background: #dbeafe; color: #2563eb; }
.notif-icon.amber { background: #fef3c7; color: #d97706; }
.notif-icon.green { background: #dcfce7; color: #16a34a; }
.notif-icon.purple { background: #f3e8ff; color: #9333ea; }
.notif-icon.pink { background: #fce7f3; color: #db2777; }
.notif-icon.red { background: #fee2e2; color: #dc2626; }

.notif-content { flex: 1; min-width: 0; }
.notif-title { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.125rem; }
.notif-desc { font-size: 0.75rem; color: var(--text-muted); line-height: 1.4; }
.notif-time { font-size: 0.7rem; color: var(--text-disabled); margin-top: 0.25rem; }

.notif-empty {
    padding: 3rem 2rem;
    text-align: center;
    color: var(--text-muted);
}
.notif-empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.4; }
.notif-empty-text { font-size: 0.9rem; font-weight: 500; }
.notif-empty-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }

/* User Menu */
.user-menu-wrap { position: relative; }

.user-info { 
    display: flex; 
    align-items: center; 
    gap: 0.6rem; 
    background: var(--bg-primary); 
    padding: 0.35rem 0.35rem 0.35rem 0.85rem; 
    border-radius: var(--radius-full); 
    border: 1px solid var(--border); 
    cursor: pointer; 
    transition: all 0.2s; 
}

.user-info:hover { 
    box-shadow: var(--shadow-md); 
    transform: translateY(-1px); 
    border-color: var(--border-strong);
}

.user-avatar { 
    width: 32px; 
    height: 32px; 
    background: linear-gradient(135deg, var(--primary), var(--primary-light)); 
    color: #fff; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 700; 
    font-size: 0.8rem; 
    flex-shrink: 0; 
}

.user-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1;
}

.user-role {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 500;
    line-height: 1;
    margin-top: 0.15rem;
}

.user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 240px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s var(--ease-out);
    z-index: var(--z-dropdown);
    padding: 0.5rem;
}

.user-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-header {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 0.25rem;
}

.user-dropdown-name { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
.user-dropdown-email { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

.user-dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--radius-md);
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: background 0.15s;
}

.user-dropdown-item:hover { background: var(--bg-secondary); }

.user-dropdown-item.logout {
    color: #dc2626;
    margin-top: 0.25rem;
    border-top: 1px solid var(--border);
    padding-top: 0.75rem;
    border-radius: 0 0 var(--radius-md) var(--radius-md);
}

/* ========================================
   COMMAND PALETTE (Ctrl+K)
   ======================================== */
.command-palette-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: var(--z-command);
    display: none;
    align-items: flex-start;
    justify-content: center;
    padding-top: 15vh;
    animation: fadeIn 0.15s ease;
}

.command-palette-overlay.show { display: flex; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.command-palette {
    width: 100%;
    max-width: 560px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    overflow: hidden;
    animation: slideDown 0.2s var(--ease-out);
}

@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.command-palette-input-wrap {
    display: flex;
    align-items: center;
    padding: 0 1.25rem;
    border-bottom: 1px solid var(--border);
}

.command-palette-input-wrap .search-icon {
    font-size: 1.25rem;
    color: var(--text-muted);
    margin-right: 0.75rem;
}

.command-palette-input {
    flex: 1;
    padding: 1.1rem 0;
    border: none;
    background: transparent;
    font-size: 1rem;
    font-family: inherit;
    color: var(--text-primary);
    outline: none;
}

.command-palette-input::placeholder { color: var(--text-muted); }

.command-palette-kbd {
    padding: 0.2rem 0.5rem;
    background: var(--bg-tertiary);
    border-radius: 4px;
    font-size: 0.7rem;
    font-family: var(--font-mono);
    color: var(--text-muted);
    font-weight: 600;
}

.command-palette-results {
    max-height: 400px;
    overflow-y: auto;
    padding: 0.5rem;
}

.command-group {
    margin-bottom: 0.5rem;
}

.command-group-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    font-weight: 700;
    padding: 0.5rem 0.75rem 0.25rem;
}

.command-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: background 0.1s;
}

.command-item:hover,
.command-item.active { background: var(--bg-secondary); }

.command-item-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.command-item-text { flex: 1; min-width: 0; }
.command-item-title { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
.command-item-desc { font-size: 0.75rem; color: var(--text-muted); }

.command-item-shortcut {
    display: flex;
    gap: 0.25rem;
}

.command-palette-footer {
    padding: 0.6rem 1.25rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.72rem;
    color: var(--text-muted);
}

.command-palette-footer kbd {
    padding: 0.15rem 0.4rem;
    background: var(--bg-tertiary);
    border-radius: 3px;
    font-family: var(--font-mono);
    font-weight: 600;
    margin: 0 0.15rem;
}

/* ========================================
   KEYBOARD SHORTCUTS MODAL
   ======================================== */
.shortcuts-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: var(--z-modal);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.shortcuts-modal-overlay.show { display: flex; }

.shortcuts-modal {
    width: 100%;
    max-width: 500px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
}

.shortcuts-modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.shortcuts-modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.shortcuts-modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--text-muted);
    padding: 0.25rem;
    border-radius: 6px;
    transition: background 0.15s;
}

.shortcuts-modal-close:hover { background: var(--bg-secondary); }

.shortcuts-modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.shortcut-group {
    margin-bottom: 1.5rem;
}

.shortcut-group:last-child { margin-bottom: 0; }

.shortcut-group-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 0.75rem;
}

.shortcut-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.shortcut-desc { font-size: 0.88rem; color: var(--text-primary); }

.shortcut-keys { display: flex; gap: 0.25rem; }

.shortcut-keys kbd {
    padding: 0.25rem 0.5rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: 4px;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    box-shadow: 0 1px 0 var(--border);
}

/* ========================================
   WELCOME BANNER
   ======================================== */
.welcome-banner {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 1.5rem 2rem;
    border-radius: var(--radius-xl);
    margin-bottom: var(--space-xl);
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
    border-radius: 50%;
}

.welcome-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.welcome-text h2 {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
}

.welcome-text p {
    font-size: 0.9rem;
    opacity: 0.9;
}

.welcome-stats {
    display: flex;
    gap: 1.5rem;
}

.welcome-stat {
    text-align: center;
}

.welcome-stat-value {
    font-size: 1.75rem;
    font-weight: 900;
    font-family: var(--font-display);
    line-height: 1;
}

.welcome-stat-label {
    font-size: 0.72rem;
    opacity: 0.85;
    margin-top: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ========================================
   CONNECTION STATUS
   ======================================== */
.connection-status {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    padding: 0.5rem 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--shadow-lg);
    z-index: var(--z-toast);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s;
}

.connection-status.show {
    opacity: 1;
    transform: translateY(0);
}

.connection-status.online { border-color: #10b981; }
.connection-status.offline { border-color: #ef4444; background: #fef2f2; color: #dc2626; }

.connection-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 2s infinite;
}

.connection-status.offline .connection-dot {
    background: #ef4444;
    animation: none;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 1024px) {
    .sidebar { 
        position: fixed; 
        left: 0; 
        top: 0; 
        transform: translateX(-100%); 
    }
    .sidebar.open { 
        transform: translateX(0); 
        box-shadow: 8px 0 32px rgba(0,0,0,0.3); 
    }
    .main { 
        padding: var(--space-md); 
    }
    #mobileMenuBtn { display: block; }
    .user-name, .user-role { display: none; }
    .top-bar { padding: 0.6rem 1rem; }
    .page-title-area h1 { font-size: 1.1rem; }
}

@media (max-width: 640px) {
    .top-bar-right { gap: 0.35rem; }
    .quick-create-btn,
    .theme-toggle,
    .notification-btn { width: 36px; height: 36px; }
    .notification-dropdown { width: calc(100vw - 2rem); right: -1rem; }
    .welcome-stats { display: none; }
    .breadcrumb { display: none; }
}

/* ========================================
   ALERT STYLES
   ======================================== */
@keyframes fadeIn { 
    from { opacity: 0; transform: translateY(-10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
.alert { animation: fadeIn 0.3s ease; }

/* ========================================
   PRINT STYLES
   ======================================== */
@media print {
    .sidebar, .top-bar, .connection-status, .command-palette-overlay { display: none !important; }
    .main { padding: 0; }
}
</style>
</head>
<body>

<div class="dashboard">
    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99; backdrop-filter:blur(4px);" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-toggle" onclick="toggleSidebarCollapse()" title="Collapse sidebar">
            <span id="sidebarToggleIcon">◀</span>
        </div>
        
        <div class="sidebar-header">
            <h2>🎓 FKIP</h2>
            <p>Admin Panel UNIMOF</p>
        </div>
        
        <!-- Sidebar Search -->
        <div class="sidebar-search">
            <input type="text" class="sidebar-search-input" id="sidebarSearch" placeholder="Cari menu... (Ctrl+K)">
        </div>
        
        <ul class="sidebar-menu" id="sidebarMenu">
            <!-- Pinned Section -->
            <div class="menu-group" id="pinnedGroup">
                <div class="menu-label">
                    <span>📌 Favorit</span>
                </div>
                <div class="menu-items" id="pinnedItems">
                    <?php foreach ($pinned_menus as $pinned): ?>
                        <?php if (isset($menu_groups[array_key_first($menu_groups)][$pinned]) || true): ?>
                            <?php
                            $found_item = null;
                            foreach ($menu_groups as $items) {
                                if (isset($items[$pinned])) {
                                    $found_item = $items[$pinned];
                                    break;
                                }
                            }
                            if ($found_item):
                            ?>
                            <li>
                                <a href="<?= $found_item[2] ?>" class="<?= $active === $pinned ? 'active pinned' : 'pinned' ?>" data-menu="<?= $pinned ?>">
                                    <span class="menu-icon"><?= $found_item[0] ?></span>
                                    <span class="menu-text">
                                        <span class="menu-name"><?= $found_item[1] ?></span>
                                        <span class="pin-icon" onclick="event.preventDefault(); event.stopPropagation(); togglePin('<?= $pinned ?>')">📌</span>
                                    </span>
                                </a>
                            </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="sidebar-divider"></div>
            
            <?php foreach ($menu_groups as $group_name => $items): ?>
                <div class="menu-group" data-group="<?= strtolower($group_name) ?>">
                    <div class="menu-label" onclick="toggleMenuGroup(this)">
                        <span><?= $group_name ?></span>
                        <span class="collapse-icon">▼</span>
                    </div>
                    <div class="menu-items">
                        <?php foreach ($items as $key => $m): 
                            $notif_count = 0;
                            if ($key === 'kontak') $notif_count = $notifications['kontak_baru'];
                            elseif ($key === 'berita') $notif_count = $notifications['berita_draft'];
                            elseif ($key === 'agenda') $notif_count = $notifications['agenda_soon'];
                            elseif ($key === 'beasiswa') $notif_count = $notifications['beasiswa_deadline'];
                        ?>
                        <li>
                            <a href="<?= $m[2] ?>" class="<?= $active === $key ? 'active' : '' ?>" 
                               data-menu="<?= $key ?>" 
                               data-search="<?= strtolower($m[1] . ' ' . ($m[3] ?? '')) ?>"
                               title="<?= $m[3] ?? '' ?>">
                                <span class="menu-icon"><?= $m[0] ?></span>
                                <span class="menu-text">
                                    <span class="menu-name"><?= $m[1] ?></span>
                                    <?php if ($notif_count > 0): ?>
                                        <span class="notification-badge"><?= $notif_count ?></span>
                                    <?php endif; ?>
                                    <span class="pin-icon" onclick="event.preventDefault(); event.stopPropagation(); togglePin('<?= $key ?>')">📌</span>
                                </span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </ul>
        
        <div class="sidebar-footer">
            <ul class="sidebar-menu" style="padding: 0;">
                <li>
                    <a href="pengaturan.php" class="<?= $active === 'pengaturan' ? 'active' : '' ?>">
                        <span class="menu-icon">⚙️</span>
                        <span class="menu-text"><span class="menu-name">Pengaturan</span></span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="logout">
                        <span class="menu-icon">🚪</span>
                        <span class="menu-text"><span class="menu-name">Keluar</span></span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <button onclick="toggleSidebar()" id="mobileMenuBtn" aria-label="Toggle menu">☰</button>
                <div class="page-title-area">
                    <h1><?= sanitize($heading) ?></h1>
                    <nav class="breadcrumb">
                        <?php foreach ($breadcrumbs as $i => $crumb): ?>
                            <?php if ($crumb[1]): ?>
                                <a href="<?= $crumb[1] ?>"><?= sanitize($crumb[0]) ?></a>
                                <span>›</span>
                            <?php else: ?>
                                <span><?= sanitize($crumb[0]) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
            
            <div class="top-bar-right">
                <!-- Quick Create -->
                <div class="quick-create-wrap">
                    <button class="quick-create-btn" onclick="toggleDropdown('quickCreateDropdown')" aria-label="Quick create" title="Buat baru">
                        ＋
                    </button>
                    <div class="quick-create-dropdown" id="quickCreateDropdown">
                        <?php foreach ($quick_create as $qc): ?>
                            <a href="<?= $qc[2] ?>" class="quick-create-item">
                                <div class="qc-icon"><?= $qc[0] ?></div>
                                <span><?= $qc[1] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Command Palette Trigger -->
                <button class="theme-toggle" onclick="openCommandPalette()" aria-label="Search" title="Pencarian cepat (Ctrl+K)">
                    🔍
                </button>

                <!-- Theme Toggle -->
                <div class="theme-toggle-wrap">
                    <button class="theme-toggle" id="themeToggle" onclick="toggleDropdown('themeDropdown')" aria-label="Toggle theme" title="Ganti tema">
                        <span class="sun-icon">☀️</span>
                        <span class="moon-icon">🌙</span>
                    </button>
                    <div class="theme-dropdown" id="themeDropdown">
                        <button class="theme-option" data-theme="light" onclick="setTheme('light')">
                            <div class="theme-preview"></div>
                            <span>☀️ Terang</span>
                        </button>
                        <button class="theme-option" data-theme="dark" onclick="setTheme('dark')">
                            <div class="theme-preview"></div>
                            <span>🌙 Gelap</span>
                        </button>
                        <button class="theme-option" data-theme="midnight" onclick="setTheme('midnight')">
                            <div class="theme-preview"></div>
                            <span>🌌 Midnight</span>
                        </button>
                        <button class="theme-option" data-theme="emerald" onclick="setTheme('emerald')">
                            <div class="theme-preview"></div>
                            <span>💚 Emerald</span>
                        </button>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="notification-wrap">
                    <button class="notification-btn" onclick="toggleDropdown('notifDropdown')" aria-label="Notifikasi">
                        🔔
                        <?php if ($total_notifications > 0): ?>
                            <span class="notification-badge"><?= $total_notifications > 9 ? '9+' : $total_notifications ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifikasi</span>
                            <button class="clear-btn" onclick="markAllRead()">Tandai dibaca</button>
                        </div>
                        <div class="notif-list">
                            <?php if ($total_notifications === 0): ?>
                                <div class="notif-empty">
                                    <div class="notif-empty-icon">🎉</div>
                                    <div class="notif-empty-text">Tidak ada notifikasi baru</div>
                                    <div class="notif-empty-sub">Semua sudah tertangani</div>
                                </div>
                            <?php else: ?>
                                <?php if ($notifications['kontak_baru'] > 0): ?>
                                <a href="kontak.php" class="notif-item">
                                    <div class="notif-icon blue">✉️</div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?= $notifications['kontak_baru'] ?> Pesan Baru</div>
                                        <div class="notif-desc">Pengunjung menunggu balasan</div>
                                        <div class="notif-time">Baru saja</div>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notifications['berita_draft'] > 0): ?>
                                <a href="berita.php?status=Draft" class="notif-item">
                                    <div class="notif-icon amber">📝</div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?= $notifications['berita_draft'] ?> Berita Draft</div>
                                        <div class="notif-desc">Perlu ditinjau sebelum publikasi</div>
                                        <div class="notif-time">Baru saja</div>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notifications['agenda_soon'] > 0): ?>
                                <a href="agenda.php" class="notif-item">
                                    <div class="notif-icon green">📆</div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?= $notifications['agenda_soon'] ?> Agenda Mendatang</div>
                                        <div class="notif-desc">Dalam 7 hari ke depan</div>
                                        <div class="notif-time">Hari ini</div>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notifications['beasiswa_deadline'] > 0): ?>
                                <a href="beasiswa.php" class="notif-item">
                                    <div class="notif-icon pink">💰</div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?= $notifications['beasiswa_deadline'] ?> Deadline Beasiswa</div>
                                        <div class="notif-desc">Dalam 14 hari ke depan</div>
                                        <div class="notif-time">Hari ini</div>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notifications['riset_pending'] > 0): ?>
                                <a href="riset.php?status=Draft" class="notif-item">
                                    <div class="notif-icon purple">🔬</div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?= $notifications['riset_pending'] ?> Riset Pending</div>
                                        <div class="notif-desc">Perlu review dan publikasi</div>
                                        <div class="notif-time">Baru saja</div>
                                    </div>
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="user-menu-wrap">
                    <div class="user-info" onclick="toggleDropdown('userDropdown')">
                        <div style="line-height: 1.2;">
                            <div class="user-name"><?= $admin_name ?></div>
                            <div class="user-role"><?= ucfirst($admin_role) ?></div>
                        </div>
                        <div class="user-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <div class="user-dropdown-name"><?= $admin_name ?></div>
                            <div class="user-dropdown-email"><?= ucfirst($admin_role) ?> • FKIP UNIMOF</div>
                        </div>
                        <a href="pengaturan.php" class="user-dropdown-item">
                            <span>👤</span>
                            <span>Profil Saya</span>
                        </a>
                        <a href="pengaturan.php" class="user-dropdown-item">
                            <span>⚙️</span>
                            <span>Pengaturan</span>
                        </a>
                        <a href="#" class="user-dropdown-item" onclick="openShortcutsModal(); return false;">
                            <span>⌨️</span>
                            <span>Keyboard Shortcuts</span>
                        </a>
                        <a href="logout.php" class="user-dropdown-item logout">
                            <span>🚪</span>
                            <span>Keluar Sistem</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Welcome Banner (dashboard only) -->
        <?php if ($active === 'dashboard'): ?>
        <div class="welcome-banner">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2><?= $greeting ?>, <?= $admin_name ?>! 👋</h2>
                    <p>Selamat datang kembali di Admin Panel FKIP UNIMOF</p>
                </div>
                <div class="welcome-stats">
                    <div class="welcome-stat">
                        <div class="welcome-stat-value"><?= $total_notifications ?></div>
                        <div class="welcome-stat-label">Notifikasi</div>
                    </div>
                    <div class="welcome-stat">
                        <div class="welcome-stat-value"><?= date('d M') ?></div>
                        <div class="welcome-stat-label">Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php display_flash(); ?>

<!-- Command Palette Modal -->
<div class="command-palette-overlay" id="commandPaletteOverlay" onclick="if(event.target===this)closeCommandPalette()">
    <div class="command-palette">
        <div class="command-palette-input-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" class="command-palette-input" id="commandInput" placeholder="Ketik untuk mencari menu, aksi, atau halaman..." autocomplete="off">
            <span class="command-palette-kbd">ESC</span>
        </div>
        <div class="command-palette-results" id="commandResults">
            <div class="command-group">
                <div class="command-group-label">Menu Utama</div>
                <?php foreach ($menu_groups as $group => $items): ?>
                    <?php foreach ($items as $key => $m): ?>
                    <div class="command-item" data-search="<?= strtolower($m[1] . ' ' . $group . ' ' . ($m[3] ?? '')) ?>" onclick="window.location='<?= $m[2] ?>'">
                        <div class="command-item-icon"><?= $m[0] ?></div>
                        <div class="command-item-text">
                            <div class="command-item-title"><?= $m[1] ?></div>
                            <div class="command-item-desc"><?= $group ?> • <?= $m[3] ?? '' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            <div class="command-group">
                <div class="command-group-label">Aksi Cepat</div>
                <?php foreach ($quick_create as $qc): ?>
                <div class="command-item" data-search="<?= strtolower($qc[1]) ?>" onclick="window.location='<?= $qc[2] ?>'">
                    <div class="command-item-icon"><?= $qc[0] ?></div>
                    <div class="command-item-text">
                        <div class="command-item-title"><?= $qc[1] ?></div>
                        <div class="command-item-desc">Buat baru</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="command-group">
                <div class="command-group-label">Sistem</div>
                <div class="command-item" data-search="theme dark mode" onclick="setTheme('dark'); closeCommandPalette();">
                    <div class="command-item-icon">🌙</div>
                    <div class="command-item-text">
                        <div class="command-item-title">Mode Gelap</div>
                        <div class="command-item-desc">Ganti tema ke dark mode</div>
                    </div>
                </div>
                <div class="command-item" data-search="theme light mode" onclick="setTheme('light'); closeCommandPalette();">
                    <div class="command-item-icon">☀️</div>
                    <div class="command-item-text">
                        <div class="command-item-title">Mode Terang</div>
                        <div class="command-item-desc">Ganti tema ke light mode</div>
                    </div>
                </div>
                <div class="command-item" data-search="logout keluar" onclick="window.location='logout.php'">
                    <div class="command-item-icon">🚪</div>
                    <div class="command-item-text">
                        <div class="command-item-title">Keluar</div>
                        <div class="command-item-desc">Logout dari sistem</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="command-palette-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> Navigasi</span>
            <span><kbd>↵</kbd> Pilih</span>
            <span><kbd>ESC</kbd> Tutup</span>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="shortcuts-modal-overlay" id="shortcutsModalOverlay" onclick="if(event.target===this)closeShortcutsModal()">
    <div class="shortcuts-modal">
        <div class="shortcuts-modal-header">
            <h3>⌨️ Keyboard Shortcuts</h3>
            <button class="shortcuts-modal-close" onclick="closeShortcutsModal()">✕</button>
        </div>
        <div class="shortcuts-modal-body">
            <div class="shortcut-group">
                <div class="shortcut-group-title">Navigasi</div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Buka Command Palette</span>
                    <span class="shortcut-keys"><kbd>Ctrl</kbd><kbd>K</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Dashboard</span>
                    <span class="shortcut-keys"><kbd>G</kbd><kbd>D</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Berita</span>
                    <span class="shortcut-keys"><kbd>G</kbd><kbd>B</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Kontak</span>
                    <span class="shortcut-keys"><kbd>G</kbd><kbd>C</kbd></span>
                </div>
            </div>
            <div class="shortcut-group">
                <div class="shortcut-group-title">Aksi</div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Buat Baru</span>
                    <span class="shortcut-keys"><kbd>N</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Simpan (di form)</span>
                    <span class="shortcut-keys"><kbd>Ctrl</kbd><kbd>S</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Toggle Sidebar</span>
                    <span class="shortcut-keys"><kbd>[</kbd></span>
                </div>
            </div>
            <div class="shortcut-group">
                <div class="shortcut-group-title">Tampilan</div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Mode Gelap/Terang</span>
                    <span class="shortcut-keys"><kbd>Ctrl</kbd><kbd>Shift</kbd><kbd>D</kbd></span>
                </div>
                <div class="shortcut-row">
                    <span class="shortcut-desc">Tampilkan Shortcuts</span>
                    <span class="shortcut-keys"><kbd>?</kbd></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Connection Status -->
<div class="connection-status online" id="connectionStatus">
    <div class="connection-dot"></div>
    <span id="connectionText">Online</span>
</div>

<script>
// ========================================
// SUPER EXTREME ULTIMATE ADMIN JS
// ========================================

// ===== THEME MANAGEMENT =====
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('admin-theme', theme);
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === theme);
    });
    closeAllDropdowns();
}

// Load saved theme
(function() {
    const saved = localStorage.getItem('admin-theme') || 'light';
    setTheme(saved);
})();

// ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
}

function toggleSidebarCollapse() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('sidebarToggleIcon');
    icon.textContent = sidebar.classList.contains('collapsed') ? '▶' : '◀';
    localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
}

// Load sidebar state
(function() {
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        document.getElementById('sidebar').classList.add('collapsed');
        document.getElementById('sidebarToggleIcon').textContent = '▶';
    }
})();

// ===== MENU GROUP COLLAPSE =====
function toggleMenuGroup(label) {
    const group = label.closest('.menu-group');
    group.classList.toggle('collapsed');
}

// ===== SIDEBAR SEARCH =====
document.getElementById('sidebarSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#sidebarMenu a[data-search]').forEach(link => {
        const match = !q || link.dataset.search.includes(q);
        link.closest('li').style.display = match ? '' : 'none';
    });
    // Show all groups if searching
    document.querySelectorAll('.menu-group').forEach(g => {
        if (q) g.classList.remove('collapsed');
    });
});

// ===== DROPDOWN MANAGEMENT =====
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const isShown = dropdown.classList.contains('show');
    closeAllDropdowns();
    if (!isShown) dropdown.classList.add('show');
}

function closeAllDropdowns() {
    document.querySelectorAll('.quick-create-dropdown, .theme-dropdown, .notification-dropdown, .user-dropdown')
        .forEach(d => d.classList.remove('show'));
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.quick-create-wrap, .theme-toggle-wrap, .notification-wrap, .user-menu-wrap')) {
        closeAllDropdowns();
    }
});

// ===== COMMAND PALETTE =====
function openCommandPalette() {
    document.getElementById('commandPaletteOverlay').classList.add('show');
    setTimeout(() => document.getElementById('commandInput').focus(), 100);
}

function closeCommandPalette() {
    document.getElementById('commandPaletteOverlay').classList.remove('show');
    document.getElementById('commandInput').value = '';
    filterCommandResults('');
}

document.getElementById('commandInput')?.addEventListener('input', function() {
    filterCommandResults(this.value.toLowerCase());
});

function filterCommandResults(query) {
    document.querySelectorAll('#commandResults .command-item').forEach(item => {
        const match = !query || item.dataset.search.includes(query);
        item.style.display = match ? '' : 'none';
    });
}

// ===== KEYBOARD SHORTCUTS =====
function openShortcutsModal() {
    document.getElementById('shortcutsModalOverlay').classList.add('show');
    closeAllDropdowns();
}

function closeShortcutsModal() {
    document.getElementById('shortcutsModalOverlay').classList.remove('show');
}

// Global keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ignore if in input/textarea
    const tag = document.activeElement.tagName;
    const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable;
    
    // Ctrl+K - Command Palette
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openCommandPalette();
    }
    
    // Escape - Close modals
    if (e.key === 'Escape') {
        closeCommandPalette();
        closeShortcutsModal();
        closeAllDropdowns();
    }
    
    // ? - Show shortcuts
    if (e.key === '?' && !isInput) {
        e.preventDefault();
        openShortcutsModal();
    }
    
    // [ - Toggle sidebar
    if (e.key === '[' && !isInput) {
        toggleSidebarCollapse();
    }
    
    // Ctrl+Shift+D - Toggle dark mode
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        const current = document.documentElement.getAttribute('data-theme');
        setTheme(current === 'light' ? 'dark' : 'light');
    }
    
    // G then D - Dashboard (vim-style)
    if (e.key === 'g' && !isInput) {
        window._gPressed = Date.now();
    }
    if (window._gPressed && Date.now() - window._gPressed < 500) {
        if (e.key === 'd' && !isInput) { window.location = 'dashboard.php'; window._gPressed = null; }
        if (e.key === 'b' && !isInput) { window.location = 'berita.php'; window._gPressed = null; }
        if (e.key === 'c' && !isInput) { window.location = 'kontak.php'; window._gPressed = null; }
    }
});

// ===== NOTIFICATIONS =====
function markAllRead() {
    showToast('Success', 'Semua notifikasi ditandai dibaca', 'success');
    // In production, this would make an API call
}

// ===== PIN MENU =====
function togglePin(menuKey) {
    let pinned = JSON.parse(localStorage.getItem('pinned-menus') || '[]');
    if (pinned.includes(menuKey)) {
        pinned = pinned.filter(m => m !== menuKey);
        showToast('Info', 'Menu dihapus dari favorit', 'info');
    } else {
        pinned.push(menuKey);
        showToast('Success', 'Menu ditambahkan ke favorit', 'success');
    }
    localStorage.setItem('pinned-menus', JSON.stringify(pinned));
    // In production, reload the pinned section
}

// ===== CONNECTION STATUS =====
function updateConnectionStatus() {
    const status = document.getElementById('connectionStatus');
    const text = document.getElementById('connectionText');
    if (navigator.onLine) {
        status.className = 'connection-status online show';
        text.textContent = 'Online';
        setTimeout(() => status.classList.remove('show'), 3000);
    } else {
        status.className = 'connection-status offline show';
        text.textContent = 'Offline';
    }
}

window.addEventListener('online', updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);

// ===== TOAST HELPER =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ===== RECENT PAGES TRACKING =====
(function() {
    const currentPage = window.location.pathname.split('/').pop();
    let recent = JSON.parse(localStorage.getItem('recent-pages') || '[]');
    recent = recent.filter(p => p.url !== currentPage);
    recent.unshift({ url: currentPage, title: document.title, time: Date.now() });
    recent = recent.slice(0, 10);
    localStorage.setItem('recent-pages', JSON.stringify(recent));
})();

// ===== INITIALIZATION =====
console.log('%c🎓 FKIP UNIMOF Admin Panel', 'color: #0a6847; font-size: 20px; font-weight: bold;');
console.log('%cSuper Extreme Ultimate Edition', 'color: #64748b; font-size: 12px;');
console.log('%cShortcuts: Ctrl+K (Search), ? (Help), [ (Sidebar)', 'color: #64748b;');
</script>