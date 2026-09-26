<?php
// admin/includes/header.php - Layout bersama panel admin (EXTREME MULTIMATE VERSION)
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

$menu_groups = [
    'Utama' => [
        'dashboard' => ['📊', 'Dashboard', 'dashboard.php', 'Overview & statistik']
    ],
    'Konten' => [
        'berita' => ['📰', 'Kelola Berita', 'berita.php', 'CRUD artikel'],
        'agenda' => ['📅', 'Agenda & Acara', 'agenda.php', 'Jadwal kegiatan'],
        'download' => ['📥', 'Download Center', 'download.php', 'Kelola file'],
    ],
    'Akademik' => [
        'prodi' => ['🎓', 'Program Studi', 'program.php', 'Kelola data prodi'],
        'dosen' => ['👨‍🏫', 'Data Dosen', 'dosen.php', 'Profil pengajar'],
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
<style>
/* === EXTREME MULTIMATE ADMIN STYLES === */
:root {
    --sidebar-bg: linear-gradient(180deg, #0a6847 0%, #064e34 100%);
    --sidebar-width: 280px;
    --primary-glow: rgba(10, 104, 71, 0.4);
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.6);
    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
}
[data-theme="dark"] {
    --glass-bg: rgba(15, 23, 42, 0.85);
    --glass-border: rgba(255, 255, 255, 0.08);
    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background: #f8fafc; 
    color: #0f172a; 
    overflow-x: hidden; 
    -webkit-font-smoothing: antialiased;
}
[data-theme="dark"] body { background: #0f172a; color: #f1f5f9; }

/* Layout */
.dashboard { display: flex; min-height: 100vh; }

/* Sidebar Premium */
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
    box-shadow: 4px 0 24px rgba(0,0,0,0.15); 
    z-index: 100; 
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}
/* Custom Scrollbar for Sidebar */
.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.4); }

.sidebar-header { 
    padding: 1.5rem; 
    border-bottom: 1px solid rgba(255,255,255,0.1); 
    background: rgba(0,0,0,0.15); 
    backdrop-filter: blur(10px);
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
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
    animation: rotateGlow 15s linear infinite;
}
@keyframes rotateGlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.sidebar-header h2 { 
    font-size: 1.25rem; 
    display: flex; 
    align-items: center; 
    gap: 0.6rem; 
    font-weight: 800; 
    letter-spacing: -0.02em;
    position: relative;
    z-index: 1;
}
.sidebar-header p { 
    font-size: 0.75rem; 
    opacity: 0.7; 
    margin-top: 0.25rem; 
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.sidebar-menu { list-style: none; padding: 1rem 0.75rem; }
.menu-label { 
    font-size: 0.65rem; 
    text-transform: uppercase; 
    letter-spacing: 0.1em; 
    color: rgba(255,255,255,0.4); 
    font-weight: 700; 
    padding: 1rem 0.75rem 0.5rem; 
    margin-top: 0.5rem; 
}
.sidebar-menu li { margin-bottom: 0.25rem; }
.sidebar-menu a { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    padding: 0.75rem 1rem; 
    color: rgba(255,255,255,0.75); 
    text-decoration: none; 
    font-size: 0.88rem; 
    font-weight: 500; 
    border-radius: 10px; 
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
    position: relative; 
    overflow: hidden;
}
.sidebar-menu a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: #fff;
    border-radius: 0 4px 4px 0;
    transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-menu a:hover { 
    background: rgba(255,255,255,0.1); 
    color: #fff; 
    transform: translateX(4px); 
}
.sidebar-menu a.active { 
    background: rgba(255,255,255,0.15); 
    color: #fff; 
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.sidebar-menu a.active::before { height: 60%; }
.sidebar-menu .menu-icon { font-size: 1.1rem; width: 24px; text-align: center; flex-shrink: 0; }
.sidebar-menu .menu-text { flex: 1; display: flex; justify-content: space-between; align-items: center; }
.sidebar-menu .menu-tooltip { 
    font-size: 0.7rem; 
    opacity: 0; 
    transform: translateX(-5px); 
    transition: all 0.3s; 
}
.sidebar-menu a:hover .menu-tooltip { opacity: 0.6; transform: translateX(0); }

/* Main Content */
.main { 
    flex: 1; 
    padding: 2rem; 
    overflow-x: hidden; 
    max-width: calc(100vw - var(--sidebar-width)); 
    transition: max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}

/* Top Bar Glassmorphism */
.top-bar { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 2rem; 
    padding: 1rem 1.5rem; 
    background: var(--glass-bg); 
    backdrop-filter: blur(16px); 
    -webkit-backdrop-filter: blur(16px); 
    border: 1px solid var(--glass-border); 
    border-radius: 16px; 
    box-shadow: var(--glass-shadow); 
    position: sticky; 
    top: 1rem; 
    z-index: 90; 
    flex-wrap: wrap; 
    gap: 1rem; 
    transition: all 0.3s ease;
}
.top-bar-left h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; letter-spacing: -0.02em; }
.breadcrumb { display: flex; gap: 0.5rem; font-size: 0.8rem; color: #64748b; align-items: center; font-weight: 500; }
.breadcrumb a { color: #64748b; text-decoration: none; transition: color 0.2s; }
.breadcrumb a:hover { color: #0a6847; }
.breadcrumb span { color: #cbd5e1; }

.top-bar-right { display: flex; gap: 0.75rem; align-items: center; }

/* Notification Dropdown */
.notification-wrap { position: relative; }
.notification-btn { 
    width: 42px; 
    height: 42px; 
    background: #fff; 
    border: 1px solid #e2e8f0; 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.04); 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    font-size: 1.1rem; 
}
[data-theme="dark"] .notification-btn { background: #1e293b; border-color: #334155; color: #f1f5f9; }
.notification-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 8px 16px rgba(0,0,0,0.08); 
}
.notification-badge { 
    position: absolute; 
    top: -4px; 
    right: -4px; 
    background: #ef4444; 
    color: #fff; 
    font-size: 0.65rem; 
    font-weight: 800; 
    min-width: 18px; 
    height: 18px; 
    border-radius: 9px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    padding: 0 4px; 
    border: 2px solid #fff; 
    animation: pulseBadge 2s infinite;
}
@keyframes pulseBadge {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.notification-dropdown { 
    position: absolute; 
    top: calc(100% + 8px); 
    right: 0; 
    width: 320px; 
    background: #fff; 
    border: 1px solid #e2e8f0; 
    border-radius: 16px; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
    opacity: 0; 
    visibility: hidden; 
    transform: translateY(-10px) scale(0.98); 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    z-index: 200; 
    overflow: hidden; 
}
[data-theme="dark"] .notification-dropdown { background: #1e293b; border-color: #334155; }
.notification-dropdown.show { 
    opacity: 1; 
    visibility: visible; 
    transform: translateY(0) scale(1); 
}
.notif-header { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid #f1f5f9; 
    font-weight: 700; 
    font-size: 0.9rem; 
    display: flex; 
    justify-content: space-between; 
}
[data-theme="dark"] .notif-header { border-color: #334155; }
.notif-item { 
    padding: 1rem 1.25rem; 
    border-bottom: 1px solid #f1f5f9; 
    display: flex; 
    gap: 0.75rem; 
    align-items: flex-start; 
    transition: background 0.2s; 
    cursor: pointer; 
    text-decoration: none; 
    color: inherit; 
}
[data-theme="dark"] .notif-item { border-color: #334155; }
.notif-item:hover { background: #f8fafc; }
[data-theme="dark"] .notif-item:hover { background: #334155; }
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
.notif-icon.msg { background: #dbeafe; color: #2563eb; }
.notif-icon.draft { background: #fef3c7; color: #d97706; }
.notif-content h4 { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.15rem; }
.notif-content p { font-size: 0.75rem; color: #64748b; line-height: 1.4; }

/* User Info */
.user-info { 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
    background: #fff; 
    padding: 0.4rem 0.4rem 0.4rem 1rem; 
    border-radius: 999px; 
    border: 1px solid #e2e8f0; 
    cursor: pointer; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}
[data-theme="dark"] .user-info { background: #1e293b; border-color: #334155; }
.user-info:hover { 
    box-shadow: 0 8px 24px rgba(0,0,0,0.08); 
    transform: translateY(-2px); 
    border-color: #cbd5e1;
}
.user-avatar { 
    width: 36px; 
    height: 36px; 
    background: linear-gradient(135deg, #0a6847, #16a34a); 
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

/* Re-use existing styles with minor tweaks for consistency */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: #fff; padding: 1.5rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 1rem; border: 1px solid #f1f5f9; transition: all 0.3s; position: relative; overflow: hidden; }
[data-theme="dark"] .stat-card { background: #1e293b; border-color: #334155; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
.stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.stat-info h3 { font-size: 1.75rem; line-height: 1; margin-bottom: 0.25rem; font-weight: 800; }
.stat-info p { color: #64748b; font-size: 0.85rem; margin-bottom: 0.25rem; }
.stat-info small { font-size: 0.75rem; font-weight: 600; }

.card { background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 1.5rem; border: 1px solid #f1f5f9; }
[data-theme="dark"] .card { background: #1e293b; border-color: #334155; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
.card-header h2 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }

.btn-sm { padding: 0.5rem 1rem; background: #0a6847; color: #fff; border: none; border-radius: 10px; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-family: inherit; font-weight: 600; transition: all 0.3s; }
.btn-sm:hover { background: #084d35; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }
.btn-sm.gray { background: #f1f5f9; color: #475569; }
[data-theme="dark"] .btn-sm.gray { background: #334155; color: #cbd5e1; }
.btn-sm.gray:hover { background: #e2e8f0; }

/* Responsive */
@media (max-width: 1024px) {
    .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,0.2); }
    .main { max-width: 100vw; padding: 1rem; }
    .top-bar { padding: 0.75rem 1rem; }
    .notification-dropdown { width: 280px; right: -1rem; }
}
@media (max-width: 640px) {
    .stats-grid { grid-template-columns: 1fr; }
    .top-bar-left h1 { font-size: 1.25rem; }
}

/* Utility Animations */
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
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
                    <a href="<?= $m[2] ?>" class="<?= $active === $key ? 'active' : '' ?>">
                        <span class="menu-icon"><?= $m[0] ?></span>
                        <span class="menu-text">
                            <?= $m[1] ?>
                        </span>
                        <?php if ($key === 'kontak' && $new_msgs > 0): ?>
                            <span class="notification-badge" style="position:static; min-width:20px; height:20px; font-size:0.7rem; border:none;"><?= $new_msgs ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 1rem 0.75rem;"></div>
            <li>
                <a href="logout.php" style="color: #fca5a5;">
                    <span class="menu-icon">🚪</span>
                    <span class="menu-text">Keluar Sistem</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main">
        <div class="top-bar">
            <div class="top-bar-left">
                <!-- Mobile Toggle -->
                <button onclick="toggleSidebar()" style="display:none; background:none; border:none; font-size:1.5rem; cursor:pointer; margin-right:0.75rem; color: inherit;" id="mobileMenuBtn">☰</button>
                
                <h1><?= sanitize($heading) ?></h1>
                <nav class="breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($crumb[1]): ?>
                            <a href="<?= $crumb[1] ?>"><?= sanitize($crumb[0]) ?></a>
                            <span>›</span>
                        <?php else: ?>
                            <span style="color: #0f172a; font-weight: 600;"><?= sanitize($crumb[0]) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            
            <div class="top-bar-right">
                <!-- Notification Dropdown -->
                <div class="notification-wrap">
                    <button class="notification-btn" id="notifBtn" aria-label="Notifikasi">
                        🔔
                        <?php if ($total_notifications > 0): ?>
                            <span class="notification-badge"><?= $total_notifications > 9 ? '9+' : $total_notifications ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span>Notifikasi</span>
                            <span style="font-size:0.75rem; color:#64748b; font-weight:500; cursor:pointer;" onclick="clearNotifs()">Tandai semua dibaca</span>
                        </div>
                        <?php if ($total_notifications === 0): ?>
                            <div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.85rem;">
                                🎉 Tidak ada notifikasi baru
                            </div>
                        <?php else: ?>
                            <?php if ($new_msgs > 0): ?>
                            <a href="kontak.php" class="notif-item">
                                <div class="notif-icon msg">✉️</div>
                                <div class="notif-content">
                                    <h4><?= $new_msgs ?> Pesan Baru</h4>
                                    <p>Pengunjung menunggu balasan Anda</p>
                                </div>
                            </a>
                            <?php endif; ?>
                            <?php if ($draft_count > 0): ?>
                            <a href="berita.php" class="notif-item">
                                <div class="notif-icon draft">📝</div>
                                <div class="notif-content">
                                    <h4><?= $draft_count ?> Berita Draft</h4>
                                    <p>Perlu ditinjau sebelum dipublikasi</p>
                                </div>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
                    <div style="line-height: 1.2;">
                        <div style="font-size:0.85rem; font-weight:700; color:#0f172a;"><?= $admin_name ?></div>
                        <div style="font-size:0.7rem; color:#64748b; font-weight:500;"><?= ucfirst($_SESSION['admin_role'] ?? 'admin') ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php display_flash(); ?>