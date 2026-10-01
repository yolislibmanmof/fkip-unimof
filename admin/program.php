<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        // Ambil logo dan banner untuk dihapus
        $stmt = $pdo->prepare("SELECT logo, banner FROM program_studi WHERE id = ?");
        $stmt->execute([$id]);
        $files = $stmt->fetch();
        if ($files) {
            if ($files['logo'] && function_exists('delete_upload')) delete_upload($files['logo'], 'prodi_logo');
            if ($files['banner'] && function_exists('delete_upload')) delete_upload($files['banner'], 'prodi_banner');
        }
        $pdo->prepare("DELETE FROM program_studi WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Program studi berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE program_studi SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status program studi diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT logo, banner FROM program_studi WHERE id = ?");
            $stmt->execute([$del_id]);
            $files = $stmt->fetch();
            if ($files) {
                if ($files['logo'] && function_exists('delete_upload')) delete_upload($files['logo'], 'prodi_logo');
                if ($files['banner'] && function_exists('delete_upload')) delete_upload($files['banner'], 'prodi_banner');
            }
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM program_studi WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' program studi berhasil dihapus.');
    }
    elseif ($action === 'bulk_activate' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE program_studi SET status = 'Aktif' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' program studi diaktifkan.');
    }

    header('Location: program.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_prodi = $pdo->query("SELECT * FROM program_studi ORDER BY urutan ASC, nama ASC")->fetchAll();

    if ($format === 'csv' && !empty($all_prodi)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="program-studi-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Kode', 'Nama', 'Singkatan', 'Jenjang', 'Akreditasi', 'Kaprodi', 'Status', 'Jumlah Dosen', 'Jumlah Mahasiswa']);
        foreach ($all_prodi as $r) {
            fputcsv($out, [
                $r['id'], $r['kode'], $r['nama'], $r['singkatan'], $r['jenjang'],
                $r['akreditasi'], $r['ketua_prodi'], $r['status'], $r['jumlah_dosen'], $r['jumlah_mahasiswa']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_prodi)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="program-studi-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_prodi),
            'data' => $all_prodi
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & PENCARIAN =====
$q = trim($_GET['q'] ?? '');
$jenjang_filter = $_GET['jenjang'] ?? '';
$status_filter = $_GET['status'] ?? '';
$akreditasi_filter = $_GET['akreditasi'] ?? '';
$sort_by = $_GET['sort'] ?? 'urutan';
$sort_dir = $_GET['dir'] ?? 'asc';
$view_mode = $_GET['view'] ?? 'grid';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR singkatan LIKE ? OR kode LIKE ? OR ketua_prodi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($jenjang_filter !== '') { $where .= ' AND jenjang = ?'; $params[] = $jenjang_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($akreditasi_filter !== '') { $where .= ' AND akreditasi = ?'; $params[] = $akreditasi_filter; }

// Sort validation
$valid_sorts = ['urutan', 'nama', 'jenjang', 'akreditasi', 'jumlah_mahasiswa', 'created_at', 'updated_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'urutan';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'ASC';

$stmt = $pdo->prepare("SELECT * FROM program_studi $where ORDER BY $sort_by $sort_dir");
$stmt->execute($params);
$program_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($program_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE status='Non-Aktif'")->fetchColumn();
$stat_s1 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='S1' AND status='Aktif'")->fetchColumn();
$stat_s2 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='S2' AND status='Aktif'")->fetchColumn();
$stat_s3 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='S3' AND status='Aktif'")->fetchColumn();
$stat_d3 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='D3' AND status='Aktif'")->fetchColumn();
$stat_unggul = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE akreditasi='Unggul' AND status='Aktif'")->fetchColumn();
$stat_baik_sekali = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE akreditasi='Baik Sekali' AND status='Aktif'")->fetchColumn();
$stat_baik = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE akreditasi='Baik' AND status='Aktif'")->fetchColumn();
$stat_total_dosen = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_dosen), 0) FROM program_studi WHERE status='Aktif'")->fetchColumn();
$stat_total_mahasiswa = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_mahasiswa), 0) FROM program_studi WHERE status='Aktif'")->fetchColumn();

// Program health score (percentage of active programs with good accreditation)
$good_accreditation = $stat_unggul + $stat_baik_sekali;
$health_score = $stat_aktif > 0 ? round(($good_accreditation / $stat_aktif) * 100) : 0;
$health_score = min(100, $health_score);

// Recent updates
$stat_updated_month = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Data untuk chart
$jenjang_stats = [];
$jenjang_rows = $pdo->query("SELECT jenjang, COUNT(*) as total FROM program_studi WHERE status='Aktif' GROUP BY jenjang ORDER BY total DESC")->fetchAll();
foreach ($jenjang_rows as $r) $jenjang_stats[$r['jenjang']] = (int)$r['total'];

$akreditasi_stats = [];
$akreditasi_rows = $pdo->query("SELECT akreditasi, COUNT(*) as total FROM program_studi WHERE status='Aktif' GROUP BY akreditasi ORDER BY total DESC")->fetchAll();
foreach ($akreditasi_rows as $r) $akreditasi_stats[$r['akreditasi']] = (int)$r['total'];

// Top prodi by mahasiswa
$top_prodi = $pdo->query("SELECT id, nama, singkatan, jumlah_mahasiswa, akreditasi FROM program_studi WHERE status='Aktif' AND jumlah_mahasiswa > 0 ORDER BY jumlah_mahasiswa DESC LIMIT 5")->fetchAll();

$csrf = generate_csrf_token();
$active_menu = 'prodi';
$page_heading = 'Kelola Program Studi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Program Studi', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Education Theme - Blue/Purple) ===== */
.program-hero {
    background: linear-gradient(135deg, #4338ca 0%, #6366f1 50%, #8b5cf6 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(67, 56, 202, 0.3);
}
.program-hero::before {
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
.program-hero::after {
    content: '🎓';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.program-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.program-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.program-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
    background: linear-gradient(90deg, var(--stat-color, #6366f1), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #6366f1);
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
    background: var(--stat-color, #6366f1);
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
    color: var(--stat-color, #6366f1);
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

/* Top Prodi Widget */
.top-prodi-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-prodi-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
.top-prodi-item {
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
.top-prodi-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: #6366f1;
}
.top-prodi-item:last-child { margin-bottom: 0; }
.top-prodi-rank {
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
.top-prodi-item:nth-child(1) .top-prodi-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-prodi-item:nth-child(2) .top-prodi-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-prodi-item:nth-child(3) .top-prodi-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-prodi-icon {
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
.top-prodi-info { flex: 1; min-width: 0; }
.top-prodi-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-prodi-meta {
    font-size: 0.7rem;
    color: var(--text-muted);
}
.top-prodi-value {
    font-weight: 800;
    color: #6366f1;
    font-size: 0.88rem;
    flex-shrink: 0;
    min-width: 50px;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.top-prodi-value small { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }

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
    background: linear-gradient(135deg, #4338ca, #6366f1);
    color: white;
    border-color: #4338ca;
    box-shadow: 0 4px 12px rgba(67,56,202,0.3);
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
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
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
.filter-select:focus { outline: none; border-color: #6366f1; }

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
.view-btn.active { background: linear-gradient(135deg, #4338ca, #6366f1); color: white; }
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
    background: linear-gradient(135deg, #4338ca, #6366f1);
    color: white;
    box-shadow: 0 4px 12px rgba(67,56,202,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(67,56,202,0.4); }
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
    background: linear-gradient(135deg, #4338ca, #6366f1);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(67,56,202,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #4338ca; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #4338ca; }
.bulk-btn.success { background: #10b981; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

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
table.extreme th[data-sortable]:hover { color: #6366f1; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #6366f1; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(99,102,241,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #6366f1; cursor: pointer; }

.prodi-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.prodi-logo-sm {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 1px solid var(--border);
}
.prodi-logo-sm img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; background: white; }
.prodi-info { flex: 1; min-width: 0; }
.prodi-name {
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
    font-family: 'Georgia', serif;
}
.prodi-code {
    font-size: 0.72rem;
    color: var(--primary);
    font-family: monospace;
    font-weight: 700;
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
.badge-s1 { background: #dbeafe; color: #1e40af; }
.badge-s2 { background: #f3e8ff; color: #7e22ce; }
.badge-s3 { background: #fce7f3; color: #be185d; }
.badge-d3 { background: #fef3c7; color: #92400e; }
.badge-unggul { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.badge-baik-sekali { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }
.badge-baik { background: linear-gradient(135deg, #cd7f32, #b87333); color: white; }
.badge-terakreditasi { background: #e0e7ff; color: #3730a3; }

.stats-cell {
    text-align: center;
}
.stats-cell strong {
    display: block;
    font-size: 1rem;
    color: var(--primary);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.stats-cell small {
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
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
.prodi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.prodi-card {
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
.prodi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-color, #6366f1);
    z-index: 2;
}
.prodi-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--card-color, #6366f1);
}
.prodi-card.selected {
    border-color: #6366f1;
    background: rgba(99,102,241,0.02);
}

.prodi-check {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 3;
}
.prodi-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1; }

.prodi-banner {
    height: 140px;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    position: relative;
    overflow: hidden;
}
.prodi-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s;
}
.prodi-card:hover .prodi-banner img { transform: scale(1.08); }
.prodi-jenjang-badge {
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
    color: #4338ca;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.prodi-status-badge {
    position: absolute;
    top: 1rem;
    left: 3.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 2;
}
.prodi-status-badge.aktif { background: #dcfce7; color: #166534; }
.prodi-status-badge.non-aktif { background: #fee2e2; color: #991b1b; }

.prodi-content {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.prodi-card-title {
    font-family: 'Georgia', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.prodi-card-code {
    font-family: monospace;
    font-size: 0.78rem;
    font-weight: 700;
    color: #6366f1;
    margin-bottom: 0.75rem;
}
.prodi-card-desc {
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
.prodi-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    gap: 0.5rem;
    flex-wrap: wrap;
}
.prodi-card-akreditasi {
    font-size: 0.78rem;
    font-weight: 700;
}
.prodi-card-stats {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    gap: 0.75rem;
}

/* Grid Actions (overlay) */
.prodi-actions-overlay {
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
.prodi-card:hover .prodi-actions-overlay {
    opacity: 1;
    pointer-events: auto;
}

/* ===== TIMELINE VIEW (Grouped by Jenjang) ===== */
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
    color: #4338ca;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.timeline-count {
    background: linear-gradient(135deg, #4338ca, #6366f1);
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
    border-color: #6366f1;
}
.timeline-item-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    background: white;
    border: 1px solid var(--border);
}
.timeline-item-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.25rem; }
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
.select-all-wrapper input { width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; }

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
.modal-header-prodi {
    padding: 0;
    position: relative;
    overflow: hidden;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    background: linear-gradient(135deg, #4338ca, #6366f1);
}
.modal-header-banner {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
}
.modal-header-placeholder {
    width: 100%;
    height: 180px;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 5rem;
    color: #4338ca;
}
.modal-header-overlay {
    padding: 1.5rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
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
    font-family: monospace;
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
.detail-tab.active { color: #6366f1; border-bottom-color: #6366f1; }
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

/* Prodi stats grid */
.prodi-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.prodi-stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.prodi-stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #6366f1;
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.prodi-stat-box-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

/* Description area */
.description-area {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid #6366f1;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--text-secondary);
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
    .detail-grid, .prodi-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .program-hero-content { flex-direction: column; text-align: center; }
    .health-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .filter-select, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .prodi-grid { grid-template-columns: 1fr; padding: 1rem; }
    .table-wrapper { overflow-x: auto; }
    table.extreme { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .stat-number-extreme { font-size: 1.85rem; }
    .bulk-bar { flex-direction: column; align-items: stretch; }
    .bulk-actions { flex-direction: column; }
    .bulk-btn { width: 100%; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .program-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="program-hero" data-aos="fade-down">
    <div class="program-hero-content">
        <div>
            <h2>🎓 Academic Programs</h2>
            <p>Kelola program studi FKIP UNIMOF. Pantau akreditasi, jenjang, dan performa akademik setiap program studi.</p>
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
                    <span class="hero-stat-num"><?= $stat_unggul ?></span>
                    <span class="hero-stat-label">⭐ Unggul</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= number_format($stat_total_mahasiswa) ?></span>
                    <span class="hero-stat-label">Mahasiswa</span>
                </div>
            </div>
        </div>
        <div class="health-ring" title="Program Health Score (persentase prodi dengan akreditasi Unggul/Baik Sekali)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $health_score ?>, 100"/>
            </svg>
            <div class="health-value">
                <div class="score-num"><?= $health_score ?></div>
                <div class="score-label">Quality</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #6366f1;">
        <div class="stat-header">
            <div class="stat-icon-box">🎓</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Program</div>
        <div class="stat-trend neutral">📚 Semua</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Program Aktif</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_aktif / $stat_total * 100) : 0 ?>%</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">⭐</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_unggul ?>">0</div>
        <div class="stat-label-extreme">Akreditasi Unggul</div>
        <div class="stat-trend up">🏆 Excellent</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">🎓</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_s1 ?>">0</div>
        <div class="stat-label-extreme">Program S1</div>
        <div class="stat-trend neutral">🎓 Sarjana</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">👨‍🎓</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total_mahasiswa ?>">0</div>
        <div class="stat-label-extreme">Total Mahasiswa</div>
        <div class="stat-trend neutral">👥 Students</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">👨‍🏫</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total_dosen ?>">0</div>
        <div class="stat-label-extreme">Total Dosen</div>
        <div class="stat-trend neutral">👨‍🏫 Faculty</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($jenjang_stats) || !empty($akreditasi_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Jenjang Program</h3>
        <div id="jenjangChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top 5 Prodi (by Mahasiswa)</h3>
        <?php if (empty($top_prodi)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🎓</div>
                <div>Belum ada data mahasiswa</div>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($top_prodi as $i => $p):
                    $akreditasi_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi'));
                ?>
                <div class="top-prodi-item" onclick="showDetail(<?= $p['id'] ?? 0 ?>)">
                    <div class="top-prodi-rank"><?= $i + 1 ?></div>
                    <div class="top-prodi-icon">🎓</div>
                    <div class="top-prodi-info">
                        <div class="top-prodi-name"><?= sanitize($p['nama']) ?></div>
                        <div class="top-prodi-meta"><?= sanitize($p['singkatan']) ?></div>
                    </div>
                    <div class="top-prodi-value">
                        <?= number_format($p['jumlah_mahasiswa']) ?>
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
        <h3>🏅 Distribusi Akreditasi</h3>
        <div id="akreditasiChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Status Program</h3>
        <div id="statusChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Ringkas</h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <div class="top-prodi-item">
                <div class="top-prodi-icon" style="background: #fef3c7; color: #b45309;">⭐</div>
                <div class="top-prodi-info">
                    <div class="top-prodi-name">Unggul</div>
                    <div class="top-prodi-meta">Akreditasi tertinggi</div>
                </div>
                <div class="top-prodi-value"><?= $stat_unggul ?></div>
            </div>
            <div class="top-prodi-item">
                <div class="top-prodi-icon" style="background: #e2e8f0; color: #475569;">✨</div>
                <div class="top-prodi-info">
                    <div class="top-prodi-name">Baik Sekali</div>
                    <div class="top-prodi-meta">Akreditasi sangat baik</div>
                </div>
                <div class="top-prodi-value"><?= $stat_baik_sekali ?></div>
            </div>
            <div class="top-prodi-item">
                <div class="top-prodi-icon" style="background: #fed7aa; color: #9a3412;">✅</div>
                <div class="top-prodi-info">
                    <div class="top-prodi-name">Baik</div>
                    <div class="top-prodi-meta">Akreditasi baik</div>
                </div>
                <div class="top-prodi-value"><?= $stat_baik ?></div>
            </div>
            <div class="top-prodi-item">
                <div class="top-prodi-icon" style="background: #dbeafe; color: #1e40af;">🆕</div>
                <div class="top-prodi-info">
                    <div class="top-prodi-name">Update Bulan Ini</div>
                    <div class="top-prodi-meta">30 hari terakhir</div>
                </div>
                <div class="top-prodi-value">+<?= $stat_updated_month ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="program.php" class="pill <?= empty($jenjang_filter) && empty($status_filter) && empty($akreditasi_filter) ? 'active' : '' ?>">
        🎓 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="program.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="program.php?status=Non-Aktif" class="pill <?= $status_filter === 'Non-Aktif' ? 'active' : '' ?>">
        ❌ Non-Aktif <span class="pill-count"><?= $stat_nonaktif ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="program.php?akreditasi=Unggul" class="pill <?= $akreditasi_filter === 'Unggul' ? 'active' : '' ?>">
        ⭐ Unggul
    </a>
    <a href="program.php?jenjang=S1" class="pill <?= $jenjang_filter === 'S1' ? 'active' : '' ?>">
        🎓 S1
    </a>
    <a href="program.php?jenjang=S2" class="pill <?= $jenjang_filter === 'S2' ? 'active' : '' ?>">
        🎓 S2
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama, kode, atau kaprodi..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="jenjangFilter">
        <option value="">🎓 Semua Jenjang</option>
        <option value="S1" <?= $jenjang_filter === 'S1' ? 'selected' : '' ?>>🎓 S1 - Sarjana</option>
        <option value="S2" <?= $jenjang_filter === 'S2' ? 'selected' : '' ?>>🎓 S2 - Magister</option>
        <option value="S3" <?= $jenjang_filter === 'S3' ? 'selected' : '' ?>>🎓 S3 - Doktor</option>
        <option value="D3" <?= $jenjang_filter === 'D3' ? 'selected' : '' ?>>🎓 D3 - Diploma</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <select class="filter-select" id="akreditasiFilter">
        <option value="">🏅 Semua Akreditasi</option>
        <option value="Unggul" <?= $akreditasi_filter === 'Unggul' ? 'selected' : '' ?>>⭐ Unggul</option>
        <option value="Baik Sekali" <?= $akreditasi_filter === 'Baik Sekali' ? 'selected' : '' ?>>✨ Baik Sekali</option>
        <option value="Baik" <?= $akreditasi_filter === 'Baik' ? 'selected' : '' ?>>✅ Baik</option>
        <option value="Terakreditasi" <?= $akreditasi_filter === 'Terakreditasi' ? 'selected' : '' ?>>📜 Terakreditasi</option>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Jenjang</button>
    </div>

    <a href="program-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Program</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar program studi</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($program_list)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>🎓</span>
        <span><strong style="color: var(--text-primary);"><?= count($program_list) ?></strong> program ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortProdi(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="urutan-asc" <?= $sort_by === 'urutan' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Urutan Default</option>
            <option value="nama-asc" <?= $sort_by === 'nama' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Nama A-Z</option>
            <option value="nama-desc" <?= $sort_by === 'nama' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Nama Z-A</option>
            <option value="jumlah_mahasiswa-desc" <?= $sort_by === 'jumlah_mahasiswa' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Mahasiswa Terbanyak</option>
            <option value="updated_at-desc" <?= $sort_by === 'updated_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Terbaru diupdate</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>program dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <button type="submit" name="action" value="bulk_activate" class="bulk-btn success" onclick="return confirm('Aktifkan semua program terpilih?')">✅ Activate</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN program terpilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($program_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🎓</div>
        <h3><?= ($q || $jenjang_filter || $status_filter || $akreditasi_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada data program studi' ?></h3>
        <p><?= ($q || $jenjang_filter || $status_filter || $akreditasi_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan program.' : 'Mulai tambahkan program studi untuk membangun katalog akademik yang lengkap.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $jenjang_filter || $status_filter || $akreditasi_filter): ?>
                <a href="program.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="program-form.php" class="btn-action primary">➕ Tambah Program Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🎓 Daftar Program Studi</h2>
                <p>Kelola semua program studi FKIP UNIMOF</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($program_list) ?> program
            </span>
        </div>

        <?php if ($view_mode === 'grid'): ?>
            <!-- GRID VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
                    <span>Pilih Semua (<?= count($program_list) ?>)</span>
                </label>
            </div>
            <div class="prodi-grid">
                <?php
                $card_colors = ['#6366f1', '#8b5cf6', '#4338ca', '#3b82f6', '#ec4899', '#06b6d4'];
                $jenjang_icons = [
                    'S1' => '🎓',
                    'S2' => '🎓',
                    'S3' => '🎓',
                    'D3' => '🎓'
                ];
                foreach ($program_list as $i => $p):
                    $jenjang_class = 'badge-' . strtolower($p['jenjang'] ?? 's1');
                    $status_class = strtolower($p['status'] ?? 'aktif') === 'aktif' ? 'aktif' : 'non-aktif';
                    $akreditasi_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi'));
                    $icon = $jenjang_icons[$p['jenjang']] ?? '🎓';
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="prodi-card" style="--card-color: <?= $color ?>;" data-id="<?= $p['id'] ?>" onclick="showDetail(<?= $p['id'] ?>)">
                    <div class="prodi-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="prodi-checkbox" value="<?= $p['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="prodi-banner">
                        <?php if (!empty($p['banner'])): ?>
                            <img src="<?= base_url('uploads/prodi_banner/' . basename($p['banner'])) ?>" alt="<?= sanitize($p['nama']) ?>">
                        <?php elseif (!empty($p['logo'])): ?>
                            <img src="<?= base_url('uploads/prodi_logo/' . basename($p['logo'])) ?>" alt="<?= sanitize($p['nama']) ?>" style="max-width: 60%; max-height: 60%; object-fit: contain;">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                        <span class="prodi-jenjang-badge badge-extreme <?= $jenjang_class ?>"><?= $icon ?> <?= sanitize($p['jenjang']) ?></span>
                        <span class="prodi-status-badge <?= $status_class ?>"><?= $p['status'] ?></span>
                    </div>
                    <div class="prodi-content">
                        <h3 class="prodi-card-title"><?= sanitize($p['nama']) ?></h3>
                        <div class="prodi-card-code"><?= sanitize($p['kode']) ?> • <?= sanitize($p['singkatan']) ?></div>
                        <p class="prodi-card-desc"><?= excerpt($p['deskripsi'] ?? 'Tidak ada deskripsi', 100) ?></p>
                        <div class="prodi-card-meta">
                            <span class="prodi-card-akreditasi badge-extreme <?= $akreditasi_class ?>"><?= sanitize($p['akreditasi']) ?></span>
                            <div class="prodi-card-stats">
                                <span>👨‍🎓 <?= number_format($p['jumlah_mahasiswa'] ?? 0) ?></span>
                                <span>👨‍🏫 <?= number_format($p['jumlah_dosen'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="prodi-actions-overlay" onclick="event.stopPropagation()">
                        <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" target="_blank" class="btn-icon view" title="Lihat Publik">👁️</a>
                        <a href="program-form.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Ubah status program ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                        </form>
                        <form method="POST" style="display:inline; margin: 0;" onsubmit="return confirm('Hapus program ini?')">
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
            <!-- TIMELINE VIEW (grouped by jenjang) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($program_list as $p) {
                    $jenjang = $p['jenjang'] ?? 'Lainnya';
                    $grouped[$jenjang][] = $p;
                }
                // Sort by jenjang importance
                $hierarchy = ['S3' => 1, 'S2' => 2, 'S1' => 3, 'D3' => 4, 'Lainnya' => 5];
                uksort($grouped, function($a, $b) use ($hierarchy) {
                    return ($hierarchy[$a] ?? 99) - ($hierarchy[$b] ?? 99);
                });
                $jenjang_icons = [
                    'S3' => '🎓',
                    'S2' => '🎓',
                    'S1' => '🎓',
                    'D3' => '🎓',
                    'Lainnya' => '📚'
                ];
                foreach ($grouped as $jenjang => $items):
                    $icon = $jenjang_icons[$jenjang] ?? '📚';
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.75rem;"><?= $icon ?></span>
                        <span class="timeline-title"><?= $jenjang ?></span>
                        <span class="timeline-count"><?= count($items) ?> program</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $p):
                            $akreditasi_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi'));
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $p['id'] ?>)">
                            <div class="timeline-item-logo">
                                <?php if (!empty($p['logo'])): ?>
                                    <img src="<?= base_url('uploads/prodi_logo/' . basename($p['logo'])) ?>" alt="">
                                <?php else: ?>
                                    🎓
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($p['nama']) ?></div>
                                <div class="timeline-item-meta">
                                    <span><?= sanitize($p['singkatan']) ?></span>
                                    <span class="badge-extreme <?= $akreditasi_class ?>"><?= sanitize($p['akreditasi']) ?></span>
                                    <span>👨‍🎓 <?= number_format($p['jumlah_mahasiswa'] ?? 0) ?></span>
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
                <table class="extreme" id="prodiTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="nama">Program Studi <span class="sort-icon">↕</span></th>
                            <th data-sortable="jenjang">Jenjang <span class="sort-icon">↕</span></th>
                            <th data-sortable="akreditasi">Akreditasi <span class="sort-icon">↕</span></th>
                            <th data-sortable="jumlah_mahasiswa">Statistik <span class="sort-icon">↕</span></th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $jenjang_icons = [
                        'S1' => '🎓',
                        'S2' => '🎓',
                        'S3' => '🎓',
                        'D3' => '🎓'
                    ];
                    foreach ($program_list as $p):
                        $jenjang_class = 'badge-' . strtolower($p['jenjang'] ?? 's1');
                        $status_class = strtolower($p['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                        $akreditasi_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi'));
                        $icon = $jenjang_icons[$p['jenjang']] ?? '🎓';
                    ?>
                    <tr data-id="<?= $p['id'] ?>" data-search="<?= strtolower(sanitize(($p['nama'] ?? '') . ' ' . ($p['kode'] ?? '') . ' ' . ($p['ketua_prodi'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="prodi-checkbox row-checkbox" value="<?= $p['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="prodi-cell">
                                <div class="prodi-logo-sm">
                                    <?php if (!empty($p['logo'])): ?>
                                        <img src="<?= base_url('uploads/prodi_logo/' . basename($p['logo'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= $icon ?>
                                    <?php endif; ?>
                                </div>
                                <div class="prodi-info">
                                    <div class="prodi-name"><?= sanitize($p['nama']) ?></div>
                                    <div class="prodi-code"><?= sanitize($p['kode']) ?> • <?= sanitize($p['singkatan']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-extreme <?= $jenjang_class ?>"><?= $icon ?> <?= sanitize($p['jenjang']) ?></span></td>
                        <td><span class="badge-extreme <?= $akreditasi_class ?>"><?= sanitize($p['akreditasi']) ?></span></td>
                        <td>
                            <div class="stats-cell">
                                <strong><?= number_format($p['jumlah_mahasiswa'] ?? 0) ?></strong>
                                <small>👨‍🎓 Mhs</small>
                            </div>
                            <div class="stats-cell" style="margin-top: 0.25rem;">
                                <strong><?= number_format($p['jumlah_dosen'] ?? 0) ?></strong>
                                <small>👨‍🏫 Dosen</small>
                            </div>
                        </td>
                        <td><span class="badge-extreme <?= $status_class ?>"><?= $p['status'] ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $p['id'] ?>)" title="Detail">👁️</button>
                                <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" target="_blank" class="btn-icon view" title="Lihat Publik">🌐</a>
                                <a href="program-form.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status program ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status" type="submit">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus program ini?')">
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
        <div class="modal-header-prodi">
            <?php if (!empty($p['banner'])): ?>
                <img class="modal-header-banner" id="modalBanner" src="" alt="">
            <?php else: ?>
                <div class="modal-header-placeholder" id="modalPlaceholder">🎓</div>
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
                <button class="detail-tab" onclick="switchDetailTab('visi', this)">🎯 Visi & Misi</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-stats">
                <div id="detailStats"></div>
            </div>

            <div class="detail-tab-content" id="tab-visi">
                <div id="detailVisiMisi"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const prodiData = <?= json_encode($program_list) ?>;

const jenjangConfig = {
    'S1': { icon: '🎓', color: '#1e40af', bg: '#dbeafe' },
    'S2': { icon: '🎓', color: '#7e22ce', bg: '#f3e8ff' },
    'S3': { icon: '🎓', color: '#be185d', bg: '#fce7f3' },
    'D3': { icon: '🎓', color: '#92400e', bg: '#fef3c7' }
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
<?php if (!empty($jenjang_stats)): ?>
new ApexCharts(document.querySelector("#jenjangChart"), {
    series: <?= json_encode(array_values($jenjang_stats)) ?>,
    labels: <?= json_encode(array_keys($jenjang_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#1e40af', '#7e22ce', '#be185d', '#92400e'],
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
<?php endif; ?>

<?php if (!empty($akreditasi_stats)): ?>
new ApexCharts(document.querySelector("#akreditasiChart"), {
    series: <?= json_encode(array_values($akreditasi_stats)) ?>,
    labels: <?= json_encode(array_keys($akreditasi_stats)) ?>,
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#f59e0b', '#94a3b8', '#cd7f32', '#6366f1'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_nonaktif ?>],
    labels: ['Aktif', 'Non-Aktif'],
    chart: { type: 'pie', height: 260, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const jenjangFilter = document.getElementById('jenjangFilter');
const statusFilter = document.getElementById('statusFilter');
const akreditasiFilter = document.getElementById('akreditasiFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
jenjangFilter?.addEventListener('change', applyFilters);
statusFilter?.addEventListener('change', applyFilters);
akreditasiFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const jenjang = jenjangFilter.value;
    const status = statusFilter.value;
    const akreditasi = akreditasiFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jenjang) url.searchParams.set('jenjang', jenjang); else url.searchParams.delete('jenjang');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    if (akreditasi) url.searchParams.set('akreditasi', akreditasi); else url.searchParams.delete('akreditasi');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortProdi(value) {
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
    document.querySelectorAll('.prodi-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.prodi-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.prodi-checkbox');
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

    const allChecks = document.querySelectorAll('.prodi-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.prodi-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllGrid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = prodiData.find(p => p.id == id);
    if (!data) return;

    const config = jenjangConfig[data.jenjang] || jenjangConfig['S1'];
    const jenjangClass = 'badge-' + (data.jenjang || 's1').toLowerCase();
    const statusClass = data.status === 'Aktif' ? 'badge-aktif' : 'badge-non-aktif';
    const akreditasiClass = 'badge-' + (data.akreditasi || 'terakreditasi').toLowerCase().replace(/ /g, '-');

    // Header banner/placeholder
    const bannerEl = document.getElementById('modalBanner');
    const placeholderEl = document.getElementById('modalPlaceholder');
    if (data.banner) {
        bannerEl.src = window.location.origin + '/uploads/prodi_banner/' + data.banner;
        bannerEl.style.display = 'block';
        placeholderEl.style.display = 'none';
    } else {
        bannerEl.style.display = 'none';
        placeholderEl.style.display = 'flex';
        placeholderEl.textContent = '🎓';
    }

    // Badges
    document.getElementById('modalBadges').innerHTML = `
        <span class="badge-extreme ${jenjangClass}">${config.icon} ${escapeHtml(data.jenjang || 'S1')}</span>
        <span class="badge-extreme ${akreditasiClass}">${escapeHtml(data.akreditasi || 'Terakreditasi')}</span>
        <span class="badge-extreme ${statusClass}">${escapeHtml(data.status || 'Aktif')}</span>
    `;

    document.getElementById('modalTitle').textContent = data.nama || '-';
    document.getElementById('modalSubtitle').textContent = `${escapeHtml(data.kode || '')} • ${escapeHtml(data.singkatan || '')}`;

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">🎓 Nama Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🌐 Nama (English)</div>
            <div class="detail-item-value">${escapeHtml(data.nama_en || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🔑 Kode</div>
            <div class="detail-item-value" style="font-family: monospace; font-weight: 700;">${escapeHtml(data.kode || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏷️ Singkatan</div>
            <div class="detail-item-value">${escapeHtml(data.singkatan || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">👤 Ketua Prodi</div>
            <div class="detail-item-value">${escapeHtml(data.ketua_prodi || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🆔 NIDN Kaprodi</div>
            <div class="detail-item-value" style="font-family: monospace;">${escapeHtml(data.nidn_kaprodi || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Dibuat</div>
            <div class="detail-item-value">${formatDate(data.created_at)}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🔄 Update Terakhir</div>
            <div class="detail-item-value">${formatDate(data.updated_at)}</div>
        </div>
    `;

    // Stats tab
    const daysSinceUpdate = data.updated_at ? Math.floor((new Date() - new Date(data.updated_at)) / (1000 * 60 * 60 * 24)) : 0;
    const ratio = data.jumlah_dosen > 0 ? Math.round(data.jumlah_mahasiswa / data.jumlah_dosen) : 0;

    document.getElementById('detailStats').innerHTML = `
        <div class="prodi-stats-grid">
            <div class="prodi-stat-box">
                <div class="prodi-stat-box-value">${(data.jumlah_mahasiswa || 0).toLocaleString('id-ID')}</div>
                <div class="prodi-stat-box-label">Mahasiswa</div>
            </div>
            <div class="prodi-stat-box">
                <div class="prodi-stat-box-value">${(data.jumlah_dosen || 0).toLocaleString('id-ID')}</div>
                <div class="prodi-stat-box-label">Dosen</div>
            </div>
            <div class="prodi-stat-box">
                <div class="prodi-stat-box-value">${ratio}:1</div>
                <div class="prodi-stat-box-label">Rasio</div>
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif;">💡 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${data.akreditasi === 'Unggul' ? '⭐ Program studi dengan akreditasi tertinggi dari BAN-PT.' : ''}
                ${data.akreditasi === 'Baik Sekali' ? '✨ Program studi dengan akreditasi sangat baik.' : ''}
                ${data.status === 'Aktif' ? '<br>✅ Program studi dalam status aktif dan menerima mahasiswa baru.' : '<br>⚠️ Program studi dalam status non-aktif.'}
                ${ratio > 30 ? '<br>⚠️ Rasio mahasiswa:dosen cukup tinggi, perlu penambahan dosen.' : ''}
                ${ratio > 0 && ratio <= 20 ? '<br>✅ Rasio mahasiswa:dosen ideal untuk pembelajaran optimal.' : ''}
                ${daysSinceUpdate < 30 ? '<br>🆕 Data baru diperbarui bulan ini.' : ''}
            </div>
        </div>
    `;

    // Visi & Misi tab
    document.getElementById('detailVisiMisi').innerHTML = `
        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 1rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif; display: flex; align-items: center; gap: 0.5rem;">🎯 Visi</h4>
            <div class="description-area">${escapeHtml(data.visi || 'Belum ada visi yang didefinisikan.').replace(/\n/g, '<br>')}</div>
        </div>
        <div>
            <h4 style="font-size: 1rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif; display: flex; align-items: center; gap: 0.5rem;">🚀 Misi</h4>
            <div class="description-area">${escapeHtml(data.misi || 'Belum ada misi yang didefinisikan.').replace(/\n/g, '<br>')}</div>
        </div>
    `;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="program-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Program Studi</a>
        <a href="${window.location.origin}/program-detail.php?id=${data.id}" target="_blank" class="btn-action secondary" style="justify-content: flex-start;">🌐 Lihat di Website Publik</a>
        <button onclick="shareProdi(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Info</button>
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

function shareProdi(id) {
    const data = prodiData.find(p => p.id == id);
    if (!data) return;
    const text = `🎓 ${data.nama}\n🔑 ${data.kode} • ${data.singkatan}\n📊 ${data.jenjang} - ${data.akreditasi}\n👨‍🎓 ${data.jumlah_mahasiswa} Mahasiswa\n\nProgram Studi FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info program studi disalin ke clipboard', 'success');
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
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
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
        window.location.href = 'program-form.php';
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
    document.querySelectorAll('.prodi-name, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c🎓 Kelola Program Studi FKIP UNIMOF - Super Extreme', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Grid/Tabel/Jenjang), Detail Modal, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>