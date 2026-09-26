<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT foto FROM dosen WHERE id = ?");
        $stmt->execute([$id]);
        $foto = $stmt->fetchColumn();
        if (function_exists('delete_upload')) delete_upload($foto, 'dosen');
        $pdo->prepare("DELETE FROM dosen WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Dosen berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE dosen SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status dosen diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT foto FROM dosen WHERE id = ?");
            $stmt->execute([$del_id]);
            if (function_exists('delete_upload')) delete_upload($stmt->fetchColumn(), 'dosen');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM dosen WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' dosen berhasil dihapus.');
    }
    elseif ($action === 'bulk_status' && !empty($ids)) {
        $new_status = $_POST['new_status'] ?? 'Aktif';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_status], $ids);
        $pdo->prepare("UPDATE dosen SET status = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', 'Status ' . count($ids) . ' dosen diubah ke ' . $new_status);
    }
    elseif ($action === 'bulk_activate' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE dosen SET status = 'Aktif' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' dosen diaktifkan.');
    }

    header('Location: dosen.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$jabatan_filter = $_GET['jabatan'] ?? '';
$status_filter = $_GET['status'] ?? '';
$prodi_filter = $_GET['prodi'] ?? '';
$view_mode = $_GET['view'] ?? 'table';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (d.nama LIKE ? OR d.nidn LIKE ? OR d.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($jabatan_filter !== '') { $where .= ' AND d.jabatan_fungsional = ?'; $params[] = $jabatan_filter; }
if ($status_filter !== '') { $where .= ' AND d.status = ?'; $params[] = $status_filter; }
if ($prodi_filter !== '') { $where .= ' AND d.program_studi_id = ?'; $params[] = $prodi_filter; }

$stmt = $pdo->prepare("SELECT d.*, ps.nama as prodi_nama FROM dosen d LEFT JOIN program_studi ps ON d.program_studi_id = ps.id $where ORDER BY d.created_at DESC");
$stmt->execute($params);
$dosen_list = $stmt->fetchAll();

// ===== EXPORT HANDLER =====
if (isset($_GET['export']) && !empty($dosen_list)) {
    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="dosen-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Nama', 'NIDN', 'Jabatan', 'Program Studi', 'Pendidikan', 'Email', 'Status']);
        foreach ($dosen_list as $r) {
            fputcsv($out, [
                $r['id'], $r['nama'], $r['nidn'] ?? '-', $r['jabatan_fungsional'] ?? '-',
                $r['prodi_nama'] ?? '-', $r['pendidikan_terakhir'] ?? '-',
                $r['email'] ?? '-', $r['status'] ?? 'Aktif'
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="dosen-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($dosen_list),
            'data' => $dosen_list
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== STATISTIK LENGKAP =====
$stat_total = (int)$pdo->query("SELECT COUNT(*) FROM dosen")->fetchColumn();
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE status='Non-Aktif'")->fetchColumn();
$stat_guru_besar = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE jabatan_fungsional='Guru Besar' AND status='Aktif'")->fetchColumn();
$stat_lektor = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE jabatan_fungsional IN ('Lektor Kepala', 'Lektor') AND status='Aktif'")->fetchColumn();
$stat_asisten = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE jabatan_fungsional='Asisten Ahli' AND status='Aktif'")->fetchColumn();
$stat_phd = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE pendidikan_terakhir LIKE '%S3%' OR pendidikan_terakhir LIKE '%Doktor%' OR pendidikan_terakhir LIKE '%Dr.%'")->fetchColumn();

// Academic quality score
$quality_score = $stat_total > 0 ? round(
    (($stat_guru_besar * 25) + ($stat_lektor * 15) + ($stat_asisten * 10) + ($stat_phd * 20)) / $stat_total
) : 0;
$quality_score = min(100, $quality_score);

// Dosen baru bulan ini
$month_new = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Jabatan stats
$jabatan_stats = [];
$jabatan_rows = $pdo->query("SELECT jabatan_fungsional, COUNT(*) as total FROM dosen WHERE status='Aktif' GROUP BY jabatan_fungsional ORDER BY total DESC")->fetchAll();
foreach ($jabatan_rows as $r) $jabatan_stats[$r['jabatan_fungsional']] = (int)$r['total'];

// Top prodi
$top_prodi = $pdo->query("
    SELECT ps.nama, COUNT(d.id) as total
    FROM dosen d
    LEFT JOIN program_studi ps ON d.program_studi_id = ps.id
    WHERE ps.nama IS NOT NULL AND d.status='Aktif'
    GROUP BY d.program_studi_id, ps.nama
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// Education distribution
$edu_stats = [];
$edu_rows = $pdo->query("SELECT pendidikan_terakhir, COUNT(*) as total FROM dosen WHERE pendidikan_terakhir IS NOT NULL AND pendidikan_terakhir != '' AND status='Aktif' GROUP BY pendidikan_terakhir ORDER BY total DESC LIMIT 5")->fetchAll();
foreach ($edu_rows as $r) $edu_stats[$r['pendidikan_terakhir']] = (int)$r['total'];

// Daftar prodi untuk filter
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

$csrf = generate_csrf_token();
$active_menu = 'dosen';
$page_heading = 'Kelola Dosen';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Data Dosen', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Academic Theme - Navy/Blue) ===== */
.dosen-hero {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(30, 64, 175, 0.3);
}
.dosen-hero::before {
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
.dosen-hero::after {
    content: 'FACULTY';
    position: absolute;
    top: 2rem;
    right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 5rem;
    font-weight: 900;
    color: rgba(255,255,255,0.05);
    letter-spacing: 0.2em;
    pointer-events: none;
}
.dosen-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.dosen-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.dosen-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; }
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    background: var(--stat-color, var(--primary));
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
    color: var(--stat-color, var(--primary));
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

/* Top prodi list */
.top-prodi-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.top-prodi-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.top-prodi-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-prodi-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 800;
    flex-shrink: 0;
}
.top-prodi-item:nth-child(1) .top-prodi-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-prodi-item:nth-child(2) .top-prodi-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-prodi-item:nth-child(3) .top-prodi-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-prodi-info { flex: 1; min-width: 0; }
.top-prodi-name {
    font-weight: 700;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.top-prodi-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}
.top-prodi-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #1e40af);
    border-radius: 999px;
    transition: width 1s ease;
}
.top-prodi-count {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--primary);
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
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border-color: #1e40af;
    box-shadow: 0 4px 12px rgba(30,64,175,0.3);
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
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
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
.filter-select:focus { outline: none; border-color: #3b82f6; }

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
.view-btn.active { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; }
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
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    box-shadow: 0 4px 12px rgba(30,64,175,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(30,64,175,0.4); }
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
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(30,64,175,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #1e40af; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #1e40af; }
.bulk-btn.success { background: #10b981; color: white; }
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
table.extreme th[data-sortable]:hover { color: #3b82f6; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #3b82f6; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(30,64,175,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #3b82f6; cursor: pointer; }

/* Avatar */
.dosen-avatar-sm {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid var(--border);
}
.dosen-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.dosen-avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid var(--border);
    box-shadow: 0 4px 12px rgba(30,64,175,0.25);
}
.dosen-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }

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
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-non-aktif { background: #fee2e2; color: #991b1b; }
.badge-guru-besar { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-lektor-kepala { background: #dbeafe; color: #1e40af; }
.badge-lektor { background: #e0e7ff; color: #4338ca; }
.badge-asisten-ahli { background: #f3e8ff; color: #7e22ce; }
.badge-tenaga-pengajar { background: #f3f4f6; color: #4b5563; }
.badge-tidak-ada { background: #f3f4f6; color: #4b5563; }

/* Social icons */
.social-icons { display: flex; gap: 0.3rem; margin-top: 0.35rem; }
.social-icon-link {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: var(--bg-tertiary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 0.82rem;
    transition: all 0.2s;
}
.social-icon-link:hover { transform: translateY(-2px); }
.social-icon-link.scholar { background: #4285f4; color: white; }
.social-icon-link.scopus { background: #e9711c; color: white; }
.social-icon-link.email { background: #ea4335; color: white; }

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
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.dosen-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.dosen-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 70px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
}
.dosen-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: #3b82f6;
}
.dosen-card-body {
    padding: 1.25rem;
    padding-top: 3rem;
    position: relative;
}
.dosen-card-avatar {
    position: absolute;
    top: -35px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.75rem;
    border: 4px solid var(--bg-primary);
    box-shadow: 0 8px 20px rgba(30,64,175,0.3);
    overflow: hidden;
}
.dosen-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dosen-card-name {
    text-align: center;
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    margin-top: 0.5rem;
    font-family: 'Georgia', serif;
}
.dosen-card-nidn {
    text-align: center;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    font-family: monospace;
}
.dosen-card-jabatan {
    text-align: center;
    margin-bottom: 1rem;
}
.dosen-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.dosen-card-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-secondary);
}
.dosen-card-info-item .info-icon {
    width: 24px;
    height: 24px;
    background: var(--bg-secondary);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.82rem;
}
.dosen-card-footer {
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
.timeline-title {
    font-size: 1.5rem;
    font-weight: 900;
    color: #1e40af;
    font-family: 'Georgia', serif;
}
.timeline-count {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
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
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(3px); border-color: #3b82f6; }
.timeline-item-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}
.timeline-item-avatar img { width: 100%; height: 100%; object-fit: cover; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Georgia', serif; }
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
.empty-state-extreme h3 { font-size: 1.25rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
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
.modal-header-dosen {
    padding: 2rem;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-dosen::before {
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
.modal-profile-info { flex: 1; min-width: 200px; }
.modal-profile-name { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; font-family: 'Georgia', serif; }
.modal-profile-nidn { font-size: 0.85rem; opacity: 0.95; margin-bottom: 0.35rem; font-family: monospace; }
.modal-profile-jabatan {
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
.detail-tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }
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

/* Academic profile */
.academic-profile {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    margin-bottom: 1rem;
}
.academic-profile h4 {
    font-size: 0.95rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.academic-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
.academic-stat {
    text-align: center;
    padding: 0.75rem;
    background: var(--bg-primary);
    border-radius: 8px;
    border: 1px solid var(--border);
}
.academic-stat-value {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.academic-stat-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

/* Social links */
.social-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
.social-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s;
}
.social-item:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
    transform: translateY(-2px);
}
.social-item-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.social-item-info { flex: 1; min-width: 0; }
.social-item-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
.social-item-value { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .detail-grid, .social-grid, .academic-stats { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .dosen-hero-content { flex-direction: column; text-align: center; }
    .quality-ring { margin: 0 auto; }
    .cards-grid { grid-template-columns: 1fr; }
    .hero-stats { justify-content: center; }
    .timeline-items { grid-template-columns: 1fr; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .dosen-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="dosen-hero" data-aos="fade-down">
    <div class="dosen-hero-content">
        <div>
            <h2>👨‍🏫 Faculty Management</h2>
            <p>Kelola data dosen FKIP UNIMOF. Pantau distribusi jabatan fungsional, kualifikasi akademik, dan status keaktifan.</p>
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
                    <span class="hero-stat-num"><?= $stat_guru_besar ?></span>
                    <span class="hero-stat-label">Guru Besar</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_phd ?></span>
                    <span class="hero-stat-label">S3/Doktor</span>
                </div>
            </div>
        </div>
        <div class="quality-ring" title="Skor Kualitas Akademik (semakin tinggi semakin baik)">
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
            <div class="stat-icon-box">🏫</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Dosen</div>
        <div class="stat-trend neutral">📚 Semua dosen</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">✅</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Dosen Aktif</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_aktif / $stat_total * 100) : 0 ?>% dari total</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">🏆</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_guru_besar ?>">0</div>
        <div class="stat-label-extreme">Guru Besar</div>
        <div class="stat-trend up">⭐ Professor</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">🎖️</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_lektor ?>">0</div>
        <div class="stat-label-extreme">Lektor</div>
        <div class="stat-trend neutral">📖 Senior</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #06b6d4;">
        <div class="stat-header">
            <div class="stat-icon-box">📚</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_asisten ?>">0</div>
        <div class="stat-label-extreme">Asisten Ahli</div>
        <div class="stat-trend neutral">📖 Junior</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="stat-icon-box">🎓</div>
        </div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_phd ?>">0</div>
        <div class="stat-label-extreme">S3/Doktor</div>
        <div class="stat-trend up">🎓 Kualifikasi tinggi</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($jabatan_stats) || !empty($top_prodi)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Jabatan Fungsional</h3>
        <div id="jabatanChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏫 Distribusi per Program Studi</h3>
        <?php if (empty($top_prodi)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🏫</div>
                <div>Belum ada data prodi</div>
            </div>
        <?php else: ?>
            <div class="top-prodi-list">
                <?php
                $max_count = max(array_column($top_prodi, 'total'));
                foreach ($top_prodi as $i => $p):
                ?>
                <div class="top-prodi-item">
                    <div class="top-prodi-rank"><?= $i + 1 ?></div>
                    <div class="top-prodi-info">
                        <div class="top-prodi-name"><?= sanitize($p['nama']) ?></div>
                        <div class="top-prodi-bar">
                            <div class="top-prodi-bar-fill" style="width: <?= ($p['total'] / $max_count) * 100 ?>%"></div>
                        </div>
                    </div>
                    <div class="top-prodi-count"><?= $p['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>📈 Ringkasan Status</h3>
        <div id="statusChart"></div>
    </div>
    <div class="chart-card">
        <h3>🎓 Kualifikasi Pendidikan</h3>
        <div id="eduChart"></div>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Jabatan</h3>
        <div id="jabatanBarChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="dosen.php" class="pill <?= empty($status_filter) && empty($jabatan_filter) ? 'active' : '' ?>">
        🎯 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="dosen.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="dosen.php?status=Non-Aktif" class="pill <?= $status_filter === 'Non-Aktif' ? 'active' : '' ?>">
        📴 Non-Aktif <span class="pill-count"><?= $stat_nonaktif ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="dosen.php?jabatan=Guru+Besar" class="pill <?= $jabatan_filter === 'Guru Besar' ? 'active' : '' ?>">
        🏆 Guru Besar
    </a>
    <a href="dosen.php?jabatan=Lektor+Kepala" class="pill <?= $jabatan_filter === 'Lektor Kepala' ? 'active' : '' ?>">
        🎖️ Lektor Kepala
    </a>
    <a href="dosen.php?jabatan=Lektor" class="pill <?= $jabatan_filter === 'Lektor' ? 'active' : '' ?>">
        📚 Lektor
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama dosen, NIDN, atau email..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="jabatanFilter">
        <option value="">🎖️ Semua Jabatan</option>
        <option value="Guru Besar" <?= $jabatan_filter === 'Guru Besar' ? 'selected' : '' ?>>🏆 Guru Besar</option>
        <option value="Lektor Kepala" <?= $jabatan_filter === 'Lektor Kepala' ? 'selected' : '' ?>>🎖️ Lektor Kepala</option>
        <option value="Lektor" <?= $jabatan_filter === 'Lektor' ? 'selected' : '' ?>>📚 Lektor</option>
        <option value="Asisten Ahli" <?= $jabatan_filter === 'Asisten Ahli' ? 'selected' : '' ?>>📖 Asisten Ahli</option>
        <option value="Tenaga Pengajar" <?= $jabatan_filter === 'Tenaga Pengajar' ? 'selected' : '' ?>>🏫 Tenaga Pengajar</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <?php if (!empty($prodi_list)): ?>
    <select class="filter-select" id="prodiFilter">
        <option value="">🎓 Semua Prodi</option>
        <?php foreach ($prodi_list as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $prodi_filter == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">🏫 Jabatan</button>
    </div>

    <a href="dosen-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Dosen</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak direktori dosen</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>dosen dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <select name="new_status" class="bulk-btn primary" style="cursor: pointer;">
                <option value="Aktif">✅ Set Aktif</option>
                <option value="Non-Aktif">📴 Set Non-Aktif</option>
            </select>
            <button type="submit" name="action" value="bulk_status" class="bulk-btn primary">Ubah Status</button>
            <button type="submit" name="action" value="bulk_activate" class="bulk-btn success">🚀 Activate All</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus semua dosen terpilih? Aksi ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== TABLE / CARD / TIMELINE VIEW ===== -->
<?php if (empty($dosen_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">👨‍🏫</div>
        <h3>Belum ada data dosen</h3>
        <p>Mulai tambahkan data dosen FKIP UNIMOF untuk membangun direktori akademik yang lengkap.</p>
        <a href="dosen-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Dosen Pertama</a>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>👨‍🏫 Daftar Dosen</h2>
                <p>Kelola data dosen FKIP UNIMOF</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($dosen_list) ?> data
            </span>
        </div>

        <?php if ($view_mode === 'card'): ?>
            <!-- CARD VIEW -->
            <div class="cards-grid">
                <?php foreach ($dosen_list as $d):
                    $initials = strtoupper(substr($d['nama'], 0, 1));
                    $jabatan_lower = strtolower(str_replace(' ', '-', $d['jabatan_fungsional'] ?? 'tidak-ada'));
                    $status_class = strtolower($d['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                ?>
                <div class="dosen-card" onclick="showDetail(<?= $d['id'] ?>)">
                    <div class="dosen-card-body">
                        <div class="dosen-card-avatar">
                            <?php if (!empty($d['foto'])): ?>
                                <img src="<?= asset('uploads/dosen/' . basename($d['foto'])) ?>" alt="">
                            <?php else: ?>
                                <?= $initials ?>
                            <?php endif; ?>
                        </div>
                        <div class="dosen-card-name"><?= sanitize($d['nama']) ?></div>
                        <div class="dosen-card-nidn"><?= sanitize($d['nidn'] ?: 'NIDN belum diisi') ?></div>
                        <div class="dosen-card-jabatan">
                            <span class="badge-extreme badge-<?= $jabatan_lower ?>"><?= sanitize($d['jabatan_fungsional'] ?? '-') ?></span>
                        </div>
                        <div class="dosen-card-info">
                            <div class="dosen-card-info-item">
                                <span class="info-icon">🎓</span>
                                <span><?= sanitize($d['prodi_nama'] ?? '-') ?></span>
                            </div>
                            <?php if (!empty($d['pendidikan_terakhir'])): ?>
                            <div class="dosen-card-info-item">
                                <span class="info-icon">📚</span>
                                <span><?= sanitize($d['pendidikan_terakhir']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($d['email'])): ?>
                            <div class="dosen-card-info-item">
                                <span class="info-icon">📧</span>
                                <span><?= sanitize($d['email']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dosen-card-footer">
                        <span class="badge-extreme <?= $status_class ?>"><?= $d['status'] ?? 'Aktif' ?></span>
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <?php if (!empty($d['email'])): ?>
                                <a href="mailto:<?= sanitize($d['email']) ?>" class="social-icon-link email" title="Email">✉</a>
                            <?php endif; ?>
                            <a href="dosen-form.php?id=<?= $d['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by jabatan) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($dosen_list as $d) {
                    $jabatan = $d['jabatan_fungsional'] ?? 'Tidak Ada';
                    $grouped[$jabatan][] = $d;
                }
                // Sort by hierarchy
                $hierarchy = ['Guru Besar' => 1, 'Lektor Kepala' => 2, 'Lektor' => 3, 'Asisten Ahli' => 4, 'Tenaga Pengajar' => 5];
                uksort($grouped, function($a, $b) use ($hierarchy) {
                    return ($hierarchy[$a] ?? 99) - ($hierarchy[$b] ?? 99);
                });
                $jabatan_icons = [
                    'Guru Besar' => '🏆',
                    'Lektor Kepala' => '🎖️',
                    'Lektor' => '📚',
                    'Asisten Ahli' => '📖',
                    'Tenaga Pengajar' => '🏫',
                    'Tidak Ada' => '📋'
                ];
                foreach ($grouped as $jabatan => $items):
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.5rem;"><?= $jabatan_icons[$jabatan] ?? '📋' ?></span>
                        <span class="timeline-title"><?= $jabatan ?></span>
                        <span class="timeline-count"><?= count($items) ?> dosen</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $d):
                            $initials = strtoupper(substr($d['nama'], 0, 1));
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $d['id'] ?>)">
                            <div class="timeline-item-avatar">
                                <?php if (!empty($d['foto'])): ?>
                                    <img src="<?= asset('uploads/dosen/' . basename($d['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($d['nama']) ?></div>
                                <div class="timeline-item-role">
                                    <?= sanitize($d['prodi_nama'] ?? '-') ?>
                                    <?php if (!empty($d['pendidikan_terakhir'])): ?>
                                        • <?= sanitize($d['pendidikan_terakhir']) ?>
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
                <table class="extreme" id="dosenTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th data-sortable="nama">Dosen <span class="sort-icon">↕</span></th>
                            <th data-sortable="nidn">NIDN <span class="sort-icon">↕</span></th>
                            <th data-sortable="jabatan">Jabatan <span class="sort-icon">↕</span></th>
                            <th data-sortable="prodi">Program Studi <span class="sort-icon">↕</span></th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dosen_list as $d):
                        $jabatan_lower = strtolower(str_replace(' ', '-', $d['jabatan_fungsional'] ?? 'tidak-ada'));
                        $status_class = strtolower($d['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                        $initials = strtoupper(substr($d['nama'], 0, 1));
                    ?>
                    <tr data-id="<?= $d['id'] ?>" data-search="<?= strtolower(sanitize($d['nama'] . ' ' . ($d['nidn'] ?? '') . ' ' . ($d['email'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="row-checkbox row-select" value="<?= $d['id'] ?>" onchange="updateBulkCount()">
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="dosen-avatar-sm">
                                    <?php if (!empty($d['foto'])): ?>
                                        <img src="<?= asset('uploads/dosen/' . basename($d['foto'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= $initials ?>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0;">
                                    <strong style="color: var(--text-primary); font-size: 0.95rem; display: block; font-family: 'Georgia', serif;"><?= sanitize($d['nama']) ?></strong>
                                    <?php if (!empty($d['pendidikan_terakhir'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">🎓 <?= sanitize($d['pendidikan_terakhir']) ?></div>
                                    <?php endif; ?>
                                    <div class="social-icons">
                                        <?php if (!empty($d['email'])): ?>
                                            <a href="mailto:<?= sanitize($d['email']) ?>" class="social-icon-link email" title="Email">✉</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 0.85rem; color: var(--text-secondary);">
                                <?= sanitize($d['nidn'] ?: '-') ?>
                            </span>
                        </td>
                        <td><span class="badge-extreme badge-<?= $jabatan_lower ?>"><?= sanitize($d['jabatan_fungsional'] ?? '-') ?></span></td>
                        <td style="color: var(--text-secondary); font-size: 0.88rem;">
                            <?= sanitize($d['prodi_nama'] ?? '-') ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $status_class ?>"><?= $d['status'] ?? 'Aktif' ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $d['id'] ?>)" title="Detail">👁️</button>
                                <a href="dosen-form.php?id=<?= $d['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status dosen ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus dosen ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
        <div class="modal-header-dosen">
            <div class="modal-profile-row">
                <div class="dosen-avatar-lg" id="modalAvatar">?</div>
                <div class="modal-profile-info">
                    <div class="modal-profile-name" id="modalName">-</div>
                    <div class="modal-profile-nidn" id="modalNIDN">-</div>
                    <div class="modal-profile-jabatan" id="modalJabatan">-</div>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('academic', this)">🎓 Akademik</button>
                <button class="detail-tab" onclick="switchDetailTab('contact', this)">📞 Kontak</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-academic">
                <div id="detailAcademic"></div>
            </div>

            <div class="detail-tab-content" id="tab-contact">
                <div class="social-grid" id="detailContact"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const dosenData = <?= json_encode($dosen_list) ?>;

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
<?php if (!empty($jabatan_stats)): ?>
new ApexCharts(document.querySelector("#jabatanChart"), {
    series: <?= json_encode(array_values($jabatan_stats)) ?>,
    labels: <?= json_encode(array_keys($jabatan_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#f59e0b', '#3b82f6', '#8b5cf6', '#06b6d4', '#6b7280'],
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

new ApexCharts(document.querySelector("#jabatanBarChart"), {
    series: [{ data: <?= json_encode(array_values($jabatan_stats)) ?> }],
    chart: { type: 'bar', height: 220, toolbar: { show: false } },
    colors: ['#1e40af'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_keys($jabatan_stats)) ?>,
        labels: { style: { fontSize: '10px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_nonaktif ?>],
    labels: ['Aktif', 'Non-Aktif'],
    chart: { type: 'pie', height: 220, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();

<?php if (!empty($edu_stats)): ?>
new ApexCharts(document.querySelector("#eduChart"), {
    series: <?= json_encode(array_values($edu_stats)) ?>,
    labels: <?= json_encode(array_keys($edu_stats)) ?>,
    chart: { type: 'pie', height: 220, animations: { enabled: true, speed: 800 } },
    colors: ['#ec4899', '#8b5cf6', '#3b82f6', '#06b6d4', '#10b981'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' }
}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const jabatanFilter = document.getElementById('jabatanFilter');
const statusFilter = document.getElementById('statusFilter');
const prodiFilter = document.getElementById('prodiFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
[jabatanFilter, statusFilter, prodiFilter].forEach(f => f?.addEventListener('change', applyFilters));

function applyFilters() {
    const q = searchInput.value;
    const jabatan = jabatanFilter.value;
    const status = statusFilter.value;
    const prodi = prodiFilter?.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jabatan) url.searchParams.set('jabatan', jabatan); else url.searchParams.delete('jabatan');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    if (prodi) url.searchParams.set('prodi', prodi); else url.searchParams.delete('prodi');

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
    const data = dosenData.find(d => d.id == id);
    if (!data) return;

    const initials = data.nama ? data.nama.charAt(0).toUpperCase() : '?';
    const avatar = document.getElementById('modalAvatar');
    if (data.foto) {
        avatar.innerHTML = `<img src="${window.location.origin}/uploads/dosen/${data.foto}" alt="">`;
    } else {
        avatar.innerHTML = initials;
    }

    document.getElementById('modalName').textContent = data.nama || '-';
    document.getElementById('modalNIDN').textContent = data.nidn ? 'NIDN: ' + data.nidn : 'NIDN belum diisi';
    document.getElementById('modalJabatan').textContent = data.jabatan_fungsional || '-';

    // Info tab
    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">👤 Nama Lengkap</div>
            <div class="detail-item-value">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🆔 NIDN</div>
            <div class="detail-item-value" style="font-family: monospace;">${escapeHtml(data.nidn || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🎖️ Jabatan Fungsional</div>
            <div class="detail-item-value">${escapeHtml(data.jabatan_fungsional || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🎓 Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.prodi_nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📚 Pendidikan Terakhir</div>
            <div class="detail-item-value">${escapeHtml(data.pendidikan_terakhir || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value"><span class="badge-extreme ${data.status === 'Aktif' ? 'badge-aktif' : 'badge-non-aktif'}">${escapeHtml(data.status || 'Aktif')}</span></div>
        </div>
        ${data.bidang_keahlian ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">🔬 Bidang Keahlian</div>
            <div class="detail-item-value" style="font-size: 0.88rem;">${escapeHtml(data.bidang_keahlian)}</div>
        </div>
        ` : ''}
    `;

    // Academic tab
    const yearsActive = data.created_at ? Math.floor((new Date() - new Date(data.created_at)) / (1000 * 60 * 60 * 24 * 365)) : 0;
    document.getElementById('detailAcademic').innerHTML = `
        <div class="academic-profile">
            <h4>📊 Profil Akademik</h4>
            <div class="academic-stats">
                <div class="academic-stat">
                    <div class="academic-stat-value">${yearsActive}</div>
                    <div class="academic-stat-label">Tahun Mengajar</div>
                </div>
                <div class="academic-stat">
                    <div class="academic-stat-value">${data.jabatan_fungsional === 'Guru Besar' ? '⭐⭐⭐' : (data.jabatan_fungsional === 'Lektor Kepala' ? '⭐⭐' : '⭐')}</div>
                    <div class="academic-stat-label">Level Akademik</div>
                </div>
                <div class="academic-stat">
                    <div class="academic-stat-value">${data.pendidikan_terakhir?.includes('S3') || data.pendidikan_terakhir?.includes('Doktor') ? 'S3' : (data.pendidikan_terakhir?.includes('S2') ? 'S2' : 'S1')}</div>
                    <div class="academic-stat-label">Kualifikasi</div>
                </div>
            </div>
        </div>
        ${data.riwayat_pendidikan ? `
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif;">🎓 Riwayat Pendidikan</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary); white-space: pre-wrap;">${escapeHtml(data.riwayat_pendidikan)}</div>
        </div>
        ` : ''}
        ${data.penelitian ? `
        <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif;">🔬 Penelitian</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary); white-space: pre-wrap;">${escapeHtml(data.penelitian)}</div>
        </div>
        ` : ''}
    `;

    // Contact tab
    const contactItems = [];
    if (data.email) {
        contactItems.push(`<a href="mailto:${escapeHtml(data.email)}" class="social-item">
            <div class="social-item-icon" style="background: #fee2e2; color: #dc2626;">✉️</div>
            <div class="social-item-info">
                <div class="social-item-label">Email</div>
                <div class="social-item-value">${escapeHtml(data.email)}</div>
            </div>
        </a>`);
    }
    if (data.phone) {
        contactItems.push(`<a href="tel:${escapeHtml(data.phone)}" class="social-item">
            <div class="social-item-icon" style="background: #dcfce7; color: #16a34a;">📞</div>
            <div class="social-item-info">
                <div class="social-item-label">Telepon</div>
                <div class="social-item-value">${escapeHtml(data.phone)}</div>
            </div>
        </a>`);
    }
    if (data.google_scholar) {
        contactItems.push(`<a href="${escapeHtml(data.google_scholar)}" target="_blank" class="social-item">
            <div class="social-item-icon" style="background: #4285f4; color: white;">🎓</div>
            <div class="social-item-info">
                <div class="social-item-label">Google Scholar</div>
                <div class="social-item-value">${escapeHtml(data.google_scholar)}</div>
            </div>
        </a>`);
    }
    if (data.scopus) {
        contactItems.push(`<a href="${escapeHtml(data.scopus)}" target="_blank" class="social-item">
            <div class="social-item-icon" style="background: #e9711c; color: white;">📚</div>
            <div class="social-item-info">
                <div class="social-item-label">Scopus</div>
                <div class="social-item-value">${escapeHtml(data.scopus)}</div>
            </div>
        </a>`);
    }
    if (contactItems.length === 0) {
        contactItems.push(`<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
            <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">📞</div>
            <div>Belum ada kontak yang terdaftar</div>
        </div>`);
    }
    document.getElementById('detailContact').innerHTML = contactItems.join('');

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="dosen-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Data Dosen</a>
        ${data.email ? `<a href="mailto:${escapeHtml(data.email)}" class="btn-action secondary" style="justify-content: flex-start;">✉️ Kirim Email</a>` : ''}
        <button onclick="shareDosen(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Profil</button>
        <a href="dosen-form.php?duplicate=${data.id}" class="btn-action secondary" style="justify-content: flex-start;" onclick="return confirm('Duplikasi data dosen ini?')">📋 Duplikasi Data</a>
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

function shareDosen(id) {
    const data = dosenData.find(d => d.id == id);
    if (!data) return;
    const text = `👨‍🏫 ${data.nama}\n🎖️ ${data.jabatan_fungsional || '-'}\n🎓 ${data.prodi_nama || '-'}\n📚 ${data.pendidikan_terakhir || '-'}\n\nDosen FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Profil dosen disalin ke clipboard', 'success');
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
        window.location.href = 'dosen-form.php';
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

console.log('%c👨‍🏫 Kelola Dosen FKIP UNIMOF - Super Extreme', 'color: #1e40af; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Jabatan), Bulk Actions, Detail Modal, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>