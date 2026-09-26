<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== AUTO-UPDATE STATUS KADALUARSA (NEW!) =====
// Update otomatis status ke 'Kadaluarsa' jika tanggal_berlaku sudah lewat
try {
    $pdo->exec("UPDATE akreditasi SET status = 'Kadaluarsa' WHERE tanggal_berlaku < CURDATE() AND status = 'Aktif'");
} catch (Exception $e) {}

// ===== FETCH ALL DATA UNTUK EXPORT & VIEW =====
// (FIX: Fetch dulu sebelum dipakai di export)
$q_all = trim($_GET['q'] ?? '');
$peringkat_filter_all = $_GET['peringkat'] ?? '';
$status_filter_all = $_GET['status'] ?? '';

$where_all = 'WHERE 1=1';
$params_all = [];
if ($q_all !== '') { 
    $where_all .= ' AND (nama_prodi LIKE ? OR nomor_sk LIKE ? OR badan_akreditasi LIKE ?)'; 
    $params_all[] = "%$q_all%"; $params_all[] = "%$q_all%"; $params_all[] = "%$q_all%";
}
if ($peringkat_filter_all !== '') { $where_all .= ' AND peringkat = ?'; $params_all[] = $peringkat_filter_all; }
if ($status_filter_all !== '') { $where_all .= ' AND status = ?'; $params_all[] = $status_filter_all; }

$stmt_all = $pdo->prepare("SELECT * FROM akreditasi $where_all ORDER BY FIELD(peringkat, 'Unggul', 'Baik Sekali', 'Baik', 'C', 'Proses Akreditasi'), tanggal_berlaku DESC");
$stmt_all->execute($params_all);
$akreditasi_list = $stmt_all->fetchAll();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT sertifikat_file FROM akreditasi WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if (function_exists('delete_upload')) delete_upload($file, 'akreditasi');
        $pdo->prepare("DELETE FROM akreditasi WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Data akreditasi berhasil dihapus.');
    } 
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT sertifikat_file FROM akreditasi WHERE id = ?");
            $stmt->execute([$del_id]);
            $file = $stmt->fetchColumn();
            if (function_exists('delete_upload')) delete_upload($file, 'akreditasi');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM akreditasi WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' data akreditasi berhasil dihapus.');
    } 
    elseif ($action === 'bulk_status' && !empty($ids)) {
        $new_status = $_POST['new_status'] ?? 'Aktif';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_status], $ids);
        $pdo->prepare("UPDATE akreditasi SET status = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', 'Status ' . count($ids) . ' akreditasi diperbarui ke ' . $new_status);
    }
    elseif ($action === 'bulk_extend') {
        // Bulk perpanjang 5 tahun
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, $ids);
        $pdo->prepare("UPDATE akreditasi SET tanggal_berlaku = DATE_ADD(tanggal_berlaku, INTERVAL 5 YEAR), status = 'Aktif' WHERE id IN ($placeholders) AND tanggal_berlaku IS NOT NULL")->execute($params);
        flash_message('success', count($ids) . ' akreditasi diperpanjang 5 tahun.');
    }
    
    header('Location: akreditasi.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export']) && !empty($akreditasi_list)) {
    $format = $_GET['export'];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akreditasi-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Program Studi', 'Badan Akreditasi', 'Peringkat', 'Nomor SK', 'Tanggal Terbit', 'Tanggal Berlaku', 'Status', 'Sisa Hari']);
        foreach ($akreditasi_list as $r) {
            $days = $r['tanggal_berlaku'] ? floor((strtotime($r['tanggal_berlaku']) - time()) / 86400) : '';
            fputcsv($out, [
                $r['id'], $r['nama_prodi'], $r['badan_akreditasi'], $r['peringkat'],
                $r['nomor_sk'], $r['tanggal_terbit'], $r['tanggal_berlaku'], $r['status'], $days
            ]);
        }
        fclose($out);
        exit;
    }
    
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="akreditasi-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($akreditasi_list),
            'data' => $akreditasi_list
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$peringkat_filter = $_GET['peringkat'] ?? '';
$status_filter = $_GET['status'] ?? '';
$view_mode = $_GET['view'] ?? 'table';

// Data sudah difetch di atas ($akreditasi_list)

// ===== STATISTIK LENGKAP =====
$stat_total = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi")->fetchColumn();
$stat_unggul = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Unggul'")->fetchColumn();
$stat_baik = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Baik Sekali'")->fetchColumn();
$stat_baik_c = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Baik'")->fetchColumn();
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE status='Aktif'")->fetchColumn();
$stat_kadaluarsa = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE status='Kadaluarsa'")->fetchColumn();
$stat_proses = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE status='Proses'")->fetchColumn();

// Skor kualitas akreditasi (bobot: Unggul=100, Baik Sekali=75, Baik=50, C=25)
$quality_score = $stat_total > 0 ? round(
    (($stat_unggul * 100) + ($stat_baik * 75) + ($stat_baik_c * 50)) / $stat_total
) : 0;

// Chart data
$chart_data = [
    'Unggul' => $stat_unggul,
    'Baik Sekali' => $stat_baik,
    'Baik' => $stat_baik_c,
    'Lainnya' => max(0, $stat_total - $stat_unggul - $stat_baik - $stat_baik_c)
];

// Trend: bandingkan dengan bulan lalu (simulasi)
$month_ago = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$trend_total = $month_ago > 0 ? round((($stat_total - $month_ago) / $month_ago) * 100, 1) : 0;

// Akreditasi yang akan kadaluarsa dalam 6 bulan
$expiring_soon = $pdo->query("SELECT * FROM akreditasi WHERE tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH) AND status='Aktif' ORDER BY tanggal_berlaku ASC LIMIT 10")->fetchAll();

// Akreditasi urgent (< 90 hari)
$urgent_count = 0;
foreach ($expiring_soon as $e) {
    $days = floor((strtotime($e['tanggal_berlaku']) - time()) / 86400);
    if ($days <= 90) $urgent_count++;
}

$csrf = generate_csrf_token();
$active_menu = 'akreditasi';
$page_heading = 'Kelola Akreditasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Akreditasi', null]];

require __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HEADER HERO ===== */
.akreditasi-hero {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
}
.akreditasi-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.akreditasi-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}
.akreditasi-hero h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.akreditasi-hero p { opacity: 0.95; font-size: 0.95rem; }
.quality-score-ring {
    width: 100px;
    height: 100px;
    position: relative;
    flex-shrink: 0;
}
.quality-score-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.quality-score-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.quality-score-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.quality-score-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.quality-score-value .score-num { font-size: 1.75rem; font-weight: 900; line-height: 1; }
.quality-score-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.15rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS EXTREME ===== */
.stats-extreme {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
.stat-mini-chart { margin-top: 0.5rem; height: 30px; }

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
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.pill .pill-count {
    background: rgba(255,255,255,0.3);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    min-width: 22px;
    text-align: center;
}
.pill.active .pill-count { background: rgba(255,255,255,0.3); }
.pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TOOLBAR EXTREME ===== */
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
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
    background: var(--bg-primary);
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
.filter-select:focus { outline: none; border-color: var(--primary); }

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
.view-btn.active { background: var(--primary); color: white; }
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
    min-width: 200px;
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

/* ===== BULK ACTION BAR ===== */
.bulk-bar {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(10,104,71,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: var(--primary); padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: var(--primary); }
.bulk-btn.warning { background: #f59e0b; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* ===== CHART SECTION ===== */
.chart-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
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

/* ===== EXPIRING SOON ALERT ===== */
.expiring-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.5s ease;
    position: relative;
    overflow: hidden;
}
.expiring-alert.urgent {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border-color: #fca5a5;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.expiring-icon { font-size: 2rem; flex-shrink: 0; animation: pulse 2s infinite; }
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.expiring-content { flex: 1; }
.expiring-content h4 { font-size: 0.95rem; font-weight: 700; color: #92400e; margin-bottom: 0.25rem; }
.expiring-alert.urgent .expiring-content h4 { color: #991b1b; }
.expiring-content p { font-size: 0.82rem; color: #78350f; margin: 0; }

/* ===== TABLE EXTREME ===== */
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
table.extreme th[data-sortable]:hover { color: var(--primary); }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: var(--primary); }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(10,104,71,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

/* Checkbox column */
.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }

/* Search highlight */
.search-highlight { background: #fef08a; padding: 0 0.15rem; border-radius: 2px; font-weight: 700; }

/* ===== BADGES EXTREME ===== */
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
.badge-unggul { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-baik-sekali { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-baik { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-c { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-kadaluarsa { background: #fee2e2; color: #991b1b; }
.badge-proses { background: #fef3c7; color: #92400e; }

.badge-urgent { animation: urgentPulse 1.5s infinite; }
@keyframes urgentPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
    50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

/* ===== PROGRESS BAR ===== */
.progress-bar-container {
    width: 100%;
    height: 6px;
    background: var(--bg-secondary);
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.4rem;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 1s ease;
}
.progress-bar-fill.ok { background: linear-gradient(90deg, #10b981, #059669); }
.progress-bar-fill.warning { background: linear-gradient(90deg, #f59e0b, #d97706); }
.progress-bar-fill.danger { background: linear-gradient(90deg, #ef4444, #dc2626); }

/* ===== ACTION BUTTONS ===== */
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
.btn-icon.download { background: #dcfce7; color: #16a34a; }
.btn-icon.download:hover { background: #16a34a; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== CARD VIEW ===== */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.akreditasi-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.akreditasi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, var(--primary));
}
.akreditasi-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-accent, var(--primary));
}
.akreditasi-card-body { padding: 1.25rem; }
.akreditasi-card-prodi {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}
.akreditasi-card-badan {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.akreditasi-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.akreditasi-card-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
}
.akreditasi-card-info-item .label { color: var(--text-muted); }
.akreditasi-card-info-item .value { font-weight: 600; color: var(--text-primary); }
.akreditasi-card-footer {
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
}
.timeline-month {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    font-family: var(--font-display);
}
.timeline-count {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}
.timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    margin-bottom: 0.6rem;
    border-left: 3px solid var(--primary);
    transition: all 0.2s;
    cursor: pointer;
}
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(4px); }
.timeline-date {
    text-align: center;
    flex-shrink: 0;
    width: 50px;
}
.timeline-day { font-size: 1.5rem; font-weight: 900; line-height: 1; color: var(--primary); }
.timeline-month-small { font-size: 0.68rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
.timeline-content { flex: 1; min-width: 0; }
.timeline-title { font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary); }
.timeline-meta { font-size: 0.78rem; color: var(--text-muted); display: flex; gap: 0.75rem; flex-wrap: wrap; }

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
.empty-state-extreme h3 { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text-primary); }
.empty-state-extreme p { color: var(--text-muted); margin-bottom: 1.5rem; }

/* ===== MODAL ===== */
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
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
}
@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.modal-header {
    padding: 2rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
}
.modal-header h3 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem; }
.modal-header p { opacity: 0.95; font-size: 0.9rem; margin: 0; }
.modal-body { padding: 2rem; }
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
}
.modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

/* Detail info grid */
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
}
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

/* Tabs in modal */
.detail-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid var(--border);
    margin-bottom: 1.5rem;
}
.detail-tab {
    padding: 0.65rem 1rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    color: var(--text-muted);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.85rem;
}
.detail-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.detail-tab-content { display: none; }
.detail-tab-content.active { display: block; animation: adminFadeIn 0.3s; }

/* Expiring list in modal */
.expiring-list-item {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    margin-bottom: 0.75rem;
    border-left: 4px solid var(--border);
    transition: all 0.2s;
}
.expiring-list-item.urgent { border-left-color: #ef4444; }
.expiring-list-item.warning { border-left-color: #f59e0b; }
.expiring-list-item:hover { background: var(--bg-tertiary); transform: translateX(3px); }

@media (max-width: 1024px) {
    .chart-section { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .detail-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .akreditasi-hero-content { flex-direction: column; text-align: center; }
    .quality-score-ring { margin: 0 auto; }
    .cards-grid { grid-template-columns: 1fr; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .expiring-alert, .stats-extreme, .akreditasi-hero { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="akreditasi-hero" data-aos="fade-down">
    <div class="akreditasi-hero-content">
        <div>
            <h2>🏆 Manajemen Akreditasi</h2>
            <p>Kelola data akreditasi semua program studi FKIP UNIMOF dengan mudah dan terorganisir</p>
        </div>
        <div class="quality-score-ring">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $quality_score ?>, 100"/>
            </svg>
            <div class="quality-score-value">
                <div class="score-num"><?= $quality_score ?></div>
                <div class="score-label">Quality</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS EXTREME ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🏆</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Terakreditasi</div>
        <div class="stat-trend <?= $trend_total > 0 ? 'up' : ($trend_total < 0 ? 'down' : 'neutral') ?>">
            <?= $trend_total > 0 ? '↑' : ($trend_total < 0 ? '↓' : '→') ?> 
            <?= abs($trend_total) ?>% dari bulan lalu
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">🥇</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_unggul ?>">0</div>
        <div class="stat-label-extreme">Peringkat Unggul</div>
        <div class="stat-trend up">
            ↑ <?= $stat_total > 0 ? round($stat_unggul / $stat_total * 100) : 0 ?>% dari total
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-icon-extreme">🥈</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_baik ?>">0</div>
        <div class="stat-label-extreme">Baik Sekali</div>
        <div class="stat-trend neutral">
            ⚖️ <?= $stat_total > 0 ? round($stat_baik / $stat_total * 100) : 0 ?>% dari total
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Status Aktif</div>
        <div class="stat-trend up">🟢 Berjalan</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ef4444;">
        <div class="stat-icon-extreme">⏰</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_kadaluarsa ?>">0</div>
        <div class="stat-label-extreme">Kadaluarsa</div>
        <div class="stat-trend <?= $stat_kadaluarsa > 0 ? 'down' : 'up' ?>">
            <?= $stat_kadaluarsa > 0 ? '⚠️ Perlu perpanjangan' : '✓ Semua aktif' ?>
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">⏳</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_proses ?>">0</div>
        <div class="stat-label-extreme">Dalam Proses</div>
        <div class="stat-trend neutral">🔄 Pengajuan</div>
    </div>
</div>

<!-- ===== EXPIRING SOON ALERT ===== -->
<?php if (!empty($expiring_soon)): ?>
<div class="expiring-alert <?= $urgent_count > 0 ? 'urgent' : '' ?>" data-aos="fade-down">
    <div class="expiring-icon"><?= $urgent_count > 0 ? '🚨' : '⚠️' ?></div>
    <div class="expiring-content">
        <h4>
            <?= $urgent_count > 0 
                ? "URGENT: $urgent_count akreditasi akan kadaluarsa dalam 90 hari!" 
                : "Perhatian: " . count($expiring_soon) . " akreditasi akan kadaluarsa dalam 6 bulan" ?>
        </h4>
        <p>
            <?= $urgent_count > 0 
                ? 'Segera lakukan perpanjangan untuk menghindari status kadaluarsa.'
                : 'Ada ' . count($expiring_soon) . ' program studi yang perlu disiapkan perpanjangannya.' ?>
        </p>
    </div>
    <button onclick="showExpiringModal()" class="btn-action primary" style="flex-shrink: 0;">
        Lihat Detail
    </button>
</div>
<?php endif; ?>

<!-- ===== CHART SECTION ===== -->
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Peringkat Akreditasi</h3>
        <div id="peringkatChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Status Akreditasi</h3>
        <div id="statusChart"></div>
    </div>
</div>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="akreditasi.php" class="pill <?= empty($peringkat_filter) && empty($status_filter) ? 'active' : '' ?>">
        🎯 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="akreditasi.php?peringkat=Unggul" class="pill <?= $peringkat_filter === 'Unggul' ? 'active' : '' ?>">
        🥇 Unggul <span class="pill-count"><?= $stat_unggul ?></span>
    </a>
    <a href="akreditasi.php?peringkat=Baik+Sekali" class="pill <?= $peringkat_filter === 'Baik Sekali' ? 'active' : '' ?>">
        🥈 Baik Sekali <span class="pill-count"><?= $stat_baik ?></span>
    </a>
    <a href="akreditasi.php?peringkat=Baik" class="pill <?= $peringkat_filter === 'Baik' ? 'active' : '' ?>">
        🥉 Baik <span class="pill-count"><?= $stat_baik_c ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="akreditasi.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="akreditasi.php?status=Kadaluarsa" class="pill <?= $status_filter === 'Kadaluarsa' ? 'active' : '' ?>">
        ⚠️ Kadaluarsa <span class="pill-count"><?= $stat_kadaluarsa ?></span>
    </a>
    <a href="akreditasi.php?status=Proses" class="pill <?= $status_filter === 'Proses' ? 'active' : '' ?>">
        ⏳ Proses <span class="pill-count"><?= $stat_proses ?></span>
    </a>
</div>

<!-- ===== TOOLBAR EXTREME ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama prodi, nomor SK, atau badan..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="peringkatFilter">
        <option value="">Semua Peringkat</option>
        <option value="Unggul" <?= $peringkat_filter === 'Unggul' ? 'selected' : '' ?>>🥇 Unggul</option>
        <option value="Baik Sekali" <?= $peringkat_filter === 'Baik Sekali' ? 'selected' : '' ?>>🥈 Baik Sekali</option>
        <option value="Baik" <?= $peringkat_filter === 'Baik' ? 'selected' : '' ?>>🥉 Baik</option>
        <option value="C" <?= $peringkat_filter === 'C' ? 'selected' : '' ?>>📋 C</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Kadaluarsa" <?= $status_filter === 'Kadaluarsa' ? 'selected' : '' ?>>⚠️ Kadaluarsa</option>
        <option value="Proses" <?= $status_filter === 'Proses' ? 'selected' : '' ?>>⏳ Proses</option>
    </select>
    
    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📅 Timeline</button>
    </div>
    
    <a href="akreditasi-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Akreditasi</span>
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
        <span>data dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <select name="new_status" class="bulk-btn primary">
                <option value="Aktif">✅ Set Aktif</option>
                <option value="Kadaluarsa">⚠️ Set Kadaluarsa</option>
                <option value="Proses">⏳ Set Proses</option>
            </select>
            <button type="submit" name="action" value="bulk_status" class="bulk-btn primary">Ubah Status</button>
            <button type="submit" name="action" value="bulk_extend" class="bulk-btn warning" onclick="return confirm('Perpanjang 5 tahun untuk semua data terpilih?')">⏰ +5 Tahun</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus semua data terpilih? Aksi ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== TABLE / CARD / TIMELINE VIEW ===== -->
<?php if (empty($akreditasi_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">📜</div>
        <h3>Belum ada data akreditasi</h3>
        <p>Mulai tambahkan data akreditasi program studi Anda untuk memantau kualitas akreditasi fakultas.</p>
        <a href="akreditasi-form.php" class="btn-action primary">+ Tambah Data Pertama</a>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🏆 Data Akreditasi Program Studi</h2>
                <p>Kelola data akreditasi semua program studi FKIP UNIMOF</p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                    📊 <?= count($akreditasi_list) ?> data
                </span>
            </div>
        </div>
        
        <?php if ($view_mode === 'card'): ?>
            <!-- CARD VIEW -->
            <div class="cards-grid">
                <?php foreach ($akreditasi_list as $a): 
                    $badge = strtolower(str_replace(' ', '-', $a['peringkat']));
                    $is_expired = $a['tanggal_berlaku'] && strtotime($a['tanggal_berlaku']) < time();
                    $days_remaining = $a['tanggal_berlaku'] ? floor((strtotime($a['tanggal_berlaku']) - time()) / 86400) : null;
                    $badge_class = 'badge-' . $badge;
                    $status_class = strtolower($a['status']) === 'aktif' ? 'badge-aktif' : (strtolower($a['status']) === 'proses' ? 'badge-proses' : 'badge-kadaluarsa');
                    $accent_colors = ['unggul' => '#f59e0b', 'baik-sekali' => '#3b82f6', 'baik' => '#10b981', 'c' => '#6b7280'];
                    $accent = $accent_colors[$badge] ?? 'var(--primary)';
                ?>
                <div class="akreditasi-card" style="--card-accent: <?= $accent ?>;" onclick="showDetail(<?= $a['id'] ?>)">
                    <div class="akreditasi-card-body">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <span class="badge-extreme <?= $badge_class ?>">
                                <?= $a['peringkat'] === 'Unggul' ? '🥇' : ($a['peringkat'] === 'Baik Sekali' ? '🥈' : ($a['peringkat'] === 'Baik' ? '🥉' : '📋')) ?>
                                <?= sanitize($a['peringkat']) ?>
                            </span>
                            <span class="badge-extreme <?= $status_class ?>"><?= $a['status'] ?></span>
                        </div>
                        <div class="akreditasi-card-prodi"><?= sanitize($a['nama_prodi']) ?></div>
                        <div class="akreditasi-card-badan">🏛️ <?= sanitize($a['badan_akreditasi']) ?></div>
                        <div class="akreditasi-card-info">
                            <div class="akreditasi-card-info-item">
                                <span class="label">No. SK</span>
                                <span class="value" style="font-family: monospace; font-size: 0.75rem;"><?= sanitize($a['nomor_sk'] ?: '-') ?></span>
                            </div>
                            <?php if ($a['tanggal_berlaku']): ?>
                            <div class="akreditasi-card-info-item">
                                <span class="label">Berlaku s/d</span>
                                <span class="value" style="color: <?= $is_expired ? '#ef4444' : 'inherit' ?>;"><?= date('d M Y', strtotime($a['tanggal_berlaku'])) ?></span>
                            </div>
                            <?php if ($days_remaining !== null && $days_remaining > 0 && $days_remaining <= 180): ?>
                            <div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill <?= $days_remaining <= 90 ? 'danger' : 'warning' ?>" style="width: <?= max(0, min(100, ($days_remaining / 180) * 100)) ?>%"></div>
                                </div>
                                <div style="font-size: 0.72rem; color: <?= $days_remaining <= 90 ? '#ef4444' : '#f59e0b' ?>; font-weight: 600; margin-top: 0.25rem;">
                                    ⏰ <?= $days_remaining ?> hari lagi
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="akreditasi-card-footer">
                        <small style="color: var(--text-muted); font-size: 0.72rem;">Klik untuk detail</small>
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <?php if (!empty($a['sertifikat_file'])): ?>
                                <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="btn-icon download" title="Unduh Sertifikat">📥</a>
                            <?php endif; ?>
                            <a href="akreditasi-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        
        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by expiry year) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($akreditasi_list as $a) {
                    if (!$a['tanggal_berlaku']) {
                        $grouped['Tanpa Tanggal'][] = $a;
                    } else {
                        $year = date('Y', strtotime($a['tanggal_berlaku']));
                        $grouped[$year][] = $a;
                    }
                }
                ksort($grouped);
                foreach ($grouped as $year => $items):
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.5rem;">📅</span>
                        <span class="timeline-month"><?= $year === 'Tanpa Tanggal' ? 'Tanpa Tanggal' : 'Kadaluarsa ' . $year ?></span>
                        <span class="timeline-count"><?= count($items) ?></span>
                    </div>
                    <?php foreach ($items as $a): 
                        $is_expired = $a['tanggal_berlaku'] && strtotime($a['tanggal_berlaku']) < time();
                        $days_remaining = $a['tanggal_berlaku'] ? floor((strtotime($a['tanggal_berlaku']) - time()) / 86400) : null;
                        $badge = strtolower(str_replace(' ', '-', $a['peringkat']));
                        $badge_class = 'badge-' . $badge;
                    ?>
                    <div class="timeline-item" onclick="showDetail(<?= $a['id'] ?>)" style="border-left-color: <?= $is_expired ? '#ef4444' : ($days_remaining && $days_remaining <= 180 ? '#f59e0b' : 'var(--primary)') ?>;">
                        <?php if ($a['tanggal_berlaku']): ?>
                        <div class="timeline-date">
                            <div class="timeline-day"><?= date('d', strtotime($a['tanggal_berlaku'])) ?></div>
                            <div class="timeline-month-small"><?= date('M', strtotime($a['tanggal_berlaku'])) ?></div>
                        </div>
                        <?php else: ?>
                        <div class="timeline-date">
                            <div class="timeline-day">-</div>
                            <div class="timeline-month-small">N/A</div>
                        </div>
                        <?php endif; ?>
                        <div class="timeline-content">
                            <div class="timeline-title"><?= sanitize($a['nama_prodi']) ?></div>
                            <div class="timeline-meta">
                                <span class="badge-extreme <?= $badge_class ?>"><?= sanitize($a['peringkat']) ?></span>
                                <span>🏛️ <?= sanitize($a['badan_akreditasi']) ?></span>
                                <?php if ($is_expired): ?>
                                    <span style="color: #ef4444; font-weight: 600;">⚠️ Kadaluarsa</span>
                                <?php elseif ($days_remaining !== null && $days_remaining <= 180): ?>
                                    <span style="color: <?= $days_remaining <= 90 ? '#ef4444' : '#f59e0b' ?>; font-weight: 600;">⏰ <?= $days_remaining ?> hari</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        
        <?php else: ?>
            <!-- TABLE VIEW (default) -->
            <div class="table-wrapper">
                <table class="extreme" id="akreditasiTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th data-sortable="nama_prodi">
                                Program Studi <span class="sort-icon">↕</span>
                            </th>
                            <th data-sortable="badan_akreditasi">
                                Badan <span class="sort-icon">↕</span>
                            </th>
                            <th data-sortable="peringkat">
                                Peringkat <span class="sort-icon">↕</span>
                            </th>
                            <th data-sortable="tanggal_berlaku">
                                Masa Berlaku <span class="sort-icon">↕</span>
                            </th>
                            <th data-sortable="status">
                                Status <span class="sort-icon">↕</span>
                            </th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($akreditasi_list as $a): 
                        $badge = strtolower(str_replace(' ', '-', $a['peringkat']));
                        $is_expired = $a['tanggal_berlaku'] && strtotime($a['tanggal_berlaku']) < time();
                        $days_remaining = $a['tanggal_berlaku'] ? floor((strtotime($a['tanggal_berlaku']) - time()) / 86400) : null;
                        $badge_class = 'badge-' . $badge;
                        $status_class = strtolower($a['status']) === 'aktif' ? 'badge-aktif' : (strtolower($a['status']) === 'proses' ? 'badge-proses' : 'badge-kadaluarsa');
                    ?>
                    <tr data-id="<?= $a['id'] ?>" data-search="<?= strtolower(sanitize($a['nama_prodi'] . ' ' . ($a['nomor_sk'] ?? '') . ' ' . $a['badan_akreditasi'])) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="row-checkbox row-select" value="<?= $a['id'] ?>" onchange="updateBulkCount()">
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= sanitize($a['nama_prodi']) ?></strong>
                            <br>
                            <small style="color: var(--text-muted); font-family: monospace; font-size: 0.75rem;">
                                SK: <?= sanitize($a['nomor_sk'] ?: 'Belum ada') ?>
                            </small>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.88rem;">
                            🏛️ <?= sanitize($a['badan_akreditasi']) ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $badge_class ?> <?= $days_remaining !== null && $days_remaining <= 90 ? 'badge-urgent' : '' ?>">
                                <?= $a['peringkat'] === 'Unggul' ? '🥇' : ($a['peringkat'] === 'Baik Sekali' ? '🥈' : ($a['peringkat'] === 'Baik' ? '🥉' : '📋')) ?>
                                <?= sanitize($a['peringkat']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['tanggal_berlaku']): ?>
                                <div style="font-weight: 600; color: <?= $is_expired ? '#ef4444' : 'var(--text-primary)' ?>; font-size: 0.88rem;">
                                    <?= date('d M Y', strtotime($a['tanggal_berlaku'])) ?>
                                    <?php if ($is_expired): ?>
                                        <div style="font-size: 0.7rem; color: #ef4444; font-weight: 700; margin-top: 0.2rem;">
                                            ⚠️ Kadaluarsa
                                        </div>
                                    <?php elseif ($days_remaining !== null && $days_remaining <= 180): ?>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill <?= $days_remaining <= 90 ? 'danger' : 'warning' ?>" style="width: <?= max(0, min(100, ($days_remaining / 180) * 100)) ?>%"></div>
                                        </div>
                                        <div style="font-size: 0.72rem; color: <?= $days_remaining <= 90 ? '#ef4444' : '#f59e0b' ?>; font-weight: 700; margin-top: 0.2rem;">
                                            ⏰ <?= $days_remaining ?> hari lagi
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $status_class ?>"><?= $a['status'] ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $a['id'] ?>)" title="Detail">👁️</button>
                                <?php if (!empty($a['sertifikat_file'])): ?>
                                    <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="btn-icon download" title="Unduh Sertifikat">📥</a>
                                <?php endif; ?>
                                <a href="akreditasi-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus data akreditasi ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
        <div class="modal-header" id="detailHeader">
            <h3 id="detailTitle">🏆 Detail Akreditasi</h3>
            <p id="detailSubtitle">-</p>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('dokumen', this)">📄 Dokumen</button>
                <button class="detail-tab" onclick="switchDetailTab('aksi', this)">⚡ Aksi</button>
            </div>
            
            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>
            
            <div class="detail-tab-content" id="tab-dokumen">
                <div id="detailDocs"></div>
            </div>
            
            <div class="detail-tab-content" id="tab-aksi">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== EXPIRING MODAL ===== -->
<?php if (!empty($expiring_soon)): ?>
<div class="modal-overlay" id="expiringModal" onclick="if(event.target===this)closeExpiringModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeExpiringModal()">✕</button>
        <div class="modal-header" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <h3>⚠️ Akreditasi Akan Kadaluarsa</h3>
            <p>Daftar program studi yang perlu segera diperpanjang</p>
        </div>
        <div class="modal-body">
            <?php foreach ($expiring_soon as $exp): 
                $days = floor((strtotime($exp['tanggal_berlaku']) - time()) / 86400);
                $is_urgent = $days <= 90;
            ?>
            <div class="expiring-list-item <?= $is_urgent ? 'urgent' : 'warning' ?>">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; gap: 0.5rem; flex-wrap: wrap;">
                    <strong style="font-size: 1rem; flex: 1; min-width: 0;"><?= sanitize($exp['nama_prodi']) ?></strong>
                    <span class="badge-extreme <?= $is_urgent ? 'badge-kadaluarsa badge-urgent' : 'badge-proses' ?>" style="font-size: 0.7rem; flex-shrink: 0;">
                        <?= $days ?> hari lagi
                    </span>
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; gap: 1rem; flex-wrap: wrap;">
                    <span>📅 <strong><?= date('d M Y', strtotime($exp['tanggal_berlaku'])) ?></strong></span>
                    <span>🏆 <strong><?= sanitize($exp['peringkat']) ?></strong></span>
                    <span>🏛️ <?= sanitize($exp['badan_akreditasi']) ?></span>
                </div>
                <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                    <a href="akreditasi-form.php?id=<?= $exp['id'] ?>" class="btn-action primary" style="padding: 0.4rem 0.85rem; font-size: 0.78rem;">✏️ Perpanjang</a>
                    <button onclick="showDetail(<?= $exp['id'] ?>); closeExpiringModal();" class="btn-action secondary" style="padding: 0.4rem 0.85rem; font-size: 0.78rem;">👁️ Detail</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ===== DATA untuk detail modal =====
const akreditasiData = <?= json_encode($akreditasi_list) ?>;

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

// ===== APEXCHARTS: PERINGKAT =====
const peringkatData = <?= json_encode($chart_data) ?>;
new ApexCharts(document.querySelector("#peringkatChart"), {
    series: Object.values(peringkatData),
    labels: Object.keys(peringkatData),
    chart: { type: 'donut', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#fbbf24', '#3b82f6', '#10b981', '#6b7280'],
    plotOptions: { 
        pie: { 
            donut: { 
                size: '70%', 
                labels: { 
                    show: true, 
                    total: { 
                        show: true, 
                        label: 'Total',
                        formatter: () => <?= $stat_total ?>
                    } 
                } 
            } 
        } 
    },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '12px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

// ===== APEXCHARTS: STATUS =====
new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_kadaluarsa ?>, <?= $stat_proses ?>],
    labels: ['Aktif', 'Kadaluarsa', 'Proses'],
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444', '#f59e0b'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '12px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const peringkatFilter = document.getElementById('peringkatFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});
peringkatFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const peringkat = peringkatFilter.value;
    const status = statusFilter.value;
    
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (peringkat) url.searchParams.set('peringkat', peringkat); else url.searchParams.delete('peringkat');
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
        
        // Reset other headers
        table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));
        
        rows.sort((a, b) => {
            const aText = a.children[index]?.textContent.trim() || '';
            const bText = b.children[index]?.textContent.trim() || '';
            const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAsc ? bNum - aNum : aNum - bNum;
            }
            return isAsc ? bText.localeCompare(bText) : aText.localeCompare(bText);
        });
        
        rows.forEach(row => tbody.appendChild(row));
        th.classList.add(isAsc ? 'desc' : 'asc');
        
        showToast('Diurutkan', `Tabel diurutkan ${isAsc ? 'menurun' : 'menaikk'}`, 'info');
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
    
    // Add hidden inputs
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
    document.getElementById('selectAll').checked = false;
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = akreditasiData.find(a => a.id == id);
    if (!data) return;
    
    const badge = data.peringkat.toLowerCase().replace(' ', '-');
    const colors = { 'unggul': '#f59e0b', 'baik-sekali': '#3b82f6', 'baik': '#10b981', 'c': '#6b7280' };
    const color = colors[badge] || '#0a6847';
    
    document.getElementById('detailHeader').style.background = `linear-gradient(135deg, ${color}, ${color}dd)`;
    document.getElementById('detailTitle').textContent = `🏆 ${data.nama_prodi}`;
    document.getElementById('detailSubtitle').textContent = `${data.peringkat} • ${data.badan_akreditasi}`;
    
    const days = data.tanggal_berlaku ? Math.floor((new Date(data.tanggal_berlaku) - new Date()) / 86400000) : null;
    
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">📋 Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.nama_prodi)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏛️ Badan Akreditasi</div>
            <div class="detail-item-value">${escapeHtml(data.badan_akreditasi)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏆 Peringkat</div>
            <div class="detail-item-value">${escapeHtml(data.peringkat)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value">${escapeHtml(data.status)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📄 Nomor SK</div>
            <div class="detail-item-value" style="font-family: monospace; font-size: 0.82rem;">${escapeHtml(data.nomor_sk || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tanggal Terbit</div>
            <div class="detail-item-value">${data.tanggal_terbit ? formatDate(data.tanggal_terbit) : '-'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">⏰ Berlaku Sampai</div>
            <div class="detail-item-value">${data.tanggal_berlaku ? formatDate(data.tanggal_berlaku) : '-'}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📆 Sisa Waktu</div>
            <div class="detail-item-value" style="color: ${days !== null ? (days < 0 ? '#ef4444' : (days < 90 ? '#ef4444' : (days < 180 ? '#f59e0b' : '#10b981'))) : 'inherit'}">
                ${days === null ? '-' : (days < 0 ? `Kadaluarsa ${Math.abs(days)} hari lalu` : `${days} hari lagi`)}
            </div>
        </div>
    `;
    
    // Documents tab
    const docsHtml = data.sertifikat_file 
        ? `<div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <span style="font-size: 2rem;">📄</span>
                    <div style="flex: 1;">
                        <div style="font-weight: 700;">Sertifikat Akreditasi</div>
                        <div style="font-size: 0.82rem; color: var(--text-muted);">${escapeHtml(data.sertifikat_file)}</div>
                    </div>
                </div>
                <a href="${window.location.origin}/uploads/akreditasi/${data.sertifikat_file}" target="_blank" class="btn-action primary" style="width: 100%;">📥 Download Sertifikat</a>
            </div>`
        : `<div class="empty-state-extreme" style="padding: 2rem;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.4;">📭</div>
                <p style="margin: 0;">Belum ada dokumen sertifikat</p>
                <a href="akreditasi-form.php?id=${data.id}" class="btn-action primary" style="margin-top: 1rem;">📤 Upload Sertifikat</a>
            </div>`;
    document.getElementById('detailDocs').innerHTML = docsHtml;
    
    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="akreditasi-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Data Akreditasi</a>
        ${days !== null && days < 180 && days >= 0 ? `<a href="akreditasi-form.php?id=${data.id}" class="btn-action secondary" style="justify-content: flex-start; background: #fef3c7; color: #92400e; border-color: #fcd34d;">⏰ Perpanjang Akreditasi</a>` : ''}
        <a href="akreditasi-form.php?duplicate=${data.id}" class="btn-action secondary" style="justify-content: flex-start;" onclick="return confirm('Duplikasi data ini?')">📋 Duplikasi Data</a>
        <button onclick="shareAkreditasi(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan</button>
    `;
    
    document.getElementById('detailModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
    document.body.style.overflow = '';
}

function switchDetailTab(tab, btn) {
    document.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function shareAkreditasi(id) {
    const data = akreditasiData.find(a => a.id == id);
    if (!data) return;
    const text = `🏆 ${data.nama_prodi}\n🏛️ ${data.badan_akreditasi}\n⭐ ${data.peringkat}\n📅 Berlaku s/d: ${data.tanggal_berlaku || '-'}`;
    if (navigator.share) {
        navigator.share({ title: data.nama_prodi, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Detail akreditasi disalin ke clipboard', 'success');
    }
}

function formatDate(str) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const d = new Date(str);
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL FUNCTIONS =====
function showExpiringModal() {
    document.getElementById('expiringModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeExpiringModal() {
    document.getElementById('expiringModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeDetailModal();
        closeExpiringModal();
    }
    // "/" untuk focus search
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        searchInput?.focus();
    }
    // Ctrl+N: tambah baru
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'akreditasi-form.php';
    }
    // Ctrl+E: export CSV
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        window.location = window.location.pathname + '?export=csv';
    }
    // Ctrl+A: select all (di table view)
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && document.activeElement.tagName !== 'INPUT') {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            e.preventDefault();
            selectAll.checked = !selectAll.checked;
            toggleSelectAll(selectAll);
        }
    }
});

// ===== TOAST HELPER (uses AdminPanel.Toast if available) =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ===== LIVE COUNTDOWN untuk urgent items =====
(function() {
    const urgentBadges = document.querySelectorAll('.badge-urgent');
    if (urgentBadges.length === 0) return;
    
    setInterval(() => {
        urgentBadges.forEach(badge => {
            badge.style.transform = badge.style.transform === 'scale(1.05)' ? 'scale(1)' : 'scale(1.05)';
        });
    }, 1500);
})();

console.log('%c🏆 Kelola Akreditasi FKIP UNIMOF', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Timeline), Bulk Actions, Detail Modal, Charts, Live Countdown', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>