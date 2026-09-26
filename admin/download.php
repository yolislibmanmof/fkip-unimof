<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT file_name FROM downloads WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if ($file) {
            $path = APP_DIR . '/assets/downloads/' . $file;
            if (file_exists($path)) @unlink($path);
        }
        $pdo->prepare("DELETE FROM downloads WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ File berhasil dihapus.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        // Ambil semua file untuk dihapus fisiknya
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT file_name FROM downloads WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $deleted_files = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if ($f) {
                $path = APP_DIR . '/assets/downloads/' . $f;
                if (file_exists($path) && @unlink($path)) $deleted_files++;
            }
        }
        $pdo->prepare("DELETE FROM downloads WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', '✅ ' . count($ids) . ' file berhasil dihapus (' . $deleted_files . ' file fisik).');
    }
    elseif ($action === 'bulk_recategorize' && !empty($ids) && !empty($_POST['new_category'])) {
        $new_cat = trim($_POST['new_category']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_cat], $ids);
        $pdo->prepare("UPDATE downloads SET kategori = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', '✅ Kategori ' . count($ids) . ' file diubah ke "' . htmlspecialchars($new_cat) . '".');
    }

    header('Location: download.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_downloads = $pdo->query("SELECT * FROM downloads ORDER BY created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_downloads)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="downloads-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Judul', 'Kategori', 'Nama File', 'Ukuran', 'Downloads', 'Tanggal']);
        foreach ($all_downloads as $r) {
            fputcsv($out, [$r['id'], $r['judul'], $r['kategori'], $r['file_name'], $r['file_size'], $r['downloads_count'], $r['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_downloads)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="downloads-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_downloads),
            'data' => $all_downloads
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$kat = $_GET['kategori'] ?? '';
$type = $_GET['type'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$view = $_GET['view'] ?? 'list';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR deskripsi LIKE ? OR file_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat !== '') { $where .= ' AND kategori = ?'; $params[] = $kat; }
if ($type !== '') {
    $ext_map = ['pdf' => 'pdf', 'doc' => 'doc', 'docx' => 'docx', 'zip' => 'zip', 'rar' => 'rar', 'xls' => 'xls', 'ppt' => 'ppt', 'img' => 'jpg'];
    if (isset($ext_map[$type])) {
        if ($type === 'doc') {
            $where .= ' AND (file_name LIKE ? OR file_name LIKE ?)';
            $params[] = '%.doc';
            $params[] = '%.docx';
        } elseif ($type === 'xls') {
            $where .= ' AND (file_name LIKE ? OR file_name LIKE ?)';
            $params[] = '%.xls';
            $params[] = '%.xlsx';
        } elseif ($type === 'ppt') {
            $where .= ' AND (file_name LIKE ? OR file_name LIKE ?)';
            $params[] = '%.ppt';
            $params[] = '%.pptx';
        } elseif ($type === 'img') {
            $where .= ' AND (file_name LIKE ? OR file_name LIKE ? OR file_name LIKE ?)';
            $params[] = '%.jpg';
            $params[] = '%.jpeg';
            $params[] = '%.png';
        } else {
            $where .= ' AND file_name LIKE ?';
            $params[] = '%.' . $ext_map[$type];
        }
    }
}

// Sort validation
$valid_sorts = ['created_at', 'judul', 'downloads_count', 'file_size', 'kategori'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$stmt = $pdo->prepare("SELECT * FROM downloads $where ORDER BY $sort_by $sort_dir");
$stmt->execute($params);
$downloads = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$total_files = count($downloads);
$total_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM downloads")->fetchColumn();

// Hitung total ukuran file fisik
$total_size_bytes = 0;
$downloads_dir = APP_DIR . '/assets/downloads';
if (is_dir($downloads_dir)) {
    foreach (glob($downloads_dir . '/*') as $file) {
        if (is_file($file)) $total_size_bytes += filesize($file);
    }
}

// Format bytes to human readable
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Kategori stats
$kat_stats = [];
$type_stats = ['pdf'=>0,'doc'=>0,'zip'=>0,'rar'=>0,'xls'=>0,'ppt'=>0,'img'=>0,'other'=>0];
$recent_downloads = [];
$top_downloads = [];
$extension_stats = [];

foreach ($downloads as $d) {
    $kategori = $d['kategori'] ?? 'Umum';
    $kat_stats[$kategori] = ($kat_stats[$kategori] ?? 0) + 1;

    $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
    $extension_stats[$ext] = ($extension_stats[$ext] ?? 0) + 1;

    if (in_array($ext, ['pdf'])) $type_stats['pdf']++;
    elseif (in_array($ext, ['doc','docx'])) $type_stats['doc']++;
    elseif (in_array($ext, ['zip'])) $type_stats['zip']++;
    elseif (in_array($ext, ['rar'])) $type_stats['rar']++;
    elseif (in_array($ext, ['xls','xlsx'])) $type_stats['xls']++;
    elseif (in_array($ext, ['ppt','pptx'])) $type_stats['ppt']++;
    elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $type_stats['img']++;
    else $type_stats['other']++;

    if (($d['downloads_count'] ?? 0) > 0) {
        $recent_downloads[] = $d;
    }
}

arsort($kat_stats);
arsort($type_stats);
arsort($extension_stats);

// Top 10 downloads
$top_downloads = array_slice($downloads, 0, 10);
usort($top_downloads, function($a, $b) {
    return ($b['downloads_count'] ?? 0) - ($a['downloads_count'] ?? 0);
});
$top_downloads = array_slice($top_downloads, 0, 5);

// Download velocity (average downloads per file)
$avg_downloads = $total_files > 0 ? round($total_downloads / $total_files, 1) : 0;

// Storage usage percentage (asumsi max 5GB)
$max_storage = 5 * 1024 * 1024 * 1024; // 5 GB
$storage_percent = $max_storage > 0 ? min(100, round(($total_size_bytes / $max_storage) * 100, 1)) : 0;

// File diversity score
$diversity_score = min(100, count($kat_stats) * 10 + count(array_filter($type_stats)) * 5);

// Trend: downloads bulan ini
$month_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM downloads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$csrf = generate_csrf_token();
$active_menu = 'download';
$page_heading = 'Kelola Download Center';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Download Center', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Cloud Storage Theme - Indigo/Cyan) ===== */
.download-hero {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
}
.download-hero::before {
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
.download-hero::after {
    content: '☁';
    position: absolute;
    bottom: -30px;
    right: 2rem;
    font-size: 12rem;
    color: rgba(255,255,255,0.05);
    pointer-events: none;
    line-height: 1;
}
.download-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.download-hero h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.01em;
}
.download-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
}
.hero-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-top: 0.25rem;
}

/* Storage Ring */
.storage-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.storage-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.storage-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.storage-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.storage-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.storage-value .score-num { font-size: 1.25rem; font-weight: 900; line-height: 1; }
.storage-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS ===== */
.dl-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.dl-stat-card {
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
.dl-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color, #6366f1), transparent);
}
.dl-stat-card:hover {
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
.dl-stat-icon {
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
.dl-stat-num {
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--stat-color, #6366f1);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.dl-stat-label {
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

/* ===== CHARTS ===== */
.charts-row {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.charts-row-3 {
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

/* Top Downloads Widget */
.top-downloads-widget {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.top-downloads-widget h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.top-item {
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
.top-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-item:last-child { margin-bottom: 0; }
.top-rank {
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
.top-item:nth-child(1) .top-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-item:nth-child(2) .top-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-item:nth-child(3) .top-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.top-info { flex: 1; min-width: 0; }
.top-name {
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.15rem;
}
.top-category {
    font-size: 0.7rem;
    color: var(--text-muted);
}
.top-count {
    font-weight: 800;
    color: var(--primary);
    font-size: 0.88rem;
    flex-shrink: 0;
    min-width: 40px;
    text-align: right;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.top-count small { font-size: 0.65rem; color: var(--text-muted); font-weight: 600; }

/* Extension List */
.extension-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.extension-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    font-size: 0.82rem;
    transition: all 0.2s;
}
.extension-item:hover { background: var(--bg-tertiary); }
.ext-badge {
    padding: 0.2rem 0.5rem;
    border-radius: 5px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    font-family: monospace;
    min-width: 45px;
    text-align: center;
}
.ext-pdf { background: #fee2e2; color: #dc2626; }
.ext-doc { background: #dbeafe; color: #2563eb; }
.ext-zip { background: #fef3c7; color: #d97706; }
.ext-rar { background: #e0e7ff; color: #4338ca; }
.ext-xls { background: #dcfce7; color: #16a34a; }
.ext-ppt { background: #fed7aa; color: #c2410c; }
.ext-img { background: #fce7f3; color: #db2777; }
.ext-other { background: #f3f4f6; color: #4b5563; }
.ext-name { flex: 1; font-weight: 600; }
.ext-count { font-weight: 800; color: var(--primary); }

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
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    border-color: #4f46e5;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
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
.dl-toolbar {
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
.dl-search { flex: 1; min-width: 250px; position: relative; }
.dl-search input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: var(--bg-secondary);
}
.dl-search input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
    background: var(--bg-primary);
}
.dl-search .search-icon {
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
.dl-filter {
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
.dl-filter:focus { outline: none; border-color: #6366f1; }

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
.view-btn.active { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; }
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
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99,102,241,0.4); }
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
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(99,102,241,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #4f46e5; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #4f46e5; }
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
.bulk-category-select option { background: #4f46e5; color: white; }

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

.file-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.file-icon-sm {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.file-info { flex: 1; min-width: 0; }
.file-name {
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-primary);
}
.file-filename {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-family: monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
.badge-kategori { background: #dbeafe; color: #1e40af; }

.downloads-cell {
    text-align: center;
}
.downloads-cell strong {
    display: block;
    font-size: 1.1rem;
    color: var(--primary);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.downloads-cell small {
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.size-cell {
    font-family: monospace;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-secondary);
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
.btn-icon.download { background: #dcfce7; color: #16a34a; }
.btn-icon.download:hover { background: #16a34a; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

/* ===== GRID VIEW ===== */
.dl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.dl-grid-item {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    transition: all 0.3s;
    position: relative;
    cursor: pointer;
}
.dl-grid-item::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--card-color, #6366f1);
}
.dl-grid-item:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-color, #6366f1);
}
.dl-grid-item.selected {
    border-color: #6366f1;
    background: rgba(99,102,241,0.02);
}
.dl-grid-check {
    position: absolute;
    top: 1rem;
    right: 1rem;
    z-index: 2;
}
.dl-grid-check input { width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1; }

.file-icon-lg {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
}
.file-pdf { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
.file-doc { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
.file-zip { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
.file-rar { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4338ca; }
.file-xls { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
.file-ppt { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #c2410c; }
.file-img { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
.file-other { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; }

.dl-grid-item h4 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.dl-grid-item .file-filename {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-family: monospace;
    margin-bottom: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dl-grid-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}
.dl-grid-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    background: var(--bg-secondary);
    border-radius: 999px;
}
.dl-grid-actions {
    display: flex;
    gap: 0.5rem;
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}
.dl-grid-actions .act-btn {
    flex: 1;
    padding: 0.6rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text-secondary);
    font-family: inherit;
}
.dl-grid-actions .act-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}
.dl-grid-actions .act-btn.danger:hover { background: #dc2626; border-color: #dc2626; }

/* ===== COMPACT LIST VIEW ===== */
.dl-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
}
.dl-list-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    transition: all 0.2s;
    cursor: pointer;
}
.dl-list-item:hover {
    background: var(--bg-secondary);
    border-color: var(--primary);
    transform: translateX(3px);
}
.dl-list-item.selected {
    background: rgba(99,102,241,0.05);
    border-color: #6366f1;
}
.dl-list-item .file-icon-sm {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.dl-list-info { flex: 1; min-width: 0; }
.dl-list-info h4 {
    font-size: 0.92rem;
    font-weight: 700;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dl-list-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    flex-wrap: wrap;
    align-items: center;
}
.dl-list-meta span { display: flex; align-items: center; gap: 0.25rem; }
.dl-list-stats {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-shrink: 0;
}
.dl-list-stat {
    text-align: center;
    padding: 0.25rem 0.5rem;
    min-width: 60px;
}
.dl-list-stat-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.dl-list-stat-label {
    font-size: 0.65rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.15rem;
}
.dl-list-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }

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
.empty-premium {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
.empty-premium h3 { font-size: 1.35rem; margin-bottom: 0.5rem; }
.empty-premium p { color: var(--text-muted); max-width: 500px; margin: 0.5rem auto 1.5rem; }

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
.modal-header-file {
    padding: 2rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-file::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
    border-radius: 50%;
}
.modal-file-row {
    display: flex;
    gap: 1.25rem;
    align-items: center;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.modal-file-icon {
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
.modal-file-info { flex: 1; min-width: 200px; }
.modal-file-name { font-size: 1.35rem; font-weight: 800; margin-bottom: 0.25rem; line-height: 1.3; }
.modal-file-filename { font-size: 0.78rem; opacity: 0.95; font-family: monospace; margin-bottom: 0.35rem; word-break: break-all; }
.modal-file-badge {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    background: rgba(255,255,255,0.2);
    border-radius: 999px;
    font-size: 0.75rem;
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
.detail-item-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }

/* Statistics Tab */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.stat-box {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    transition: all 0.2s;
}
.stat-box:hover { background: var(--bg-tertiary); transform: translateY(-2px); }
.stat-box-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.stat-box-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

/* Popularity meter */
.popularity-meter {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
}
.popularity-meter h4 {
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.popularity-bar {
    height: 10px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}
.popularity-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    border-radius: 999px;
    transition: width 1s ease;
}
.popularity-label {
    font-size: 0.78rem;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
}

/* File preview area */
.file-preview-area {
    padding: 1.5rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    margin-bottom: 1rem;
}
.file-preview-icon {
    font-size: 4rem;
    margin-bottom: 0.75rem;
}
.file-preview-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

/* Actions list */
.modal-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Hash display */
.file-hash {
    padding: 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-family: monospace;
    font-size: 0.75rem;
    color: var(--text-secondary);
    word-break: break-all;
    margin-top: 1rem;
}
.file-hash-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

/* Search highlight */
mark {
    background: #fef08a;
    padding: 0 0.15rem;
    border-radius: 2px;
    font-weight: 700;
}

@media (max-width: 1024px) {
    .charts-row, .charts-row-3 { grid-template-columns: 1fr; }
    .dl-stats { grid-template-columns: repeat(2, 1fr); }
    .detail-grid, .stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .download-hero-content { flex-direction: column; text-align: center; }
    .storage-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .dl-toolbar { flex-direction: column; align-items: stretch; }
    .dl-search { min-width: 100%; }
    .dl-filter, .view-toggle, .btn-action { width: 100%; justify-content: center; }
    .dl-grid { grid-template-columns: 1fr; padding: 1rem; }
    .table-wrapper { overflow-x: auto; }
    table.extreme { min-width: 900px; }
}
@media (max-width: 640px) {
    .dl-stats { grid-template-columns: 1fr; }
    .dl-stat-num { font-size: 1.85rem; }
    .bulk-bar { flex-direction: column; align-items: stretch; }
    .bulk-actions { flex-direction: column; }
    .bulk-btn, .bulk-category-select { width: 100%; }
    .dl-list-stats { flex-direction: column; gap: 0.25rem; }
}

/* Print styles */
@media print {
    .dl-toolbar, .bulk-bar, .action-buttons, .charts-row, .charts-row-3, .dl-stats, .download-hero, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="download-hero" data-aos="fade-down">
    <div class="download-hero-content">
        <div>
            <h2>📥 Download Center</h2>
            <p>Kelola repository file dan dokumen FKIP UNIMOF. Pantau statistik unduhan, distribusi tipe file, dan popularitas dokumen secara real-time.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $total_files ?></span>
                    <span class="hero-stat-label">Total File</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= number_format($total_downloads) ?></span>
                    <span class="hero-stat-label">Downloads</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= formatBytes($total_size_bytes) ?></span>
                    <span class="hero-stat-label">Storage</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= count($kat_stats) ?></span>
                    <span class="hero-stat-label">Kategori</span>
                </div>
            </div>
        </div>
        <div class="storage-ring" title="Storage Usage (dari 5GB)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $storage_percent ?>, 100"/>
            </svg>
            <div class="storage-value">
                <div class="score-num"><?= $storage_percent ?>%</div>
                <div class="score-label">Storage</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="dl-stats" data-aos="fade-up">
    <div class="dl-stat-card" style="--stat-color: #6366f1;">
        <div class="stat-header">
            <div class="dl-stat-icon">📁</div>
        </div>
        <div class="dl-stat-num count-up" data-target="<?= $total_files ?>">0</div>
        <div class="dl-stat-label">Total File</div>
        <div class="stat-trend neutral">📚 Repository</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="dl-stat-icon">⬇️</div>
        </div>
        <div class="dl-stat-num count-up" data-target="<?= $total_downloads ?>">0</div>
        <div class="dl-stat-label">Total Download</div>
        <div class="stat-trend up">📊 Avg <?= $avg_downloads ?>/file</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #06b6d4;">
        <div class="stat-header">
            <div class="dl-stat-icon">💾</div>
        </div>
        <div class="dl-stat-num" style="font-size: 1.5rem;"><?= formatBytes($total_size_bytes) ?></div>
        <div class="dl-stat-label">Storage Used</div>
        <div class="stat-trend <?= $storage_percent > 80 ? 'down' : 'up' ?>">
            <?= $storage_percent > 80 ? '⚠️ Hampir penuh' : '✅ ' . $storage_percent . '% dari 5GB' ?>
        </div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="dl-stat-icon">📊</div>
        </div>
        <div class="dl-stat-num count-up" data-target="<?= count($kat_stats) ?>">0</div>
        <div class="dl-stat-label">Kategori Aktif</div>
        <div class="stat-trend neutral">📂 Terorganisir</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="dl-stat-icon">🔥</div>
        </div>
        <div class="dl-stat-num count-up" data-target="<?= count($recent_downloads) ?>">0</div>
        <div class="dl-stat-label">File Populer</div>
        <div class="stat-trend up">📈 Aktif diunduh</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #ec4899;">
        <div class="stat-header">
            <div class="dl-stat-icon">📈</div>
        </div>
        <div class="dl-stat-num count-up" data-target="<?= $month_downloads ?>">0</div>
        <div class="dl-stat-label">Download Bulan Ini</div>
        <div class="stat-trend up">📊 30 hari terakhir</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($kat_stats) || !empty($type_stats)): ?>
<div class="charts-row" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Kategori</h3>
        <div id="chartKategori"></div>
    </div>
    <div class="chart-card">
        <h3>🔥 Top 5 File Terpopuler</h3>
        <?php if (empty($top_downloads)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">📥</div>
                <div>Belum ada file yang diunduh</div>
            </div>
        <?php else: ?>
            <div>
                <?php
                $max_dl = max(array_column($top_downloads, 'downloads_count') ?: [1]);
                foreach ($top_downloads as $i => $d):
                    $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                    $icons = ['pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'zip' => '📦', 'rar' => '🗜️', 'xls' => '📗', 'xlsx' => '📗', 'ppt' => '📙', 'pptx' => '📙'];
                    $icon = $icons[$ext] ?? '📄';
                ?>
                <div class="top-item" onclick="showDetail(<?= $d['id'] ?>)">
                    <div class="top-rank"><?= $i + 1 ?></div>
                    <div class="top-icon file-icon-sm file-<?= in_array($ext, ['pdf']) ? 'pdf' : (in_array($ext, ['doc','docx']) ? 'doc' : (in_array($ext, ['zip']) ? 'zip' : (in_array($ext, ['rar']) ? 'rar' : (in_array($ext, ['xls','xlsx']) ? 'xls' : (in_array($ext, ['ppt','pptx']) ? 'ppt' : 'other'))))) ?>"><?= $icon ?></div>
                    <div class="top-info">
                        <div class="top-name"><?= sanitize($d['judul']) ?></div>
                        <div class="top-category"><?= sanitize($d['kategori'] ?? 'Umum') ?> • <?= sanitize($d['file_size'] ?? '-') ?></div>
                    </div>
                    <div class="top-count">
                        <?= number_format($d['downloads_count']) ?>
                        <small>⬇️</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="charts-row-3" data-aos="fade-up">
    <div class="chart-card">
        <h3>📄 Distribusi Tipe File</h3>
        <div id="chartTipe"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Ekstensi Terpopuler</h3>
        <?php if (empty($extension_stats)): ?>
            <div style="text-align: center; padding: 1rem; color: var(--text-muted);">
                <div>Belum ada data</div>
            </div>
        <?php else: ?>
            <div class="extension-list">
                <?php foreach (array_slice($extension_stats, 0, 6, true) as $ext => $count):
                    $extClass = 'ext-other';
                    if (in_array($ext, ['pdf'])) $extClass = 'ext-pdf';
                    elseif (in_array($ext, ['doc','docx'])) $extClass = 'ext-doc';
                    elseif (in_array($ext, ['zip'])) $extClass = 'ext-zip';
                    elseif (in_array($ext, ['rar'])) $extClass = 'ext-rar';
                    elseif (in_array($ext, ['xls','xlsx'])) $extClass = 'ext-xls';
                    elseif (in_array($ext, ['ppt','pptx'])) $extClass = 'ext-ppt';
                    elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $extClass = 'ext-img';
                ?>
                <div class="extension-item">
                    <span class="ext-badge <?= $extClass ?>"><?= strtoupper($ext ?: '???') ?></span>
                    <span class="ext-name"><?= $count ?> file</span>
                    <span class="ext-count"><?= $total_files > 0 ? round(($count / $total_files) * 100, 1) : 0 ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <h3>📊 Statistik Ringkas</h3>
        <div class="extension-list">
            <div class="extension-item">
                <span class="ext-badge" style="background: #dbeafe; color: #1e40af;">📁</span>
                <span class="ext-name">Rata-rata Download</span>
                <span class="ext-count"><?= $avg_downloads ?>/file</span>
            </div>
            <div class="extension-item">
                <span class="ext-badge" style="background: #dcfce7; color: #166534;">💾</span>
                <span class="ext-name">Avg File Size</span>
                <span class="ext-count"><?= $total_files > 0 ? formatBytes($total_size_bytes / $total_files) : '0 B' ?></span>
            </div>
            <div class="extension-item">
                <span class="ext-badge" style="background: #fef3c7; color: #92400e;">🎯</span>
                <span class="ext-name">Diversity Score</span>
                <span class="ext-count"><?= $diversity_score ?>/100</span>
            </div>
            <div class="extension-item">
                <span class="ext-badge" style="background: #fce7f3; color: #be185d;">📈</span>
                <span class="ext-name">Files with Downloads</span>
                <span class="ext-count"><?= count($recent_downloads) ?>/<?= $total_files ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="download.php" class="pill <?= empty($kat) && empty($type) ? 'active' : '' ?>">
        📁 Semua <span class="pill-count"><?= $total_files ?></span>
    </a>
    <?php foreach (array_slice($kat_stats, 0, 5, true) as $kategori => $count): ?>
        <a href="download.php?kategori=<?= urlencode($kategori) ?>" class="pill <?= $kat === $kategori ? 'active' : '' ?>">
            📂 <?= sanitize($kategori) ?> <span class="pill-count"><?= $count ?></span>
        </a>
    <?php endforeach; ?>
    <div style="flex: 1;"></div>
    <a href="download.php?type=pdf" class="pill <?= $type === 'pdf' ? 'active' : '' ?>">📕 PDF</a>
    <a href="download.php?type=doc" class="pill <?= $type === 'doc' ? 'active' : '' ?>">📘 DOC</a>
    <a href="download.php?type=zip" class="pill <?= $type === 'zip' ? 'active' : '' ?>">📦 ZIP</a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="dl-toolbar" data-aos="fade-up">
    <div class="dl-search">
        <span class="search-icon">🔍</span>
        <input type="text" id="dlSearch" placeholder="Cari judul, deskripsi, atau nama file..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="dl-filter" id="katFilter">
        <option value="">📂 Semua Kategori</option>
        <?php foreach (array_keys($kat_stats) as $k): ?>
            <option value="<?= sanitize($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= sanitize($k) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="dl-filter" id="typeFilter">
        <option value="">📄 Semua Tipe</option>
        <option value="pdf" <?= $type === 'pdf' ? 'selected' : '' ?>>📕 PDF</option>
        <option value="doc" <?= $type === 'doc' ? 'selected' : '' ?>>📘 DOC/DOCX</option>
        <option value="xls" <?= $type === 'xls' ? 'selected' : '' ?>>📗 XLS/XLSX</option>
        <option value="ppt" <?= $type === 'ppt' ? 'selected' : '' ?>>📙 PPT/PPTX</option>
        <option value="zip" <?= $type === 'zip' ? 'selected' : '' ?>>📦 ZIP</option>
        <option value="rar" <?= $type === 'rar' ? 'selected' : '' ?>>🗜️ RAR</option>
        <option value="img" <?= $type === 'img' ? 'selected' : '' ?>>🖼️ Gambar</option>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 Tabel</button>
        <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Kartu</button>
        <button class="view-btn <?= $view === 'compact' ? 'active' : '' ?>" onclick="switchView('compact')">📝 Kompak</button>
    </div>

    <a href="download-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Upload File</span>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak daftar file</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== SORT CONTROLS ===== -->
<?php if (!empty($downloads)): ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 0 0.5rem; flex-wrap: wrap; gap: 0.5rem;" data-aos="fade-up">
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
        <span>📊</span>
        <span><strong style="color: var(--text-primary);"><?= count($downloads) ?></strong> file ditampilkan</span>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
        <span>Sort:</span>
        <select onchange="sortFiles(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
            <option value="created_at-desc" <?= $sort_by === 'created_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
            <option value="created_at-asc" <?= $sort_by === 'created_at' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Terlama</option>
            <option value="downloads_count-desc" <?= $sort_by === 'downloads_count' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Most Downloaded</option>
            <option value="judul-asc" <?= $sort_by === 'judul' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Judul A-Z</option>
            <option value="judul-desc" <?= $sort_by === 'judul' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Judul Z-A</option>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>file dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <input type="text" name="new_category" class="bulk-category-select" placeholder="Kategori baru..." style="min-width: 150px;">
            <button type="submit" name="action" value="bulk_recategorize" class="bulk-btn primary" onclick="return confirm('Ubah kategori file terpilih?')">📂 Recategorize</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('HAPUS PERMANEN file terpilih beserta file fisiknya? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($downloads)): ?>
    <div class="empty-premium" data-aos="fade-up">
        <div class="empty-icon-lg">📂</div>
        <h3><?= ($q || $kat || $type) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum Ada File' ?></h3>
        <p><?= ($q || $kat || $type) ? 'Coba ubah kata kunci atau filter untuk menemukan file.' : 'Mulai upload dokumen pertama Anda untuk dibagikan ke pengunjung website.' ?></p>
        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($q || $kat || $type): ?>
                <a href="download.php" class="btn-action secondary">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="download-form.php" class="btn-action primary">➕ Upload File Pertama</a>
        </div>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>📁 Repository File</h2>
                <p>Kelola semua file dan dokumen yang dapat diunduh</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($downloads) ?> file
            </span>
        </div>

        <?php if ($view === 'grid'): ?>
            <!-- GRID VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
                    <span>Pilih Semua (<?= count($downloads) ?>)</span>
                </label>
            </div>
            <div class="dl-grid">
                <?php
                $card_colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
                foreach ($downloads as $i => $d):
                    $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                    $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip']) ? 'file-zip' : (in_array($ext, ['rar']) ? 'file-rar' : (in_array($ext, ['xls','xlsx']) ? 'file-xls' : (in_array($ext, ['ppt','pptx']) ? 'file-ppt' : (in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'file-img' : 'file-other'))))));
                    $icons = ['pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'zip' => '📦', 'rar' => '🗜️', 'xls' => '📗', 'xlsx' => '📗', 'ppt' => '📙', 'pptx' => '📙', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️'];
                    $file_icon = $icons[$ext] ?? '📄';
                    $color = $card_colors[$i % count($card_colors)];
                ?>
                <div class="dl-grid-item" style="--card-color: <?= $color ?>;" data-id="<?= $d['id'] ?>" onclick="showDetail(<?= $d['id'] ?>)">
                    <div class="dl-grid-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="dl-checkbox" value="<?= $d['id'] ?>" onchange="updateBulk()">
                    </div>
                    <div class="file-icon-lg <?= $file_class ?>"><?= $file_icon ?></div>
                    <h4><?= sanitize($d['judul']) ?></h4>
                    <div class="file-filename" title="<?= sanitize($d['file_name']) ?>"><?= sanitize($d['file_name']) ?></div>
                    <div class="dl-grid-meta">
                        <span>📂 <?= sanitize($d['kategori'] ?? 'Umum') ?></span>
                        <span>💾 <?= sanitize($d['file_size'] ?? '-') ?></span>
                        <span>⬇️ <?= number_format($d['downloads_count'] ?? 0) ?></span>
                    </div>
                    <div class="dl-grid-actions" onclick="event.stopPropagation()">
                        <a href="download-form.php?id=<?= $d['id'] ?>" class="act-btn" title="Edit">✏️ Edit</a>
                        <a href="<?= base_url('assets/downloads/' . urlencode($d['file_name'])) ?>" class="act-btn" title="Download" download>⬇️ Download</a>
                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Hapus file ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="act-btn danger" title="Hapus" type="submit">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view === 'compact'): ?>
            <!-- COMPACT LIST VIEW -->
            <div style="padding: 1rem 1.5rem 0;">
                <label class="select-all-wrapper">
                    <input type="checkbox" id="selectAllCompact" onchange="toggleSelectAll('compact')">
                    <span>Pilih Semua (<?= count($downloads) ?>)</span>
                </label>
            </div>
            <div class="dl-list">
                <?php foreach ($downloads as $d):
                    $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                    $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip']) ? 'file-zip' : (in_array($ext, ['rar']) ? 'file-rar' : (in_array($ext, ['xls','xlsx']) ? 'file-xls' : (in_array($ext, ['ppt','pptx']) ? 'file-ppt' : (in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'file-img' : 'file-other'))))));
                    $icons = ['pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'zip' => '📦', 'rar' => '🗜️', 'xls' => '📗', 'xlsx' => '📗', 'ppt' => '📙', 'pptx' => '📙', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️'];
                    $file_icon = $icons[$ext] ?? '📄';
                ?>
                <div class="dl-list-item" data-id="<?= $d['id'] ?>" onclick="showDetail(<?= $d['id'] ?>)">
                    <input type="checkbox" class="dl-checkbox" value="<?= $d['id'] ?>" onchange="updateBulk()" onclick="event.stopPropagation()" style="width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1;">
                    <div class="file-icon-sm <?= $file_class ?>"><?= $file_icon ?></div>
                    <div class="dl-list-info">
                        <h4><?= sanitize($d['judul']) ?></h4>
                        <div class="dl-list-meta">
                            <span class="badge-extreme badge-kategori"><?= sanitize($d['kategori'] ?? 'Umum') ?></span>
                            <span title="<?= sanitize($d['file_name']) ?>" style="font-family: monospace; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= sanitize($d['file_name']) ?></span>
                        </div>
                    </div>
                    <div class="dl-list-stats">
                        <div class="dl-list-stat">
                            <div class="dl-list-stat-value"><?= number_format($d['downloads_count'] ?? 0) ?></div>
                            <div class="dl-list-stat-label">Downloads</div>
                        </div>
                        <div class="dl-list-stat">
                            <div class="dl-list-stat-value" style="font-size: 0.85rem;"><?= sanitize($d['file_size'] ?? '-') ?></div>
                            <div class="dl-list-stat-label">Size</div>
                        </div>
                    </div>
                    <div class="dl-list-actions" onclick="event.stopPropagation()">
                        <a href="download-form.php?id=<?= $d['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        <a href="<?= base_url('assets/downloads/' . urlencode($d['file_name'])) ?>" class="btn-icon download" title="Download" download>⬇️</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus file ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn-icon delete" title="Hapus" type="submit">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- TABLE VIEW -->
            <div class="table-wrapper">
                <table class="extreme" id="downloadTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll('table')">
                            </th>
                            <th data-sortable="judul">File <span class="sort-icon">↕</span></th>
                            <th data-sortable="kategori">Kategori <span class="sort-icon">↕</span></th>
                            <th data-sortable="file_size">Ukuran <span class="sort-icon">↕</span></th>
                            <th data-sortable="downloads_count">Downloads <span class="sort-icon">↕</span></th>
                            <th data-sortable="created_at">Tanggal <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($downloads as $d):
                        $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                        $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip']) ? 'file-zip' : (in_array($ext, ['rar']) ? 'file-rar' : (in_array($ext, ['xls','xlsx']) ? 'file-xls' : (in_array($ext, ['ppt','pptx']) ? 'file-ppt' : (in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'file-img' : 'file-other'))))));
                        $icons = ['pdf' => '📕', 'doc' => '📘', 'docx' => '📘', 'zip' => '📦', 'rar' => '🗜️', 'xls' => '📗', 'xlsx' => '📗', 'ppt' => '📙', 'pptx' => '📙', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️'];
                        $file_icon = $icons[$ext] ?? '📄';
                    ?>
                    <tr data-id="<?= $d['id'] ?>" data-search="<?= strtolower(sanitize(($d['judul'] ?? '') . ' ' . ($d['file_name'] ?? '') . ' ' . ($d['deskripsi'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="dl-checkbox row-checkbox" value="<?= $d['id'] ?>" onchange="updateBulk()">
                        </td>
                        <td>
                            <div class="file-cell">
                                <div class="file-icon-sm <?= $file_class ?>"><?= $file_icon ?></div>
                                <div class="file-info">
                                    <div class="file-name"><?= sanitize($d['judul']) ?></div>
                                    <div class="file-filename" title="<?= sanitize($d['file_name']) ?>"><?= sanitize($d['file_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-extreme badge-kategori"><?= sanitize($d['kategori'] ?? 'Umum') ?></span></td>
                        <td><span class="size-cell"><?= sanitize($d['file_size'] ?? '-') ?></span></td>
                        <td>
                            <div class="downloads-cell">
                                <strong><?= number_format($d['downloads_count'] ?? 0) ?></strong>
                                <small>downloads</small>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <?= date('d M Y', strtotime($d['created_at'])) ?>
                                <small><?= date('H:i', strtotime($d['created_at'])) ?> WIB</small>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $d['id'] ?>)" title="Detail">👁️</button>
                                <a href="download-form.php?id=<?= $d['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <a href="<?= base_url('assets/downloads/' . urlencode($d['file_name'])) ?>" class="btn-icon download" title="Download" download>⬇️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus file ini beserta file fisiknya?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
        <div class="modal-header-file">
            <div class="modal-file-row">
                <div class="modal-file-icon" id="modalIcon">📄</div>
                <div class="modal-file-info">
                    <div class="modal-file-name" id="modalName">-</div>
                    <div class="modal-file-filename" id="modalFilename">-</div>
                    <div class="modal-file-badge" id="modalCategory">-</div>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('stats', this)">📊 Statistik</button>
                <button class="detail-tab" onclick="switchDetailTab('preview', this)">👁️ Preview</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-stats">
                <div id="detailStats"></div>
            </div>

            <div class="detail-tab-content" id="tab-preview">
                <div id="detailPreview"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div class="modal-actions-list" id="detailActions"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const downloadData = <?= json_encode($downloads) ?>;
const maxDownloads = <?= $max_dl ?? 1 ?>;

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
<?php if (!empty($kat_stats)): ?>
new ApexCharts(document.querySelector("#chartKategori"), {
    series: <?= json_encode(array_values($kat_stats)) ?>,
    labels: <?= json_encode(array_keys($kat_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#dc2626'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total File',
                        formatter: () => <?= $total_files ?>
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

<?php if (!empty($type_stats)): ?>
new ApexCharts(document.querySelector("#chartTipe"), {
    series: [<?= $type_stats['pdf'] ?>, <?= $type_stats['doc'] ?>, <?= $type_stats['zip'] ?>, <?= $type_stats['rar'] ?>, <?= $type_stats['xls'] ?>, <?= $type_stats['ppt'] ?>, <?= $type_stats['img'] ?>, <?= $type_stats['other'] ?>],
    labels: ['PDF', 'DOC', 'ZIP', 'RAR', 'XLS', 'PPT', 'Image', 'Other'],
    chart: { type: 'pie', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#dc2626', '#2563eb', '#d97706', '#4338ca', '#16a34a', '#c2410c', '#db2777', '#6b7280'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' },
    stroke: { show: true, colors: ['var(--bg-primary)'], width: 3 }
}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
const dlSearch = document.getElementById('dlSearch');
const katFilter = document.getElementById('katFilter');
const typeFilter = document.getElementById('typeFilter');

let searchTimeout;
dlSearch?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
katFilter?.addEventListener('change', applyFilters);
typeFilter?.addEventListener('change', applyFilters);

function applyFilters() {
    const q = dlSearch.value;
    const kat = katFilter.value;
    const type = typeFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (kat) url.searchParams.set('kategori', kat); else url.searchParams.delete('kategori');
    if (type) url.searchParams.set('type', type); else url.searchParams.delete('type');

    window.location = url;
}

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function sortFiles(value) {
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
    const masterId = type === 'grid' ? 'selectAllGrid' : (type === 'compact' ? 'selectAllCompact' : 'selectAll');
    const master = document.getElementById(masterId);
    document.querySelectorAll('.dl-checkbox').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulk();
}

function updateBulk() {
    const checked = document.querySelectorAll('.dl-checkbox:checked');
    const count = checked.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);

    document.querySelectorAll('[data-id]').forEach(el => {
        const cb = el.querySelector('.dl-checkbox');
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

    // Update select all checkboxes
    const allChecks = document.querySelectorAll('.dl-checkbox');
    const checkedAll = allChecks.length > 0 && count === allChecks.length;
    ['selectAll', 'selectAllGrid', 'selectAllCompact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = checkedAll;
    });
}

function clearSelection() {
    document.querySelectorAll('.dl-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
    ['selectAll', 'selectAllGrid', 'selectAllCompact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = downloadData.find(d => d.id == id);
    if (!data) return;

    const ext = (data.file_name || '').split('.').pop().toLowerCase();
    const icons = {
        'pdf': '📕', 'doc': '📘', 'docx': '📘',
        'zip': '📦', 'rar': '🗜️',
        'xls': '📗', 'xlsx': '📗',
        'ppt': '📙', 'pptx': '📙',
        'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️'
    };
    const icon = icons[ext] || '📄';

    document.getElementById('modalIcon').textContent = icon;
    document.getElementById('modalName').textContent = data.judul || '-';
    document.getElementById('modalFilename').textContent = data.file_name || '-';
    document.getElementById('modalCategory').textContent = '📂 ' + (data.kategori || 'Umum');

    // Info tab
    const created = data.created_at ? new Date(data.created_at) : null;
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">📝 Judul File</div>
            <div class="detail-item-value">${escapeHtml(data.judul || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📂 Kategori</div>
            <div class="detail-item-value">${escapeHtml(data.kategori || 'Umum')}</div>
        </div>
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📎 Nama File</div>
            <div class="detail-item-value" style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(data.file_name || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">💾 Ukuran</div>
            <div class="detail-item-value">${escapeHtml(data.file_size || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🔖 Ekstensi</div>
            <div class="detail-item-value"><span class="ext-badge ext-${getExtClass(ext)}">${ext.toUpperCase() || '-'}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">⬇️ Total Download</div>
            <div class="detail-item-value" style="color: var(--primary); font-weight: 800;">${(data.downloads_count || 0).toLocaleString('id-ID')}x</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📅 Tanggal Upload</div>
            <div class="detail-item-value">${created ? created.getDate() + ' ' + months[created.getMonth()] + ' ' + created.getFullYear() : '-'}</div>
        </div>
        ${data.deskripsi ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📄 Deskripsi</div>
            <div class="detail-item-value" style="font-size: 0.88rem; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(data.deskripsi)}</div>
        </div>
        ` : ''}
    `;

    // Stats tab
    const popularityPercent = maxDownloads > 0 ? Math.min(100, Math.round(((data.downloads_count || 0) / maxDownloads) * 100)) : 0;
    const daysSinceUpload = created ? Math.floor((new Date() - created) / (1000 * 60 * 60 * 24)) : 0;
    const downloadsPerDay = daysSinceUpload > 0 ? ((data.downloads_count || 0) / daysSinceUpload).toFixed(2) : (data.downloads_count || 0);

    document.getElementById('detailStats').innerHTML = `
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-box-value">${(data.downloads_count || 0).toLocaleString('id-ID')}</div>
                <div class="stat-box-label">Total Download</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-value">${downloadsPerDay}</div>
                <div class="stat-box-label">Downloads/Hari</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-value">${daysSinceUpload}</div>
                <div class="stat-box-label">Hari Aktif</div>
            </div>
        </div>

        <div class="popularity-meter">
            <h4>📈 Tingkat Popularitas</h4>
            <div class="popularity-bar">
                <div class="popularity-bar-fill" style="width: ${popularityPercent}%"></div>
            </div>
            <div class="popularity-label">
                <span>${popularityPercent < 30 ? '🔵 Rendah' : (popularityPercent < 70 ? '🟡 Sedang' : '🔥 Tinggi')}</span>
                <span>${popularityPercent}% dari file terpopuler</span>
            </div>
        </div>

        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;">📊 Analisis</h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary);">
                ${popularityPercent >= 70 ? '🔥 File ini sangat populer! Pertimbangkan untuk membuat konten serupa.' : ''}
                ${popularityPercent >= 30 && popularityPercent < 70 ? '📊 File ini memiliki performa download yang baik.' : ''}
                ${popularityPercent < 30 ? '💡 File ini masih kurang populer. Pertimbangkan promosi lebih lanjut.' : ''}
                ${daysSinceUpload < 7 ? '<br>🆕 File baru diupload, berikan waktu untuk mendapatkan traction.' : ''}
                ${daysSinceUpload > 365 ? '<br>📅 File lama, pertimbangkan untuk memperbarui jika perlu.' : ''}
            </div>
        </div>
    `;

    // Preview tab
    const downloadUrl = '<?= base_url('assets/downloads/') ?>' + encodeURIComponent(data.file_name || '');
    const isPdf = ext === 'pdf';
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

    let previewHtml = `
        <div class="file-preview-area">
            <div class="file-preview-icon">${icon}</div>
            <div class="file-preview-info">
                <strong>${escapeHtml(data.file_name || '-')}</strong><br>
                ${escapeHtml(data.file_size || '-')} • ${ext.toUpperCase()} File
            </div>
            <a href="${downloadUrl}" class="btn-action primary" download style="justify-content: center;">
                ⬇️ Download File
            </a>
        </div>
    `;

    if (isPdf) {
        previewHtml += `
            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); margin-top: 1rem;">
                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;">📄 PDF Preview</h4>
                <iframe src="${downloadUrl}" style="width: 100%; height: 400px; border: 1px solid var(--border); border-radius: 8px;"></iframe>
            </div>
        `;
    } else if (isImage) {
        previewHtml += `
            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); margin-top: 1rem; text-align: center;">
                <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;">🖼️ Image Preview</h4>
                <img src="${downloadUrl}" alt="" style="max-width: 100%; max-height: 400px; border-radius: 8px;">
            </div>
        `;
    }

    document.getElementById('detailPreview').innerHTML = previewHtml;

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="${downloadUrl}" class="btn-action primary" style="justify-content: flex-start;" download>⬇️ Download File</a>
        <a href="download-form.php?id=${data.id}" class="btn-action secondary" style="justify-content: flex-start;">✏️ Edit Informasi File</a>
        <button onclick="copyDownloadLink(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Copy Link Download</button>
        <button onclick="shareFile(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">📤 Bagikan File</button>
        <button onclick="resetDownloadCount(${data.id})" class="btn-action secondary" style="justify-content: flex-start;" title="Reset counter download">🔄 Reset Download Counter</button>
    `;

    document.getElementById('detailModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function getExtClass(ext) {
    if (['pdf'].includes(ext)) return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'doc';
    if (['zip'].includes(ext)) return 'zip';
    if (['rar'].includes(ext)) return 'rar';
    if (['xls', 'xlsx'].includes(ext)) return 'xls';
    if (['ppt', 'pptx'].includes(ext)) return 'ppt';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'img';
    return 'other';
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

function copyDownloadLink(id) {
    const data = downloadData.find(d => d.id == id);
    if (!data) return;
    const url = window.location.origin + '/assets/downloads/' + encodeURIComponent(data.file_name);
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url);
        showToast('Disalin', 'Link download disalin ke clipboard', 'success');
    }
}

function shareFile(id) {
    const data = downloadData.find(d => d.id == id);
    if (!data) return;
    const url = window.location.origin + '/assets/downloads/' + encodeURIComponent(data.file_name);
    const text = `📥 ${data.judul}\n📂 ${data.kategori || 'Umum'}\n💾 ${data.file_size || '-'}\n⬇️ ${data.downloads_count || 0} downloads\n\n${url}`;
    if (navigator.share) {
        navigator.share({ title: data.judul, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info file disalin ke clipboard', 'success');
    }
}

function resetDownloadCount(id) {
    if (!confirm('Reset download counter menjadi 0?')) return;
    showToast('Info', 'Fitur ini membutuhkan endpoint API. Silakan update melalui form edit.', 'warning');
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
        dlSearch?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'download-form.php';
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
    document.querySelectorAll('.file-name, .dl-list-info h4, .dl-grid-item h4').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c📥 Download Center FKIP UNIMOF - Super Extreme', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Upload), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Kompak), Detail Modal dengan PDF Preview, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>