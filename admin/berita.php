<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== AUTO-UPDATE: Arsip berita lama (> 2 tahun) =====
try {
    $pdo->exec("UPDATE berita SET status = 'Archived' WHERE status = 'Published' AND created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)");
} catch (Exception $e) {}

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', '❌ Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));

        if ($action === 'bulk_delete' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT gambar FROM berita WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $g) {
                if (function_exists('delete_upload')) delete_upload($g);
            }
            $pdo->prepare("DELETE FROM berita WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' berita berhasil dihapus.');
        }
        elseif ($action === 'bulk_publish' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET status='Published', published_at=IFNULL(published_at, NOW()) WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' berita dipublikasikan.');
        }
        elseif ($action === 'bulk_draft' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET status='Draft' WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' berita diubah ke draft.');
        }
        elseif ($action === 'bulk_archive' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET status='Archived' WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', '✅ ' . count($ids) . ' berita diarsipkan.');
        }
        elseif ($action === 'bulk_category' && !empty($ids) && !empty($_POST['new_category'])) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $category = sanitize($_POST['new_category']);
            $pdo->prepare("UPDATE berita SET kategori=? WHERE id IN ($placeholders)")->execute(array_merge([$category], $ids));
            flash_message('success', '✅ Kategori diubah untuk ' . count($ids) . ' berita.');
        }
        elseif ($action === 'bulk_feature' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET is_featured=1 WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', '⭐ ' . count($ids) . ' berita ditandai sebagai featured.');
        }
        elseif ($action === 'delete' && $id) {
            $stmt = $pdo->prepare("SELECT gambar FROM berita WHERE id = ?");
            $stmt->execute([$id]);
            if (function_exists('delete_upload')) delete_upload($stmt->fetchColumn());
            $pdo->prepare("DELETE FROM berita WHERE id = ?")->execute([$id]);
            flash_message('success', '✅ Berita berhasil dihapus.');
        }
        elseif ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE berita SET status = IF(status='Published','Draft','Published'), published_at = IF(status='Published', published_at, NOW()) WHERE id = ?")->execute([$id]);
            flash_message('success', '✅ Status berita diubah.');
        }
        elseif ($action === 'duplicate' && $id) {
            $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
            $stmt->execute([$id]);
            $orig = $stmt->fetch();
            if ($orig) {
                $slug = $orig['slug'] . '-copy-' . time();
                $pdo->prepare("INSERT INTO berita (judul,slug,konten,excerpt,gambar,kategori,penulis,status,is_featured,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())")
                    ->execute([$orig['judul'] . ' (Copy)', $slug, $orig['konten'], $orig['excerpt'], $orig['gambar'], $orig['kategori'], $orig['penulis'], 'Draft', 0]);
                flash_message('success', '✅ Berita berhasil diduplikasi sebagai draft.');
            }
        }
        elseif ($action === 'toggle_featured' && $id) {
            $pdo->prepare("UPDATE berita SET is_featured = IF(is_featured=1,0,1) WHERE id = ?")->execute([$id]);
            flash_message('success', '⭐ Status featured diubah.');
        }
    }
    header('Location: berita.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// ===== EXPORT HANDLER =====
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $all_berita = $pdo->query("SELECT * FROM berita ORDER BY created_at DESC")->fetchAll();

    if ($format === 'csv' && !empty($all_berita)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="berita-fkip-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Judul','Slug','Kategori','Status','Views','Penulis','Featured','Tanggal']);
        foreach ($all_berita as $r) {
            fputcsv($out, [$r['id'], $r['judul'], $r['slug'], $r['kategori'], $r['status'], $r['views'], $r['penulis'], $r['is_featured'] ? 'Ya' : 'Tidak', $r['created_at']]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' && !empty($all_berita)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="berita-fkip-' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exported_at' => date('c'),
            'total' => count($all_berita),
            'data' => $all_berita
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===== FILTER & PAGINATION =====
$q = trim($_GET['q'] ?? '');
$kat_filter = trim($_GET['kategori'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_filter = trim($_GET['tanggal'] ?? '');
$author_filter = trim($_GET['penulis'] ?? '');
$featured_filter = isset($_GET['featured']) ? (int)$_GET['featured'] : '';
$view_mode = $_GET['view'] ?? 'table';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';

$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = $view_mode === 'grid' ? 12 : 15;
$offset = ($halaman - 1) * $per_page;

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR konten LIKE ? OR slug LIKE ? OR penulis LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kat_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($date_filter !== '') { $where .= ' AND DATE(created_at) = ?'; $params[] = $date_filter; }
if ($author_filter !== '') { $where .= ' AND penulis LIKE ?'; $params[] = "%$author_filter%"; }
if ($featured_filter !== '') { $where .= ' AND is_featured = ?'; $params[] = $featured_filter; }

// Sort validation
$valid_sorts = ['created_at', 'judul', 'views', 'kategori', 'status'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_dir = in_array(strtolower($sort_dir), ['asc', 'desc']) ? strtoupper($sort_dir) : 'DESC';

$cs = $pdo->prepare("SELECT COUNT(*) FROM berita $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM berita $where ORDER BY $sort_by $sort_dir LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$berita = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_published = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();
$stat_draft = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
$stat_archived = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Archived'")->fetchColumn();
$stat_total_views = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM berita")->fetchColumn();
$stat_featured = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE is_featured=1")->fetchColumn();
$stat_today = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stat_week = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stat_month = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Trend: bulan ini vs bulan lalu
$month_prev = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$trend_month = $month_prev > 0 ? round((($stat_month - $month_prev) / $month_prev) * 100, 1) : 0;

// Average views per article
$avg_views = $stat_published > 0 ? round($stat_total_views / $stat_published) : 0;

// Engagement score (featured + views + recent)
$total_all = max(1, $stat_published + $stat_draft + $stat_archived);
$engagement_score = round(
    (($stat_featured * 10) + ($stat_published * 3) + min(100, $stat_total_views / 100)) / $total_all
);
$engagement_score = min(100, $engagement_score);

// Top categories
$category_stats = [];
$category_rows = $pdo->query("SELECT kategori, COUNT(*) as total, SUM(views) as views FROM berita WHERE status='Published' GROUP BY kategori ORDER BY total DESC LIMIT 5")->fetchAll();
foreach ($category_rows as $r) $category_stats[$r['kategori']] = ['count' => (int)$r['total'], 'views' => (int)$r['views']];

// Top authors
$author_stats = $pdo->query("SELECT penulis, COUNT(*) as total FROM berita WHERE status='Published' AND penulis IS NOT NULL AND penulis != '' GROUP BY penulis ORDER BY total DESC LIMIT 5")->fetchAll();

// Monthly trend (6 bulan terakhir)
$monthly_trend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $count = (int)$pdo->prepare("SELECT COUNT(*) FROM berita WHERE DATE_FORMAT(created_at, '%Y-%m') = ?")
        ->execute([$month]) ? (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn() : 0;
    $monthly_trend[$label] = $count;
}

// Daftar penulis unik untuk filter
$unique_authors = $pdo->query("SELECT DISTINCT penulis FROM berita WHERE penulis IS NOT NULL AND penulis != '' ORDER BY penulis")->fetchAll(PDO::FETCH_COLUMN);

$kategoris = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
$csrf = generate_csrf_token();

$active_menu = 'berita';
$page_heading = 'Kelola Berita';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Berita', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PAGE HERO (Journalism Theme - Navy/Red) ===== */
.berita-hero {
    background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #0f172a 100%);
    color: white;
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(30, 41, 59, 0.3);
}
.berita-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, #dc2626, #ef4444, #f87171);
}
.berita-hero::after {
    content: 'NEWS';
    position: absolute;
    top: 2rem;
    right: 2rem;
    font-family: 'Georgia', serif;
    font-size: 6rem;
    font-weight: 900;
    color: rgba(255,255,255,0.03);
    letter-spacing: 0.2em;
    pointer-events: none;
}
.berita-hero-content {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.berita-hero h2 {
    font-family: 'Georgia', serif;
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.02em;
}
.berita-hero p { opacity: 0.9; font-size: 0.95rem; max-width: 500px; line-height: 1.6; }
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
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.15);
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
    opacity: 0.85;
    margin-top: 0.25rem;
}

/* Engagement Score Ring */
.engagement-ring {
    width: 110px;
    height: 110px;
    position: relative;
    flex-shrink: 0;
}
.engagement-ring svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.engagement-ring .ring-bg { fill: none; stroke: rgba(255,255,255,0.15); stroke-width: 8; }
.engagement-ring .ring-fill { fill: none; stroke: #dc2626; stroke-width: 8; stroke-linecap: round; transition: stroke-dasharray 1.5s ease; }
.engagement-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}
.engagement-value .score-num { font-size: 1.85rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.engagement-value .score-label { font-size: 0.65rem; opacity: 0.85; margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== STATS GRID ===== */
.berita-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card-ultimate {
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
.stat-card-ultimate::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--stat-color, #1e293b);
}
.stat-card-ultimate:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, #1e293b);
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
    background: var(--stat-color, #1e293b);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.stat-number-ultimate {
    font-family: 'Georgia', serif;
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--stat-color, #1e293b);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-ultimate {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}
.stat-trend-ultimate {
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    width: fit-content;
}
.stat-trend-ultimate.up { background: rgba(16,185,129,0.1); color: #059669; }
.stat-trend-ultimate.down { background: rgba(239,68,68,0.1); color: #dc2626; }
.stat-trend-ultimate.neutral { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== CHARTS SECTION ===== */
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

/* Top authors list */
.top-authors-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.top-author-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.top-author-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(3px);
    border-color: var(--primary);
}
.top-author-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.top-author-info { flex: 1; min-width: 0; }
.top-author-name {
    font-weight: 700;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.top-author-bar {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    margin-top: 0.3rem;
    overflow: hidden;
}
.top-author-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #1e293b, #dc2626);
    border-radius: 999px;
    transition: width 1s ease;
}
.top-author-count {
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
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    border-color: #1e293b;
    box-shadow: 0 4px 12px rgba(30,41,59,0.3);
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

/* ===== ADVANCED FILTER BAR ===== */
.advanced-filter-bar {
    background: var(--bg-secondary);
    padding: 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
}
.advanced-search-form {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}
.search-group { flex: 1; min-width: 280px; position: relative; }
.search-input-wrap { position: relative; }
.search-icon-pro {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    color: var(--text-muted);
    pointer-events: none;
}
.search-input-pro {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    transition: all 0.3s;
    font-family: inherit;
    background: var(--bg-primary);
}
.search-input-pro:focus {
    outline: none;
    border-color: #1e293b;
    box-shadow: 0 0 0 4px rgba(30,41,59,0.1);
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
.search-clear-pro {
    position: absolute;
    right: 2.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--bg-tertiary);
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: var(--text-muted);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.search-clear-pro:hover { background: #dc2626; color: white; }

.filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.filter-select-pro, .date-filter-pro {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    background: var(--bg-primary);
    cursor: pointer;
    transition: all 0.3s;
    font-family: inherit;
    color: var(--text-primary);
}
.filter-select-pro:focus, .date-filter-pro:focus { outline: none; border-color: #1e293b; }

.view-toggle-pro {
    display: flex;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    padding: 0.25rem;
    gap: 0.25rem;
}
.view-btn-pro {
    padding: 0.5rem 0.85rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    font-family: inherit;
}
.view-btn-pro.active {
    background: var(--bg-primary);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}
.view-btn-pro:hover:not(.active) { background: var(--bg-secondary); color: var(--text-primary); }

.btn-reset-filter {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    font-family: inherit;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.btn-reset-filter:hover { background: var(--bg-tertiary); border-color: var(--primary); color: var(--primary); }

/* ===== CATEGORY CHIPS ===== */
.category-chips-pro {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 0.75rem 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    align-items: center;
}
.category-chips-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.chip-pro {
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    font-size: 0.78rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    color: var(--text-secondary);
}
.chip-pro:hover { border-color: var(--primary); transform: translateY(-2px); }
.chip-pro.active {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border-color: #dc2626;
    box-shadow: 0 4px 12px rgba(220,38,38,0.3);
}
.chip-count-pro {
    background: var(--bg-tertiary);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    min-width: 20px;
    text-align: center;
}
.chip-pro.active .chip-count-pro { background: rgba(255,255,255,0.25); }

/* ===== CARD HEADER PRO ===== */
.card-header-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}
.header-left h2 {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: -0.01em;
}
.count-badge-pro {
    background: #1e293b;
    color: white;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
}
.header-actions-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.btn-action-pro {
    padding: 0.7rem 1.15rem;
    border-radius: var(--radius-md);
    border: 2px solid var(--border);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.85rem;
    font-family: inherit;
    white-space: nowrap;
}
.btn-action-pro:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}
.btn-action-pro.primary {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border-color: #dc2626;
    box-shadow: 0 4px 12px rgba(220,38,38,0.25);
}
.btn-action-pro.primary:hover {
    box-shadow: 0 8px 20px rgba(220,38,38,0.4);
}

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
.bulk-bar-pro {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    animation: slideDown 0.3s ease;
    box-shadow: 0 10px 30px rgba(30,41,59,0.3);
    position: relative;
    overflow: hidden;
}
.bulk-bar-pro::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #dc2626, #ef4444);
}
.bulk-bar-pro.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info-pro {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.bulk-count {
    background: white;
    color: #1e293b;
    padding: 0.25rem 0.7rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 800;
}
.bulk-actions-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-form-inline { display: flex; gap: 0.5rem; flex-wrap: wrap; margin: 0; }
.bulk-btn-pro {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.bulk-btn-pro:hover { transform: translateY(-2px); }
.bulk-btn-pro.publish { background: #10b981; color: white; }
.bulk-btn-pro.draft { background: #f59e0b; color: white; }
.bulk-btn-pro.archive { background: #64748b; color: white; }
.bulk-btn-pro.feature { background: #8b5cf6; color: white; }
.bulk-btn-pro.delete { background: #dc2626; color: white; }
.bulk-btn-pro.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }
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
.bulk-category-select option { background: #1e293b; color: white; }

/* ===== TABLE ===== */
.table-wrapper-pro {
    overflow-x: auto;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    background: var(--bg-primary);
}
.berita-table-premium { width: 100%; border-collapse: collapse; }
.berita-table-premium thead { background: var(--bg-secondary); }
.berita-table-premium th {
    padding: 0.85rem 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
    user-select: none;
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--bg-secondary);
}
.berita-table-premium th[data-sortable] { cursor: pointer; transition: color 0.2s; }
.berita-table-premium th[data-sortable]:hover { color: var(--primary); }
.berita-table-premium th .sort-icon { opacity: 0.3; margin-left: 0.3rem; font-size: 0.7rem; }
.berita-table-premium th.asc .sort-icon,
.berita-table-premium th.desc .sort-icon { opacity: 1; color: var(--primary); }

.berita-table-premium td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    transition: all 0.2s;
}
.berita-table-premium tbody tr { transition: all 0.2s; }
.berita-table-premium tbody tr:hover { background: var(--bg-secondary); }
.berita-table-premium tbody tr.selected { background: rgba(30,41,59,0.05); }
.berita-table-premium tbody tr.featured-row { background: rgba(245, 158, 11, 0.04); }
.berita-table-premium tbody tr.featured-row:hover { background: rgba(245, 158, 11, 0.08); }

.row-checkbox { width: 18px; height: 18px; accent-color: #dc2626; cursor: pointer; }

.table-thumb-pro {
    width: 70px;
    height: 50px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    background: var(--bg-tertiary);
    position: relative;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.table-thumb-pro:hover { transform: scale(1.05); }
.table-thumb-pro img { width: 100%; height: 100%; object-fit: cover; }
.thumb-placeholder-pro {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, #1e293b, #334155);
}

.title-cell-pro strong {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
    line-height: 1.3;
    color: var(--text-primary);
}
.title-cell-pro small {
    display: block;
    font-size: 0.72rem;
    color: var(--text-muted);
    font-family: monospace;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.title-cell-pro .author-line {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.featured-icon-pro { color: #f59e0b; font-size: 0.95rem; }

/* Badges */
.badge-pro {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.badge-published { background: #dcfce7; color: #166534; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-archived { background: #f3f4f6; color: #475569; }
.badge-category { background: #dbeafe; color: #1e40af; }
.badge-featured { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }

.views-cell-pro { text-align: center; }
.views-cell-pro strong {
    display: block;
    font-size: 1.1rem;
    color: var(--primary);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.views-label { font-size: 0.68rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.date-cell-pro { font-size: 0.85rem; }
.date-cell-pro small { color: var(--text-muted); font-size: 0.72rem; display: block; margin-top: 0.1rem; }

.action-buttons-pro { display: flex; gap: 0.25rem; justify-content: flex-end; }
.act-btn-pro {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
    background: var(--bg-tertiary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 0.95rem;
    text-decoration: none;
    color: inherit;
}
.act-btn-pro:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.act-btn-pro.view:hover { background: #4338ca; color: white; }
.act-btn-pro.edit:hover { background: #3b82f6; color: white; }
.act-btn-pro.toggle:hover { background: #f59e0b; color: white; }
.act-btn-pro.feature:hover { background: #8b5cf6; color: white; }
.act-btn-pro.duplicate:hover { background: #10b981; color: white; }
.act-btn-pro.danger:hover { background: #dc2626; color: white; }

/* ===== GRID VIEW ===== */
.grid-container-pro {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
    padding: 1.5rem;
}
.grid-item-pro {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}
.grid-item-pro::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--card-color, #1e293b);
    z-index: 2;
}
.grid-item-pro:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--card-color, #1e293b);
}
.grid-item-pro.selected { border-color: var(--primary); background: rgba(30,41,59,0.02); }
.grid-item-pro.featured-card { border-color: #f59e0b; }

.grid-check-pro { position: absolute; top: 0.75rem; left: 0.75rem; z-index: 3; }
.grid-check-pro input { width: 20px; height: 20px; cursor: pointer; accent-color: #dc2626; }

.featured-badge-pro {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    box-shadow: 0 2px 6px rgba(245,158,11,0.3);
    animation: starPulse 2s infinite;
}
@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.grid-image-pro {
    height: 180px;
    overflow: hidden;
    background: var(--bg-tertiary);
    cursor: pointer;
    position: relative;
}
.grid-image-pro img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.grid-item-pro:hover .grid-image-pro img { transform: scale(1.05); }
.image-placeholder-pro {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
}
.image-overlay-pro {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: white;
    font-size: 2rem;
}
.grid-image-pro:hover .image-overlay-pro { opacity: 1; }

.grid-content-pro { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
.grid-badges-pro { display: flex; gap: 0.4rem; margin-bottom: 0.65rem; flex-wrap: wrap; }
.grid-content-pro h3 {
    font-size: 1rem;
    margin: 0.5rem 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-family: 'Georgia', serif;
    font-weight: 700;
    letter-spacing: -0.01em;
}
.grid-content-pro p {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.5;
    margin-bottom: 1rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}
.grid-meta-pro {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    color: var(--text-muted);
    padding-top: 0.75rem;
    border-top: 1px dashed var(--border);
}
.meta-item { display: flex; align-items: center; gap: 0.3rem; }
.meta-item strong { color: var(--primary); font-weight: 800; }

.grid-actions-pro {
    display: flex;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
}
.grid-action-btn {
    flex: 1;
    padding: 0.7rem;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    border-right: 1px solid var(--border);
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.grid-action-btn:last-child { border-right: none; }
.grid-action-btn:hover { background: var(--bg-primary); }
.grid-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
.grid-action-btn.feature:hover { background: #f5f3ff; color: #8b5cf6; }
.grid-action-btn.edit:hover { background: #dbeafe; color: #2563eb; }

.select-all-grid {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    width: fit-content;
}
.select-all-grid input { width: 18px; height: 18px; cursor: pointer; accent-color: #dc2626; }

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
.timeline-month {
    font-family: 'Georgia', serif;
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--primary);
    letter-spacing: -0.02em;
}
.timeline-count {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.timeline-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.85rem;
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
.timeline-item:hover { background: var(--bg-tertiary); transform: translateX(3px); border-color: var(--primary); }
.timeline-date-box {
    width: 55px;
    text-align: center;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    border-radius: 8px;
    padding: 0.5rem 0.25rem;
    flex-shrink: 0;
}
.timeline-date-box.featured { background: linear-gradient(135deg, #f59e0b, #d97706); }
.timeline-day { font-size: 1.3rem; font-weight: 900; line-height: 1; font-family: 'Georgia', serif; }
.timeline-month-small { font-size: 0.65rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name {
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Georgia', serif;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.timeline-item-role { font-size: 0.72rem; color: var(--text-muted); }

/* ===== PAGINATION ===== */
.pagination-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}
.pagination-info {
    font-size: 0.88rem;
    color: var(--text-muted);
}
.pagination-info strong { color: var(--text-primary); font-weight: 700; }
.pagination-buttons { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
.page-btn-pro {
    padding: 0.5rem 0.85rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--bg-primary);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    text-decoration: none;
    color: var(--text-primary);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-family: inherit;
}
.page-btn-pro:hover:not(.current) {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}
.page-btn-pro.current {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    border-color: #1e293b;
    box-shadow: 0 4px 12px rgba(30,41,59,0.3);
}
.page-dots { padding: 0 0.4rem; color: var(--text-muted); }

/* ===== EMPTY STATE ===== */
.empty-state-premium {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-animation {
    position: relative;
    width: 140px;
    height: 140px;
    margin: 0 auto 1.5rem;
}
.empty-circle-pro {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #1e293b, #334155);
    border-radius: 50%;
    animation: emptyPulse 3s ease-in-out infinite;
    opacity: 0.15;
}
@keyframes emptyPulse {
    0%, 100% { transform: scale(1); opacity: 0.15; }
    50% { transform: scale(1.1); opacity: 0.05; }
}
.empty-icon-pro {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    animation: emptyFloat 3s ease-in-out infinite;
}
@keyframes emptyFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.empty-state-premium h3 {
    font-size: 1.35rem;
    margin-bottom: 0.5rem;
    font-family: 'Georgia', serif;
    font-weight: 700;
}
.empty-state-premium p {
    color: var(--text-muted);
    max-width: 400px;
    margin: 0 auto 1.5rem;
    line-height: 1.6;
}
.empty-actions-pro { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

/* ===== DETAIL MODAL ===== */
.modal-overlay-premium {
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
.modal-overlay-premium.open { display: flex; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 800px;
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

.modal-header-news {
    padding: 2rem;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}
.modal-header-news::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #dc2626, #ef4444, #f87171);
}
.modal-close-premium {
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
.modal-close-premium:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

.modal-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    position: relative;
    z-index: 1;
}

.modal-title {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}
.modal-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.82rem;
    opacity: 0.9;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.modal-meta-item { display: flex; align-items: center; gap: 0.35rem; }

.modal-body { padding: 2rem; }

/* Detail Tabs */
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
.detail-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.detail-tab:hover:not(.active) { color: var(--text-primary); }
.detail-tab-content { display: none; animation: fadeInTab 0.3s; }
.detail-tab-content.active { display: block; }
@keyframes fadeInTab { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Article content */
.article-image-preview {
    width: 100%;
    height: 280px;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.5rem;
    background: var(--bg-tertiary);
    position: relative;
}
.article-image-preview img { width: 100%; height: 100%; object-fit: cover; }
.article-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
}

.article-content-preview {
    font-size: 0.95rem;
    line-height: 1.7;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--primary);
    max-height: 300px;
    overflow-y: auto;
}
.article-content-preview p { margin-bottom: 0.75rem; }

.article-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.article-stat-item {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
    transition: all 0.2s;
}
.article-stat-item:hover { background: var(--bg-tertiary); transform: translateY(-2px); }
.article-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.25rem;
    font-family: 'Georgia', serif;
}
.article-stat-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
}

/* SEO Score */
.seo-score-box {
    padding: 1rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.seo-score-circle {
    width: 60px;
    height: 60px;
    position: relative;
    flex-shrink: 0;
}
.seo-score-circle svg { transform: rotate(-90deg); width: 100%; height: 100%; }
.seo-score-circle .ring-bg { fill: none; stroke: rgba(59,130,246,0.2); stroke-width: 6; }
.seo-score-circle .ring-fill { fill: none; stroke: #3b82f6; stroke-width: 6; stroke-linecap: round; }
.seo-score-value {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 900;
    color: #1e40af;
    font-family: 'Georgia', serif;
}
.seo-score-info { flex: 1; }
.seo-score-label { font-weight: 700; color: #1e40af; margin-bottom: 0.2rem; font-size: 0.95rem; }
.seo-score-text { font-size: 0.78rem; color: #1e40af; opacity: 0.85; }

/* Reading time indicator */
.reading-time-box {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.75rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    font-size: 0.85rem;
}
.reading-time-box .icon { font-size: 1.25rem; }
.reading-time-box .value { font-weight: 700; color: var(--primary); }

/* Social share preview */
.social-share-preview {
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-top: 1rem;
}
.social-share-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.65rem;
}
.social-share-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.share-btn {
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    text-decoration: none;
    color: white;
}
.share-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.share-btn.twitter { background: #1da1f2; }
.share-btn.facebook { background: #1877f2; }
.share-btn.whatsapp { background: #25d366; }
.share-btn.copy { background: #64748b; }

/* Actions tab */
.modal-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Image modal */
.image-modal-premium {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    animation: zoomIn 0.3s;
    box-shadow: 0 30px 80px rgba(0,0,0,0.4);
}
@keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.modal-header-premium {
    padding: 1rem 1.5rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 1rem;
    max-width: 600px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.modal-body-premium {
    max-height: 70vh;
    overflow: auto;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-body-premium img { max-width: 100%; max-height: 70vh; object-fit: contain; }

/* Confirm modal */
.confirm-modal-premium {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    padding: 2rem;
    max-width: 420px;
    width: 100%;
    text-align: center;
    animation: zoomIn 0.3s;
    border: 1px solid var(--border);
}
.confirm-icon-premium { font-size: 3rem; margin-bottom: 1rem; animation: bounce 1s; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.confirm-modal-premium h3 { font-size: 1.25rem; margin-bottom: 0.5rem; font-family: 'Georgia', serif; }
.confirm-modal-premium p { color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.6; }
.confirm-actions-premium { display: flex; gap: 0.75rem; justify-content: center; }

/* Search highlight */
mark {
    background: #fef08a;
    padding: 0 0.15rem;
    border-radius: 2px;
    font-weight: 700;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .chart-section, .chart-section-3 { grid-template-columns: 1fr; }
    .berita-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .article-stats-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .berita-hero-content { flex-direction: column; text-align: center; }
    .engagement-ring { margin: 0 auto; }
    .hero-stats { justify-content: center; }
    .card-header-pro { flex-direction: column; align-items: flex-start; }
    .header-actions-pro { width: 100%; }
    .btn-action-pro { flex: 1; justify-content: center; }
    .advanced-search-form { flex-direction: column; }
    .search-group { min-width: 100%; }
    .filter-group { width: 100%; }
    .filter-select-pro, .date-filter-pro, .btn-reset-filter { flex: 1; min-width: 0; }
    .grid-container-pro { grid-template-columns: 1fr; padding: 1rem; }
    .table-wrapper-pro { overflow-x: auto; }
    .berita-table-premium { min-width: 900px; }
    .timeline-items { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .berita-stats-grid { grid-template-columns: 1fr; }
    .stat-number-ultimate { font-size: 1.85rem; }
    .bulk-bar-pro { flex-direction: column; align-items: stretch; }
    .bulk-actions-pro, .bulk-form-inline { flex-direction: column; }
    .bulk-btn-pro, .bulk-category-select { width: 100%; }
    .category-chips-pro { padding: 0.5rem; }
    .chip-pro { font-size: 0.72rem; padding: 0.35rem 0.7rem; }
}

/* Print styles */
@media print {
    .advanced-filter-bar, .bulk-bar-pro, .action-buttons-pro, .chart-section, .chart-section-3, .berita-stats-grid, .berita-hero, .category-chips-pro, .modal-overlay-premium, .pagination-premium, .header-actions-pro { display: none !important; }
    .table-wrapper-pro { box-shadow: none; border: 1px solid #ddd; }
    .berita-table-premium tbody tr:hover { background: transparent; }
}
</style>

<!-- ===== PAGE HERO ===== -->
<div class="berita-hero" data-aos="fade-down">
    <div class="berita-hero-content">
        <div>
            <h2>📰 Newsroom Dashboard</h2>
            <p>Kelola konten berita, publikasi, dan artikel FKIP UNIMOF. Pantau engagement, trending topics, dan performa konten secara real-time.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $stat_published + $stat_draft + $stat_archived ?></span>
                    <span class="hero-stat-label">Total</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= number_format($stat_total_views) ?></span>
                    <span class="hero-stat-label">Views</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num">+<?= $stat_week ?></span>
                    <span class="hero-stat-label">Minggu Ini</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $avg_views ?></span>
                    <span class="hero-stat-label">Avg/Art</span>
                </div>
            </div>
        </div>
        <div class="engagement-ring" title="Skor Engagement (semakin tinggi semakin baik)">
            <svg viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                <circle cx="18" cy="18" r="15.915" class="ring-fill" style="stroke-dasharray: <?= $engagement_score ?>, 100"/>
            </svg>
            <div class="engagement-value">
                <div class="score-num"><?= $engagement_score ?></div>
                <div class="score-label">Engage</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== STATS GRID ===== -->
<div class="berita-stats-grid" data-aos="fade-up">
    <div class="stat-card-ultimate" style="--stat-color: #10b981;">
        <div class="stat-header">
            <div class="stat-icon-box">📰</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_published ?>">0</div>
        <div class="stat-label-ultimate">Published</div>
        <div class="stat-trend-ultimate up">+<?= $stat_today ?> hari ini</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #f59e0b;">
        <div class="stat-header">
            <div class="stat-icon-box">📝</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_draft ?>">0</div>
        <div class="stat-label-ultimate">Draft</div>
        <div class="stat-trend-ultimate neutral">⏳ Perlu review</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #3b82f6;">
        <div class="stat-header">
            <div class="stat-icon-box">👁️</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_total_views ?>">0</div>
        <div class="stat-label-ultimate">Total Views</div>
        <div class="stat-trend-ultimate up">📊 Avg <?= $avg_views ?>/artikel</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #8b5cf6;">
        <div class="stat-header">
            <div class="stat-icon-box">⭐</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_featured ?>">0</div>
        <div class="stat-label-ultimate">Featured</div>
        <div class="stat-trend-ultimate neutral">🌟 Highlight</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #dc2626;">
        <div class="stat-header">
            <div class="stat-icon-box">🔥</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_week ?>">0</div>
        <div class="stat-label-ultimate">Minggu Ini</div>
        <div class="stat-trend-ultimate <?= $trend_month >= 0 ? 'up' : 'down' ?>">
            <?= $trend_month >= 0 ? '↑' : '↓' ?> <?= abs($trend_month) ?>% vs bulan lalu
        </div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #64748b;">
        <div class="stat-header">
            <div class="stat-icon-box">📦</div>
        </div>
        <div class="stat-number-ultimate count-up" data-target="<?= $stat_archived ?>">0</div>
        <div class="stat-label-ultimate">Archived</div>
        <div class="stat-trend-ultimate neutral">🗃️ Arsip</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($category_stats) || !empty($monthly_trend)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Tren Publikasi (6 Bulan Terakhir)</h3>
        <div id="trendChart"></div>
    </div>
    <div class="chart-card">
        <h3>🏆 Top Penulis</h3>
        <?php if (empty($author_stats)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem;">✍️</div>
                <div>Belum ada data penulis</div>
            </div>
        <?php else: ?>
            <div class="top-authors-list">
                <?php
                $max_count = max(array_column($author_stats, 'total'));
                foreach ($author_stats as $i => $a):
                ?>
                <div class="top-author-item">
                    <div class="top-author-avatar"><?= strtoupper(substr($a['penulis'], 0, 1)) ?></div>
                    <div class="top-author-info">
                        <div class="top-author-name"><?= sanitize($a['penulis']) ?></div>
                        <div class="top-author-bar">
                            <div class="top-author-bar-fill" style="width: <?= ($a['total'] / $max_count) * 100 ?>%"></div>
                        </div>
                    </div>
                    <div class="top-author-count"><?= $a['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="chart-section-3" data-aos="fade-up">
    <div class="chart-card" style="grid-column: 1 / -1;">
        <h3>📁 Distribusi Kategori (Published)</h3>
        <div id="categoryChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK FILTER PILLS ===== -->
<div class="quick-filter-pills" data-aos="fade-up">
    <a href="berita.php" class="pill <?= empty($status_filter) && empty($kat_filter) ? 'active' : '' ?>">
        📰 Semua <span class="pill-count"><?= $stat_published + $stat_draft + $stat_archived ?></span>
    </a>
    <a href="berita.php?status=Published" class="pill <?= $status_filter === 'Published' ? 'active' : '' ?>">
        🟢 Published <span class="pill-count"><?= $stat_published ?></span>
    </a>
    <a href="berita.php?status=Draft" class="pill <?= $status_filter === 'Draft' ? 'active' : '' ?>">
        📝 Draft <span class="pill-count"><?= $stat_draft ?></span>
    </a>
    <a href="berita.php?status=Archived" class="pill <?= $status_filter === 'Archived' ? 'active' : '' ?>">
        📦 Archived <span class="pill-count"><?= $stat_archived ?></span>
    </a>
    <div style="flex: 1;"></div>
    <a href="berita.php?featured=1" class="pill <?= $featured_filter === 1 ? 'active' : '' ?>">
        ⭐ Featured <span class="pill-count"><?= $stat_featured ?></span>
    </a>
</div>

<div class="card berita-page-pro" style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);">
    <div class="card-header-pro">
        <div class="header-left">
            <h2>📰 Daftar Berita <span class="count-badge-pro"><?= $total ?></span></h2>
            <p style="color: var(--text-muted); font-size: 0.82rem; margin-top: 0.25rem;">Kelola konten berita dan publikasi FKIP UNIMOF</p>
        </div>
        <div class="header-actions-pro">
            <div class="export-dropdown">
                <button class="btn-action-pro" onclick="toggleExportMenu(event)">
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
            <a href="berita-form.php" class="btn-action-pro primary">
                <span>➕</span>
                <span>Tambah Berita</span>
            </a>
        </div>
    </div>

    <!-- ===== ADVANCED FILTER BAR ===== -->
    <div class="advanced-filter-bar">
        <form method="GET" class="advanced-search-form" id="searchForm">
            <div class="search-group">
                <div class="search-input-wrap">
                    <span class="search-icon-pro">🔍</span>
                    <input type="text" name="q" class="search-input-pro" placeholder="Cari judul, konten, slug, atau penulis..." value="<?= sanitize($q) ?>" autocomplete="off">
                    <?php if ($q !== ''): ?>
                        <button type="button" class="search-clear-pro" onclick="clearSearch()">✕</button>
                    <?php endif; ?>
                    <span class="search-shortcut">/</span>
                </div>
            </div>
            <div class="filter-group">
                <select name="kategori" class="filter-select-pro" onchange="this.form.submit()">
                    <option value="">📁 Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?= $k ?>" <?= $kat_filter === $k ? 'selected' : '' ?>><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="filter-select-pro" onchange="this.form.submit()">
                    <option value="">📊 Semua Status</option>
                    <option value="Published" <?= $status_filter === 'Published' ? 'selected' : '' ?>>🟢 Published</option>
                    <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>📝 Draft</option>
                    <option value="Archived" <?= $status_filter === 'Archived' ? 'selected' : '' ?>>📦 Archived</option>
                </select>
                <?php if (!empty($unique_authors)): ?>
                <select name="penulis" class="filter-select-pro" onchange="this.form.submit()">
                    <option value="">✍️ Semua Penulis</option>
                    <?php foreach ($unique_authors as $author): ?>
                        <option value="<?= sanitize($author) ?>" <?= $author_filter === $author ? 'selected' : '' ?>><?= sanitize($author) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <input type="date" name="tanggal" class="date-filter-pro" value="<?= sanitize($date_filter) ?>" onchange="this.form.submit()" title="Filter tanggal">
                <button type="button" class="btn-reset-filter" onclick="resetFilters()" title="Reset semua filter">🔄 Reset</button>
            </div>
            <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
        </form>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 0.75rem;">
            <div class="view-toggle-pro">
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'table'])) ?>" class="view-btn-pro <?= $view_mode==='table'?'active':'' ?>">
                    📋 <span>Tabel</span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>" class="view-btn-pro <?= $view_mode==='grid'?'active':'' ?>">
                    🎴 <span>Grid</span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'timeline'])) ?>" class="view-btn-pro <?= $view_mode==='timeline'?'active':'' ?>">
                    📅 <span>Timeline</span>
                </a>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
                <span>Sort:</span>
                <select onchange="sortTable(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.78rem; background: var(--bg-primary); cursor: pointer;">
                    <option value="created_at-desc" <?= $sort_by === 'created_at' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="created_at-asc" <?= $sort_by === 'created_at' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Terlama</option>
                    <option value="views-desc" <?= $sort_by === 'views' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Most Viewed</option>
                    <option value="judul-asc" <?= $sort_by === 'judul' && $sort_dir === 'ASC' ? 'selected' : '' ?>>Judul A-Z</option>
                    <option value="judul-desc" <?= $sort_by === 'judul' && $sort_dir === 'DESC' ? 'selected' : '' ?>>Judul Z-A</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ===== CATEGORY CHIPS ===== -->
    <div class="category-chips-pro">
        <div class="category-chips-label">📁 Kategori:</div>
        <a class="chip-pro <?= $kat_filter === '' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['kategori' => '', 'halaman' => 1])) ?>">
            Semua
        </a>
        <?php
        $category_icons = [
            'Akademik' => '🎓', 'Pengumuman' => '📢', 'Prestasi' => '🏆',
            'Kegiatan' => '🎉', 'Riset' => '🔬', 'Umum' => '📌'
        ];
        foreach ($kategoris as $k):
            $cstmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE kategori = ?");
            $cstmt->execute([$k]);
            $count = (int)$cstmt->fetchColumn();
        ?>
            <a class="chip-pro <?= $kat_filter === $k ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['kategori' => $k, 'halaman' => 1])) ?>">
                <?= $category_icons[$k] ?? '📄' ?>
                <?= $k ?> <span class="chip-count-pro"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== BULK ACTIONS BAR ===== -->
    <div class="bulk-bar-pro" id="bulkBar">
        <div class="bulk-info-pro">
            <span class="bulk-count" id="bulkCount">0</span>
            <span>berita dipilih</span>
        </div>
        <form method="POST" class="bulk-form-inline" id="bulkForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <div class="bulk-actions-pro">
                <button type="submit" name="action" value="bulk_publish" class="bulk-btn-pro publish" onclick="return confirm('Publikasikan berita yang dipilih?')">📤 Publish</button>
                <button type="submit" name="action" value="bulk_draft" class="bulk-btn-pro draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
                <button type="submit" name="action" value="bulk_archive" class="bulk-btn-pro archive" onclick="return confirm('Arsipkan berita yang dipilih?')">📦 Archive</button>
                <button type="submit" name="action" value="bulk_feature" class="bulk-btn-pro feature" onclick="return confirm('Tandai sebagai featured?')">⭐ Feature</button>
                <select name="new_category" class="bulk-category-select" onchange="if(this.value){if(confirm('Ubah kategori?')){const f=document.getElementById('bulkForm'); const btn=f.querySelector('[name=action]'); btn.value='bulk_category'; f.submit();}}">
                    <option value="">📁 Ubah Kategori...</option>
                    <?php foreach ($kategoris as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
                </select>
                <button type="submit" name="action" value="bulk_delete" class="bulk-btn-pro delete" onclick="return confirm('HAPUS PERMANEN berita yang dipilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
            </div>
        </form>
        <button class="bulk-btn-pro cancel" onclick="clearSelection()">Batal</button>
    </div>

    <?php if (empty($berita)): ?>
    <div class="empty-state-premium">
        <div class="empty-animation">
            <div class="empty-circle-pro"></div>
            <div class="empty-icon-pro">📭</div>
        </div>
        <h3><?= ($q || $kat_filter || $status_filter || $date_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada berita' ?></h3>
        <p><?= ($q || $kat_filter || $status_filter || $date_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan berita.' : 'Mulailah dengan membuat berita pertama Anda untuk dibagikan ke pengunjung website.' ?></p>
        <div class="empty-actions-pro">
            <?php if ($q || $kat_filter || $status_filter || $date_filter): ?><a href="berita.php" class="btn-action-pro">🔄 Reset Filter</a><?php endif; ?>
            <a href="berita-form.php" class="btn-action-pro primary">➕ Buat Berita Pertama</a>
        </div>
    </div>

    <?php elseif ($view_mode === 'grid'): ?>
    <!-- GRID VIEW -->
    <div class="berita-grid-premium" style="padding: 1.5rem;">
        <label class="select-all-grid">
            <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
            <span>Pilih Semua (<?= count($berita) ?>)</span>
        </label>
        <div class="grid-container-pro" style="padding: 0;">
            <?php
            $card_colors = ['#1e293b', '#dc2626', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'];
            foreach ($berita as $i => $b):
                $color = $card_colors[$i % count($card_colors)];
            ?>
            <div class="grid-item-pro <?= $b['is_featured'] ? 'featured-card' : '' ?>" style="--card-color: <?= $color ?>;" data-id="<?= $b['id'] ?>">
                <div class="grid-check-pro">
                    <input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()">
                </div>
                <?php if ($b['is_featured']): ?>
                    <span class="featured-badge-pro">⭐ Featured</span>
                <?php endif; ?>
                <div class="grid-image-pro" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', <?= json_encode(sanitize($b['judul'])) ?>)">
                    <?php if ($b['gambar']): ?>
                        <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="image-placeholder-pro">📰</div>
                    <?php endif; ?>
                    <div class="image-overlay-pro">🔍</div>
                </div>
                <div class="grid-content-pro">
                    <div class="grid-badges-pro">
                        <span class="badge-pro badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                        <span class="badge-pro badge-category"><?= sanitize($b['kategori']) ?></span>
                    </div>
                    <h3><?= sanitize($b['judul']) ?></h3>
                    <p><?= excerpt($b['excerpt'] ?: strip_tags($b['konten']), 100) ?></p>
                    <div class="grid-meta-pro">
                        <span class="meta-item">👁️ <strong><?= number_format($b['views']) ?></strong></span>
                        <span class="meta-item">📅 <?= format_tanggal_singkat($b['created_at']) ?></span>
                        <?php if (!empty($b['penulis'])): ?>
                        <span class="meta-item">✍️ <?= excerpt($b['penulis'], 15) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid-actions-pro">
                    <button class="grid-action-btn" onclick="showDetail(<?= $b['id'] ?>)" title="Preview">👁️</button>
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="grid-action-btn edit" title="Edit">✏️</a>
                    <button class="grid-action-btn" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status"><?= $b['status'] === 'Published' ? '📤' : '📥' ?></button>
                    <button class="grid-action-btn feature" onclick="quickAction(<?= $b['id'] ?>, 'toggle_featured')" title="<?= $b['is_featured'] ? 'Unfeature' : 'Feature' ?>"><?= $b['is_featured'] ? '⭐' : '☆' ?></button>
                    <button class="grid-action-btn" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                    <button class="grid-action-btn danger" onclick="confirmDelete(<?= $b['id'] ?>, <?= json_encode(sanitize($b['judul'])) ?>)" title="Hapus">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php elseif ($view_mode === 'timeline'): ?>
    <!-- TIMELINE VIEW (grouped by month) -->
    <div class="timeline-view">
        <?php
        $grouped = [];
        foreach ($berita as $b) {
            $key = date('Y-m', strtotime($b['created_at']));
            $label = date('F Y', strtotime($b['created_at']));
            $grouped[$label][] = $b;
        }
        foreach ($grouped as $month => $items):
        ?>
        <div class="timeline-group">
            <div class="timeline-group-header">
                <span style="font-size: 1.5rem;">📅</span>
                <span class="timeline-month"><?= $month ?></span>
                <span class="timeline-count"><?= count($items) ?> berita</span>
            </div>
            <div class="timeline-items">
                <?php foreach ($items as $b): ?>
                <div class="timeline-item" onclick="showDetail(<?= $b['id'] ?>)">
                    <div class="timeline-date-box <?= $b['is_featured'] ? 'featured' : '' ?>">
                        <div class="timeline-day"><?= date('d', strtotime($b['created_at'])) ?></div>
                        <div class="timeline-month-small"><?= date('M', strtotime($b['created_at'])) ?></div>
                    </div>
                    <div class="timeline-item-info">
                        <div class="timeline-item-name">
                            <?php if ($b['is_featured']): ?><span style="color: #f59e0b;">⭐</span><?php endif; ?>
                            <?= sanitize($b['judul']) ?>
                        </div>
                        <div class="timeline-item-role">
                            <?= sanitize($b['kategori']) ?> • <?= $b['status'] ?> • 👁️ <?= number_format($b['views']) ?>
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
    <div class="table-wrapper-pro">
        <table class="berita-table-premium" id="beritaTable">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll('table')"></th>
                    <th style="width:80px">Gambar</th>
                    <th data-sortable="judul">Judul & Info <span class="sort-icon">↕</span></th>
                    <th data-sortable="kategori" style="width:120px">Kategori <span class="sort-icon">↕</span></th>
                    <th data-sortable="status" style="width:100px">Status <span class="sort-icon">↕</span></th>
                    <th data-sortable="views" style="width:90px">Views <span class="sort-icon">↕</span></th>
                    <th data-sortable="created_at" style="width:120px">Tanggal <span class="sort-icon">↕</span></th>
                    <th style="width:260px; text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($berita as $b): ?>
                <tr data-id="<?= $b['id'] ?>" class="<?= $b['is_featured'] ? 'featured-row' : '' ?>">
                    <td><input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></td>
                    <td>
                        <div class="table-thumb-pro" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', <?= json_encode(sanitize($b['judul'])) ?>)">
                            <?php if ($b['gambar']): ?>
                                <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="thumb-placeholder-pro">📰</div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="title-cell-pro">
                            <strong>
                                <?php if ($b['is_featured']): ?><span class="featured-icon-pro" title="Featured">⭐</span><?php endif; ?>
                                <?= sanitize($b['judul']) ?>
                            </strong>
                            <small><?= sanitize($b['slug']) ?></small>
                            <?php if (!empty($b['penulis'])): ?>
                            <div class="author-line">✍️ <?= sanitize($b['penulis']) ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="badge-pro badge-category"><?= sanitize($b['kategori']) ?></span></td>
                    <td><span class="badge-pro badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                    <td>
                        <div class="views-cell-pro">
                            <strong><?= number_format($b['views']) ?></strong>
                            <span class="views-label">views</span>
                        </div>
                    </td>
                    <td>
                        <div class="date-cell-pro">
                            <div><?= date('d M Y', strtotime($b['created_at'])) ?></div>
                            <small><?= date('H:i', strtotime($b['created_at'])) ?> WIB</small>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons-pro">
                            <button class="act-btn-pro view" onclick="showDetail(<?= $b['id'] ?>)" title="Preview">👁️</button>
                            <a href="berita-form.php?id=<?= $b['id'] ?>" class="act-btn-pro edit" title="Edit">✏️</a>
                            <button class="act-btn-pro toggle" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status"><?= $b['status'] === 'Published' ? '📤' : '📥' ?></button>
                            <button class="act-btn-pro feature" onclick="quickAction(<?= $b['id'] ?>, 'toggle_featured')" title="<?= $b['is_featured'] ? 'Unfeature' : 'Feature' ?>"><?= $b['is_featured'] ? '⭐' : '☆' ?></button>
                            <button class="act-btn-pro duplicate" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                            <button class="act-btn-pro danger" onclick="confirmDelete(<?= $b['id'] ?>, <?= json_encode(sanitize($b['judul'])) ?>)" title="Hapus">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination-premium">
        <div class="pagination-info">
            Menampilkan <strong><?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?></strong> dari <strong><?= $total ?></strong> berita
        </div>
        <div class="pagination-buttons">
            <?php
            $base_query = $_GET;
            if ($halaman > 1): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman - 1])) ?>" class="page-btn-pro">← Prev</a>
            <?php endif;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i === 1 || $i === $total_pages || ($i >= $halaman - 2 && $i <= $halaman + 2)):
                    echo $i === $halaman ? "<span class='page-btn-pro current'>$i</span>" : "<a href='?" . http_build_query(array_merge($base_query, ['halaman' => $i])) . "' class='page-btn-pro'>$i</a>";
                elseif ($i === $halaman - 3 || $i === $halaman + 3):
                    echo "<span class='page-dots'>…</span>";
                endif;
            endfor;
            if ($halaman < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman + 1])) ?>" class="page-btn-pro">Next →</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>

<!-- ===== IMAGE PREVIEW MODAL ===== -->
<div class="modal-overlay-premium" id="imageModal" onclick="if(event.target===this)closeImageModal()">
    <div class="image-modal-premium">
        <button class="modal-close-premium" onclick="closeImageModal()">✕</button>
        <div class="modal-header-premium" id="imageModalTitle"></div>
        <div class="modal-body-premium">
            <img id="imageModalImg" src="" alt="">
        </div>
    </div>
</div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-overlay-premium" id="detailModal" onclick="if(event.target===this)closeDetailModal()">
    <div class="modal-content">
        <button class="modal-close-premium" onclick="closeDetailModal()">✕</button>
        <div class="modal-header-news">
            <div class="modal-category-badge" id="detailCategory">-</div>
            <h2 class="modal-title" id="detailTitle">-</h2>
            <div class="modal-meta">
                <span class="modal-meta-item" id="detailAuthor">✍️ -</span>
                <span class="modal-meta-item" id="detailDate">📅 -</span>
                <span class="modal-meta-item" id="detailStatus">-</span>
            </div>
        </div>
        <div class="modal-body">
            <div class="detail-tabs">
                <button class="detail-tab active" onclick="switchDetailTab('preview', this)">📖 Preview</button>
                <button class="detail-tab" onclick="switchDetailTab('stats', this)">📊 Statistik</button>
                <button class="detail-tab" onclick="switchDetailTab('seo', this)">🔍 SEO</button>
                <button class="detail-tab" onclick="switchDetailTab('actions', this)">⚡ Aksi</button>
            </div>

            <div class="detail-tab-content active" id="tab-preview">
                <div class="article-image-preview" id="detailImage"></div>
                <div class="reading-time-box">
                    <span class="icon">⏱️</span>
                    <span>Waktu baca: <span class="value" id="detailReadTime">-</span></span>
                </div>
                <div class="article-content-preview" id="detailContent">-</div>
            </div>

            <div class="detail-tab-content" id="tab-stats">
                <div class="article-stats-grid" id="detailStats"></div>
            </div>

            <div class="detail-tab-content" id="tab-seo">
                <div class="seo-score-box">
                    <div class="seo-score-circle">
                        <svg viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.915" class="ring-bg"/>
                            <circle cx="18" cy="18" r="15.915" class="ring-fill" id="seoRing" style="stroke-dasharray: 0, 100"/>
                        </svg>
                        <div class="seo-score-value" id="seoScoreValue">0</div>
                    </div>
                    <div class="seo-score-info">
                        <div class="seo-score-label" id="seoScoreLabel">SEO Score</div>
                        <div class="seo-score-text" id="seoScoreText">Menghitung...</div>
                    </div>
                </div>
                <div id="seoAnalysis"></div>
            </div>

            <div class="detail-tab-content" id="tab-actions">
                <div class="modal-actions-list" id="detailActions"></div>
                <div class="social-share-preview">
                    <div class="social-share-label">🔗 Bagikan Artikel</div>
                    <div class="social-share-buttons" id="shareButtons"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay-premium" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="confirm-modal-premium">
        <div class="confirm-icon-premium" id="confirmIcon">⚠️</div>
        <h3 id="confirmTitle">Konfirmasi</h3>
        <p id="confirmMessage">Apakah Anda yakin?</p>
        <div class="confirm-actions-premium">
            <button class="btn-action-pro" onclick="closeConfirm()">Batal</button>
            <button class="btn-action-pro" id="confirmOk" style="background: #dc2626; color: white; border-color: #dc2626;">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<!-- ===== HIDDEN FORMS ===== -->
<form id="quickActionForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="qaId">
    <input type="hidden" name="action" id="qaAction">
</form>
<form id="deleteForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="delId">
    <input type="hidden" name="action" value="delete">
</form>

<script>
// ===== DATA untuk detail modal =====
const beritaData = <?= json_encode($berita) ?>;

// ===== COUNTER ANIMATION =====
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
<?php if (!empty($monthly_trend)): ?>
new ApexCharts(document.querySelector("#trendChart"), {
    series: [{ name: 'Berita', data: <?= json_encode(array_values($monthly_trend)) ?> }],
    chart: { type: 'area', height: 260, toolbar: { show: false } },
    colors: ['#dc2626'],
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 }
    },
    stroke: { curve: 'smooth', width: 3 },
    xaxis: {
        categories: <?= json_encode(array_keys($monthly_trend)) ?>,
        labels: { style: { fontSize: '10px' } }
    },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: v => v + ' berita' } }
}).render();
<?php endif; ?>

<?php if (!empty($category_stats)): ?>
new ApexCharts(document.querySelector("#categoryChart"), {
    series: <?= json_encode(array_column($category_stats, 'count')) ?>,
    labels: <?= json_encode(array_keys($category_stats)) ?>,
    chart: { type: 'donut', height: 280 },
    colors: ['#1e293b', '#dc2626', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'],
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        formatter: () => <?= array_sum(array_column($category_stats, 'count')) ?>
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

// ===== SEARCH =====
let searchTimer;
document.querySelector('.search-input-pro')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const val = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (val) url.searchParams.set('q', val); else url.searchParams.delete('q');
        url.searchParams.set('halaman', '1');
        window.location = url;
    }, 500);
});

function clearSearch() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    url.searchParams.set('halaman', '1');
    window.location = url;
}

function resetFilters() {
    if(confirm('Reset semua filter?')) window.location = 'berita.php';
}

function sortTable(value) {
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

// ===== SELECTION MANAGEMENT =====
function toggleSelectAll(type) {
    const master = type === 'grid' ? document.getElementById('selectAllGrid') : document.getElementById('selectAll');
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulkBar();
}

document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('[data-id]')?.classList.toggle('selected', this.checked);
        updateBulkBar();
    });
});

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkCount').textContent = checked;
    const bulkBar = document.getElementById('bulkBar');
    if (bulkBar) bulkBar.classList.toggle('show', checked > 0);

    const allChecks = document.querySelectorAll('.row-check');
    const checkedAll = allChecks.length > 0 && checked === allChecks.length;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = checkedAll;
    const gridSel = document.getElementById('selectAllGrid');
    if (gridSel) gridSel.checked = checkedAll;
}

function clearSelection() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(r => r.classList.remove('selected'));
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    const gridSel = document.getElementById('selectAllGrid');
    if (gridSel) gridSel.checked = false;
    updateBulkBar();
}

// ===== IMAGE PREVIEW =====
function previewImage(src, title) {
    if (!src || !/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(src)) {
        showToast('Error', 'Gambar tidak tersedia', 'error');
        return;
    }
    document.getElementById('imageModalImg').src = src;
    document.getElementById('imageModalTitle').textContent = title || 'Preview';
    document.getElementById('imageModal').classList.add('open');
}
function closeImageModal() {
    document.getElementById('imageModal').classList.remove('open');
}

// ===== DETAIL MODAL =====
function showDetail(id) {
    const data = beritaData.find(b => b.id == id);
    if (!data) return;

    document.getElementById('detailTitle').textContent = data.judul || '-';
    document.getElementById('detailCategory').textContent = (data.kategori || 'Umum') + (data.is_featured ? ' • ⭐ Featured' : '');
    document.getElementById('detailAuthor').textContent = '✍️ ' + (data.penulis || 'Admin FKIP');
    document.getElementById('detailDate').textContent = '📅 ' + formatDate(data.created_at);
    document.getElementById('detailStatus').innerHTML = `<span class="badge-pro badge-${data.status.toLowerCase()}">${data.status}</span>`;

    // Image
    const imageContainer = document.getElementById('detailImage');
    if (data.gambar) {
        imageContainer.innerHTML = `<img src="${window.location.origin}/uploads/${data.gambar}" alt="">`;
    } else {
        imageContainer.innerHTML = '<div class="article-image-placeholder">📰</div>';
    }

    // Content
    const content = data.konten || data.excerpt || 'Tidak ada konten';
    const plainContent = content.replace(/<[^>]+>/g, '');
    const words = plainContent.split(/\s+/).filter(w => w).length;
    const readTime = Math.max(1, Math.ceil(words / 200));
    document.getElementById('detailReadTime').textContent = readTime + ' menit';
    document.getElementById('detailContent').innerHTML = content.length > 1500
        ? content.substring(0, 1500) + '<div style="margin-top: 1rem; padding: 0.75rem; background: rgba(220,38,38,0.1); border-radius: 8px; color: #dc2626; font-weight: 600; font-size: 0.85rem;">📖 Baca selengkapnya di halaman edit...</div>'
        : content;

    // Stats
    document.getElementById('detailStats').innerHTML = `
        <div class="article-stat-item">
            <div class="article-stat-value">${(data.views || 0).toLocaleString('id-ID')}</div>
            <div class="article-stat-label">Views</div>
        </div>
        <div class="article-stat-item">
            <div class="article-stat-value">${words}</div>
            <div class="article-stat-label">Kata</div>
        </div>
        <div class="article-stat-item">
            <div class="article-stat-value">${readTime}m</div>
            <div class="article-stat-label">Read Time</div>
        </div>
    `;

    // SEO Analysis
    const seoScore = calculateSEO(data);
    document.getElementById('seoRing').style.strokeDasharray = seoScore + ', 100';
    document.getElementById('seoScoreValue').textContent = seoScore;

    let seoLabel = 'Buruk', seoColor = '#ef4444';
    if (seoScore >= 80) { seoLabel = 'Excellent'; seoColor = '#10b981'; }
    else if (seoScore >= 60) { seoLabel = 'Baik'; seoColor = '#3b82f6'; }
    else if (seoScore >= 40) { seoLabel = 'Cukup'; seoColor = '#f59e0b'; }
    document.getElementById('seoScoreLabel').textContent = seoLabel;
    document.getElementById('seoScoreLabel').style.color = seoColor;
    document.getElementById('seoRing').style.stroke = seoColor;
    document.getElementById('seoScoreValue').style.color = seoColor;

    const seoChecks = [];
    if ((data.judul || '').length >= 30) seoChecks.push('✅ Judul cukup panjang (30+ karakter)');
    else seoChecks.push('❌ Judul terlalu pendek (< 30 karakter)');
    if ((data.excerpt || '').length >= 100) seoChecks.push('✅ Excerpt informatif');
    else seoChecks.push('⚠️ Excerpt kurang panjang');
    if (data.gambar) seoChecks.push('✅ Memiliki gambar');
    else seoChecks.push('❌ Tidak ada gambar');
    if (words >= 300) seoChecks.push('✅ Konten panjang (300+ kata)');
    else seoChecks.push('⚠️ Konten kurang panjang');
    if ((data.slug || '').includes('-')) seoChecks.push('✅ Slug SEO-friendly');
    else seoChecks.push('⚠️ Slug perlu diperbaiki');

    document.getElementById('seoAnalysis').innerHTML = `
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border);">
            <h4 style="font-size: 0.95rem; margin-bottom: 0.75rem; font-family: 'Georgia', serif;">📋 Analisis SEO</h4>
            ${seoChecks.map(c => `<div style="padding: 0.4rem 0; font-size: 0.85rem; border-bottom: 1px dashed var(--border);">${c}</div>`).join('')}
        </div>
    `;

    // Actions
    document.getElementById('detailActions').innerHTML = `
        <a href="berita-form.php?id=${data.id}" class="btn-action-pro primary" style="justify-content: flex-start;">✏️ Edit Berita</a>
        <button onclick="quickAction(${data.id}, 'toggle'); closeDetailModal();" class="btn-action-pro" style="justify-content: flex-start;">🔄 Toggle Status (Currently: ${data.status})</button>
        <button onclick="quickAction(${data.id}, 'toggle_featured'); closeDetailModal();" class="btn-action-pro" style="justify-content: flex-start;">⭐ ${data.is_featured ? 'Unfeature' : 'Feature'} Article</button>
        <button onclick="quickAction(${data.id}, 'duplicate'); closeDetailModal();" class="btn-action-pro" style="justify-content: flex-start;">📋 Duplikasi sebagai Draft</button>
    `;

    // Share buttons
    const shareUrl = window.location.origin + '/berita/' + (data.slug || data.id);
    const shareText = `📰 ${data.judul}\n\n${data.excerpt || excerpt(plainContent, 100)}\n\nBaca selengkapnya di FKIP UNIMOF`;
    document.getElementById('shareButtons').innerHTML = `
        <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}" target="_blank" class="share-btn twitter">🐦 Twitter</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}" target="_blank" class="share-btn facebook">📘 Facebook</a>
        <a href="https://wa.me/?text=${encodeURIComponent(shareText + '\n' + shareUrl)}" target="_blank" class="share-btn whatsapp">💬 WhatsApp</a>
        <button onclick="copyShareLink('${shareUrl}')" class="share-btn copy">📋 Copy Link</button>
    `;

    document.getElementById('detailModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('open');
    document.body.style.overflow = '';
}

function switchDetailTab(tab, btn) {
    document.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.detail-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function calculateSEO(data) {
    let score = 0;
    const title = (data.judul || '').length;
    const excerpt = (data.excerpt || '').length;
    const content = (data.konten || '').replace(/<[^>]+>/g, '').length;

    if (title >= 30 && title <= 70) score += 25;
    else if (title >= 20) score += 15;

    if (excerpt >= 100) score += 20;
    else if (excerpt >= 50) score += 10;

    if (content >= 500) score += 25;
    else if (content >= 300) score += 15;
    else if (content >= 100) score += 5;

    if (data.gambar) score += 15;
    if ((data.slug || '').length > 5 && data.slug.includes('-')) score += 15;

    return Math.min(100, score);
}

function copyShareLink(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url);
        showToast('Disalin', 'Link berhasil disalin ke clipboard', 'success');
    }
}

function excerpt(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const d = new Date(dateStr);
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
}

// ===== CONFIRM MODAL =====
let confirmCallback = null;
function showConfirm(title, message, icon, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmIcon').textContent = icon;
    confirmCallback = onConfirm;
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    confirmCallback = null;
}
document.getElementById('confirmOk').addEventListener('click', function() {
    if (confirmCallback) confirmCallback();
    closeConfirm();
});

// ===== QUICK ACTIONS =====
function quickAction(id, action) {
    const configs = {
        toggle: { title: 'Ubah Status?', message: 'Status berita akan diubah (Published ↔ Draft).', icon: '🔄' },
        duplicate: { title: 'Duplikat Berita?', message: 'Akan dibuat salinan sebagai draft baru.', icon: '📋' },
        toggle_featured: { title: 'Toggle Featured?', message: 'Status featured artikel akan diubah.', icon: '⭐' }
    };
    const cfg = configs[action];
    showConfirm(cfg.title, cfg.message, cfg.icon, () => {
        document.getElementById('qaId').value = id;
        document.getElementById('qaAction').value = action;
        document.getElementById('quickActionForm').submit();
    });
}

function confirmDelete(id, title) {
    showConfirm('Hapus Berita Permanen?', `Berita "${title}" akan dihapus permanen. Tindakan ini TIDAK BISA dibatalkan!`, '⚠️', () => {
        document.getElementById('delId').value = id;
        document.getElementById('deleteForm').submit();
    });
}

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

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeImageModal(); closeConfirm(); closeDetailModal(); }
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.querySelector('.search-input-pro')?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'berita-form.php';
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

// ===== HIGHLIGHT SEARCH =====
function highlightSearchTerms() {
    const q = <?= json_encode($q) ?>;
    if (!q) return;
    document.querySelectorAll('.title-cell-pro strong, .grid-content-pro h3, .timeline-item-name').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();

console.log('%c📰 Newsroom FKIP UNIMOF - Super Extreme', 'color: #dc2626; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Search), Ctrl+N (Tambah), Ctrl+E (Export), Ctrl+A (Select All), ESC (Tutup modal)', 'color: #64748b;');
console.log('%cFitur: 3 View (Tabel/Grid/Timeline), Detail Modal dengan SEO Score, Bulk Actions, Charts, Export CSV/JSON', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>