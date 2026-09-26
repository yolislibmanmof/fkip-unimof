<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM jurnal WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Jurnal berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE jurnal SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status jurnal diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM jurnal WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' jurnal berhasil dihapus.');
    }
    elseif ($action === 'bulk_activate' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE jurnal SET status = 'Aktif' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' jurnal diaktifkan.');
    }
    elseif ($action === 'bulk_akreditasi' && !empty($ids) && !empty($_POST['new_akreditasi'])) {
        $new_akr = trim($_POST['new_akreditasi']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_akr], $ids);
        $pdo->prepare("UPDATE jurnal SET akreditasi = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', '✅ Akreditasi ' . count($ids) . ' jurnal diubah ke "' . htmlspecialchars($new_akr) . '".');
    }

    header('Location: jurnal.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_jurnal = $pdo->query("SELECT * FROM jurnal ORDER BY created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_jurnal)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="jurnal-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Nama', 'Penerbit', 'ISSN', 'Akreditasi', 'URL', 'Status', 'Tanggal']);
        foreach ($all_jurnal as $r) {
            fputcsv($out, [$r['id'], $r['nama'], $r['penerbit'], $r['issn'], $r['akreditasi'], $r['url'] ?? '-', $r['status'], $r['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_jurnal)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="jurnal-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_jurnal),
            'data' => $all_jurnal
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$akreditasi_filter = $_GET['akreditasi'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$view_mode = $_GET['view'] ?? 'table';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR penerbit LIKE ? OR issn LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($akreditasi_filter !== '') { $where .= ' AND akreditasi = ?'; $params[] = $akreditasi_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

// Sort validation
$valid_sorts = ['created_at', 'nama', 'penerbit', 'akreditasi', 'status'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$stmt = $pdo->prepare("SELECT * FROM jurnal $where ORDER BY $sort_by $sort_dir");
$stmt->execute($params);
$jurnal_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($jurnal_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE status='Non-Aktif'")->fetchColumn();
$stat_url = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE url IS NOT NULL AND url != '' AND status='Aktif'")->fetchColumn();

// Akreditasi breakdown
$stat_scopus = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE akreditasi LIKE '%Scopus%'")->fetchColumn();
$stat_sinta_1_2 = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE akreditasi IN ('Sinta 1', 'Sinta 2')")->fetchColumn();
$stat_sinta_3_4 = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE akreditasi IN ('Sinta 3', 'Sinta 4')")->fetchColumn();
$stat_sinta_5_6 = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE akreditasi IN ('Sinta 5', 'Sinta 6')")->fetchColumn();
$stat_terakreditasi = $stat_scopus + $stat_sinta_1_2 + $stat_sinta_3_4 + $stat_sinta_5_6;

// Quality Score (weighted by accreditation level)
$quality_score = $stat_total > 0 ? min(100, round(
    ($stat_scopus * 30 + $stat_sinta_1_2 * 25 + $stat_sinta_3_4 * 15 + $stat_sinta_5_6 * 8) / $stat_total
)) : 0;

// Recent additions
$stat_new_month = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Data untuk chart
$akreditasi_stats = [];
$akreditasi_rows = $pdo->query("SELECT akreditasi, COUNT(*) as total FROM jurnal WHERE status='Aktif' GROUP BY akreditasi ORDER BY total DESC")->fetchAll();
foreach ($akreditasi_rows as $r) {
    $akreditasi_stats[$r['akreditasi'] ?: 'Belum'] = (int)$r['total'];
}

// Top penerbit
$top_penerbit = $pdo->query("SELECT penerbit, COUNT(*) as total FROM jurnal WHERE penerbit IS NOT NULL AND penerbit != '' AND status='Aktif' GROUP BY penerbit ORDER BY total DESC LIMIT 5")->fetchAll();

// Akreditasi hierarchy for timeline view
$akreditasi_hierarchy = ['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6'];

$csrf = generate_csrf_token();
$active_menu = 'jurnal';
$page_heading = 'Kelola Jurnal Ilmiah';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Jurnal Ilmiah', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Academic Publishing Theme - Purple/Indigo) ===== */
.jurnal-hero {
    background: linear-gradient(135deg, #6d28d9 0%, #7c3aed 50%, #8b5cf6 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(109, 40, 217, 0.3);
}
.jurnal-hero::before {
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
.jurnal-hero::after {
    content: '📚';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.jurnal-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.jurnal-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.jurnal-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
.hero-stats {
    display: flex;
    gap: 1rem;
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
    min-width: 90px;
}
.hero-stat-num {
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    font-family: 'Georgia', serif;
}
.hero-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-top: 0.25rem;
}

/* Quality Score Ring */
.quality-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.quality-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.quality-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.quality-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.quality-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.quality-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.quality-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS ===== */
.stats-extreme {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    background: linear-gradient(90deg, var(--stat-color, #7c3aed), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #7c3aed);
}
.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}
.stat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--stat-color, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-number-extreme {
    font-family: 'Georgia', serif;
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--stat-color, #7c3aed);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-extreme {
    font-size: 0.75rem;
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
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    width: fit-content;
}
.stat-trend.up { background: rgba(16,185,129,0.1); color: #059669; }
.stat-trend.down { background: rgba(239,68,68,0.1); color: #dc2626; }
.stat-trend.neutral { background: var(--bg-tertiary); color: var(--text-muted); }

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
    font-family: 'Georgia', serif;
}

/* Top Penerbit Widget */
.top-penerbit-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-penerbit-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.top-penerbit-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    margin-bottom: 0.5rem;
    transition: all 0.2s;
}
.top-penerbit-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-penerbit-item:last-child { margin-bottom: 0; }
.top-penerbit-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.82rem;
    flex-shrink: 0;
}
.top-penerbit-item:nth-child(1) .top-penerbit-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-penerbit-item:nth-child(2) .top-penerbit-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-penerbit-item:nth-child(3) .top-penerbit-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-penerbit-info { flex: 1; min-width: 0; }
.top-penerbit-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-penerbit-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.25rem;
    overflow: hidden;
}
.top-penerbit-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6d28d9, #8b5cf6);
    border-radius: 999px;
    transition: width 1s ease;
}
.top-penerbit-count {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.88rem;
    flex-shrink: 0;
    min-width: 30px;
    text-align: right;
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
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    border-color: #6d28d9;
    box-shadow: 0 4px 12px rgba(109,40,217,0.3);
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
    border-color: #7c3aed;
    box-shadow: 0 0 0 4px rgba(124,58,237,0.1);
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
    color: var(--text-primary);
}
.filter-select:focus { outline: none; border-color: #7c3aed; }

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
.view-btn.active { background: linear-gradient(135deg, #6d28d9, #8b5cf6); color: white; }
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
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    box-shadow: 0 4px 12px rgba(109,40,217,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(109,40,217,0.4); }
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
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(109,40,217,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #6d28d9; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #6d28d9; }
.bulk-btn.success { background: #10b981; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }
.bulk-akreditasi-select {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    font-size: 0.82rem;
    cursor: pointer;
    background: rgba(255,255,255,0.1);
    color: white;
    font-family: inherit;
}
.bulk-akreditasi-select option { background: #6d28d9; color: white; }

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
.table-header h2 { font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; font-family: 'Georgia', serif; }
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
table.extreme th[data-sortable]:hover { color: #7c3aed; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #7c3aed; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(124,58,237,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #7c3aed; cursor: pointer; }

.journal-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.journal-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
    letter-spacing: -0.01em;
}
.journal-issn {
    font-family: monospace;
    font-size: 0.78rem;
    color: var(--text-muted);
}

/* Badges */
.badge-extreme {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-non-aktif { background: #fee2e2; color: #991b1b; }
.badge-scopus {
    background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
    color: #6d28d9;
    border: 1px solid #d8b4fe;
    font-weight: 800;
}
.badge-sinta-1 {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #fcd34d;
    font-weight: 800;
}
.badge-sinta-2 { background: #dcfce7; color: #166534; border: 1px solid #86efac; font-weight: 800; }
.badge-sinta-3 { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.badge-sinta-4 { background: #e0e7ff; color: #4338ca; border: 1px solid #a5b4fc; }
.badge-sinta-5 { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.badge-sinta-6 { background: #fed7aa; color: #c2410c; border: 1px solid #fdba74; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

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
.btn-icon.link { background: #dcfce7; color: #16a34a; }
.btn-icon.link:hover { background: #16a34a; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== CARD VIEW ===== */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.journal-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: all 0.3s;
    position: relative;
    cursor: pointer;
    overflow: hidden;
}
.journal-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-color, #7c3aed);
}
.journal-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-color, #7c3aed);
}
.journal-card.selected {
    border-color: #7c3aed;
    background: rgba(124,58,237,0.02);
}
.journal-card-check {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
}
.journal-card-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #7c3aed; }

.journal-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 0.5rem;
}
.journal-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.journal-card-badges { display: flex; flex-direction: column; gap: 0.35rem; align-items: flex-end; }

.journal-card-title {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    color: var(--text-primary);
    letter-spacing: -0.01em;
}
.journal-card-issn {
    font-family: monospace;
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    padding: 0.25rem 0.5rem;
    background: var(--bg-secondary);
    border-radius: 4px;
    display: inline-block;
}
.journal-card-penerbit {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.journal-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.journal-card-date {
    font-size: 0.72rem;
    color: var(--text-muted);
}

/* ===== TIMELINE VIEW (Grouped by Akreditasi) ===== */
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
.timeline-title {
    font-size: 1.35rem;
    font-weight: 900;
    color: #6d28d9;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.timeline-count {
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
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
.timeline-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: #7c3aed;
}
.timeline-item-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name {
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Georgia', serif;
}
.timeline-item-meta {
    font-size: 0.72rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Select all wrapper */
.select-all-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    margin: 1rem 1.5rem 0;
    width: fit-content;
}
.select-all-wrapper input { width: 18px; height: 18px; cursor: pointer; accent-color: #7c3aed; }

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
.empty-state-extreme h3 { font-size: 1.35rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
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
.modal-header-jurnal {
    padding: 2rem;
    background: linear-gradient(135deg, #6d28d9, #8b5cf6);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-jurnal::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
    border-radius: 50%;
}
.modal-header-row {
    display: flex;
    gap: 1.25rem;
    align-items: center;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.modal-journal-icon {
    width: 72px;
    height: 72px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    border: 2px solid rgba(255,255,255,0.3);
    flex-shrink: 0;
}
.modal-journal-info { flex: 1; min-width: 200px; }
.modal-journal-name { font-size: 1.35rem; font-weight: 800; margin-bottom: 0.25rem; line-height: 1.3; font-family: 'Georgia', serif; }
.modal-journal-issn { font-size: 0.85rem; opacity: 0.95; font-family: monospace; margin-bottom: 0.35rem; }
.modal-badges-row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}
.modal-badge {
    padding: 0.25rem 0.75rem;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
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
.detail-tab.active { color: #7c3aed; border-bottom-color: #7c3aed; }
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
.detail-item-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }

/* Impact meter */
.impact-meter {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
}
.impact-meter h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Georgia', serif;
}
.impact-bar {
    height: 12px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}
.impact-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6d28d9, #8b5cf6);
    border-radius: 999px;
    transition: width 1s ease;
}
.impact-label {
    font-size: 0.78rem;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
}

/* Journal stats grid */
.journal-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.journal-stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.journal-stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.journal-stat-box-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

/* Search highlight */
mark {
    background: #fef08a;
    padding: 0 0.15rem;
    border-radius: 2px;
    font-weight: 700;
}

@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .detail-grid, .journal-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .jurnal-hero-content { flex-direction: column; text-align: center; }
    .quality-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .cards-grid { grid-template-columns: 1fr; padding: 1rem; }
    .table-wrapper { overflow-x: auto; }
    table.extreme { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .stat-number-extreme { font-size: 1.85rem; }
    .bulk-bar { flex-direction: column; align-items: stretch; }
    .bulk-actions { flex-direction: column; }
    .bulk-btn, .bulk-akreditasi-select { width: 100%; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .jurnal-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="jurnal-hero" data-aos="fade-down">
    <div class="jurnal-hero-content">
        <div>
            <h2>📚 Academic Journals</h2>
            <p>Kelola jurnal ilmiah dan publikasi FKIP UNIMOF. Pantau akreditasi SINTA/Scopus, penerbit, dan kualitas publikasi secara real-time.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_total ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_aktif ?></span>
                    <span class="hero-stat-label">Aktif</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_terakreditasi ?></span>
                    <span class="hero-stat-label">Terakreditasi</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_scopus ?></span>
                    <span class="hero-stat-label">Scopus</span>
                </div>
            </div>
        </div>
        <div class="quality-ring" title="Academic Quality Score">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $quality_score ?>, 100"/>
            </svg>
            <div class="quality-value">
                <div class="score-num"><?= $quality_score ?></div>
                <div class="score-label">Quality</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">📚</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Jurnal</div>
        <div class="stat-trend neutral">📚 Semua</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Jurnal Aktif</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_aktif / $stat_total * 100) : 0 ?>%</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #6d28d9;">
        <div class="stat-header">
            <div class="stat-icon-box">🌍</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_scopus ?>">0</div>
        <div class="stat-label-extreme">Scopus</div>
        <div class="stat-trend up">🏆 International</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">🥇</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_sinta_1_2 ?>">0</div>
        <div class="stat-label-extreme">Sinta 1-2</div>
        <div class="stat-trend up">⭐ Premium</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #06b6d4;">
        <div class="stat-header">
            <div class="stat-icon-box">🥈</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_sinta_3_4 ?>">0</div>
        <div class="stat-label-extreme">Sinta 3-4</div>
        <div class="stat-trend neutral">📊 Standard</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">🔗</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_url ?>">0</div>
        <div class="stat-label-extreme">Online (URL)</div>
        <div class="stat-trend up">🌐 Web Ready</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($akreditasi_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Akreditasi</h3>
        <div id="akreditasiChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top 5 Penerbit</h3>
        <?php if (empty($top_penerbit)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🏢</div>
                <div>Belum ada data penerbit</div>
            </div>
        <?php else: ?>
            <div>
                <?php
                $max_pub = max(array_column($top_penerbit, 'total'));
                foreach ($top_penerbit as $i => $p):
                ?>
                <div class="top-penerbit-item">
                    <div class="top-penerbit-rank"><?= $i + 1 ?></div>
                    <div class="top-penerbit-info">
                        <div class="top-penerbit-name"><?= sanitize($p['penerbit']) ?></div>
                        <div class="top-penerbit-bar">
                            <div class="top-penerbit-bar-fill" style="width: <?= ($p['total'] / $max_pub) * 100 ?>%"></div>
                        </div>
                    </div>
                    <div class="top-penerbit-count"><?= $p['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>🏅 Hierarki SINTA</h3>
        <div id="sintaChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Status Jurnal</h3>
        <div id="statusChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Ringkas</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="top-penerbit-item">
                <div class="top-penerbit-rank" style="background: #f3e8ff; color: #6d28d9;">🌍</div>
                <div class="top-penerbit-info">
                    <div class="top-penerbit-name">Scopus Indexed</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">International</div>
                </div>
                <div class="top-penerbit-count"><?= $stat_scopus ?></div>
            </div>
            <div class="top-penerbit-item">
                <div class="top-penerbit-rank" style="background: #fef3c7; color: #92400e;">🥇</div>
                <div class="top-penerbit-info">
                    <div class="top-penerbit-name">Sinta 1-2</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Premium National</div>
                </div>
                <div class="top-penerbit-count"><?= $stat_sinta_1_2 ?></div>
            </div>
            <div class="top-penerbit-item">
                <div class="top-penerbit-rank" style="background: #dbeafe; color: #1e40af;">🥈</div>
                <div class="top-penerbit-info">
                    <div class="top-penerbit-name">Sinta 3-4</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Standard National</div>
                </div>
                <div class="top-penerbit-count"><?= $stat_sinta_3_4 ?></div>
            </div>
            <div class="top-penerbit-item">
                <div class="top-penerbit-rank" style="background: #fed7aa; color: #c2410c;">🥉</div>
                <div class="top-penerbit-info">
                    <div class="top-penerbit-name">Sinta 5-6</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Emerging</div>
                </div>
                <div class="top-penerbit-count"><?= $stat_sinta_5_6 ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="jurnal.php" class="pill <?= empty($akreditasi_filter) && empty($status_filter) ? 'active' : '' ?>">
        📚 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="jurnal.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="jurnal.php?status=Non-Aktif" class="pill <?= $status_filter === 'Non-Aktif' ? 'active' : '' ?>">
        ❌ Non-Aktif <span class="pill-count"><?= $stat_nonaktif ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="jurnal.php?akreditasi=Scopus" class="pill <?= $akreditasi_filter === 'Scopus' ? 'active' : '' ?>">
        🌍 Scopus
    </a>
    <a href="jurnal.php?akreditasi=Sinta+1" class="pill <?= $akreditasi_filter === 'Sinta 1' ? 'active' : '' ?>">
        🥇 Sinta 1
    </a>
    <a href="jurnal.php?akreditasi=Sinta+2" class="pill <?= $akreditasi_filter === 'Sinta 2' ? 'active' : '' ?>">
        🥈 Sinta 2
    </a>
    <a href="jurnal.php?akreditasi=Sinta+3" class="pill <?= $akreditasi_filter === 'Sinta 3' ? 'active' : '' ?>">
        🥉 Sinta 3
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama jurnal, penerbit, atau ISSN..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="akreditasiFilter">
        <option value="">🏅 Semua Akreditasi</option>
        <option value="Scopus" <?= $akreditasi_filter === 'Scopus' ? 'selected' : '' ?>>🌍 Scopus</option>
        <option value="Sinta 1" <?= $akreditasi_filter === 'Sinta 1' ? 'selected' : '' ?>>🥇 Sinta 1</option>
        <option value="Sinta 2" <?= $akreditasi_filter === 'Sinta 2' ? 'selected' : '' ?>>🥈 Sinta 2</option>
        <option value="Sinta 3" <?= $akreditasi_filter === 'Sinta 3' ? 'selected' : '' ?>>🥉 Sinta 3</option>
        <option value="Sinta 4" <?= $akreditasi_filter === 'Sinta 4' ? 'selected' : '' ?>>📜 Sinta 4</option>
        <option value="Sinta 5" <?= $akreditasi_filter === 'Sinta 5' ? 'selected' : '' ?>>📄 Sinta 5</option>
        <option value="Sinta 6" <?= $akreditasi_filter === 'Sinta 6' ? 'selected' : '' ?>>📃 Sinta 6</option>
        <option value="Belum Terakreditasi" <?= $akreditasi_filter === 'Belum Terakreditasi' ? 'selected' : '' ?>>⏳ Belum</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">🏅 Akreditasi</button>
    </div>

    <a href="jurnal-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Jurnal</span>
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
                    <div style="font-weight: 600;">Print Directory</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar jurnal</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($jurnal_list)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>📚</span>
        <span><strong style="color: var(--text-primary);"><?= count($jurnal_list) ?></strong> jurnal ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortJurnal(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="created_at-desc" <?= $sort_by === 'created_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
            <option value="created_at-asc" <?= $sort_by === 'created_at' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Terlama</option>
            <option value="nama-asc" <?= $sort_by === 'nama' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Nama A-Z</option>
            <option value="nama-desc" <?= $sort_by === 'nama' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Nama Z-A</option>
            <option value="akreditasi-asc" <?= $sort_by === 'akreditasi' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Akreditasi A-Z</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>jurnal dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <button type="submit" name="action" value="bulk_activate" class="bulk-btn success" onclick="return confirm('Aktifkan semua jurnal terpilih?')">✅ Activate</button>
            <select name="new_akreditasi" class="bulk-akreditasi-select">
                <option value="">Pilih akreditasi...</option>
                <option value="Scopus">🌍 Scopus</option>
                <option value="Sinta 1">🥇 Sinta 1</option>
                <option value="Sinta 2">🥈 Sinta 2</option>
                <option value="Sinta 3">🥉 Sinta 3</option>
                <option value="Sinta 4">📜 Sinta 4</option>
                <option value="Sinta 5">📄 Sinta 5</option>
                <option value="Sinta 6">📃 Sinta 6</option>
                <option value="Belum Terakreditasi">⏳ Belum</option>
            </select>
            <button type="submit" name="action" value="bulk_akreditasi" class="bulk-btn primary" onclick="return confirm('Ubah akreditasi jurnal terpilih?')">🏅 Update Akreditasi</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN jurnal terpilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($jurnal_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">📚</div>
        <h3><?= ($q || $akreditasi_filter || $status_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada data jurnal' ?></h3>
        <p><?= ($q || $akreditasi_filter || $status_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan jurnal.' : 'Mulai tambahkan jurnal ilmiah fakultas untuk membangun portofolio publikasi yang lengkap.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $akreditasi_filter || $status_filter): ?>
                <a href="jurnal.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="jurnal-form.php" class="btn-action primary">➕ Tambah Jurnal Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>📚 Daftar Jurnal Ilmiah</h2>
                <p>Kelola semua jurnal ilmiah dan publikasi fakultas</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($jurnal_list) ?> jurnal
            </span>
        </div>

        <?php if ($view_mode === 'card'): ?>
            <!-- CARD VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllCard" onchange="toggleSelectAll('card')">
                    <span>Pilih Semua (<?= count($jurnal_list) ?>)</span>
                </label>
            </div>
            <div class="cards-grid">
                <?php
                $card_colors = ['#6d28d9', '#7c3aed', '#8b5cf6', '#a78bfa', '#6366f1', '#4f46e5'];
                foreach ($jurnal_list as $i => $j):
                    $akr_lower = strtolower($j['akreditasi'] ?? '');
                    if (stripos($akr_lower, 'scopus') !== false) $badge_class = 'badge-scopus';
                    elseif ($akr_lower === 'sinta 1') $badge_class = 'badge-sinta-1';
                    elseif ($akr_lower === 'sinta 2') $badge_class = 'badge-sinta-2';
                    elseif ($akr_lower === 'sinta 3') $badge_class = 'badge-sinta-3';
                    elseif ($akr_lower === 'sinta 4') $badge_class = 'badge-sinta-4';
                    elseif ($akr_lower === 'sinta 5') $badge_class = 'badge-sinta-5';
                    elseif ($akr_lower === 'sinta 6') $badge_class = 'badge-sinta-6';
                    else $badge_class = 'badge-lainnya';

                    $status_class = strtolower($j['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="journal-card" style="--card-color: <?= $color ?>;" data-id="<?= $j['id'] ?>" onclick="showDetail(<?= $j['id'] ?>)">
                    <div class="journal-card-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="journal-checkbox" value="<?= $j['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="journal-card-header">
                        <div class="journal-card-icon">📚</div>
                        <div class="journal-card-badges">
                            <span class="badge-extreme <?= $badge_class ?>"><?= sanitize($j['akreditasi'] ?: 'Belum') ?></span>
                            <span class="badge-extreme <?= $status_class ?>" style="font-size: 0.68rem; padding: 0.2rem 0.6rem;"><?= $j['status'] ?></span>
                        </div>
                    </div>
                    <h3 class="journal-card-title"><?= sanitize($j['nama']) ?></h3>
                    <div class="journal-card-issn">ISSN: <?= sanitize($j['issn'] ?: '-') ?></div>
                    <div class="journal-card-penerbit">
                        <span>🏢</span>
                        <span><?= sanitize($j['penerbit'] ?: '-') ?></span>
                    </div>
                    <div class="journal-card-footer">
                        <span class="journal-card-date"><?= date('d M Y', strtotime($j['created_at'])) ?></span>
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <a href="jurnal-form.php?id=<?= $j['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <?php if (!empty($j['url'])): ?>
                                <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-icon link" title="Website">🔗</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by akreditasi) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($jurnal_list as $j) {
                    $akr = $j['akreditasi'] ?: 'Belum Terakreditasi';
                    $grouped[$akr][] = $j;
                }
                // Sort by hierarchy
                $hierarchy = array_flip(['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6', 'Belum Terakreditasi']);
                uksort($grouped, function($a, $b) use ($hierarchy) {
                    return ($hierarchy[$a] ?? 99) - ($hierarchy[$b] ?? 99);
                });
                $akr_icons = [
                    'Scopus' => '🌍',
                    'Sinta 1' => '🥇',
                    'Sinta 2' => '🥈',
                    'Sinta 3' => '🥉',
                    'Sinta 4' => '📜',
                    'Sinta 5' => '📄',
                    'Sinta 6' => '📃',
                    'Belum Terakreditasi' => '⏳'
                ];
                foreach ($grouped as $akr => $items):
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.75rem;"><?= $akr_icons[$akr] ?? '📚' ?></span>
                        <span class="timeline-title"><?= $akr ?></span>
                        <span class="timeline-count"><?= count($items) ?> jurnal</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $j): ?>
                        <div class="timeline-item" onclick="showDetail(<?= $j['id'] ?>)">
                            <div class="timeline-item-icon">📚</div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($j['nama']) ?></div>
                                <div class="timeline-item-meta">
                                    <?= sanitize($j['penerbit'] ?: '-') ?> • ISSN: <?= sanitize($j['issn'] ?: '-') ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- TABLE VIEW -->
            <div class="table-wrapper">
                <table class="extreme" id="jurnalTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="nama">Nama Jurnal & ISSN <span class="sort-icon">↕</span></th>
                            <th data-sortable="penerbit">Penerbit <span class="sort-icon">↕</span></th>
                            <th data-sortable="akreditasi">Akreditasi <span class="sort-icon">↕</span></th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($jurnal_list as $j):
                        $akr_lower = strtolower($j['akreditasi'] ?? '');
                        if (stripos($akr_lower, 'scopus') !== false) $badge_class = 'badge-scopus';
                        elseif ($akr_lower === 'sinta 1') $badge_class = 'badge-sinta-1';
                        elseif ($akr_lower === 'sinta 2') $badge_class = 'badge-sinta-2';
                        elseif ($akr_lower === 'sinta 3') $badge_class = 'badge-sinta-3';
                        elseif ($akr_lower === 'sinta 4') $badge_class = 'badge-sinta-4';
                        elseif ($akr_lower === 'sinta 5') $badge_class = 'badge-sinta-5';
                        elseif ($akr_lower === 'sinta 6') $badge_class = 'badge-sinta-6';
                        else $badge_class = 'badge-lainnya';

                        $status_class = strtolower($j['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                    ?>
                    <tr data-id="<?= $j['id'] ?>" data-search="<?= strtolower(sanitize(($j['nama'] ?? '') . ' ' . ($j['penerbit'] ?? '') . ' ' . ($j['issn'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="journal-checkbox row-checkbox" value="<?= $j['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="journal-cell">
                                <div class="journal-name"><?= sanitize($j['nama']) ?></div>
                                <div class="journal-issn">ISSN: <?= sanitize($j['issn'] ?: 'Belum diisi') ?></div>
                            </div>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.88rem;">
                            <?= sanitize($j['penerbit'] ?: '-') ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $badge_class ?>">
                                <?= sanitize($j['akreditasi'] ?: 'Belum Terakreditasi') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $status_class ?>"><?= $j['status'] ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $j['id'] ?>)" title="Detail">👁️</button>
                                <a href="jurnal-form.php?id=<?= $j['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <?php if (!empty($j['url'])): ?>
                                    <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-icon link" title="Website">🔗</a>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status jurnal ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus jurnal ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn-icon delete" title="Hapus" type="submit">🗑️</button>
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
        <div class="modal-header-jurnal">
            <div class="modal-header-row">
                <div class="modal-journal-icon">📚</div>
                <div class="modal-journal-info">
                    <div class="modal-journal-name" id="modalName">-</div>
                    <div class="modal-journal-issn" id="modalIssn">-</div>
                    <div class="modal-badges-row" id="modalBadges"></div>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('stats', this)">📊 Statistik</button>
                <button class="detail-tab" onclick="switchDetailTab('akreditasi', this)">🏅 Akreditasi</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-stats">
                <div id="detailStats"></div>
            </div>

            <div class="detail-tab-content" id="tab-akreditasi">
                <div id="detailAkreditasi"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const jurnalData = <?= json_encode($jurnal_list) ?>;

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
<?php if (!empty($akreditasi_stats)): ?>
new ApexCharts(document.querySelector("#akreditasiChart"), {
    series: <?= json_encode(array_values($akreditasi_stats)) ?>,
    labels: <?= json_encode(array_keys($akreditasi_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#6d28d9', '#f59e0b', '#16a34a', '#2563eb', '#4338ca', '#d97706', '#c2410c', '#6b7280'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Aktif',
                        formatter: () => <?= $stat_aktif ?>
                    }
                }
            }
        }
    },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

new ApexCharts(document.querySelector("#sintaChart"), {
    series: [<?= $stat_scopus ?>, <?= $stat_sinta_1_2 ?>, <?= $stat_sinta_3_4 ?>, <?= $stat_sinta_5_6 ?>],
    chart: { type: 'bar', height: 260, toolbar: { show: false } },
    colors: ['#6d28d9'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: ['Scopus', 'Sinta 1-2', 'Sinta 3-4', 'Sinta 5-6'],
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } }
}).render();

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_nonaktif ?>],
    labels: ['Aktif', 'Non-Aktif'],
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const akreditasiFilter = document.getElementById('akreditasiFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
akreditasiFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const akreditasi = akreditasiFilter.value;
    const status = statusFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (akreditasi) url.searchParams.set('akreditasi', akreditasi); else url.searchParams.delete('akreditasi');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortJurnal(value) {
    const [sortBy, sortDir] = value.split('-');
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    url.searchParams.set('dir', sortDir);
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
function toggleSelectAll(type) {
    const masterId = type === 'card' ? 'selectAllCard' : 'selectAll';
    const master = document.getElementById(masterId);
    document.querySelectorAll('.journal-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.journal-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.journal-checkbox');
        el.classList.toggle('selected', cb?.checked);
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

    const allChecks = document.querySelectorAll('.journal-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllCard'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.journal-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllCard'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = jurnalData.find(j => j.id == id);
    if (!data) return;

    const akrLower = (data.akreditasi || '').toLowerCase();
    let badgeClass = 'badge-lainnya';
    if (akrLower.includes('scopus')) badgeClass = 'badge-scopus';
    else if (akrLower === 'sinta 1') badgeClass = 'badge-sinta-1';
    else if (akrLower === 'sinta 2') badgeClass = 'badge-sinta-2';
    else if (akrLower === 'sinta 3') badgeClass = 'badge-sinta-3';
    else if (akrLower === 'sinta 4') badgeClass = 'badge-sinta-4';
    else if (akrLower === 'sinta 5') badgeClass = 'badge-sinta-5';
    else if (akrLower === 'sinta 6') badgeClass = 'badge-sinta-6';

    const statusClass = data.status === 'Aktif' ? 'badge-aktif' : 'badge-non-aktif';

    document.getElementById('modalName').textContent = data.nama || '-';
    document.getElementById('modalIssn').textContent = 'ISSN: ' + (data.issn || '-');
    document.getElementById('modalBadges').innerHTML = `
        <span class="modal-badge ${badgeClass}">${escapeHtml(data.akreditasi || 'Belum Terakreditasi')}</span>
        <span class="modal-badge ${statusClass}">${escapeHtml(data.status || 'Aktif')}</span>
    `;

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📚 Nama Jurnal</div>
            <div class="detail-item-value" style="font-family: 'Georgia', serif; font-size: 1.1rem;">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏢 Penerbit</div>
            <div class="detail-item-value">${escapeHtml(data.penerbit || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🔢 ISSN</div>
            <div class="detail-item-value" style="font-family: monospace;">${escapeHtml(data.issn || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏅 Akreditasi</div>
            <div class="detail-item-value"><span class="badge-extreme ${badgeClass}">${escapeHtml(data.akreditasi || 'Belum')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value"><span class="badge-extreme ${statusClass}">${escapeHtml(data.status || 'Aktif')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Ditambahkan</div>
            <div class="detail-item-value">${formatDate(data.created_at)}</div>
        </div>
        ${data.url ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">🔗 Website</div>
            <div class="detail-item-value">
                <a href="${escapeHtml(data.url)}" target="_blank" style="color: var(--primary); text-decoration: underline; word-break: break-all;">
                    ${escapeHtml(data.url)}
                </a>
            </div>
        </div>
        ` : ''}
    `;

    // Stats tab
    const daysSinceCreated = data.created_at ? Math.floor((new Date() - new Date(data.created_at)) / (1000 * 60 * 60 * 24)) : 0;
    const monthsSinceCreated = Math.floor(daysSinceCreated / 30);

    // Impact score based on accreditation
    let impactScore = 0;
    let impactLevel = 'Rendah';
    if (akrLower.includes('scopus')) { impactScore = 100; impactLevel = 'International'; }
    else if (akrLower === 'sinta 1') { impactScore = 90; impactLevel = 'Premium'; }
    else if (akrLower === 'sinta 2') { impactScore = 80; impactLevel = 'High'; }
    else if (akrLower === 'sinta 3') { impactScore = 65; impactLevel = 'Medium-High'; }
    else if (akrLower === 'sinta 4') { impactScore = 50; impactLevel = 'Medium'; }
    else if (akrLower === 'sinta 5') { impactScore = 35; impactLevel = 'Low-Medium'; }
    else if (akrLower === 'sinta 6') { impactScore = 20; impactLevel = 'Low'; }
    else { impactScore = 5; impactLevel = 'Not Accredited'; }

    document.getElementById('detailStats').innerHTML = `
        <div class="journal-stats-grid">
            <div class="journal-stat-box">
                <div class="journal-stat-box-value">${daysSinceCreated}</div>
                <div class="journal-stat-box-label">Hari Aktif</div>
            </div>
            <div class="journal-stat-box">
                <div class="journal-stat-box-value">${monthsSinceCreated}</div>
                <div class="journal-stat-box-label">Bulan</div>
            </div>
            <div class="journal-stat-box">
                <div class="journal-stat-box-value">${impactScore}</div>
                <div class="journal-stat-box-label">Impact Score</div>
            </div>
        </div>

        <div class="impact-meter">
            <h4>📊 Academic Impact</h4>
            <div class="impact-bar">
                <div class="impact-bar-fill" style="width: ${impactScore}%"></div>
            </div>
            <div class="impact-label">
                <span>${impactLevel}</span>
                <span>${impactScore}/100</span>
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif;">💡 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${impactScore >= 80 ? '🏆 Jurnal ini memiliki akreditasi tinggi, sangat bagus untuk reputasi akademik.' : ''}
                ${impactScore >= 50 && impactScore < 80 ? '📊 Jurnal dengan akreditasi yang baik. Pertahankan kualitas publikasi.' : ''}
                ${impactScore < 50 && impactScore > 10 ? '📈 Jurnal perlu peningkatan kualitas untuk naik level akreditasi.' : ''}
                ${impactScore <= 10 ? '⚠️ Jurnal belum terakreditasi. Disarankan untuk mengajukan akreditasi SINTA.' : ''}
                ${data.status === 'Aktif' ? '<br>✅ Jurnal dalam status aktif.' : '<br>⚠️ Jurnal non-aktif, perlu perhatian.'}
                ${data.url ? '<br>🌐 Jurnal memiliki website online.' : '<br>💡 Tambahkan URL website untuk akses online.'}
            </div>
        </div>
    `;

    // Akreditasi tab
    const akrHierarchy = ['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6'];
    const currentLevel = akrHierarchy.findIndex(a => akrLower.includes(a.toLowerCase())) + 1;
    const nextLevel = currentLevel < akrHierarchy.length ? akrHierarchy[currentLevel] : '-';

    document.getElementById('detailAkreditasi').innerHTML = `
        <div style="padding: 1.5rem; background: linear-gradient(135deg, #ede9fe, #ddd6fe); border: 1px solid #c4b5fd; border-radius: var(--radius-md); margin-bottom: 1rem;">
            <h4 style="font-size: 1rem; color: #6d28d9; margin-bottom: 0.75rem; font-family: 'Georgia', serif;">🏅 Status Akreditasi Saat Ini</h4>
            <div style="font-size: 1.5rem; font-weight: 800; color: #6d28d9; font-family: 'Georgia', serif; margin-bottom: 0.25rem;">
                ${escapeHtml(data.akreditasi || 'Belum Terakreditasi')}
            </div>
            <div style="font-size: 0.85rem; color: #6d28d9;">Level: ${impactLevel}</div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif;">📈 Hierarki Akreditasi</h4>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                ${akrHierarchy.map((akr, i) => {
                    const isCurrent = akrLower.includes(akr.toLowerCase());
                    const isHigher = currentLevel > 0 && i < currentLevel - 1;
                    const bgColor = isCurrent ? '#6d28d9' : (isHigher ? '#dcfce7' : 'var(--bg-primary)');
                    const textColor = isCurrent ? 'white' : (isHigher ? '#166534' : 'var(--text-secondary)');
                    const icon = isCurrent ? '✓' : (isHigher ? '✓' : '○');
                    return `
                        <div style="padding: 0.6rem 0.85rem; background: ${bgColor}; color: ${textColor}; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; font-weight: ${isCurrent ? '700' : '500'};">
                            <span style="width: 20px;">${icon}</span>
                            <span>${akr}</span>
                            ${isCurrent ? '<span style="margin-left: auto; font-size: 0.72rem; opacity: 0.9;">← Current</span>' : ''}
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="jurnal-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Jurnal</a>
        ${data.url ? `<a href="${escapeHtml(data.url)}" target="_blank" class="btn-action secondary" style="justify-content: flex-start;">🔗 Kunjungi Website</a>` : ''}
        <button onclick="shareJurnal(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">📤 Bagikan Info</button>
        <button onclick="copyISSN(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">📋 Copy ISSN</button>
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

function shareJurnal(id) {
    const data = jurnalData.find(j => j.id == id);
    if (!data) return;
    const text = `📚 ${data.nama}\n🏢 ${data.penerbit || '-'}\n🔢 ISSN: ${data.issn || '-'}\n🏅 ${data.akreditasi || 'Belum Terakreditasi'}\n📊 Status: ${data.status}\n\nJurnal FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info jurnal disalin ke clipboard', 'success');
    }
}

function copyISSN(id) {
    const data = jurnalData.find(j => j.id == id);
    if (!data || !data.issn) {
        showToast('Info', 'ISSN belum tersedia', 'warning');
        return;
    }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(data.issn);
        showToast('Disalin', 'ISSN ' + data.issn + ' disalin', 'success');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const d = new Date(dateStr);
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
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
        window.location.href = 'jurnal-form.php';
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
            toggleSelectAll('table');
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

// ===== HIGHLIGHT SEARCH =====
function highlightSearchTerms() {
    const q = <?= json_encode($q) ?>;
    if (!q) return;
    document.querySelectorAll('.journal-name, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c📚 Kelola Jurnal FKIP UNIMOF - Super Extreme', 'color: #6d28d9; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Akreditasi), Detail Modal dengan Impact Score, Bulk Actions, Charts, Export', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>