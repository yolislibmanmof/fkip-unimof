<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT foto FROM prestasi WHERE id = ?");
        $stmt->execute([$id]);
        $foto = $stmt->fetchColumn();
        if ($foto && function_exists('delete_upload')) delete_upload($foto, 'prestasi');
        $pdo->prepare("DELETE FROM prestasi WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Prestasi berhasil dihapus.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT foto FROM prestasi WHERE id = ?");
            $stmt->execute([$del_id]);
            $foto = $stmt->fetchColumn();
            if ($foto && function_exists('delete_upload')) delete_upload($foto, 'prestasi');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM prestasi WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' prestasi berhasil dihapus.');
    }
    elseif ($action === 'bulk_recategorize' && !empty($ids) && !empty($_POST['new_tingkat'])) {
        $new_tingkat = trim($_POST['new_tingkat']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_tingkat], $ids);
        $pdo->prepare("UPDATE prestasi SET tingkat = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', '✅ Tingkat ' . count($ids) . ' prestasi diubah ke "' . htmlspecialchars($new_tingkat) . '".');
    }

    header('Location: prestasi.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_prestasi = $pdo->query("SELECT p.*, ps.nama as prodi_nama FROM prestasi p LEFT JOIN program_studi ps ON p.program_studi_id = ps.id ORDER BY p.tahun DESC, p.created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_prestasi)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="prestasi-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Judul', 'Mahasiswa', 'Lomba', 'Juara', 'Tingkat', 'Tahun', 'Program Studi', 'Deskripsi']);
        foreach ($all_prestasi as $r) {
            fputcsv($out, [
                $r['id'], $r['judul'], $r['mahasiswa'], $r['lomba'], $r['juara'],
                $r['tingkat'], $r['tahun'], $r['prodi_nama'] ?? '-', strip_tags($r['deskripsi'] ?? '')
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_prestasi)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="prestasi-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_prestasi),
            'data' => $all_prestasi
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$tingkat_filter = $_GET['tingkat'] ?? '';
$juara_filter = $_GET['juara'] ?? '';
$tahun_filter = $_GET['tahun'] ?? '';
$prodi_filter = $_GET['prodi'] ?? '';
$sort_by = $_GET['sort'] ?? 'tahun';
$sort_dir = $_GET['dir'] ?? 'desc';
$view_mode = $_GET['view'] ?? 'grid';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (p.judul LIKE ? OR p.mahasiswa LIKE ? OR p.lomba LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($tingkat_filter !== '') { $where .= ' AND p.tingkat = ?'; $params[] = $tingkat_filter; }
if ($juara_filter !== '') { $where .= ' AND p.juara = ?'; $params[] = $juara_filter; }
if ($tahun_filter !== '') { $where .= ' AND p.tahun = ?'; $params[] = $tahun_filter; }
if ($prodi_filter !== '') { $where .= ' AND p.program_studi_id = ?'; $params[] = $prodi_filter; }

// Sort validation
$valid_sorts = ['tahun', 'judul', 'mahasiswa', 'tingkat', 'juara', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'tahun';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$stmt = $pdo->prepare("SELECT p.*, ps.nama as prodi_nama FROM prestasi p LEFT JOIN program_studi ps ON p.program_studi_id = ps.id $where ORDER BY p.$sort_by $sort_dir");
$stmt->execute($params);
$prestasi_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($prestasi_list);
$stat_intl = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE tingkat='Internasional'")->fetchColumn();
$stat_nasional = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE tingkat='Nasional'")->fetchColumn();
$stat_provinsi = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE tingkat='Provinsi'")->fetchColumn();
$stat_kabupaten = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE tingkat='Kabupaten'")->fetchColumn();
$stat_juara1 = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE juara='Juara 1'")->fetchColumn();
$stat_juara2 = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE juara='Juara 2'")->fetchColumn();
$stat_juara3 = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE juara='Juara 3'")->fetchColumn();

// Achievement health score (percentage of top achievements)
$top_achievements = $stat_juara1 + $stat_juara2 + $stat_juara3;
$health_score = $stat_total > 0 ? round(($top_achievements / $stat_total) * 100) : 0;
$health_score = min(100, $health_score);

// Recent additions
$stat_new_month = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$stat_this_year = (int)$pdo->query("SELECT COUNT(*) FROM prestasi WHERE tahun = YEAR(CURDATE())")->fetchColumn();

// Data untuk chart
$tingkat_stats = [];
$tingkat_rows = $pdo->query("SELECT tingkat, COUNT(*) as total FROM prestasi GROUP BY tingkat ORDER BY total DESC")->fetchAll();
foreach ($tingkat_rows as $r) $tingkat_stats[$r['tingkat']] = (int)$r['total'];

$juara_stats = [];
$juara_rows = $pdo->query("SELECT juara, COUNT(*) as total FROM prestasi WHERE juara IN ('Juara 1','Juara 2','Juara 3') GROUP BY juara ORDER BY total DESC")->fetchAll();
foreach ($juara_rows as $r) $juara_stats[$r['juara']] = (int)$r['total'];

// Top prestasi by prodi
$prodi_stats = $pdo->query("
    SELECT ps.nama, COUNT(p.id) as total 
    FROM prestasi p 
    JOIN program_studi ps ON p.program_studi_id = ps.id 
    GROUP BY ps.id, ps.nama 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

// Top recent achievements
$top_recent = $pdo->query("SELECT p.*, ps.nama as prodi_nama FROM prestasi p LEFT JOIN program_studi ps ON p.program_studi_id = ps.id ORDER BY p.tahun DESC, p.created_at DESC LIMIT 5")->fetchAll();

// Get distinct years for filter
$tahun_list = $pdo->query("SELECT DISTINCT tahun FROM prestasi ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);

$csrf = generate_csrf_token();
$active_menu = 'prestasi';
$page_heading = 'Kelola Prestasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Prestasi', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Achievement Theme - Gold/Amber) ===== */
.prestasi-hero {
    background: linear-gradient(135deg, #b45309 0%, #d97706 50%, #f59e0b 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(180, 83, 9, 0.3);
}
.prestasi-hero::before {
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
.prestasi-hero::after {
    content: '🏆';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.prestasi-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.prestasi-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.prestasi-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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

/* Health Score Ring */
.health-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.health-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.health-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.health-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.health-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.health-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.health-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

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
    background: linear-gradient(90deg, var(--stat-color, #f59e0b), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #f59e0b);
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
    background: var(--stat-color, #f59e0b);
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
    color: var(--stat-color, #f59e0b);
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

/* Top Prestasi Widget */
.top-prestasi-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-prestasi-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.top-prestasi-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    margin-bottom: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
}
.top-prestasi-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: #f59e0b;
}
.top-prestasi-item:last-child { margin-bottom: 0; }
.top-prestasi-rank {
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
    color: var(--text-primary);
}
.top-prestasi-item:nth-child(1) .top-prestasi-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-prestasi-item:nth-child(2) .top-prestasi-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-prestasi-item:nth-child(3) .top-prestasi-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-prestasi-trophy {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    background: var(--bg-tertiary);
}
.top-prestasi-info { flex: 1; min-width: 0; }
.top-prestasi-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-prestasi-meta {
    font-size: 0.7rem;
    color: var(--text-muted);
}
.top-prestasi-badge {
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
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
    background: linear-gradient(135deg, #b45309, #d97706);
    color: white;
    border-color: #b45309;
    box-shadow: 0 4px 12px rgba(180,83,9,0.3);
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
    border-color: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245,158,11,0.1);
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
.filter-select:focus { outline: none; border-color: #f59e0b; }

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
.view-btn.active { background: linear-gradient(135deg, #b45309, #d97706); color: white; }
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
    background: linear-gradient(135deg, #b45309, #d97706);
    color: white;
    box-shadow: 0 4px 12px rgba(180,83,9,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(180,83,9,0.4); }
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
    background: linear-gradient(135deg, #b45309, #d97706);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(180,83,9,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #b45309; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #b45309; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }
.bulk-category-select {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    font-size: 0.82rem;
    cursor: pointer;
    background: rgba(255,255,255,0.1);
    color: white;
    font-family: inherit;
}
.bulk-category-select option { background: #b45309; color: white; }

/* ===== TABLE VIEW ===== */
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
table.extreme th[data-sortable]:hover { color: #f59e0b; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #f59e0b; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(245,158,11,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #f59e0b; cursor: pointer; }

.prestasi-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.prestasi-thumb {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid #fcd34d;
}
.prestasi-thumb img { width: 100%; height: 100%; object-fit: cover; }
.prestasi-info { flex: 1; min-width: 0; }
.prestasi-title {
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.prestasi-desc-mini {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 250px;
}

/* Badges */
.badge-extreme {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-internasional { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-nasional { background: #dbeafe; color: #1e40af; }
.badge-provinsi { background: #dcfce7; color: #166534; }
.badge-kabupaten { background: #f3e8ff; color: #7e22ce; }
.badge-sekolah { background: #fce7f3; color: #be185d; }

.badge-juara-1 { background: linear-gradient(135deg, #fef3c7, #fbbf24); color: #92400e; border: 1px solid #f59e0b; }
.badge-juara-2 { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #475569; border: 1px solid #94a3b8; }
.badge-juara-3 { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #9a3412; border: 1px solid #fb923c; }
.badge-harapan { background: #f3f4f6; color: #4b5563; }

.year-cell {
    font-family: 'Georgia', serif;
    font-weight: 800;
    font-size: 1rem;
    color: var(--primary);
}
.year-cell small {
    display: block;
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 500;
    font-family: system-ui;
}

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
.btn-icon.view { background: #fef3c7; color: #b45309; }
.btn-icon.view:hover { background: #b45309; color: white; transform: translateY(-2px); }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== GRID VIEW ===== */
.prestasi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.prestasi-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    transition: all 0.4s;
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    cursor: pointer;
    position: relative;
}
.prestasi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-color, #f59e0b);
    z-index: 2;
}
.prestasi-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--card-color, #f59e0b);
}
.prestasi-card.selected {
    border-color: #f59e0b;
    background: rgba(245,158,11,0.02);
}

.prestasi-check {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 3;
}
.prestasi-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #f59e0b; }

.prestasi-image {
    height: 180px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    overflow: hidden;
}
.prestasi-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s;
}
.prestasi-card:hover .prestasi-image img { transform: scale(1.08); }
.prestasi-tingkat-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px);
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #b45309;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.prestasi-juara-badge {
    position: absolute;
    top: 1rem;
    left: 3.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}

.prestasi-content {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.prestasi-card-title {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.prestasi-card-desc {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.6;
    margin-bottom: 1rem;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.prestasi-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    gap: 0.5rem;
    flex-wrap: wrap;
}
.prestasi-card-year {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: #b45309;
}
.prestasi-card-prodi {
    font-size: 0.75rem;
    color: var(--text-muted);
    padding: 0.2rem 0.65rem;
    background: var(--bg-secondary);
    border-radius: 999px;
}

/* Grid Actions (overlay) */
.prestasi-actions-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, var(--bg-primary));
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}
.prestasi-card:hover .prestasi-actions-overlay {
    opacity: 1;
    pointer-events: auto;
}

/* ===== TIMELINE VIEW (Grouped by Tingkat) ===== */
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
    color: #b45309;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.timeline-count {
    background: linear-gradient(135deg, #b45309, #d97706);
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
    border-color: #f59e0b;
}
.timeline-item-trophy {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
}
.timeline-item-trophy img { width: 100%; height: 100%; object-fit: cover; }
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
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.timeline-item-meta span { display: flex; align-items: center; gap: 0.2rem; }

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
.select-all-wrapper input { width: 18px; height: 18px; cursor: pointer; accent-color: #f59e0b; }

/* ===== EMPTY STATE ===== */
.empty-state-extreme {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
    grid-column: 1/-1;
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
.modal-header-prestasi {
    padding: 0;
    position: relative;
    overflow: hidden;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    background: linear-gradient(135deg, #b45309, #d97706);
}
.modal-header-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}
.modal-header-placeholder {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    color: #b45309;
}
.modal-header-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1.5rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
}
.modal-header-badges {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}
.modal-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
    letter-spacing: -0.01em;
}
.modal-subtitle {
    font-size: 0.85rem;
    opacity: 0.9;
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
.detail-tab.active { color: #f59e0b; border-bottom-color: #f59e0b; }
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

/* Trophy visual */
.trophy-visual {
    padding: 1.5rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    text-align: center;
}
.trophy-visual .trophy-icon {
    font-size: 4rem;
    margin-bottom: 0.5rem;
    animation: bounce 2s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.trophy-visual h4 {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
    font-weight: 800;
    color: #b45309;
    margin-bottom: 0.25rem;
}
.trophy-visual p {
    font-size: 0.85rem;
    color: #92400e;
}

/* Description area */
.description-area {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid #f59e0b;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--text-secondary);
}

/* Prestasi stats grid */
.prestasi-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.prestasi-stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.prestasi-stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #b45309;
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.prestasi-stat-box-label {
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
    .detail-grid, .prestasi-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .prestasi-hero-content { flex-direction: column; text-align: center; }
    .health-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .prestasi-grid { grid-template-columns: 1fr; padding: 1rem; }
    .table-wrapper { overflow-x: auto; }
    table.extreme { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .stat-number-extreme { font-size: 1.85rem; }
    .bulk-bar { flex-direction: column; align-items: stretch; }
    .bulk-actions { flex-direction: column; }
    .bulk-btn, .bulk-category-select { width: 100%; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .prestasi-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="prestasi-hero" data-aos="fade-down">
    <div class="prestasi-hero-content">
        <div>
            <h2>🏆 Achievement Hall of Fame</h2>
            <p>Kelola prestasi mahasiswa dan dosen FKIP UNIMOF. Rayakan pencapaian akademik dan non-akademik yang membanggakan.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_total ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_juara1 ?></span>
                    <span class="hero-stat-label">🥇 Juara 1</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_intl ?></span>
                    <span class="hero-stat-label">🌍 Internasional</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_this_year ?></span>
                    <span class="hero-stat-label">Tahun Ini</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="Achievement Score (persentase prestasi juara 1-3)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $health_score ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $health_score ?></div>
                <div class="score-label">Score</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">🏆</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Prestasi</div>
        <div class="stat-trend neutral">🏆 Semua</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #fbbf24;">
        <div class="stat-header">
            <div class="stat-icon-box">🥇</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_juara1 ?>">0</div>
        <div class="stat-label-extreme">Juara 1</div>
        <div class="stat-trend up">🥇 Gold</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #94a3b8;">
        <div class="stat-header">
            <div class="stat-icon-box">🥈</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_juara2 ?>">0</div>
        <div class="stat-label-extreme">Juara 2</div>
        <div class="stat-trend neutral">🥈 Silver</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #fb923c;">
        <div class="stat-header">
            <div class="stat-icon-box">🥉</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_juara3 ?>">0</div>
        <div class="stat-label-extreme">Juara 3</div>
        <div class="stat-trend neutral">🥉 Bronze</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">🌍</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_intl ?>">0</div>
        <div class="stat-label-extreme">Internasional</div>
        <div class="stat-trend neutral">🌐 Global</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">🇮🇩</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_nasional ?>">0</div>
        <div class="stat-label-extreme">Nasional</div>
        <div class="stat-trend neutral">🇮🇩 National</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($tingkat_stats) || !empty($juara_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Tingkat Prestasi</h3>
        <div id="tingkatChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top 5 Prestasi Terbaru</h3>
        <?php if (empty($top_recent)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🏆</div>
                <div>Belum ada data prestasi</div>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($top_recent as $i => $p):
                    $trophy_icon = stripos($p['juara'], '1') !== false ? '🥇' : (stripos($p['juara'], '2') !== false ? '🥈' : (stripos($p['juara'], '3') !== false ? '🥉' : '🏆'));
                ?>
                <div class="top-prestasi-item" onclick="showDetail(<?= $p['id'] ?? 0 ?>)">
                    <div class="top-prestasi-rank"><?= $i + 1 ?></div>
                    <div class="top-prestasi-trophy"><?= $trophy_icon ?></div>
                    <div class="top-prestasi-info">
                        <div class="top-prestasi-name"><?= sanitize($p['judul']) ?></div>
                        <div class="top-prestasi-meta"><?= sanitize($p['mahasiswa']) ?> • <?= $p['tahun'] ?></div>
                    </div>
                    <div class="top-prestasi-badge badge-extreme badge-<?= strtolower($p['tingkat']) ?>"><?= sanitize($p['tingkat']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>🥇 Distribusi Juara (Top 3)</h3>
        <div id="juaraChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏫 Prestasi per Prodi</h3>
        <div id="prodiChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Ringkas</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="top-prestasi-item">
                <div class="top-prestasi-trophy" style="background: #fef3c7; color: #b45309;">🥇</div>
                <div class="top-prestasi-info">
                    <div class="top-prestasi-name">Juara 1</div>
                    <div class="top-prestasi-meta">Pencapaian tertinggi</div>
                </div>
                <div class="top-prestasi-badge badge-extreme badge-juara-1"><?= $stat_juara1 ?></div>
            </div>
            <div class="top-prestasi-item">
                <div class="top-prestasi-trophy" style="background: #e2e8f0; color: #475569;">🥈</div>
                <div class="top-prestasi-info">
                    <div class="top-prestasi-name">Juara 2</div>
                    <div class="top-prestasi-meta">Runner-up</div>
                </div>
                <div class="top-prestasi-badge badge-extreme badge-juara-2"><?= $stat_juara2 ?></div>
            </div>
            <div class="top-prestasi-item">
                <div class="top-prestasi-trophy" style="background: #fed7aa; color: #9a3412;">🥉</div>
                <div class="top-prestasi-info">
                    <div class="top-prestasi-name">Juara 3</div>
                    <div class="top-prestasi-meta">Podium finish</div>
                </div>
                <div class="top-prestasi-badge badge-extreme badge-juara-3"><?= $stat_juara3 ?></div>
            </div>
            <div class="top-prestasi-item">
                <div class="top-prestasi-trophy" style="background: #dbeafe; color: #1e40af;">🌍</div>
                <div class="top-prestasi-info">
                    <div class="top-prestasi-name">Internasional</div>
                    <div class="top-prestasi-meta">Global achievement</div>
                </div>
                <div class="top-prestasi-badge badge-extreme badge-internasional"><?= $stat_intl ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="prestasi.php" class="pill <?= empty($tingkat_filter) && empty($juara_filter) && empty($tahun_filter) ? 'active' : '' ?>">
        🏆 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="prestasi.php?juara=Juara+1" class="pill <?= $juara_filter === 'Juara 1' ? 'active' : '' ?>">
        🥇 Juara 1 <span class="pill-count"><?= $stat_juara1 ?></span>
    </a>
    <a href="prestasi.php?juara=Juara+2" class="pill <?= $juara_filter === 'Juara 2' ? 'active' : '' ?>">
        🥈 Juara 2 <span class="pill-count"><?= $stat_juara2 ?></span>
    </a>
    <a href="prestasi.php?juara=Juara+3" class="pill <?= $juara_filter === 'Juara 3' ? 'active' : '' ?>">
        🥉 Juara 3 <span class="pill-count"><?= $stat_juara3 ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="prestasi.php?tingkat=Internasional" class="pill <?= $tingkat_filter === 'Internasional' ? 'active' : '' ?>">
        🌍 Intl
    </a>
    <a href="prestasi.php?tingkat=Nasional" class="pill <?= $tingkat_filter === 'Nasional' ? 'active' : '' ?>">
        🇮🇩 Nasional
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari judul, nama mahasiswa, atau lomba..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="tingkatFilter">
        <option value="">🏆 Semua Tingkat</option>
        <option value="Internasional" <?= $tingkat_filter === 'Internasional' ? 'selected' : '' ?>>🌍 Internasional</option>
        <option value="Nasional" <?= $tingkat_filter === 'Nasional' ? 'selected' : '' ?>>🇮🇩 Nasional</option>
        <option value="Provinsi" <?= $tingkat_filter === 'Provinsi' ? 'selected' : '' ?>>🏛️ Provinsi</option>
        <option value="Kabupaten" <?= $tingkat_filter === 'Kabupaten' ? 'selected' : '' ?>>🏘️ Kabupaten</option>
    </select>
    <select class="filter-select" id="juaraFilter">
        <option value="">🥇 Semua Juara</option>
        <option value="Juara 1" <?= $juara_filter === 'Juara 1' ? 'selected' : '' ?>>🥇 Juara 1</option>
        <option value="Juara 2" <?= $juara_filter === 'Juara 2' ? 'selected' : '' ?>>🥈 Juara 2</option>
        <option value="Juara 3" <?= $juara_filter === 'Juara 3' ? 'selected' : '' ?>>🥉 Juara 3</option>
        <option value="Harapan 1" <?= $juara_filter === 'Harapan 1' ? 'selected' : '' ?>>🎯 Harapan 1</option>
    </select>
    <select class="filter-select" id="tahunFilter">
        <option value="">📅 Semua Tahun</option>
        <?php foreach ($tahun_list as $t): ?>
        <option value="<?= $t ?>" <?= $tahun_filter == $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Tingkat</button>
    </div>

    <a href="prestasi-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Prestasi</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar prestasi</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($prestasi_list)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>🏆</span>
        <span><strong style="color: var(--text-primary);"><?= count($prestasi_list) ?></strong> prestasi ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortPrestasi(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="tahun-desc" <?= $sort_by === 'tahun' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Tahun Terbaru</option>
            <option value="tahun-asc" <?= $sort_by === 'tahun' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Tahun Terlama</option>
            <option value="judul-asc" <?= $sort_by === 'judul' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Judul A-Z</option>
            <option value="mahasiswa-asc" <?= $sort_by === 'mahasiswa' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Nama A-Z</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>prestasi dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <select name="new_tingkat" class="bulk-category-select" style="min-width: 150px;">
                <option value="">Ubah Tingkat...</option>
                <option value="Internasional">🌍 Internasional</option>
                <option value="Nasional">🇮🇩 Nasional</option>
                <option value="Provinsi">🏛️ Provinsi</option>
                <option value="Kabupaten">🏘️ Kabupaten</option>
            </select>
            <button type="submit" name="action" value="bulk_recategorize" class="bulk-btn primary">📂 Ubah Tingkat</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN prestasi terpilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($prestasi_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🏆</div>
        <h3><?= ($q || $tingkat_filter || $juara_filter || $tahun_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada data prestasi' ?></h3>
        <p><?= ($q || $tingkat_filter || $juara_filter || $tahun_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan prestasi.' : 'Mulai tambahkan prestasi mahasiswa dan dosen untuk membangun hall of fame.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $tingkat_filter || $juara_filter || $tahun_filter): ?>
                <a href="prestasi.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="prestasi-form.php" class="btn-action primary">➕ Tambah Prestasi Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🏆 Daftar Prestasi</h2>
                <p>Kelola semua prestasi mahasiswa dan dosen FKIP UNIMOF</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($prestasi_list) ?> prestasi
            </span>
        </div>

        <?php if ($view_mode === 'grid'): ?>
            <!-- GRID VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
                    <span>Pilih Semua (<?= count($prestasi_list) ?>)</span>
                </label>
            </div>
            <div class="prestasi-grid">
                <?php
                $card_colors = ['#f59e0b', '#fbbf24', '#d97706', '#b45309', '#fcd34d', '#fde68a'];
                $tingkat_icons = [
                    'Internasional' => '🌍',
                    'Nasional' => '🇮🇩',
                    'Provinsi' => '🏛️',
                    'Kabupaten' => '🏘️',
                    'Sekolah' => '🏫'
                ];
                foreach ($prestasi_list as $i => $p):
                    $tingkat_lower = strtolower($p['tingkat'] ?? 'lainnya');
                    $juara_lower = strtolower(str_replace(' ', '-', $p['juara'] ?? ''));
                    
                    // Determine trophy icon
                    $trophy_icon = '🏆';
                    if (stripos($juara_lower, 'juara-1') !== false || stripos($juara_lower, 'juara1') !== false) $trophy_icon = '🥇';
                    elseif (stripos($juara_lower, 'juara-2') !== false || stripos($juara_lower, 'juara2') !== false) $trophy_icon = '🥈';
                    elseif (stripos($juara_lower, 'juara-3') !== false || stripos($juara_lower, 'juara3') !== false) $trophy_icon = '🥉';
                    
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="prestasi-card" style="--card-color: <?= $color ?>;" data-id="<?= $p['id'] ?>" onclick="showDetail(<?= $p['id'] ?>)">
                    <div class="prestasi-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="prestasi-checkbox" value="<?= $p['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="prestasi-image">
                        <?php if (!empty($p['foto'])): ?>
                            <img src="<?= asset('uploads/prestasi/' . basename($p['foto'])) ?>" alt="<?= sanitize($p['judul']) ?>">
                        <?php else: ?>
                            <span><?= $trophy_icon ?></span>
                        <?php endif; ?>
                        <span class="prestasi-tingkat-badge badge-extreme badge-<?= $tingkat_lower ?>"><?= $tingkat_icons[$p['tingkat']] ?? '🏆' ?> <?= sanitize($p['tingkat']) ?></span>
                        <span class="prestasi-juara-badge badge-extreme badge-<?= $juara_lower ?>"><?= sanitize($p['juara']) ?></span>
                    </div>
                    <div class="prestasi-content">
                        <h3 class="prestasi-card-title"><?= sanitize($p['judul']) ?></h3>
                        <p class="prestasi-card-desc"><?= sanitize($p['mahasiswa']) ?> • <?= sanitize($p['lomba'] ?: '-') ?></p>
                        <div class="prestasi-card-meta">
                            <span class="prestasi-card-year"><?= $p['tahun'] ?></span>
                            <?php if (!empty($p['prodi_nama'])): ?>
                            <span class="prestasi-card-prodi">🏫 <?= sanitize($p['prodi_nama']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="prestasi-actions-overlay" onclick="event.stopPropagation()">
                        <a href="prestasi-form.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Hapus prestasi ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn-icon delete" title="Hapus" type="submit">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by tingkat) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($prestasi_list as $p) {
                    $tingkat = $p['tingkat'] ?? 'Lainnya';
                    $grouped[$tingkat][] = $p;
                }
                // Sort by tingkat importance
                $hierarchy = ['Internasional' => 1, 'Nasional' => 2, 'Provinsi' => 3, 'Kabupaten' => 4, 'Sekolah' => 5, 'Lainnya' => 6];
                uksort($grouped, function($a, $b) use ($hierarchy) {
                    return ($hierarchy[$a] ?? 99) - ($hierarchy[$b] ?? 99);
                });
                $tingkat_icons = [
                    'Internasional' => '🌍',
                    'Nasional' => '🇮🇩',
                    'Provinsi' => '🏛️',
                    'Kabupaten' => '🏘️',
                    'Sekolah' => '🏫',
                    'Lainnya' => '🏆'
                ];
                foreach ($grouped as $tingkat => $items):
                    $icon = $tingkat_icons[$tingkat] ?? '🏆';
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.75rem;"><?= $icon ?></span>
                        <span class="timeline-title"><?= $tingkat ?></span>
                        <span class="timeline-count"><?= count($items) ?> prestasi</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $p):
                            $juara_lower = strtolower(str_replace(' ', '-', $p['juara'] ?? ''));
                            $trophy_icon = '🏆';
                            if (stripos($juara_lower, 'juara-1') !== false || stripos($juara_lower, 'juara1') !== false) $trophy_icon = '🥇';
                            elseif (stripos($juara_lower, 'juara-2') !== false || stripos($juara_lower, 'juara2') !== false) $trophy_icon = '🥈';
                            elseif (stripos($juara_lower, 'juara-3') !== false || stripos($juara_lower, 'juara3') !== false) $trophy_icon = '🥉';
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $p['id'] ?>)">
                            <div class="timeline-item-trophy">
                                <?php if (!empty($p['foto'])): ?>
                                    <img src="<?= asset('uploads/prestasi/' . basename($p['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <?= $trophy_icon ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($p['judul']) ?></div>
                                <div class="timeline-item-meta">
                                    <span><?= sanitize($p['mahasiswa']) ?></span>
                                    <span>📅 <?= $p['tahun'] ?></span>
                                    <span><?= sanitize($p['juara']) ?></span>
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
                <table class="extreme" id="prestasiTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="judul">Prestasi <span class="sort-icon">↕</span></th>
                            <th data-sortable="mahasiswa">Mahasiswa <span class="sort-icon">↕</span></th>
                            <th data-sortable="lomba">Lomba <span class="sort-icon">↕</span></th>
                            <th data-sortable="juara">Juara <span class="sort-icon">↕</span></th>
                            <th data-sortable="tingkat">Tingkat <span class="sort-icon">↕</span></th>
                            <th data-sortable="tahun">Tahun <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $tingkat_icons = [
                        'Internasional' => '🌍',
                        'Nasional' => '🇮🇩',
                        'Provinsi' => '🏛️',
                        'Kabupaten' => '🏘️',
                        'Sekolah' => '🏫'
                    ];
                    foreach ($prestasi_list as $p):
                        $tingkat_lower = strtolower($p['tingkat'] ?? 'lainnya');
                        $juara_lower = strtolower(str_replace(' ', '-', $p['juara'] ?? ''));
                        
                        $trophy_icon = '🏆';
                        if (stripos($juara_lower, 'juara-1') !== false || stripos($juara_lower, 'juara1') !== false) $trophy_icon = '🥇';
                        elseif (stripos($juara_lower, 'juara-2') !== false || stripos($juara_lower, 'juara2') !== false) $trophy_icon = '🥈';
                        elseif (stripos($juara_lower, 'juara-3') !== false || stripos($juara_lower, 'juara3') !== false) $trophy_icon = '🥉';
                        
                        $juara_class = 'badge-harapan';
                        if (stripos($juara_lower, 'juara-1') !== false || stripos($juara_lower, 'juara1') !== false) $juara_class = 'badge-juara-1';
                        elseif (stripos($juara_lower, 'juara-2') !== false || stripos($juara_lower, 'juara2') !== false) $juara_class = 'badge-juara-2';
                        elseif (stripos($juara_lower, 'juara-3') !== false || stripos($juara_lower, 'juara3') !== false) $juara_class = 'badge-juara-3';
                    ?>
                    <tr data-id="<?= $p['id'] ?>" data-search="<?= strtolower(sanitize(($p['judul'] ?? '') . ' ' . ($p['mahasiswa'] ?? '') . ' ' . ($p['lomba'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="prestasi-checkbox row-checkbox" value="<?= $p['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="prestasi-cell">
                                <div class="prestasi-thumb">
                                    <?php if (!empty($p['foto'])): ?>
                                        <img src="<?= asset('uploads/prestasi/' . basename($p['foto'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= $trophy_icon ?>
                                    <?php endif; ?>
                                </div>
                                <div class="prestasi-info">
                                    <div class="prestasi-title"><?= sanitize($p['judul']) ?></div>
                                    <div class="prestasi-desc-mini"><?= excerpt($p['deskripsi'] ?? 'Tidak ada deskripsi', 60) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="color: var(--text-secondary);"><?= sanitize($p['mahasiswa']) ?></td>
                        <td style="color: var(--text-secondary); font-size: 0.9rem;"><?= sanitize($p['lomba'] ?: '-') ?></td>
                        <td><span class="badge-extreme <?= $juara_class ?>"><?= $trophy_icon ?> <?= sanitize($p['juara'] ?: '-') ?></span></td>
                        <td><span class="badge-extreme badge-<?= $tingkat_lower ?>"><?= $tingkat_icons[$p['tingkat']] ?? '🏆' ?> <?= sanitize($p['tingkat']) ?></span></td>
                        <td>
                            <div class="year-cell">
                                <?= $p['tahun'] ?>
                                <?php if (!empty($p['prodi_nama'])): ?>
                                <small>🏫 <?= sanitize($p['prodi_nama']) ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $p['id'] ?>)" title="Detail">👁️</button>
                                <a href="prestasi-form.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus prestasi ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
        <div class="modal-header-prestasi">
            <?php if (!empty($p['foto'])): ?>
                <img class="modal-header-image" id="modalImage" src="" alt="">
            <?php else: ?>
                <div class="modal-header-placeholder" id="modalPlaceholder">🏆</div>
            <?php endif; ?>
            <div class="modal-header-overlay">
                <div class="modal-header-badges" id="modalBadges"></div>
                <h2 class="modal-title" id="modalTitle">-</h2>
                <div class="modal-subtitle" id="modalSubtitle">-</div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('stats', this)">🏆 Pencapaian</button>
                <button class="detail-tab" onclick="switchDetailTab('deskripsi', this)">📝 Deskripsi</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-stats">
                <div id="detailStats"></div>
            </div>

            <div class="detail-tab-content" id="tab-deskripsi">
                <div id="detailDescription"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const prestasiData = <?= json_encode($prestasi_list) ?>;

const tingkatConfig = {
    'Internasional': { icon: '🌍', color: '#b45309', bg: '#fef3c7' },
    'Nasional': { icon: '🇮🇩', color: '#1e40af', bg: '#dbeafe' },
    'Provinsi': { icon: '🏛️', color: '#166534', bg: '#dcfce7' },
    'Kabupaten': { icon: '🏘️', color: '#7e22ce', bg: '#f3e8ff' },
    'Sekolah': { icon: '🏫', color: '#be185d', bg: '#fce7f3' },
    'Lainnya': { icon: '🏆', color: '#4b5563', bg: '#f3f4f6' }
};

function getTrophyIcon(juara) {
    const j = (juara || '').toLowerCase();
    if (j.includes('juara 1') || j.includes('juara1')) return '🥇';
    if (j.includes('juara 2') || j.includes('juara2')) return '🥈';
    if (j.includes('juara 3') || j.includes('juara3')) return '🥉';
    return '🏆';
}

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
<?php if (!empty($tingkat_stats)): ?>
new ApexCharts(document.querySelector("#tingkatChart"), {
    series: <?= json_encode(array_values($tingkat_stats)) ?>,
    labels: <?= json_encode(array_keys($tingkat_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#b45309', '#1e40af', '#166534', '#7e22ce', '#be185d', '#4b5563'],
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
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

<?php if (!empty($juara_stats)): ?>
new ApexCharts(document.querySelector("#juaraChart"), {
    series: <?= json_encode(array_values($juara_stats)) ?>,
    labels: <?= json_encode(array_keys($juara_stats)) ?>,
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#fbbf24', '#94a3b8', '#fb923c'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

<?php if (!empty($prodi_stats)): ?>
new ApexCharts(document.querySelector("#prodiChart"), {
    series: [{ data: <?= json_encode(array_column($prodi_stats, 'total')) ?> }],
    chart: { type: 'bar', height: 260, toolbar: { show: false } },
    colors: ['#f59e0b'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_column($prodi_stats, 'nama')) ?>,
        labels: { style: { fontSize: '9px' }, rotate: -45 }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } }
}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const tingkatFilter = document.getElementById('tingkatFilter');
const juaraFilter = document.getElementById('juaraFilter');
const tahunFilter = document.getElementById('tahunFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
tingkatFilter?.addEventListener('change', applyFilters);
juaraFilter?.addEventListener('change', applyFilters);
tahunFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const tingkat = tingkatFilter.value;
    const juara = juaraFilter.value;
    const tahun = tahunFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (tingkat) url.searchParams.set('tingkat', tingkat); else url.searchParams.delete('tingkat');
    if (juara) url.searchParams.set('juara', juara); else url.searchParams.delete('juara');
    if (tahun) url.searchParams.set('tahun', tahun); else url.searchParams.delete('tahun');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortPrestasi(value) {
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
    const masterId = type === 'grid' ? 'selectAllGrid' : 'selectAll';
    const master = document.getElementById(masterId);
    document.querySelectorAll('.prestasi-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.prestasi-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.prestasi-checkbox');
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

    const allChecks = document.querySelectorAll('.prestasi-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.prestasi-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = prestasiData.find(p => p.id == id);
    if (!data) return;

    const config = tingkatConfig[data.tingkat] || tingkatConfig['Lainnya'];
    const tingkatClass = 'badge-' + (data.tingkat || 'lainnya').toLowerCase().replace(/ /g, '-');
    const juara_lower = (data.juara || '').toLowerCase().replace(/ /g, '-');
    const trophyIcon = getTrophyIcon(data.juara);
    
    let juaraClass = 'badge-harapan';
    if (juara_lower.includes('juara-1') || juara_lower.includes('juara1')) juaraClass = 'badge-juara-1';
    else if (juara_lower.includes('juara-2') || juara_lower.includes('juara2')) juaraClass = 'badge-juara-2';
    else if (juara_lower.includes('juara-3') || juara_lower.includes('juara3')) juaraClass = 'badge-juara-3';

    // Header image/placeholder
    const imgEl = document.getElementById('modalImage');
    const placeholderEl = document.getElementById('modalPlaceholder');
    if (data.foto) {
        imgEl.src = window.location.origin + '/uploads/prestasi/' + data.foto;
        imgEl.style.display = 'block';
        placeholderEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none';
        placeholderEl.style.display = 'flex';
        placeholderEl.textContent = trophyIcon;
    }

    // Badges
    document.getElementById('modalBadges').innerHTML = `
        <span class="badge-extreme ${tingkatClass}">${config.icon} ${escapeHtml(data.tingkat || 'Lainnya')}</span>
        <span class="badge-extreme ${juaraClass}">${trophyIcon} ${escapeHtml(data.juara || '-')}</span>
    `;

    document.getElementById('modalTitle').textContent = data.judul || '-';
    document.getElementById('modalSubtitle').textContent = `${escapeHtml(data.mahasiswa || '-')} • ${data.tahun || '-'}`;

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">🏆 Judul Prestasi</div>
            <div class="detail-item-value">${escapeHtml(data.judul || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">👤 Mahasiswa</div>
            <div class="detail-item-value">${escapeHtml(data.mahasiswa || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🎯 Lomba</div>
            <div class="detail-item-value">${escapeHtml(data.lomba || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏅 Juara</div>
            <div class="detail-item-value"><span class="badge-extreme ${juaraClass}">${trophyIcon} ${escapeHtml(data.juara || '-')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Tingkat</div>
            <div class="detail-item-value"><span class="badge-extreme ${tingkatClass}">${config.icon} ${escapeHtml(data.tingkat || '-')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tahun</div>
            <div class="detail-item-value">${data.tahun || '-'}</div>
        </div>
        ${data.prodi_nama ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">🏫 Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.prodi_nama)}</div>
        </div>
        ` : ''}
    `;

    // Stats tab
    const daysSinceCreated = data.created_at ? Math.floor((new Date() - new Date(data.created_at)) / (1000 * 60 * 60 * 24)) : 0;
    const yearsSinceAchievement = new Date().getFullYear() - (data.tahun || new Date().getFullYear());

    document.getElementById('detailStats').innerHTML = `
        <div class="trophy-visual">
            <div class="trophy-icon">${trophyIcon}</div>
            <h4>${escapeHtml(data.juara || 'Prestasi')}</h4>
            <p>${escapeHtml(data.tingkat || '-')} Level</p>
        </div>

        <div class="prestasi-stats-grid">
            <div class="prestasi-stat-box">
                <div class="prestasi-stat-box-value">${data.tahun}</div>
                <div class="prestasi-stat-box-label">Tahun</div>
            </div>
            <div class="prestasi-stat-box">
                <div class="prestasi-stat-box-value">${yearsSinceAchievement}</div>
                <div class="prestasi-stat-box-label">Tahun Lalu</div>
            </div>
            <div class="prestasi-stat-box">
                <div class="prestasi-stat-box-value">${daysSinceCreated}</div>
                <div class="prestasi-stat-box-label">Hari Dicatat</div>
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif;">💡 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${data.tingkat === 'Internasional' ? '🌍 Prestasi tingkat internasional, pencapaian global yang membanggakan.' : ''}
                ${data.tingkat === 'Nasional' ? '🇮🇩 Prestasi tingkat nasional, diakui di seluruh Indonesia.' : ''}
                ${juara_lower.includes('juara-1') || juara_lower.includes('juara1') ? '<br>🥇 Juara 1 - pencapaian tertinggi di kompetisi ini.' : ''}
                ${juara_lower.includes('juara-2') || juara_lower.includes('juara2') ? '<br>🥈 Juara 2 - runner-up yang sangat kompetitif.' : ''}
                ${juara_lower.includes('juara-3') || juara_lower.includes('juara3') ? '<br>🥉 Juara 3 - podium finish yang impressive.' : ''}
                ${yearsSinceAchievement === 0 ? '<br>🆕 Prestasi baru tahun ini!' : ''}
                ${yearsSinceAchievement > 5 ? '<br>📜 Prestasi legacy yang tetap membanggakan.' : ''}
            </div>
        </div>
    `;

    // Description tab
    const deskripsi = data.deskripsi || 'Tidak ada deskripsi untuk prestasi ini.';
    document.getElementById('detailDescription').innerHTML = `
        <div class="description-area">
            ${escapeHtml(deskripsi).replace(/\n/g, '<br>')}
        </div>
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="prestasi-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Prestasi</a>
        <button onclick="sharePrestasi(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Info</button>
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

function sharePrestasi(id) {
    const data = prestasiData.find(p => p.id == id);
    if (!data) return;
    const trophyIcon = getTrophyIcon(data.juara);
    const text = `🏆 ${data.judul}\n${trophyIcon} ${data.juara || '-'} - ${data.tingkat || '-'}\n👤 ${data.mahasiswa || '-'}\n📅 ${data.tahun}\n\nPrestasi FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info prestasi disalin ke clipboard', 'success');
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
        window.location.href = 'prestasi-form.php';
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
    document.querySelectorAll('.prestasi-title, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c🏆 Kelola Prestasi FKIP UNIMOF - Super Extreme', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Grid/Tabel/Tingkat), Detail Modal, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>