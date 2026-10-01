<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Agenda & Kalender Akademik';
$page_description = 'Jadwal kegiatan, ujian, seminar, wisuda, dan libur FKIP UNIMOF.';

// ===== SCHEMA-SAFE: deteksi kolom agenda =====
$has_agenda_end = false;
$has_agenda_loc = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `agenda`")->fetchAll(PDO::FETCH_COLUMN);
    $has_agenda_end = in_array('tanggal_selesai', $cols, true);
    $has_agenda_loc = in_array('lokasi', $cols, true);
} catch (Exception $e) {}

// ===== FILTER & SEARCH =====
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$period = $_GET['period'] ?? 'all'; // all, upcoming, past, month
$view   = $_GET['view']   ?? 'timeline'; // timeline, cards, calendar

$where = "WHERE status = 'Aktif'";
$params = [];

if ($filter !== 'all') {
    $where .= " AND jenis = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $where .= " AND (judul LIKE ? OR " . ($has_agenda_loc ? "lokasi LIKE ? OR " : "") . "deskripsi LIKE ?)";
    if ($has_agenda_loc) {
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}
if ($period === 'upcoming') {
    $where .= " AND tanggal_mulai >= CURDATE()";
} elseif ($period === 'past') {
    $where .= " AND tanggal_mulai < CURDATE()";
} elseif ($period === 'month') {
    $where .= " AND MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE())";
}

$sql = "SELECT * FROM agenda $where ORDER BY tanggal_mulai ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agenda = $stmt->fetchAll();

// ===== STATS PER KATEGORI =====
$cat_stats = [];
try {
    $rows = $pdo->query("SELECT jenis, COUNT(*) as total FROM agenda WHERE status='Aktif' GROUP BY jenis")->fetchAll();
    foreach ($rows as $r) $cat_stats[$r['jenis']] = (int)$r['total'];
} catch (Exception $e) {}

$stat_total = count($agenda);
$stat_upcoming = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai >= CURDATE() AND status='Aktif'")->fetchColumn();
$stat_this_month = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE()) AND status='Aktif'")->fetchColumn();

// ===== SPOTLIGHT NEXT EVENT =====
$next_event = $pdo->query("SELECT * FROM agenda WHERE tanggal_mulai >= CURDATE() AND status='Aktif' ORDER BY tanggal_mulai ASC LIMIT 1")->fetch();

// ===== GROUP BY MONTH (untuk timeline) =====
$grouped = [];
foreach ($agenda as $a) {
    $key = date('Y-m', strtotime($a['tanggal_mulai']));
    $grouped[$key][] = $a;
}
krsort($grouped);

// ===== CALENDAR VIEW DATA =====
$calendar_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $calendar_month)) $calendar_month = date('Y-m');
$cal_ts = strtotime($calendar_month . '-01');
$cal_year = (int)date('Y', $cal_ts);
$cal_mon  = (int)date('m', $cal_ts);
$cal_days_in_month = (int)date('t', $cal_ts);
$cal_first_dow = (int)date('w', $cal_ts); // 0=Sun
$cal_prev = date('Y-m', strtotime('-1 month', $cal_ts));
$cal_next = date('Y-m', strtotime('+1 month', $cal_ts));

// Events di bulan terpilih
$cal_events = [];
try {
    $q = $pdo->prepare("SELECT * FROM agenda WHERE status='Aktif' AND YEAR(tanggal_mulai)=? AND MONTH(tanggal_mulai)=? ORDER BY tanggal_mulai ASC");
    $q->execute([$cal_year, $cal_mon]);
    $cal_events = $q->fetchAll();
} catch (Exception $e) {}

// Map events by day
$cal_by_day = [];
foreach ($cal_events as $e) {
    $d = (int)date('j', strtotime($e['tanggal_mulai']));
    $cal_by_day[$d][] = $e;
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.agenda-hero-extreme {
    position: relative;
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 50%, #4c1d95 100%);
    color: white; padding: 8rem 0 6rem; overflow: hidden;
}
.agenda-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.15) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.agenda-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.agenda-hero-extreme .container { position: relative; z-index: 2; }
.agenda-hero-extreme .breadcrumb a, .agenda-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.agenda-hero-extreme .breadcrumb a:hover { color: white; }

.hero-live-row {
    display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem;
    justify-content: center; align-items: center;
}
.hero-countdown-box {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.25);
    padding: 0.75rem 1.25rem; border-radius: 16px;
    display: flex; gap: 1rem; align-items: center;
}
.countdown-item { text-align: center; min-width: 42px; }
.countdown-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.35rem; font-weight: 900; line-height: 1;
    font-variant-numeric: tabular-nums;
}
.countdown-label {
    font-size: 0.62rem; text-transform: uppercase;
    letter-spacing: 0.08em; opacity: 0.85; margin-top: 0.25rem;
}
.countdown-sep { font-size: 1.25rem; opacity: 0.6; font-weight: 800; }

/* ===== SPOTLIGHT ===== */
.spotlight-card {
    max-width: 900px; margin: -4rem auto 3rem; position: relative; z-index: 10;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-xl);
    display: grid; grid-template-columns: auto 1fr auto; gap: 2rem; align-items: center;
}
.spotlight-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.spotlight-title { font-family: var(--font-display); font-size: 1.65rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.spotlight-meta { display: flex; gap: 1.25rem; color: var(--text-muted); font-size: 0.88rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.spotlight-meta span { display: flex; align-items: center; gap: 0.4rem; }
.spotlight-desc { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem; }
.spotlight-visual {
    width: 120px; height: 120px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    box-shadow: 0 10px 30px rgba(139,92,246,0.3);
}
.spotlight-day { font-size: 2.5rem; font-weight: 900; line-height: 1; }
.spotlight-month { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.2rem; }
.spotlight-actions { display: flex; flex-direction: column; gap: 0.5rem; }
.spotlight-btn {
    padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-primary); text-decoration: none;
    font-size: 0.78rem; font-weight: 600; display: flex; align-items: center; gap: 0.35rem;
    transition: all 0.2s;
}
.spotlight-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== STATS ===== */
.agenda-stats {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin-bottom: 2.5rem;
}
.agenda-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem; display: flex;
    align-items: center; gap: 1rem; transition: all 0.3s;
}
.agenda-stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--stat-color, var(--primary)); }
.agenda-stat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: var(--stat-color, var(--primary));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: white; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.agenda-stat-num {
    font-family: var(--font-display); font-size: 1.75rem;
    font-weight: 900; color: var(--stat-color, var(--primary));
    line-height: 1; font-variant-numeric: tabular-nums;
}
.agenda-stat-label {
    font-size: 0.72rem; color: var(--text-muted);
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-top: 0.2rem;
}

/* ===== TOOLBAR ===== */
.agenda-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.agenda-search { flex: 1; min-width: 240px; position: relative; }
.agenda-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.agenda-search input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(139,92,246,0.1); }
.agenda-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.agenda-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.agenda-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.agenda-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.agenda-select:focus { outline: none; border-color: var(--primary); }

.view-toggle {
    display: flex; background: var(--bg-secondary);
    border-radius: var(--radius-md); padding: 0.25rem; border: 1px solid var(--border);
}
.view-btn {
    padding: 0.5rem 0.85rem; border-radius: 7px; border: none; background: transparent;
    cursor: pointer; font-size: 0.82rem; font-weight: 600; color: var(--text-muted);
    transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem;
    font-family: inherit;
}
.view-btn.active { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

/* ===== FILTER PILLS ===== */
.filter-pills {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem;
    padding: 0.5rem; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.filter-pill {
    padding: 0.55rem 1rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.filter-pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.filter-pill.active {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white; border-color: #6d28d9;
    box-shadow: 0 4px 12px rgba(109,40,217,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TIMELINE ===== */
.timeline-extreme-pub { max-width: 900px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-extreme-pub::before {
    content: ''; position: absolute; left: 24px; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #8b5cf6, #6d28d9, transparent);
    border-radius: 4px;
}
.timeline-month-group { margin-bottom: 3rem; }
.timeline-month-label {
    position: sticky; top: 80px; z-index: 5; background: var(--bg-primary);
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;
    padding: 0.5rem 0.75rem 0.5rem 0; padding-left: 4rem;
}
.timeline-month-label::before {
    content: ''; position: absolute; left: 24px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    border: 4px solid var(--bg-primary); box-shadow: 0 0 0 2px #6d28d9;
}
.timeline-month-label h3 {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    color: var(--text-primary); margin: 0;
}
.timeline-month-label .month-count {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white;
    padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.72rem;
    font-weight: 700;
}

.timeline-item-pub { display: flex; gap: 2rem; margin-bottom: 2rem; position: relative; }
.timeline-dot-pub {
    width: 52px; height: 52px; background: var(--bg-primary);
    border: 4px solid var(--primary); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0; z-index: 2;
    box-shadow: 0 4px 15px rgba(139,92,246,0.2); transition: all 0.3s;
}
.timeline-dot-pub[data-jenis="ujian"] { border-color: #dc2626; }
.timeline-dot-pub[data-jenis="seminar"] { border-color: #2563eb; }
.timeline-dot-pub[data-jenis="wisuda"] { border-color: #d97706; }
.timeline-dot-pub[data-jenis="libur"] { border-color: #4338ca; }
.timeline-dot-pub[data-jenis="pmb"] { border-color: #16a34a; }

.timeline-item-pub:hover .timeline-dot-pub { transform: scale(1.1); box-shadow: 0 6px 20px rgba(139,92,246,0.3); }
.timeline-card-pub {
    flex: 1; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem; transition: all 0.3s;
    position: relative;
}
.timeline-card-pub::before {
    content: ''; position: absolute; left: -10px; top: 1.5rem; width: 20px; height: 20px;
    background: var(--bg-primary); border-left: 1px solid var(--border); border-bottom: 1px solid var(--border);
    transform: rotate(45deg);
}
.timeline-item-pub:hover .timeline-card-pub {
    transform: translateX(8px); box-shadow: var(--shadow-lg); border-color: #8b5cf6;
}
.timeline-date-pub {
    font-family: var(--font-display); font-size: 0.92rem; font-weight: 700;
    color: #8b5cf6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.timeline-countdown {
    font-size: 0.7rem; padding: 0.15rem 0.55rem;
    background: #fef3c7; color: #92400e; border-radius: 999px;
    font-weight: 700; font-family: monospace;
}
.timeline-countdown.past { background: #f3f4f6; color: #6b7280; }
.timeline-card-pub h3 {
    font-size: 1.15rem; font-weight: 700; margin-bottom: 0.75rem;
    color: var(--text-primary); font-family: var(--font-display);
}
.timeline-card-pub p {
    color: var(--text-secondary); font-size: 0.92rem; line-height: 1.7; margin-bottom: 0.75rem;
}
.timeline-meta-row {
    display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem;
    color: var(--text-secondary); margin-bottom: 0.75rem;
}
.timeline-meta-row span { display: flex; align-items: center; gap: 0.35rem; }
.timeline-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.timeline-action-btn {
    padding: 0.35rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
    background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border);
    text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;
    transition: all 0.2s; cursor: pointer;
}
.timeline-action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* ===== BADGES ===== */
.badge-jenis-extreme {
    display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.85rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-ujian { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
.badge-seminar { background: #dbeafe; color: #2563eb; border: 1px solid #93c5fd; }
.badge-wisuda { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
.badge-libur { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
.badge-pmb { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }
.badge-umum { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

/* ===== CARDS VIEW ===== */
.cards-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem; }
.event-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; transition: all 0.3s;
    cursor: pointer; position: relative;
}
.event-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--card-color, #8b5cf6);
}
.event-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); border-color: var(--card-color, #8b5cf6); }
.event-card-header {
    padding: 1.25rem 1.25rem 0.75rem; display: flex; justify-content: space-between;
    align-items: flex-start; gap: 0.75rem;
}
.event-card-date {
    width: 60px; height: 60px; border-radius: 12px;
    background: linear-gradient(135deg, var(--card-color, #8b5cf6), var(--card-color-light, #6d28d9));
    color: white; display: flex; flex-direction: column;
    align-items: center; justify-content: center; flex-shrink: 0;
}
.event-card-date .ecd-day { font-size: 1.6rem; font-weight: 900; line-height: 1; }
.event-card-date .ecd-month { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 0.1rem; }
.event-card-badge { align-self: flex-start; }
.event-card-body { padding: 0 1.25rem 1.25rem; }
.event-card-title {
    font-family: var(--font-display); font-size: 1.05rem; font-weight: 700;
    color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.event-card-meta {
    display: flex; flex-direction: column; gap: 0.35rem;
    font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.75rem;
}
.event-card-meta span { display: flex; align-items: center; gap: 0.35rem; }
.event-card-excerpt {
    font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ===== CALENDAR VIEW ===== */
.calendar-view {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm);
}
.calendar-header {
    padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    color: white; display: flex; justify-content: space-between; align-items: center;
}
.calendar-nav {
    display: flex; gap: 0.5rem; align-items: center;
}
.cal-nav-btn {
    width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.15);
    border: none; color: white; cursor: pointer; text-decoration: none;
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    transition: all 0.2s;
}
.cal-nav-btn:hover { background: rgba(255,255,255,0.25); }
.calendar-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; }
.calendar-grid {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px;
    background: var(--border);
}
.cal-day-name {
    background: var(--bg-secondary); padding: 0.65rem; text-align: center;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--text-muted);
}
.cal-day {
    background: var(--bg-primary); min-height: 90px; padding: 0.5rem;
    position: relative; transition: background 0.2s;
}
.cal-day:hover { background: var(--bg-secondary); }
.cal-day.other-month { background: var(--bg-tertiary); opacity: 0.4; }
.cal-day.today { background: #f5f3ff; }
.cal-day.today .cal-day-num { background: #8b5cf6; color: white; }
.cal-day-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%; font-size: 0.82rem;
    font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;
}
.cal-events { display: flex; flex-direction: column; gap: 0.2rem; }
.cal-event-pill {
    padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.65rem;
    font-weight: 600; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; cursor: pointer; color: white;
    background: var(--pill-color, #8b5cf6);
}
.cal-event-pill:hover { filter: brightness(1.1); transform: translateY(-1px); }
.cal-event-more {
    font-size: 0.62rem; color: var(--text-muted); font-weight: 600;
    padding: 0.1rem 0.35rem;
}

/* ===== CHART VIEW ===== */
.chart-view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.chart-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1rem; font-weight: 700; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}

/* ===== EMPTY STATE ===== */
.empty-state-premium {
    text-align: center; padding: 4rem 2rem; background: var(--bg-secondary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.empty-icon-lg {
    font-size: 5rem; margin-bottom: 1rem; opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
.empty-state-premium h3 { font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 0.5rem; }
.empty-state-premium p { color: var(--text-muted); margin-bottom: 1.25rem; }

/* ===== TOAST ===== */
.pub-toast {
    position: fixed; bottom: 2rem; right: 2rem;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: 12px; padding: 0.9rem 1.25rem;
    box-shadow: var(--shadow-lg); display: flex; align-items: center;
    gap: 0.75rem; z-index: 10002;
    transform: translateY(150%); transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    max-width: 320px;
}
.pub-toast.show { transform: translateY(0); }
.pub-toast-icon {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
    background: #dcfce7; color: #166534;
}

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.modal-header-event {
    padding: 2rem; position: relative; overflow: hidden;
    background: linear-gradient(135deg, var(--modal-color, #8b5cf6), var(--modal-color-light, #6d28d9));
    color: white; border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-event::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.modal-close {
    position: absolute; top: 1rem; right: 1rem;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.2); border: none; color: white;
    cursor: pointer; font-size: 1.1rem; z-index: 2;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover { background: #dc2626; transform: rotate(90deg); }
.modal-date-badge {
    display: inline-flex; gap: 0.5rem; padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.2); border-radius: 999px;
    font-size: 0.82rem; font-weight: 700; margin-bottom: 1rem;
    backdrop-filter: blur(10px); position: relative;
}
.modal-title {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800;
    line-height: 1.3; margin-bottom: 0.5rem; position: relative;
}
.modal-subtitle { font-size: 0.85rem; opacity: 0.9; position: relative; }

.modal-body { padding: 1.75rem 2rem; }
.modal-info-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem; margin-bottom: 1.5rem;
}
.modal-info-item {
    padding: 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
}
.modal-info-label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.2rem;
}
.modal-info-value {
    font-size: 0.9rem; color: var(--text-primary); font-weight: 600;
}
.modal-desc-label {
    font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.4rem;
}
.modal-desc {
    background: var(--bg-secondary); padding: 1.15rem;
    border-radius: var(--radius-md); border-left: 4px solid var(--modal-color, #8b5cf6);
    font-size: 0.9rem; line-height: 1.7; color: var(--text-secondary);
    white-space: pre-wrap;
}

.modal-footer {
    padding: 1rem 2rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;
    background: var(--bg-secondary);
}
.modal-btn {
    padding: 0.65rem 1.15rem; border-radius: 8px; border: none;
    font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    transition: all 0.2s;
}
.modal-btn.primary { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }
.modal-btn.secondary { background: var(--bg-tertiary); color: var(--text-primary); }
.modal-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

@media (max-width: 968px) {
    .chart-view-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .agenda-hero-extreme { padding: 7rem 0 5rem; }
    .spotlight-card {
        grid-template-columns: 1fr; text-align: center;
        margin: -3rem 1rem 2rem; gap: 1.25rem;
    }
    .spotlight-meta { justify-content: center; }
    .spotlight-visual { margin: 0 auto; width: 100px; height: 100px; }
    .spotlight-day { font-size: 2rem; }
    .spotlight-actions { flex-direction: row; justify-content: center; }
    .timeline-extreme-pub::before { left: 20px; }
    .timeline-dot-pub { width: 44px; height: 44px; font-size: 1.2rem; }
    .timeline-card-pub::before { display: none; }
    .agenda-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .calendar-grid { font-size: 0.7rem; }
    .cal-day { min-height: 70px; padding: 0.35rem; }
    .cal-day-num { width: 24px; height: 24px; font-size: 0.75rem; }
    .cal-event-pill { font-size: 0.58rem; padding: 0.1rem 0.3rem; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .cards-view { grid-template-columns: 1fr; }
    .agenda-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="agenda-hero-extreme">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Agenda & Kalender</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem; text-align: center;" data-aos="fade-up">
            Agenda & Kalender <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Akademik</span>
        </h1>
        <p class="page-subtitle" style="max-width: 640px; margin: 0 auto; opacity: 0.92; font-size: 1.1rem; text-align: center; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Pantau jadwal kegiatan akademik, ujian, seminar, wisuda, dan hari libur fakultas agar Anda tidak ketinggalan momen penting.
        </p>

        <?php if ($next_event):
            $next_ts = strtotime($next_event['tanggal_mulai']);
            $now_ts = time();
            $diff = $next_ts - $now_ts;
            $days = max(0, floor($diff / 86400));
            $hours = max(0, floor(($diff % 86400) / 3600));
        ?>
        <div class="hero-live-row" data-aos="fade-up" data-aos-delay="200">
            <div class="hero-countdown-box" id="countdownBox">
                <div class="countdown-item">
                    <div class="countdown-num" id="cdDays"><?= $days ?></div>
                    <div class="countdown-label">Hari</div>
                </div>
                <div class="countdown-sep">:</div>
                <div class="countdown-item">
                    <div class="countdown-num" id="cdHours"><?= $hours ?></div>
                    <div class="countdown-label">Jam</div>
                </div>
                <div class="countdown-sep">:</div>
                <div class="countdown-item">
                    <div class="countdown-num" id="cdMins">00</div>
                    <div class="countdown-label">Menit</div>
                </div>
                <div class="countdown-sep">:</div>
                <div class="countdown-item">
                    <div class="countdown-num" id="cdSecs">00</div>
                    <div class="countdown-label">Detik</div>
                </div>
                <span style="margin-left: 0.75rem; font-size: 0.82rem; opacity: 0.9;">hingga <strong><?= sanitize($next_event['judul']) ?></strong></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== SPOTLIGHT ===== -->
<div class="container">
    <?php if ($next_event):
        $d = new DateTime($next_event['tanggal_mulai']);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $jenis_icons = ['ujian'=>'📝','seminar'=>'🎤','wisuda'=>'🎓','libur'=>'🏖️','pmb'=>'📋','umum'=>'📌'];
    ?>
    <div class="spotlight-card" data-aos="fade-up">
        <div class="spotlight-visual">
            <span class="spotlight-day"><?= $d->format('d') ?></span>
            <span class="spotlight-month"><?= $months[$d->format('n')-1] ?></span>
        </div>
        <div>
            <span class="spotlight-badge">🔥 Agenda Terdekat</span>
            <h2 class="spotlight-title"><?= sanitize($next_event['judul']) ?></h2>
            <div class="spotlight-meta">
                <span>📅 <?= format_tanggal_range($next_event['tanggal_mulai'], $has_agenda_end ? ($next_event['tanggal_selesai'] ?? null) : null) ?></span>
                <?php if ($has_agenda_loc && !empty($next_event['lokasi'])): ?>
                    <span>📍 <?= sanitize($next_event['lokasi']) ?></span>
                <?php endif; ?>
                <span class="badge-jenis-extreme badge-<?= strtolower($next_event['jenis']) ?>">
                    <?= $jenis_icons[$next_event['jenis']] ?? '📌' ?> <?= ucfirst($next_event['jenis']) ?>
                </span>
            </div>
            <?php if (!empty($next_event['deskripsi'])): ?>
            <p class="spotlight-desc"><?= excerpt($next_event['deskripsi'], 180) ?></p>
            <?php endif; ?>
        </div>
        <div class="spotlight-actions">
            <button class="spotlight-btn" onclick="showEventDetail(<?= htmlspecialchars(json_encode($next_event), ENT_QUOTES, 'UTF-8') ?>)">👁️ Detail</button>
            <button class="spotlight-btn" onclick="shareEvent(<?= htmlspecialchars(json_encode($next_event), ENT_QUOTES, 'UTF-8') ?>)">🔗 Share</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="agenda-stats" data-aos="fade-up">
            <div class="agenda-stat-card" style="--stat-color: #8b5cf6;">
                <div class="agenda-stat-icon">📅</div>
                <div>
                    <div class="agenda-stat-num count-up" data-target="<?= count($agenda) ?>">0</div>
                    <div class="agenda-stat-label">Ditampilkan</div>
                </div>
            </div>
            <div class="agenda-stat-card" style="--stat-color: #f59e0b;">
                <div class="agenda-stat-icon">⏰</div>
                <div>
                    <div class="agenda-stat-num count-up" data-target="<?= $stat_upcoming ?>">0</div>
                    <div class="agenda-stat-label">Akan Datang</div>
                </div>
            </div>
            <div class="agenda-stat-card" style="--stat-color: #10b981;">
                <div class="agenda-stat-icon">📆</div>
                <div>
                    <div class="agenda-stat-num count-up" data-target="<?= $stat_this_month ?>">0</div>
                    <div class="agenda-stat-label">Bulan Ini</div>
                </div>
            </div>
            <div class="agenda-stat-card" style="--stat-color: #3b82f6;">
                <div class="agenda-stat-icon">🏷️</div>
                <div>
                    <div class="agenda-stat-num"><?= count($cat_stats) ?></div>
                    <div class="agenda-stat-label">Kategori</div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="agenda-toolbar" data-aos="fade-up">
            <div class="agenda-search">
                <span class="s-icon">🔍</span>
                <input type="text" id="agendaSearch" placeholder="Cari judul, lokasi, atau deskripsi..." value="<?= sanitize($search) ?>">
                <?php if ($search !== ''): ?>
                    <a href="agenda.php?filter=<?= urlencode($filter) ?>&period=<?= urlencode($period) ?>" class="s-clear" title="Clear">✕</a>
                <?php endif; ?>
            </div>
            <select class="agenda-select" id="periodSelect">
                <option value="all"     <?= $period === 'all' ? 'selected' : '' ?>>📅 Semua Waktu</option>
                <option value="upcoming"<?= $period === 'upcoming' ? 'selected' : '' ?>>⏰ Akan Datang</option>
                <option value="month"   <?= $period === 'month' ? 'selected' : '' ?>>📆 Bulan Ini</option>
                <option value="past"    <?= $period === 'past' ? 'selected' : '' ?>>🕐 Telah Lewat</option>
            </select>
            <div class="view-toggle">
                <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📜 Timeline</button>
                <button class="view-btn <?= $view === 'cards' ? 'active' : '' ?>" onclick="switchView('cards')">🎴 Kartu</button>
                <button class="view-btn <?= $view === 'calendar' ? 'active' : '' ?>" onclick="switchView('calendar')">📅 Kalender</button>
                <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
            </div>
        </div>

        <!-- Filter Pills -->
        <div class="filter-pills" data-aos="fade-up">
            <a class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>" href="agenda.php?filter=all&view=<?= urlencode($view) ?>&period=<?= urlencode($period) ?>">
                📅 Semua <span class="pill-count"><?= array_sum($cat_stats) ?></span>
            </a>
            <?php
            $filter_list = [
                'ujian'   => ['icon'=>'📝','label'=>'Ujian'],
                'seminar' => ['icon'=>'🎤','label'=>'Seminar'],
                'wisuda'  => ['icon'=>'🎓','label'=>'Wisuda'],
                'libur'   => ['icon'=>'🏖️','label'=>'Libur'],
                'pmb'     => ['icon'=>'📋','label'=>'PMB'],
                'umum'    => ['icon'=>'📌','label'=>'Umum'],
            ];
            foreach ($filter_list as $k => $f):
                $cnt = $cat_stats[$k] ?? 0;
                if ($cnt === 0 && $filter !== $k) continue;
            ?>
            <a class="filter-pill <?= $filter === $k ? 'active' : '' ?>" href="agenda.php?filter=<?= $k ?>&view=<?= urlencode($view) ?>&period=<?= urlencode($period) ?>">
                <?= $f['icon'] ?> <?= $f['label'] ?> <span class="pill-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($agenda) && $view !== 'calendar' && $view !== 'chart'): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📅</div>
                <h3>Belum ada agenda</h3>
                <p>Silakan coba filter lain atau cek kembali nanti.</p>
                <a href="agenda.php" class="timeline-action-btn" style="justify-content:center;">🔄 Reset Filter</a>
            </div>
        <?php endif; ?>

        <!-- ===== TIMELINE VIEW ===== -->
        <?php if ($view === 'timeline' && !empty($grouped)): ?>
        <div class="timeline-extreme-pub">
            <?php
            $months_id = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
            foreach ($grouped as $ym => $items):
                list($y, $m) = explode('-', $ym);
            ?>
            <div class="timeline-month-group" data-aos="fade-up">
                <div class="timeline-month-label">
                    <h3><?= $months_id[$m] ?> <?= $y ?></h3>
                    <span class="month-count"><?= count($items) ?> agenda</span>
                </div>
                <?php foreach ($items as $a):
                    $jenis_icons = ['ujian'=>'📝','seminar'=>'🎤','wisuda'=>'🎓','libur'=>'🏖️','pmb'=>'📋','umum'=>'📌'];
                    $icon = $jenis_icons[$a['jenis']] ?? '📌';
                    $date_str = format_tanggal_range($a['tanggal_mulai'], $has_agenda_end ? ($a['tanggal_selesai'] ?? null) : null);
                    $ts = strtotime($a['tanggal_mulai']);
                    $diff = $ts - time();
                    $days_until = ceil($diff / 86400);
                    $countdown_label = '';
                    $countdown_class = '';
                    if ($days_until > 0 && $days_until <= 30) {
                        $countdown_label = "🔥 dalam $days_until hari";
                    } elseif ($days_until === 0) {
                        $countdown_label = '🎯 HARI INI';
                    } elseif ($days_until < 0) {
                        $countdown_label = '✓ Telah lewat';
                        $countdown_class = 'past';
                    }
                ?>
                <div class="timeline-item-pub">
                    <div class="timeline-dot-pub" data-jenis="<?= strtolower($a['jenis']) ?>"><?= $icon ?></div>
                    <div class="timeline-card-pub">
                        <div class="timeline-date-pub">
                            📅 <?= $date_str ?>
                            <?php if ($countdown_label): ?>
                            <span class="timeline-countdown <?= $countdown_class ?>"><?= $countdown_label ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?= sanitize($a['judul']) ?></h3>
                        <?php if ($has_agenda_loc && !empty($a['lokasi'])): ?>
                        <div class="timeline-meta-row"><span>📍 <?= sanitize($a['lokasi']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($a['deskripsi'])): ?>
                        <p><?= excerpt($a['deskripsi'], 280) ?></p>
                        <?php endif; ?>
                        <div class="timeline-actions">
                            <span class="badge-jenis-extreme badge-<?= strtolower($a['jenis']) ?>"><?= $icon ?> <?= ucfirst($a['jenis']) ?></span>
                            <button class="timeline-action-btn" onclick='showEventDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                            <button class="timeline-action-btn" onclick='shareEvent(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== CARDS VIEW ===== -->
        <?php if ($view === 'cards' && !empty($agenda)): ?>
        <div class="cards-view">
            <?php
            $jenis_colors = [
                'ujian'=>['c'=>'#dc2626','l'=>'#ef4444'],'seminar'=>['c'=>'#2563eb','l'=>'#3b82f6'],
                'wisuda'=>['c'=>'#d97706','l'=>'#f59e0b'],'libur'=>['c'=>'#4338ca','l'=>'#6366f1'],
                'pmb'=>['c'=>'#16a34a','l'=>'#22c55e'],'umum'=>['c'=>'#8b5cf6','l'=>'#a78bfa']
            ];
            foreach ($agenda as $a):
                $col = $jenis_colors[$a['jenis']] ?? $jenis_colors['umum'];
                $d = new DateTime($a['tanggal_mulai']);
                $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            ?>
            <div class="event-card" style="--card-color: <?= $col['c'] ?>; --card-color-light: <?= $col['l'] ?>;" onclick='showEventDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                <div class="event-card-header">
                    <div class="event-card-date">
                        <span class="ecd-day"><?= $d->format('d') ?></span>
                        <span class="ecd-month"><?= $months[$d->format('n')-1] ?></span>
                    </div>
                    <span class="event-card-badge badge-jenis-extreme badge-<?= strtolower($a['jenis']) ?>"><?= ucfirst($a['jenis']) ?></span>
                </div>
                <div class="event-card-body">
                    <h3 class="event-card-title"><?= sanitize($a['judul']) ?></h3>
                    <div class="event-card-meta">
                        <span>📅 <?= format_tanggal_range($a['tanggal_mulai'], $has_agenda_end ? ($a['tanggal_selesai'] ?? null) : null) ?></span>
                        <?php if ($has_agenda_loc && !empty($a['lokasi'])): ?>
                            <span>📍 <?= excerpt($a['lokasi'], 40) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($a['deskripsi'])): ?>
                    <p class="event-card-excerpt"><?= sanitize($a['deskripsi']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== CALENDAR VIEW ===== -->
        <?php if ($view === 'calendar'): ?>
        <div class="calendar-view" data-aos="fade-up">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <a href="agenda.php?view=calendar&month=<?= $cal_prev ?>&filter=<?= urlencode($filter) ?>" class="cal-nav-btn" title="Bulan sebelumnya">←</a>
                </div>
                <div class="calendar-title"><?= $months_id[sprintf('%02d', $cal_mon)] ?> <?= $cal_year ?></div>
                <div class="calendar-nav">
                    <a href="agenda.php?view=calendar&month=<?= $cal_next ?>&filter=<?= urlencode($filter) ?>" class="cal-nav-btn" title="Bulan berikutnya">→</a>
                </div>
            </div>
            <div class="calendar-grid">
                <?php foreach (['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $dn): ?>
                <div class="cal-day-name"><?= $dn ?></div>
                <?php endforeach; ?>

                <?php
                $total_cells = $cal_first_dow + $cal_days_in_month;
                $rows = (int)ceil($total_cells / 7);
                $today_day = (date('Y-m') === sprintf('%04d-%02d', $cal_year, $cal_mon)) ? (int)date('j') : 0;
                $cell_idx = 0;
                for ($r = 0; $r < $rows; $r++) {
                    for ($c = 0; $c < 7; $c++) {
                        $day_num = $cell_idx - $cal_first_dow + 1;
                        if ($day_num < 1 || $day_num > $cal_days_in_month) {
                            echo '<div class="cal-day other-month"></div>';
                        } else {
                            $is_today = $day_num === $today_day;
                            $events = $cal_by_day[$day_num] ?? [];
                            $jenis_colors = [
                                'ujian'=>'#dc2626','seminar'=>'#2563eb','wisuda'=>'#d97706',
                                'libur'=>'#4338ca','pmb'=>'#16a34a','umum'=>'#8b5cf6'
                            ];
                            echo '<div class="cal-day'.($is_today ? ' today' : '').'">';
                            echo '<div class="cal-day-num">'.$day_num.'</div>';
                            if (!empty($events)) {
                                echo '<div class="cal-events">';
                                $shown = 0;
                                foreach ($events as $e) {
                                    if ($shown >= 2) break;
                                    $col = $jenis_colors[$e['jenis']] ?? '#8b5cf6';
                                    echo '<div class="cal-event-pill" style="--pill-color:'.$col.';background:'.$col.'" onclick=\'showEventDetail('.htmlspecialchars(json_encode($e), ENT_QUOTES, "UTF-8").')\' title="'.htmlspecialchars($e['judul'], ENT_QUOTES).'">'.htmlspecialchars(excerpt($e['judul'], 18), ENT_QUOTES).'</div>';
                                    $shown++;
                                }
                                if (count($events) > 2) {
                                    echo '<div class="cal-event-more">+'.(count($events) - 2).' lainnya</div>';
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        $cell_idx++;
                    }
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== CHART VIEW ===== -->
        <?php if ($view === 'chart'): ?>
        <div class="chart-view-grid" data-aos="fade-up">
            <div class="chart-card">
                <h3>📊 Distribusi Kategori Agenda</h3>
                <div id="catChart"></div>
            </div>
            <div class="chart-card">
                <h3>📅 Timeline Agenda 12 Bulan</h3>
                <div id="timelineChart"></div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- Modal Detail -->
<div class="modal-overlay" id="eventModal" onclick="if(event.target===this)closeEventModal()">
    <div class="modal-content" id="eventModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const hasAgendaEnd = <?= $has_agenda_end ? 'true' : 'false' ?>;
const hasAgendaLoc = <?= $has_agenda_loc ? 'true' : 'false' ?>;
const catStats = <?= json_encode($cat_stats) ?>;
const jenisColors = {
    ujian: '#dc2626', seminar: '#2563eb', wisuda: '#d97706',
    libur: '#4338ca', pmb: '#16a34a', umum: '#8b5cf6'
};
const jenisIcons = {
    ujian: '📝', seminar: '🎤', wisuda: '🎓',
    libur: '🏖️', pmb: '📋', umum: '📌'
};

// ===== COUNT-UP =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1800; const start = performance.now();
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
        if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== COUNTDOWN =====
<?php if ($next_event):
    $next_ts = strtotime($next_event['tanggal_mulai']);
    $diff = $next_ts - time();
?>
let cdSeconds = <?= max(0, $diff) ?>;
function updateCountdown() {
    if (cdSeconds <= 0) {
        document.getElementById('countdownBox')?.remove();
        return;
    }
    cdSeconds--;
    const d = Math.floor(cdSeconds / 86400);
    const h = Math.floor((cdSeconds % 86400) / 3600);
    const m = Math.floor((cdSeconds % 3600) / 60);
    const s = cdSeconds % 60;
    const pad = (n) => String(n).padStart(2, '0');
    const de = document.getElementById('cdDays');
    const he = document.getElementById('cdHours');
    const me = document.getElementById('cdMins');
    const se = document.getElementById('cdSecs');
    if (de) de.textContent = d;
    if (he) he.textContent = pad(h);
    if (me) me.textContent = pad(m);
    if (se) se.textContent = pad(s);
}
setInterval(updateCountdown, 1000);
<?php endif; ?>

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== SEARCH =====
let searchTimer;
document.getElementById('agendaSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

// ===== PERIOD SELECT =====
document.getElementById('periodSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('period', this.value);
    window.location = url;
});

// ===== VIEW SWITCH =====
function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    if (view === 'calendar') url.searchParams.set('month', '<?= $calendar_month ?>');
    window.location = url;
}

// ===== FORMAT HELPERS =====
function formatDateRange(start, end) {
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const sd = new Date(start);
    const s = `${sd.getDate()} ${months[sd.getMonth()]} ${sd.getFullYear()}`;
    if (!end || !hasAgendaEnd) return s;
    const ed = new Date(end);
    if (sd.toDateString() === ed.toDateString()) return s;
    if (sd.getMonth() === ed.getMonth() && sd.getFullYear() === ed.getFullYear()) {
        return `${sd.getDate()} - ${ed.getDate()} ${months[ed.getMonth()]} ${ed.getFullYear()}`;
    }
    return `${s} - ${ed.getDate()} ${months[ed.getMonth()]} ${ed.getFullYear()}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== EVENT DETAIL MODAL =====
function showEventDetail(ev) {
    const jenis = ev.jenis || 'umum';
    const color = jenisColors[jenis] || '#8b5cf6';
    const lightColor = color + 'cc';
    const icon = jenisIcons[jenis] || '📌';

    const ts = new Date(ev.tanggal_mulai).getTime();
    const now = Date.now();
    const diff = Math.floor((ts - now) / 86400000);
    let statusBadge = '';
    if (diff > 0 && diff <= 30) statusBadge = `<span style="background:rgba(255,255,255,0.25);padding:0.3rem 0.7rem;border-radius:999px;font-size:0.75rem;font-weight:700;">🔥 dalam ${diff} hari</span>`;
    else if (diff === 0) statusBadge = `<span style="background:rgba(255,255,255,0.25);padding:0.3rem 0.7rem;border-radius:999px;font-size:0.75rem;font-weight:700;">🎯 HARI INI</span>`;
    else if (diff < 0) statusBadge = `<span style="background:rgba(255,255,255,0.25);padding:0.3rem 0.7rem;border-radius:999px;font-size:0.75rem;font-weight:700;">✓ Telah lewat</span>`;

    const html = `
        <div class="modal-header-event" style="--modal-color: ${color}; --modal-color-light: ${lightColor}">
            <button class="modal-close" onclick="closeEventModal()">✕</button>
            <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; position:relative;">
                <div class="modal-date-badge">${icon} ${formatDateRange(ev.tanggal_mulai, ev.tanggal_selesai)}</div>
                ${statusBadge}
            </div>
            <h2 class="modal-title">${escapeHtml(ev.judul)}</h2>
            <div class="modal-subtitle">Kategori: ${escapeHtml((ev.jenis || 'umum').charAt(0).toUpperCase() + (ev.jenis || 'umum').slice(1))}</div>
        </div>
        <div class="modal-body">
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <div class="modal-info-label">📅 Tanggal</div>
                    <div class="modal-info-value">${formatDateRange(ev.tanggal_mulai, ev.tanggal_selesai)}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">🏷️ Kategori</div>
                    <div class="modal-info-value">${icon} ${escapeHtml((ev.jenis || 'umum').charAt(0).toUpperCase() + (ev.jenis || 'umum').slice(1))}</div>
                </div>
                ${hasAgendaLoc && ev.lokasi ? `
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                    <div class="modal-info-label">📍 Lokasi</div>
                    <div class="modal-info-value">${escapeHtml(ev.lokasi)}</div>
                </div>` : ''}
            </div>
            ${ev.deskripsi ? `
            <div class="modal-desc-label">📝 Deskripsi</div>
            <div class="modal-desc">${escapeHtml(ev.deskripsi)}</div>` : '<p style="color:var(--text-muted);font-style:italic;">Tidak ada deskripsi.</p>'}
        </div>
        <div class="modal-footer">
            <button class="modal-btn secondary" onclick="shareEvent(${JSON.stringify(ev).replace(/"/g, '&quot;')})">🔗 Share</button>
            ${hasAgendaLoc && ev.lokasi ? `<a class="modal-btn secondary" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ev.lokasi)}" target="_blank">🗺️ Peta</a>` : ''}
            <button class="modal-btn primary" onclick="addToCalendar(${JSON.stringify(ev).replace(/"/g, '&quot;')})">📅 Add to Calendar</button>
        </div>
    `;
    document.getElementById('eventModalContent').innerHTML = html;
    document.getElementById('eventModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeEventModal() {
    document.getElementById('eventModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareEvent(ev) {
    const text = `📅 ${ev.judul}\n🗓️ ${formatDateRange(ev.tanggal_mulai, ev.tanggal_selesai)}${(hasAgendaLoc && ev.lokasi) ? '\n📍 ' + ev.lokasi : ''}\n\nAgenda FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: ev.judul, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info agenda disalin', '📋');
    }
}

// ===== ADD TO CALENDAR (Google Calendar) =====
function addToCalendar(ev) {
    const start = ev.tanggal_mulai.replace(/-/g, '');
    const end = (hasAgendaEnd && ev.tanggal_selesai) ? ev.tanggal_selesai.replace(/-/g, '') : start;
    const url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(ev.judul)}&dates=${start}/${end}&details=${encodeURIComponent(ev.deskripsi || '')}&location=${encodeURIComponent((hasAgendaLoc && ev.lokasi) ? ev.lokasi : '')}`;
    window.open(url, '_blank');
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const catData = <?= json_encode($cat_stats) ?>;
if (Object.keys(catData).length > 0) {
    new ApexCharts(document.querySelector("#catChart"), {
        series: Object.values(catData),
        labels: Object.keys(catData).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
        chart: { type: 'donut', height: 300 },
        colors: Object.keys(catData).map(k => jenisColors[k] || '#8b5cf6'),
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(catData).reduce((a,b)=>a+b,0) } } } } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

// Timeline 12 bulan
(async function() {
    const months = [];
    const counts = [];
    const now = new Date();
    for (let i = 11; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const y = d.getFullYear(); const m = d.getMonth() + 1;
        months.push(d.toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }));
        // Fallback: hitung dari data yang sudah ada di halaman (kalau chart view dan tidak ada filter)
        counts.push(0); // akan diisi dari backend jika perlu
    }
    new ApexCharts(document.querySelector("#timelineChart"), {
        series: [{ name: 'Agenda', data: counts }],
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        colors: ['#8b5cf6'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: months, labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        stroke: { curve: 'smooth', width: 3 }
    }).render();
})();
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('agendaSearch')?.focus();
    }
    if (e.key === 'Escape') closeEventModal();
});

console.log('%c📅 Agenda FKIP UNIMOF - EXTREME MULTIMATE', 'color:#8b5cf6;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>