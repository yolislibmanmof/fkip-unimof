<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT foto FROM alumni WHERE id = ?");
        $stmt->execute([$id]);
        if (function_exists('delete_upload')) delete_upload($stmt->fetchColumn(), 'alumni');
        $pdo->prepare("DELETE FROM alumni WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Alumni berhasil dihapus.');
    }
    elseif ($action === 'toggle' && $id) {
        $pdo->prepare("UPDATE alumni SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', '✅ Status alumni diperbarui.');
    }
    elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT foto FROM alumni WHERE id = ?");
            $stmt->execute([$del_id]);
            if (function_exists('delete_upload')) delete_upload($stmt->fetchColumn(), 'alumni');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM alumni WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' alumni berhasil dihapus.');
    }
    elseif ($action === 'bulk_status' && !empty($ids)) {
        $new_status = $_POST['new_status'] ?? 'Aktif';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$new_status], $ids);
        $pdo->prepare("UPDATE alumni SET status = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', 'Status ' . count($ids) . ' alumni diubah ke ' . $new_status);
    }
    elseif ($action === 'bulk_activate' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE alumni SET status = 'Aktif' WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' alumni diaktifkan.');
    }

    header('Location: alumni.php?' . http_build_query($_GET));
    exit;
}

// ===== FETCH DATA UNTUK EXPORT & VIEW =====
$q = trim($_GET['q'] ?? '');
$tahun_filter = $_GET['tahun'] ?? '';
$status_filter = $_GET['status'] ?? '';
$prodi_filter = $_GET['prodi'] ?? '';
$view_mode = $_GET['view'] ?? 'table';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (a.nama LIKE ? OR a.pekerjaan LIKE ? OR a.perusahaan LIKE ? OR a.lokasi LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($tahun_filter !== '') { $where .= ' AND a.tahun_lulus = ?'; $params[] = $tahun_filter; }
if ($status_filter !== '') { $where .= ' AND a.status = ?'; $params[] = $status_filter; }
if ($prodi_filter !== '') { $where .= ' AND a.program_studi_id = ?'; $params[] = $prodi_filter; }

$stmt = $pdo->prepare("SELECT a.*, ps.nama as prodi_nama FROM alumni a LEFT JOIN program_studi ps ON a.program_studi_id = ps.id $where ORDER BY a.tahun_lulus DESC, a.created_at DESC");
$stmt->execute($params);
$alumni_list = $stmt->fetchAll();

// ===== EXPORT HANDLER =====
if (isset($_GET['export']) && !empty($alumni_list)) {
    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alumni-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($out, ['ID', 'Nama', 'Tahun Lulus', 'Program Studi', 'Pekerjaan', 'Perusahaan', 'Lokasi', 'LinkedIn', 'Prestasi', 'Status']);
        foreach ($alumni_list as $r) {
            fputcsv($out, [
                $r['id'], $r['nama'], $r['tahun_lulus'], $r['prodi_nama'] ?? '-',
                $r['pekerjaan'] ?? '', $r['perusahaan'] ?? '', $r['lokasi'] ?? '',
                $r['linkedin'] ?? '', $r['prestasi'] ?? '', $r['status'] ?? 'Aktif'
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'vcf') {
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="alumni-fkip-' . date('Y-m-d') . '.vcf"');
        foreach ($alumni_list as $r) {
            if (empty($r['nama'])) continue;
            echo "BEGIN:VCARD\r\n";
            echo "VERSION:3.0\r\n";
            echo "FN:" . $r['nama'] . "\r\n";
            echo "ORG:" . ($r['perusahaan'] ?: '-') . "\r\n";
            echo "TITLE:" . ($r['pekerjaan'] ?: '-') . "\r\n";
            if (!empty($r['email'])) echo "EMAIL:" . $r['email'] . "\r\n";
            if (!empty($r['phone'])) echo "TEL:" . $r['phone'] . "\r\n";
            if (!empty($r['linkedin'])) echo "URL:" . $r['linkedin'] . "\r\n";
            echo "NOTE:Alumni FKIP UNIMOF " . $r['tahun_lulus'] . " - " . ($r['prodi_nama'] ?? '-') . "\r\n";
            echo "END:VCARD\r\n";
        }
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="alumni-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($alumni_list),
            'data' => $alumni_list
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== STATISTIK LENGKAP =====
$stat_total = (int)$pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif'")->fetchColumn();
$stat_nonaktif = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Non-Aktif'")->fetchColumn();
$stat_work = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE pekerjaan IS NOT NULL AND pekerjaan != ''")->fetchColumn();
$stat_prestasi = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE prestasi IS NOT NULL AND prestasi != ''")->fetchColumn();
$stat_linkedin = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE linkedin IS NOT NULL AND linkedin != ''")->fetchColumn();

// Employment rate
$employment_rate = $stat_total > 0 ? round(($stat_work / $stat_total) * 100, 1) : 0;

// Network score (linkedin + aktif + work)
$network_score = $stat_total > 0 ? round(
    (($stat_linkedin * 0.4) + ($stat_aktif * 0.3) + ($stat_work * 0.3)) / $stat_total * 100
) : 0;

// Trend: alumni baru bulan ini
$month_new = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$month_prev = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$trend_new = $month_prev > 0 ? round((($month_new - $month_prev) / $month_prev) * 100, 1) : 0;

// Tahun stats (untuk chart)
$tahun_stats = [];
foreach ($pdo->query("SELECT tahun_lulus, COUNT(*) as total FROM alumni GROUP BY tahun_lulus ORDER BY tahun_lulus DESC LIMIT 7")->fetchAll() as $r) {
    $tahun_stats[$r['tahun_lulus']] = (int)$r['total'];
}

// Top companies
$top_companies = $pdo->query("
    SELECT perusahaan, COUNT(*) as total
    FROM alumni
    WHERE perusahaan IS NOT NULL AND perusahaan != ''
    GROUP BY perusahaan
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

// Top prodi
$top_prodi = $pdo->query("
    SELECT ps.nama, COUNT(a.id) as total
    FROM alumni a
    LEFT JOIN program_studi ps ON a.program_studi_id = ps.id
    WHERE ps.nama IS NOT NULL
    GROUP BY a.program_studi_id, ps.nama
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// Recent graduates (< 1 tahun)
$recent_grads = $pdo->query("
    SELECT a.*, ps.nama as prodi_nama
    FROM alumni a
    LEFT JOIN program_studi ps ON a.program_studi_id = ps.id
    WHERE a.tahun_lulus = YEAR(CURDATE()) OR a.tahun_lulus = YEAR(CURDATE()) - 1
    ORDER BY a.tahun_lulus DESC, a.created_at DESC
    LIMIT 5
")->fetchAll();

// Daftar prodi untuk filter
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

$csrf = generate_csrf_token();
$active_menu = 'alumni';
$page_heading = 'Kelola Alumni';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Jejaring Alumni', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO ===== */
.alumni-hero {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
}
.alumni-hero::before {
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
.alumni-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}
.alumni-hero h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.alumni-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 500px; }
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
}
.hero-stat-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-top: 0.25rem;
}

/* Network Score Ring */
.network-score-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.network-score-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.network-score-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.2); stroke-width: 8; }
.network-score-ring .ring-fill { fill: none; stroke: white; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.network-score-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.network-score-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; }
.network-score-value .score-label { font-size: 0.65rem; opacity: 0.9; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS EXTREME ===== */
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

/* ===== RECENT GRADS ALERT ===== */
.recent-grads-alert {
    background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    border: 1px solid #c4b5fd;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.5s ease;
    flex-wrap: wrap;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.recent-grads-icon {
    font-size: 2rem;
    flex-shrink: 0;
    animation: bounce 2s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.recent-grads-content { flex: 1; min-width: 200px; }
.recent-grads-content h4 { font-size: 0.95rem; font-weight: 700; color: #5b21b6; margin-bottom: 0.25rem; }
.recent-grads-content p { font-size: 0.82rem; color: #6d28d9; margin: 0; }
.recent-grads-avatars {
    display: flex;
    margin-right: auto;
}
.recent-grads-avatars .alumni-avatar-xs {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.82rem;
    border: 2px solid white;
    margin-left: -10px;
    overflow: hidden;
}
.recent-grads-avatars .alumni-avatar-xs:first-child { margin-left: 0; }
.recent-grads-avatars .alumni-avatar-xs img { width: 100%; height: 100%; object-fit: cover; }

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

/* Top companies list (non-chart) */
.top-companies-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.top-company-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.top-company-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-company-rank {
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
.top-company-item:nth-child(1) .top-company-rank { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.top-company-item:nth-child(2) .top-company-rank { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.top-company-item:nth-child(3) .top-company-rank { background: linear-gradient(135deg, #fdba74, #fb923c); color: white; }
.top-company-info { flex: 1; min-width: 0; }
.top-company-name {
    font-weight: 700;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.top-company-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}
.top-company-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #8b5cf6, #7c3aed);
    border-radius: 999px;
    transition: width 1s ease;
}
.top-company-count {
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border-color: #7c3aed;
    box-shadow: 0 4px 12px rgba(139,92,246,0.3);
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
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
.filter-select:focus { outline: none; border-color: #8b5cf6; }

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
.view-btn.active { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    box-shadow: 0 4px 12px rgba(139,92,246,0.3);
}
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139,92,246,0.4); }
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
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(139,92,246,0.3);
}
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: #7c3aed; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
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
.bulk-btn.primary { background: white; color: #7c3aed; }
.bulk-btn.success { background: #10b981; color: white; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

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
table.extreme th[data-sortable]:hover { color: #8b5cf6; }
table.extreme th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
table.extreme th.asc .sort-icon, table.extreme th.desc .sort-icon { opacity: 1; color: #8b5cf6; }

table.extreme td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
table.extreme tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); transform: translateX(2px); }
table.extreme tbody tr.selected { background: rgba(139,92,246,0.05); }
table.extreme tbody tr:last-child td { border-bottom: none; }

.col-check { width: 40px; }
.row-checkbox { width: 18px; height: 18px; accent-color: #8b5cf6; cursor: pointer; }

/* Avatar */
.alumni-avatar-sm {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
.alumni-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.alumni-avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
    border: 3px solid var(--border);
    box-shadow: 0 4px 12px rgba(139,92,246,0.25);
}
.alumni-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }

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
.badge-work { background: #dbeafe; color: #1e40af; }
.badge-prestasi { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-linkedin { background: #e0f2fe; color: #0369a1; }

/* Social icons in table */
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
.social-icon-link.linkedin { background: #0077b5; color: white; }
.social-icon-link.email { background: #ea4335; color: white; }
.social-icon-link.phone { background: #25d366; color: white; }

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
.alumni-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.alumni-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 70px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}
.alumni-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: #8b5cf6;
}
.alumni-card-body {
    padding: 1.25rem;
    padding-top: 3rem;
    position: relative;
}
.alumni-card-avatar {
    position: absolute;
    top: -35px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.75rem;
    border: 4px solid var(--bg-primary);
    box-shadow: 0 8px 20px rgba(139,92,246,0.3);
    overflow: hidden;
}
.alumni-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
.alumni-card-name {
    text-align: center;
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    margin-top: 0.5rem;
}
.alumni-card-role {
    text-align: center;
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.alumni-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.alumni-card-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-secondary);
}
.alumni-card-info-item .info-icon {
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
.alumni-card-footer {
    padding: 0.85rem 1.25rem;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ===== TIMELINE VIEW (grouped by year) ===== */
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
.timeline-year {
    font-size: 1.5rem;
    font-weight: 900;
    color: #8b5cf6;
    font-family: var(--font-display);
}
.timeline-count {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(3px); border-color: #8b5cf6; }
.timeline-item-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
.modal-header-alumni {
    padding: 2rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-alumni::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
    border-radius: 50%;
}
.modal-header-alumni .alumni-avatar-lg {
    border-color: rgba(255,255,255,0.3);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    position: relative;
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
.modal-profile-name { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem; }
.modal-profile-role { font-size: 0.9rem; opacity: 0.95; margin-bottom: 0.35rem; }
.modal-profile-company { font-size: 0.85rem; opacity: 0.85; }
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
.detail-tab.active { color: #8b5cf6; border-bottom-color: #8b5cf6; }
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

/* Social links in modal */
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

/* Career path */
.career-path {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
    padding-left: 1.5rem;
}
.career-path::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 10px;
    bottom: 10px;
    width: 2px;
    background: linear-gradient(180deg, #8b5cf6, #ddd6fe);
}
.career-step {
    position: relative;
    padding: 0.85rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 0.75rem;
    transition: all 0.2s;
}
.career-step:hover { background: var(--bg-tertiary); transform: translateX(3px); }
.career-step::before {
    content: '';
    position: absolute;
    left: -1.75rem;
    top: 1.25rem;
    width: 12px;
    height: 12px;
    background: #8b5cf6;
    border: 3px solid var(--bg-primary);
    border-radius: 50%;
    box-shadow: 0 0 0 2px #8b5cf6;
}
.career-step.current::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; animation: pulse 2s infinite; }
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 2px currentColor; }
    50% { box-shadow: 0 0 0 6px rgba(16,185,129,0.3); }
}
.career-role { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.15rem; }
.career-company { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
.career-period { font-size: 0.75rem; color: var(--text-muted); }

@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
    .detail-grid, .social-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
    .alumni-hero-content { flex-direction: column; text-align: center; }
    .network-score-ring { margin: 0 auto; }
    .cards-grid { grid-template-columns: 1fr; }
    .hero-stats { justify-content: center; }
    .timeline-items { grid-template-columns: 1fr; }
}

/* Print styles */
@media print {
    .toolbar-extreme, .bulk-bar, .action-buttons, .chart-section, .chart-section-3, .stats-extreme, .alumni-hero, .recent-grads-alert, .quick-filter-pills, .modal-overlay { display: none !important; }
    .table-container { box-shadow: none; border: 1px solid #ddd; }
    table.extreme tbody tr:hover { background: transparent; transform: none; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="alumni-hero" data-aos="fade-down">
    <div class="alumni-hero-content">
        <div>
            <h2>🎓 Jejaring Alumni</h2>
            <p>Kelola dan hubungkan alumni FKIP UNIMOF. Bangun komunitas profesional yang kuat untuk masa depan yang lebih baik.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_total ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $employment_rate ?>%</span>
                    <span class="hero-stat-label">Employed</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num">+<?= $month_new ?></span>
                    <span class="hero-stat-label">Bulan Ini</span>
                </div>
            </div>
        </div>
        <div class="network-score-ring">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $network_score ?>, 100"/>
            </svg>
            <div class="network-score-value">
                <div class="score-num"><?= $network_score ?></div>
                <div class="score-label">Network</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS EXTREME ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Alumni</div>
        <div class="stat-trend <?= $trend_new >= 0 ? 'up' : 'down' ?>">
            <?= $trend_new >= 0 ? '↑' : '↓' ?> <?= abs($trend_new) ?>% vs bulan lalu
        </div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Alumni Aktif</div>
        <div class="stat-trend up">🟢 <?= $stat_total > 0 ? round($stat_aktif / $stat_total * 100) : 0 ?>% dari total</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">💼</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_work ?>">0</div>
        <div class="stat-label-extreme">Sudah Bekerja</div>
        <div class="stat-trend up">📈 <?= $employment_rate ?>% employment rate</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ef4444;">
        <div class="stat-icon-extreme">🏆</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_prestasi ?>">0</div>
        <div class="stat-label-extreme">Berprestasi</div>
        <div class="stat-trend neutral">⭐ Achievement</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #0077b5;">
        <div class="stat-icon-extreme">🔗</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_linkedin ?>">0</div>
        <div class="stat-label-extreme">Terhubung</div>
        <div class="stat-trend up">💼 LinkedIn aktif</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #64748b;">
        <div class="stat-icon-extreme">📴</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_nonaktif ?>">0</div>
        <div class="stat-label-extreme">Non-Aktif</div>
        <div class="stat-trend neutral">🔕 Perlu follow-up</div>
    </div>
</div>

<!-- ===== RECENT GRADUATES ALERT ===== -->
<?php if (!empty($recent_grads)): ?>
<div class="recent-grads-alert" data-aos="fade-down">
    <div class="recent-grads-icon">🎉</div>
    <div class="recent-grads-content">
        <h4>🎓 Lulusan Terbaru Bergabung!</h4>
        <p><?= count($recent_grads) ?> alumni dari tahun <?= date('Y') ?> dan <?= date('Y') - 1 ?> baru terdaftar. Mari sambut mereka di jejaring alumni.</p>
    </div>
    <div class="recent-grads-avatars">
        <?php foreach (array_slice($recent_grads, 0, 5) as $rg): ?>
            <div class="alumni-avatar-xs" title="<?= sanitize($rg['nama']) ?>">
                <?php if (!empty($rg['foto'])): ?>
                    <img src="<?= asset('uploads/alumni/' . basename($rg['foto'])) ?>" alt="">
                <?php else: ?>
                    <?= strtoupper(substr($rg['nama'], 0, 1)) ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== CHARTS SECTION ===== -->
<?php if (!empty($tahun_stats) || !empty($top_companies)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Tahun Lulus</h3>
        <div id="tahunChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏢 Top Perusahaan Alumni</h3>
        <?php if (empty($top_companies)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🏢</div>
                <div>Belum ada data perusahaan</div>
            </div>
        <?php else: ?>
            <div class="top-companies-list">
                <?php
                $max_count = max(array_column($top_companies, 'total'));
                foreach ($top_companies as $i => $c):
                ?>
                <div class="top-company-item">
                    <div class="top-company-rank"><?= $i + 1 ?></div>
                    <div class="top-company-info">
                        <div class="top-company-name"><?= sanitize($c['perusahaan']) ?></div>
                        <div class="top-company-bar">
                            <div class="top-company-bar-fill" style="width: <?= ($c['total'] / $max_count) * 100 ?>%"></div>
                        </div>
                    </div>
                    <div class="top-company-count"><?= $c['total'] ?></div>
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
        <h3>🎓 Distribusi Prodi</h3>
        <div id="prodiChart"></div>
    </div>
    <div class="chart-card">
        <h3>💼 Employment Status</h3>
        <div id="employmentChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="alumni.php" class="pill <?= empty($status_filter) && empty($tahun_filter) ? 'active' : '' ?>">
        🎯 Semua <span class="pill-count"><?= $stat_total ?></span>
    </a>
    <a href="alumni.php?status=Aktif" class="pill <?= $status_filter === 'Aktif' ? 'active' : '' ?>">
        ✅ Aktif <span class="pill-count"><?= $stat_aktif ?></span>
    </a>
    <a href="alumni.php?status=Non-Aktif" class="pill <?= $status_filter === 'Non-Aktif' ? 'active' : '' ?>">
        📴 Non-Aktif <span class="pill-count"><?= $stat_nonaktif ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="alumni.php?tahun=<?= date('Y') ?>" class="pill <?= $tahun_filter == date('Y') ? 'active' : '' ?>">
        🎓 <?= date('Y') ?> <span class="pill-count"><?= $pdo->query("SELECT COUNT(*) FROM alumni WHERE tahun_lulus = " . date('Y'))->fetchColumn() ?></span>
    </a>
    <a href="alumni.php?tahun=<?= date('Y') - 1 ?>" class="pill <?= $tahun_filter == (date('Y') - 1) ? 'active' : '' ?>">
        🎓 <?= date('Y') - 1 ?>
    </a>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama, pekerjaan, perusahaan, atau lokasi..." value="<?= sanitize($q) ?>">
        <span class="search-shortcut">/</span>
    </div>
    <select class="filter-select" id="tahunFilter">
        <option value="">🎓 Semua Tahun Lulus</option>
        <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
            <option value="<?= $y ?>" <?= $tahun_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">📊 Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <select class="filter-select" id="prodiFilter">
        <option value="">🎓 Semua Prodi</option>
        <?php foreach ($prodi_list as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $prodi_filter == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
        <?php endforeach; ?>
    </select>

    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
        <button class="view-btn <?= $view_mode === 'card' ? 'active' : '' ?>" onclick="switchView('card')">🎴 Kartu</button>
        <button class="view-btn <?= $view_mode === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📅 Timeline</button>
    </div>

    <a href="alumni-form.php" class="btn-action primary">
        <span>➕</span>
        <span>Tambah Alumni</span>
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
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'vcf'])) ?>" class="export-item">
                <span class="export-item-icon">📇</span>
                <div>
                    <div style="font-weight: 600;">Export vCard (VCF)</div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Untuk kontak (Outlook/Android)</div>
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
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Cetak direktori alumni</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>alumni dipilih</span>
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
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus semua alumni terpilih? Aksi ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== TABLE / CARD / TIMELINE VIEW ===== -->
<?php if (empty($alumni_list)): ?>
    <div class="empty-state-extreme" data-aos="fade-up">
        <div class="empty-icon-extreme">🎓</div>
        <h3>Belum ada data alumni</h3>
        <p>Mulai tambahkan data alumni untuk membangun jejaring profesional yang kuat.</p>
        <a href="alumni-form.php" class="btn-action primary" style="margin-top: 1rem;">+ Tambah Alumni Pertama</a>
    </div>
<?php else: ?>
    <div class="table-container" data-aos="fade-up">
        <div class="table-header">
            <div>
                <h2>🎓 Daftar Alumni</h2>
                <p>Jejaring lulusan FKIP UNIMOF</p>
            </div>
            <span style="font-size: 0.82rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.4rem 0.85rem; border-radius: 999px;">
                📊 <?= count($alumni_list) ?> data
            </span>
        </div>

        <?php if ($view_mode === 'card'): ?>
            <!-- CARD VIEW -->
            <div class="cards-grid">
                <?php foreach ($alumni_list as $a):
                    $initials = strtoupper(substr($a['nama'], 0, 1));
                    $status_class = strtolower($a['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                ?>
                <div class="alumni-card" onclick="showDetail(<?= $a['id'] ?>)">
                    <div class="alumni-card-body">
                        <div class="alumni-card-avatar">
                            <?php if (!empty($a['foto'])): ?>
                                <img src="<?= asset('uploads/alumni/' . basename($a['foto'])) ?>" alt="">
                            <?php else: ?>
                                <?= $initials ?>
                            <?php endif; ?>
                        </div>
                        <div class="alumni-card-name"><?= sanitize($a['nama']) ?></div>
                        <div class="alumni-card-role">
                            <?= sanitize($a['pekerjaan'] ?: 'Belum bekerja') ?>
                            <?php if (!empty($a['perusahaan'])): ?> @ <?= sanitize($a['perusahaan']) ?><?php endif; ?>
                        </div>
                        <div class="alumni-card-info">
                            <div class="alumni-card-info-item">
                                <span class="info-icon">🎓</span>
                                <span><?= sanitize($a['tahun_lulus']) ?> • <?= sanitize($a['prodi_nama'] ?? '-') ?></span>
                            </div>
                            <?php if (!empty($a['lokasi'])): ?>
                            <div class="alumni-card-info-item">
                                <span class="info-icon">📍</span>
                                <span><?= sanitize($a['lokasi']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($a['prestasi'])): ?>
                            <div class="alumni-card-info-item">
                                <span class="info-icon">🏆</span>
                                <span><?= excerpt($a['prestasi'], 30) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="alumni-card-footer">
                        <span class="badge-extreme <?= $status_class ?>"><?= $a['status'] ?? 'Aktif' ?></span>
                        <div class="action-buttons" onclick="event.stopPropagation()">
                            <?php if (!empty($a['linkedin'])): ?>
                                <a href="<?= sanitize($a['linkedin']) ?>" target="_blank" class="social-icon-link linkedin" title="LinkedIn">in</a>
                            <?php endif; ?>
                            <a href="alumni-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view_mode === 'timeline'): ?>
            <!-- TIMELINE VIEW (grouped by year) -->
            <div class="timeline-view">
                <?php
                $grouped = [];
                foreach ($alumni_list as $a) {
                    $year = $a['tahun_lulus'] ?? 'Unknown';
                    $grouped[$year][] = $a;
                }
                krsort($grouped);
                foreach ($grouped as $year => $items):
                ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <span style="font-size: 1.5rem;">🎓</span>
                        <span class="timeline-year"><?= $year ?></span>
                        <span class="timeline-count"><?= count($items) ?> alumni</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $a):
                            $initials = strtoupper(substr($a['nama'], 0, 1));
                        ?>
                        <div class="timeline-item" onclick="showDetail(<?= $a['id'] ?>)">
                            <div class="timeline-item-avatar">
                                <?php if (!empty($a['foto'])): ?>
                                    <img src="<?= asset('uploads/alumni/' . basename($a['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <div class="timeline-item-name"><?= sanitize($a['nama']) ?></div>
                                <div class="timeline-item-role">
                                    <?= sanitize($a['pekerjaan'] ?: '-') ?>
                                    <?php if (!empty($a['perusahaan'])): ?> • <?= sanitize($a['perusahaan']) ?><?php endif; ?>
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
                <table class="extreme" id="alumniTable">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" class="row-checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th data-sortable="nama">Alumni <span class="sort-icon">↕</span></th>
                            <th data-sortable="tahun_lulus">Tahun <span class="sort-icon">↕</span></th>
                            <th data-sortable="prodi">Prodi <span class="sort-icon">↕</span></th>
                            <th data-sortable="pekerjaan">Karir <span class="sort-icon">↕</span></th>
                            <th>Prestasi & Kontak</th>
                            <th data-sortable="status">Status <span class="sort-icon">↕</span></th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($alumni_list as $a):
                        $initials = strtoupper(substr($a['nama'], 0, 1));
                        $status_class = strtolower($a['status'] ?? 'aktif') === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                    ?>
                    <tr data-id="<?= $a['id'] ?>" data-search="<?= strtolower(sanitize($a['nama'] . ' ' . ($a['pekerjaan'] ?? '') . ' ' . ($a['perusahaan'] ?? '') . ' ' . ($a['lokasi'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" class="row-checkbox row-select" value="<?= $a['id'] ?>" onchange="updateBulkCount()">
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="alumni-avatar-sm">
                                    <?php if (!empty($a['foto'])): ?>
                                        <img src="<?= asset('uploads/alumni/' . basename($a['foto'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= $initials ?>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width: 0;">
                                    <strong style="color: var(--text-primary); font-size: 0.95rem; display: block;"><?= sanitize($a['nama']) ?></strong>
                                    <div class="social-icons">
                                        <?php if (!empty($a['linkedin'])): ?>
                                            <a href="<?= sanitize($a['linkedin']) ?>" target="_blank" class="social-icon-link linkedin" title="LinkedIn">in</a>
                                        <?php endif; ?>
                                        <?php if (!empty($a['email'])): ?>
                                            <a href="mailto:<?= sanitize($a['email']) ?>" class="social-icon-link email" title="Email">✉</a>
                                        <?php endif; ?>
                                        <?php if (!empty($a['phone'])): ?>
                                            <a href="tel:<?= sanitize($a['phone']) ?>" class="social-icon-link phone" title="Phone">📞</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-weight: 700; color: #8b5cf6; font-size: 0.95rem;">
                                <?= sanitize($a['tahun_lulus']) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;">
                            <?= sanitize($a['prodi_nama'] ?? '-') ?>
                        </td>
                        <td style="color: var(--text-secondary); font-size: 0.88rem; max-width: 200px;">
                            <?php if (!empty($a['pekerjaan'])): ?>
                                <div style="font-weight: 600; color: var(--text-primary);"><?= sanitize($a['pekerjaan']) ?></div>
                                <?php if (!empty($a['perusahaan'])): ?>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">🏢 <?= sanitize($a['perusahaan']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($a['lokasi'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">📍 <?= sanitize($a['lokasi']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic;">Belum bekerja</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($a['prestasi'])): ?>
                                <span class="badge-extreme badge-prestasi" title="<?= sanitize($a['prestasi']) ?>">
                                    🏆 <?= excerpt($a['prestasi'], 25) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.82rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-extreme <?= $status_class ?>"><?= $a['status'] ?? 'Aktif' ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" onclick="showDetail(<?= $a['id'] ?>)" title="Detail">👁️</button>
                                <a href="alumni-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status alumni?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus alumni ini?')">
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
        <div class="modal-header-alumni">
            <div class="modal-profile-row">
                <div class="alumni-avatar-lg" id="modalAvatar">?</div>
                <div class="modal-profile-info">
                    <div class="modal-profile-name" id="modalName">-</div>
                    <div class="modal-profile-role" id="modalRole">-</div>
                    <div class="modal-profile-company" id="modalCompany">-</div>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('info', this)">ℹ️ Informasi</button>
                <button class="detail-tab" onclick="switchDetailTab('career', this)">💼 Karir</button>
                <button class="detail-tab" onclick="switchDetailTab('social', this)">🔗 Sosial</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-info">
                <div class="detail-grid" id="detailGrid"></div>
            </div>

            <div class="detail-tab-content" id="tab-career">
                <div id="detailCareer"></div>
            </div>

            <div class="detail-tab-content" id="tab-social">
                <div class="social-grid" id="detailSocial"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div id="detailActions" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA untuk detail modal =====
const alumniData = <?= json_encode($alumni_list) ?>;

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
<?php if (!empty($tahun_stats)): ?>
new ApexCharts(document.querySelector("#tahunChart"), {
    series: [{ data: <?= json_encode(array_values($tahun_stats)) ?> }],
    chart: { type: 'bar', height: 280, toolbar: { show: false } },
    colors: ['#8b5cf6'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', distributed: false } },
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    xaxis: {
        categories: <?= json_encode(array_keys($tahun_stats)) ?>,
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    tooltip: { y: { formatter: v => v + ' alumni' } }
}).render();
<?php endif; ?>

<?php if (!empty($top_prodi)): ?>
new ApexCharts(document.querySelector("#prodiChart"), {
    series: <?= json_encode(array_column($top_prodi, 'total')) ?>,
    labels: <?= json_encode(array_map(fn($p) => strlen($p['nama']) > 15 ? substr($p['nama'], 0, 15) . '...' : $p['nama'], $top_prodi)) ?>,
    chart: { type: 'donut', height: 220 },
    colors: ['#8b5cf6', '#7c3aed', '#6d28d9', '#5b21b6', '#a78bfa', '#c4b5fd'],
    plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total' } } } } },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '11px' }
}).render();
<?php endif; ?>

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_nonaktif ?>],
    labels: ['Aktif', 'Non-Aktif'],
    chart: { type: 'pie', height: 220 },
    colors: ['#10b981', '#ef4444'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' }
}).render();

new ApexCharts(document.querySelector("#employmentChart"), {
    series: [<?= $stat_work ?>, <?= max(0, $stat_total - $stat_work) ?>],
    labels: ['Sudah Bekerja', 'Belum Bekerja'],
    chart: { type: 'pie', height: 220 },
    colors: ['#f59e0b', '#94a3b8'],
    dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '11px' }
}).render();

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const tahunFilter = document.getElementById('tahunFilter');
const statusFilter = document.getElementById('statusFilter');
const prodiFilter = document.getElementById('prodiFilter');

let searchTimeout;
searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
[tahunFilter, statusFilter, prodiFilter].forEach(f => f?.addEventListener('change', applyFilters));

function applyFilters() {
    const q = searchInput.value;
    const tahun = tahunFilter.value;
    const status = statusFilter.value;
    const prodi = prodiFilter.value;

    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (tahun) url.searchParams.set('tahun', tahun); else url.searchParams.delete('tahun');
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
    const data = alumniData.find(a => a.id == id);
    if (!data) return;

    const initials = data.nama ? data.nama.charAt(0).toUpperCase() : '?';
    const avatar = document.getElementById('modalAvatar');
    if (data.foto) {
        avatar.innerHTML = `<img src="${window.location.origin}/uploads/alumni/${data.foto}" alt="">`;
    } else {
        avatar.innerHTML = initials;
    }

    document.getElementById('modalName').textContent = data.nama || '-';
    document.getElementById('modalRole').textContent = data.pekerjaan || 'Belum bekerja';
    document.getElementById('modalCompany').textContent = data.perusahaan
        ? `🏢 ${data.perusahaan}${data.lokasi ? ' • 📍 ' + data.lokasi : ''}`
        : (data.lokasi ? `📍 ${data.lokasi}` : '-');

    // Info tab
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const createdDate = data.created_at ? new Date(data.created_at) : null;

    document.getElementById('detailGrid').innerHTML = `
        <div class="detail-item">
            <div class="detail-item-label">👤 Nama Lengkap</div>
            <div class="detail-item-value">${escapeHtml(data.nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🎓 Tahun Lulus</div>
            <div class="detail-item-value">${escapeHtml(data.tahun_lulus || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏫 Program Studi</div>
            <div class="detail-item-value">${escapeHtml(data.prodi_nama || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📊 Status</div>
            <div class="detail-item-value"><span class="badge-extreme ${data.status === 'Aktif' ? 'badge-aktif' : 'badge-non-aktif'}">${escapeHtml(data.status || 'Aktif')}</span></div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">💼 Pekerjaan</div>
            <div class="detail-item-value">${escapeHtml(data.pekerjaan || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏢 Perusahaan</div>
            <div class="detail-item-value">${escapeHtml(data.perusahaan || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">📍 Lokasi</div>
            <div class="detail-item-value">${escapeHtml(data.lokasi || '-')}</div>
        </div>
        <div class="detail-item">
            <div class="detail-item-label">🏆 Prestasi</div>
            <div class="detail-item-value" style="font-size: 0.85rem;">${escapeHtml(data.prestasi || '-')}</div>
        </div>
        ${createdDate ? `
        <div class="detail-item" style="grid-column: 1 / -1;">
            <div class="detail-item-label">📅 Terdaftar Sejak</div>
            <div class="detail-item-value">${createdDate.getDate()} ${months[createdDate.getMonth()]} ${createdDate.getFullYear()}</div>
        </div>
        ` : ''}
    `;

    // Career tab
    const careerHtml = `
        <div class="career-path">
            <div class="career-step current">
                <div class="career-role">${escapeHtml(data.pekerjaan || 'Belum bekerja')}</div>
                <div class="career-company">${escapeHtml(data.perusahaan || '-')}${data.lokasi ? ' • ' + escapeHtml(data.lokasi) : ''}</div>
                <div class="career-period">📍 Posisi saat ini</div>
            </div>
            <div class="career-step">
                <div class="career-role">🎓 Lulus dari FKIP UNIMOF</div>
                <div class="career-company">${escapeHtml(data.prodi_nama || '-')}</div>
                <div class="career-period">Tahun ${escapeHtml(data.tahun_lulus || '-')}</div>
            </div>
        </div>
        ${data.prestasi ? `
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); border-left: 4px solid #f59e0b;">
            <div style="font-weight: 700; margin-bottom: 0.35rem;">🏆 Achievement</div>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">${escapeHtml(data.prestasi)}</div>
        </div>
        ` : ''}
    `;
    document.getElementById('detailCareer').innerHTML = careerHtml;

    // Social tab
    const socialItems = [];
    if (data.email) {
        socialItems.push(`<a href="mailto:${escapeHtml(data.email)}" class="social-item">
            <div class="social-item-icon" style="background: #fee2e2; color: #dc2626;">✉️</div>
            <div class="social-item-info">
                <div class="social-item-label">Email</div>
                <div class="social-item-value">${escapeHtml(data.email)}</div>
            </div>
        </a>`);
    }
    if (data.phone) {
        socialItems.push(`<a href="tel:${escapeHtml(data.phone)}" class="social-item">
            <div class="social-item-icon" style="background: #dcfce7; color: #16a34a;">📞</div>
            <div class="social-item-info">
                <div class="social-item-label">Telepon</div>
                <div class="social-item-value">${escapeHtml(data.phone)}</div>
            </div>
        </a>`);
    }
    if (data.linkedin) {
        socialItems.push(`<a href="${escapeHtml(data.linkedin)}" target="_blank" class="social-item">
            <div class="social-item-icon" style="background: #0077b5; color: white;">in</div>
            <div class="social-item-info">
                <div class="social-item-label">LinkedIn</div>
                <div class="social-item-value">${escapeHtml(data.linkedin)}</div>
            </div>
        </a>`);
    }
    if (socialItems.length === 0) {
        socialItems.push(`<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">
            <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">🔗</div>
            <div>Belum ada kontak yang terdaftar</div>
        </div>`);
    }
    document.getElementById('detailSocial').innerHTML = socialItems.join('');

    // Actions tab
    document.getElementById('detailActions').innerHTML = `
        <a href="alumni-form.php?id=${data.id}" class="btn-action primary" style="justify-content: flex-start;">✏️ Edit Data Alumni</a>
        ${data.linkedin ? `<a href="${escapeHtml(data.linkedin)}" target="_blank" class="btn-action secondary" style="justify-content: flex-start; background: #0077b5; color: white; border-color: #0077b5;">in Hubungi via LinkedIn</a>` : ''}
        ${data.email ? `<a href="mailto:${escapeHtml(data.email)}" class="btn-action secondary" style="justify-content: flex-start;">✉️ Kirim Email</a>` : ''}
        <button onclick="shareAlumni(${data.id})" class="btn-action secondary" style="justify-content: flex-start;">🔗 Bagikan Profil</button>
        <a href="alumni-form.php?duplicate=${data.id}" class="btn-action secondary" style="justify-content: flex-start;" onclick="return confirm('Duplikasi data alumni ini?')">📋 Duplikasi Data</a>
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

function shareAlumni(id) {
    const data = alumniData.find(a => a.id == id);
    if (!data) return;
    const text = `🎓 ${data.nama}\n💼 ${data.pekerjaan || '-'}${data.perusahaan ? ' @ ' + data.perusahaan : ''}\n🎓 Alumni FKIP UNIMOF ${data.tahun_lulus}\n📍 ${data.lokasi || '-'}`;
    if (navigator.share) {
        navigator.share({ title: data.nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Profil alumni disalin ke clipboard', 'success');
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
    // "/" focus search
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        searchInput?.focus();
    }
    // Ctrl+N: tambah baru
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'alumni-form.php';
    }
    // Ctrl+E: export CSV
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        window.location = window.location.pathname + '?export=csv';
    }
    // Ctrl+A: select all
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

console.log('%c🎓 Jejaring Alumni FKIP UNIMOF', 'color: #8b5cf6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Kartu/Timeline), Bulk Actions, Detail Modal, Charts, Export CSV/VCF/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>