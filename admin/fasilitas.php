<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT gambar FROM fasilitas WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && function_exists('delete_upload')) delete_upload($img, 'fasilitas');
        $pdo->prepare("DELETE FROM fasilitas WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Fasilitas berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE fasilitas SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status fasilitas diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT gambar FROM fasilitas WHERE id = ?");
            $stmt->execute([$del_id]);
            $img = $stmt->fetchColumn();
            if ($img && function_exists('delete_upload')) delete_upload($img, 'fasilitas');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM fasilitas WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' fasilitas berhasil dihapus.');
    }
    elseif ($action === 'bulk_activate' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE fasilitas SET status = 'Aktif' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' fasilitas diaktifkan.');
    }
    elseif ($action === 'bulk_recategorize' && !empty($ids) && !empty($_POST['new_category'])) {
        $new_cat = trim($_POST['new_category']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_cat], $ids);
        $pdo->prepare("UPDATE fasilitas SET kategori = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', '✅ Kategori ' . count($ids) . ' fasilitas diubah ke "' . htmlspecialchars($new_cat) . '".');
    }

    header('Location: fasilitas.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_fasilitas = $pdo->query("SELECT * FROM fasilitas ORDER BY created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_fasilitas)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fasilitas-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Nama', 'Kategori', 'Kapasitas', 'Status', 'Deskripsi', 'Tanggal']);
        foreach ($all_fasilitas as $r) {
            fputcsv($out, [
                $r['id'], $r['nama'], $r['kategori'], $r['kapasitas'],
                $r['status'], strip_tags($r['deskripsi'] ?? ''), $r['created_at']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_fasilitas)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="fasilitas-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_fasilitas),
            'data' => $all_fasilitas
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$kategori_filter = $_GET['kategori'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$view_mode = $_GET['view'] ?? 'grid';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR deskripsi LIKE ? OR kategori LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kategori_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kategori_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

// Sort validation
$valid_sorts = ['created_at', 'nama', 'kapasitas', 'kategori', 'status'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$stmt = $pdo->prepare("SELECT * FROM fasilitas $where ORDER BY $sort_by $sort_dir");
$stmt->execute($params);
$fasilitas_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($fasilitas_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE status='Non-Aktif'")->fetchColumn();
$stat_lab = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Laboratorium' AND status='Aktif'")->fetchColumn();
$stat_kelas = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Ruang Kelas' AND status='Aktif'")->fetchColumn();
$stat_perpus = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Perpustakaan' AND status='Aktif'")->fetchColumn();
$stat_umum = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Fasilitas Umum' AND status='Aktif'")->fetchColumn();
$stat_kapasitas = (int)$pdo->query("SELECT COALESCE(SUM(kapasitas), 0) FROM fasilitas WHERE status='Aktif'")->fetchColumn();

// Facility health score (based on active facilities and capacity)
$health_score = $stat_total > 0 ? round(($stat_aktif / $stat_total) * 100) : 0;
$health_score = min(100, $health_score);

// Average capacity per facility
$avg_capacity = $stat_aktif > 0 ? round($stat_kapasitas / $stat_aktif) : 0;

// Recent additions
$stat_new_month = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Data untuk chart
$kategori_stats = [];
$kategori_rows = $pdo->query("SELECT kategori, COUNT(*) as total FROM fasilitas WHERE status='Aktif' GROUP BY kategori ORDER BY total DESC")->fetchAll();
foreach ($kategori_rows as $r) $kategori_stats[$r['kategori']] = (int)$r['total'];

// Status distribution
$status_stats = [
    'Aktif' => $stat_aktif,
    'Non-Aktif' => $stat_nonaktif
];

// Top facilities by capacity
$top_capacity = $pdo->query("SELECT nama, kapasitas, kategori FROM fasilitas WHERE status='Aktif' AND kapasitas > 0 ORDER BY kapasitas DESC LIMIT 5")->fetchAll();

$csrf = generate_csrf_token();
$active_menu = 'fasilitas';
$page_heading = 'Kelola Fasilitas & Lab';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Fasilitas', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Campus Theme - Emerald/Teal) ===== */
.fasilitas-hero {
    background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(5, 150, 105, 0.3);
}
.fasilitas-hero::before {
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
.fasilitas-hero::after {
    content: '🏛';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.fasilitas-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.fasilitas-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.fasilitas-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
    background: linear-gradient(90deg, var(--stat-color, #10b981), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #10b981);
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
    background: var(--stat-color, #10b981);
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
    color: var(--stat-color, #10b981);
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

/* Top Capacity Widget */
.top-capacity-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-capacity-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.top-cap-item {
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
.top-cap-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-cap-item:last-child { margin-bottom: 0; }
.top-cap-rank {
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
.top-cap-item:nth-child(1) .top-cap-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-cap-item:nth-child(2) .top-cap-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-cap-item:nth-child(3) .top-cap-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-cap-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    background: var(--bg-tertiary);
}
.top-cap-info { flex: 1; min-width: 0; }
.top-cap-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-cap-category {
    font-size: 0.7rem;
    color: var(--text-muted);
}
.top-cap-value {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.88rem;
    flex-shrink: 0;
    min-width: 50px;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.top-cap-value small { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }

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
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    border-color: #059669;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
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
.view-btn.active { background: linear-gradient(135deg, #059669, #10b981); color: white; }
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
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5,150,105,0.4); }
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
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(5,150,105,0.3);
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
.bulk-btn.success { background: #10b981; color: white; }
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
.bulk-category-select option { background: #059669; color: white; }

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
table.extreme th[data-sortable]:hover { color: #10b981; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #10b981; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(16,185,129,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #10b981; cursor: pointer; }

.facility-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.facility-thumb {
    width: 56px;
    height: 56px;
    border-radius: 10px;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
}
.facility-thumb img { width: 100%; height: 100%; object-fit: cover; }
.facility-info { flex: 1; min-width: 0; }
.facility-name {
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.facility-desc-mini {
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
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-non-aktif { background: #fee2e2; color: #991b1b; }
.badge-laboratorium { background: #dbeafe; color: #1e40af; }
.badge-ruang-kelas { background: #fef3c7; color: #92400e; }
.badge-perpustakaan { background: #f3e8ff; color: #7e22ce; }
.badge-fasilitas-umum { background: #fce7f3; color: #be185d; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; }

.capacity-cell {
    text-align: center;
}
.capacity-cell strong {
    display: block;
    font-size: 1.1rem;
    color: var(--primary);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.capacity-cell small {
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.date-cell {
    font-size: 0.85rem;
}
.date-cell small {
    display: block;
    color: var(--text-muted);
    font-size: 0.72rem;
    margin-top: 0.1rem;
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
.btn-icon.view { background: #e0e7ff; color: #4338ca; }
.btn-icon.view:hover { background: #4338ca; color: white; transform: translateY(-2px); }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== GRID VIEW ===== */
.facility-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.facility-card {
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
.facility-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--card-color, #10b981);
    z-index: 2;
}
.facility-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--card-color, #10b981);
}
.facility-card.selected {
    border-color: #10b981;
    background: rgba(16,185,129,0.02);
}

.facility-check {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 3;
}
.facility-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #10b981; }

.facility-image {
    height: 180px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    overflow: hidden;
}
.facility-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s;
}
.facility-card:hover .facility-image img { transform: scale(1.08); }
.facility-kategori-badge {
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
    color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.facility-status-badge {
    position: absolute;
    top: 1rem;
    left: 3.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}
.facility-status-badge.aktif { background: #dcfce7; color: #166534; }
.facility-status-badge.non-aktif { background: #fee2e2; color: #991b1b; }

.facility-content {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.facility-title {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.facility-desc {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.6;
    margin-bottom: 1rem;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.facility-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.facility-kapasitas {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.facility-date {
    font-size: 0.72rem;
    color: var(--text-muted);
}

/* Grid Actions (overlay) */
.facility-actions-overlay {
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
.facility-card:hover .facility-actions-overlay {
    opacity: 1;
    pointer-events: auto;
}

/* ===== TIMELINE VIEW (Grouped by Kategori) ===== */
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
    color: #059669;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.timeline-count {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.timeline-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
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
    border-color: #10b981;
}
.timeline-item-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    overflow: hidden;
}
.timeline-item-icon img { width: 100%; height: 100%; object-fit: cover; }
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
.select-all-wrapper input { width: 18px; height: 18px; cursor: pointer; accent-color: #10b981; }

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
.modal-header-fasilitas {
    padding: 0;
    position: relative;
    overflow: hidden;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
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
    background: linear-gradient(135deg, #059669, #10b981);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    color: white;
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

/* Capacity visual */
.capacity-visual {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
}
.capacity-visual h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-family: 'Georgia', serif;
}
.capacity-bar-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.capacity-bar {
    flex: 1;
    height: 12px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    overflow: hidden;
}
.capacity-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 999px;
    transition: width 1s ease;
}
.capacity-value {
    font-weight: 800;
    color: var(--primary);
    font-size: 1rem;
    min-width: 60px;
    text-align: right;
}

/* Description area */
.description-area {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid #10b981;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--text-secondary);
}

/* Facility stats grid */
.facility-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.facility-stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.facility-stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.facility-stat-box-label {
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
    .detail-grid, .facility-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .fasilitas-hero-content { flex-direction: column; text-align: center; }
    .health-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .facility-grid { grid-template-columns: 1fr; padding: 1rem; }
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
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .fasilitas-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="fasilitas-hero" data-aos="fade-down">
    <div class="fasilitas-hero-content">
        <div>
            <h2>🏢 Campus Facilities</h2>
            <p>Kelola fasilitas kampus, laboratorium, dan ruang kelas FKIP UNIMOF. Pantau kondisi, kapasitas, dan ketersediaan fasilitas secara real-time.</p>
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
                    <span class="hero-stat-num"><?= $stat_kapasitas ?></span>
                    <span class="hero-stat-label">Kapasitas</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num">+<?= $stat_new_month ?></span>
                    <span class="hero-stat-label">Bulan Ini</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="Facility Health Score (persentase fasilitas aktif)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $health_score ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $health_score ?></div>
                <div class="score-label">Health</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">🏢</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Fasilitas</div>
        <div class="stat-trend neutral">📚 Semua</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Fasilitas Aktif</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_aktif / $stat_total * 100) : 0 ?>%</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ef4444;">
        <div class="stat-header">
            <div class="stat-icon-box">🧪</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_lab ?>">0</div>
        <div class="stat-label-extreme">Laboratorium</div>
        <div class="stat-trend neutral">🔬 Research</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">🏫</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_kelas ?>">0</div>
        <div class="stat-label-extreme">Ruang Kelas</div>
        <div class="stat-trend neutral">📖 Teaching</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #06b6d4;">
        <div class="stat-header">
            <div class="stat-icon-box">📚</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_perpus ?>">0</div>
        <div class="stat-label-extreme">Perpustakaan</div>
        <div class="stat-trend neutral">📖 Library</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">👥</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_kapasitas ?>">0</div>
        <div class="stat-label-extreme">Total Kapasitas</div>
        <div class="stat-trend up">📊 Avg <?= $avg_capacity ?>/fasilitas</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($kategori_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Kategori Fasilitas</h3>
        <div id="kategoriChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top 5 by Kapasitas</h3>
        <?php if (empty($top_capacity)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🏢</div>
                <div>Belum ada data kapasitas</div>
            </div>
        <?php else: ?>
            <div>
                <?php
                $cat_icons = [
                    'Laboratorium' => '🧪',
                    'Ruang Kelas' => '🏫',
                    'Perpustakaan' => '📚',
                    'Fasilitas Umum' => '🏢',
                    'Lainnya' => '📌'
                ];
                $max_cap = max(array_column($top_capacity, 'kapasitas'));
                foreach ($top_capacity as $i => $f):
                    $icon = $cat_icons[$f['kategori']] ?? '🏢';
                ?>
                <div class="top-cap-item" onclick="showDetail(<?= $f['id'] ?? 0 ?>)">
                    <div class="top-cap-rank"><?= $i + 1 ?></div>
                    <div class="top-cap-icon"><?= $icon ?></div>
                    <div class="top-cap-info">
                        <div class="top-cap-name"><?= sanitize($f['nama']) ?></div>
                        <div class="top-cap-category"><?= sanitize($f['kategori']) ?></div>
                    </div>
                    <div class="top-cap-value">
                        <?= number_format($f['kapasitas']) ?>
                        <small>👥</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>📈 Ringkasan per Kategori</h3>
        <div id="ringkasanChart"></div>
    </div>
    <div class="chart-card">
        <h3>🎯 Status Fasilitas</h3>
        <div id="statusChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Ringkas</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="top-cap-item">
                <div class="top-cap-icon" style="background: #dcfce7; color: #16a34a;">🟢</div>
                <div class="top-cap-info">
                    <div class="top-cap-name">Aktif</div>
                    <div class="top-cap-category">Siap digunakan</div>
                </div>
                <div class="top-cap-value"><?= $stat_aktif ?></div>
            </div>
            <div class="top-cap-item">
                <div class="top-cap-icon" style="background: #fee2e2; color: #dc2626;">🔴</div>
                <div class="top-cap-info">
                    <div class="top-cap-name">Non-Aktif</div>
                    <div class="top-cap-category">Perlu perhatian</div>
                </div>
                <div class="top-cap-value"><?= $stat_nonaktif ?></div>
            </div>
            <div class="top-cap-item">
                <div class="top-cap-icon" style="background: #dbeafe; color: #2563eb;">📊</div>
                <div class="top-cap-info">
                    <div class="top-cap-name">Avg Kapasitas</div>
                    <div class="top-cap-category">Per fasilitas aktif</div>
                </div>
                <div class="top-cap-value"><?= $avg_capacity ?></div>
            </div>
            <div class="top-cap-item">
                <div class="top-cap-icon" style="background: #fef3c7; color: #d97706;">🆕</div>
                <div class="top-cap-info">
                    <div class="top-cap-name">Baru Bulan Ini</div>
                    <div class="top-cap-category">30 hari terakhir</div>
                </div>
                <div class="top-cap-value">+<?= $stat_new_month ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="fasilitas.php" class="pill <?= empty($kategori_filter) && empty($status_filter) ? 'active' : '' ?>">
        🏢 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="fasilitas.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="fasilitas.php?status=Non-Aktif" class="pill <?= $status_filter === 'Non-Aktif' ? 'active' : '' ?>">
        ❌ Non-Aktif <span class="pill-count"><?= $stat_nonaktif ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="fasilitas.php?kategori=Laboratorium" class="pill <?= $kategori_filter === 'Laboratorium' ? 'active' : '' ?>">
        🧪 Lab
    </a>
    <a href="fasilitas.php?kategori=Ruang+Kelas" class="pill <?= $kategori_filter === 'Ruang Kelas' ? 'active' : '' ?>">
        🏫 Kelas
    </a>
    <a href="fasilitas.php?kategori=Perpustakaan" class="pill <?= $kategori_filter === 'Perpustakaan' ? 'active' : '' ?>">
        📚 Perpus
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama fasilitas, deskripsi, atau kategori..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="kategoriFilter">
        <option value="">📂 Semua Kategori</option>
        <option value="Laboratorium" <?= $kategori_filter === 'Laboratorium' ? 'selected' : '' ?>>🧪 Laboratorium</option>
        <option value="Ruang Kelas" <?= $kategori_filter === 'Ruang Kelas' ? 'selected' : '' ?>>🏫 Ruang Kelas</option>
        <option value="Perpustakaan" <?= $kategori_filter === 'Perpustakaan' ? 'selected' : '' ?>>📚 Perpustakaan</option>
        <option value="Fasilitas Umum" <?= $kategori_filter === 'Fasilitas Umum' ? 'selected' : '' ?>>🏢 Fasilitas Umum</option>
        <option value="Lainnya" <?= $kategori_filter === 'Lainnya' ? 'selected' : '' ?>>📌 Lainnya</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Kategori</button>
    </div>

    <a href="fasilitas-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Fasilitas</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar fasilitas</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($fasilitas_list)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>🏢</span>
        <span><strong style="color: var(--text-primary);"><?= count($fasilitas_list) ?></strong> fasilitas ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortFacilities(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="created_at-desc" <?= $sort_by === 'created_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
            <option value="created_at-asc" <?= $sort_by === 'created_at' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Terlama</option>
            <option value="kapasitas-desc" <?= $sort_by === 'kapasitas' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Kapasitas Terbesar</option>
            <option value="nama-asc" <?= $sort_by === 'nama' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Nama A-Z</option>
            <option value="nama-desc" <?= $sort_by === 'nama' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Nama Z-A</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>fasilitas dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <button type="submit" name="action" value="bulk_activate" class="bulk-btn success" onclick="return confirm('Aktifkan semua fasilitas terpilih?')">✅ Activate</button>
            <input type="text" name="new_category" class="bulk-category-select" placeholder="Kategori baru..." style="min-width: 150px;">
            <button type="submit" name="action" value="bulk_recategorize" class="bulk-btn primary" onclick="return confirm('Ubah kategori fasilitas terpilih?')">📂 Recategorize</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN fasilitas terpilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($fasilitas_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🏢</div>
        <h3><?= ($q || $kategori_filter || $status_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada data fasilitas' ?></h3>
        <p><?= ($q || $kategori_filter || $status_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan fasilitas.' : 'Mulai tambahkan fasilitas dan laboratorium kampus untuk membangun direktori fasilitas yang lengkap.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $kategori_filter || $status_filter): ?>
                <a href="fasilitas.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="fasilitas-form.php" class="btn-action primary">➕ Tambah Fasilitas Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🏢 Daftar Fasilitas</h2>
                <p>Kelola semua fasilitas kampus, laboratorium, dan ruang kelas</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($fasilitas_list) ?> fasilitas
            </span>
        </div>

        <?php if ($view_mode === 'grid'): ?>
            <!-- GRID VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
                    <span>Pilih Semua (<?= count($fasilitas_list) ?>)</span>
                </label>
            </div>
            <div class="facility-grid">
                <?php
                $card_colors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
                $cat_icons = [
                    'Laboratorium' => '🧪',
                    'Ruang Kelas' => '🏫',
                    'Perpustakaan' => '📚',
                    'Fasilitas Umum' => '🏢',
                    'Lainnya' => '📌'
                ];
                foreach ($fasilitas_list as $i => $f):
                    $kategori_class = 'badge-' . strtolower(str_replace(' ', '-', $f['kategori'] ?? 'lainnya'));
                    $status_class = strtolower($f['status'] ?? 'aktif') === 'aktif' ? 'aktif' : 'non-aktif';
                    $icon = $cat_icons[$f['kategori']] ?? '🏢';
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="facility-card" style="--card-color: <?= $color ?>;" data-id="<?= $f['id'] ?>" onclick="showDetail(<?= $f['id'] ?>)">
                    <div class="facility-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="facility-checkbox" value="<?= $f['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="facility-image">
                        <?php if (!empty($f['gambar'])): ?>
                            <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                        <span class="facility-kategori-badge badge-<?= $kategori_class ?>"><?= $icon ?> <?= sanitize($f['kategori']) ?></span>
                        <span class="facility-status-badge <?= $status_class ?>"><?= $f['status'] ?></span>
                    </div>
                    <div class="facility-content">
                        <h3 class="facility-title"><?= sanitize($f['nama']) ?></h3>
                        <p class="facility-desc"><?= excerpt($f['deskripsi'] ?? 'Tidak ada deskripsi', 120) ?></p>
                        <div class="facility-meta">
                            <span class="facility-kapasitas">👥 <?= number_format($f['kapasitas'] ?? 0) ?> orang</span>
                            <span class="facility-date"><?= date('d M Y', strtotime($f['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="facility-actions-overlay" onclick="event.stopPropagation()">
                        <a href="fasilitas-form.php?id=<?= $f['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Ubah status fasilitas ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                        </form>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Hapus fasilitas ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn-icon delete" title="Hapus" type="submit">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by kategori) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($fasilitas_list as $f) {
                    $kategori = $f['kategori'] ?? 'Lainnya';
                    $grouped[$kategori][] = $f;
                }
                // Sort by category importance
                $hierarchy = ['Laboratorium' => 1, 'Ruang Kelas' => 2, 'Perpustakaan' => 3, 'Fasilitas Umum' => 4, 'Lainnya' => 5];
                uksort($grouped, function($a, $b) use ($hierarchy) {
                    return ($hierarchy[$a] ?? 99) - ($hierarchy[$b] ?? 99);
                });
                $cat_icons = [
                    'Laboratorium' => '🧪',
                    'Ruang Kelas' => '🏫',
                    'Perpustakaan' => '📚',
                    'Fasilitas Umum' => '🏢',
                    'Lainnya' => '📌'
                ];
                foreach ($grouped as $kategori => $items):
                    $icon = $cat_icons[$kategori] ?? '🏢';
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.75rem;"><?= $icon ?></span>
                        <span class="timeline-title"><?= $kategori ?></span>
                        <span class="timeline-count"><?= count($items) ?> fasilitas</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $f): ?>
                        <div class="timeline-item" onclick="showDetail(<?= $f['id'] ?>)">
                            <div class="timeline-item-icon" style="background: var(--bg-tertiary);">
                                <?php if (!empty($f['gambar'])): ?>
                                    <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="">
                                <?php else: ?>
                                    <?= $icon ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($f['nama']) ?></div>
                                <div class="timeline-item-meta">
                                    <span>👥 <?= number_format($f['kapasitas'] ?? 0) ?></span>
                                    <span><?= $f['status'] ?></span>
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
                <table class="extreme" id="fasilitasTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="nama">Fasilitas <span class="sort-icon">↕</span></th>
                            <th data-sortable="kategori">Kategori <span class="sort-icon">↕</span></th>
                            <th data-sortable="kapasitas">Kapasitas <span class="sort-icon">↕</span></th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th data-sortable="created_at">Tanggal <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $cat_icons = [
                        'Laboratorium' => '🧪',
                        'Ruang Kelas' => '🏫',
                        'Perpustakaan' => '📚',
                        'Fasilitas Umum' => '🏢',
                        'Lainnya' => '📌'
                    ];
                    foreach ($fasilitas_list as $f):
                        $kategori_class = 'badge-' . strtolower(str_replace(' ', '-', $f['kategori'] ?? 'lainnya'));
                        $status_class = strtolower($f['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                        $icon = $cat_icons[$f['kategori']] ?? '🏢';
                    ?>
                    <tr data-id="<?= $f['id'] ?>" data-search="<?= strtolower(sanitize(($f['nama'] ?? '') . ' ' . ($f['deskripsi'] ?? '') . ' ' . ($f['kategori'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="facility-checkbox row-checkbox" value="<?= $f['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="facility-cell">
                                <div class="facility-thumb">
                                    <?php if (!empty($f['gambar'])): ?>
                                        <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= $icon ?>
                                    <?php endif; ?>
                                </div>
                                <div class="facility-info">
                                    <div class="facility-name"><?= sanitize($f['nama']) ?></div>
                                    <div class="facility-desc-mini"><?= excerpt($f['deskripsi'] ?? 'Tidak ada deskripsi', 80) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-extreme badge-<?= $kategori_class ?>"><?= $icon ?> <?= sanitize($f['kategori']) ?></span></td>
                        <td>
                            <div class="capacity-cell">
                                <strong><?= number_format($f['kapasitas'] ?? 0) ?></strong>
                                <small>orang</small>
                            </div>
                        </td>
                        <td><span class="badge-extreme <?= $status_class ?>"><?= $f['status'] ?></span></td>
                        <td>
                            <div class="date-cell">
                                <?= date('d M Y', strtotime($f['created_at'])) ?>
                                <small><?= date('H:i', strtotime($f['created_at'])) ?> WIB</small>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $f['id'] ?>)" title="Detail">👁️</button>
                                <a href="fasilitas-form.php?id=<?= $f['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status fasilitas ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus fasilitas ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
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
        <div class="modal-header-fasilitas">
            <?php if (!empty($f['gambar'])): ?>
                <img class="modal-header-image" id="modalImage" src="" alt="">
            <?php else: ?>
                <div class="modal-header-placeholder" id="modalPlaceholder">🏢</div>
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
                <button class="detail-tab" onclick="switchDetailTab('stats', this)">📊 Statistik</button>
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
const fasilitasData = <?= json_encode($fasilitas_list) ?>;
const maxKapasitas = <?= $max_cap ?? 1 ?>;

const catConfig = {
    'Laboratorium': { icon: '🧪', color: '#1e40af', bg: '#dbeafe' },
    'Ruang Kelas': { icon: '🏫', color: '#92400e', bg: '#fef3c7' },
    'Perpustakaan': { icon: '📚', color: '#7e22ce', bg: '#f3e8ff' },
    'Fasilitas Umum': { icon: '🏢', color: '#be185d', bg: '#fce7f3' },
    'Lainnya': { icon: '📌', color: '#4b5563', bg: '#f3f4f6' }
};

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
<?php if (!empty($kategori_stats)): ?>
new ApexCharts(document.querySelector("#kategoriChart"), {
    series: <?= json_encode(array_values($kategori_stats)) ?>,
    labels: <?= json_encode(array_keys($kategori_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#1e40af', '#f59e0b', '#7e22ce', '#be185d', '#4b5563'],
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

new ApexCharts(document.querySelector("#ringkasanChart"), {
    series: [{ data: [<?= $stat_lab ?>, <?= $stat_kelas ?>, <?= $stat_perpus ?>, <?= $stat_umum ?>] }],
    chart: { type: 'bar', height: 260, toolbar: { show: false } },
    colors: ['#1e40af'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: ['Lab', 'Kelas', 'Perpus', 'Umum'],
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
const kategoriFilter = document.getElementById('kategoriFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
kategoriFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const kategori = kategoriFilter.value;
    const status = statusFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (kategori) url.searchParams.set('kategori', kategori); else url.searchParams.delete('kategori');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortFacilities(value) {
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
    document.querySelectorAll('.facility-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.facility-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.facility-checkbox');
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

    const allChecks = document.querySelectorAll('.facility-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.facility-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = fasilitasData.find(f => f.id == id);
    if (!data) return;

    const config = catConfig[data.kategori] || catConfig['Lainnya'];
    const statusClass = data.status === 'Aktif' ? 'badge-aktif' : 'badge-non-aktif';
    const kategoriClass = 'badge-' + (data.kategori || 'lainnya').toLowerCase().replace(/ /g, '-');

    // Header image/placeholder
    const imgEl = document.getElementById('modalImage');
    const placeholderEl = document.getElementById('modalPlaceholder');
    if (data.gambar) {
        imgEl.src = window.location.origin + '/uploads/fasilitas/' + data.gambar;
        imgEl.style.display = 'block';
        placeholderEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none';
        placeholderEl.style.display = 'flex';
        placeholderEl.textContent = config.icon;
    }

    // Badges
    document.getElementById('modalBadges').innerHTML = `
        <span class="badge-extreme badge-<?= strtolower(str_replace(' ', '-', $f['kategori'] ?? 'lainnya')) ?> ${kategoriClass}">${config.icon} ${escapeHtml(data.kategori || 'Lainnya')}</span>
        <span class="badge-extreme ${statusClass}">${escapeHtml(data.status || 'Aktif')}</span>
    `;

    document.getElementById('modalTitle').textContent = data.nama || '-';
    document.getElementById('modalSubtitle').textContent = `Ditambahkan ${formatDate(data.created_at)}`;

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">🏢 Nama Fasilitas</div>
            <div class="detail-item-value">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📂 Kategori</div>
            <div class="detail-item-value">${config.icon} ${escapeHtml(data.kategori || 'Lainnya')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">👥 Kapasitas</div>
            <div class="detail-item-value" style="color: var(--primary); font-weight: 800;">${(data.kapasitas || 0).toLocaleString('id-ID')} orang</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value"><span class="badge-extreme ${statusClass}">${escapeHtml(data.status || 'Aktif')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tanggal Ditambahkan</div>
            <div class="detail-item-value">${formatDate(data.created_at)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🕐 Waktu</div>
            <div class="detail-item-value">${data.created_at ? formatTime(data.created_at) : '-'}</div>
        </div>
        ${data.lokasi ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📍 Lokasi</div>
            <div class="detail-item-value">${escapeHtml(data.lokasi)}</div>
        </div>
        ` : ''}
    `;

    // Stats tab
    const capacityPercent = maxKapasitas > 0 ? Math.min(100, Math.round(((data.kapasitas || 0) / maxKapasitas) * 100)) : 0;
    const daysSinceCreated = data.created_at ? Math.floor((new Date() - new Date(data.created_at)) / (1000 * 60 * 60 * 24)) : 0;
    const monthsSinceCreated = Math.floor(daysSinceCreated / 30);

    document.getElementById('detailStats').innerHTML = `
        <div class="facility-stats-grid">
            <div class="facility-stat-box">
                <div class="facility-stat-box-value">${(data.kapasitas || 0).toLocaleString('id-ID')}</div>
                <div class="facility-stat-box-label">Kapasitas</div>
            </div>
            <div class="facility-stat-box">
                <div class="facility-stat-box-value">${daysSinceCreated}</div>
                <div class="facility-stat-box-label">Hari Aktif</div>
            </div>
            <div class="facility-stat-box">
                <div class="facility-stat-box-value">${monthsSinceCreated}</div>
                <div class="facility-stat-box-label">Bulan</div>
            </div>
        </div>

        <div class="capacity-visual">
            <h4>📊 Kapasitas Relatif</h4>
            <div class="capacity-bar-wrap">
                <div class="capacity-bar">
                    <div class="capacity-bar-fill" style="width: ${capacityPercent}%"></div>
                </div>
                <div class="capacity-value">${capacityPercent}%</div>
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.5rem;">
                ${capacityPercent >= 70 ? '🔥 Fasilitas berkapasitas besar' : (capacityPercent >= 30 ? '📊 Kapasitas menengah' : '📍 Fasilitas kecil')} dibanding fasilitas terbesar
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif;">💡 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${data.status === 'Aktif' ? '✅ Fasilitas dalam kondisi aktif dan siap digunakan.' : '⚠️ Fasilitas dalam status non-aktif, perlu pengecekan.'}
                ${daysSinceCreated < 30 ? '<br>🆕 Fasilitas baru ditambahkan bulan ini.' : ''}
                ${capacityPercent >= 70 ? '<br>📊 Fasilitas ini memiliki kapasitas besar, cocok untuk kegiatan massal.' : ''}
                ${capacityPercent < 30 ? '<br>📍 Fasilitas dengan kapasitas kecil, ideal untuk kelompok kecil.' : ''}
            </div>
        </div>
    `;

    // Description tab
    const deskripsi = data.deskripsi || 'Tidak ada deskripsi untuk fasilitas ini.';
    document.getElementById('detailDescription').innerHTML = `
        <div class="description-area">
            ${escapeHtml(deskripsi).replace(/\n/g, '<br>')}
        </div>
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="fasilitas-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Fasilitas</a>
        <button onclick="shareFasilitas(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Info</button>
        <button onclick="toggleStatusFromModal(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">
            🔄 Toggle Status (Currently: ${data.status})
        </button>
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

function shareFasilitas(id) {
    const data = fasilitasData.find(f => f.id == id);
    if (!data) return;
    const text = `🏢 ${data.nama}\n📂 ${data.kategori || 'Lainnya'}\n👥 Kapasitas: ${data.kapasitas || 0} orang\n📊 Status: ${data.status}\n\nFasilitas FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info fasilitas disalin ke clipboard', 'success');
    }
}

function toggleStatusFromModal(id) {
    showToast('Info', 'Silakan gunakan tombol toggle di card atau table', 'info');
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

function formatTime(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')} WIB`;
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
        window.location.href = 'fasilitas-form.php';
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
    document.querySelectorAll('.facility-name, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c🏢 Kelola Fasilitas FKIP UNIMOF - Super Extreme', 'color: #10b981; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Grid/Tabel/Kategori), Detail Modal, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>