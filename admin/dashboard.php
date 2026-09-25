<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== STATISTIK LENGKAP =====
$total_berita = (int)$pdo->query("SELECT COUNT(*) FROM berita")->fetchColumn();
$total_prodi  = (int)$pdo->query("SELECT COUNT(*) FROM program_studi")->fetchColumn();
$total_dosen  = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$total_pesan  = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE status='Baru'")->fetchColumn();
$total_alumni = (int)$pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
$total_prestasi = (int)$pdo->query("SELECT COUNT(*) FROM prestasi")->fetchColumn();

$published_today = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = CURDATE() AND status = 'Published'")->fetchColumn();
$draft_count = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
$published_count = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();

// ===== SPARKLINE DATA (7 hari terakhir) =====
$sparkline_berita = [];
$sparkline_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $sparkline_berita[] = (int)$stmt->fetchColumn();
    $sparkline_labels[] = date('d M', strtotime($date));
}

// Trend calculation
$week_now = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$week_prev = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$trend_berita = $week_prev > 0 ? round((($week_now - $week_prev) / $week_prev) * 100, 1) : 0;

$msg_now = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$msg_prev = (int)$pdo->query("SELECT COUNT(*) FROM kontak WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$trend_pesan = $msg_prev > 0 ? round((($msg_now - $msg_prev) / $msg_prev) * 100, 1) : 0;

// ===== KATEGORI STATS =====
$kat_stats = $pdo->query("SELECT kategori, COUNT(*) as total FROM berita GROUP BY kategori ORDER BY total DESC")->fetchAll();
$kat_total = array_sum(array_column($kat_stats, 'total')) ?: 1;
$kat_labels = array_column($kat_stats, 'kategori');
$kat_data = array_column($kat_stats, 'total');

// ===== PRODI STATS =====
$prodi_stats = $pdo->query("SELECT ps.nama, COUNT(d.id) as jml_dosen FROM program_studi ps LEFT JOIN dosen d ON ps.id = d.program_studi_id WHERE ps.status = 'Aktif' GROUP BY ps.id ORDER BY jml_dosen DESC LIMIT 5")->fetchAll();

// ===== DATA TERBARU =====
$berita_terbaru = $pdo->query("SELECT * FROM berita ORDER BY created_at DESC LIMIT 4")->fetchAll();
$pesan_terbaru = $pdo->query("SELECT * FROM kontak ORDER BY created_at DESC LIMIT 4")->fetchAll();

// ===== AGENDA HARI INI =====
$agenda_hari_ini = $pdo->query("SELECT * FROM agenda WHERE tanggal_mulai <= CURDATE() AND (tanggal_selesai IS NULL OR tanggal_selesai >= CURDATE()) AND status = 'Aktif' LIMIT 3")->fetchAll();

// ===== PERFORMANCE SCORE =====
$score = 0;
if ($published_count > 0) $score += 25;
if ($total_dosen > 10) $score += 25;
if ($total_prodi >= 8) $score += 25;
if ($total_pesan == 0) $score += 25; elseif ($total_pesan < 5) $score += 15;

// ===== GREETING =====
$hour = (int)date('H');
if ($hour < 11) { $greeting = 'Selamat Pagi'; $greet_emoji = '☀️'; $greet_color = '#f59e0b'; }
elseif ($hour < 15) { $greeting = 'Selamat Siang'; $greet_emoji = '🌤️'; $greet_color = '#f97316'; }
elseif ($hour < 18) { $greeting = 'Selamat Sore'; $greet_emoji = '🌅'; $greet_color = '#ef4444'; }
else { $greeting = 'Selamat Malam'; $greet_emoji = '🌙'; $greet_color = '#6366f1'; }

// ===== QUOTE ISLAMI =====
$quotes = [
    '"Barangsiapa yang menempuh jalan untuk mencari ilmu, Allah akan memudahkan baginya jalan menuju surga." — HR. Muslim',
    '"Sebaik-baik manusia adalah yang paling bermanfaat bagi manusia lain." — HR. Ahmad',
    '"Tuntutlah ilmu dari buaian hingga ke liang lahat." — Pepatah Arab',
    '"Ilmu itu lebih baik daripada harta. Ilmu menjaga engkau dan engkau menjaga harta." — Ali bin Abi Thalib',
    '"Didiklah anak-anakmu sesuai dengan zamannya, karena mereka hidup di zaman mereka bukan pada zamanmu." — Ali bin Abi Thalib',
];
$quote = $quotes[array_rand($quotes)];

$active_menu = 'dashboard';
$page_heading = 'Dashboard';
$breadcrumbs = [['Dashboard', null]];
require __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN for Extreme Data Visualization -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- ============ WELCOME HERO ============ -->
<section class="welcome-hero" data-aos="fade-down">
    <div class="welcome-bg-pattern"></div>
    <div class="welcome-content">
        <div class="welcome-left">
            <div class="welcome-badge">
                <span class="badge-pulse"></span>
                <span>Sistem Online • <?= date('l, d F Y') ?></span>
            </div>
            <h1 class="welcome-title">
                <span class="greet-emoji"><?= $greet_emoji ?></span>
                <span class="greet-text" style="color:<?= $greet_color ?>"><?= $greeting ?>,</span>
                <span class="admin-name"><?= sanitize($_SESSION['admin_name'] ?? $_SESSION['admin_username']) ?></span>
            </h1>
            <p class="welcome-subtitle">Semoga hari Anda penuh berkah dan produktivitas. Berikut ringkasan performa FKIP UNIMOF hari ini.</p>
            <div class="welcome-quote">
                <span class="quote-icon">💎</span>
                <em><?= $quote ?></em>
            </div>
        </div>
        <div class="welcome-right">
            <div class="live-clock">
                <div class="clock-display" id="liveClock">--:--:--</div>
                <div class="clock-date" id="liveDate">--</div>
                <div class="clock-hijri" id="liveHijri">🕌 Memuat...</div>
            </div>
            <div class="command-hint">
                <kbd>Ctrl</kbd> + <kbd>K</kbd> untuk <strong>Command Palette</strong>
            </div>
        </div>
    </div>
</section>

<!-- ============ ULTIMATE STATS ============ -->
<div class="stats-ultimate">
    <!-- Card Berita -->
    <div class="stat-ultimate-card" style="--card-accent:#10b981" data-aos="fade-up">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">📰</div>
            <div class="stat-ultimate-trend <?= $trend_berita >= 0 ? 'up' : 'down' ?>">
                <?= $trend_berita >= 0 ? '↑' : '↓' ?> <?= abs($trend_berita) ?>%
            </div>
        </div>
        <div class="stat-ultimate-value">
            <span class="count-up" data-target="<?= $total_berita ?>">0</span>
        </div>
        <div class="stat-ultimate-label">Total Berita</div>
        <div class="stat-ultimate-meta">
            <span class="meta-pill green">+<?= $published_today ?> hari ini</span>
        </div>
        <div id="chartBerita" class="sparkline-chart"></div>
    </div>

    <!-- Card Prodi -->
    <div class="stat-ultimate-card" style="--card-accent:#3b82f6" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🎓</div>
            <div class="stat-ultimate-trend stable">● Active</div>
        </div>
        <div class="stat-ultimate-value">
            <span class="count-up" data-target="<?= $total_prodi ?>">0</span>
        </div>
        <div class="stat-ultimate-label">Program Studi</div>
        <div class="stat-ultimate-meta">
            <span class="meta-pill blue"><?= $total_dosen ?> dosen</span>
        </div>
        <div class="stat-ultimate-progress">
            <div class="progress-ring-mini">
                <svg viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" class="ring-bg"/>
                    <circle cx="18" cy="18" r="16" class="ring-fill" style="stroke-dasharray:<?= ($total_prodi/8) * 100 ?>, 100"/>
                </svg>
                <span><?= round(($total_prodi/8)*100) ?>%</span>
            </div>
            <small>Target 8 Prodi</small>
        </div>
    </div>

    <!-- Card Prestasi -->
    <div class="stat-ultimate-card" style="--card-accent:#f59e0b" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🏆</div>
            <div class="stat-ultimate-trend up">⭐ Top</div>
        </div>
        <div class="stat-ultimate-value">
            <span class="count-up" data-target="<?= $total_prestasi ?>">0</span>
        </div>
        <div class="stat-ultimate-label">Prestasi</div>
        <div class="stat-ultimate-meta">
            <span class="meta-pill amber"><?= $total_alumni ?> alumni</span>
        </div>
        <div class="stat-ultimate-achievements">
            <div class="achievement-orb" style="--orb-delay:0s">🥇</div>
            <div class="achievement-orb" style="--orb-delay:0.3s">🥈</div>
            <div class="achievement-orb" style="--orb-delay:0.6s">🥉</div>
            <div class="achievement-orb" style="--orb-delay:0.9s">🎖️</div>
        </div>
    </div>

    <!-- Card Pesan -->
    <div class="stat-ultimate-card <?= $total_pesan > 0 ? 'has-alert' : '' ?>" style="--card-accent:#ef4444" data-aos="fade-up" data-aos-delay="300">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">✉️</div>
            <div class="stat-ultimate-trend <?= $trend_pesan >= 0 ? 'up' : 'down' ?>">
                <?= $trend_pesan >= 0 ? '↑' : '↓' ?> <?= abs($trend_pesan) ?>%
            </div>
        </div>
        <div class="stat-ultimate-value">
            <span class="count-up" data-target="<?= $total_pesan ?>">0</span>
        </div>
        <div class="stat-ultimate-label">Pesan Baru</div>
        <div class="stat-ultimate-meta">
            <span class="meta-pill red"><?= $total_pesan > 0 ? 'Perlu respon' : 'Semua bersih' ?></span>
        </div>
        <?php if ($total_pesan > 0): ?>
            <div class="alert-pulse-indicator">
                <span class="pulse-ring"></span>
                <span class="pulse-ring"></span>
                <span class="pulse-dot">!</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ MAIN DASHBOARD GRID ============ -->
<div class="dashboard-ultimate-grid">
    
    <!-- ===== LEFT COLUMN ===== -->
    <div class="dashboard-col-ultimate">
        
        <!-- Quick Command Actions -->
        <div class="card ultimate" data-aos="fade-up">
            <div class="card-header">
                <h2>⚡ Aksi Cepat</h2>
                <button class="btn-command" onclick="openCommandPalette()" title="Ctrl+K">
                    <kbd>⌘</kbd> Command
                </button>
            </div>
            <div class="quick-actions-ultimate">
                <a href="berita-form.php" class="qa-ultimate-btn" style="--btn-color:#10b981">
                    <div class="qa-ultimate-icon-wrap"><span>✍️</span></div>
                    <div class="qa-ultimate-text">
                        <strong>Tulis Berita</strong>
                        <small>Buat artikel baru</small>
                    </div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="kontak.php" class="qa-ultimate-btn" style="--btn-color:#3b82f6">
                    <div class="qa-ultimate-icon-wrap"><span>💬</span></div>
                    <div class="qa-ultimate-text">
                        <strong>Balas Pesan</strong>
                        <small><?= $total_pesan ?> menunggu</small>
                    </div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="<?= base_url() ?>" class="qa-ultimate-btn" target="_blank" style="--btn-color:#8b5cf6">
                    <div class="qa-ultimate-icon-wrap"><span>🌐</span></div>
                    <div class="qa-ultimate-text">
                        <strong>Website Publik</strong>
                        <small>Lihat tampilan</small>
                    </div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="berita.php" class="qa-ultimate-btn" style="--btn-color:#f59e0b">
                    <div class="qa-ultimate-icon-wrap"><span>📚</span></div>
                    <div class="qa-ultimate-text">
                        <strong>Kelola Konten</strong>
                        <small><?= $draft_count ?> draft</small>
                    </div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
            </div>
        </div>

        <!-- Berita Terbaru Ultimate -->
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header">
                <h2>📰 Berita Terbaru</h2>
                <a href="berita.php" class="btn-sm">Lihat Semua →</a>
            </div>
            
            <?php if (empty($berita_terbaru)): ?>
                <div class="empty-state-ultimate">
                    <div class="empty-illustration">📭</div>
                    <p>Belum ada berita</p>
                </div>
            <?php else: ?>
                <div class="berita-ultimate-list">
                    <?php foreach ($berita_terbaru as $b): ?>
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="berita-ultimate-item">
                        <div class="berita-ultimate-thumb">
                            <?php if ($b['gambar']): ?>
                                <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="">
                            <?php else: ?>
                                <div class="thumb-gradient" style="background:linear-gradient(135deg,hsl(<?= rand(150,280) ?>,70%,60%),hsl(<?= rand(150,280) ?>,70%,40%))"><span>📰</span></div>
                            <?php endif; ?>
                            <div class="berita-ultimate-overlay">
                                <span>✏️ Edit</span>
                            </div>
                        </div>
                        <div class="berita-ultimate-info">
                            <h4><?= sanitize($b['judul']) ?></h4>
                            <div class="berita-ultimate-meta">
                                <span class="badge-ultimate badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                                <span>👁 <?= number_format($b['views']) ?></span>
                                <span><?= time_ago(strtotime($b['created_at'])) ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kanban Status -->
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header">
                <h2>📋 Status Konten</h2>
                <span class="badge-ultimate badge-info"><?= $total_berita ?> total</span>
            </div>
            <div class="kanban-mini">
                <?php
                $draft = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
                $pub = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();
                $arch = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Archived'")->fetchColumn();
                ?>
                <div class="kanban-column draft">
                    <div class="kanban-col-header">
                        <span class="kanban-icon">📝</span>
                        <span>Draft</span>
                        <span class="kanban-count"><?= $draft ?></span>
                    </div>
                    <div class="kanban-items">
                        <?php if ($draft > 0): ?><div class="kanban-item">Menunggu review</div><?php else: ?><div class="kanban-empty">✓ Kosong</div><?php endif; ?>
                    </div>
                </div>
                <div class="kanban-column published">
                    <div class="kanban-col-header">
                        <span class="kanban-icon">✅</span>
                        <span>Published</span>
                        <span class="kanban-count"><?= $pub ?></span>
                    </div>
                    <div class="kanban-items">
                        <?php if ($pub > 0): ?><div class="kanban-item">Aktif di website</div><?php else: ?><div class="kanban-empty">Belum ada</div><?php endif; ?>
                    </div>
                </div>
                <div class="kanban-column archived">
                    <div class="kanban-col-header">
                        <span class="kanban-icon">🗄️</span>
                        <span>Archived</span>
                        <span class="kanban-count"><?= $arch ?></span>
                    </div>
                    <div class="kanban-items">
                        <?php if ($arch > 0): ?><div class="kanban-item">Arsip</div><?php else: ?><div class="kanban-empty">✓ Kosong</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT COLUMN ===== -->
    <div class="dashboard-col-ultimate">
        
        <!-- Performance Score -->
        <div class="card ultimate performance-card" data-aos="fade-left">
            <div class="card-header">
                <h2>🎯 Skor Kesehatan</h2>
                <button class="btn-sm gray" onclick="location.reload()">🔄</button>
            </div>
            <div class="performance-wrap">
                <div class="performance-ring">
                    <svg viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="80" class="perf-ring-bg"/>
                        <circle cx="100" cy="100" r="80" class="perf-ring-fill" style="stroke-dasharray:<?= $score * 5.03 ?>, 503"/>
                    </svg>
                    <div class="performance-value">
                        <span class="perf-num count-up" data-target="<?= $score ?>">0</span>
                        <span class="perf-max">/100</span>
                    </div>
                </div>
                <div class="performance-details">
                    <div class="perf-detail-item <?= $published_count > 0 ? 'done' : '' ?>">
                        <span class="perf-check"><?= $published_count > 0 ? '✓' : '○' ?></span>
                        <span>Berita published (<?= $published_count ?>)</span>
                    </div>
                    <div class="perf-detail-item <?= $total_dosen > 10 ? 'done' : '' ?>">
                        <span class="perf-check"><?= $total_dosen > 10 ? '✓' : '○' ?></span>
                        <span>Dosen aktif (<?= $total_dosen ?>)</span>
                    </div>
                    <div class="perf-detail-item <?= $total_prodi >= 8 ? 'done' : '' ?>">
                        <span class="perf-check"><?= $total_prodi >= 8 ? '✓' : '○' ?></span>
                        <span>8 Prodi lengkap</span>
                    </div>
                    <div class="perf-detail-item <?= $total_pesan == 0 ? 'done' : '' ?>">
                        <span class="perf-check"><?= $total_pesan == 0 ? '✓' : '○' ?></span>
                        <span>Inbox bersih (<?= $total_pesan ?>)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar & Agenda -->
        <div class="card ultimate calendar-card" data-aos="fade-left" data-aos-delay="100">
            <div class="card-header">
                <h2>📅 Agenda Hari Ini</h2>
                <span class="today-date"><?= date('d M Y') ?></span>
            </div>
            <div class="agenda-list">
                <?php if (empty($agenda_hari_ini)): ?>
                    <div class="agenda-empty">
                        <div class="agenda-empty-icon">🌴</div>
                        <p>Hari ini tidak ada agenda</p>
                        <small>Waktunya fokus pada pekerjaan prioritas!</small>
                    </div>
                <?php else: foreach ($agenda_hari_ini as $ag): ?>
                    <div class="agenda-item">
                        <div class="agenda-time">
                            <span class="agenda-day"><?= date('d', strtotime($ag['tanggal_mulai'])) ?></span>
                            <span class="agenda-month"><?= strtoupper(date('M', strtotime($ag['tanggal_mulai']))) ?></span>
                        </div>
                        <div class="agenda-content">
                            <h4><?= sanitize($ag['judul']) ?></h4>
                            <span class="badge-ultimate badge-<?= $ag['jenis'] ?>"><?= ucfirst($ag['jenis']) ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Donut Chart Kategori -->
        <div class="card ultimate donut-card" data-aos="fade-left" data-aos-delay="150">
            <div class="card-header">
                <h2>🍩 Distribusi Kategori</h2>
            </div>
            <div class="donut-wrap">
                <div id="chartDonut" class="donut-chart-apex"></div>
                <div class="donut-legend" id="donutLegend">
                    <!-- Legend will be populated by JS -->
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card ultimate system-card" data-aos="fade-left" data-aos-delay="200">
            <div class="card-header">
                <h2>💻 System Info</h2>
            </div>
            <div class="system-info-list">
                <div class="sys-item">
                    <span>PHP Version</span>
                    <strong><?= phpversion() ?></strong>
                </div>
                <div class="sys-item">
                    <span>Server</span>
                    <strong><?= php_uname('s') ?></strong>
                </div>
                <div class="sys-item">
                    <span>Database</span>
                    <strong>MySQL <?= $pdo->query("SELECT VERSION()")->fetchColumn() ?></strong>
                </div>
                <div class="sys-item">
                    <span>App Version</span>
                    <strong>v<?= APP_VERSION ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ BOTTOM SECTION ============ -->
<div class="dashboard-ultimate-grid" style="margin-top:1.5rem">
    
    <!-- Pesan Terbaru -->
    <div class="dashboard-col-ultimate">
        <div class="card ultimate" data-aos="fade-up">
            <div class="card-header">
                <h2>✉️ Inbox Terbaru</h2>
                <a href="kontak.php" class="btn-sm">Buka Inbox →</a>
            </div>
            <?php if (empty($pesan_terbaru)): ?>
                <div class="empty-state-ultimate">
                    <div class="empty-illustration">📭</div>
                    <p>Inbox bersih!</p>
                </div>
            <?php else: ?>
                <div class="pesan-ultimate-list">
                    <?php foreach ($pesan_terbaru as $p): ?>
                    <div class="pesan-ultimate-item <?= $p['status'] === 'Baru' ? 'unread' : '' ?>">
                        <div class="pesan-ultimate-avatar" style="background:linear-gradient(135deg,hsl(<?= crc32($p['nama']) % 360 ?>,70%,50%),hsl(<?= (crc32($p['nama'])+40) % 360 ?>,70%,40%))">
                            <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                        </div>
                        <div class="pesan-ultimate-body">
                            <div class="pesan-ultimate-head">
                                <strong><?= sanitize($p['nama']) ?></strong>
                                <small><?= time_ago(strtotime($p['created_at'])) ?></small>
                            </div>
                            <div class="pesan-ultimate-subject"><?= sanitize($p['subjek'] ?: '(tanpa subjek)') ?></div>
                            <p><?= excerpt($p['pesan'], 80) ?></p>
                        </div>
                        <?php if ($p['status'] === 'Baru'): ?>
                            <div class="unread-indicator"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="dashboard-col-ultimate">
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header">
                <h2>🕐 Aktivitas Terkini</h2>
            </div>
            <div class="timeline-ultimate">
                <?php
                $timeline = [];
                foreach ($berita_terbaru as $b) {
                    $timeline[] = ['time' => strtotime($b['created_at']), 'icon' => '📰', 'text' => 'Berita: <strong>' . excerpt(sanitize($b['judul']), 40) . '</strong>', 'color' => '#10b981'];
                }
                foreach ($pesan_terbaru as $p) {
                    $timeline[] = ['time' => strtotime($p['created_at']), 'icon' => '✉️', 'text' => 'Pesan dari <strong>' . sanitize($p['nama']) . '</strong>', 'color' => '#3b82f6'];
                }
                usort($timeline, fn($a, $b) => $b['time'] - $a['time']);
                $timeline = array_slice($timeline, 0, 6);
                ?>
                <?php if (empty($timeline)): ?>
                    <div class="empty-state-ultimate"><p>Belum ada aktivitas</p></div>
                <?php else: foreach ($timeline as $t): ?>
                    <div class="timeline-ultimate-item">
                        <div class="timeline-ultimate-dot" style="background:<?= $t['color'] ?>"></div>
                        <div class="timeline-ultimate-line"></div>
                        <div class="timeline-ultimate-content">
                            <span class="timeline-ultimate-icon"><?= $t['icon'] ?></span>
                            <div>
                                <p><?= $t['text'] ?></p>
                                <small><?= time_ago($t['time']) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============ COMMAND PALETTE MODAL ============ -->
<div class="command-palette-overlay" id="commandPalette" onclick="if(event.target===this)closeCommandPalette()">
    <div class="command-palette-box">
        <div class="command-palette-header">
            <span class="cmd-search-icon">🔍</span>
            <input type="text" id="commandInput" class="command-input" placeholder="Ketik perintah atau cari halaman..." autofocus autocomplete="off">
            <kbd>ESC</kbd>
        </div>
        <div class="command-palette-body" id="commandResults">
            <div class="command-section">
                <div class="command-section-label">Navigasi</div>
                <a href="dashboard.php" class="command-item" data-search="dashboard beranda home"><span class="cmd-icon">📊</span><span class="cmd-text">Dashboard</span><span class="cmd-shortcut">⌘1</span></a>
                <a href="berita.php" class="command-item" data-search="berita news artikel"><span class="cmd-icon">📰</span><span class="cmd-text">Kelola Berita</span><span class="cmd-shortcut">⌘2</span></a>
                <a href="kontak.php" class="command-item" data-search="kontak pesan inbox"><span class="cmd-icon">✉️</span><span class="cmd-text">Pesan Masuk</span><span class="cmd-shortcut">⌘3</span></a>
                <a href="berita-form.php" class="command-item" data-search="tulis baru create berita"><span class="cmd-icon">✍️</span><span class="cmd-text">Tulis Berita Baru</span><span class="cmd-shortcut">⌘4</span></a>
            </div>
            <div class="command-section">
                <div class="command-section-label">Aksi</div>
                <a href="<?= base_url() ?>" target="_blank" class="command-item" data-search="website publik lihat view"><span class="cmd-icon">🌐</span><span class="cmd-text">Buka Website Publik</span></a>
                <a href="logout.php" class="command-item" data-search="logout keluar exit"><span class="cmd-icon">🚪</span><span class="cmd-text">Logout</span></a>
                <button class="command-item" onclick="toggleDarkMode()"><span class="cmd-icon">🌙</span><span class="cmd-text">Toggle Dark Mode</span></button>
            </div>
        </div>
        <div class="command-palette-footer">
            <span>↑↓ Navigasi</span><span>↵ Pilih</span><span>ESC Tutup</span>
        </div>
    </div>
</div>

<!-- ============ ULTIMATE STYLES (Scoped) ============ -->
<style>
/* === WELCOME HERO === */
.welcome-hero{position:relative;background:linear-gradient(135deg,#0a6847 0%,#084d35 50%,#16213e 100%);color:#fff;padding:2.5rem;border-radius:24px;overflow:hidden;margin-bottom:2rem;box-shadow:0 20px 60px rgba(10,104,71,.3)}
.welcome-bg-pattern{position:absolute;inset:0;background-image:radial-gradient(circle at 20% 30%,rgba(255,255,255,.08) 0%,transparent 50%),radial-gradient(circle at 80% 70%,rgba(245,166,35,.1) 0%,transparent 50%);pointer-events:none}
.welcome-bg-pattern::before{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,transparent,transparent 35px,rgba(255,255,255,.02) 35px,rgba(255,255,255,.02) 70px);animation:patternShift 20s linear infinite}
@keyframes patternShift{to{background-position:70px 70px}}
.welcome-content{position:relative;display:grid;grid-template-columns:2fr 1fr;gap:2rem;align-items:center}
.welcome-badge{display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);padding:.5rem 1rem;border-radius:999px;font-size:.8rem;font-weight:600;border:1px solid rgba(255,255,255,.2);margin-bottom:1rem}
.badge-pulse{width:8px;height:8px;background:#10b981;border-radius:50%;position:relative}
.badge-pulse::after{content:'';position:absolute;inset:0;background:#10b981;border-radius:50%;animation:badgePulse 2s infinite}
@keyframes badgePulse{0%{transform:scale(1);opacity:1}100%{transform:scale(3);opacity:0}}
.welcome-title{font-size:2.5rem;font-weight:800;line-height:1.2;margin-bottom:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
.greet-emoji{font-size:2.75rem;animation:bounce 2s infinite}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.admin-name{background:linear-gradient(135deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.welcome-subtitle{font-size:1.05rem;opacity:.9;margin-bottom:1.25rem;line-height:1.6}
.welcome-quote{display:flex;gap:.75rem;padding:1rem;background:rgba(255,255,255,.08);border-radius:12px;border-left:3px solid #fbbf24;font-size:.9rem;line-height:1.5}
.quote-icon{font-size:1.25rem;flex-shrink:0}
.welcome-right{display:flex;flex-direction:column;gap:1rem;align-items:flex-end}
.live-clock{background:rgba(255,255,255,.1);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);padding:1.5rem;border-radius:16px;text-align:center;min-width:200px}
.clock-display{font-size:2.5rem;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em;line-height:1}
.clock-date{font-size:.85rem;opacity:.85;margin-top:.5rem}
.clock-hijri{font-size:.75rem;opacity:.75;margin-top:.25rem}
.command-hint{display:flex;align-items:center;gap:.5rem;font-size:.8rem;opacity:.85}
.command-hint kbd{background:rgba(255,255,255,.2);padding:.25rem .5rem;border-radius:6px;font-family:inherit;font-weight:700;border:1px solid rgba(255,255,255,.3)}

/* === ULTIMATE STAT CARDS === */
.stats-ultimate{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;margin-bottom:2rem}
.stat-ultimate-card{background:#fff;border-radius:20px;padding:1.75rem;position:relative;overflow:hidden;transition:all .4s;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid #f1f5f9}
.stat-ultimate-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--card-accent)}
.stat-ultimate-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.1)}
.stat-ultimate-card.has-alert{animation:alertGlow 2s infinite}
@keyframes alertGlow{0%,100%{box-shadow:0 4px 20px rgba(239,68,68,.2)}50%{box-shadow:0 4px 30px rgba(239,68,68,.5)}}
.stat-ultimate-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem}
.stat-ultimate-icon{width:50px;height:50px;background:linear-gradient(135deg,var(--card-accent),color-mix(in srgb,var(--card-accent),#000 15%));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.75rem;box-shadow:0 8px 16px color-mix(in srgb,var(--card-accent),transparent 70%)}
.stat-ultimate-trend{font-size:.8rem;font-weight:700;padding:.3rem .7rem;border-radius:999px;display:inline-flex;align-items:center;gap:.2rem}
.stat-ultimate-trend.up{background:#dcfce7;color:#166534}
.stat-ultimate-trend.down{background:#fee2e2;color:#991b1b}
.stat-ultimate-trend.stable{background:#dbeafe;color:#1e40af}
.stat-ultimate-value{font-size:3rem;font-weight:800;line-height:1;color:#0f172a;margin-bottom:.25rem;font-variant-numeric:tabular-nums}
.stat-ultimate-label{color:#64748b;font-size:.9rem;margin-bottom:1rem}
.stat-ultimate-meta{display:flex;gap:.5rem;margin-bottom:1rem}
.meta-pill{padding:.3rem .7rem;border-radius:999px;font-size:.72rem;font-weight:600}
.meta-pill.green{background:#dcfce7;color:#166534}
.meta-pill.blue{background:#dbeafe;color:#1e40af}
.meta-pill.amber{background:#fef3c7;color:#92400e}
.meta-pill.red{background:#fee2e2;color:#991b1b}

/* Sparkline Chart Container */
.sparkline-chart{margin-top:1rem;height:50px;}
.donut-chart-apex{min-height:200px;}

/* Progress ring mini */
.stat-ultimate-progress{display:flex;align-items:center;gap:1rem;margin-top:.5rem}
.progress-ring-mini{position:relative;width:50px;height:50px;flex-shrink:0}
.progress-ring-mini svg{transform:rotate(-90deg)}
.progress-ring-mini .ring-bg{fill:none;stroke:#e2e8f0;stroke-width:3}
.progress-ring-mini .ring-fill{fill:none;stroke:var(--card-accent);stroke-width:3;stroke-linecap:round;transition:stroke-dasharray 1s ease}
.progress-ring-mini span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--card-accent)}
.stat-ultimate-progress small{font-size:.75rem;color:#64748b}

/* Achievement orbs */
.stat-ultimate-achievements{display:flex;gap:.5rem;margin-top:.5rem}
.achievement-orb{width:36px;height:36px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;animation:orbFloat 3s ease-in-out infinite;animation-delay:var(--orb-delay)}
@keyframes orbFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}

/* Alert pulse indicator */
.alert-pulse-indicator{position:absolute;top:1.5rem;right:1.5rem;width:28px;height:28px;display:flex;align-items:center;justify-content:center}
.pulse-dot{width:12px;height:12px;background:#ef4444;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;position:relative;z-index:2}
.pulse-ring{position:absolute;inset:0;border:2px solid #ef4444;border-radius:50%;animation:pulseRing 2s infinite}
.pulse-ring:nth-child(2){animation-delay:.5s}
@keyframes pulseRing{0%{transform:scale(.5);opacity:1}100%{transform:scale(2);opacity:0}}

/* === ULTIMATE GRID === */
.dashboard-ultimate-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:1.5rem}
.dashboard-col-ultimate{display:flex;flex-direction:column;gap:1.5rem}
.card.ultimate{background:#fff;border-radius:20px;padding:1.75rem;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid #f1f5f9}

/* Quick Actions Ultimate */
.quick-actions-ultimate{display:flex;flex-direction:column;gap:.75rem}
.qa-ultimate-btn{display:flex;align-items:center;gap:1rem;padding:1rem;background:#f8fafc;border-radius:14px;text-decoration:none;color:#0f172a;transition:all .3s;position:relative;overflow:hidden;border:1px solid transparent}
.qa-ultimate-btn::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--btn-color);transition:width .3s}
.qa-ultimate-btn:hover{background:#fff;border-color:var(--btn-color);transform:translateX(4px);box-shadow:0 8px 20px rgba(0,0,0,.08)}
.qa-ultimate-btn:hover::before{width:6px}
.qa-ultimate-icon-wrap{width:48px;height:48px;background:color-mix(in srgb,var(--btn-color),#fff 85%);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.qa-ultimate-text{flex:1;display:flex;flex-direction:column}
.qa-ultimate-text strong{font-size:.95rem}
.qa-ultimate-text small{font-size:.75rem;color:#64748b}
.qa-ultimate-arrow{color:var(--btn-color);font-size:1.25rem;opacity:.5;transition:all .3s}
.qa-ultimate-btn:hover .qa-ultimate-arrow{opacity:1;transform:translateX(4px)}

/* Command button */
.btn-command{background:#0f172a;color:#fff;border:none;padding:.4rem .85rem;border-radius:8px;font-size:.75rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;font-family:inherit;font-weight:600;transition:all .3s}
.btn-command:hover{background:#1e293b;transform:translateY(-1px)}
.btn-command kbd{background:rgba(255,255,255,.2);padding:.1rem .4rem;border-radius:4px;font-size:.7rem}

/* Berita Ultimate */
.berita-ultimate-list{display:flex;flex-direction:column;gap:.75rem}
.berita-ultimate-item{display:flex;gap:1rem;padding:.75rem;background:#f8fafc;border-radius:14px;text-decoration:none;color:inherit;transition:all .3s;position:relative;overflow:hidden}
.berita-ultimate-item:hover{background:#f1f5f9;transform:translateX(4px)}
.berita-ultimate-thumb{width:100px;height:70px;border-radius:10px;overflow:hidden;flex-shrink:0;position:relative}
.berita-ultimate-thumb img{width:100%;height:100%;object-fit:cover}
.thumb-gradient{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.75rem}
.berita-ultimate-overlay{position:absolute;inset:0;background:rgba(10,104,71,.85);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:.85rem;opacity:0;transition:opacity .3s}
.berita-ultimate-item:hover .berita-ultimate-overlay{opacity:1}
.berita-ultimate-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center}
.berita-ultimate-info h4{font-size:.95rem;margin-bottom:.5rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.berita-ultimate-meta{display:flex;gap:.75rem;align-items:center;font-size:.75rem;color:#64748b;flex-wrap:wrap}

/* Badge ultimate */
.badge-ultimate{padding:.25rem .65rem;border-radius:999px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.badge-published{background:#dcfce7;color:#166534}
.badge-draft{background:#fef3c7;color:#92400e}
.badge-archived{background:#e2e8f0;color:#475569}
.badge-ujian{background:#fee2e2;color:#991b1b}
.badge-seminar{background:#dbeafe;color:#1e40af}
.badge-wisuda{background:#fef3c7;color:#92400e}
.badge-libur{background:#e0e7ff;color:#4338ca}
.badge-info{background:#dbeafe;color:#1e40af}

/* Kanban mini */
.kanban-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}
.kanban-column{background:#f8fafc;border-radius:12px;padding:.75rem;min-height:150px;border-top:3px solid}
.kanban-column.draft{border-color:#f59e0b}
.kanban-column.published{border-color:#10b981}
.kanban-column.archived{border-color:#64748b}
.kanban-col-header{display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:700;margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid #e2e8f0}
.kanban-icon{font-size:1rem}
.kanban-count{margin-left:auto;background:#fff;padding:.1rem .5rem;border-radius:999px;font-size:.7rem;font-weight:800}
.kanban-item{background:#fff;padding:.75rem;border-radius:8px;font-size:.8rem;margin-bottom:.4rem;border-left:3px solid currentColor;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.kanban-column.draft .kanban-item{color:#f59e0b}
.kanban-column.published .kanban-item{color:#10b981}
.kanban-column.archived .kanban-item{color:#64748b}
.kanban-empty{text-align:center;padding:1rem .5rem;color:#94a3b8;font-size:.75rem;font-style:italic}

/* Performance card */
.performance-wrap{display:grid;grid-template-columns:auto 1fr;gap:1.5rem;align-items:center}
.performance-ring{position:relative;width:140px;height:140px;flex-shrink:0}
.performance-ring svg{transform:rotate(-90deg);width:100%;height:100%}
.perf-ring-bg{fill:none;stroke:#e2e8f0;stroke-width:10}
.perf-ring-fill{fill:none;stroke:url(#perfGradient);stroke-width:10;stroke-linecap:round;transition:stroke-dasharray 1.5s ease}
.performance-ring::before{content:'';position:absolute;inset:0;background:conic-gradient(from 0deg,#10b981,#3b82f6,#8b5cf6);border-radius:50%;opacity:0;filter:blur(20px);z-index:-1}
.performance-value{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.perf-num{font-size:2.5rem;font-weight:800;line-height:1;color:#0a6847}
.perf-max{font-size:.85rem;color:#94a3b8}
.performance-details{display:flex;flex-direction:column;gap:.6rem}
.perf-detail-item{display:flex;align-items:center;gap:.6rem;font-size:.85rem;padding:.4rem;border-radius:8px;transition:all .3s}
.perf-detail-item:hover{background:#f8fafc}
.perf-check{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;background:#e2e8f0;color:#64748b}
.perf-detail-item.done .perf-check{background:#10b981;color:#fff}

/* Calendar card */
.calendar-card .today-date{font-size:.8rem;color:#64748b;background:#f1f5f9;padding:.3rem .7rem;border-radius:999px}
.agenda-list{display:flex;flex-direction:column;gap:.75rem}
.agenda-item{display:flex;gap:1rem;padding:.85rem;background:#f8fafc;border-radius:12px;transition:all .3s}
.agenda-item:hover{background:#f1f5f9;transform:translateX(4px)}
.agenda-time{width:55px;flex-shrink:0;text-align:center;background:linear-gradient(135deg,#0a6847,#16a34a);color:#fff;border-radius:10px;padding:.5rem .25rem;display:flex;flex-direction:column}
.agenda-day{font-size:1.4rem;font-weight:800;line-height:1}
.agenda-month{font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;margin-top:.2rem}
.agenda-content{flex:1;min-width:0}
.agenda-content h4{font-size:.9rem;margin-bottom:.4rem}
.agenda-empty{text-align:center;padding:2rem 1rem;color:#64748b}
.agenda-empty-icon{font-size:3rem;margin-bottom:.5rem;opacity:.5}
.agenda-empty small{font-size:.75rem;opacity:.75}

/* Donut chart */
.donut-wrap{display:grid;grid-template-columns:150px 1fr;gap:1.5rem;align-items:center}
.donut-legend{display:flex;flex-direction:column;gap:.5rem}
.legend-item{display:flex;align-items:center;gap:.5rem;font-size:.85rem;padding:.4rem;border-radius:8px;transition:background .2s;cursor:pointer}
.legend-item:hover{background:#f8fafc}
.legend-color{width:12px;height:12px;border-radius:3px;flex-shrink:0}
.legend-label{flex:1;color:#475569}
.legend-value{font-weight:700;color:#0a6847;background:#dcfce7;padding:.1rem .5rem;border-radius:999px;font-size:.75rem}

/* System info */
.system-info-list{display:flex;flex-direction:column;gap:.5rem}
.sys-item{display:flex;justify-content:space-between;align-items:center;padding:.7rem .9rem;background:#f8fafc;border-radius:10px;font-size:.85rem;transition:all .3s}
.sys-item:hover{background:#f1f5f9;transform:translateX(3px)}
.sys-item span{color:#64748b}
.sys-item strong{color:#0f172a;font-family:ui-monospace,monospace;font-size:.8rem}

/* Pesan Ultimate */
.pesan-ultimate-list{display:flex;flex-direction:column;gap:.75rem}
.pesan-ultimate-item{display:flex;gap:1rem;padding:1rem;background:#f8fafc;border-radius:14px;transition:all .3s;position:relative}
.pesan-ultimate-item:hover{background:#f1f5f9}
.pesan-ultimate-item.unread{background:#eff6ff;border-left:3px solid #3b82f6}
.pesan-ultimate-avatar{width:48px;height:48px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;font-size:1.1rem}
.pesan-ultimate-body{flex:1;min-width:0}
.pesan-ultimate-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem}
.pesan-ultimate-head strong{font-size:.95rem}
.pesan-ultimate-head small{font-size:.7rem;color:#94a3b8}
.pesan-ultimate-subject{font-size:.85rem;color:#0a6847;font-weight:600;margin-bottom:.25rem}
.pesan-ultimate-body p{font-size:.82rem;color:#64748b;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0}
.unread-indicator{position:absolute;top:1rem;right:1rem;width:10px;height:10px;background:#3b82f6;border-radius:50%;animation:unreadPulse 2s infinite}
@keyframes unreadPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.7}}

/* Timeline Ultimate */
.timeline-ultimate{display:flex;flex-direction:column;gap:0;padding-left:.5rem}
.timeline-ultimate-item{display:flex;gap:1rem;position:relative;padding-bottom:1.25rem}
.timeline-ultimate-item:last-child{padding-bottom:0}
.timeline-ultimate-item:last-child .timeline-ultimate-line{display:none}
.timeline-ultimate-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;margin-top:.3rem;position:relative;z-index:2;box-shadow:0 0 0 4px #fff}
.timeline-ultimate-line{position:absolute;left:6px;top:14px;bottom:0;width:2px;background:#e2e8f0;z-index:1}
.timeline-ultimate-content{flex:1;display:flex;gap:.75rem;align-items:flex-start;background:#f8fafc;padding:.85rem 1rem;border-radius:12px;transition:all .3s}
.timeline-ultimate-content:hover{background:#f1f5f9;transform:translateX(4px)}
.timeline-ultimate-icon{font-size:1.25rem;flex-shrink:0}
.timeline-ultimate-content p{font-size:.85rem;margin:0 0 .2rem;line-height:1.4}
.timeline-ultimate-content small{font-size:.7rem;color:#94a3b8}

/* Empty state ultimate */
.empty-state-ultimate{text-align:center;padding:2.5rem 1rem;color:#64748b}
.empty-illustration{font-size:4rem;margin-bottom:.75rem;opacity:.4;animation:emptyFloat 3s ease-in-out infinite}
@keyframes emptyFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

/* === COMMAND PALETTE === */
.command-palette-overlay{position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);display:none;align-items:flex-start;justify-content:center;padding-top:15vh;z-index:10000;animation:cmdFadeIn .2s}
.command-palette-overlay.open{display:flex}
@keyframes cmdFadeIn{from{opacity:0}to{opacity:1}}
.command-palette-box{background:#fff;border-radius:16px;width:90%;max-width:600px;box-shadow:0 30px 80px rgba(0,0,0,.4);overflow:hidden;animation:cmdSlideDown .3s}
@keyframes cmdSlideDown{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.command-palette-header{display:flex;align-items:center;gap:.75rem;padding:1.25rem 1.5rem;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.cmd-search-icon{font-size:1.25rem}
.command-input{flex:1;border:none;outline:none;font-size:1rem;font-family:inherit;background:transparent;color:#0f172a}
.command-palette-header kbd{background:#e2e8f0;padding:.25rem .5rem;border-radius:6px;font-size:.7rem;font-weight:700;color:#64748b}
.command-palette-body{max-height:400px;overflow-y:auto;padding:.75rem}
.command-section{margin-bottom:1rem}
.command-section-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;font-weight:700;padding:.5rem .75rem;margin-bottom:.25rem}
.command-item{display:flex;align-items:center;gap:.85rem;padding:.75rem .85rem;border-radius:10px;text-decoration:none;color:#0f172a;cursor:pointer;background:transparent;border:none;width:100%;text-align:left;font-family:inherit;font-size:.9rem;transition:all .15s}
.command-item:hover,.command-item.active{background:#f1f5f9}
.cmd-icon{font-size:1.15rem;width:24px;text-align:center}
.cmd-text{flex:1}
.cmd-shortcut{font-size:.7rem;color:#94a3b8;background:#f1f5f9;padding:.15rem .4rem;border-radius:4px;font-family:ui-monospace,monospace}
.command-palette-footer{display:flex;gap:1rem;padding:.75rem 1.5rem;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:.75rem;color:#64748b}

/* Responsive */
@media(max-width:968px){
    .welcome-content{grid-template-columns:1fr}
    .welcome-right{align-items:flex-start}
    .welcome-title{font-size:1.75rem}
    .dashboard-ultimate-grid{grid-template-columns:1fr}
    .performance-wrap{grid-template-columns:1fr;text-align:center}
    .donut-wrap{grid-template-columns:1fr;text-align:center}
    .donut-chart-apex{margin:0 auto}
    .kanban-mini{grid-template-columns:1fr}
}
</style>

<!-- SVG gradient for performance ring -->
<svg width="0" height="0" style="position:absolute">
    <defs>
        <linearGradient id="perfGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#10b981"/>
            <stop offset="50%" stop-color="#3b82f6"/>
            <stop offset="100%" stop-color="#8b5cf6"/>
        </linearGradient>
    </defs>
</svg>

<!-- ============ ULTIMATE SCRIPTS ============ -->
<script>
// === LIVE CLOCK ===
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    const clockEl = document.getElementById('liveClock');
    if (clockEl) clockEl.textContent = `${h}:${m}:${s}`;
    
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const dateEl = document.getElementById('liveDate');
    if (dateEl) dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
    
    const hijriEl = document.getElementById('liveHijri');
    if (hijriEl) {
        try {
            const hijri = new Intl.DateTimeFormat('id-u-ca-islamic', {day:'numeric',month:'long',year:'numeric'}).format(now);
            hijriEl.textContent = '🕌 ' + hijri + ' H';
        } catch(e) { hijriEl.textContent = ''; }
    }
}
setInterval(updateClock, 1000);
updateClock();

// === COUNT UP ANIMATION ===
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1800;
    const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCount(entry.target);
            countObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// === CONFETTI on first load today ===
(function(){
    const today = new Date().toDateString();
    const key = 'fkip_confetti_' + today;
    if (!localStorage.getItem(key)) {
        localStorage.setItem(key, '1');
        launchConfetti();
    }
})();

function launchConfetti() {
    const colors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
    for (let i = 0; i < 60; i++) {
        const piece = document.createElement('div');
        piece.style.cssText = `position:fixed;width:${6+Math.random()*6}px;height:${6+Math.random()*10}px;background:${colors[Math.floor(Math.random()*colors.length)]};left:${Math.random()*100}vw;top:-20px;z-index:9999;pointer-events:none;border-radius:2px;opacity:0.9;`;
        document.body.appendChild(piece);
        const x = (Math.random()-.5)*400;
        const y = window.innerHeight + 100;
        const rot = Math.random()*720;
        const dur = 2500 + Math.random()*1500;
        piece.animate([
            { transform:'translateY(0) translateX(0) rotate(0)', opacity:1 },
            { transform:`translateY(${y}px) translateX(${x}px) rotate(${rot}deg)`, opacity:0 }
        ], { duration: dur, easing:'cubic-bezier(.25,.46,.45,.94)' }).onfinish = () => piece.remove();
    }
}

// === APEXCHARTS: SPARKLINE ===
const sparklineOptions = {
    series: [{ data: <?= json_encode($sparkline_berita) ?> }],
    chart: { type: 'area', height: 50, sparkline: { enabled: true }, animations: { enabled: true, easing: 'easeinout', speed: 800 } },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
    colors: ['#10b981'],
    tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: () => 'Berita' } }, marker: { show: false } }
};
new ApexCharts(document.querySelector("#chartBerita"), sparklineOptions).render();

// === APEXCHARTS: DONUT ===
const donutOptions = {
    series: <?= json_encode($kat_data ?: [1]) ?>,
    labels: <?= json_encode($kat_labels ?: ['Belum ada data']) ?>,
    chart: { type: 'donut', height: 200, animations: { enabled: true, easing: 'easeinout', speed: 800 } },
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    plotOptions: { pie: { donut: { size: '75%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $kat_total ?> } } } } },
    dataLabels: { enabled: false },
    legend: { show: false },
    stroke: { show: true, colors: ['#fff'], width: 2 }
};
const donutChart = new ApexCharts(document.querySelector("#chartDonut"), donutOptions);
donutChart.render();

// Custom Legend for Donut
const legendContainer = document.getElementById('donutLegend');
<?php if (!empty($kat_stats)): ?>
    <?php foreach ($kat_stats as $i => $k): ?>
        const color<?= $i ?> = ['<?= implode("','", ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']) ?>'][<?= $i ?> % 6];
        legendContainer.innerHTML += `
            <div class="legend-item" onclick="donutChart.toggleSeries('${addslashes($k['kategori'])}')">
                <span class="legend-color" style="background:${color<?= $i ?>}"></span>
                <span class="legend-label"><?= sanitize($k['kategori']) ?></span>
                <span class="legend-value"><?= $k['total'] ?></span>
            </div>
        `;
    <?php endforeach; ?>
<?php else: ?>
    legendContainer.innerHTML = '<small style="color:#94a3b8">Belum ada data</small>';
<?php endif; ?>

// === COMMAND PALETTE ===
const palette = document.getElementById('commandPalette');
const cmdInput = document.getElementById('commandInput');
const cmdResults = document.getElementById('commandResults');

function openCommandPalette() {
    palette.classList.add('open');
    setTimeout(() => cmdInput.focus(), 50);
}
function closeCommandPalette() {
    palette.classList.remove('open');
    cmdInput.value = '';
    filterCommands('');
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (palette.classList.contains('open')) closeCommandPalette();
        else openCommandPalette();
    }
    if (e.key === 'Escape' && palette.classList.contains('open')) {
        closeCommandPalette();
    }
});

cmdInput?.addEventListener('input', (e) => filterCommands(e.target.value.toLowerCase()));

function filterCommands(q) {
    const items = cmdResults.querySelectorAll('.command-item');
    const sections = cmdResults.querySelectorAll('.command-section');
    items.forEach(item => {
        const search = (item.dataset.search || '') + ' ' + item.textContent.toLowerCase();
        item.style.display = search.includes(q) ? 'flex' : 'none';
    });
    sections.forEach(sec => {
        const visible = [...sec.querySelectorAll('.command-item')].some(i => i.style.display !== 'none');
        sec.style.display = visible ? 'block' : 'none';
    });
}

// === DARK MODE TOGGLE ===
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('admin_dark', document.body.classList.contains('dark-mode') ? '1' : '0');
    closeCommandPalette();
}
if (localStorage.getItem('admin_dark') === '1') {
    document.body.classList.add('dark-mode');
}

// === KEYBOARD SHORTCUTS 1-4 ===
document.addEventListener('keydown', (e) => {
    if (!palette.classList.contains('open')) return;
    if (e.metaKey || e.ctrlKey) {
        const links = cmdResults.querySelectorAll('.command-item');
        const num = parseInt(e.key);
        if (num >= 1 && num <= 4 && links[num-1]) {
            e.preventDefault();
            links[num-1].click();
        }
    }
});

console.log('%c🎓 FKIP UNIMOF Admin Ultimate', 'color:#0a6847;font-size:20px;font-weight:bold');
console.log('%cTip: Tekan Ctrl+K untuk Command Palette', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>