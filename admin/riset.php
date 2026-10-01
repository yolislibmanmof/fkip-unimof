<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM riset WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Riset berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE riset SET status = IF(status='Published','Draft','Published') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status riset diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM riset WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' riset berhasil dihapus.');
    }
    elseif ($action === 'bulk_publish' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE riset SET status = 'Published' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' riset dipublikasikan.');
    }
    elseif ($action === 'bulk_recategorize' && !empty($ids) && !empty($_POST['new_category'])) {
        $new_cat = trim($_POST['new_category']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_cat], $ids);
        $pdo->prepare("UPDATE riset SET kategori = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', '✅ Kategori ' . count($ids) . ' riset diubah ke "' . htmlspecialchars($new_cat) . '".');
    }

    header('Location: riset.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_riset = $pdo->query("SELECT r.*, d.nama as ketua_nama, ps.nama as prodi_nama FROM riset r LEFT JOIN dosen d ON r.ketua_id = d.id LEFT JOIN program_studi ps ON r.program_studi_id = ps.id ORDER BY r.tahun DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_riset)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="riset-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Judul', 'Ketua', 'Anggota', 'Jenis', 'Kategori', 'Tahun', 'Jurnal', 'DOI', 'Status', 'Program Studi']);
        foreach ($all_riset as $r) {
            fputcsv($out, [
                $r['id'], $r['judul'], $r['ketua_nama'] ?? '-', $r['anggota'] ?? '-',
                $r['jenis'], $r['kategori'], $r['tahun'], $r['jurnal'] ?? '-',
                $r['doi'] ?? '-', $r['status'], $r['prodi_nama'] ?? '-'
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_riset)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="riset-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_riset),
            'data' => $all_riset
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$kat_filter = $_GET['kategori'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tahun_filter = $_GET['tahun'] ?? '';
$sort_by = $_GET['sort'] ?? 'tahun';
$sort_dir = $_GET['dir'] ?? 'desc';
$view_mode = $_GET['view'] ?? 'grid';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (r.judul LIKE ? OR r.deskripsi LIKE ? OR r.abstrak LIKE ? OR d.nama LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat_filter !== '') { $where .= ' AND r.kategori = ?'; $params[] = $kat_filter; }
if ($jenis_filter !== '') { $where .= ' AND r.jenis = ?'; $params[] = $jenis_filter; }
if ($status_filter !== '') { $where .= ' AND r.status = ?'; $params[] = $status_filter; }
if ($tahun_filter !== '') { $where .= ' AND r.tahun = ?'; $params[] = $tahun_filter; }

// Sort validation
$valid_sorts = ['tahun', 'judul', 'created_at', 'status'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'tahun';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$stmt = $pdo->prepare("SELECT r.*, d.nama as ketua_nama, ps.nama as prodi_nama 
                        FROM riset r 
                        LEFT JOIN dosen d ON r.ketua_id = d.id 
                        LEFT JOIN program_studi ps ON r.program_studi_id = ps.id 
                        $where 
                        ORDER BY r.$sort_by $sort_dir");
$stmt->execute($params);
$riset_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($riset_list);
$stat_pub = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published'")->fetchColumn();
$stat_draft = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Draft'")->fetchColumn();
$stat_year = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE tahun = YEAR(CURDATE())")->fetchColumn();
$stat_hibah = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE jenis='Hibah'")->fetchColumn();
$stat_publikasi = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE jenis='Publikasi'")->fetchColumn();
$stat_pengabdian = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE jenis='Pengabdian'")->fetchColumn();

// Unique researchers count
$stat_researchers = (int)$pdo->query("SELECT COUNT(DISTINCT ketua_id) FROM riset")->fetchColumn();

// Total DOI
$stat_doi = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE doi IS NOT NULL AND doi != ''")->fetchColumn();

// Research impact score (published percentage)
$impact_score = $stat_total > 0 ? round(($stat_pub / $stat_total) * 100) : 0;
$impact_score = min(100, $impact_score);

// Recent additions
$stat_new_month = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Data untuk chart
$kat_stats = [];
$kat_rows = $pdo->query("SELECT kategori, COUNT(*) as total FROM riset WHERE status='Published' GROUP BY kategori ORDER BY total DESC")->fetchAll();
foreach ($kat_rows as $r) $kat_stats[$r['kategori']] = (int)$r['total'];

$jenis_stats = [];
$jenis_rows = $pdo->query("SELECT jenis, COUNT(*) as total FROM riset GROUP BY jenis ORDER BY total DESC")->fetchAll();
foreach ($jenis_rows as $r) $jenis_stats[$r['jenis']] = (int)$r['total'];

// Top researchers
$top_peneliti = $pdo->query("
    SELECT d.nama, COUNT(r.id) as total, MAX(r.tahun) as tahun_terakhir
    FROM riset r 
    JOIN dosen d ON r.ketua_id = d.id 
    GROUP BY d.id, d.nama 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll();

// Recent publications
$top_recent = $pdo->query("SELECT r.*, d.nama as ketua_nama FROM riset r LEFT JOIN dosen d ON r.ketua_id = d.id WHERE r.status='Published' ORDER BY r.tahun DESC, r.created_at DESC LIMIT 5")->fetchAll();

// Year distribution
$tahun_stats = [];
$tahun_rows = $pdo->query("SELECT tahun, COUNT(*) as total FROM riset GROUP BY tahun ORDER BY tahun DESC LIMIT 6")->fetchAll();
foreach ($tahun_rows as $r) $tahun_stats[$r['tahun']] = (int)$r['total'];
$tahun_stats = array_reverse($tahun_stats, true);

// Get distinct years for filter
$tahun_list = $pdo->query("SELECT DISTINCT tahun FROM riset ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);

$csrf = generate_csrf_token();
$active_menu = 'riset';
$page_heading = 'Kelola Riset & Publikasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Riset', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Research Theme - Cyan/Teal) ===== */
.riset-hero {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #22d3ee 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(8, 145, 178, 0.3);
}
.riset-hero::before {
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
.riset-hero::after {
    content: '🔬';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.riset-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.riset-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.riset-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
    background: linear-gradient(90deg, var(--stat-color, #06b6d4), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #06b6d4);
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
    background: var(--stat-color, #06b6d4);
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
    color: var(--stat-color, #06b6d4);
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

/* Top Researchers Widget */
.top-peneliti-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-peneliti-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.top-peneliti-item {
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
.top-peneliti-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: #06b6d4;
}
.top-peneliti-item:last-child { margin-bottom: 0; }
.top-peneliti-rank {
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
.top-peneliti-item:nth-child(1) .top-peneliti-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-peneliti-item:nth-child(2) .top-peneliti-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-peneliti-item:nth-child(3) .top-peneliti-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-peneliti-icon {
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
.top-peneliti-info { flex: 1; min-width: 0; }
.top-peneliti-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-peneliti-meta {
    font-size: 0.7rem;
    color: var(--text-muted);
}
.top-peneliti-value {
    font-weight: 800;
    color: #0891b2;
    font-size: 0.88rem;
    flex-shrink: 0;
    min-width: 50px;
    text-align: right;
}
.top-peneliti-value small { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }

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
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    border-color: #0891b2;
    box-shadow: 0 4px 12px rgba(8,145,178,0.3);
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
    border-color: #06b6d4;
    box-shadow: 0 0 0 4px rgba(6,182,212,0.1);
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
.filter-select:focus { outline: none; border-color: #06b6d4; }

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
.view-btn.active { background: linear-gradient(135deg, #0891b2, #06b6d4); color: white; }
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
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    box-shadow: 0 4px 12px rgba(8,145,178,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(8,145,178,0.4); }
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
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(8,145,178,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #0891b2; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #0891b2; }
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
.bulk-category-select option { background: #0891b2; color: white; }

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
table.extreme th[data-sortable]:hover { color: #06b6d4; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #06b6d4; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(6,182,212,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #06b6d4; cursor: pointer; }

.riset-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.riset-thumb {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    flex-shrink: 0;
    border: 1px solid #67e8f9;
}
.riset-info { flex: 1; min-width: 0; }
.riset-title {
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.riset-desc-mini {
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
.badge-published { background: #dcfce7; color: #166534; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-publikasi { background: #dbeafe; color: #1e40af; }
.badge-hibah { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-pengabdian { background: #dcfce7; color: #166534; }
.badge-pendidikan { background: #e0e7ff; color: #3730a3; }
.badge-teknologi-pendidikan { background: #f3e8ff; color: #7e22ce; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; }

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

.doi-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.6rem;
    background: #dbeafe;
    color: #1e40af;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.doi-link:hover {
    background: #1e40af;
    color: white;
    transform: translateY(-1px);
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
.btn-icon.view { background: #cffafe; color: #0891b2; }
.btn-icon.view:hover { background: #0891b2; color: white; transform: translateY(-2px); }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== GRID VIEW ===== */
.riset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.riset-card {
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
.riset-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-color, #06b6d4);
    z-index: 2;
}
.riset-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--card-color, #06b6d4);
}
.riset-card.selected {
    border-color: #06b6d4;
    background: rgba(6,182,212,0.02);
}

.riset-check {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 3;
}
.riset-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #06b6d4; }

.riset-image {
    height: 140px;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    overflow: hidden;
}
.riset-jenis-badge {
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
    color: #0891b2;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.riset-status-badge {
    position: absolute;
    top: 1rem;
    left: 3.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}
.riset-status-badge.published { background: #dcfce7; color: #166534; }
.riset-status-badge.draft { background: #fef3c7; color: #92400e; }

.riset-content {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.riset-card-title {
    font-family: 'Georgia', serif;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.riset-card-ketua {
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.riset-card-desc {
    color: var(--text-secondary);
    font-size: 0.82rem;
    line-height: 1.6;
    margin-bottom: 1rem;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.riset-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    gap: 0.5rem;
    flex-wrap: wrap;
}
.riset-card-year {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: #0891b2;
}
.riset-card-tags {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.riset-card-tag {
    font-size: 0.7rem;
    padding: 0.15rem 0.55rem;
    background: var(--bg-secondary);
    border-radius: 999px;
    color: var(--text-muted);
    font-weight: 600;
}

/* Grid Actions (overlay) */
.riset-actions-overlay {
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
.riset-card:hover .riset-actions-overlay {
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
    color: #0891b2;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.timeline-count {
    background: linear-gradient(135deg, #0891b2, #06b6d4);
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
    border-color: #06b6d4;
}
.timeline-item-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    border: 1px solid #67e8f9;
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
.select-all-wrapper input { width: 18px; height: 18px; cursor: pointer; accent-color: #06b6d4; }

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
.modal-header-riset {
    padding: 1.5rem;
    background: linear-gradient(135deg, #0891b2, #06b6d4);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-riset::before {
    content: '🔬';
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 5rem;
    opacity: 0.15;
    line-height: 1;
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
.detail-tab.active { color: #06b6d4; border-bottom-color: #06b6d4; }
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

/* Citation visual */
.citation-visual {
    padding: 1.5rem;
    background: linear-gradient(135deg, #cffafe, #a5f3fc);
    border: 1px solid #67e8f9;
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    text-align: center;
}
.citation-visual h4 {
    font-family: 'Georgia', serif;
    font-size: 1rem;
    font-weight: 800;
    color: #0891b2;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}
.citation-box {
    background: white;
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--text-secondary);
    text-align: left;
    border-left: 3px solid #06b6d4;
    font-style: italic;
}

/* Description area */
.description-area {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid #06b6d4;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--text-secondary);
    max-height: 300px;
    overflow-y: auto;
}

/* Riset stats grid */
.riset-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.riset-stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.riset-stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0891b2;
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.riset-stat-box-label {
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
    .detail-grid, .riset-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .riset-hero-content { flex-direction: column; text-align: center; }
    .health-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .riset-grid { grid-template-columns: 1fr; padding: 1rem; }
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
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .riset-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="riset-hero" data-aos="fade-down">
    <div class="riset-hero-content">
        <div>
            <h2>🔬 Research & Publications</h2>
            <p>Kelola riset, publikasi ilmiah, dan pengabdian masyarakat dosen FKIP UNIMOF. Pantau produktivitas akademik dan dampak penelitian.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_total ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_pub ?></span>
                    <span class="hero-stat-label">Published</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_year ?></span>
                    <span class="hero-stat-label">Tahun Ini</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_doi ?></span>
                    <span class="hero-stat-label">DOI</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="Research Impact Score (persentase riset yang dipublikasikan)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $impact_score ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $impact_score ?></div>
                <div class="score-label">Impact</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #06b6d4;">
        <div class="stat-header">
            <div class="stat-icon-box">🔬</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Riset</div>
        <div class="stat-trend neutral">📚 Semua</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_pub ?>">0</div>
        <div class="stat-label-extreme">Published</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_pub / $stat_total * 100) : 0 ?>%</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">📝</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_draft ?>">0</div>
        <div class="stat-label-extreme">Draft</div>
        <div class="stat-trend neutral">✍️ In Progress</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">💰</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_hibah ?>">0</div>
        <div class="stat-label-extreme">Hibah</div>
        <div class="stat-trend neutral">💰 Grants</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">📄</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_publikasi ?>">0</div>
        <div class="stat-label-extreme">Publikasi</div>
        <div class="stat-trend neutral">📖 Papers</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">👨‍🔬</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_researchers ?>">0</div>
        <div class="stat-label-extreme">Peneliti</div>
        <div class="stat-trend neutral">👥 Researchers</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($jenis_stats) || !empty($kat_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Jenis Riset</h3>
        <div id="jenisChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top 5 Peneliti</h3>
        <?php if (empty($top_peneliti)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">👨‍🔬</div>
                <div>Belum ada data peneliti</div>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($top_peneliti as $i => $p): ?>
                <div class="top-peneliti-item">
                    <div class="top-peneliti-rank"><?= $i + 1 ?></div>
                    <div class="top-peneliti-icon">👨‍🔬</div>
                    <div class="top-peneliti-info">
                        <div class="top-peneliti-name"><?= sanitize($p['nama']) ?></div>
                        <div class="top-peneliti-meta">Terakhir: <?= $p['tahun_terakhir'] ?></div>
                    </div>
                    <div class="top-peneliti-value">
                        <?= $p['total'] ?>
                        <small>riset</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>📈 Kategori Riset</h3>
        <div id="katChart"></div>
    </div>
    <div class="chart-card">
        <h3>📅 Tren Tahunan</h3>
        <div id="tahunChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Status Publikasi</h3>
        <div id="statusChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="riset.php" class="pill <?= empty($jenis_filter) && empty($status_filter) && empty($kat_filter) ? 'active' : '' ?>">
        🔬 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="riset.php?status=Published" class="pill <?= $status_filter === 'Published' ? 'active' : '' ?>">
        ✅ Published <span class="pill-count"><?= $stat_pub ?></span>
    </a>
    <a href="riset.php?status=Draft" class="pill <?= $status_filter === 'Draft' ? 'active' : '' ?>">
        📝 Draft <span class="pill-count"><?= $stat_draft ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="riset.php?jenis=Hibah" class="pill <?= $jenis_filter === 'Hibah' ? 'active' : '' ?>">
        💰 Hibah
    </a>
    <a href="riset.php?jenis=Publikasi" class="pill <?= $jenis_filter === 'Publikasi' ? 'active' : '' ?>">
        📄 Publikasi
    </a>
    <a href="riset.php?jenis=Pengabdian" class="pill <?= $jenis_filter === 'Pengabdian' ? 'active' : '' ?>">
        🤝 Pengabdian
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari judul, abstrak, atau nama peneliti..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="jenisFilter">
        <option value="">📊 Semua Jenis</option>
        <option value="Publikasi" <?= $jenis_filter === 'Publikasi' ? 'selected' : '' ?>>📄 Publikasi</option>
        <option value="Hibah" <?= $jenis_filter === 'Hibah' ? 'selected' : '' ?>>💰 Hibah</option>
        <option value="Pengabdian" <?= $jenis_filter === 'Pengabdian' ? 'selected' : '' ?>>🤝 Pengabdian</option>
    </select>
    <select class="filter-select" id="katFilter">
        <option value="">📚 Semua Kategori</option>
        <option value="Pendidikan" <?= $kat_filter === 'Pendidikan' ? 'selected' : '' ?>>🎓 Pendidikan</option>
        <option value="Teknologi Pendidikan" <?= $kat_filter === 'Teknologi Pendidikan' ? 'selected' : '' ?>>💻 Teknologi Pendidikan</option>
        <option value="Pengabdian" <?= $kat_filter === 'Pengabdian' ? 'selected' : '' ?>>🤝 Pengabdian</option>
        <option value="Lainnya" <?= $kat_filter === 'Lainnya' ? 'selected' : '' ?>>📌 Lainnya</option>
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
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Kategori</button>
    </div>

    <a href="riset-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Riset</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar riset</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($riset_list)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>🔬</span>
        <span><strong style="color: var(--text-primary);"><?= count($riset_list) ?></strong> riset ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortRiset(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="tahun-desc" <?= $sort_by === 'tahun' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Tahun Terbaru</option>
            <option value="tahun-asc" <?= $sort_by === 'tahun' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Tahun Terlama</option>
            <option value="judul-asc" <?= $sort_by === 'judul' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Judul A-Z</option>
            <option value="created_at-desc" <?= $sort_by === 'created_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Baru Ditambahkan</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>riset dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <button type="submit" name="action" value="bulk_publish" class="bulk-btn success" onclick="return confirm('Publikasikan semua riset terpilih?')">✅ Publish</button>
            <select name="new_category" class="bulk-category-select" style="min-width: 150px;">
                <option value="">Ubah Kategori...</option>
                <option value="Pendidikan">🎓 Pendidikan</option>
                <option value="Teknologi Pendidikan">💻 Teknologi Pendidikan</option>
                <option value="Pengabdian">🤝 Pengabdian</option>
                <option value="Lainnya">📌 Lainnya</option>
            </select>
            <button type="submit" name="action" value="bulk_recategorize" class="bulk-btn primary">📂 Recategorize</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN riset terpilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($riset_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🔬</div>
        <h3><?= ($q || $jenis_filter || $kat_filter || $status_filter || $tahun_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada data riset' ?></h3>
        <p><?= ($q || $jenis_filter || $kat_filter || $status_filter || $tahun_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan riset.' : 'Mulai tambahkan data riset dan publikasi untuk membangun portofolio akademik.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $jenis_filter || $kat_filter || $status_filter || $tahun_filter): ?>
                <a href="riset.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="riset-form.php" class="btn-action primary">➕ Tambah Riset Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🔬 Daftar Riset & Publikasi</h2>
                <p>Kelola semua riset dan publikasi ilmiah FKIP UNIMOF</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($riset_list) ?> riset
            </span>
        </div>

        <?php if ($view_mode === 'grid'): ?>
            <!-- GRID VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
                    <span>Pilih Semua (<?= count($riset_list) ?>)</span>
                </label>
            </div>
            <div class="riset-grid">
                <?php
                $card_colors = ['#06b6d4', '#0891b2', '#0e7490', '#155e75', '#22d3ee', '#67e8f9'];
                $jenis_icons = [
                    'Publikasi' => '📄',
                    'Hibah' => '💰',
                    'Pengabdian' => '🤝'
                ];
                foreach ($riset_list as $i => $r):
                    $status_lower = strtolower($r['status'] ?? 'draft');
                    $jenis_lower = strtolower($r['jenis'] ?? 'lainnya');
                    $kat_lower = 'badge-' . strtolower(str_replace(' ', '-', $r['kategori'] ?? 'lainnya'));
                    $icon = $jenis_icons[$r['jenis']] ?? '📄';
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="riset-card" style="--card-color: <?= $color ?>;" data-id="<?= $r['id'] ?>" onclick="showDetail(<?= $r['id'] ?>)">
                    <div class="riset-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="riset-checkbox" value="<?= $r['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="riset-image">
                        <span><?= $icon ?></span>
                        <span class="riset-jenis-badge badge-extreme badge-<?= $jenis_lower ?>"><?= $icon ?> <?= sanitize($r['jenis']) ?></span>
                        <span class="riset-status-badge <?= $status_lower ?>"><?= $r['status'] ?></span>
                    </div>
                    <div class="riset-content">
                        <h3 class="riset-card-title"><?= sanitize($r['judul']) ?></h3>
                        <div class="riset-card-ketua">👤 <?= sanitize($r['ketua_nama'] ?? 'Unknown') ?></div>
                        <p class="riset-card-desc"><?= excerpt($r['abstrak'] ?? $r['deskripsi'] ?? 'Tidak ada abstrak', 120) ?></p>
                        <div class="riset-card-meta">
                            <span class="riset-card-year"><?= $r['tahun'] ?></span>
                            <div class="riset-card-tags">
                                <span class="riset-card-tag badge-extreme <?= $kat_lower ?>"><?= sanitize($r['kategori'] ?? '-') ?></span>
                                <?php if (!empty($r['doi'])): ?>
                                <span class="riset-card-tag">🔗 DOI</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="riset-actions-overlay" onclick="event.stopPropagation()">
                        <a href="riset-form.php?id=<?= $r['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Ubah status riset?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                        </form>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Hapus riset ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
                foreach ($riset_list as $r) {
                    $kat = $r['kategori'] ?? 'Lainnya';
                    $grouped[$kat][] = $r;
                }
                $kat_icons = [
                    'Pendidikan' => '🎓',
                    'Teknologi Pendidikan' => '💻',
                    'Pengabdian' => '🤝',
                    'Lainnya' => '📌'
                ];
                foreach ($grouped as $kat => $items):
                    $icon = $kat_icons[$kat] ?? '📌';
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.75rem;"><?= $icon ?></span>
                        <span class="timeline-title"><?= $kat ?></span>
                        <span class="timeline-count"><?= count($items) ?> riset</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $r):
                            $jenis_icons = ['Publikasi' => '📄', 'Hibah' => '💰', 'Pengabdian' => '🤝'];
                            $jenis_icon = $jenis_icons[$r['jenis']] ?? '📄';
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $r['id'] ?>)">
                            <div class="timeline-item-icon"><?= $jenis_icon ?></div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($r['judul']) ?></div>
                                <div class="timeline-item-meta">
                                    <span>👤 <?= sanitize($r['ketua_nama'] ?? '-') ?></span>
                                    <span>📅 <?= $r['tahun'] ?></span>
                                    <span><?= $r['status'] ?></span>
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
                <table class="extreme" id="risetTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="judul">Judul Riset <span class="sort-icon">↕</span></th>
                            <th>Ketua</th>
                            <th data-sortable="tahun">Tahun <span class="sort-icon">↕</span></th>
                            <th>Jenis</th>
                            <th>Jurnal/DOI</th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $jenis_icons = ['Publikasi' => '📄', 'Hibah' => '💰', 'Pengabdian' => '🤝'];
                    foreach ($riset_list as $r):
                        $status_class = $r['status'] === 'Published' ? 'badge-published' : 'badge-draft';
                        $jenis_lower = strtolower($r['jenis'] ?? 'lainnya');
                        $icon = $jenis_icons[$r['jenis']] ?? '📄';
                    ?>
                    <tr data-id="<?= $r['id'] ?>" data-search="<?= strtolower(sanitize(($r['judul'] ?? '') . ' ' . ($r['abstrak'] ?? '') . ' ' . ($r['ketua_nama'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="riset-checkbox row-checkbox" value="<?= $r['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="riset-cell">
                                <div class="riset-thumb"><?= $icon ?></div>
                                <div class="riset-info">
                                    <div class="riset-title"><?= sanitize($r['judul']) ?></div>
                                    <div class="riset-desc-mini"><?= excerpt($r['abstrak'] ?? $r['deskripsi'] ?? 'Tidak ada abstrak', 60) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?= sanitize($r['ketua_nama'] ?? 'Unknown') ?></strong>
                                <?php if (!empty($r['anggota'])): ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem;">+ <?= excerpt($r['anggota'], 30) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="year-cell">
                                <?= $r['tahun'] ?>
                                <?php if (!empty($r['prodi_nama'])): ?>
                                <small>🏫 <?= sanitize($r['prodi_nama']) ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge-extreme badge-<?= $jenis_lower ?>"><?= $icon ?> <?= sanitize($r['jenis']) ?></span></td>
                        <td style="font-size: 0.85rem;">
                            <?php if (!empty($r['jurnal'])): ?>
                                <div>📖 <?= sanitize($r['jurnal']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['doi'])): ?>
                                <a href="https://doi.org/<?= sanitize($r['doi']) ?>" target="_blank" class="doi-link">🔗 <?= sanitize($r['doi']) ?></a>
                            <?php endif; ?>
                            <?php if (empty($r['jurnal']) && empty($r['doi'])): ?>
                                <span style="color: var(--text-muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-extreme <?= $status_class ?>"><?= $r['status'] ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $r['id'] ?>)" title="Detail">👁️</button>
                                <a href="riset-form.php?id=<?= $r['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status riset?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle" type="submit">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus riset ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
        <div class="modal-header-riset">
            <div class="modal-header-badges" id="modalBadges"></div>
            <h2 class="modal-title" id="modalTitle">-</h2>
            <div class="modal-subtitle" id="modalSubtitle">-</div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('abstrak', this)">📝 Abstrak</button>
                <button class="detail-tab" onclick="switchDetailTab('metrics', this)">📊 Metrics</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-abstrak">
                <div id="detailAbstrak"></div>
            </div>

            <div class="detail-tab-content" id="tab-metrics">
                <div id="detailMetrics"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const risetData = <?= json_encode($riset_list) ?>;

const jenisConfig = {
    'Publikasi': { icon: '📄', color: '#1e40af', bg: '#dbeafe' },
    'Hibah': { icon: '💰', color: '#92400e', bg: '#fef3c7' },
    'Pengabdian': { icon: '🤝', color: '#166534', bg: '#dcfce7' }
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
<?php if (!empty($jenis_stats)): ?>
new ApexCharts(document.querySelector("#jenisChart"), {
    series: <?= json_encode(array_values($jenis_stats)) ?>,
    labels: <?= json_encode(array_keys($jenis_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#1e40af', '#f59e0b', '#10b981'],
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

<?php if (!empty($kat_stats)): ?>
new ApexCharts(document.querySelector("#katChart"), {
    series: [{ data: <?= json_encode(array_values($kat_stats)) ?> }],
    chart: { type: 'bar', height: 260, toolbar: { show: false } },
    colors: ['#8b5cf6'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_keys($kat_stats)) ?>,
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } }
}).render();
<?php endif; ?>

<?php if (!empty($tahun_stats)): ?>
new ApexCharts(document.querySelector("#tahunChart"), {
    series: [{ name: 'Riset', data: <?= json_encode(array_values($tahun_stats)) ?> }],
    chart: { type: 'area', height: 260, toolbar: { show: false } },
    colors: ['#06b6d4'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_keys($tahun_stats)) ?>,
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_pub ?>, <?= $stat_draft ?>],
    labels: ['Published', 'Draft'],
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#f59e0b'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const jenisFilter = document.getElementById('jenisFilter');
const katFilter = document.getElementById('katFilter');
const statusFilter = document.querySelector('select#statusFilter');
const tahunFilter = document.getElementById('tahunFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
jenisFilter?.addEventListener('change', applyFilters);
katFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);
tahunFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const jenis = jenisFilter.value;
    const kat = katFilter.value;
    const status = statusFilter?.value || '';
    const tahun = tahunFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jenis) url.searchParams.set('jenis', jenis); else url.searchParams.delete('jenis');
    if (kat) url.searchParams.set('kategori', kat); else url.searchParams.delete('kategori');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    if (tahun) url.searchParams.set('tahun', tahun); else url.searchParams.delete('tahun');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortRiset(value) {
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
    document.querySelectorAll('.riset-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.riset-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.riset-checkbox');
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

    const allChecks = document.querySelectorAll('.riset-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.riset-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = risetData.find(r => r.id == id);
    if (!data) return;

    const config = jenisConfig[data.jenis] || jenisConfig['Publikasi'];
    const statusClass = data.status === 'Published' ? 'badge-published' : 'badge-draft';
    const jenisClass = 'badge-' + (data.jenis || 'publikasi').toLowerCase();
    const katClass = 'badge-' + (data.kategori || 'lainnya').toLowerCase().replace(/ /g, '-');

    // Badges
    document.getElementById('modalBadges').innerHTML = `
        <span class="badge-extreme ${jenisClass}">${config.icon} ${escapeHtml(data.jenis || 'Publikasi')}</span>
        <span class="badge-extreme ${katClass}">${escapeHtml(data.kategori || '-')}</span>
        <span class="badge-extreme ${statusClass}">${escapeHtml(data.status || 'Draft')}</span>
    `;

    document.getElementById('modalTitle').textContent = data.judul || '-';
    document.getElementById('modalSubtitle').textContent = `${escapeHtml(data.ketua_nama || 'Unknown')} • ${data.tahun || '-'}`;

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">🔬 Judul Riset</div>
            <div class="detail-item-value">${escapeHtml(data.judul || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">👤 Ketua Peneliti</div>
            <div class="detail-item-value">${escapeHtml(data.ketua_nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">👥 Anggota</div>
            <div class="detail-item-value">${escapeHtml(data.anggota || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Jenis</div>
            <div class="detail-item-value"><span class="badge-extreme ${jenisClass}">${config.icon} ${escapeHtml(data.jenis || '-')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📚 Kategori</div>
            <div class="detail-item-value"><span class="badge-extreme ${katClass}">${escapeHtml(data.kategori || '-')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tahun</div>
            <div class="detail-item-value">${data.tahun || '-'}</div>
        </div>
        ${data.jurnal ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📖 Jurnal / Prosiding</div>
            <div class="detail-item-value">${escapeHtml(data.jurnal)}</div>
        </div>
        ` : ''}
        ${data.doi ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">🔗 DOI</div>
            <div class="detail-item-value"><a href="https://doi.org/${escapeHtml(data.doi)}" target="_blank" class="doi-link">🔗 ${escapeHtml(data.doi)}</a></div>
        </div>
        ` : ''}
        ${data.prodi_nama ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">🏫 Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.prodi_nama)}</div>
        </div>
        ` : ''}
    `;

    // Abstrak tab
    const abstrak = data.abstrak || data.deskripsi || 'Tidak ada abstrak.';
    document.getElementById('detailAbstrak').innerHTML = `
        <div class="citation-visual">
            <h4>📜 Citation Preview</h4>
            <div class="citation-box">
                ${escapeHtml(data.ketua_nama || 'Author')}${data.anggota ? ', ' + escapeHtml(data.anggota) : ''} (${data.tahun}). <strong>${escapeHtml(data.judul)}</strong>. ${data.jurnal ? '<em>' + escapeHtml(data.jurnal) + '</em>.' : ''} ${data.doi ? 'DOI: ' + escapeHtml(data.doi) : ''}
            </div>
        </div>
        <div class="description-area">
            ${escapeHtml(abstrak).replace(/\n/g, '<br>')}
        </div>
    `;

    // Metrics tab
    const daysSinceCreated = data.created_at ? Math.floor((new Date() - new Date(data.created_at)) / (1000 * 60 * 60 * 24)) : 0;
    const yearsSincePub = new Date().getFullYear() - (data.tahun || new Date().getFullYear());

    document.getElementById('detailMetrics').innerHTML = `
        <div class="riset-stats-grid">
            <div class="riset-stat-box">
                <div class="riset-stat-box-value">${data.tahun}</div>
                <div class="riset-stat-box-label">Tahun</div>
            </div>
            <div class="riset-stat-box">
                <div class="riset-stat-box-value">${yearsSincePub}</div>
                <div class="riset-stat-box-label">Tahun Lalu</div>
            </div>
            <div class="riset-stat-box">
                <div class="riset-stat-box-value">${daysSinceCreated}</div>
                <div class="riset-stat-box-label">Hari Dicatat</div>
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif;">💡 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${data.status === 'Published' ? '✅ Riset telah dipublikasikan dan dapat diakses publik.' : '📝 Riset masih dalam status draft, perlu finalisasi.'}
                ${data.doi ? '<br>🔗 Memiliki DOI, mudah dilacak dan dikutip.' : '<br>⚠️ Belum memiliki DOI, pertimbangkan untuk mendaftarkannya.'}
                ${data.jenis === 'Hibah' ? '<br>💰 Riset didanai hibah, menunjukkan kualitas proposal.' : ''}
                ${data.jenis === 'Publikasi' ? '<br>📄 Publikasi ilmiah, berkontribusi pada reputasi akademik.' : ''}
                ${yearsSincePub === 0 ? '<br>🆕 Riset baru tahun ini!' : ''}
                ${data.kategori === 'Teknologi Pendidikan' ? '<br>💻 Fokus pada teknologi pendidikan, sesuai tren digital.' : ''}
            </div>
        </div>
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="riset-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Riset</a>
        ${data.doi ? `<a href="https://doi.org/${escapeHtml(data.doi)}" target="_blank" class="btn-action secondary" style="justify-content: flex-start;">🔗 Buka DOI</a>` : ''}
        <button onclick="shareRiset(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Info</button>
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

function shareRiset(id) {
    const data = risetData.find(r => r.id == id);
    if (!data) return;
    let text = `🔬 ${data.judul}\n👤 ${data.ketua_nama || '-'} (${data.tahun})\n📊 ${data.jenis} - ${data.kategori}\n\nRiset FKIP UNIMOF`;
    if (data.doi) text += `\n🔗 https://doi.org/${data.doi}`;
    if (navigator.share) {
        navigator.share({ title: data.judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info riset disalin ke clipboard', 'success');
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
        window.location.href = 'riset-form.php';
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
    document.querySelectorAll('.riset-title, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c🔬 Kelola Riset FKIP UNIMOF - Super Extreme', 'color: #06b6d4; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Grid/Tabel/Kategori), Detail Modal, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>