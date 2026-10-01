<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== HELPER LOKAL =====
if (!function_exists('gal_thumb')) {
    function gal_thumb($row) {
        if (!empty($row['gambar'])) return asset('uploads/galeri/' . basename($row['gambar']));
        return '';
    }
}
if (!function_exists('gal_del_img')) {
    function gal_del_img($f) {
        if ($f) { 
            $p = (defined('APP_DIR') ? APP_DIR : dirname(__DIR__, 2)) . '/assets/uploads/galeri/' . basename($f); 
            if (is_file($p)) @unlink($p); 
        }
    }
}

$GSTATUS = ['Published', 'Draft'];

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));

        if ($action === 'bulk_delete' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $s = $pdo->prepare("SELECT gambar FROM galeri WHERE id IN ($ph)"); $s->execute($ids);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $img) { gal_del_img($img); }
            $pdo->prepare("DELETE FROM galeri WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '🗑️ ' . count($ids) . ' foto dihapus permanen.');
        }
        elseif ($action === 'bulk_publish' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE galeri SET status='Published' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' foto dipublikasikan.');
        }
        elseif ($action === 'bulk_draft' && !empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE galeri SET status='Draft' WHERE id IN ($ph)")->execute($ids);
            flash_message('success', '📝 ' . count($ids) . ' foto diubah ke draft.');
        }
        elseif ($action === 'delete' && $id) {
            $s = $pdo->prepare("SELECT gambar FROM galeri WHERE id=?"); $s->execute([$id]); gal_del_img($s->fetchColumn());
            $pdo->prepare("DELETE FROM galeri WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Foto berhasil dihapus.');
        }
        elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE galeri SET status=IF(status='Published','Draft','Published') WHERE id=?")->execute([$id]);
            flash_message('success', '✅ Status foto diubah.');
        }
    }
    header('Location: galeri.php?' . http_build_query($_GET)); exit;
}

// ===== EXPORT =====
if (isset($_GET['export'])) {
    $fmt = $_GET['export'];
    $all = $pdo->query("SELECT * FROM galeri ORDER BY tanggal DESC, id DESC")->fetchAll();
    if ($fmt === 'csv' && $all) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="galeri-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Judul', 'Kategori', 'Status', 'Tanggal', 'Deskripsi']);
        foreach ($all as $r) fputcsv($out, [$r['id'], $r['judul'], $r['kategori'], $r['status'], $r['tanggal'], strip_tags($r['deskripsi'])]);
        fclose($out); exit;
    }
    if ($fmt === 'json' && $all) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="galeri-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode(['exported_at' => date('c'), 'total' => count($all), 'data' => $all], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
    }
}

// ===== TAB & FILTER =====
$q = trim($_GET['q'] ?? '');
$kat_filter = trim($_GET['kategori'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_filter = trim($_GET['tanggal'] ?? '');
$view_mode = $_GET['view'] ?? 'grid';
$sort_by = $_GET['sort'] ?? 'tanggal';
$sort_dir = $_GET['dir'] ?? 'desc';
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = $view_mode === 'grid' ? 15 : 20;
$offset = ($halaman - 1) * $per_page;

$kat_list = $pdo->query("SELECT DISTINCT kategori FROM galeri WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);
if (empty($kat_list)) $kat_list = ['Akademik', 'Kegiatan', 'Prestasi', 'Wisuda', 'Riset', 'Kerjasama', 'Umum'];

$where = 'WHERE 1=1'; $params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR deskripsi LIKE ?)'; $l="%$q%"; $params[]=$l; $params[]=$l; }
if ($kat_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kat_filter; }
if (in_array($status_filter, $GSTATUS, true)) { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($date_filter !== '') { $where .= ' AND DATE(tanggal) = ?'; $params[] = $date_filter; }

$valid_sorts = ['tanggal', 'judul', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'tanggal';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$cs = $pdo->prepare("SELECT COUNT(*) FROM galeri $where"); $cs->execute($params); $total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page)); $halaman = min($halaman, $total_pages); $offset = ($halaman - 1) * $per_page;

$st = $pdo->prepare("SELECT * FROM galeri $where ORDER BY $sort_by $sort_dir LIMIT ? OFFSET ?");
$st->execute(array_merge($params, [$per_page, $offset])); $list = $st->fetchAll();

// ===== STATISTIK =====
$stat_published = (int)$pdo->query("SELECT COUNT(*) FROM galeri WHERE status='Published'")->fetchColumn();
$stat_draft     = (int)$pdo->query("SELECT COUNT(*) FROM galeri WHERE status='Draft'")->fetchColumn();
$stat_total     = $stat_published + $stat_draft;
$stat_week      = (int)$pdo->query("SELECT COUNT(*) FROM galeri WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$kat_stats = [];
try {
    foreach ($pdo->query("SELECT COALESCE(kategori,'Umum') nm, COUNT(*) t FROM galeri WHERE status='Published' GROUP BY nm ORDER BY t DESC")->fetchAll() as $r)
        $kat_stats[$r['nm']] = (int)$r['t'];
} catch (Exception $e) {}

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months")); $lb = date('M Y', strtotime("-$i months"));
    $q2 = $pdo->prepare("SELECT COUNT(*) FROM galeri WHERE DATE_FORMAT(tanggal,'%Y-%m')=?"); $q2->execute([$m]);
    $monthly[$lb] = (int)$q2->fetchColumn();
}

$csrf = generate_csrf_token();
$active_menu = 'galeri';
$page_heading = 'Kelola Galeri Foto';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Galeri', null]];
require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== GALERI EXTREME MULTIMATE STYLES ===== */
:root {
    --gal-accent: #8b5cf6;
    --gal-accent-light: #a78bfa;
    --gal-gradient: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%);
}
[data-theme="dark"] { --gal-accent: #a78bfa; --gal-accent-light: #c4b5fd; }

.gal-hero {
    background: var(--gal-gradient);
    color: white; padding: 2rem; border-radius: 20px; margin-bottom: 2rem;
    position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.3);
}
.gal-hero::before {
    content: ''; position: absolute; top: -50%; right: -15%; width: 450px; height: 450px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%); border-radius: 50%; pointer-events: none;
}
.gal-hero::after {
    content: 'GALERI'; position: absolute; top: 2rem; right: 2rem;
    font-family: 'Georgia', serif; font-size: 6rem; font-weight: 900;
    color: rgba(255,255,255,0.05); letter-spacing: 0.2em; pointer-events: none;
}
.gal-hero-content { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap; z-index: 1; }
.gal-hero h2 { font-family: 'Georgia', serif; font-size: 2rem; font-weight: 900; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.gal-hero p { opacity: 0.95; font-size: 0.95rem; max-width: 520px; line-height: 1.6; }

.hero-stats { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
.hero-stat {
    display: flex; flex-direction: column; align-items: center; padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.12); backdrop-filter: blur(10px); border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.2); min-width: 90px;
}
.hero-stat-num { font-size: 1.5rem; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; font-family: 'Georgia', serif; }
.hero-stat-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9; margin-top: 0.25rem; }

.gal-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.stat-card-gal {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl);
    padding: 1.5rem; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm); cursor: pointer;
}
.stat-card-gal::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gal-gradient); }
.stat-card-gal:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); border-color: var(--gal-accent); }
.stat-header-gal { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
.stat-icon-gal {
    width: 42px; height: 42px; border-radius: 10px; background: var(--gal-gradient);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: white; flex-shrink: 0; box-shadow: 0 4px 12px rgba(124,58,237,0.3);
}
.stat-number-gal { font-family: 'Georgia', serif; font-size: 2.25rem; font-weight: 900; color: var(--gal-accent); line-height: 1; margin-bottom: 0.25rem; }
.stat-label-gal { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.stat-trend-gal {
    font-size: 0.72rem; display: flex; align-items: center; gap: 0.3rem; font-weight: 600;
    padding: 0.25rem 0.55rem; border-radius: 999px; width: fit-content;
}
.stat-trend-gal.up { background: rgba(16,185,129,0.1); color: #059669; }
.stat-trend-gal.neutral { background: var(--bg-tertiary); color: var(--text-muted); }

.chart-section { display: grid; grid-template-columns: 1.3fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.chart-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); }
.chart-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; font-family: 'Georgia', serif; color: var(--text-primary); }

.quick-filter-pills {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; padding: 0.5rem;
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); align-items: center;
}
.pill {
    padding: 0.5rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 999px;
    font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none;
}
.pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); border-color: var(--gal-accent); }
.pill.active { background: var(--gal-gradient); color: white; border-color: var(--gal-accent); box-shadow: 0 4px 12px rgba(124,58,237,0.3); }
.pill .pill-count { background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 800; min-width: 22px; text-align: center; }
.pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

.advanced-filter-bar { background: var(--bg-secondary); padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
.advanced-search-form { display: flex; gap: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.search-group { flex: 1; min-width: 280px; position: relative; }
.search-input-wrap { position: relative; }
.search-icon-pro { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1rem; color: var(--text-muted); pointer-events: none; }
.search-input-pro {
    width: 100%; padding: 0.75rem 3rem 0.75rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.95rem; transition: all 0.3s; font-family: inherit; background: var(--bg-primary); color: var(--text-primary);
}
.search-input-pro:focus { outline: none; border-color: var(--gal-accent); box-shadow: 0 0 0 4px rgba(139,92,246,0.1); }
.search-shortcut { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: var(--bg-tertiary); color: var(--text-muted); padding: 0.15rem 0.5rem; border-radius: 5px; font-size: 0.68rem; font-family: monospace; font-weight: 600; pointer-events: none; border: 1px solid var(--border); }
.search-clear-pro { position: absolute; right: 2.5rem; top: 50%; transform: translateY(-50%); background: var(--bg-tertiary); border: none; font-size: 1rem; cursor: pointer; color: var(--text-muted); width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.search-clear-pro:hover { background: var(--gal-accent); color: white; }

.filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.filter-select-pro, .date-filter-pro { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-size: 0.9rem; background: var(--bg-primary); cursor: pointer; transition: all 0.3s; font-family: inherit; color: var(--text-primary); }
.filter-select-pro:focus, .date-filter-pro:focus { outline: none; border-color: var(--gal-accent); }

.view-toggle-pro { display: flex; background: var(--bg-tertiary); border-radius: var(--radius-md); padding: 0.25rem; gap: 0.25rem; border: 1px solid var(--border); }
.view-btn-pro { padding: 0.5rem 0.85rem; border-radius: 7px; border: none; background: transparent; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; color: var(--text-muted); text-decoration: none; font-size: 0.82rem; font-weight: 600; font-family: inherit; }
.view-btn-pro.active { background: var(--bg-primary); color: var(--gal-accent); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
.view-btn-pro:hover:not(.active) { background: var(--bg-secondary); color: var(--text-primary); }

.btn-reset-filter { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); background: var(--bg-primary); cursor: pointer; font-weight: 600; transition: all 0.3s; font-family: inherit; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.3rem; }
.btn-reset-filter:hover { background: var(--bg-tertiary); border-color: var(--gal-accent); color: var(--gal-accent); }

.category-chips-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-lg); border: 1px solid var(--border); align-items: center; }
.category-chips-label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-right: 0.5rem; }
.chip-pro { padding: 0.4rem 0.9rem; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-primary); cursor: pointer; font-weight: 600; font-size: 0.78rem; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; color: var(--text-secondary); }
.chip-pro:hover { border-color: var(--gal-accent); transform: translateY(-2px); color: var(--gal-accent); }
.chip-pro.active { background: var(--gal-gradient); color: white; border-color: var(--gal-accent); box-shadow: 0 4px 12px rgba(124,58,237,0.3); }
.chip-count-pro { background: var(--bg-tertiary); padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px; text-align: center; }
.chip-pro.active .chip-count-pro { background: rgba(255,255,255,0.3); color: white; }

.card-header-pro { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border); flex-wrap: wrap; gap: 1rem; }
.header-left h2 { font-family: 'Georgia', serif; font-size: 1.5rem; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
.count-badge-pro { background: var(--gal-gradient); color: white; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; }
.header-actions-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.btn-action-pro { padding: 0.7rem 1.15rem; border-radius: var(--radius-md); border: 2px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.85rem; font-family: inherit; white-space: nowrap; }
.btn-action-pro:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--gal-accent); color: var(--gal-accent); }
.btn-action-pro.primary { background: var(--gal-gradient); color: white; border-color: var(--gal-accent); box-shadow: 0 4px 12px rgba(124,58,237,0.3); }
.btn-action-pro.primary:hover { color: white; box-shadow: 0 8px 20px rgba(124,58,237,0.4); }

.export-dropdown { position: relative; }
.export-menu { position: absolute; top: calc(100% + 6px); right: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); min-width: 220px; display: none; z-index: 50; overflow: hidden; }
.export-menu.show { display: block; animation: menuPop 0.2s ease; }
@keyframes menuPop { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.export-item { padding: 0.7rem 1rem; display: flex; align-items: center; gap: 0.7rem; color: var(--text-primary); text-decoration: none; font-size: 0.85rem; transition: background 0.15s; border-bottom: 1px solid var(--border); }
.export-item:last-child { border-bottom: none; }
.export-item:hover { background: var(--bg-secondary); }
.export-item-icon { font-size: 1.1rem; width: 22px; text-align: center; }

.bulk-bar-pro {
    background: var(--gal-gradient); color: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg);
    margin-bottom: 1.5rem; display: none; align-items: center; gap: 1rem; flex-wrap: wrap;
    animation: slideDown 0.3s ease; box-shadow: 0 10px 30px rgba(124,58,237,0.3); position: relative; overflow: hidden;
}
.bulk-bar-pro::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: rgba(255,255,255,0.3); }
.bulk-bar-pro.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info-pro { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: var(--gal-accent); padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.82rem; font-weight: 800; }
.bulk-actions-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-btn-pro { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 0.3rem; }
.bulk-btn-pro:hover { transform: translateY(-2px); }
.bulk-btn-pro.publish { background: #10b981; color: white; }
.bulk-btn-pro.draft { background: #f59e0b; color: white; }
.bulk-btn-pro.delete { background: #dc2626; color: white; }
.bulk-btn-pro.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

.grid-container-pro { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; padding: 1.5rem; }
.gal-card-pro {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg);
    overflow: hidden; transition: all 0.3s; position: relative; display: flex; flex-direction: column; cursor: pointer;
}
.gal-card-pro::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--gal-gradient); z-index: 2; }
.gal-card-pro:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--gal-accent); }
.gal-card-pro.selected { border-color: var(--gal-accent); background: rgba(139,92,246,0.05); }

.grid-check-pro { position: absolute; top: 0.75rem; left: 0.75rem; z-index: 3; }
.grid-check-pro input { width: 20px; height: 20px; cursor: pointer; accent-color: var(--gal-accent); }

.gal-thumb-pro { height: 180px; overflow: hidden; background: var(--bg-tertiary); position: relative; }
.gal-thumb-pro img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.gal-card-pro:hover .gal-thumb-pro img { transform: scale(1.05); }
.gal-thumb-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; background: var(--gal-gradient); color: white; }
.gal-overlay-pro { position: absolute; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; color: white; font-size: 2rem; }
.gal-card-pro:hover .gal-overlay-pro { opacity: 1; }

.gal-content-pro { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
.gal-badges-pro { display: flex; gap: 0.4rem; margin-bottom: 0.65rem; flex-wrap: wrap; }
.gal-content-pro h3 { font-size: 1rem; margin: 0.5rem 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-family: 'Georgia', serif; font-weight: 700; color: var(--text-primary); }
.gal-content-pro p { font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
.gal-meta-pro { display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted); padding-top: 0.75rem; border-top: 1px dashed var(--border); }
.meta-item { display: flex; align-items: center; gap: 0.3rem; }
.meta-item strong { color: var(--gal-accent); font-weight: 800; }

.gal-actions-pro { display: flex; border-top: 1px solid var(--border); background: var(--bg-secondary); }
.gal-action-btn { flex: 1; padding: 0.7rem; background: transparent; border: none; cursor: pointer; font-size: 1rem; transition: all 0.2s; border-right: 1px solid var(--border); color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; justify-content: center; }
.gal-action-btn:last-child { border-right: none; }
.gal-action-btn:hover { background: var(--bg-primary); color: var(--gal-accent); }
.gal-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }

.select-all-grid { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; cursor: pointer; font-weight: 600; font-size: 0.88rem; padding: 0.5rem 0.75rem; background: var(--bg-secondary); border-radius: var(--radius-md); width: fit-content; border: 1px solid var(--border); }
.select-all-grid input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--gal-accent); }

.timeline-view { padding: 1.5rem; }
.timeline-group { margin-bottom: 2rem; }
.timeline-group-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border); position: sticky; top: 0; background: var(--bg-primary); z-index: 5; padding-top: 0.5rem; }
.timeline-month { font-family: 'Georgia', serif; font-size: 1.5rem; font-weight: 900; color: var(--gal-accent); letter-spacing: -0.02em; }
.timeline-count { background: var(--gal-gradient); color: white; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; }
.timeline-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0.85rem; }
.timeline-item { display: flex; gap: 0.85rem; padding: 0.85rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); transition: all 0.2s; cursor: pointer; align-items: center; }
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(3px); border-color: var(--gal-accent); }
.timeline-date-box { width: 55px; text-align: center; background: var(--gal-gradient); color: white; border-radius: 8px; padding: 0.5rem 0.25rem; flex-shrink: 0; }
.timeline-day { font-size: 1.3rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.timeline-month-small { font-size: 0.65rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Georgia', serif; display: flex; align-items: center; gap: 0.3rem; color: var(--text-primary); }
.timeline-item-role { font-size: 0.72rem; color: var(--text-muted); }

.table-wrapper-pro { overflow-x: auto; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); background: var(--bg-primary); }
.gal-table-premium { width: 100%; border-collapse: collapse; }
.gal-table-premium thead { background: var(--bg-secondary); }
.gal-table-premium th { padding: 0.85rem 1rem; text-align: left; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 2px solid var(--border); user-select: none; position: sticky; top: 0; z-index: 5; background: var(--bg-secondary); }
.gal-table-premium td { padding: 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; transition: all 0.2s; }
.gal-table-premium tbody tr { transition: all 0.2s; }
.gal-table-premium tbody tr:hover { background: var(--bg-secondary); }
.gal-table-premium tbody tr.selected { background: rgba(139,92,246,0.05); }
.row-checkbox { width: 18px; height: 18px; accent-color: var(--gal-accent); cursor: pointer; }

.table-thumb-pro { width: 70px; height: 50px; border-radius: 8px; overflow: hidden; cursor: pointer; background: var(--bg-tertiary); position: relative; flex-shrink: 0; transition: transform 0.2s; }
.table-thumb-pro:hover { transform: scale(1.05); }
.table-thumb-pro img { width: 100%; height: 100%; object-fit: cover; }
.thumb-placeholder-pro { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: var(--gal-gradient); color: white; }

.title-cell-pro strong { display: flex; align-items: center; gap: 0.3rem; font-size: 0.95rem; margin-bottom: 0.25rem; line-height: 1.3; color: var(--text-primary); }
.title-cell-pro small { display: block; font-size: 0.72rem; color: var(--text-muted); font-family: monospace; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.badge-pro { padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; display: inline-flex; align-items: center; gap: 0.3rem; }
.badge-published { background: #dcfce7; color: #166534; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-category { background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; }

.action-buttons-pro { display: flex; gap: 0.25rem; justify-content: flex-end; }
.act-btn-pro { width: 34px; height: 34px; border-radius: 8px; border: none; background: var(--bg-tertiary); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 0.95rem; text-decoration: none; color: var(--text-secondary); }
.act-btn-pro:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.act-btn-pro.view:hover { background: #8b5cf6; color: white; }
.act-btn-pro.edit:hover { background: #3b82f6; color: white; }
.act-btn-pro.toggle:hover { background: #f59e0b; color: white; }
.act-btn-pro.danger:hover { background: #dc2626; color: white; }

.pagination-premium { display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border); flex-wrap: wrap; gap: 1rem; }
.pagination-info { font-size: 0.88rem; color: var(--text-muted); }
.pagination-info strong { color: var(--text-primary); font-weight: 700; }
.pagination-buttons { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
.page-btn-pro { padding: 0.5rem 0.85rem; border: 2px solid var(--border); border-radius: 8px; background: var(--bg-primary); cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-family: inherit; }
.page-btn-pro:hover:not(.current) { border-color: var(--gal-accent); color: var(--gal-accent); transform: translateY(-2px); }
.page-btn-pro.current { background: var(--gal-gradient); color: white; border-color: var(--gal-accent); box-shadow: 0 4px 12px rgba(124,58,237,0.3); }
.page-dots { padding: 0 0.4rem; color: var(--text-muted); }

.empty-state-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-animation { position: relative; width: 140px; height: 140px; margin: 0 auto 1.5rem; }
.empty-circle-pro { position: absolute; inset: 0; background: var(--gal-gradient); border-radius: 50%; animation: emptyPulse 3s ease-in-out infinite; opacity: 0.15; }
@keyframes emptyPulse { 0%, 100% { transform: scale(1); opacity: 0.15; } 50% { transform: scale(1.1); opacity: 0.05; } }
.empty-icon-pro { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 4rem; animation: emptyFloat 3s ease-in-out infinite; }
@keyframes emptyFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.empty-state-premium h3 { font-size: 1.35rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; font-weight: 700; color: var(--text-primary); }
.empty-state-premium p { color: var(--text-muted); max-width: 400px; margin: 0 auto 1.5rem; line-height: 1.6; }
.empty-actions-pro { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

.modal-overlay-premium { position: fixed; inset: 0; background: rgba(15,23,42,0.85); backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 1.5rem; }
.modal-overlay-premium.open { display: flex; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-content { background: var(--bg-primary); border-radius: var(--radius-xl); width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto; position: relative; animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 25px 50px rgba(0,0,0,0.3); border: 1px solid var(--border); }
@keyframes slideUp { from { transform: translateY(20px) scale(0.95); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
.modal-header-gal { padding: 2rem; background: var(--gal-gradient); color: white; border-radius: var(--radius-xl) var(--radius-xl) 0 0; position: relative; overflow: hidden; }
.modal-close-premium { position: absolute; top: 1rem; right: 1rem; width: 40px; height: 40px; background: rgba(255,255,255,0.2); border: none; border-radius: 50%; cursor: pointer; font-size: 1.25rem; color: white; transition: all 0.3s; display: flex; align-items: center; justify-content: center; z-index: 2; }
.modal-close-premium:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
.modal-title { font-family: 'Georgia', serif; font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; position: relative; z-index: 1; }
.modal-meta { display: flex; gap: 1rem; font-size: 0.85rem; opacity: 0.95; flex-wrap: wrap; position: relative; z-index: 1; }
.modal-meta-item { display: flex; align-items: center; gap: 0.35rem; }
.modal-body { padding: 2rem; }
.modal-img-preview { width: 100%; border-radius: var(--radius-md); overflow: hidden; margin-bottom: 1.5rem; border: 1px solid var(--border); }
.modal-img-preview img { width: 100%; max-height: 400px; object-fit: contain; background: var(--bg-tertiary); }
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.detail-item { padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border); }
.detail-item-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 700; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.3rem; }
.detail-item-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); word-break: break-word; }
.modal-actions-list { display: flex; flex-direction: column; gap: 0.75rem; }

@media (max-width: 1024px) { .chart-section { grid-template-columns: 1fr; } .gal-stats-grid { grid-template-columns: repeat(2, 1fr); } .detail-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .gal-hero-content { flex-direction: column; text-align: center; }
    .hero-stats { justify-content: center; }
    .card-header-pro { flex-direction: column; align-items: flex-start; }
    .header-actions-pro { width: 100%; }
    .btn-action-pro { flex: 1; justify-content: center; }
    .advanced-search-form { flex-direction: column; }
    .search-group { min-width: 100%; }
    .filter-group { width: 100%; }
    .filter-select-pro, .date-filter-pro, .btn-reset-filter { flex: 1; min-width: 0; }
    .grid-container-pro { grid-template-columns: 1fr; padding: 1rem; }
    .gal-table-premium { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .gal-stats-grid { grid-template-columns: 1fr; }
    .stat-number-gal { font-size: 1.85rem; }
    .bulk-bar-pro { flex-direction: column; align-items: stretch; }
    .bulk-actions-pro { flex-direction: column; }
    .bulk-btn-pro { width: 100%; }
    .category-chips-pro { padding: 0.5rem; }
    .chip-pro { font-size: 0.72rem; padding: 0.35rem 0.7rem; }
}
@media print {
    .advanced-filter-bar, .bulk-bar-pro, .action-buttons-pro, .chart-section, .gal-stats-grid, .gal-hero, .category-chips-pro, .modal-overlay-premium, .pagination-premium, .header-actions-pro { display: none !important; }
    .table-wrapper-pro { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<!-- ===== HERO ===== -->
<div class="gal-hero" data-aos="fade-down">
  <div class="gal-hero-content">
    <div>
      <h2>📸 Galeri Dokumentasi</h2>
      <p>Kelola foto kegiatan, prestasi, dan momen penting FKIP UNIMOF. Pantau distribusi kategori dan publikasi secara real-time.</p>
      <div class="hero-stats">
        <div class="hero-stat"><span class="hero-stat-num"><?= $stat_total ?></span><span class="hero-stat-label">Total</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= $stat_published ?></span><span class="hero-stat-label">Published</span></div>
        <div class="hero-stat"><span class="hero-stat-num"><?= count($kat_list) ?></span><span class="hero-stat-label">Kategori</span></div>
        <div class="hero-stat"><span class="hero-stat-num">+<?= $stat_week ?></span><span class="hero-stat-label">Minggu Ini</span></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== STATS GRID ===== -->
<div class="gal-stats-grid" data-aos="fade-up">
  <div class="stat-card-gal" style="--stat-color: #8b5cf6;">
    <div class="stat-header-gal"><div class="stat-icon-gal">📸</div></div>
    <div class="stat-number-gal count-up" data-target="<?= $stat_total ?>">0</div>
    <div class="stat-label-gal">Total Foto</div>
    <div class="stat-trend-gal neutral">📚 Semua data</div>
  </div>
  <div class="stat-card-gal" style="--stat-color: #10b981;">
    <div class="stat-header-gal"><div class="stat-icon-gal">✅</div></div>
    <div class="stat-number-gal count-up" data-target="<?= $stat_published ?>">0</div>
    <div class="stat-label-gal">Published</div>
    <div class="stat-trend-gal up">🟢 <?= $stat_total > 0 ? round($stat_published / $stat_total * 100) : 0 ?>% dari total</div>
  </div>
  <div class="stat-card-gal" style="--stat-color: #f59e0b;">
    <div class="stat-header-gal"><div class="stat-icon-gal">📝</div></div>
    <div class="stat-number-gal count-up" data-target="<?= $stat_draft ?>">0</div>
    <div class="stat-label-gal">Draft</div>
    <div class="stat-trend-gal neutral">⏳ Perlu review</div>
  </div>
  <div class="stat-card-gal" style="--stat-color: #ec4899;">
    <div class="stat-header-gal"><div class="stat-icon-gal">📂</div></div>
    <div class="stat-number-gal count-up" data-target="<?= count($kat_list) ?>">0</div>
    <div class="stat-label-gal">Kategori Unik</div>
    <div class="stat-trend-gal neutral">🏷️ Terorganisir</div>
  </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($kat_stats) || !empty($monthly)): ?>
<div class="chart-section" data-aos="fade-up">
  <div class="chart-card">
    <h3>📊 Tren Upload (6 Bulan Terakhir)</h3>
    <div id="trendChart"></div>
  </div>
  <div class="chart-card">
    <h3>🏆 Distribusi Kategori (Published)</h3>
    <div id="categoryChart"></div>
  </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
  <a href="galeri.php" class="pill <?= empty($status_filter) && empty($kat_filter) ? 'active' : '' ?>">📸 Semua <span class="pill-count"><?= $stat_total ?></span></a>
  <a href="galeri.php?status=Published" class="pill <?= $status_filter === 'Published' ? 'active' : '' ?>">✅ Published <span class="pill-count"><?= $stat_published ?></span></a>
  <a href="galeri.php?status=Draft" class="pill <?= $status_filter === 'Draft' ? 'active' : '' ?>">📝 Draft <span class="pill-count"><?= $stat_draft ?></span></a>
  <div style="flex: 1;"></div>
</div>

<div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:var(--radius-xl);padding:1.5rem;box-shadow:var(--shadow-sm)" data-aos="fade-up">
  <div class="card-header-pro">
    <div class="header-left">
      <h2>📸 Daftar Foto <span class="count-badge-pro"><?= $total ?></span></h2>
      <p style="color:var(--text-muted);font-size:.82rem;margin-top:.25rem">Kelola dokumentasi visual kegiatan FKIP UNIMOF</p>
    </div>
    <div class="header-actions-pro">
      <div class="export-dropdown">
        <button class="btn-action-pro" onclick="toggleExportMenu(event)"><span>📥</span><span>Export</span><span>▾</span></button>
        <div class="export-menu" id="exportMenu">
          <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="export-item"><span class="export-item-icon">📊</span><div><div style="font-weight:600">Export CSV</div><div style="font-size:.72rem;color:var(--text-muted)">Excel/Spreadsheet</div></div></a>
          <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'json'])) ?>" class="export-item"><span class="export-item-icon">🔧</span><div><div style="font-weight:600">Export JSON</div><div style="font-size:.72rem;color:var(--text-muted)">Integrasi API</div></div></a>
          <a href="#" onclick="window.print();return false" class="export-item"><span class="export-item-icon">🖨️</span><div><div style="font-weight:600">Print PDF</div><div style="font-size:.72rem;color:var(--text-muted)">Cetak laporan</div></div></a>
        </div>
      </div>
      <a href="galeri-form.php" class="btn-action-pro primary"><span>➕</span><span>Upload Foto</span></a>
    </div>
  </div>

  <!-- ADVANCED FILTER -->
  <div class="advanced-filter-bar">
    <form method="GET" class="advanced-search-form" id="searchForm">
      <div class="search-group"><div class="search-input-wrap"><span class="search-icon-pro">🔍</span>
        <input type="text" name="q" class="search-input-pro" placeholder="Cari judul atau deskripsi..." value="<?= sanitize($q) ?>" autocomplete="off">
        <?php if($q!==''): ?><button type="button" class="search-clear-pro" onclick="clearSearch()">✕</button><?php endif; ?>
        <span class="search-shortcut">/</span>
      </div></div>
      <div class="filter-group">
        <select name="kategori" class="filter-select-pro" onchange="this.form.submit()"><option value="">📁 Semua Kategori</option><?php foreach($kat_list as $k): ?><option value="<?= sanitize($k) ?>" <?= $kat_filter===$k?'selected':'' ?>><?= sanitize($k) ?></option><?php endforeach; ?></select>
        <select name="status" class="filter-select-pro" onchange="this.form.submit()"><option value="">📊 Semua Status</option><option value="Published" <?= $status_filter==='Published'?'selected':'' ?>>✅ Published</option><option value="Draft" <?= $status_filter==='Draft'?'selected':'' ?>>📝 Draft</option></select>
        <input type="date" name="tanggal" class="date-filter-pro" value="<?= sanitize($date_filter) ?>" onchange="this.form.submit()" title="Filter tanggal">
        <button type="button" class="btn-reset-filter" onclick="resetFilters()" title="Reset">🔄 Reset</button>
      </div>
      <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
    </form>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:.75rem">
      <div class="view-toggle-pro">
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'grid'])) ?>" class="view-btn-pro <?= $view_mode==='grid'?'active':'' ?>">🎴 <span>Grid</span></a>
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'timeline'])) ?>" class="view-btn-pro <?= $view_mode==='timeline'?'active':'' ?>">📅 <span>Timeline</span></a>
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'table'])) ?>" class="view-btn-pro <?= $view_mode==='table'?'active':'' ?>">📋 <span>Tabel</span></a>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;font-size:.78rem;color:var(--text-muted)"><span>Sort:</span>
        <select onchange="sortGaleri(this.value)" style="padding:.4rem .75rem;border:1px solid var(--border);border-radius:6px;font-size:.78rem;background:var(--bg-primary);cursor:pointer;color:var(--text-primary)">
          <option value="tanggal-desc" <?= $sort_by==='tanggal'&&$sort_dir==='DESC'?'selected':'' ?>>Terbaru</option>
          <option value="tanggal-asc" <?= $sort_by==='tanggal'&&$sort_dir==='ASC'?'selected':'' ?>>Terlama</option>
          <option value="judul-asc" <?= $sort_by==='judul'&&$sort_dir==='ASC'?'selected':'' ?>>Judul A-Z</option>
        </select>
      </div>
    </div>
  </div>

  <!-- CATEGORY CHIPS -->
  <div class="category-chips-pro">
    <div class="category-chips-label">📁 Kategori:</div>
    <a class="chip-pro <?= $kat_filter===''?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>'','halaman'=>1])) ?>">Semua</a>
    <?php foreach($kat_list as $k): 
        $cstmt = $pdo->prepare("SELECT COUNT(*) FROM galeri WHERE kategori = ?"); $cstmt->execute([$k]); $cn = (int)$cstmt->fetchColumn();
    ?>
      <a class="chip-pro <?= $kat_filter===$k?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['kategori'=>$k,'halaman'=>1])) ?>"><?= sanitize($k) ?> <span class="chip-count-pro"><?= $cn ?></span></a>
    <?php endforeach; ?>
  </div>

  <!-- BULK BAR -->
  <div class="bulk-bar-pro" id="bulkBar">
    <div class="bulk-info-pro"><span class="bulk-count" id="bulkCount">0</span><span>foto dipilih</span></div>
    <form method="POST" class="bulk-form-inline" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
      <div class="bulk-actions-pro">
        <button type="submit" name="action" value="bulk_publish" class="bulk-btn-pro publish" onclick="return confirm('Publikasikan foto terpilih?')">📤 Publish</button>
        <button type="submit" name="action" value="bulk_draft" class="bulk-btn-pro draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
        <button type="submit" name="action" value="bulk_delete" class="bulk-btn-pro delete" onclick="return confirm('HAPUS PERMANEN foto terpilih?')">🗑️ Hapus</button>
      </div>
    </form>
    <button class="bulk-btn-pro cancel" onclick="clearSelection()">Batal</button>
  </div>

  <?php if(empty($list)): ?>
  <div class="empty-state-premium"><div class="empty-animation"><div class="empty-circle-pro"></div><div class="empty-icon-pro">📸</div></div>
    <h3><?= ($q||$kat_filter||$status_filter)?'Tidak ada hasil untuk filter ini':'Belum ada foto' ?></h3>
    <p><?= ($q||$kat_filter||$status_filter)?'Coba ubah kata kunci atau filter.':'Mulai unggah dokumentasi kegiatan FKIP UNIMOF.' ?></p>
    <div class="empty-actions-pro"><?php if($q||$kat_filter||$status_filter): ?><a href="galeri.php" class="btn-action-pro">🔄 Reset Filter</a><?php endif; ?><a href="galeri-form.php" class="btn-action-pro primary">➕ Upload Foto Pertama</a></div>
  </div>

  <?php elseif($view_mode==='grid'): ?>
  <div style="padding:1.5rem"><label class="select-all-grid"><input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')"><span>Pilih Semua (<?= count($list) ?>)</span></label>
    <div class="grid-container-pro" style="padding:0">
      <?php foreach($list as $g):
        $img = gal_thumb($g); $kat = trim($g['kategori'] ?? '') ?: 'Umum';
      ?>
      <div class="gal-card-pro" data-id="<?= (int)$g['id'] ?>" onclick="showDetail(<?= $g['id'] ?>)">
        <div class="grid-check-pro" onclick="event.stopPropagation()"><input type="checkbox" class="gal-chk" value="<?= (int)$g['id'] ?>" onchange="updateBulk()"></div>
        <div class="gal-thumb-pro">
          <?php if($img): ?><img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt="<?= sanitize($g['judul']) ?>" loading="lazy"><?php else: ?><div class="gal-thumb-placeholder">📷</div><?php endif; ?>
          <div class="gal-overlay-pro">🔍</div>
        </div>
        <div class="gal-content-pro">
          <div class="gal-badges-pro"><span class="badge-pro badge-<?= strtolower($g['status']) ?>"><?= $g['status'] ?></span><span class="badge-pro badge-category"><?= sanitize($kat) ?></span></div>
          <h3><?= sanitize($g['judul']) ?></h3>
          <p><?= excerpt($g['deskripsi']?:'', 80) ?></p>
          <div class="gal-meta-pro"><span class="meta-item">📅 <?= !empty($g['tanggal']) ? date('d M Y', strtotime($g['tanggal'])) : '-' ?></span></div>
        </div>
        <div class="gal-actions-pro" onclick="event.stopPropagation()">
          <a href="galeri-form.php?id=<?= (int)$g['id'] ?>" class="gal-action-btn" title="Edit">✏️</a>
          <form method="POST" style="display:inline;flex:1;" onsubmit="return confirm('Ubah status?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button type="submit" title="Toggle" style="width:100%">🔄</button></form>
          <form method="POST" style="display:inline;flex:1;" onsubmit="return confirm('Hapus foto ini?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button type="submit" class="danger" title="Hapus" style="width:100%">🗑️</button></form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php elseif($view_mode==='timeline'): ?>
  <div class="timeline-view">
    <?php 
    $grp=[]; foreach($list as $g){ $lb=date('F Y',strtotime($g['tanggal'] ?: $g['created_at'])); $grp[$lb][]=$g; } 
    foreach($grp as $m=>$its): 
    ?>
    <div class="timeline-group"><div class="timeline-group-header"><span style="font-size:1.5rem">📅</span><span class="timeline-month"><?= $m ?></span><span class="timeline-count"><?= count($its) ?> foto</span></div>
      <div class="timeline-items">
        <?php foreach($its as $g): ?>
        <div class="timeline-item" onclick="showDetail(<?= $g['id'] ?>)">
          <div class="timeline-date-box"><div class="timeline-day"><?= date('d',strtotime($g['tanggal'] ?: $g['created_at'])) ?></div><div class="timeline-month-small"><?= date('M',strtotime($g['tanggal'] ?: $g['created_at'])) ?></div></div>
          <div class="timeline-item-info"><div class="timeline-item-name"><?= sanitize($g['judul']) ?></div><div class="timeline-item-role"><?= sanitize(trim($g['kategori']?:'Umum')) ?> • <?= $g['status'] ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="table-wrapper-pro">
    <table class="gal-table-premium" id="galTable">
      <thead><tr>
        <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll('table')"></th>
        <th style="width:80px">Foto</th>
        <th data-sortable="judul">Judul & Deskripsi</th>
        <th data-sortable="kategori" style="width:120px">Kategori</th>
        <th data-sortable="status" style="width:100px">Status</th>
        <th data-sortable="tanggal" style="width:120px">Tanggal</th>
        <th style="width:150px;text-align:right">Aksi</th>
      </tr></thead>
      <tbody>
        <?php foreach($list as $g): $img = gal_thumb($g); $kat = trim($g['kategori'] ?? '') ?: 'Umum'; ?>
        <tr data-id="<?= (int)$g['id'] ?>" class="<?= strtolower($g['status'])==='published'?'':'opacity-75' ?>">
          <td><input type="checkbox" class="gal-chk row-checkbox" value="<?= (int)$g['id'] ?>" onchange="updateBulk()"></td>
          <td><div class="table-thumb-pro" onclick="showDetail(<?= $g['id'] ?>)"><?php if($img): ?><img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt=""><?php else: ?><div class="thumb-placeholder-pro">📷</div><?php endif; ?></div></td>
          <td><div class="title-cell-pro"><strong><?= sanitize($g['judul']) ?></strong><small><?= excerpt($g['deskripsi']?:'', 60) ?></small></div></td>
          <td><span class="badge-pro badge-category"><?= sanitize($kat) ?></span></td>
          <td><span class="badge-pro badge-<?= strtolower($g['status']) ?>"><?= $g['status'] ?></span></td>
          <td><div style="font-size:.85rem"><?= !empty($g['tanggal']) ? date('d M Y', strtotime($g['tanggal'])) : '-' ?></div></td>
          <td><div class="action-buttons-pro">
            <button class="act-btn-pro view" onclick="showDetail(<?= $g['id'] ?>)" title="Detail">👁️</button>
            <a href="galeri-form.php?id=<?= (int)$g['id'] ?>" class="act-btn-pro edit" title="Edit">✏️</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="act-btn-pro toggle" title="Toggle">🔄</button></form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus foto?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="act-btn-pro danger" title="Hapus">🗑️</button></form>
          </div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if($total_pages>1): $bq=$_GET; ?>
  <nav class="pagination-premium"><div class="pagination-info">Menampilkan <strong><?= min($offset+1,$total) ?>-<?= min($offset+$per_page,$total) ?></strong> dari <strong><?= $total ?></strong> foto</div>
    <div class="pagination-buttons">
      <?php if($halaman>1): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman-1])) ?>" class="page-btn-pro">← Prev</a><?php endif;
      for($i=1;$i<=$total_pages;$i++){ if($i===1||$i===$total_pages||($i>=$halaman-2&&$i<=$halaman+2)){ echo $i===$halaman?"<span class='page-btn-pro current'>$i</span>":"<a href='?".http_build_query(array_merge($bq,['halaman'=>$i]))."' class='page-btn-pro'>$i</a>"; } elseif($i===$halaman-3||$i===$halaman+3){ echo "<span class='page-dots'>…</span>"; } }
      if($halaman<$total_pages): ?><a href="?<?= http_build_query(array_merge($bq,['halaman'=>$halaman+1])) ?>" class="page-btn-pro">Next →</a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay-premium" id="detailModal" onclick="if(event.target===this)closeDetailModal()">
  <div class="modal-content">
    <button class="modal-close-premium" onclick="closeDetailModal()">✕</button>
    <div class="modal-header-gal">
      <h2 class="modal-title" id="modalTitle">-</h2>
      <div class="modal-meta">
        <span class="modal-meta-item" id="modalKat">📁 -</span>
        <span class="modal-meta-item" id="modalDate">📅 -</span>
        <span class="modal-meta-item" id="modalStatus">📊 -</span>
      </div>
    </div>
    <div class="modal-body">
      <div class="modal-img-preview" id="modalImg"></div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-item-label">📝 Deskripsi</div><div class="detail-item-value" id="modalDesc">-</div></div>
        <div class="detail-item"><div class="detail-item-label">🕒 Ditambahkan</div><div class="detail-item-value" id="modalCreated">-</div></div>
      </div>
      <div class="modal-actions-list">
        <a href="#" id="modalEditBtn" class="btn-action-pro primary" style="justify-content:flex-start">✏️ Edit Foto Ini</a>
      </div>
    </div>
  </div>
</div>

<!-- ===== HIDDEN FORMS ===== -->
<form id="bulkFormHidden" method="POST" style="display:none"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"></form>

<script>
const galData = <?= json_encode($list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

// ===== COUNTER ANIMATION =====
function animateCount(el){const t=parseInt(el.dataset.target)||0,d=1800,s=performance.now();(function n(now){const p=Math.min((now-s)/d,1),e=1-Math.pow(1-p,3);el.textContent=Math.floor(e*t).toLocaleString('id-ID');if(p<1)requestAnimationFrame(n);})(s);}
const cObs=new IntersectionObserver(es=>es.forEach(x=>{if(x.isIntersecting){animateCount(x.target);cObs.unobserve(x.target);}}),{threshold:.3});
document.querySelectorAll('.count-up').forEach(el=>cObs.observe(el));

// ===== CHARTS =====
<?php if(!empty($monthly)): ?>
new ApexCharts(document.querySelector("#trendChart"),{series:[{name:'Foto',data:<?= json_encode(array_values($monthly)) ?>}],chart:{type:'area',height:260,toolbar:{show:false}},colors:['#8b5cf6'],fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.5,opacityTo:.1}},stroke:{curve:'smooth',width:3},xaxis:{categories:<?= json_encode(array_keys($monthly)) ?>,labels:{style:{fontSize:'10px'}}},yaxis:{labels:{style:{fontSize:'11px'}}},dataLabels:{enabled:false},tooltip:{y:{formatter:v=>v+' foto'}}}).render();
<?php endif; ?>
<?php if(!empty($kat_stats)): ?>
new ApexCharts(document.querySelector("#categoryChart"),{series:<?= json_encode(array_values($kat_stats)) ?>,labels:<?= json_encode(array_keys($kat_stats)) ?>,chart:{type:'donut',height:280},colors:['#8b5cf6','#ec4899','#3b82f6','#10b981','#f59e0b','#64748b'],plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Total',formatter:()=><?= array_sum(array_values($kat_stats)) ?>}}}}},dataLabels:{enabled:true,style:{fontSize:'11px',fontWeight:700}},legend:{position:'bottom',fontSize:'11px'},stroke:{show:true,colors:['var(--bg-primary)'],width:3}}).render();
<?php endif; ?>

// ===== SEARCH & FILTER =====
let sT;document.querySelector('.search-input-pro')?.addEventListener('input',function(){clearTimeout(sT);const v=this.value;sT=setTimeout(()=>{const u=new URL(window.location);v?u.searchParams.set('q',v):u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;},500);});
function clearSearch(){const u=new URL(window.location);u.searchParams.delete('q');u.searchParams.set('halaman','1');window.location=u;}
function resetFilters(){if(confirm('Reset semua filter?'))window.location='galeri.php';}
function sortGaleri(v){const[s,d]=v.split('-');const u=new URL(window.location);u.searchParams.set('sort',s);u.searchParams.set('dir',d);window.location=u;}
function toggleExportMenu(e){e.stopPropagation();document.getElementById('exportMenu').classList.toggle('show');}
document.addEventListener('click',e=>{if(!e.target.closest('.export-dropdown'))document.getElementById('exportMenu')?.classList.remove('show');});

// ===== BULK SELECTION =====
function toggleSelectAll(type){
    const m = type==='grid'?document.getElementById('selectAllGrid'):document.getElementById('selectAll');
    document.querySelectorAll('.gal-chk').forEach(cb=>{cb.checked=m.checked;cb.closest('[data-id]')?.classList.toggle('selected',m.checked);});
    updateBulk();
}
document.querySelectorAll('.gal-chk').forEach(cb=>cb.addEventListener('change',function(){this.closest('[data-id]')?.classList.toggle('selected',this.checked);updateBulk();}));
function updateBulk(){
    const c=document.querySelectorAll('.gal-chk:checked').length;
    document.getElementById('bulkCount').textContent=c;
    document.getElementById('bulkBar').classList.toggle('show',c>0);
    const f=document.getElementById('bulkForm');
    f.querySelectorAll('input[name="ids[]"]').forEach(x=>x.remove());
    document.querySelectorAll('.gal-chk:checked').forEach(cb=>{const i=document.createElement('input');i.type='hidden';i.name='ids[]';i.value=cb.value;f.appendChild(i);});
    const all=document.querySelectorAll('.gal-chk'),ck=all.length>0&&c===all.length;
    const sa=document.getElementById('selectAll');if(sa)sa.checked=ck;
    const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=ck;
}
function clearSelection(){
    document.querySelectorAll('.gal-chk').forEach(cb=>cb.checked=false);
    document.querySelectorAll('[data-id]').forEach(r=>r.classList.remove('selected'));
    const sa=document.getElementById('selectAll');if(sa)sa.checked=false;
    const sg=document.getElementById('selectAllGrid');if(sg)sg.checked=false;
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== DETAIL MODAL =====
function showDetail(id){
    const d=galData.find(g=>g.id==id); if(!d) return;
    const img = d.gambar ? `<?= str_replace('uploads/galeri/', '', asset('uploads/galeri/')) ?>` + d.gambar : '';
    const fullImg = d.gambar ? `<?= asset('uploads/galeri/') ?>` + d.gambar : '';
    
    document.getElementById('modalTitle').textContent = d.judul || 'Tanpa Judul';
    document.getElementById('modalKat').textContent = '📁 ' + (d.kategori || 'Umum');
    document.getElementById('modalDate').textContent = '📅 ' + (d.tanggal ? new Date(d.tanggal).toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'}) : '-');
    document.getElementById('modalStatus').innerHTML = `<span class="badge-pro badge-${(d.status||'draft').toLowerCase()}">${d.status || 'Draft'}</span>`;
    document.getElementById('modalDesc').textContent = d.deskripsi || 'Tidak ada deskripsi.';
    document.getElementById('modalCreated').textContent = d.created_at ? new Date(d.created_at).toLocaleString('id-ID') : '-';
    document.getElementById('modalImg').innerHTML = fullImg ? `<img src="${fullImg}" alt="${d.judul}">` : '<div style="padding:4rem;text-align:center;font-size:4rem;background:var(--bg-tertiary);color:var(--text-muted)">📷</div>';
    document.getElementById('modalEditBtn').href = `galeri-form.php?id=${d.id}`;
    
    document.getElementById('detailModal').classList.add('open');
    document.body.style.overflow='hidden';
}
function closeDetailModal(){document.getElementById('detailModal').classList.remove('open');document.body.style.overflow='';}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){closeDetailModal();}
    if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)){e.preventDefault();document.querySelector('.search-input-pro')?.focus();}
    if((e.ctrlKey||e.metaKey)&&e.key==='n'){e.preventDefault();window.location.href='galeri-form.php';}
    if((e.ctrlKey||e.metaKey)&&e.key==='a'&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)){
        e.preventDefault();const sa=document.getElementById('selectAll');if(sa){sa.checked=!sa.checked;toggleSelectAll('table');}
    }
});

// ===== HIGHLIGHT SEARCH =====
function hlSearch(){const q=<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>;if(!q)return;document.querySelectorAll('.title-cell-pro strong, .gal-content-pro h3, .timeline-item-name').forEach(el=>{if(!el.querySelector('mark')){el.innerHTML=el.innerHTML.replace(new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi'),'<mark>$1</mark>');}});}
hlSearch();

console.log('%c📸 Galeri FKIP UNIMOF - EXTREME MULTIMATE','color:#8b5cf6;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Cari), Ctrl+N (Upload), Ctrl+A (Select All), Esc (Tutup)','color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>