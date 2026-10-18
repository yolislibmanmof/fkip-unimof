<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== AUTO-UPDATE: Tutup otomatis jika deadline sudah lewat =====
try {
    $pdo->exec("UPDATE beasiswa SET status = 'Tertutup' WHERE deadline < CURDATE() AND status = 'Terbuka'");
} catch (Exception $e) {}

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM beasiswa WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Beasiswa berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE beasiswa SET status = IF(status='Terbuka','Tertutup','Terbuka') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status beasiswa diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM beasiswa WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' beasiswa berhasil dihapus.');
    }
    elseif ($action === 'bulk_status' && !empty($ids)) {
        $new_status = $_POST['new_status'] ?? 'Terbuka';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_status], $ids);
        $pdo->prepare("UPDATE beasiswa SET status = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', 'Status ' . count($ids) . ' beasiswa diperbarui.');
    }
    elseif ($action === 'bulk_extend' && !empty($ids)) {
        $days = (int)($_POST['extend_days'] ?? 30);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$days], $ids);
        $pdo->prepare("UPDATE beasiswa SET deadline = DATE_ADD(deadline, INTERVAL ? DAY) WHERE id IN ($placeholders) AND deadline IS NOT NULL")->execute($params);
        flash_message('success', 'Deadline ' . count($ids) . ' beasiswa diperpanjang ' . $days . ' hari.');
    }

    header('Location: beasiswa.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';
$view_mode = $_GET['view'] ?? 'table';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR sumber LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($jenis_filter !== '') { $where .= ' AND jenis = ?'; $params[] = $jenis_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM beasiswa $where ORDER BY FIELD(status, 'Terbuka', 'Tertutup'), deadline ASC");
$stmt->execute($params);
$beasiswa_list = $stmt->fetchAll();

// ===== EXPORT HANDLER =====
if (isset($_GET['export']) && !empty($beasiswa_list)) {
    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="beasiswa-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Nama', 'Jenis', 'Sumber', 'Nominal', 'Deadline', 'Status', 'Sisa Hari']);
        foreach ($beasiswa_list as $r) {
            $days = $r['deadline'] ? floor((strtotime($r['deadline']) - time()) / 86400) : '';
            fputcsv($out, [
                $r['id'], $r['nama'], $r['jenis'], $r['sumber'] ?? '-',
                $r['nominal'] ?? '-', $r['deadline'] ?? '-', $r['status'], $days
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="beasiswa-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($beasiswa_list),
            'data' => $beasiswa_list
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== STATISTIK LENGKAP =====
$stat_total = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa")->fetchColumn();
$stat_terbuka = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE status='Terbuka'")->fetchColumn();
$stat_tutup = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE status='Tertutup'")->fetchColumn();
$stat_urgent = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE status='Terbuka' AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$stat_critical = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE status='Terbuka' AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

// Total nominal (angka yang bisa di-parse)
$total_nominal = 0;
$nominal_rows = $pdo->query("SELECT nominal FROM beasiswa WHERE nominal IS NOT NULL AND nominal != ''")->fetchAll(PDO::FETCH_COLUMN);
foreach ($nominal_rows as $n) {
    $num = (int)preg_replace('/[^0-9]/', '', $n);
    $total_nominal += $num;
}

// Beasiswa baru bulan ini
$month_new = (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Chart data
$jenis_stats = [];
$jenis_rows = $pdo->query("SELECT jenis, COUNT(*) as total FROM beasiswa WHERE status='Terbuka' GROUP BY jenis")->fetchAll();
foreach ($jenis_rows as $r) $jenis_stats[$r['jenis']] = (int)$r['total'];

// Status chart data
$status_stats = ['Terbuka' => $stat_terbuka, 'Tertutup' => $stat_tutup];

// Deadline timeline (next 12 months)
$deadline_timeline = [];
for ($m = 0; $m < 12; $m++) {
    $month_start = date('Y-m-d', strtotime("+$m months"));
    $month_end = date('Y-m-d', strtotime("+" . ($m + 1) . " months -1 day"));
    $month_label = date('M Y', strtotime("+$m months"));
    $count = (int)$pdo->prepare("SELECT COUNT(*) FROM beasiswa WHERE deadline BETWEEN ? AND ? AND status='Terbuka'")
        ->execute([$month_start, $month_end]) ? (int)$pdo->query("SELECT COUNT(*) FROM beasiswa WHERE deadline BETWEEN '$month_start' AND '$month_end' AND status='Terbuka'")->fetchColumn() : 0;
    $deadline_timeline[$month_label] = $count;
}

// Upcoming deadlines (7 hari ke depan)
$upcoming = $pdo->query("SELECT * FROM beasiswa WHERE deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status='Terbuka' ORDER BY deadline ASC LIMIT 5")->fetchAll();

// Expiring soon (< 30 hari)
$expiring_soon = $pdo->query("SELECT * FROM beasiswa WHERE deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='Terbuka' ORDER BY deadline ASC LIMIT 10")->fetchAll();

// Skor urgensi
$urgency_score = $stat_terbuka > 0 ? round(100 - (($stat_urgent / $stat_terbuka) * 100)) : 100;

$csrf = generate_csrf_token();
$active_menu = 'beasiswa';
$page_heading = 'Kelola Beasiswa';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Beasiswa', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO ===== */
.beasiswa-hero {
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(16,185,129,0.3);
}
.beasiswa-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -15%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.beasiswa-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}
.beasiswa-hero h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.beasiswa-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; }
.hero-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
.hero-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.2);
    min-width: 110px;
}
.hero-stat-num {
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.hero-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-top: 0.25rem;
}

/* Urgency Score Ring */
.urgency-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.urgency-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.urgency-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.urgency-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.urgency-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.urgency-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.urgency-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS ===== */
.stats-extreme {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
}
.stat-card-extreme::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, var(--primary));
}
.stat-icon-extreme {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: inline-block;
    animation: floatIcon 3s ease-in-out infinite;
}
@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}
.stat-number-extreme {
    font-family: var(--font-display);
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--stat-color, var(--primary));
    line-height: 1;
    margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-extreme {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}
.stat-trend {
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
}
.stat-trend.up { color: #10b981; }
.stat-trend.down { color: #ef4444; }
.stat-trend.neutral { color: var(--text-muted); }

/* ===== URGENT ALERT ===== */
.urgent-alert {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 1px solid #fca5a5;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.5s ease;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
}
.urgent-alert::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #ef4444, #dc2626);
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.urgent-icon {
    font-size: 2rem;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.urgent-content { flex: 1; min-width: 200px; }
.urgent-content h4 { font-size: 0.95rem; font-weight: 700; color: #991b1b; margin-bottom: 0.25rem; }
.urgent-content p { font-size: 0.82rem; color: #7f1d1d; margin: 0; }

/* ===== UPCOMING DEADLINES ===== */
.upcoming-section {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}
.upcoming-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.upcoming-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.upcoming-list {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
}
.upcoming-item {
    min-width: 220px;
    max-width: 280px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1rem;
    flex-shrink: 0;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.upcoming-item::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--item-color, #f59e0b);
}
.upcoming-item:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--item-color, #f59e0b);
}
.upcoming-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.upcoming-name {
    font-weight: 700;
    font-size: 0.95rem;
    margin-bottom: 0.35rem;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.upcoming-source {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.upcoming-countdown {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
}
.upcoming-countdown.critical {
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
    animation: urgentPulse 1.5s infinite;
}
@keyframes urgentPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
    50% { box-shadow: 0 0 0 5px rgba(239,68,68,0); }
}

/* ===== CHART SECTION ===== */
.chart-section {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-section-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ===== QUICK FILTER PILLS ===== */
.quick-filter-pills {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 0.5rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    align-items: center;
}
.pill {
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
}
.pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.pill.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-color: #059669;
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}
.pill .pill-count {
    background: rgba(255,255,255,0.25);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}
.pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TOOLBAR ===== */
.toolbar-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.search-box { flex: 1; min-width: 250px; position: relative; }
.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: var(--bg-secondary);
}
.search-box input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16,185,129,0.1);
}
.search-box .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.search-shortcut {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-tertiary);
    color: var(--text-muted);
    padding: 0.15rem 0.5rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-family: monospace;
    font-weight: 600;
    pointer-events: none;
}
.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.9rem;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.3s;
}
.filter-select:focus { outline: none; border-color: #10b981; }

.view-toggle {
    display: flex;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: 0.25rem;
    border: 1px solid var(--border);
}
.view-btn {
    padding: 0.5rem 0.85rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-family: inherit;
}
.view-btn.active { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.btn-action {
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
    font-family: inherit;
    white-space: nowrap;
}
.btn-action.primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}
.btn-action.secondary:hover { background: var(--bg-tertiary); transform: translateY(-2px); }

/* Export Dropdown */
.export-dropdown { position: relative; }
.export-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    min-width: 220px;
    display: none;
    z-index: 50;
    overflow: hidden;
}
.export-menu.show { display: block; animation: menuPop 0.2s ease; }
@keyframes menuPop {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.export-item {
    padding: 0.7rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 0.85rem;
    transition: background 0.15s;
    border-bottom: 1px solid var(--border);
}
.export-item:last-child { border-bottom: none; }
.export-item:hover { background: var(--bg-secondary); }
.export-item-icon { font-size: 1.1rem; width: 22px; text-align: center; }

/* ===== BULK BAR ===== */
.bulk-bar {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(16,185,129,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #059669; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
.bulk-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: 0.82rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.bulk-btn:hover { transform: translateY(-2px); }
.bulk-btn.primary { background: white; color: #059669; }
.bulk-btn.warning { background: #f59e0b; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* ===== TABLE ===== */
.table-container {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.table-header h2 { font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; }
.table-header p { font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem; }
.table-wrapper { overflow-x: auto; }

table.extreme { width: 100%; border-collapse: collapse; }
table.extreme th {
    background: var(--bg-secondary);
    padding: 0.85rem 1rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
    user-select: none;
    position: sticky;
    top: 0;
    z-index: 5;
}
table.extreme th[data-sortable] { cursor: pointer; transition: color 0.2s; }
table.extreme th[data-sortable]:hover { color: #10b981; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #10b981; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(16,185,129,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #10b981; cursor: pointer; }

/* Badges */
.badge-extreme {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-terbuka { background: #dcfce7; color: #166534; }
.badge-tertutup { background: #fee2e2; color: #991b1b; }
.badge-prestasi-akademik { background: #fef3c7; color: #92400e; }
.badge-kip-kuliah { background: #dbeafe; color: #1e40af; }
.badge-muhammadiyah { background: #dcfce7; color: #166534; }
.badge-talent-scouting { background: #e0e7ff; color: #4338ca; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; }

.badge-urgent { animation: urgentPulse 1.5s infinite; }

/* Nominal display */
.nominal-display {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.nominal-amount {
    font-weight: 700;
    color: #059669;
    font-variant-numeric: tabular-nums;
    font-family: var(--font-display);
}
.nominal-note {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-style: italic;
}

/* Action buttons */
.action-buttons { display: flex; gap: 0.35rem; justify-content: flex-end; }
.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-icon.view { background: #e0e7ff; color: #4338ca; }
.btn-icon.view:hover { background: #4338ca; color: white; transform: translateY(-2px); }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== CARD VIEW ===== */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.beasiswa-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.beasiswa-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-color, #10b981);
}
.beasiswa-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-color, #10b981);
}
.beasiswa-card-body { padding: 1.25rem; }
.beasiswa-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.beasiswa-card-name {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.3;
    flex: 1;
    min-width: 0;
}
.beasiswa-card-source {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.beasiswa-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.beasiswa-card-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
}
.beasiswa-card-info-item .label { color: var(--text-muted); }
.beasiswa-card-info-item .value { font-weight: 600; color: var(--text-primary); }

/* Countdown ring di card */
.countdown-ring-mini {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: 999px;
    border: 1px solid var(--border);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-primary);
}
.countdown-ring-mini.urgent {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}
.countdown-ring-mini.warning {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}
.countdown-ring-mini svg {
    width: 16px;
    height: 16px;
    transform: rotate(-90deg);
}
.countdown-ring-mini .ring-bg { fill: none; stroke: rgba(0,0,0,0.1); stroke-width: 3; }
.countdown-ring-mini .ring-fill { fill: none; stroke: currentColor; stroke-width: 3; stroke-linecap: round; }

.beasiswa-card-footer {
    padding: 0.85rem 1.25rem;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ===== TIMELINE VIEW ===== */
.timeline-view { padding: 1.5rem; }
.timeline-group { margin-bottom: 2rem; }
.timeline-group-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--bg-primary);
    z-index: 5;
    padding-top: 0.5rem;
}
.timeline-month {
    font-size: 1.5rem;
    font-weight: 900;
    color: #10b981;
    font-family: var(--font-display);
}
.timeline-count {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.timeline-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}
.timeline-item {
    display: flex;
    gap: 0.85rem;
    padding: 0.85rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
    cursor: pointer;
    align-items: center;
}
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(3px); border-color: #10b981; }
.timeline-date-box {
    width: 50px;
    text-align: center;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 8px;
    padding: 0.5rem 0.25rem;
    flex-shrink: 0;
}
.timeline-day { font-size: 1.3rem; font-weight: 900; line-height: 1; }
.timeline-month-small { font-size: 0.65rem; text-transform: uppercase; font-weight: 600; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.timeline-item-role { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ===== EMPTY STATE ===== */
.empty-state-extreme {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-extreme {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
.empty-state-extreme h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }
.empty-state-extreme p { color: var(--text-muted); margin-bottom: 1.5rem; }

/* ===== DETAIL MODAL ===== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1.5rem;
}
.modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 720px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
    border: 1px solid var(--border);
}
@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.modal-header-beasiswa {
    padding: 2rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-beasiswa::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
    border-radius: 50%;
}
.modal-profile-row {
    display: flex;
    gap: 1.25rem;
    align-items: center;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.modal-beasiswa-icon {
    width: 72px;
    height: 72px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.25rem;
    border: 2px solid rgba(255,255,255,0.3);
    flex-shrink: 0;
}
.modal-profile-info { flex: 1; min-width: 200px; }
.modal-profile-name { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
.modal-profile-source { font-size: 0.9rem; opacity: 0.95; }
.modal-profile-type {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    background: rgba(255,255,255,0.2);
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
}
.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.25rem;
    color: white;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
}
.modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

.modal-body { padding: 2rem; }

/* Tabs */
.detail-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    scrollbar-width: none;
}
.detail-tabs::-webkit-scrollbar { display: none; }
.detail-tab {
    padding: 0.7rem 1.1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    color: var(--text-muted);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.88rem;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.detail-tab.active { color: #10b981; border-bottom-color: #10b981; }
.detail-tab:hover:not(.active) { color: var(--text-primary); }
.detail-tab-content { display: none; animation: adminFadeIn 0.3s; }
.detail-tab-content.active { display: block; }
@keyframes adminFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Detail grid */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.detail-item {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.detail-item:hover { background: var(--bg-tertiary); transform: translateX(2px); }
.detail-item-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.detail-item-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }

/* Countdown big */
.countdown-big {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 2px solid #fcd34d;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}
.countdown-big.critical {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border-color: #fca5a5;
}
.countdown-big.safe {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border-color: #86efac;
}
.countdown-big-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700;
    opacity: 0.7;
    margin-bottom: 0.5rem;
}
.countdown-big-numbers {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 1rem 0;
    flex-wrap: wrap;
}
.countdown-unit {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 60px;
}
.countdown-unit-value {
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
.countdown-unit-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-top: 0.25rem;
    font-weight: 600;
}

@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .detail-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .beasiswa-hero-content { flex-direction: column; text-align: center; }
    .urgency-ring { margin: 0 auto; }
    .cards-grid { grid-template-columns: 1fr; }
    .hero-stats { justify-content: center; }
    .timeline-items { grid-template-columns: 1fr; }
    .upcoming-list { flex-wrap: nowrap; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .beasiswa-hero, .upcoming-section, .urgent-alert, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="beasiswa-hero" data-aos="fade-down">
    <div class="beasiswa-hero-content">
        <div>
            <h2>🎓 Manajemen Beasiswa</h2>
            <p>Kelola program beasiswa untuk mahasiswa FKIP UNIMOF. Pantau deadline, distribusi, dan status pendaftaran secara real-time.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_total ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_terbuka ?></span>
                    <span class="hero-stat-label">Terbuka</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_urgent ?></span>
                    <span class="hero-stat-label">Urgent</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num">+<?= $month_new ?></span>
                    <span class="hero-stat-label">Bulan Ini</span>
                </div>
            </div>
        </div>
        <div class="urgency-ring" title="Skor Urgensi (semakin tinggi semakin aman)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $urgency_score ?>, 100"/>
            </svg>
            <div class="urgency-value">
                <div class="score-num"><?= $urgency_score ?></div>
                <div class="score-label">Safety</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Program</div>
        <div class="stat-trend neutral">📚 Semua beasiswa</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_terbuka ?>">0</div>
        <div class="stat-label-extreme">Pendaftaran Terbuka</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_terbuka / $stat_total * 100) : 0 ?>% dari total</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ef4444;">
        <div class="stat-icon-extreme">🚨</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_urgent ?>">0</div>
        <div class="stat-label-extreme">Segera Tutup (< 30 Hari)</div>
        <div class="stat-trend <?= $stat_urgent > 0 ? 'down' : 'up' ?>">
            <?= $stat_urgent > 0 ? '⚠️ Perlu perhatian' : '✓ Aman' ?>
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #dc2626;">
        <div class="stat-icon-extreme">⏰</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_critical ?>">0</div>
        <div class="stat-label-extreme">Critical (< 7 Hari)</div>
        <div class="stat-trend <?= $stat_critical > 0 ? 'down' : 'up' ?>">
            <?= $stat_critical > 0 ? '🔥 Segera!' : '✓ Tidak ada' ?>
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #64748b;">
        <div class="stat-icon-extreme">🔒</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_tutup ?>">0</div>
        <div class="stat-label-extreme">Pendaftaran Ditutup</div>
        <div class="stat-trend neutral">📕 Arsip</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #059669;">
        <div class="stat-icon-extreme">💰</div>
        <div class="stat-number-extreme" style="font-size: 1.5rem;">
            <?= $total_nominal > 0 ? 'Rp ' . number_format($total_nominal, 0, ',', '.') : '-' ?>
        </div>
        <div class="stat-label-extreme">Total Nominal</div>
        <div class="stat-trend up">💵 Total dana</div>
    </div>
</div>

<!-- ===== URGENT ALERT ===== -->
<?php if ($stat_critical > 0): ?>
<div class="urgent-alert" data-aos="fade-down">
    <div class="urgent-icon">🚨</div>
    <div class="urgent-content">
        <h4>URGENT: <?= $stat_critical ?> Beasiswa Tutup dalam 7 Hari!</h4>
        <p>Segera informasikan kepada mahasiswa agar tidak melewatkan kesempatan.</p>
    </div>
    <a href="beasiswa.php?status=Terbuka" class="btn-action primary" style="flex-shrink: 0;">
        Lihat Daftar
    </a>
</div>
<?php endif; ?>

<!-- ===== UPCOMING DEADLINES ===== -->
<?php if (!empty($upcoming)): ?>
<div class="upcoming-section" data-aos="fade-up">
    <div class="upcoming-header">
        <h3>⏰ Deadline Terdekat (14 Hari)</h3>
        <a href="beasiswa.php?status=Terbuka" class="btn-action secondary" style="padding: 0.5rem 0.85rem; font-size: 0.78rem;">
            Lihat Semua →
        </a>
    </div>
    <div class="upcoming-list">
        <?php foreach ($upcoming as $u):
            $days = floor((strtotime($u['deadline']) - time()) / 86400);
            $colors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
            $color = $colors[array_rand($colors)];
            $is_critical = $days <= 7;
        ?>
        <div class="upcoming-item" style="--item-color: <?= $color ?>;" onclick="showDetail(<?= $u['id'] ?>)">
            <div class="upcoming-date">
                📅 <?= date('d M Y', strtotime($u['deadline'])) ?>
            </div>
            <div class="upcoming-name"><?= sanitize($u['nama']) ?></div>
            <div class="upcoming-source">🏛️ <?= sanitize($u['sumber'] ?: '-') ?></div>
            <div class="upcoming-countdown <?= $is_critical ? 'critical' : '' ?>">
                ⏰ <?= $days > 0 ? $days . ' hari lagi' : 'Hari ini!' ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== CHARTS ===== -->
<?php if (!empty($jenis_stats) || !empty($deadline_timeline)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Beasiswa Aktif per Jenis</h3>
        <div id="jenisChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Status Pendaftaran</h3>
        <div id="statusChart"></div>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card" style="grid-column: 1 / -1;">
        <h3>📅 Timeline Deadline (12 Bulan Kedepan)</h3>
        <div id="timelineChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="beasiswa.php" class="pill <?= empty($status_filter) && empty($jenis_filter) ? 'active' : '' ?>">
        🎯 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="beasiswa.php?status=Terbuka" class="pill <?= $status_filter === 'Terbuka' ? 'active' : '' ?>">
        ✅ Terbuka <span class="pill-count"><?= $stat_terbuka ?></span>
    </a>
    <a href="beasiswa.php?status=Tertutup" class="pill <?= $status_filter === 'Tertutup' ? 'active' : '' ?>">
        🔒 Tertutup <span class="pill-count"><?= $stat_tutup ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="beasiswa.php?jenis=Prestasi+Akademik" class="pill <?= $jenis_filter === 'Prestasi Akademik' ? 'active' : '' ?>">
        🏆 Prestasi
    </a>
    <a href="beasiswa.php?jenis=KIP+Kuliah" class="pill <?= $jenis_filter === 'KIP Kuliah' ? 'active' : '' ?>">
        💰 KIP
    </a>
    <a href="beasiswa.php?jenis=Muhammadiyah" class="pill <?= $jenis_filter === 'Muhammadiyah' ? 'active' : '' ?>">
        🕌 Muhammadiyah
    </a>
    <a href="beasiswa.php?jenis=Talent+Scouting" class="pill <?= $jenis_filter === 'Talent Scouting' ? 'active' : '' ?>">
        🌟 Talent
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama beasiswa atau pemberi..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="jenisFilter">
        <option value="">🏆 Semua Jenis</option>
        <option value="Prestasi Akademik" <?= $jenis_filter === 'Prestasi Akademik' ? 'selected' : '' ?>>🏆 Prestasi Akademik</option>
        <option value="KIP Kuliah" <?= $jenis_filter === 'KIP Kuliah' ? 'selected' : '' ?>>💰 KIP Kuliah</option>
        <option value="Muhammadiyah" <?= $jenis_filter === 'Muhammadiyah' ? 'selected' : '' ?>>🕌 Muhammadiyah</option>
        <option value="Talent Scouting" <?= $jenis_filter === 'Talent Scouting' ? 'selected' : '' ?>>🌟 Talent Scouting</option>
        <option value="Lainnya" <?= $jenis_filter === 'Lainnya' ? 'selected' : '' ?>>📌 Lainnya</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Terbuka" <?= $status_filter === 'Terbuka' ? 'selected' : '' ?>>✅ Terbuka</option>
        <option value="Tertutup" <?= $status_filter === 'Tertutup' ? 'selected' : '' ?>>🔒 Tertutup</option>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📅 Timeline</button>
    </div>

    <a href="beasiswa-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Beasiswa</span>
    </a>

    <div class="export-dropdown">
        <button class="btn-action secondary" onclick="toggleExportMenu(event)">
            <span>📥</span>
            <span>Export</span>
            <span>▾</span>
        </button>
        <div class="export-menu" id="exportMenu">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="export-item">
                <span class="export-item-icon">📊</span>
                <div>
                    <div style="font-weight: 600;">Export CSV</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Untuk Excel/Spreadsheet</div>
                </div>
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'json'])) ?>" class="export-item">
                <span class="export-item-icon">🔧</span>
                <div>
                    <div style="font-weight: 600;">Export JSON</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Untuk integrasi API</div>
                </div>
            </a>
            <a href="#" onclick="window.print(); return false;" class="export-item">
                <span class="export-item-icon">🖨️</span>
                <div>
                    <div style="font-weight: 600;">Print PDF</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak laporan</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>beasiswa dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <select name="new_status" class="bulk-btn primary">
                <option value="Terbuka">✅ Set Terbuka</option>
                <option value="Tertutup">🔒 Set Tertutup</option>
            </select>
            <button type="submit" name="action" value="bulk_status" class="bulk-btn primary">Ubah Status</button>
            <select name="extend_days" class="bulk-btn warning">
                <option value="7">+7 hari</option>
                <option value="14">+14 hari</option>
                <option value="30" selected>+30 hari</option>
                <option value="90">+90 hari</option>
            </select>
            <button type="submit" name="action" value="bulk_extend" class="bulk-btn warning" onclick="return confirm('Perpanjang deadline untuk semua beasiswa terpilih?')">⏰ Perpanjang</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus semua beasiswa terpilih? Aksi ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== TABLE / CARD / TIMELINE VIEW ===== -->
<?php if (empty($beasiswa_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🎓</div>
        <h3>Belum ada data beasiswa</h3>
        <p>Mulai tambahkan program beasiswa untuk membantu mahasiswa FKIP UNIMOF.</p>
        <a href="beasiswa-form.php" class="btn-action primary">+ Tambah Beasiswa Pertama</a>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🎓 Daftar Program Beasiswa</h2>
                <p>Kelola program beasiswa untuk mahasiswa</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($beasiswa_list) ?> data
            </span>
        </div>

        <?php if ($view_mode === 'card'): ?>
            <!-- CARD VIEW -->
            <div class="cards-grid">
                <?php foreach ($beasiswa_list as $b):
                    $jenis_class = strtolower(str_replace(' ', '-', $b['jenis'] ?? 'lainnya'));
                    $status_class = strtolower($b['status']) === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup';
                    $is_urgent = $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days') && $b['status'] === 'Terbuka';
                    $days_left = $b['deadline'] ? floor((strtotime($b['deadline']) - time()) / 86400) : null;
                    $colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899'];
                    $color = $colors[array_rand($colors)];
                ?>
                <div class="beasiswa-card" style="--card-color: <?= $color ?>;" onclick="showDetail(<?= $b['id'] ?>)">
                    <div class="beasiswa-card-body">
                        <div class="beasiswa-card-header">
                            <div class="beasiswa-card-name"><?= sanitize($b['nama']) ?></div>
                            <span class="badge-extreme <?= $status_class ?>"><?= $b['status'] ?></span>
                        </div>
                        <div class="beasiswa-card-source">🏛️ <?= sanitize($b['sumber'] ?: 'Tidak disebutkan') ?></div>
                        <div class="beasiswa-card-info">
                            <div class="beasiswa-card-info-item">
                                <span class="label">Jenis</span>
                                <span class="badge-extreme badge-<?= $jenis_class ?>" style="padding: 0.25rem 0.5rem; font-size: 0.65rem;"><?= sanitize($b['jenis']) ?></span>
                            </div>
                            <div class="beasiswa-card-info-item">
                                <span class="label">Nominal</span>
                                <span class="value" style="color: #059669;"><?= sanitize($b['nominal'] ?: '-') ?></span>
                            </div>
                            <div class="beasiswa-card-info-item">
                                <span class="label">Deadline</span>
                                <span class="value" style="color: <?= $is_urgent ? '#ef4444' : 'inherit' ?>; font-size: 0.82rem;"><?= $b['deadline'] ? date('d M Y', strtotime($b['deadline'])) : 'Sepanjang tahun' ?></span>
                            </div>
                        </div>
                        <?php if ($days_left !== null && $days_left >= 0): ?>
                        <div class="countdown-ring-mini <?= $days_left <= 7 ? 'urgent' : ($days_left <= 30 ? 'warning' : '') ?>">
                            <svg viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15" class="ring-bg"/>
                                <circle cx="18" cy="18" r="15" class="ring-fill" style="stroke-dasharray: <?= min(100, ($days_left / 30) * 100) ?>, 100"/>
                            </svg>
                            ⏰ <?= $days_left ?> hari
                        </div>
                        <?php elseif ($days_left !== null && $days_left < 0): ?>
                        <div class="countdown-ring-mini urgent">
                            ❌ Kadaluarsa <?= abs($days_left) ?> hari lalu
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="beasiswa-card-footer">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Klik untuk detail</small>
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <a href="beasiswa-form.php?id=<?= $b['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by deadline month) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($beasiswa_list as $b) {
                    if (!$b['deadline']) {
                        $grouped['Tanpa Deadline'][] = $b;
                    } else {
                        $key = date('Y-m', strtotime($b['deadline']));
                        $label = date('F Y', strtotime($b['deadline']));
                        $grouped[$label][] = $b;
                    }
                }
                ksort($grouped);
                foreach ($grouped as $month => $items):
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.5rem;">📅</span>
                        <span class="timeline-month"><?= $month ?></span>
                        <span class="timeline-count"><?= count($items) ?> beasiswa</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $b):
                            $is_urgent = $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days') && $b['status'] === 'Terbuka';
                            $days_left = $b['deadline'] ? floor((strtotime($b['deadline']) - time()) / 86400) : null;
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $b['id'] ?>)">
                            <?php if ($b['deadline']): ?>
                            <div class="timeline-date-box" style="<?= $is_urgent ? 'background: linear-gradient(135deg, #ef4444, #dc2626);' : '' ?>">
                                <div class="timeline-day"><?= date('d', strtotime($b['deadline'])) ?></div>
                                <div class="timeline-month-small"><?= date('M', strtotime($b['deadline'])) ?></div>
                            </div>
                            <?php else: ?>
                            <div class="timeline-date-box" style="background: var(--bg-tertiary); color: var(--text-muted);">
                                <div class="timeline-day">∞</div>
                                <div class="timeline-month-small">N/A</div>
                            </div>
                            <?php endif; ?>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($b['nama']) ?></div>
                                <div class="timeline-item-role">
                                    <?= sanitize($b['jenis']) ?>
                                    <?php if ($days_left !== null && $days_left >= 0 && $days_left <= 30): ?>
                                        • <span style="color: <?= $days_left <= 7 ? '#ef4444' : '#f59e0b' ?>; font-weight: 700;"><?= $days_left ?> hari lagi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- TABLE VIEW (default) -->
            <div class="table-wrapper">
                <table class="extreme" id="beasiswaTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th data-sortable="nama">Nama Beasiswa <span class="sort-icon">↕</span></th>
                            <th data-sortable="jenis">Jenis <span class="sort-icon">↕</span></th>
                            <th data-sortable="nominal">Nominal <span class="sort-icon">↕</span></th>
                            <th data-sortable="deadline">Deadline <span class="sort-icon">↕</span></th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($beasiswa_list as $b):
                        $jenis_class = strtolower(str_replace(' ', '-', $b['jenis'] ?? 'lainnya'));
                        $status_class = strtolower($b['status']) === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup';
                        $is_urgent = $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days') && $b['status'] === 'Terbuka';
                        $days_left = $b['deadline'] ? floor((strtotime($b['deadline']) - time()) / 86400) : null;
                    ?>
                    <tr data-id="<?= $b['id'] ?>" data-search="<?= strtolower(sanitize($b['nama'] . ' ' . ($b['sumber'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="row-checkbox row-select" value="<?= $b['id'] ?>" onchange="updateBulkCount()">
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= sanitize($b['nama']) ?></strong>
                            <br>
                            <small style="color: var(--text-muted); font-size: 0.78rem;">🏛️ <?= sanitize($b['sumber'] ?: 'Tidak disebutkan') ?></small>
                        </td>
                        <td><span class="badge-extreme badge-<?= $jenis_class ?>"><?= sanitize($b['jenis']) ?></span></td>
                        <td>
                            <div class="nominal-display">
                                <span class="nominal-amount"><?= sanitize($b['nominal'] ?: '-') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($b['deadline']): ?>
                                <div style="font-weight: 600; color: <?= $is_urgent ? '#ef4444' : 'var(--text-primary)' ?>; font-size: 0.88rem;">
                                    <?= date('d M Y', strtotime($b['deadline'])) ?>
                                    <?php if ($days_left !== null && $days_left < 0): ?>
                                        <div style="font-size: 0.7rem; color: #ef4444; font-weight: 700; margin-top: 0.2rem;">
                                            ❌ Kadaluarsa <?= abs($days_left) ?> hari lalu
                                        </div>
                                    <?php elseif ($days_left !== null && $days_left <= 30): ?>
                                        <div style="font-size: 0.72rem; color: <?= $days_left <= 7 ? '#ef4444' : '#f59e0b' ?>; font-weight: 700; margin-top: 0.2rem;">
                                            ⏰ <?= $days_left ?> hari lagi
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic;">Sepanjang tahun</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $status_class ?> <?= ($is_urgent && $days_left !== null && $days_left <= 7) ? 'badge-urgent' : '' ?>">
                                <?= $b['status'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $b['id'] ?>)" title="Detail">👁️</button>
                                <a href="beasiswa-form.php?id=<?= $b['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status beasiswa ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus beasiswa ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn-icon delete" title="Hapus">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay" id="detailModal" onclick="if(event.target===this)closeDetailModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeDetailModal()">✕</button>
        <div class="modal-header-beasiswa">
            <div class="modal-profile-row">
                <div class="modal-beasiswa-icon" id="modalIcon">🎓</div>
                <div class="modal-profile-info">
                    <div class="modal-profile-name" id="modalName">-</div>
                    <div class="modal-profile-source" id="modalSource">-</div>
                    <div class="modal-profile-type" id="modalType">-</div>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('countdown', this)">⏰ Countdown</button>
                <button class="detail-tab" onclick="switchDetailTab('details', this)">📋 Detail</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-countdown">
                <div id="detailCountdown"></div>
            </div>

            <div class="detail-tab-content" id="tab-details">
                <div id="detailDetails"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const beasiswaData = <?= json_encode($beasiswa_list) ?>;

// ===== COUNT UP ANIMATION =====
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

// ===== CHARTS =====
<?php if (!empty($jenis_stats)): ?>
new ApexCharts(document.querySelector("#jenisChart"), {
    series: <?= json_encode(array_values($jenis_stats)) ?>,
    labels: <?= json_encode(array_keys($jenis_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#6b7280'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Aktif',
                        formatter: () => <?= $stat_terbuka ?>
                    }
                }
            }
        }
    },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_terbuka ?>, <?= $stat_tutup ?>],
    labels: ['Terbuka', 'Tertutup'],
    chart: { type: 'pie', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#64748b'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

<?php if (!empty($deadline_timeline)): ?>
new ApexCharts(document.querySelector("#timelineChart"), {
    series: [{ name: 'Beasiswa', data: <?= json_encode(array_values($deadline_timeline)) ?> }],
    chart: { type: 'area', height: 240, toolbar: { show: false } },
    colors: ['#10b981'],
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 }
    },
    stroke: { curve: 'smooth', width: 3 },
    xaxis: {
        categories: <?= json_encode(array_keys($deadline_timeline)) ?>,
        labels: { style: { fontSize: '10px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: v => v + ' beasiswa' } }
}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const jenisFilter = document.getElementById('jenisFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
jenisFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const jenis = jenisFilter.value;
    const status = statusFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jenis) url.searchParams.set('jenis', jenis); else url.searchParams.delete('jenis');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== EXPORT DROPDOWN =====
function toggleExportMenu(e) {
    e.stopPropagation();
    document.getElementById('exportMenu').classList.toggle('show');
}
document.addEventListener('click', (e) => {
    if (!e.target.closest('.export-dropdown')) {
        document.getElementById('exportMenu')?.classList.remove('show');
    }
});

// ===== TABLE SORTING =====
document.querySelectorAll('table.extreme th[data-sortable]').forEach(th => {
    th.addEventListener('click', () => {
        const table = th.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(th.parentElement.children).indexOf(th);
        const isAsc = th.classList.contains('asc');

        table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));

        rows.sort((a, b) => {
            const aText = a.children[index]?.textContent.trim() || '';
            const bText = b.children[index]?.textContent.trim() || '';
            const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? bNum - aNum : aNum - bNum;
            }
            return isAsc ? bText.localeCompare(aText) : aText.localeCompare(bText);
        });

        rows.forEach(row => tbody.appendChild(row));
        th.classList.add(isAsc ? 'desc' : 'asc');

        showToast('Diurutkan', `Tabel diurutkan ${isAsc ? 'menurun' : 'menaik'}`, 'info');
    });
});

// ===== BULK SELECTION =====
function toggleSelectAll(el) {
    document.querySelectorAll('.row-select').forEach(cb => cb.checked = el.checked);
    document.querySelectorAll('tbody tr').forEach(tr => tr.classList.toggle('selected', el.checked));
    updateBulkCount();
}

function updateBulkCount() {
    const checked = document.querySelectorAll('.row-select:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('tbody tr').forEach(tr => {
        const cb = tr.querySelector('.row-select');
        tr.classList.toggle('selected', cb?.checked);
    });

    const bulkForm = document.getElementById('bulkForm');
    bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        bulkForm.appendChild(input);
    });
}

function clearSelection() {
    document.querySelectorAll('.row-select').forEach(cb => cb.checked = false);
    document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected'));
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = beasiswaData.find(b => b.id == id);
    if (!data) return;

    document.getElementById('modalName').textContent = data.nama || '-';
    document.getElementById('modalSource').textContent = '🏛️ ' + (data.sumber || 'Tidak disebutkan');
    document.getElementById('modalType').textContent = data.jenis || '-';

    // Info tab
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const tglTerbit = data.tanggal_terbit ? new Date(data.tanggal_terbit) : null;
    const deadline = data.deadline ? new Date(data.deadline) : null;

    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">🎓 Nama Beasiswa</div>
            <div class="detail-item-value">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏆 Jenis</div>
            <div class="detail-item-value">${escapeHtml(data.jenis || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏛️ Sumber</div>
            <div class="detail-item-value">${escapeHtml(data.sumber || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value"><span class="badge-extreme ${data.status === 'Terbuka' ? 'badge-terbuka' : 'badge-tertutup'}">${escapeHtml(data.status || '-')}</span></div>
        </div>
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">💰 Nominal / Cakupan</div>
            <div class="detail-item-value" style="color: #059669; font-size: 1.1rem;">${escapeHtml(data.nominal || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tanggal Terbit</div>
            <div class="detail-item-value">${tglTerbit ? tglTerbit.getDate() + ' ' + months[tglTerbit.getMonth()] + ' ' + tglTerbit.getFullYear() : '-'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">⏰ Deadline</div>
            <div class="detail-item-value">${deadline ? deadline.getDate() + ' ' + months[deadline.getMonth()] + ' ' + deadline.getFullYear() : 'Sepanjang tahun'}</div>
        </div>
    `;

    // Countdown tab
    if (deadline) {
        const now = new Date();
        const diff = deadline - now;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        let urgencyClass = 'safe';
        let urgencyText = 'Aman';
        if (diff < 0) { urgencyClass = 'critical'; urgencyText = 'Sudah Lewat'; }
        else if (days <= 7) { urgencyClass = 'critical'; urgencyText = 'URGENT!'; }
        else if (days <= 30) { urgencyClass = ''; urgencyText = 'Perhatian'; }

        document.getElementById('detailCountdown').innerHTML = `
            <div class="countdown-big ${urgencyClass}">
                <div class="countdown-big-label">${urgencyText}</div>
                <div style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem;">
                    ${diff < 0 ? 'Sudah lewat ' + Math.abs(days) + ' hari' : 'Sisa Waktu Pendaftaran'}
                </div>
                ${diff >= 0 ? `
                <div class="countdown-big-numbers">
                    <div class="countdown-unit">
                        <div class="countdown-unit-value" id="cd-days">${days}</div>
                        <div class="countdown-unit-label">Hari</div>
                    </div>
                    <div class="countdown-unit">
                        <div class="countdown-unit-value" id="cd-hours">${hours}</div>
                        <div class="countdown-unit-label">Jam</div>
                    </div>
                    <div class="countdown-unit">
                        <div class="countdown-unit-value" id="cd-minutes">${minutes}</div>
                        <div class="countdown-unit-label">Menit</div>
                    </div>
                    <div class="countdown-unit">
                        <div class="countdown-unit-value" id="cd-seconds">${seconds}</div>
                        <div class="countdown-unit-label">Detik</div>
                    </div>
                </div>
                ` : ''}
                <div style="font-size: 0.85rem; margin-top: 0.5rem;">
                    📅 Deadline: <strong>${deadline.getDate()} ${months[deadline.getMonth()]} ${deadline.getFullYear()}</strong>
                </div>
            </div>
        `;

        // Live countdown update
        if (diff >= 0 && window.countdownInterval) clearInterval(window.countdownInterval);
        if (diff >= 0) {
            window.countdownInterval = setInterval(() => {
                const now2 = new Date();
                const diff2 = deadline - now2;
                if (diff2 < 0) {
                    clearInterval(window.countdownInterval);
                    document.getElementById('detailCountdown').innerHTML = `
                        <div class="countdown-big critical">
                            <div style="font-size: 3rem;">⏰</div>
                            <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.5rem;">Deadline Telah Lewat!</div>
                        </div>
                    `;
                    return;
                }
                const d = Math.floor(diff2 / (1000 * 60 * 60 * 24));
                const h = Math.floor((diff2 % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const m = Math.floor((diff2 % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff2 % (1000 * 60)) / 1000);
                const el = (id) => document.getElementById(id);
                if (el('cd-days')) el('cd-days').textContent = d;
                if (el('cd-hours')) el('cd-hours').textContent = h;
                if (el('cd-minutes')) el('cd-minutes').textContent = m;
                if (el('cd-seconds')) el('cd-seconds').textContent = s;
            }, 1000);
        }
    } else {
        document.getElementById('detailCountdown').innerHTML = `
            <div class="countdown-big safe">
                <div style="font-size: 3rem;">🎉</div>
                <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.5rem;">Pendaftaran Sepanjang Tahun</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Tidak ada batasan waktu pendaftaran</div>
            </div>
        `;
    }

    // Details tab
    document.getElementById('detailDetails').innerHTML = `
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="margin-bottom: 0.75rem; font-size: 1rem;">📝 Deskripsi & Persyaratan</h4>
            <div style="font-size: 0.88rem; line-height: 1.6; color: var(--text-secondary); white-space: pre-wrap;">
                ${escapeHtml(data.deskripsi || 'Belum ada deskripsi lengkap. Silakan edit untuk menambahkan detail persyaratan, cakupan, dan cara pendaftaran.')}
            </div>
        </div>
        ${data.link_pendaftaran ? `
        <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, #dcfce7, #bbf7d0); border: 1px solid #86efac; border-radius: var(--radius-md);">
            <div style="font-weight: 700; margin-bottom: 0.35rem;">🔗 Link Pendaftaran</div>
            <a href="${escapeHtml(data.link_pendaftaran)}" target="_blank" style="color: #166534; word-break: break-all; font-size: 0.85rem;">${escapeHtml(data.link_pendaftaran)}</a>
        </div>
        ` : ''}
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="beasiswa-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Beasiswa</a>
        ${data.link_pendaftaran ? `<a href="${escapeHtml(data.link_pendaftaran)}" target="_blank" class="btn-action secondary" style="justify-content: flex-start; background: #10b981; color: white; border-color: #10b981;">🌐 Buka Link Pendaftaran</a>` : ''}
        <button onclick="shareBeasiswa(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan ke Mahasiswa</button>
        <a href="beasiswa-form.php?duplicate=${data.id}" class="btn-action secondary" style="justify-content: flex-start;" onclick="return confirm('Duplikasi data beasiswa ini?')">📋 Duplikasi Data</a>
    `;

    document.getElementById('detailModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
    document.body.style.overflow = '';
    if (window.countdownInterval) {
        clearInterval(window.countdownInterval);
        window.countdownInterval = null;
    }
}

function switchDetailTab(tab, btn) {
    document.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function shareBeasiswa(id) {
    const data = beasiswaData.find(b => b.id == id);
    if (!data) return;
    const deadlineText = data.deadline
        ? `Deadline: ${new Date(data.deadline).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}`
        : 'Pendaftaran sepanjang tahun';
    const text = `🎓 ${data.nama}\n💰 ${data.nominal || '-'}\n🏛️ ${data.sumber || '-'}\n📅 ${deadlineText}\n\nInfo beasiswa dari FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info beasiswa disalin ke clipboard', 'success');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeDetailModal();
    }
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        searchInput?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'beasiswa-form.php';
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        window.location = window.location.pathname + '?export=csv';
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && document.activeElement.tagName !== 'INPUT') {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            e.preventDefault();
            selectAll.checked = !selectAll.checked;
            toggleSelectAll(selectAll);
        }
    }
});

// ===== TOAST HELPER =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else if (window.showToast) {
        window.showToast(title, message, type);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

console.log('%c🎓 Kelola Beasiswa FKIP UNIMOF - Super Extreme', 'color: #10b981; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Timeline), Live Countdown, Bulk Actions, Detail Modal, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>