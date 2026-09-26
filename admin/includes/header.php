<?php
// admin/includes/header.php - EXTREME MULTIMATE SIDEBAR
require_login();

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$active = $active_menu ?? 'dashboard';
$heading = $page_heading ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [['Dashboard', null]];

$new_msgs = 0; $draft_count = 0;
try { 
    $new_msgs = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Baru'")->fetchColumn();
    $draft_count = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
} catch (Exception $e) {}
$total_notifications = $new_msgs + $draft_count;

// Menu dengan ikon yang LENGKAP dan proper
$menu_groups = [
    'Utama' => [
        'dashboard' => ['📊', 'Dashboard', 'dashboard.php', 'Overview & statistik']
    ],
    'Konten' => [
        'berita' => ['📰', 'Kelola Berita', 'berita.php', 'CRUD artikel'],
        'agenda' => ['📆', 'Agenda & Acara', 'agenda.php', 'Jadwal kegiatan'],
        'download' => ['📥', 'Download Center', 'download.php', 'Kelola file'],
    ],
    'Akademik' => [
        'prodi' => ['🎓', 'Program Studi', 'program.php', 'Kelola data prodi'],
        'dosen' => ['👨‍', 'Data Dosen', 'dosen.php', 'Profil pengajar'],
        'riset' => ['🔬', 'Riset & Jurnal', 'riset.php', 'Publikasi ilmiah'],
        'jurnal' => ['📚', 'Jurnal Ilmiah', 'jurnal.php', 'Kelola jurnal'],
        'fasilitas' => ['🏢', 'Fasilitas & Lab', 'fasilitas.php', 'Sarana prasarana'],
        'akreditasi'=> ['🏅', 'Akreditasi', 'akreditasi.php', 'Data akreditasi'],
    ],
    'Kemahasiswaan' => [
        'prestasi' => ['🏆', 'Prestasi', 'prestasi.php', 'Pencapaian mahasiswa'],
        'alumni' => ['🎓', 'Data Alumni', 'alumni.php', 'Jejaring lulusan'],
        'beasiswa' => ['💰', 'Beasiswa', 'beasiswa.php', 'Program beasiswa'],
    ],
    'Lainnya' => [
        'kerjasama' => ['🤝', 'Kerjasama', 'kerjasama.php', 'Mitra institusi'],
        'kontak' => ['✉️', 'Pesan Masuk', 'kontak.php', 'Inbox pengunjung'],
        'pengaturan'=> ['⚙️', 'Pengaturan', 'pengaturan.php', 'Konfigurasi'],
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= sanitize($heading) ?> - Admin FKIP UNIMOF</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('admin/assets/css/admin-style.css') ?>">
<style>
/* === EXTREME MULTIMATE SIDEBAR STYLES === */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f8fafc;
    --sidebar-bg: linear-gradient(180deg, #0a6847 0%, #064e34 100%);
    --sidebar-width: 280px;
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.6);
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --primary: #0a6847;
    --primary-light: #16a34a;
    --shadow-glow: 0 0 40px rgba(10,104,71,0.3);
}

[data-theme="dark"] {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --glass-bg: rgba(15, 23, 42, 0.85);
    --glass-border: rgba(255, 255, 255, 0.08);
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --text-muted: #94a3b8;
    --border: #334155;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background: var(--bg-secondary); 
    color: var(--text-primary); 
    overflow-x: hidden;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.dashboard { display: flex; min-height: 100vh; }

/* ===== SIDEBAR EXTREME ===== */
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
    z-index: 100; 
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(20px);
}

.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { 
    background: rgba(255,255,255,0.2); 
    border-radius: 10px;
    transition: background 0.3s;
}
.sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.4); }

/* Sidebar Header dengan Glassmorphism */
.sidebar-header { 
    padding: 2rem 1.5rem; 
    border-bottom: 1px solid rgba(255,255,255,0.1); 
    background: rgba(0,0,0,0.2); 
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}

.sidebar-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    animation: rotateGlow 20s linear infinite;
    pointer-events: none;
}

@keyframes rotateGlow { 
    from { transform: rotate(0deg); } 
    to { transform: rotate(360deg); } 
}

.sidebar-header h2 { 
    font-size: 1.5rem; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    font-weight: 800; 
    letter-spacing: -0.02em;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.sidebar-header p { 
    font-size: 0.75rem; 
    opacity: 0.8; 
    margin-top: 0.25rem; 
    font-weight: 500;
    position: relative;
    z-index: 1;
}

/* Sidebar Menu */
.sidebar-menu { 
    list-style: none; 
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.menu-label { 
    font-size: 0.7rem; 
    text-transform: uppercase; 
    letter-spacing: 0.15em; 
    color: rgba(255,255,255,0.4); 
    font-weight: 700; 
    padding: 1rem 1rem 0.5rem; 
    margin-top: 0.5rem;
    transition: color 0.3s;
}

.sidebar-menu li { 
    margin-bottom: 0.15rem; 
}

.sidebar-menu a { 
    display: flex; 
    align-items: center; 
    gap: 0.875rem; 
    padding: 0.875rem 1rem; 
    color: rgba(255,255,255,0.75); 
    text-decoration: none; 
    font-size: 0.9rem; 
    font-weight: 500; 
    border-radius: 12px; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    position: relative; 
    overflow: hidden;
    border: 1px solid transparent;
}

/* Icon container dengan efek glow */
.sidebar-menu .menu-icon { 
    font-size: 1.25rem; 
    width: 28px; 
    text-align: center; 
    flex-shrink: 0;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    transition: transform 0.3s;
}

/* Text area */
.sidebar-menu .menu-text { 
    flex: 1; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
}

/* Hover effects */
.sidebar-menu a:hover { 
    background: rgba(255,255,255,0.12); 
    color: #fff; 
    transform: translateX(6px);
    border-color: rgba(255,255,255,0.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.sidebar-menu a:hover .menu-icon {
    transform: scale(1.15) rotate(-5deg);
}

/* Active state dengan gradient */
.sidebar-menu a.active { 
    background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.1) 100%); 
    color: #fff; 
    font-weight: 600;
    border-color: rgba(255,255,255,0.2);
    box-shadow: 0 4px 16px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.2);
}

.sidebar-menu a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 60%;
    background: linear-gradient(180deg, #fff 0%, rgba(255,255,255,0.6) 100%);
    border-radius: 0 4px 4px 0;
    box-shadow: 0 0 10px rgba(255,255,255,0.5);
}

/* Tooltip */
.sidebar-menu .menu-tooltip { 
    font-size: 0.7rem; 
    opacity: 0; 
    transform: translateX(-10px); 
    transition: all 0.3s;
    color: rgba(255,255,255,0.5);
}

.sidebar-menu a:hover .menu-tooltip { 
    opacity: 0.7; 
    transform: translateX(0); 
}

/* Badge notification di menu */
.notification-badge {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff; 
    font-size: 0.7rem; 
    font-weight: 700; 
    min-width: 22px; 
    height: 22px; 
    border-radius: 999px; 
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

/* Separator */
.sidebar-divider {
    height: 1px; 
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.2) 50%, transparent 100%);
    margin: 1.5rem 1rem;
}

/* Logout button */
.sidebar-menu a.logout {
    color: #fca5a5;
    margin-top: 1rem;
    border: 1px solid rgba(252,165,165,0.2);
}

.sidebar-menu a.logout:hover {
    background: rgba(252,165,165,0.15);
    border-color: rgba(252,165,165,0.4);
    color: #fff;
}

/* Main Content */
.main { 
    flex: 1; 
    padding: 2rem; 
    overflow-x: hidden; 
    max-width: calc(100vw - var(--sidebar-width)); 
    transition: max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}

/* Top Bar */
.top-bar { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 2rem; 
    padding: 1rem 1.5rem; 
    background: var(--glass-bg); 
    backdrop-filter: blur(20px); 
    border: 1px solid var(--glass-border); 
    border-radius: 16px; 
    box-shadow: 0 8px 32px rgba(0,0,0,0.08); 
    position: sticky; 
    top: 1rem; 
    z-index: 90; 
    flex-wrap: wrap; 
    gap: 1rem; 
}

.top-bar-left h1 { 
    font-size: 1.5rem; 
    font-weight: 800; 
    margin-bottom: 0.25rem; 
    letter-spacing: -0.02em; 
    color: var(--text-primary); 
}

.breadcrumb { 
    display: flex; 
    gap: 0.5rem; 
    font-size: 0.8rem; 
    color: var(--text-muted); 
    align-items: center; 
    font-weight: 500; 
}

.breadcrumb a { 
    color: var(--text-muted); 
    text-decoration: none; 
    transition: color 0.2s; 
}

.breadcrumb a:hover { 
    color: var(--primary); 
}

.breadcrumb span { 
    color: var(--text-secondary); 
}

.top-bar-right { 
    display: flex; 
    gap: 0.75rem; 
    align-items: center; 
}

/* Theme Toggle */
.theme-toggle {
    width: 42px;
    height: 42px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 1.2rem;
}

.theme-toggle:hover {
    transform: translateY(-2px) rotate(15deg);
    box-shadow: 0 8px 16px rgba(0,0,0,0.12);
}

.theme-toggle .sun-icon { display: none; color: #f59e0b; }
.theme-toggle .moon-icon { display: block; color: #64748b; }

[data-theme="dark"] .theme-toggle .sun-icon { display: block; }
[data-theme="dark"] .theme-toggle .moon-icon { display: none; }

/* Notification */
.notification-wrap { position: relative; }

.notification-btn { 
    width: 42px; 
    height: 42px; 
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
    transition: all 0.3s; 
    font-size: 1.1rem; 
    color: var(--text-primary);
}

.notification-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 8px 16px rgba(0,0,0,0.12); 
}

.notification-dropdown { 
    position: absolute; 
    top: calc(100% + 8px); 
    right: 0; 
    width: 320px; 
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: 16px; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
    opacity: 0; 
    visibility: hidden; 
    transform: translateY(-10px) scale(0.98); 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    z-index: 200; 
    overflow: hidden; 
}

.notification-dropdown.show { 
    opacity: 1; 
    visibility: visible; 
    transform: translateY(0) scale(1); 
}

.notif-header { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid var(--border); 
    font-weight: 700; 
    font-size: 0.9rem; 
    display: flex; 
    justify-content: space-between;
    color: var(--text-primary);
}

.notif-item { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    gap: 0.75rem; 
    align-items: flex-start; 
    transition: background 0.2s; 
    cursor: pointer; 
    text-decoration: none; 
    color: var(--text-primary);
}

.notif-item:hover { background: var(--bg-secondary); }

/* User Info */
.user-info { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    background: var(--bg-primary); 
    padding: 0.4rem 0.4rem 0.4rem 1rem; 
    border-radius: 999px; 
    border: 1px solid var(--border); 
    cursor: pointer; 
    transition: all 0.3s; 
}

.user-info:hover { 
    box-shadow: 0 8px 16px rgba(0,0,0,0.1); 
    transform: translateY(-2px); 
    border-color: var(--text-secondary);
}

.user-avatar { 
    width: 36px; 
    height: 36px; 
    background: linear-gradient(135deg, var(--primary), var(--primary-light)); 
    color: #fff; 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 700; 
    font-size: 0.9rem; 
    flex-shrink: 0; 
    box-shadow: 0 2px 8px rgba(10,104,71,0.3);
}

/* Responsive */
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
        max-width: 100vw; 
        padding: 1rem; 
    }
}

/* Alert animation */
@keyframes fadeIn { 
    from { opacity: 0; transform: translateY(-10px); } 
    to { opacity: 1; transform: translateY(0); } 
}
.alert { animation: fadeIn 0.3s ease; }
</style>
</head>
<body>

<div class="dashboard">
    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99; backdrop-filter:blur(4px);" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>🎓 FKIP ADMIN</h2>
            <p>Universitas Muhammadiyah Maumere</p>
        </div>
        <ul class="sidebar-menu">
            <?php foreach ($menu_groups as $group_name => $items): ?>
                <div class="menu-label"><?= $group_name ?></div>
                <?php foreach ($items as $key => $m): ?>
                <li>
                    <a href="<?= $m[2] ?>" class="<?= $active === $key ? 'active' : '' ?>" title="<?= $m[3] ?? '' ?>">
                        <span class="menu-icon"><?= $m[0] ?></span>
                        <span class="menu-text">
                            <?= $m[1] ?>
                            <?php if ($key === 'kontak' && $new_msgs > 0): ?>
                                <span class="notification-badge"><?= $new_msgs ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            <div class="sidebar-divider"></div>
            <li>
                <a href="logout.php" class="logout">
                    <span class="menu-icon">🚪</span>
                    <span class="menu-text">Keluar Sistem</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main">
        <div class="top-bar">
            <div class="top-bar-left">
                <button onclick="toggleSidebar()" style="display:none; background:none; border:none; font-size:1.5rem; cursor:pointer; margin-right:0.75rem; color: var(--text-primary);" id="mobileMenuBtn">☰</button>
                <h1><?= sanitize($heading) ?></h1>
                <nav class="breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($crumb[1]): ?>
                            <a href="<?= $crumb[1] ?>"><?= sanitize($crumb[0]) ?></a>
                            <span>›</span>
                        <?php else: ?>
                            <span style="color: var(--text-primary); font-weight: 600;"><?= sanitize($crumb[0]) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            
            <div class="top-bar-right">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle Dark Mode" title="Ganti Tema">
                    <span class="sun-icon">☀️</span>
                    <span class="moon-icon">🌙</span>
                </button>

                <div class="notification-wrap">
                    <button class="notification-btn" id="notifBtn" aria-label="Notifikasi">
                        🔔
                        <?php if ($total_notifications > 0): ?>
                            <span class="notification-badge" style="position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; font-size:0.65rem; border:2px solid var(--bg-primary);"><?= $total_notifications > 9 ? '9+' : $total_notifications ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifikasi</span>
                            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:500; cursor:pointer;" onclick="clearNotifs()">Tandai semua dibaca</span>
                        </div>
                        <?php if ($total_notifications === 0): ?>
                            <div style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                🎉 Tidak ada notifikasi baru
                            </div>
                        <?php else: ?>
                            <?php if ($new_msgs > 0): ?>
                            <a href="kontak.php" class="notif-item">
                                <div style="width:36px;height:36px;border-radius:10px;background:#dbeafe;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1rem;">✉️</div>
                                <div>
                                    <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);"><?= $new_msgs ?> Pesan Baru</div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">Pengunjung menunggu balasan</div>
                                </div>
                            </a>
                            <?php endif; ?>
                            <?php if ($draft_count > 0): ?>
                            <a href="berita.php" class="notif-item">
                                <div style="width:36px;height:36px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:1rem;">📝</div>
                                <div>
                                    <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);"><?= $draft_count ?> Berita Draft</div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">Perlu ditinjau sebelum publikasi</div>
                                </div>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
                    <div style="line-height: 1.2;">
                        <div style="font-size:0.85rem; font-weight:700; color:var(--text-primary);"><?= $admin_name ?></div>
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight:500;"><?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php display_flash(); ?>