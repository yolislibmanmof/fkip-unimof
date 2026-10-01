<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Berita & Pengumuman';
$page_description = 'Kabar terbaru seputar aktivitas akademik, prestasi, dan pengumuman resmi FKIP UNIMOF';

// ===== SCHEMA-SAFE: deteksi kolom berita =====
$berita_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `berita`")->fetchAll(PDO::FETCH_COLUMN);
    $berita_cols = [
        'slug'         => in_array('slug', $cols, true),
        'gambar'       => in_array('gambar', $cols, true),
        'konten'       => in_array('konten', $cols, true),
        'excerpt'      => in_array('excerpt', $cols, true),
        'penulis'      => in_array('penulis', $cols, true),
        'views'        => in_array('views', $cols, true),
        'kategori'     => in_array('kategori', $cols, true),
        'status'       => in_array('status', $cols, true),
        'is_featured'  => in_array('is_featured', $cols, true),
        'published_at' => in_array('published_at', $cols, true),
        'created_at'   => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $berita_cols = array_fill_keys(['slug','gambar','konten','excerpt','penulis','views','kategori','status','is_featured','published_at','created_at'], false);
}

// ===== FILTER & SEARCH =====
$kategori = trim($_GET['kategori'] ?? '');
$q        = trim($_GET['q'] ?? '');
$period   = $_GET['period'] ?? 'all'; // all, week, month, year
$sort     = $_GET['sort'] ?? 'newest'; // newest, oldest, popular
$view     = $_GET['view'] ?? 'grid';
$halaman  = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = ($view === 'magazine') ? 7 : 9;
$offset   = ($halaman - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];

if ($berita_cols['status']) {
    $where .= " AND status = 'Published'";
}
if ($kategori !== '' && $berita_cols['kategori']) {
    $where .= " AND kategori = ?";
    $params[] = $kategori;
}
if ($q !== '') {
    $search_fields = ['judul'];
    if ($berita_cols['excerpt']) $search_fields[] = 'excerpt';
    if ($berita_cols['konten']) $search_fields[] = 'konten';
    $placeholders = implode(' OR ', array_map(fn($f) => "$f LIKE ?", $search_fields));
    $where .= " AND ($placeholders)";
    foreach ($search_fields as $f) $params[] = "%$q%";
}
if ($period === 'week') {
    $where .= " AND published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $where .= " AND published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period === 'year') {
    $where .= " AND published_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM berita $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// Sort
$order_by = ($berita_cols['is_featured'] ? 'is_featured DESC, ' : '') . 'published_at DESC';
if ($sort === 'oldest') $order_by = 'published_at ASC';
if ($sort === 'popular' && $berita_cols['views']) $order_by = 'views DESC, published_at DESC';

// Fetch news
$stmt = $pdo->prepare("SELECT * FROM berita $where ORDER BY $order_by LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$berita = $stmt->fetchAll();

// Featured news (untuk magazine view)
$featured = null;
if ($berita_cols['is_featured']) {
    $fStmt = $pdo->query("SELECT * FROM berita WHERE status='Published' AND is_featured=1 ORDER BY published_at DESC LIMIT 1");
    $featured = $fStmt->fetch();
}

// Category counts
$kategoris = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
$kat_counts = [];
$total_all = 0;
if ($berita_cols['kategori']) {
    try {
        $rows = $pdo->query("SELECT kategori, COUNT(*) as cnt FROM berita WHERE status='Published' GROUP BY kategori")->fetchAll();
        foreach ($rows as $r) $kat_counts[$r['kategori']] = (int)$r['cnt'];
        $total_all = array_sum($kat_counts);
    } catch (Exception $e) {}
}

// Stats
$stat_total = $total_all;
$stat_featured = $berita_cols['is_featured'] ? (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published' AND is_featured=1")->fetchColumn() : 0;
$stat_views = $berita_cols['views'] ? (int)$pdo->query("SELECT SUM(views) FROM berita WHERE status='Published'")->fetchColumn() : 0;
$stat_this_month = $berita_cols['published_at'] ? (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published' AND MONTH(published_at)=MONTH(CURDATE()) AND YEAR(published_at)=YEAR(CURDATE())")->fetchColumn() : 0;

// Trending (top 5 by views)
$trending = [];
if ($berita_cols['views']) {
    try {
        $tStmt = $pdo->query("SELECT judul, slug, views, kategori FROM berita WHERE status='Published' ORDER BY views DESC LIMIT 5");
        $trending = $tStmt->fetchAll();
    } catch (Exception $e) {}
}

// Monthly stats for chart
$monthly_stats = [];
if ($berita_cols['published_at']) {
    try {
        $rows = $pdo->query("SELECT DATE_FORMAT(published_at, '%Y-%m') as month, COUNT(*) as cnt FROM berita WHERE status='Published' AND published_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month ASC")->fetchAll();
        foreach ($rows as $r) $monthly_stats[$r['month']] = (int)$r['cnt'];
    } catch (Exception $e) {}
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.berita-hero-extreme {
    position: relative; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    color: white; padding: 8rem 0 5rem; overflow: hidden;
}
.berita-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.berita-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.berita-hero-extreme .container { position: relative; z-index: 2; }
.berita-hero-extreme .breadcrumb a, .berita-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.berita-hero-extreme .breadcrumb a:hover { color: white; }

.hero-badge-pill {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.5rem 1.25rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;
}
.pulse-dot {
    width: 8px; height: 8px; background: #10b981;
    border-radius: 50%; position: relative;
}
.pulse-dot::after {
    content: ''; position: absolute; inset: 0; background: #10b981;
    border-radius: 50%; animation: pulse 2s infinite;
}
@keyframes pulse { to { transform: scale(2.5); opacity: 0; } }

.hero-trust-row {
    display: flex; gap: 0.75rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;
}
.trust-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    border-radius: 999px; font-size: 0.82rem; font-weight: 600;
}

/* ===== STATS BAR ===== */
.berita-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -3rem 0 3rem; position: relative; z-index: 10;
}
.berita-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.berita-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.berita-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.berita-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.berita-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.berita-stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== FEATURED HERO (Magazine Style) ===== */
.featured-hero {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: 1fr 1fr;
    min-height: 400px; position: relative;
}
.featured-hero::before {
    content: ''; position: absolute; top: 1rem; left: 1rem; z-index: 5;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.72rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
}
.featured-hero-image {
    position: relative; overflow: hidden; min-height: 400px;
}
.featured-hero-image img, .featured-hero-image .img-placeholder {
    width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;
    transition: transform 0.6s ease;
}
.featured-hero:hover .featured-hero-image img,
.featured-hero:hover .featured-hero-image .img-placeholder { transform: scale(1.05); }
.featured-hero-content {
    padding: 2.5rem; display: flex; flex-direction: column; justify-content: center;
}
.featured-hero-category {
    display: inline-flex; padding: 0.35rem 0.85rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.75rem; font-weight: 700; color: var(--primary);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.featured-hero-title {
    font-family: var(--font-display); font-size: 2rem; font-weight: 900;
    line-height: 1.2; margin-bottom: 1rem; color: var(--text-primary);
}
.featured-hero-excerpt {
    color: var(--text-secondary); font-size: 1rem; line-height: 1.7;
    margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3;
    -webkit-box-orient: vertical; overflow: hidden;
}
.featured-hero-meta {
    display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem;
    color: var(--text-muted); margin-bottom: 1.5rem;
}
.featured-hero-meta span { display: flex; align-items: center; gap: 0.35rem; }

/* ===== TOOLBAR ===== */
.berita-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.berita-search { flex: 1; min-width: 240px; position: relative; }
.berita-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.berita-search input:focus { outline: none; border-color: #3b82f6; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
.berita-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.berita-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.berita-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.berita-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.berita-select:focus { outline: none; border-color: #3b82f6; }

.view-toggle {
    display: flex; background: var(--bg-secondary);
    border-radius: var(--radius-md); padding: 0.25rem; border: 1px solid var(--border);
}
.view-btn {
    padding: 0.5rem 0.85rem; border-radius: 7px; border: none; background: transparent;
    cursor: pointer; font-size: 0.82rem; font-weight: 600; color: var(--text-muted);
    transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem;
    font-family: inherit;
}
.view-btn.active { background: linear-gradient(135deg, #3b82f6, #1e40af); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

/* ===== FILTER PILLS ===== */
.filter-pills {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem;
    padding: 0.5rem; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.filter-pill {
    padding: 0.5rem 0.95rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.filter-pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.filter-pill.active {
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    color: white; border-color: #1e40af;
    box-shadow: 0 4px 12px rgba(59,130,246,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.news-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;
}
.news-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column; position: relative;
}
.news-card.featured-card { grid-column: span 2; }
.news-card.featured-card .news-image { height: 280px; }
.news-card.featured-card .news-title { font-size: 1.5rem; }
.news-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #3b82f6; }

.news-image { position: relative; height: 200px; overflow: hidden; }
.news-image .img-placeholder, .news-image img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.news-card:hover .news-image .img-placeholder,
.news-card:hover .news-image img { transform: scale(1.08); }

.news-category {
    position: absolute; top: 1rem; left: 1rem;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
    color: #3b82f6; padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: var(--shadow-sm); z-index: 2;
}
.featured-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; display: flex; align-items: center; gap: 0.3rem;
    z-index: 2; box-shadow: var(--shadow-sm);
}

.news-content { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
.news-meta {
    display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.78rem;
    margin-bottom: 0.75rem; align-items: center; flex-wrap: wrap;
}
.read-time { display: inline-flex; align-items: center; gap: 0.25rem; }

.news-title {
    font-size: 1.1rem; font-weight: 700; line-height: 1.4; margin-bottom: 0.75rem;
    font-family: var(--font-display);
}
.news-title a {
    color: var(--text-primary); text-decoration: none;
    background-image: linear-gradient(#3b82f6, #3b82f6);
    background-size: 0% 2px; background-position: 0 100%; background-repeat: no-repeat;
    transition: background-size 0.3s, color 0.3s;
}
.news-title a:hover { color: #3b82f6; background-size: 100% 2px; }

.news-excerpt {
    color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.news-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 1rem; border-top: 1px solid var(--border);
}
.read-more {
    color: #3b82f6; font-weight: 600; font-size: 0.85rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem; transition: gap 0.3s;
}
.read-more:hover { gap: 0.8rem; }
.news-actions { display: flex; gap: 0.4rem; }
.news-action-btn {
    width: 32px; height: 32px; border-radius: 8px; background: var(--bg-secondary);
    border: 1px solid var(--border); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; cursor: pointer; transition: all 0.2s; text-decoration: none;
}
.news-action-btn:hover { background: #3b82f6; color: white; border-color: #3b82f6; transform: translateY(-1px); }

/* ===== LIST VIEW ===== */
.news-list { display: flex; flex-direction: column; gap: 1rem; }
.news-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; transition: all 0.3s;
    display: grid; grid-template-columns: 200px 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.news-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #3b82f6; }
.news-list-image { height: 140px; overflow: hidden; position: relative; }
.news-list-image img, .news-list-image .img-placeholder {
    width: 100%; height: 100%; object-fit: cover;
}
.news-list-content { padding: 1rem 0; }
.news-list-content h3 {
    font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem;
    color: var(--text-primary); line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.news-list-meta {
    display: flex; gap: 1rem; font-size: 0.78rem; color: var(--text-muted);
    flex-wrap: wrap;
}
.news-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.news-list-actions { padding: 1rem 1rem 1rem 0; display: flex; flex-direction: column; gap: 0.4rem; }

/* ===== MAGAZINE VIEW ===== */
.magazine-view { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
.magazine-main { display: flex; flex-direction: column; gap: 1.5rem; }
.magazine-sidebar-right { display: flex; flex-direction: column; gap: 1.5rem; }

.magazine-hero-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; position: relative;
    min-height: 380px; cursor: pointer; transition: all 0.4s;
}
.magazine-hero-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); }
.magazine-hero-image {
    position: absolute; inset: 0; overflow: hidden;
}
.magazine-hero-image img, .magazine-hero-image .img-placeholder {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.6s;
}
.magazine-hero-card:hover .magazine-hero-image img,
.magazine-hero-card:hover .magazine-hero-image .img-placeholder { transform: scale(1.05); }
.magazine-hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.4) 60%, transparent 100%);
    padding: 2rem; display: flex; flex-direction: column; justify-content: flex-end; color: white;
}
.magazine-hero-category {
    display: inline-flex; padding: 0.3rem 0.75rem; background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px); border-radius: 999px; font-size: 0.7rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.75rem; width: fit-content;
}
.magazine-hero-title {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    line-height: 1.2; margin-bottom: 0.75rem;
}
.magazine-hero-meta {
    display: flex; gap: 1rem; font-size: 0.82rem; opacity: 0.9; flex-wrap: wrap;
}

.magazine-small-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; display: flex;
    transition: all 0.3s; cursor: pointer;
}
.magazine-small-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: #3b82f6; }
.magazine-small-image { width: 120px; flex-shrink: 0; overflow: hidden; }
.magazine-small-image img, .magazine-small-image .img-placeholder {
    width: 100%; height: 100%; object-fit: cover; min-height: 100px;
}
.magazine-small-content { padding: 1rem; flex: 1; display: flex; flex-direction: column; }
.magazine-small-content h4 {
    font-size: 0.92rem; font-weight: 700; margin-bottom: 0.4rem;
    color: var(--text-primary); line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.magazine-small-meta { font-size: 0.72rem; color: var(--text-muted); }

/* ===== TRENDING SIDEBAR ===== */
.trending-sidebar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);
}
.trending-title {
    font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}
.trending-list { display: flex; flex-direction: column; gap: 0.75rem; }
.trending-item {
    display: flex; gap: 0.75rem; padding: 0.75rem;
    background: var(--bg-secondary); border-radius: var(--radius-md);
    transition: all 0.2s; cursor: pointer; text-decoration: none;
    align-items: center;
}
.trending-item:hover { background: var(--bg-tertiary); transform: translateX(3px); }
.trending-rank {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
    font-family: var(--font-display);
}
.trending-rank.top-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.trending-rank.top-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.trending-rank.top-3 { background: linear-gradient(135deg, #d97706, #92400e); }
.trending-info { flex: 1; min-width: 0; }
.trending-info h5 {
    font-size: 0.85rem; font-weight: 600; margin-bottom: 0.2rem;
    color: var(--text-primary); line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.trending-info span { font-size: 0.7rem; color: var(--text-muted); }

/* ===== CHART VIEW ===== */
.chart-view-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}

/* ===== PAGINATION ===== */
.pagination-pro {
    display: flex; justify-content: center; align-items: center;
    gap: 0.5rem; margin-top: 3rem; flex-wrap: wrap;
}
.page-btn {
    min-width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-md); border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary);
    font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s;
}
.page-btn:hover:not(.current):not(.disabled) {
    border-color: #3b82f6; color: #3b82f6; transform: translateY(-2px);
}
.page-btn.current {
    background: linear-gradient(135deg, #3b82f6, #1e40af); color: white;
    border-color: #1e40af; box-shadow: 0 4px 12px rgba(59,130,246,0.3);
}
.page-btn.disabled { opacity: 0.5; cursor: not-allowed; }

/* ===== EMPTY STATE ===== */
.empty-state-premium {
    text-align: center; padding: 4rem 2rem; background: var(--bg-secondary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.empty-icon-lg {
    font-size: 5rem; margin-bottom: 1rem; opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* ===== TOAST ===== */
.pub-toast {
    position: fixed; bottom: 2rem; right: 2rem;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: 12px; padding: 0.9rem 1.25rem;
    box-shadow: var(--shadow-lg); display: flex; align-items: center;
    gap: 0.75rem; z-index: 10002;
    transform: translateY(150%); transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    max-width: 320px;
}
.pub-toast.show { transform: translateY(0); }
.pub-toast-icon {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
    background: #dcfce7; color: #166534;
}

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-news {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.modal-header-news {
    position: relative; overflow: hidden;
}
.modal-header-news img, .modal-header-news .img-placeholder {
    width: 100%; height: 300px; object-fit: cover; display: block;
}
.modal-header-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.3) 50%, transparent 100%);
    padding: 2rem; display: flex; flex-direction: column; justify-content: flex-end; color: white;
}
.modal-close {
    position: absolute; top: 1rem; right: 1rem;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.2); border: none; color: white;
    cursor: pointer; font-size: 1.1rem; z-index: 2;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover { background: #dc2626; transform: rotate(90deg); }
.modal-category {
    display: inline-flex; padding: 0.35rem 0.85rem; background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px); border-radius: 999px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.75rem; width: fit-content;
}
.modal-title {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    line-height: 1.2; margin-bottom: 0.75rem;
}
.modal-meta {
    display: flex; gap: 1rem; font-size: 0.85rem; opacity: 0.9; flex-wrap: wrap;
}
.modal-body-news { padding: 2rem; }
.modal-excerpt {
    font-size: 1.1rem; color: var(--text-secondary); line-height: 1.8;
    margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border);
}
.modal-content-text {
    font-size: 1rem; color: var(--text-primary); line-height: 1.8;
}
.modal-content-text p { margin-bottom: 1.25rem; }
.modal-footer-news {
    padding: 1rem 2rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: space-between; flex-wrap: wrap;
    background: var(--bg-secondary);
}
.modal-btn {
    padding: 0.7rem 1.25rem; border-radius: 8px; border: none;
    font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    transition: all 0.2s;
}
.modal-btn.primary { background: linear-gradient(135deg, #3b82f6, #1e40af); color: white; }
.modal-btn.secondary { background: var(--bg-tertiary); color: var(--text-primary); }
.modal-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

@media (max-width: 968px) {
    .chart-view-grid { grid-template-columns: 1fr; }
    .featured-hero { grid-template-columns: 1fr; }
    .featured-hero-image { min-height: 250px; }
    .magazine-view { grid-template-columns: 1fr; }
    .news-card.featured-card { grid-column: span 1; }
}
@media (max-width: 768px) {
    .berita-hero-extreme { padding: 7rem 0 4rem; }
    .berita-stats-bar { grid-template-columns: 1fr 1fr; margin: -2rem 1rem 2rem; }
    .berita-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .news-grid { grid-template-columns: 1fr; }
    .news-list-item { grid-template-columns: 100px 1fr; gap: 1rem; }
    .news-list-image { height: 100px; }
    .news-list-actions { padding: 0.5rem 1rem 1rem 0; flex-direction: row; }
    .magazine-small-card { flex-direction: column; }
    .magazine-small-image { width: 100%; height: 120px; }
}
@media (max-width: 480px) {
    .berita-stats-bar { grid-template-columns: 1fr; }
    .featured-hero-content { padding: 1.5rem; }
    .featured-hero-title { font-size: 1.5rem; }
}
</style>

<!-- ===== HERO ===== -->
<section class="berita-hero-extreme">
    <div class="container" style="text-align: center;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Berita & Pengumuman</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>📰 <?= $total_all ?> Artikel Terpublikasi</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Berita &
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Pengumuman</span>
        </h1>
        <p class="page-subtitle" style="max-width: 640px; margin: 0 auto; opacity: 0.92; font-size: 1.1rem; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Kabar terbaru seputar aktivitas akademik, prestasi, dan pengumuman resmi FKIP UNIMOF
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">📅 Update Harian</span>
            <span class="trust-pill">✨ <?= $stat_featured ?> Artikel Pilihan</span>
            <span class="trust-pill">👁️ <?= number_format($stat_views) ?> Total Views</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats Bar -->
        <div class="berita-stats-bar" data-aos="fade-up">
            <div class="berita-stat-card" style="--stat-color: #3b82f6;">
                <div class="berita-stat-icon">📰</div>
                <div class="berita-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="berita-stat-label">Total Artikel</div>
            </div>
            <div class="berita-stat-card" style="--stat-color: #f59e0b;">
                <div class="berita-stat-icon">⭐</div>
                <div class="berita-stat-num count-up" data-target="<?= $stat_featured ?>">0</div>
                <div class="berita-stat-label">Featured</div>
            </div>
            <div class="berita-stat-card" style="--stat-color: #10b981;">
                <div class="berita-stat-icon">📅</div>
                <div class="berita-stat-num count-up" data-target="<?= $stat_this_month ?>">0</div>
                <div class="berita-stat-label">Bulan Ini</div>
            </div>
            <div class="berita-stat-card" style="--stat-color: #8b5cf6;">
                <div class="berita-stat-icon">👁️</div>
                <div class="berita-stat-num"><?= $stat_views > 1000 ? round($stat_views/1000, 1) . 'K' : $stat_views ?></div>
                <div class="berita-stat-label">Total Views</div>
            </div>
        </div>

        <!-- Featured Hero -->
        <?php if ($featured && $berita_cols['is_featured']):
            $hue = crc32($featured['kategori'] ?? 'umum') % 360;
            $word_count = $berita_cols['konten'] ? str_word_count(strip_tags($featured['konten'] ?? '')) : 0;
            $read_time = max(1, ceil($word_count / 200));
        ?>
        <div class="featured-hero" data-aos="fade-up" onclick='openNewsModal(<?= htmlspecialchars(json_encode($featured), ENT_QUOTES, "UTF-8") ?>)'>
            <span style="position:absolute;top:1rem;left:1rem;z-index:5;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:0.4rem 1rem;border-radius:999px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">⭐ Editor's Pick</span>
            <div class="featured-hero-image">
                <?php if ($berita_cols['gambar'] && !empty($featured['gambar'])): ?>
                    <img src="<?= asset('uploads/' . basename($featured['gambar'])) ?>" alt="<?= sanitize($featured['judul']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                        <span style="font-size:4rem; opacity:0.8; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">📰</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="featured-hero-content">
                <span class="featured-hero-category"><?= sanitize($featured['kategori'] ?? 'Umum') ?></span>
                <h2 class="featured-hero-title"><?= sanitize($featured['judul']) ?></h2>
                <p class="featured-hero-excerpt"><?= excerpt($featured['excerpt'] ?? $featured['konten'] ?? '', 200) ?></p>
                <div class="featured-hero-meta">
                    <span>📅 <?= format_tanggal_singkat($featured['published_at'] ?? $featured['created_at'] ?? '') ?></span>
                    <?php if ($berita_cols['penulis'] && !empty($featured['penulis'])): ?>
                        <span>✍️ <?= sanitize($featured['penulis']) ?></span>
                    <?php endif; ?>
                    <span class="read-time">⏱️ <?= $read_time ?> mnt</span>
                    <?php if ($berita_cols['views']): ?>
                        <span>👁️ <?= number_format($featured['views'] ?? 0) ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?= base_url('berita-detail.php?slug=' . urlencode($featured['slug'])) ?>" class="read-more" onclick="event.stopPropagation();">
                    Baca Selengkapnya →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="berita-toolbar" data-aos="fade-up">
            <form method="GET" action="" class="berita-search" onsubmit="return false;">
                <span class="s-icon">🔍</span>
                <input type="text" id="beritaSearch" placeholder="Cari berita, pengumuman, atau prestasi..." value="<?= sanitize($q) ?>">
                <?php if ($q !== ''): ?>
                    <a href="berita.php?kategori=<?= urlencode($kategori) ?>&period=<?= urlencode($period) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>" class="s-clear" title="Clear">✕</a>
                <?php endif; ?>
            </form>
            <select class="berita-select" id="periodSelect">
                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>📅 Semua Waktu</option>
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>📅 Minggu Ini</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>📅 Bulan Ini</option>
                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>📅 Tahun Ini</option>
            </select>
            <select class="berita-select" id="sortSelect">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>🕐 Terlama</option>
                <?php if ($berita_cols['views']): ?>
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>🔥 Terpopuler</option>
                <?php endif; ?>
            </select>
            <div class="view-toggle">
                <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                <button class="view-btn <?= $view === 'magazine' ? 'active' : '' ?>" onclick="switchView('magazine')">📰 Magazine</button>
                <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
            </div>
        </div>

        <!-- Filter Pills -->
        <div class="filter-pills" data-aos="fade-up">
            <a class="filter-pill <?= $kategori === '' ? 'active' : '' ?>" href="berita.php?kategori=&period=<?= urlencode($period) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                📰 Semua <span class="pill-count"><?= $total_all ?></span>
            </a>
            <?php foreach ($kategoris as $k):
                $count = $kat_counts[$k] ?? 0;
                if ($count === 0 && $kategori !== $k) continue;
            ?>
            <a class="filter-pill <?= $kategori === $k ? 'active' : '' ?>" href="berita.php?kategori=<?= urlencode($k) ?>&period=<?= urlencode($period) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                <?= $k ?> <span class="pill-count"><?= $count ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($berita)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📭</div>
                <h3>Belum ada berita ditemukan</h3>
                <p style="color: var(--text-muted); max-width: 400px; margin: 0.5rem auto 1.5rem;">
                    <?php if ($q || $kategori): ?>
                        Coba ubah kata kunci pencarian atau pilih kategori lain.
                    <?php else: ?>
                        Kami sedang menyiapkan konten terbaru untuk Anda. Nantikan update selanjutnya!
                    <?php endif; ?>
                </p>
                <?php if ($q || $kategori): ?>
                    <a href="berita.php" class="modal-btn secondary">🔄 Reset Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="news-grid" data-aos="fade-up">
                <?php foreach ($berita as $index => $b):
                    $is_featured = $berita_cols['is_featured'] && !empty($b['is_featured']);
                    $word_count = $berita_cols['konten'] ? str_word_count(strip_tags($b['konten'] ?? '')) : 0;
                    $read_time = max(1, ceil($word_count / 200));
                    $hue = crc32($b['kategori'] ?? 'umum') % 360;
                ?>
                <article class="news-card <?= $is_featured && $index === 0 ? 'featured-card' : '' ?>" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                    <div class="news-image">
                        <?php if ($berita_cols['gambar'] && !empty($b['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                                <span style="font-size:3rem; opacity:0.8; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">📰</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($berita_cols['kategori'] && !empty($b['kategori'])): ?>
                            <span class="news-category"><?= sanitize($b['kategori']) ?></span>
                        <?php endif; ?>
                        <?php if ($is_featured): ?>
                            <span class="featured-badge">⭐ Utama</span>
                        <?php endif; ?>
                    </div>
                    <div class="news-content">
                        <div class="news-meta">
                            <span>📅 <?= format_tanggal_singkat($b['published_at'] ?? $b['created_at'] ?? '') ?></span>
                            <?php if ($berita_cols['views']): ?>
                                <span>👁️ <?= number_format($b['views'] ?? 0) ?></span>
                            <?php endif; ?>
                            <span class="read-time">⏱️ <?= $read_time ?> mnt</span>
                        </div>
                        <h3 class="news-title">
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>">
                                <?= function_exists('highlight_search') ? highlight_search(sanitize($b['judul']), $q) : sanitize($b['judul']) ?>
                            </a>
                        </h3>
                        <p class="news-excerpt">
                            <?= excerpt($b['excerpt'] ?? $b['konten'] ?? '', 120) ?>
                        </p>
                        <div class="news-footer">
                            <a class="read-more" href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>">
                                Baca Selengkapnya →
                            </a>
                            <div class="news-actions">
                                <button class="news-action-btn" onclick='openNewsModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' title="Quick View">👁️</button>
                                <button class="news-action-btn" onclick='shareNews(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' title="Share">🔗</button>
                            </div>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="news-list" data-aos="fade-up">
                <?php foreach ($berita as $b):
                    $hue = crc32($b['kategori'] ?? 'umum') % 360;
                    $word_count = $berita_cols['konten'] ? str_word_count(strip_tags($b['konten'] ?? '')) : 0;
                    $read_time = max(1, ceil($word_count / 200));
                ?>
                <div class="news-list-item" onclick="window.location='<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>'">
                    <div class="news-list-image">
                        <?php if ($berita_cols['gambar'] && !empty($b['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                                <span style="font-size:2rem; opacity:0.8; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">📰</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="news-list-content">
                        <h3><?= sanitize($b['judul']) ?></h3>
                        <div class="news-list-meta">
                            <?php if ($berita_cols['kategori'] && !empty($b['kategori'])): ?>
                                <span class="news-category" style="position:static; padding:0.2rem 0.6rem; font-size:0.68rem;"><?= sanitize($b['kategori']) ?></span>
                            <?php endif; ?>
                            <span>📅 <?= format_tanggal_singkat($b['published_at'] ?? $b['created_at'] ?? '') ?></span>
                            <?php if ($berita_cols['views']): ?>
                                <span>👁️ <?= number_format($b['views'] ?? 0) ?></span>
                            <?php endif; ?>
                            <span>⏱️ <?= $read_time ?> mnt</span>
                        </div>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.5rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= excerpt($b['excerpt'] ?? $b['konten'] ?? '', 150) ?>
                        </p>
                    </div>
                    <div class="news-list-actions" onclick="event.stopPropagation();">
                        <button class="news-action-btn" onclick='shareNews(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' title="Share">🔗</button>
                        <button class="news-action-btn" onclick='openNewsModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)' title="Quick View">👁️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== MAGAZINE VIEW ===== -->
            <?php if ($view === 'magazine'): ?>
            <div class="magazine-view" data-aos="fade-up">
                <div class="magazine-main">
                    <?php
                    $first = $berita[0] ?? null;
                    if ($first):
                        $hue = crc32($first['kategori'] ?? 'umum') % 360;
                    ?>
                    <div class="magazine-hero-card" onclick="window.location='<?= base_url('berita-detail.php?slug=' . urlencode($first['slug'])) ?>'">
                        <div class="magazine-hero-image">
                            <?php if ($berita_cols['gambar'] && !empty($first['gambar'])): ?>
                                <img src="<?= asset('uploads/' . basename($first['gambar'])) ?>" alt="<?= sanitize($first['judul']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                                    <span style="font-size:4rem; opacity:0.8; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">📰</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="magazine-hero-overlay">
                            <?php if ($berita_cols['kategori'] && !empty($first['kategori'])): ?>
                                <span class="magazine-hero-category"><?= sanitize($first['kategori']) ?></span>
                            <?php endif; ?>
                            <h2 class="magazine-hero-title"><?= sanitize($first['judul']) ?></h2>
                            <div class="magazine-hero-meta">
                                <span>📅 <?= format_tanggal_singkat($first['published_at'] ?? $first['created_at'] ?? '') ?></span>
                                <?php if ($berita_cols['views']): ?>
                                    <span>👁️ <?= number_format($first['views'] ?? 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php foreach (array_slice($berita, 1, 4) as $b):
                        $hue = crc32($b['kategori'] ?? 'umum') % 360;
                    ?>
                    <div class="magazine-small-card" onclick="window.location='<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>'">
                        <div class="magazine-small-image">
                            <?php if ($berita_cols['gambar'] && !empty($b['gambar'])): ?>
                                <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="img-placeholder" style="background:linear-gradient(135deg, hsl(<?= $hue ?>, 60%, 50%), hsl(<?= ($hue + 40) % 360 ?>, 60%, 40%));">
                                    <span style="font-size:1.5rem; opacity:0.8; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">📰</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="magazine-small-content">
                            <h4><?= sanitize($b['judul']) ?></h4>
                            <div class="magazine-small-meta">
                                📅 <?= format_tanggal_singkat($b['published_at'] ?? $b['created_at'] ?? '') ?>
                                <?php if ($berita_cols['views']): ?> • 👁️ <?= number_format($b['views'] ?? 0) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="magazine-sidebar-right">
                    <!-- Trending -->
                    <?php if (!empty($trending)): ?>
                    <div class="trending-sidebar">
                        <h3 class="trending-title">🔥 Trending</h3>
                        <div class="trending-list">
                            <?php foreach ($trending as $i => $t): ?>
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($t['slug'])) ?>" class="trending-item">
                                <div class="trending-rank <?= $i < 3 ? 'top-' . ($i + 1) : '' ?>"><?= $i + 1 ?></div>
                                <div class="trending-info">
                                    <h5><?= sanitize($t['judul']) ?></h5>
                                    <span>👁️ <?= number_format($t['views']) ?> views</span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Categories -->
                    <div class="trending-sidebar">
                        <h3 class="trending-title">🏷️ Kategori</h3>
                        <div class="trending-list">
                            <?php foreach ($kategoris as $k):
                                $cnt = $kat_counts[$k] ?? 0;
                                if ($cnt === 0) continue;
                            ?>
                            <a href="berita.php?kategori=<?= urlencode($k) ?>&view=magazine" class="trending-item">
                                <div class="trending-rank" style="background:linear-gradient(135deg, #3b82f6, #1e40af);">📁</div>
                                <div class="trending-info">
                                    <h5><?= $k ?></h5>
                                    <span><?= $cnt ?> artikel</span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-view-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>📊 Distribusi Kategori</h3>
                    <div id="kategoriChart"></div>
                </div>
                <div class="chart-card">
                    <h3>📈 Tren Artikel 12 Bulan</h3>
                    <div id="trendChart"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="pagination-pro" data-aos="fade-up">
                <?php if ($halaman > 1): ?>
                    <a href="berita.php?<?= http_build_query(array_merge($_GET, ['halaman' => $halaman - 1])) ?>" class="page-btn">←</a>
                <?php else: ?>
                    <span class="page-btn disabled">←</span>
                <?php endif; ?>

                <?php
                $range = 2;
                $start = max(1, $halaman - $range);
                $end = min($total_pages, $halaman + $range);
                if ($start > 1):
                ?>
                    <a href="berita.php?<?= http_build_query(array_merge($_GET, ['halaman' => 1])) ?>" class="page-btn">1</a>
                    <?php if ($start > 2): ?><span class="page-btn disabled">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?= $i === $halaman ? "<span class='page-btn current'>$i</span>" : "<a href='berita.php?" . http_build_query(array_merge($_GET, ['halaman'=>$i])) . "' class='page-btn'>$i</a>" ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="page-btn disabled">…</span><?php endif; ?>
                    <a href="berita.php?<?= http_build_query(array_merge($_GET, ['halaman' => $total_pages])) ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($halaman < $total_pages): ?>
                    <a href="berita.php?<?= http_build_query(array_merge($_GET, ['halaman' => $halaman + 1])) ?>" class="page-btn">→</a>
                <?php else: ?>
                    <span class="page-btn disabled">→</span>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Modal Quick View -->
<div class="modal-overlay" id="newsModal" onclick="if(event.target===this)closeNewsModal()">
    <div class="modal-content-news" id="newsModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const beritaCols = <?= json_encode($berita_cols) ?>;
const katCounts = <?= json_encode($kat_counts) ?>;
const monthlyStats = <?= json_encode($monthly_stats) ?>;

// ===== COUNT UP =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1800; const start = performance.now();
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
        if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); }
    });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== SEARCH =====
let searchTimer;
document.getElementById('beritaSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        url.searchParams.delete('halaman');
        window.location = url;
    }, 500);
});

document.getElementById('periodSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('period', this.value);
    url.searchParams.delete('halaman');
    window.location = url;
});

document.getElementById('sortSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('sort', this.value);
    url.searchParams.delete('halaman');
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    url.searchParams.delete('halaman');
    window.location = url;
}

// ===== HELPERS =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL QUICK VIEW =====
function openNewsModal(b) {
    const hue = crc32(b.kategori || 'umum') % 360;
    const wordCount = beritaCols.konten && b.konten ? (b.konten.match(/\S+/g) || []).length : 0;
    const readTime = Math.max(1, Math.ceil(wordCount / 200));

    const html = `
        <button class="modal-close" onclick="closeNewsModal()">✕</button>
        <div class="modal-header-news">
            ${beritaCols.gambar && b.gambar
                ? `<img src="${'<?= asset('uploads/') ?>' + encodeURIComponent(b.gambar.split('/').pop())}" alt="${escapeHtml(b.judul)}">`
                : `<div class="img-placeholder" style="background:linear-gradient(135deg, hsl(${hue}, 60%, 50%), hsl(${(hue+40)%360}, 60%, 40%)); height:300px; display:flex; align-items:center; justify-content:center;"><span style="font-size:4rem; opacity:0.8;">📰</span></div>`}
            <div class="modal-header-overlay">
                ${beritaCols.kategori && b.kategori ? `<span class="modal-category">${escapeHtml(b.kategori)}</span>` : ''}
                <h2 class="modal-title">${escapeHtml(b.judul)}</h2>
                <div class="modal-meta">
                    <span>📅 ${new Date(b.published_at || b.created_at).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</span>
                    ${beritaCols.penulis && b.penulis ? `<span>✍️ ${escapeHtml(b.penulis)}</span>` : ''}
                    <span>⏱️ ${readTime} mnt</span>
                    ${beritaCols.views ? `<span>👁️ ${Number(b.views || 0).toLocaleString('id-ID')}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-news">
            <p class="modal-excerpt">${escapeHtml(b.excerpt || (b.konten ? b.konten.substring(0, 250) + '...' : ''))}</p>
            <div class="modal-content-text">
                ${beritaCols.konten && b.konten ? `<p>${escapeHtml(b.konten.substring(0, 500))}${b.konten.length > 500 ? '...' : ''}</p>` : '<p style="color:var(--text-muted);font-style:italic;">Konten tidak tersedia.</p>'}
            </div>
        </div>
        <div class="modal-footer-news">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareNews(${JSON.stringify(b).replace(/"/g, "&quot;")})'>🔗 Share</button>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="closeNewsModal()">Tutup</button>
                <a href="${'<?= base_url('berita-detail.php?slug=') ?>' + encodeURIComponent(b.slug)}" class="modal-btn primary">📖 Baca Lengkap</a>
            </div>
        </div>
    `;
    document.getElementById('newsModalContent').innerHTML = html;
    document.getElementById('newsModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeNewsModal() {
    document.getElementById('newsModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareNews(b) {
    const url = window.location.origin + '<?= base_url('berita-detail.php?slug=') ?>' + encodeURIComponent(b.slug);
    const text = `📰 ${b.judul}\n\nBaca selengkapnya di FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: b.judul, text, url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + '\n' + url);
        pubToast('Link berita disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const katColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#ef4444', '#06b6d4'];

if (Object.keys(katCounts).length > 0) {
    new ApexCharts(document.querySelector("#kategoriChart"), {
        series: Object.values(katCounts),
        labels: Object.keys(katCounts),
        chart: { type: 'donut', height: 300 },
        colors: katColors.slice(0, Object.keys(katCounts).length),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(katCounts).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(monthlyStats).length > 0) {
    new ApexCharts(document.querySelector("#trendChart"), {
        series: [{ name: 'Artikel', data: Object.values(monthlyStats) }],
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        colors: ['#3b82f6'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: {
            categories: Object.keys(monthlyStats).map(m => {
                const [y, mo] = m.split('-');
                const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                return months[parseInt(mo)-1] + ' ' + y.slice(-2);
            }),
            labels: { style: { fontSize: '11px' } }
        },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        stroke: { curve: 'smooth', width: 3 }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('beritaSearch')?.focus();
    }
    if (e.key === 'Escape') closeNewsModal();
});

// CRC32 helper
function crc32(str) {
    let crc = 0xFFFFFFFF;
    for (let i = 0; i < str.length; i++) {
        crc ^= str.charCodeAt(i);
        for (let j = 0; j < 8; j++) crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
}

console.log('%c📰 Berita FKIP UNIMOF - EXTREME MULTIMATE', 'color:#3b82f6;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>