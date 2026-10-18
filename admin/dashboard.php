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
$total_riset = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published'")->fetchColumn();
$total_kerjasama = (int)$pdo->query("SELECT COUNT(*) FROM kerjasama WHERE status='Aktif'")->fetchColumn();
$total_agenda = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE status='Aktif'")->fetchColumn();

$published_today = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = CURDATE() AND status = 'Published'")->fetchColumn();
$draft_count = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
$published_count = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();

// ===== SPARKLINE DATA (7 hari terakhir) =====
$sparkline_berita = [];
for ($i = 6; $i >= 0; $i--) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = ?");
    $stmt->execute([date('Y-m-d', strtotime("-$i days"))]);
    $sparkline_berita[] = (int)$stmt->fetchColumn();
}

// ===== HEATMAP DATA (30 hari terakhir) =====
$heatmap_data = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $heatmap_data[] = [
        'date' => $date,
        'count' => (int)$stmt->fetchColumn(),
        'day' => date('D', strtotime($date)),
        'label' => date('d M', strtotime($date))
    ];
}

// ===== TREND CALCULATION =====
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

// ===== TOP PERFORMING BERITA =====
$top_berita = $pdo->query("SELECT id, judul, views, published_at FROM berita WHERE status='Published' ORDER BY views DESC LIMIT 5")->fetchAll();

// ===== DOSEN PER PRODI =====
$dosen_per_prodi = $pdo->query("
    SELECT p.nama as prodi, COUNT(d.id) as jumlah 
    FROM program_studi p 
    LEFT JOIN dosen d ON p.id = d.program_studi_id AND d.status='Aktif'
    GROUP BY p.id, p.nama 
    ORDER BY jumlah DESC 
    LIMIT 6
")->fetchAll();

// ===== DATA TERBARU =====
$berita_terbaru = $pdo->query("SELECT id, judul, status, views, created_at, gambar FROM berita ORDER BY created_at DESC LIMIT 4")->fetchAll();
$pesan_terbaru = $pdo->query("SELECT id, nama, subjek, pesan, status, created_at FROM kontak ORDER BY created_at DESC LIMIT 4")->fetchAll();
$agenda_hari_ini = $pdo->query("SELECT judul, jenis, tanggal_mulai FROM agenda WHERE tanggal_mulai <= CURDATE() AND (tanggal_selesai IS NULL OR tanggal_selesai >= CURDATE()) AND status = 'Aktif' LIMIT 3")->fetchAll();

// ===== MOST ACTIVE DAY (dari data 30 hari) =====
$day_counts = [];
foreach ($heatmap_data as $h) {
    $day = $h['day'];
    $day_counts[$day] = ($day_counts[$day] ?? 0) + $h['count'];
}
$most_active_day = !empty($day_counts) ? array_keys($day_counts, max($day_counts))[0] : '-';

// ===== PERFORMANCE SCORE =====
$score = 0;
$score_details = [];
if ($published_count > 0) { $score += 25; $score_details[] = ['text' => 'Berita published (' . $published_count . ')', 'done' => true]; }
else { $score_details[] = ['text' => 'Berita published (0)', 'done' => false]; }

if ($total_dosen > 10) { $score += 25; $score_details[] = ['text' => 'Dosen aktif (' . $total_dosen . ')', 'done' => true]; }
else { $score_details[] = ['text' => 'Dosen aktif (' . $total_dosen . ')', 'done' => false]; }

if ($total_prodi >= 8) { $score += 25; $score_details[] = ['text' => '8 Prodi lengkap', 'done' => true]; }
else { $score_details[] = ['text' => '8 Prodi lengkap (' . $total_prodi . '/8)', 'done' => false]; }

if ($total_pesan == 0) { $score += 25; $score_details[] = ['text' => 'Inbox bersih (0)', 'done' => true]; }
elseif ($total_pesan < 5) { $score += 15; $score_details[] = ['text' => 'Inbox hampir bersih (' . $total_pesan . ')', 'done' => false]; }
else { $score_details[] = ['text' => 'Inbox perlu respon (' . $total_pesan . ')', 'done' => false]; }

// ===== SERVER HEALTH (simulasi - ganti dengan real di production) =====
$disk_usage = function_exists('disk_free_space') ? round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 1) : rand(30, 70);
$memory_usage = rand(40, 75); // Simulasi
$db_size = 0;
try {
    $result = $pdo->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.tables WHERE table_schema = 'fkip'")->fetch();
    $db_size = round($result['size_mb'] ?? 0, 2);
} catch (Exception $e) { $db_size = 'N/A'; }

// ===== UPTIME CALCULATION =====
$server_start = $_SESSION['server_start'] ?? time();
if (!isset($_SESSION['server_start'])) $_SESSION['server_start'] = time();
$uptime_seconds = time() - $server_start;
$uptime_days = floor($uptime_seconds / 86400);
$uptime_hours = floor(($uptime_seconds % 86400) / 3600);
$uptime_str = $uptime_days > 0 ? "{$uptime_days}h {$uptime_hours}j" : "{$uptime_hours}j " . floor(($uptime_seconds % 3600) / 60) . "m";

// ===== GREETING & QUOTE =====
$hour = (int)date('H');
if ($hour < 11) { $greeting = 'Selamat Pagi'; $greet_emoji = '☀️'; $greet_color = '#f59e0b'; }
elseif ($hour < 15) { $greeting = 'Selamat Siang'; $greet_emoji = '🌤️'; $greet_color = '#f97316'; }
elseif ($hour < 18) { $greeting = 'Selamat Sore'; $greet_emoji = '🌅'; $greet_color = '#ef4444'; }
else { $greeting = 'Selamat Malam'; $greet_emoji = '🌙'; $greet_color = '#6366f1'; }

$quotes = [
    '"Barangsiapa yang menempuh jalan untuk mencari ilmu, Allah akan memudahkan baginya jalan menuju surga." — HR. Muslim',
    '"Sebaik-baik manusia adalah yang paling bermanfaat bagi manusia lain." — HR. Ahmad',
    '"Tuntutlah ilmu dari buaian hingga ke liang lahat." — Pepatah Arab',
    '"Ilmu tanpa amal ibarat pohon tanpa buah." — Pepatah Arab',
    '"Pendidikan adalah senjata paling mematikan di dunia." — Nelson Mandela'
];
$quote = $quotes[array_rand($quotes)];

$active_menu = 'dashboard';
$page_heading = 'Dashboard';
$breadcrumbs = [['Dashboard', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- ===== WELCOME HERO ===== -->
<section class="welcome-hero" data-aos="fade-down">
    <div class="welcome-bg-pattern"></div>
    <div class="welcome-content">
        <div class="welcome-left">
            <div class="welcome-badge">
                <span class="badge-pulse"></span>
                <span>Sistem Online • <?= date('l, d F Y') ?></span>
                <?php if ($total_pesan > 0): ?>
                <span class="badge-alert">🔔 <?= $total_pesan ?> pesan baru</span>
                <?php endif; ?>
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
            <div class="welcome-stats-row">
                <div class="welcome-stat">
                    <span class="stat-icon">📊</span>
                    <span class="stat-num"><?= $total_berita + $total_riset + $total_prestasi ?></span>
                    <span class="stat-label">Total Konten</span>
                </div>
                <div class="welcome-stat">
                    <span class="stat-icon">🎯</span>
                    <span class="stat-num"><?= $score ?>/100</span>
                    <span class="stat-label">Skor Kesehatan</span>
                </div>
                <div class="welcome-stat">
                    <span class="stat-icon">⏱️</span>
                    <span class="stat-num"><?= $uptime_str ?></span>
                    <span class="stat-label">Uptime</span>
                </div>
            </div>
        </div>
        <div class="welcome-right">
            <div class="live-clock">
                <div class="clock-display" id="liveClock">--:--:--</div>
                <div class="clock-date" id="liveDate">--</div>
            </div>
            <div class="pomodoro-widget" id="pomodoroWidget">
                <div class="pomodoro-header">
                    <span>🍅 Pomodoro</span>
                    <span class="pomodoro-mode" id="pomodoroMode">Fokus</span>
                </div>
                <div class="pomodoro-timer" id="pomodoroTimer">25:00</div>
                <div class="pomodoro-controls">
                    <button class="pomo-btn" onclick="togglePomodoro()" id="pomoToggle">▶️ Mulai</button>
                    <button class="pomo-btn pomo-btn-secondary" onclick="resetPomodoro()">🔄 Reset</button>
                </div>
                <div class="pomodoro-sessions">
                    <span>🍅 Sesi hari ini: <strong id="pomoCount">0</strong></span>
                </div>
            </div>
            <div class="command-hint">
                <kbd>Ctrl</kbd> + <kbd>K</kbd> untuk <strong>Command Palette</strong>
            </div>
        </div>
    </div>
</section>

<!-- ===== FOCUS MODE TOGGLE ===== -->
<div class="focus-mode-toggle" onclick="toggleFocusMode()" title="Toggle Focus Mode">
    <span id="focusIcon">🎯</span>
    <span id="focusLabel">Focus Mode</span>
</div>

<!-- ===== ULTIMATE STATS ===== -->
<div class="stats-ultimate">
    <div class="stat-ultimate-card" style="--card-accent:#10b981" data-aos="fade-up">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">📰</div>
            <div class="stat-ultimate-trend <?= $trend_berita >= 0 ? 'up' : 'down' ?>">
                <?= $trend_berita >= 0 ? '↑' : '↓' ?> <?= abs($trend_berita) ?>%
            </div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_berita ?>">0</span></div>
        <div class="stat-ultimate-label">Total Berita</div>
        <div class="stat-ultimate-meta"><span class="meta-pill green">+<?= $published_today ?> hari ini</span></div>
        <div id="chartBerita" class="sparkline-chart"></div>
    </div>

    <div class="stat-ultimate-card" style="--card-accent:#3b82f6" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🎓</div>
            <div class="stat-ultimate-trend stable">● Active</div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_prodi ?>">0</span></div>
        <div class="stat-ultimate-label">Program Studi</div>
        <div class="stat-ultimate-meta"><span class="meta-pill blue"><?= $total_dosen ?> dosen</span></div>
        <div class="stat-ultimate-progress">
            <div class="progress-ring-mini">
                <svg viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="16" class="ring-bg"/>
                    <circle cx="18" cy="18" r="16" class="ring-fill" style="stroke-dasharray:<?= min(100, ($total_prodi/8) * 100) ?>, 100"/>
                </svg>
                <span><?= min(100, round(($total_prodi/8)*100)) ?>%</span>
            </div>
            <small>Target 8 Prodi</small>
        </div>
    </div>

    <div class="stat-ultimate-card" style="--card-accent:#f59e0b" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🏆</div>
            <div class="stat-ultimate-trend up">⭐ Top</div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_prestasi ?>">0</span></div>
        <div class="stat-ultimate-label">Prestasi</div>
        <div class="stat-ultimate-meta"><span class="meta-pill amber"><?= $total_alumni ?> alumni</span></div>
        <div class="stat-ultimate-achievements">
            <div class="achievement-orb" style="--orb-delay:0s">🥇</div>
            <div class="achievement-orb" style="--orb-delay:0.3s">🥈</div>
            <div class="achievement-orb" style="--orb-delay:0.6s">🥉</div>
        </div>
    </div>

    <div class="stat-ultimate-card <?= $total_pesan > 0 ? 'has-alert' : '' ?>" style="--card-accent:#ef4444" data-aos="fade-up" data-aos-delay="300">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">✉️</div>
            <div class="stat-ultimate-trend <?= $trend_pesan >= 0 ? 'up' : 'down' ?>">
                <?= $trend_pesan >= 0 ? '↑' : '↓' ?> <?= abs($trend_pesan) ?>%
            </div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_pesan ?>">0</span></div>
        <div class="stat-ultimate-label">Pesan Baru</div>
        <div class="stat-ultimate-meta"><span class="meta-pill red"><?= $total_pesan > 0 ? 'Perlu respon' : 'Semua bersih' ?></span></div>
        <?php if ($total_pesan > 0): ?>
            <div class="alert-pulse-indicator">
                <span class="pulse-ring"></span>
                <span class="pulse-ring"></span>
                <span class="pulse-dot">!</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="stat-ultimate-card" style="--card-accent:#8b5cf6" data-aos="fade-up" data-aos-delay="400">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🔬</div>
            <div class="stat-ultimate-trend stable">📚 Riset</div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_riset ?>">0</span></div>
        <div class="stat-ultimate-label">Riset Published</div>
        <div class="stat-ultimate-meta"><span class="meta-pill purple">Aktif</span></div>
    </div>

    <div class="stat-ultimate-card" style="--card-accent:#ec4899" data-aos="fade-up" data-aos-delay="500">
        <div class="stat-ultimate-header">
            <div class="stat-ultimate-icon">🤝</div>
            <div class="stat-ultimate-trend stable">🌐 Global</div>
        </div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $total_kerjasama ?>">0</span></div>
        <div class="stat-ultimate-label">Kerjasama Aktif</div>
        <div class="stat-ultimate-meta"><span class="meta-pill pink">Partner</span></div>
    </div>
</div>

<!-- ===== HEATMAP + GOAL TRACKER ===== -->
<div class="dashboard-ultimate-grid">
    <div class="dashboard-col-ultimate">
        <div class="card ultimate" data-aos="fade-up">
            <div class="card-header">
                <h2>🔥 Activity Heatmap</h2>
                <span class="badge-ultimate badge-info">30 hari terakhir</span>
            </div>
            <div class="heatmap-container">
                <div class="heatmap-grid" id="heatmapGrid">
                    <?php foreach ($heatmap_data as $h): 
                        $intensity = min(4, $h['count']);
                        $colors = ['#ebedf0', '#9be9a8', '#40c463', '#30a14e', '#216e39'];
                        $bg = $colors[$intensity];
                    ?>
                    <div class="heatmap-cell" 
                         style="background:<?= $bg ?>" 
                         title="<?= $h['label'] ?>: <?= $h['count'] ?> berita"
                         data-count="<?= $h['count'] ?>">
                        <?php if ($h['count'] > 0): ?>
                            <span class="heatmap-count"><?= $h['count'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="heatmap-legend">
                    <span>Kurang</span>
                    <div class="legend-cell" style="background:#ebedf0"></div>
                    <div class="legend-cell" style="background:#9be9a8"></div>
                    <div class="legend-cell" style="background:#40c463"></div>
                    <div class="legend-cell" style="background:#30a14e"></div>
                    <div class="legend-cell" style="background:#216e39"></div>
                    <span>Lebih</span>
                </div>
                <div class="heatmap-stats">
                    <div class="heatmap-stat">
                        <strong><?= $most_active_day ?></strong>
                        <span>Hari Paling Aktif</span>
                    </div>
                    <div class="heatmap-stat">
                        <strong><?= array_sum(array_column($heatmap_data, 'count')) ?></strong>
                        <span>Total 30 Hari</span>
                    </div>
                    <div class="heatmap-stat">
                        <strong><?= round(array_sum(array_column($heatmap_data, 'count')) / 30, 1) ?></strong>
                        <span>Rata-rata/Hari</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-col-ultimate">
        <div class="card ultimate goal-card" data-aos="fade-up">
            <div class="card-header">
                <h2>🎯 Goal Tracker</h2>
                <button class="btn-sm gray" onclick="addGoal()">+ Tambah</button>
            </div>
            <div class="goal-list" id="goalList">
                <!-- Goals akan di-render via JavaScript dari localStorage -->
            </div>
            <div class="goal-summary">
                <div class="goal-progress-bar">
                    <div class="goal-progress-fill" id="goalProgressFill" style="width:0%"></div>
                </div>
                <span class="goal-progress-text" id="goalProgressText">0% selesai</span>
            </div>
        </div>
    </div>
</div>

<!-- ===== MAIN DASHBOARD GRID ===== -->
<div class="dashboard-ultimate-grid">
    <!-- LEFT COLUMN -->
    <div class="dashboard-col-ultimate">
        <!-- Quick Actions -->
        <div class="card ultimate" data-aos="fade-up">
            <div class="card-header">
                <h2>⚡ Aksi Cepat</h2>
                <button class="btn-command" onclick="openCommandPalette()" title="Ctrl+K"><kbd>⌘</kbd> Command</button>
            </div>
            <div class="quick-actions-ultimate">
                <a href="berita-form.php" class="qa-ultimate-btn" style="--btn-color:#10b981">
                    <div class="qa-ultimate-icon-wrap"><span>✍️</span></div>
                    <div class="qa-ultimate-text"><strong>Tulis Berita</strong><small>Buat artikel baru</small></div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="kontak.php" class="qa-ultimate-btn" style="--btn-color:#3b82f6">
                    <div class="qa-ultimate-icon-wrap"><span>💬</span></div>
                    <div class="qa-ultimate-text"><strong>Balas Pesan</strong><small><?= $total_pesan ?> menunggu</small></div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="agenda-form.php" class="qa-ultimate-btn" style="--btn-color:#f59e0b">
                    <div class="qa-ultimate-icon-wrap"><span>📅</span></div>
                    <div class="qa-ultimate-text"><strong>Tambah Agenda</strong><small><?= $total_agenda ?> aktif</small></div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
                <a href="<?= base_url() ?>" class="qa-ultimate-btn" target="_blank" style="--btn-color:#8b5cf6">
                    <div class="qa-ultimate-icon-wrap"><span>🌐</span></div>
                    <div class="qa-ultimate-text"><strong>Website Publik</strong><small>Lihat tampilan</small></div>
                    <div class="qa-ultimate-arrow">→</div>
                </a>
            </div>
        </div>

        <!-- Top Performing Content -->
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header">
                <h2>🔥 Konten Terpopuler</h2>
                <a href="berita.php?sort=views" class="btn-sm">Lihat Semua →</a>
            </div>
            <?php if (empty($top_berita)): ?>
                <div class="empty-state-ultimate"><div class="empty-illustration">📊</div><p>Belum ada data views</p></div>
            <?php else: ?>
                <div class="top-content-list">
                    <?php foreach ($top_berita as $i => $b): 
                        $rank_colors = ['#fbbf24', '#9ca3af', '#cd7f32', '#64748b', '#64748b'];
                        $rank_icons = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                    ?>
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="top-content-item">
                        <div class="top-rank" style="background:<?= $rank_colors[$i] ?>">
                            <?= $rank_icons[$i] ?>
                        </div>
                        <div class="top-info">
                            <h4><?= sanitize($b['judul']) ?></h4>
                            <div class="top-meta">
                                <span>👁 <?= number_format($b['views']) ?> views</span>
                                <?php if ($b['published_at']): ?>
                                <span>📅 <?= date('d M Y', strtotime($b['published_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="top-views">
                            <strong><?= number_format($b['views']) ?></strong>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Latest News -->
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="150">
            <div class="card-header">
                <h2>📰 Berita Terbaru</h2>
                <a href="berita.php" class="btn-sm">Lihat Semua →</a>
            </div>
            <?php if (empty($berita_terbaru)): ?>
                <div class="empty-state-ultimate"><div class="empty-illustration">📭</div><p>Belum ada berita</p></div>
            <?php else: ?>
                <div class="berita-ultimate-list">
                    <?php foreach ($berita_terbaru as $b): 
                        $img = !empty($b['gambar']) ? asset('uploads/' . basename($b['gambar'])) : null;
                    ?>
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="berita-ultimate-item">
                        <div class="berita-ultimate-thumb">
                            <?php if ($img): ?>
                                <img src="<?= $img ?>" alt="">
                            <?php else: ?>
                                <div class="thumb-gradient"><span>📰</span></div>
                            <?php endif; ?>
                            <div class="berita-ultimate-overlay"><span>✏️ Edit</span></div>
                        </div>
                        <div class="berita-ultimate-info">
                            <h4><?= sanitize($b['judul']) ?></h4>
                            <div class="berita-ultimate-meta">
                                <span class="badge-ultimate badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                                <span>👁 <?= number_format($b['views']) ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kanban Status -->
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header">
                <h2>📋 Status Konten</h2>
                <span class="badge-ultimate badge-info"><?= $total_berita ?> total</span>
            </div>
            <div class="kanban-mini">
                <?php $arch = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Archived'")->fetchColumn(); ?>
                <div class="kanban-column draft">
                    <div class="kanban-col-header"><span class="kanban-icon">📝</span><span>Draft</span><span class="kanban-count"><?= $draft_count ?></span></div>
                    <div class="kanban-items"><?= $draft_count > 0 ? '<div class="kanban-item">Menunggu review</div>' : '<div class="kanban-empty">✓ Kosong</div>' ?></div>
                </div>
                <div class="kanban-column published">
                    <div class="kanban-col-header"><span class="kanban-icon">✅</span><span>Published</span><span class="kanban-count"><?= $published_count ?></span></div>
                    <div class="kanban-items"><?= $published_count > 0 ? '<div class="kanban-item">Aktif di website</div>' : '<div class="kanban-empty">Belum ada</div>' ?></div>
                </div>
                <div class="kanban-column archived">
                    <div class="kanban-col-header"><span class="kanban-icon">🗄️</span><span>Archived</span><span class="kanban-count"><?= $arch ?></span></div>
                    <div class="kanban-items"><?= $arch > 0 ? '<div class="kanban-item">Arsip</div>' : '<div class="kanban-empty">✓ Kosong</div>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
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
                    <?php foreach ($score_details as $sd): ?>
                    <div class="perf-detail-item <?= $sd['done'] ? 'done' : '' ?>">
                        <span class="perf-check"><?= $sd['done'] ? '✓' : '○' ?></span>
                        <span><?= $sd['text'] ?></span>
                    </div>
                    <?php endforeach; ?>
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
                            <span class="badge-ultimate badge-<?= strtolower($ag['jenis']) ?>"><?= ucfirst($ag['jenis']) ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Dosen per Prodi Chart -->
        <div class="card ultimate" data-aos="fade-left" data-aos-delay="150">
            <div class="card-header">
                <h2>👨‍🏫 Dosen per Prodi</h2>
                <a href="dosen.php" class="btn-sm">Detail →</a>
            </div>
            <div id="chartDosen" style="min-height:220px;"></div>
        </div>

        <!-- Donut Chart Kategori -->
        <div class="card ultimate donut-card" data-aos="fade-left" data-aos-delay="200">
            <div class="card-header"><h2>🍩 Distribusi Kategori</h2></div>
            <div class="donut-wrap">
                <div id="chartDonut" class="donut-chart-apex"></div>
                <div class="donut-legend" id="donutLegend">
                    <?php if (!empty($kat_stats)): ?>
                        <?php 
                        $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                        foreach ($kat_stats as $i => $k): 
                            $color = $colors[$i % count($colors)];
                            $katName = htmlspecialchars($k['kategori'], ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="legend-item" onclick="window.donutChartInstance?.toggleSeries('<?= $katName ?>')">
                                <span class="legend-color" style="background:<?= $color ?>"></span>
                                <span class="legend-label"><?= $katName ?></span>
                                <span class="legend-value"><?= $k['total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="color:#94a3b8">Belum ada data</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Server Health -->
        <div class="card ultimate system-card" data-aos="fade-left" data-aos-delay="250">
            <div class="card-header"><h2>💻 System Health</h2></div>
            <div class="system-health">
                <div class="health-item">
                    <div class="health-header">
                        <span>💾 Disk Usage</span>
                        <strong><?= $disk_usage ?>%</strong>
                    </div>
                    <div class="health-bar">
                        <div class="health-fill" style="width:<?= $disk_usage ?>%;background:<?= $disk_usage > 80 ? '#ef4444' : ($disk_usage > 60 ? '#f59e0b' : '#10b981') ?>"></div>
                    </div>
                </div>
                <div class="health-item">
                    <div class="health-header">
                        <span>🧠 Memory</span>
                        <strong><?= $memory_usage ?>%</strong>
                    </div>
                    <div class="health-bar">
                        <div class="health-fill" style="width:<?= $memory_usage ?>%;background:<?= $memory_usage > 80 ? '#ef4444' : ($memory_usage > 60 ? '#f59e0b' : '#10b981') ?>"></div>
                    </div>
                </div>
                <div class="system-info-list">
                    <div class="sys-item"><span>PHP</span><strong><?= phpversion() ?></strong></div>
                    <div class="sys-item"><span>MySQL</span><strong><?= $pdo->query("SELECT VERSION()")->fetchColumn() ?></strong></div>
                    <div class="sys-item"><span>DB Size</span><strong><?= $db_size ?> MB</strong></div>
                    <div class="sys-item"><span>Uptime</span><strong><?= $uptime_str ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== BOTTOM SECTION ===== -->
<div class="dashboard-ultimate-grid" style="margin-top:1.5rem">
    <!-- Quick Notes -->
    <div class="dashboard-col-ultimate">
        <div class="card ultimate notes-card" data-aos="fade-up">
            <div class="card-header">
                <h2>📝 Quick Notes</h2>
                <button class="btn-sm gray" onclick="clearNotes()">🗑️ Clear</button>
            </div>
            <textarea class="quick-notes-input" id="quickNotes" placeholder="Tulis catatan cepat di sini... (tersimpan otomatis)" oninput="saveNotes()"></textarea>
            <div class="notes-meta">
                <span id="notesWordCount">0 kata</span>
                <span id="notesSavedAt">Belum disimpan</span>
            </div>
        </div>
    </div>

    <!-- Pesan Terbaru -->
    <div class="dashboard-col-ultimate">
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="100">
            <div class="card-header">
                <h2>✉️ Inbox Terbaru</h2>
                <a href="kontak.php" class="btn-sm">Buka Inbox →</a>
            </div>
            <?php if (empty($pesan_terbaru)): ?>
                <div class="empty-state-ultimate"><div class="empty-illustration">📭</div><p>Inbox bersih!</p></div>
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
                        <?php if ($p['status'] === 'Baru'): ?><div class="unread-indicator"></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="dashboard-col-ultimate">
        <div class="card ultimate" data-aos="fade-up" data-aos-delay="200">
            <div class="card-header"><h2>🕐 Aktivitas Terkini</h2></div>
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

<!-- ===== COMMAND PALETTE MODAL ===== -->
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
                <a href="agenda.php" class="command-item" data-search="agenda jadwal calendar"><span class="cmd-icon">📅</span><span class="cmd-text">Kelola Agenda</span><span class="cmd-shortcut">⌘3</span></a>
                <a href="kontak.php" class="command-item" data-search="kontak pesan inbox"><span class="cmd-icon">✉️</span><span class="cmd-text">Pesan Masuk</span><span class="cmd-shortcut">⌘4</span></a>
                <a href="program-studi.php" class="command-item" data-search="program studi prodi"><span class="cmd-icon">🎓</span><span class="cmd-text">Program Studi</span><span class="cmd-shortcut">⌘5</span></a>
                <a href="dosen.php" class="command-item" data-search="dosen guru pengajar"><span class="cmd-icon">👨‍🏫</span><span class="cmd-text">Data Dosen</span><span class="cmd-shortcut">⌘6</span></a>
                <a href="berita-form.php" class="command-item" data-search="tulis baru create berita"><span class="cmd-icon">✍️</span><span class="cmd-text">Tulis Berita Baru</span><span class="cmd-shortcut">⌘7</span></a>
                <a href="agenda-form.php" class="command-item" data-search="tambah agenda event"><span class="cmd-icon">📅</span><span class="cmd-text">Tambah Agenda</span><span class="cmd-shortcut">⌘8</span></a>
            </div>
            <div class="command-section">
                <div class="command-section-label">Aksi</div>
                <a href="<?= base_url() ?>" target="_blank" class="command-item" data-search="website publik lihat view"><span class="cmd-icon">🌐</span><span class="cmd-text">Buka Website Publik</span></a>
                <button class="command-item" onclick="exportDashboard()"><span class="cmd-icon">📥</span><span class="cmd-text">Export Dashboard (PDF)</span></button>
                <button class="command-item" onclick="window.print()"><span class="cmd-icon">🖨️</span><span class="cmd-text">Print Dashboard</span></button>
                <button class="command-item" onclick="toggleDarkMode()"><span class="cmd-icon">🌙</span><span class="cmd-text">Toggle Dark Mode</span></button>
                <button class="command-item" onclick="toggleFocusMode()"><span class="cmd-icon">🎯</span><span class="cmd-text">Toggle Focus Mode</span></button>
                <a href="logout.php" class="command-item" data-search="logout keluar exit"><span class="cmd-icon">🚪</span><span class="cmd-text">Logout</span></a>
            </div>
        </div>
        <div class="command-palette-footer">
            <span>↑↓ Navigasi</span><span>↵ Pilih</span><span>ESC Tutup</span>
        </div>
    </div>
</div>

<!-- ===== GOAL MODAL ===== -->
<div class="goal-modal-overlay" id="goalModal" onclick="if(event.target===this)closeGoalModal()">
    <div class="goal-modal-box">
        <h3>🎯 Tambah Goal Baru</h3>
        <input type="text" id="goalInput" class="goal-input" placeholder="Contoh: Tulis 5 artikel minggu ini">
        <div class="goal-modal-actions">
            <button class="btn-sm" onclick="saveGoal()">💾 Simpan</button>
            <button class="btn-sm gray" onclick="closeGoalModal()">Batal</button>
        </div>
    </div>
</div>

<!-- ===== ULTIMATE STYLES (Scoped) ===== -->
<style>
/* === WELCOME HERO === */
.welcome-hero{position:relative;background:linear-gradient(135deg,#0a6847 0%,#084d35 50%,#16213e 100%);color:#fff;padding:2.5rem;border-radius:24px;overflow:hidden;margin-bottom:2rem;box-shadow:0 20px 60px rgba(10,104,71,.3)}
.welcome-bg-pattern{position:absolute;inset:0;background-image:radial-gradient(circle at 20% 30%,rgba(255,255,255,.08) 0%,transparent 50%),radial-gradient(circle at 80% 70%,rgba(245,166,35,.1) 0%,transparent 50%);pointer-events:none}
.welcome-bg-pattern::before{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,transparent,transparent 35px,rgba(255,255,255,.02) 35px,rgba(255,255,255,.02) 70px);animation:patternShift 20s linear infinite}
@keyframes patternShift{to{background-position:70px 70px}}
.welcome-content{position:relative;display:grid;grid-template-columns:1.8fr 1fr;gap:2rem;align-items:center}
.welcome-badge{display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);padding:.5rem 1rem;border-radius:999px;font-size:.8rem;font-weight:600;border:1px solid rgba(255,255,255,.2);margin-bottom:1rem;flex-wrap:wrap}
.badge-pulse{width:8px;height:8px;background:#10b981;border-radius:50%;position:relative}
.badge-pulse::after{content:'';position:absolute;inset:0;background:#10b981;border-radius:50%;animation:badgePulse 2s infinite}
.badge-alert{background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);padding:.2rem .6rem;border-radius:999px;font-size:.75rem;animation:alertBlink 1.5s infinite}
@keyframes alertBlink{0%,100%{opacity:1}50%{opacity:.5}}
@keyframes badgePulse{0%{transform:scale(1);opacity:1}100%{transform:scale(3);opacity:0}}
.welcome-title{font-size:2.5rem;font-weight:800;line-height:1.2;margin-bottom:.75rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
.greet-emoji{font-size:2.75rem;animation:bounce 2s infinite}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.admin-name{background:linear-gradient(135deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.welcome-subtitle{font-size:1.05rem;opacity:.9;margin-bottom:1.25rem;line-height:1.6}
.welcome-quote{display:flex;gap:.75rem;padding:1rem;background:rgba(255,255,255,.08);border-radius:12px;border-left:3px solid #fbbf24;font-size:.9rem;line-height:1.5;margin-bottom:1.25rem}
.quote-icon{font-size:1.25rem;flex-shrink:0}
.welcome-stats-row{display:flex;gap:1rem;flex-wrap:wrap}
.welcome-stat{background:rgba(255,255,255,.1);backdrop-filter:blur(10px);padding:.75rem 1.25rem;border-radius:12px;display:flex;align-items:center;gap:.75rem;border:1px solid rgba(255,255,255,.15)}
.welcome-stat .stat-icon{font-size:1.5rem}
.welcome-stat .stat-num{font-size:1.5rem;font-weight:800}
.welcome-stat .stat-label{font-size:.75rem;opacity:.85;display:block;margin-top:-.25rem}
.welcome-right{display:flex;flex-direction:column;gap:1rem;align-items:flex-end}
.live-clock{background:rgba(255,255,255,.1);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);padding:1.5rem;border-radius:16px;text-align:center;min-width:200px}
.clock-display{font-size:2.5rem;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em;line-height:1}
.clock-date{font-size:.85rem;opacity:.85;margin-top:.5rem}

/* === POMODORO WIDGET === */
.pomodoro-widget{background:linear-gradient(135deg,#ef4444,#dc2626);color:white;padding:1.25rem;border-radius:16px;text-align:center;width:200px;box-shadow:0 10px 30px rgba(239,68,68,.3)}
.pomodoro-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;font-size:.8rem;font-weight:600}
.pomodoro-mode{background:rgba(255,255,255,.2);padding:.2rem .6rem;border-radius:999px;font-size:.7rem}
.pomodoro-timer{font-size:2.5rem;font-weight:900;font-variant-numeric:tabular-nums;margin-bottom:.75rem}
.pomodoro-controls{display:flex;gap:.5rem;margin-bottom:.75rem}
.pomo-btn{flex:1;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:white;padding:.4rem;border-radius:8px;cursor:pointer;font-family:inherit;font-size:.8rem;font-weight:600;transition:all .2s}
.pomo-btn:hover{background:rgba(255,255,255,.3)}
.pomo-btn-secondary{background:rgba(0,0,0,.1)}
.pomodoro-sessions{font-size:.75rem;opacity:.9}

/* === FOCUS MODE === */
.focus-mode-toggle{position:fixed;bottom:2rem;left:2rem;background:var(--bg-primary);border:2px solid var(--border);border-radius:999px;padding:.75rem 1.25rem;display:flex;align-items:center;gap:.5rem;cursor:pointer;box-shadow:var(--shadow-lg);z-index:99;transition:all .3s;font-weight:600;font-size:.85rem}
.focus-mode-toggle:hover{transform:translateY(-3px);border-color:var(--primary)}
body.focus-mode .card.ultimate:not(:hover){opacity:.7;filter:blur(1px);transition:all .3s}
body.focus-mode .welcome-hero,body.focus-mode .stats-ultimate,body.focus-mode .focus-mode-toggle{opacity:1;filter:none}
body.focus-mode .stat-ultimate-card,body.focus-mode .dashboard-col-ultimate{transition:all .3s}

/* === HEATMAP === */
.heatmap-container{padding:.5rem 0}
.heatmap-grid{display:grid;grid-template-columns:repeat(15,1fr);gap:4px;margin-bottom:1rem}
.heatmap-cell{aspect-ratio:1;border-radius:3px;transition:transform .2s;position:relative;cursor:pointer;display:flex;align-items:center;justify-content:center}
.heatmap-cell:hover{transform:scale(1.3);z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.heatmap-count{font-size:.65rem;font-weight:700;color:white;text-shadow:0 1px 2px rgba(0,0,0,.3)}
.heatmap-legend{display:flex;align-items:center;gap:.5rem;justify-content:flex-end;font-size:.75rem;color:var(--text-muted);margin-bottom:1rem}
.legend-cell{width:12px;height:12px;border-radius:2px}
.heatmap-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)}
.heatmap-stat{text-align:center}
.heatmap-stat strong{display:block;font-size:1.25rem;font-weight:800;color:var(--primary);margin-bottom:.25rem}
.heatmap-stat span{font-size:.75rem;color:var(--text-muted)}

/* === GOAL TRACKER === */
.goal-card{min-height:300px}
.goal-list{display:flex;flex-direction:column;gap:.5rem;margin-bottom:1rem;max-height:200px;overflow-y:auto}
.goal-item{display:flex;align-items:center;gap:.75rem;padding:.75rem;background:var(--bg-secondary);border-radius:10px;transition:all .2s}
.goal-item:hover{background:var(--bg-tertiary)}
.goal-checkbox{width:22px;height:22px;border:2px solid var(--border);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.goal-checkbox.checked{background:var(--primary);border-color:var(--primary);color:white}
.goal-text{flex:1;font-size:.9rem}
.goal-text.completed{text-decoration:line-through;opacity:.5}
.goal-remove{opacity:0;background:none;border:none;cursor:pointer;font-size:1rem;padding:.25rem;transition:opacity .2s}
.goal-item:hover .goal-remove{opacity:.5}
.goal-remove:hover{opacity:1!important;color:#ef4444}
.goal-summary{border-top:1px solid var(--border);padding-top:1rem}
.goal-progress-bar{height:8px;background:var(--bg-tertiary);border-radius:999px;overflow:hidden;margin-bottom:.5rem}
.goal-progress-fill{height:100%;background:linear-gradient(90deg,#10b981,#059669);transition:width .5s;border-radius:999px}
.goal-progress-text{font-size:.85rem;font-weight:600;color:var(--primary)}

/* === TOP CONTENT === */
.top-content-list{display:flex;flex-direction:column;gap:.75rem}
.top-content-item{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--bg-secondary);border-radius:12px;text-decoration:none;color:inherit;transition:all .3s}
.top-content-item:hover{background:var(--bg-tertiary);transform:translateX(4px)}
.top-rank{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.top-info{flex:1;min-width:0}
.top-info h4{font-size:.95rem;margin-bottom:.4rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.top-meta{display:flex;gap:.75rem;font-size:.75rem;color:var(--text-muted);flex-wrap:wrap}
.top-views{font-size:1.5rem;font-weight:800;color:var(--primary);flex-shrink:0}

/* === QUICK NOTES === */
.quick-notes-input{width:100%;min-height:180px;border:2px solid var(--border);border-radius:12px;padding:1rem;font-family:inherit;font-size:.9rem;resize:vertical;background:var(--bg-secondary);transition:border-color .2s}
.quick-notes-input:focus{outline:none;border-color:var(--primary);background:var(--bg-primary)}
.notes-meta{display:flex;justify-content:space-between;margin-top:.75rem;font-size:.75rem;color:var(--text-muted)}

/* === SYSTEM HEALTH === */
.system-health{display:flex;flex-direction:column;gap:1rem}
.health-item{display:flex;flex-direction:column;gap:.4rem}
.health-header{display:flex;justify-content:space-between;font-size:.85rem}
.health-header strong{color:var(--primary);font-weight:700}
.health-bar{height:8px;background:var(--bg-tertiary);border-radius:999px;overflow:hidden}
.health-fill{height:100%;border-radius:999px;transition:width 1s}

/* === GOAL MODAL === */
.goal-modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;z-index:10001;padding:2rem}
.goal-modal-overlay.show{display:flex}
.goal-modal-box{background:var(--bg-primary);border-radius:16px;padding:2rem;max-width:400px;width:100%;box-shadow:0 30px 60px rgba(0,0,0,.3)}
.goal-modal-box h3{margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.goal-input{width:100%;padding:.75rem 1rem;border:2px solid var(--border);border-radius:10px;font-family:inherit;font-size:1rem;margin-bottom:1rem;background:var(--bg-secondary)}
.goal-input:focus{outline:none;border-color:var(--primary);background:var(--bg-primary)}
.goal-modal-actions{display:flex;gap:.5rem}

/* === ULTIMATE STAT CARDS === */
.stats-ultimate{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;margin-bottom:2rem}
.stat-ultimate-card{background:var(--bg-primary);border-radius:20px;padding:1.5rem;position:relative;overflow:hidden;transition:all .4s;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid var(--border)}
.stat-ultimate-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--card-accent)}
.stat-ultimate-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.1)}
.stat-ultimate-card.has-alert{animation:alertGlow 2s infinite}
@keyframes alertGlow{0%,100%{box-shadow:0 4px 20px rgba(239,68,68,.2)}50%{box-shadow:0 4px 30px rgba(239,68,68,.5)}}
.stat-ultimate-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem}
.stat-ultimate-icon{width:48px;height:48px;background:linear-gradient(135deg,var(--card-accent),color-mix(in srgb,var(--card-accent),#000 15%));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 8px 16px color-mix(in srgb,var(--card-accent),transparent 70%)}
.stat-ultimate-trend{font-size:.75rem;font-weight:700;padding:.25rem .6rem;border-radius:999px;display:inline-flex;align-items:center;gap:.2rem}
.stat-ultimate-trend.up{background:#dcfce7;color:#166534}
.stat-ultimate-trend.down{background:#fee2e2;color:#991b1b}
.stat-ultimate-trend.stable{background:#dbeafe;color:#1e40af}
.stat-ultimate-value{font-size:2.5rem;font-weight:800;line-height:1;margin-bottom:.25rem;font-variant-numeric:tabular-nums}
.stat-ultimate-label{color:var(--text-muted);font-size:.85rem;margin-bottom:.75rem}
.stat-ultimate-meta{display:flex;gap:.5rem;margin-bottom:.75rem}
.meta-pill{padding:.25rem .6rem;border-radius:999px;font-size:.7rem;font-weight:600}
.meta-pill.green{background:#dcfce7;color:#166534}
.meta-pill.blue{background:#dbeafe;color:#1e40af}
.meta-pill.amber{background:#fef3c7;color:#92400e}
.meta-pill.red{background:#fee2e2;color:#991b1b}
.meta-pill.purple{background:#f3e8ff;color:#7e22ce}
.meta-pill.pink{background:#fce7f3;color:#be185d}
.sparkline-chart{margin-top:.75rem;height:50px}
.donut-chart-apex{min-height:200px}
.stat-ultimate-progress{display:flex;align-items:center;gap:.75rem;margin-top:.5rem}
.progress-ring-mini{position:relative;width:44px;height:44px;flex-shrink:0}
.progress-ring-mini svg{transform:rotate(-90deg)}
.progress-ring-mini .ring-bg{fill:none;stroke:var(--bg-tertiary);stroke-width:3}
.progress-ring-mini .ring-fill{fill:none;stroke:var(--card-accent);stroke-width:3;stroke-linecap:round;transition:stroke-dasharray 1s ease}
.progress-ring-mini span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--card-accent)}
.stat-ultimate-progress small{font-size:.75rem;color:var(--text-muted)}
.stat-ultimate-achievements{display:flex;gap:.4rem;margin-top:.5rem}
.achievement-orb{width:32px;height:32px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.95rem;animation:orbFloat 3s ease-in-out infinite;animation-delay:var(--orb-delay)}
@keyframes orbFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
.alert-pulse-indicator{position:absolute;top:1.25rem;right:1.25rem;width:24px;height:24px;display:flex;align-items:center;justify-content:center}
.pulse-dot{width:10px;height:10px;background:#ef4444;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;position:relative;z-index:2}
.pulse-ring{position:absolute;inset:0;border:2px solid #ef4444;border-radius:50%;animation:pulseRing 2s infinite}
.pulse-ring:nth-child(2){animation-delay:.5s}
@keyframes pulseRing{0%{transform:scale(.5);opacity:1}100%{transform:scale(2);opacity:0}}

/* === ULTIMATE GRID === */
.dashboard-ultimate-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
.dashboard-col-ultimate{display:flex;flex-direction:column;gap:1.25rem}
.card.ultimate{background:var(--bg-primary);border-radius:20px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid var(--border)}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem}
.card-header h2{font-size:1.1rem;font-weight:700;display:flex;align-items:center;gap:.5rem}
.quick-actions-ultimate{display:flex;flex-direction:column;gap:.75rem}
.qa-ultimate-btn{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--bg-secondary);border-radius:14px;text-decoration:none;color:var(--text-primary);transition:all .3s;position:relative;overflow:hidden;border:1px solid transparent}
.qa-ultimate-btn::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--btn-color);transition:width .3s}
.qa-ultimate-btn:hover{background:var(--bg-tertiary);border-color:var(--btn-color);transform:translateX(4px);box-shadow:0 8px 20px rgba(0,0,0,.08)}
.qa-ultimate-btn:hover::before{width:6px}
.qa-ultimate-icon-wrap{width:44px;height:44px;background:color-mix(in srgb,var(--btn-color),var(--bg-primary) 85%);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0}
.qa-ultimate-text{flex:1;display:flex;flex-direction:column}
.qa-ultimate-text strong{font-size:.9rem}
.qa-ultimate-text small{font-size:.72rem;color:var(--text-muted)}
.qa-ultimate-arrow{color:var(--btn-color);font-size:1.1rem;opacity:.5;transition:all .3s}
.qa-ultimate-btn:hover .qa-ultimate-arrow{opacity:1;transform:translateX(4px)}
.btn-command{background:var(--text-primary);color:var(--bg-primary);border:none;padding:.35rem .75rem;border-radius:8px;font-size:.72rem;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;font-family:inherit;font-weight:600;transition:all .3s}
.btn-command:hover{background:var(--primary);color:white;transform:translateY(-1px)}
.btn-command kbd{background:rgba(255,255,255,.2);padding:.1rem .35rem;border-radius:4px;font-size:.65rem}
.berita-ultimate-list{display:flex;flex-direction:column;gap:.75rem}
.berita-ultimate-item{display:flex;gap:.85rem;padding:.75rem;background:var(--bg-secondary);border-radius:12px;text-decoration:none;color:inherit;transition:all .3s;position:relative;overflow:hidden}
.berita-ultimate-item:hover{background:var(--bg-tertiary);transform:translateX(4px)}
.berita-ultimate-thumb{width:80px;height:60px;border-radius:8px;overflow:hidden;flex-shrink:0;position:relative}
.berita-ultimate-thumb img{width:100%;height:100%;object-fit:cover}
.thumb-gradient{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;background:linear-gradient(135deg,#667eea,#764ba2)}
.berita-ultimate-overlay{position:absolute;inset:0;background:rgba(10,104,71,.85);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:.75rem;opacity:0;transition:opacity .3s}
.berita-ultimate-item:hover .berita-ultimate-overlay{opacity:1}
.berita-ultimate-info{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center}
.berita-ultimate-info h4{font-size:.9rem;margin-bottom:.4rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.berita-ultimate-meta{display:flex;gap:.5rem;align-items:center;font-size:.72rem;color:var(--text-muted);flex-wrap:wrap}
.badge-ultimate{padding:.2rem .55rem;border-radius:999px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.badge-published{background:#dcfce7;color:#166534}
.badge-draft{background:#fef3c7;color:#92400e}
.badge-archived{background:#e2e8f0;color:#475569}
.badge-ujian{background:#fee2e2;color:#991b1b}
.badge-seminar{background:#dbeafe;color:#1e40af}
.badge-wisuda{background:#fef3c7;color:#92400e}
.badge-libur{background:#e0e7ff;color:#4338ca}
.badge-info{background:#dbeafe;color:#1e40af}
.kanban-mini{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}
.kanban-column{background:var(--bg-secondary);border-radius:10px;padding:.75rem;min-height:130px;border-top:3px solid}
.kanban-column.draft{border-color:#f59e0b}
.kanban-column.published{border-color:#10b981}
.kanban-column.archived{border-color:#64748b}
.kanban-col-header{display:flex;align-items:center;gap:.35rem;font-size:.75rem;font-weight:700;margin-bottom:.6rem;padding-bottom:.4rem;border-bottom:1px solid var(--border)}
.kanban-icon{font-size:.9rem}
.kanban-count{margin-left:auto;background:var(--bg-primary);padding:.1rem .45rem;border-radius:999px;font-size:.68rem;font-weight:800}
.kanban-item{background:var(--bg-primary);padding:.6rem;border-radius:6px;font-size:.75rem;margin-bottom:.35rem;border-left:3px solid currentColor;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.kanban-column.draft .kanban-item{color:#f59e0b}
.kanban-column.published .kanban-item{color:#10b981}
.kanban-column.archived .kanban-item{color:#64748b}
.kanban-empty{text-align:center;padding:.75rem .5rem;color:var(--text-muted);font-size:.72rem;font-style:italic}
.performance-wrap{display:grid;grid-template-columns:auto 1fr;gap:1.25rem;align-items:center}
.performance-ring{position:relative;width:120px;height:120px;flex-shrink:0}
.performance-ring svg{transform:rotate(-90deg);width:100%;height:100%}
.perf-ring-bg{fill:none;stroke:var(--bg-tertiary);stroke-width:10}
.perf-ring-fill{fill:none;stroke:url(#perfGradient);stroke-width:10;stroke-linecap:round;transition:stroke-dasharray 1.5s ease}
.performance-value{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.perf-num{font-size:2rem;font-weight:800;line-height:1;color:var(--primary)}
.perf-max{font-size:.75rem;color:var(--text-muted)}
.performance-details{display:flex;flex-direction:column;gap:.5rem}
.perf-detail-item{display:flex;align-items:center;gap:.5rem;font-size:.82rem;padding:.35rem;border-radius:6px;transition:all .3s}
.perf-detail-item:hover{background:var(--bg-secondary)}
.perf-check{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;background:var(--bg-tertiary);color:var(--text-muted)}
.perf-detail-item.done .perf-check{background:#10b981;color:#fff}
.calendar-card .today-date{font-size:.75rem;color:var(--text-muted);background:var(--bg-secondary);padding:.25rem .6rem;border-radius:999px}
.agenda-list{display:flex;flex-direction:column;gap:.65rem}
.agenda-item{display:flex;gap:.75rem;padding:.75rem;background:var(--bg-secondary);border-radius:10px;transition:all .3s}
.agenda-item:hover{background:var(--bg-tertiary);transform:translateX(4px)}
.agenda-time{width:48px;flex-shrink:0;text-align:center;background:linear-gradient(135deg,#0a6847,#16a34a);color:#fff;border-radius:8px;padding:.4rem .25rem;display:flex;flex-direction:column}
.agenda-day{font-size:1.2rem;font-weight:800;line-height:1}
.agenda-month{font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;margin-top:.15rem}
.agenda-content{flex:1;min-width:0}
.agenda-content h4{font-size:.85rem;margin-bottom:.35rem}
.agenda-empty{text-align:center;padding:1.5rem .75rem;color:var(--text-muted)}
.agenda-empty-icon{font-size:2.5rem;margin-bottom:.4rem;opacity:.5}
.agenda-empty small{font-size:.72rem;opacity:.75}
.donut-wrap{display:grid;grid-template-columns:140px 1fr;gap:1.25rem;align-items:center}
.donut-legend{display:flex;flex-direction:column;gap:.4rem}
.legend-item{display:flex;align-items:center;gap:.4rem;font-size:.8rem;padding:.35rem;border-radius:6px;transition:background .2s;cursor:pointer}
.legend-item:hover{background:var(--bg-secondary)}
.legend-color{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.legend-label{flex:1;color:var(--text-secondary)}
.legend-value{font-weight:700;color:var(--primary);background:#dcfce7;padding:.1rem .45rem;border-radius:999px;font-size:.72rem}
.system-info-list{display:flex;flex-direction:column;gap:.4rem;margin-top:.75rem}
.sys-item{display:flex;justify-content:space-between;align-items:center;padding:.55rem .75rem;background:var(--bg-secondary);border-radius:8px;font-size:.8rem;transition:all .3s}
.sys-item:hover{background:var(--bg-tertiary);transform:translateX(3px)}
.sys-item span{color:var(--text-muted)}
.sys-item strong{font-family:ui-monospace,monospace;font-size:.75rem}
.pesan-ultimate-list{display:flex;flex-direction:column;gap:.65rem}
.pesan-ultimate-item{display:flex;gap:.75rem;padding:.85rem;background:var(--bg-secondary);border-radius:12px;transition:all .3s;position:relative}
.pesan-ultimate-item:hover{background:var(--bg-tertiary)}
.pesan-ultimate-item.unread{background:rgba(59,130,246,.08);border-left:3px solid #3b82f6}
.pesan-ultimate-avatar{width:42px;height:42px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;font-size:1rem}
.pesan-ultimate-body{flex:1;min-width:0}
.pesan-ultimate-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem}
.pesan-ultimate-head strong{font-size:.9rem}
.pesan-ultimate-head small{font-size:.68rem;color:var(--text-muted)}
.pesan-ultimate-subject{font-size:.82rem;color:var(--primary);font-weight:600;margin-bottom:.2rem}
.pesan-ultimate-body p{font-size:.78rem;color:var(--text-muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0}
.unread-indicator{position:absolute;top:.85rem;right:.85rem;width:8px;height:8px;background:#3b82f6;border-radius:50%;animation:unreadPulse 2s infinite}
@keyframes unreadPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.7}}
.timeline-ultimate{display:flex;flex-direction:column;gap:0;padding-left:.4rem}
.timeline-ultimate-item{display:flex;gap:.75rem;position:relative;padding-bottom:1rem}
.timeline-ultimate-item:last-child{padding-bottom:0}
.timeline-ultimate-item:last-child .timeline-ultimate-line{display:none}
.timeline-ultimate-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:.25rem;position:relative;z-index:2;box-shadow:0 0 0 3px var(--bg-primary)}
.timeline-ultimate-line{position:absolute;left:5px;top:12px;bottom:0;width:2px;background:var(--bg-tertiary);z-index:1}
.timeline-ultimate-content{flex:1;display:flex;gap:.6rem;align-items:flex-start;background:var(--bg-secondary);padding:.7rem .85rem;border-radius:10px;transition:all .3s}
.timeline-ultimate-content:hover{background:var(--bg-tertiary);transform:translateX(4px)}
.timeline-ultimate-icon{font-size:1.1rem;flex-shrink:0}
.timeline-ultimate-content p{font-size:.82rem;margin:0 0 .15rem;line-height:1.4}
.timeline-ultimate-content small{font-size:.68rem;color:var(--text-muted)}
.empty-state-ultimate{text-align:center;padding:2rem .75rem;color:var(--text-muted)}
.empty-illustration{font-size:3.5rem;margin-bottom:.6rem;opacity:.4;animation:emptyFloat 3s ease-in-out infinite}
@keyframes emptyFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.command-palette-overlay{position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);display:none;align-items:flex-start;justify-content:center;padding-top:15vh;z-index:10000;animation:cmdFadeIn .2s}
.command-palette-overlay.open{display:flex}
@keyframes cmdFadeIn{from{opacity:0}to{opacity:1}}
.command-palette-box{background:var(--bg-primary);border-radius:16px;width:90%;max-width:600px;box-shadow:0 30px 80px rgba(0,0,0,.4);overflow:hidden;animation:cmdSlideDown .3s}
@keyframes cmdSlideDown{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
.command-palette-header{display:flex;align-items:center;gap:.65rem;padding:1rem 1.25rem;border-bottom:1px solid var(--border);background:var(--bg-secondary)}
.cmd-search-icon{font-size:1.1rem}
.command-input{flex:1;border:none;outline:none;font-size:.95rem;font-family:inherit;background:transparent;color:var(--text-primary)}
.command-palette-header kbd{background:var(--bg-tertiary);padding:.2rem .45rem;border-radius:5px;font-size:.68rem;font-weight:700;color:var(--text-muted)}
.command-palette-body{max-height:400px;overflow-y:auto;padding:.65rem}
.command-section{margin-bottom:.85rem}
.command-section-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;padding:.4rem .65rem;margin-bottom:.2rem}
.command-item{display:flex;align-items:center;gap:.75rem;padding:.65rem .75rem;border-radius:8px;text-decoration:none;color:var(--text-primary);cursor:pointer;background:transparent;border:none;width:100%;text-align:left;font-family:inherit;font-size:.85rem;transition:all .15s}
.command-item:hover,.command-item.active{background:var(--bg-secondary)}
.cmd-icon{font-size:1rem;width:22px;text-align:center}
.cmd-text{flex:1}
.cmd-shortcut{font-size:.68rem;color:var(--text-muted);background:var(--bg-secondary);padding:.15rem .35rem;border-radius:4px;font-family:ui-monospace,monospace}
.command-palette-footer{display:flex;gap:.85rem;padding:.65rem 1.25rem;border-top:1px solid var(--border);background:var(--bg-secondary);font-size:.72rem;color:var(--text-muted)}
@media(max-width:1024px){
    .stats-ultimate{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:968px){
    .welcome-content{grid-template-columns:1fr}
    .welcome-right{align-items:flex-start}
    .welcome-title{font-size:1.75rem}
    .stats-ultimate{grid-template-columns:repeat(2,1fr)}
    .dashboard-ultimate-grid{grid-template-columns:1fr}
    .performance-wrap{grid-template-columns:1fr;text-align:center}
    .donut-wrap{grid-template-columns:1fr;text-align:center}
    .donut-chart-apex{margin:0 auto}
    .kanban-mini{grid-template-columns:1fr}
    .heatmap-grid{grid-template-columns:repeat(10,1fr)}
    .heatmap-stats{grid-template-columns:1fr}
}
@media(max-width:640px){
    .stats-ultimate{grid-template-columns:1fr}
    .heatmap-grid{grid-template-columns:repeat(6,1fr)}
    .welcome-stats-row{flex-direction:column}
}
@media print{
    .focus-mode-toggle,.command-palette-overlay,.goal-modal-overlay{display:none!important}
    body.focus-mode *{opacity:1!important;filter:none!important}
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

<!-- ===== ULTIMATE SCRIPTS ===== -->
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

// === APEXCHARTS: SPARKLINE ===
new ApexCharts(document.querySelector("#chartBerita"), {
    series: [{ data: <?= json_encode($sparkline_berita) ?> }],
    chart: { type: 'area', height: 50, sparkline: { enabled: true }, animations: { enabled: true, easing: 'easeinout', speed: 800 } },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
    colors: ['#10b981'],
    tooltip: { fixed: { enabled: false }, x: { show: false }, y: { title: { formatter: () => 'Berita' } }, marker: { show: false } }
}).render();

// === APEXCHARTS: DONUT ===
const donutOptions = {
    series: <?= json_encode($kat_data ?: [1]) ?>,
    labels: <?= json_encode($kat_labels ?: ['Belum ada data']) ?>,
    chart: { type: 'donut', height: 200, animations: { enabled: true, easing: 'easeinout', speed: 800 } },
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    plotOptions: { pie: { donut: { size: '75%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $kat_total ?> } } } } },
    dataLabels: { enabled: false },
    legend: { show: false },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 2 }
};
window.donutChartInstance = new ApexCharts(document.querySelector("#chartDonut"), donutOptions);
window.donutChartInstance.render();

// === APEXCHARTS: DOSEN PER PRODI ===
const dosenData = <?= json_encode($dosen_per_prodi) ?>;
if (dosenData.length > 0) {
    new ApexCharts(document.querySelector("#chartDosen"), {
        series: [{
            name: 'Jumlah Dosen',
            data: dosenData.map(d => d.jumlah)
        }],
        chart: { type: 'bar', height: 220, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, horizontal: true, distributed: true } },
        colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: dosenData.map(d => d.prodi.length > 20 ? d.prodi.substring(0,20) + '...' : d.prodi), labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        legend: { show: false },
        tooltip: { y: { formatter: v => v + ' dosen' } }
    }).render();
}

// === POMODORO TIMER ===
let pomoTime = 25 * 60;
let pomoInterval = null;
let pomoRunning = false;
const pomoModes = [
    { name: 'Fokus', time: 25 * 60, color: '#ef4444' },
    { name: 'Istirahat Pendek', time: 5 * 60, color: '#10b981' },
    { name: 'Istirahat Panjang', time: 15 * 60, color: '#3b82f6' }
];
let pomoModeIndex = 0;

function updatePomoDisplay() {
    const m = Math.floor(pomoTime / 60);
    const s = pomoTime % 60;
    document.getElementById('pomodoroTimer').textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    document.getElementById('pomodoroMode').textContent = pomoModes[pomoModeIndex].name;
    document.getElementById('pomodoroWidget').style.background = `linear-gradient(135deg, ${pomoModes[pomoModeIndex].color}, ${pomoModes[pomoModeIndex].color}dd)`;
}

function togglePomodoro() {
    if (pomoRunning) {
        clearInterval(pomoInterval);
        pomoRunning = false;
        document.getElementById('pomoToggle').textContent = '▶️ Lanjut';
    } else {
        pomoRunning = true;
        document.getElementById('pomoToggle').textContent = '⏸️ Pause';
        pomoInterval = setInterval(() => {
            pomoTime--;
            if (pomoTime <= 0) {
                clearInterval(pomoInterval);
                pomoRunning = false;
                document.getElementById('pomoToggle').textContent = '▶️ Mulai';
                
                // Increment session count
                let count = parseInt(localStorage.getItem('pomoCount') || '0');
                if (pomoModeIndex === 0) {
                    count++;
                    localStorage.setItem('pomoCount', count);
                    document.getElementById('pomoCount').textContent = count;
                }
                
                // Switch to next mode
                pomoModeIndex = (pomoModeIndex + 1) % pomoModes.length;
                pomoTime = pomoModes[pomoModeIndex].time;
                updatePomoDisplay();
                
                // Notify
                if (Notification.permission === 'granted') {
                    new Notification('🍅 Pomodoro', { body: `${pomoModes[(pomoModeIndex - 1 + pomoModes.length) % pomoModes.length].name} selesai!` });
                } else {
                    alert(`🍅 ${pomoModes[(pomoModeIndex - 1 + pomoModes.length) % pomoModes.length].name} selesai!`);
                }
            }
            updatePomoDisplay();
        }, 1000);
    }
}

function resetPomodoro() {
    clearInterval(pomoInterval);
    pomoRunning = false;
    pomoModeIndex = 0;
    pomoTime = pomoModes[0].time;
    document.getElementById('pomoToggle').textContent = '▶️ Mulai';
    updatePomoDisplay();
}

// Init pomodoro
document.getElementById('pomoCount').textContent = localStorage.getItem('pomoCount') || '0';
updatePomoDisplay();

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

// === GOAL TRACKER ===
let goals = JSON.parse(localStorage.getItem('fkip_goals') || '[]');

function renderGoals() {
    const list = document.getElementById('goalList');
    if (goals.length === 0) {
        list.innerHTML = '<div class="empty-state-ultimate" style="padding:1.5rem"><p>Belum ada goal</p><small>Klik "+ Tambah" untuk membuat goal pertama</small></div>';
    } else {
        list.innerHTML = goals.map((g, i) => `
            <div class="goal-item">
                <div class="goal-checkbox ${g.done ? 'checked' : ''}" onclick="toggleGoal(${i})">
                    ${g.done ? '✓' : ''}
                </div>
                <span class="goal-text ${g.done ? 'completed' : ''}">${g.text}</span>
                <button class="goal-remove" onclick="removeGoal(${i})">×</button>
            </div>
        `).join('');
    }
    updateGoalProgress();
}

function toggleGoal(i) {
    goals[i].done = !goals[i].done;
    localStorage.setItem('fkip_goals', JSON.stringify(goals));
    renderGoals();
}

function removeGoal(i) {
    goals.splice(i, 1);
    localStorage.setItem('fkip_goals', JSON.stringify(goals));
    renderGoals();
}

function addGoal() {
    document.getElementById('goalModal').classList.add('show');
    document.getElementById('goalInput').focus();
}

function closeGoalModal() {
    document.getElementById('goalModal').classList.remove('show');
    document.getElementById('goalInput').value = '';
}

function saveGoal() {
    const text = document.getElementById('goalInput').value.trim();
    if (text) {
        goals.push({ text, done: false, created: Date.now() });
        localStorage.setItem('fkip_goals', JSON.stringify(goals));
        renderGoals();
        closeGoalModal();
    }
}

function updateGoalProgress() {
    const total = goals.length;
    const done = goals.filter(g => g.done).length;
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;
    document.getElementById('goalProgressFill').style.width = percent + '%';
    document.getElementById('goalProgressText').textContent = `${percent}% selesai (${done}/${total})`;
}

document.getElementById('goalInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') saveGoal();
    if (e.key === 'Escape') closeGoalModal();
});

renderGoals();

// === QUICK NOTES ===
const notesArea = document.getElementById('quickNotes');
const savedNotes = localStorage.getItem('fkip_notes');
if (savedNotes) {
    notesArea.value = savedNotes;
    updateNotesMeta();
}

function saveNotes() {
    localStorage.setItem('fkip_notes', notesArea.value);
    updateNotesMeta();
}

function clearNotes() {
    if (confirm('Hapus semua catatan?')) {
        notesArea.value = '';
        localStorage.removeItem('fkip_notes');
        updateNotesMeta();
    }
}

function updateNotesMeta() {
    const words = notesArea.value.trim().split(/\s+/).filter(w => w).length;
    document.getElementById('notesWordCount').textContent = words + ' kata';
    document.getElementById('notesSavedAt').textContent = 'Tersimpan: ' + new Date().toLocaleTimeString('id-ID');
}

// === FOCUS MODE ===
function toggleFocusMode() {
    document.body.classList.toggle('focus-mode');
    const icon = document.getElementById('focusIcon');
    const label = document.getElementById('focusLabel');
    if (document.body.classList.contains('focus-mode')) {
        icon.textContent = '👁️';
        label.textContent = 'Exit Focus';
        localStorage.setItem('fkip_focus', '1');
    } else {
        icon.textContent = '🎯';
        label.textContent = 'Focus Mode';
        localStorage.removeItem('fkip_focus');
    }
}

if (localStorage.getItem('fkip_focus') === '1') {
    document.body.classList.add('focus-mode');
    document.getElementById('focusIcon').textContent = '👁️';
    document.getElementById('focusLabel').textContent = 'Exit Focus';
}

// === COMMAND PALETTE ===
const palette = document.getElementById('commandPalette');
const cmdInput = document.getElementById('commandInput');

function openCommandPalette() {
    if (palette) {
        palette.classList.add('open');
        setTimeout(() => cmdInput?.focus(), 50);
    }
}
function closeCommandPalette() {
    if (palette) {
        palette.classList.remove('open');
        if (cmdInput) cmdInput.value = '';
    }
}

function exportDashboard() {
    alert('📥 Fitur export PDF akan segera tersedia!\n\nSementara, gunakan Ctrl+P untuk print dashboard.');
    closeCommandPalette();
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (palette?.classList.contains('open')) closeCommandPalette();
        else openCommandPalette();
    }
    if (e.key === 'Escape' && palette?.classList.contains('open')) {
        closeCommandPalette();
    }
    // Shortcuts ⌘1-8
    if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '8') {
        const idx = parseInt(e.key);
        const items = document.querySelectorAll('.command-item');
        if (items[idx - 1]) {
            e.preventDefault();
            items[idx - 1].click();
        }
    }
});

cmdInput?.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.command-item');
    const sections = document.querySelectorAll('.command-section');
    items.forEach(item => {
        const search = (item.dataset.search || '') + ' ' + item.textContent.toLowerCase();
        item.style.display = search.includes(q) ? 'flex' : 'none';
    });
    sections.forEach(sec => {
        const visible = [...sec.querySelectorAll('.command-item')].some(i => i.style.display !== 'none');
        sec.style.display = visible ? 'block' : 'none';
    });
});

// === DARK MODE TOGGLE ===
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('admin_dark', document.body.classList.contains('dark-mode') ? '1' : '0');
    closeCommandPalette();
}
if (localStorage.getItem('admin_dark') === '1') {
    document.body.classList.add('dark-mode');
}

// === HEATMAP TOOLTIPS ===
document.querySelectorAll('.heatmap-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        const count = this.dataset.count;
        const title = this.getAttribute('title');
        if (count > 0) {
            showToast(title, 'info');
        }
    });
});

// === TOAST NOTIFICATION ===
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed;top:100px;right:2rem;z-index:10001;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.style.cssText = `background:var(--bg-primary);border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem;box-shadow:0 10px 30px rgba(0,0,0,.15);display:flex;align-items:center;gap:.75rem;min-width:280px;animation:slideInRight .3s;border-left:4px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'}`;
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };
    toast.innerHTML = `<span style="font-size:1.25rem">${icons[type]}</span><span style="font-size:.9rem;font-weight:600">${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all .3s'; setTimeout(() => toast.remove(), 300); }, 3500);
}

console.log('%c🎓 FKIP UNIMOF Admin Ultimate Dashboard', 'color:#0a6847;font-size:20px;font-weight:bold');
console.log('%cShortcuts: Ctrl+K (Command), Ctrl+1-8 (Quick nav), Ctrl+P (Print)', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>