<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Unduhan';
$page_description = 'Download dokumen akademik, formulir, pedoman, dan informasi PMB FKIP UNIMOF.';

// ===== SCHEMA-SAFE =====
$dl_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `downloads`")->fetchAll(PDO::FETCH_COLUMN);
    $dl_cols = [
        'judul'           => in_array('judul', $cols, true),
        'file_name'       => in_array('file_name', $cols, true),
        'file_size'       => in_array('file_size', $cols, true),
        'kategori'        => in_array('kategori', $cols, true),
        'deskripsi'       => in_array('deskripsi', $cols, true),
        'downloads_count' => in_array('downloads_count', $cols, true),
        'created_at'      => in_array('created_at', $cols, true),
        'status'          => in_array('status', $cols, true),
        'versi'           => in_array('versi', $cols, true),
        'author'          => in_array('author', $cols, true),
    ];
} catch (Exception $e) {
    $dl_cols = array_fill_keys(['judul','file_name','file_size','kategori','deskripsi','downloads_count','created_at','status','versi','author'], false);
}

// ===== PARAMS =====
$kat = $_GET['kategori'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'list';
$sort = $_GET['sort'] ?? 'newest';

$where = "WHERE 1=1";
$params = [];
if ($dl_cols['status']) $where .= " AND status = 'Aktif'";
if ($kat !== 'all' && $dl_cols['kategori']) {
    $where .= " AND kategori = ?";
    $params[] = $kat;
}
if ($q !== '') {
    $search_fields = ['judul'];
    if ($dl_cols['deskripsi']) $search_fields[] = 'deskripsi';
    $placeholders = implode(' OR ', array_map(fn($f) => "$f LIKE ?", $search_fields));
    $where .= " AND ($placeholders)";
    foreach ($search_fields as $f) $params[] = "%$q%";
}

$order_by = $dl_cols['created_at'] ? 'created_at DESC' : 'id DESC';
if ($sort === 'oldest' && $dl_cols['created_at']) $order_by = 'created_at ASC';
if ($sort === 'popular' && $dl_cols['downloads_count']) $order_by = 'downloads_count DESC';
if ($sort === 'name') $order_by = 'judul ASC';

$stmt = $pdo->prepare("SELECT * FROM downloads $where ORDER BY $order_by");
$stmt->execute($params);
$files = $stmt->fetchAll();

// Stats
$total_files = count($files);
$total_downloads = $dl_cols['downloads_count'] ? (int)$pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM downloads WHERE status='Aktif'")->fetchColumn() : 0;
$kategori_dist = [];
$ext_dist = [];
$total_size_bytes = 0;

foreach ($files as $f) {
    if ($dl_cols['kategori'] && !empty($f['kategori'])) {
        $kategori_dist[$f['kategori']] = ($kategori_dist[$f['kategori']] ?? 0) + 1;
    }
    if ($dl_cols['file_name'] && !empty($f['file_name'])) {
        $ext = strtoupper(pathinfo($f['file_name'], PATHINFO_EXTENSION));
        $ext_dist[$ext] = ($ext_dist[$ext] ?? 0) + 1;
    }
}

// Top downloads
$top_files = [];
if ($dl_cols['downloads_count']) {
    try {
        $topStmt = $pdo->query("SELECT id, judul, kategori, downloads_count, file_name FROM downloads WHERE status='Aktif' ORDER BY downloads_count DESC LIMIT 5");
        $top_files = $topStmt->fetchAll();
    } catch (Exception $e) {}
}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.download-hero-extreme {
    position: relative; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e3a8a 100%);
    color: white; padding: 8rem 0 6rem; overflow: hidden;
}
.download-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(251,191,36,0.2) 0%, transparent 50%),
                radial-gradient(circle at 70% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.download-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.download-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.download-hero-extreme .breadcrumb a, .download-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.download-hero-extreme .breadcrumb a:hover { color: white; }

.hero-search-extreme {
    max-width: 600px; margin: 2rem auto 0; position: relative;
}
.hero-search-extreme input {
    width: 100%; padding: 1.1rem 1.5rem 1.1rem 3.5rem; border: none; border-radius: 999px;
    font-size: 1rem; font-family: inherit; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    transition: all 0.3s; background: white; color: #1e293b;
}
.hero-search-extreme input:focus { outline: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); transform: scale(1.02); }
.hero-search-extreme .search-icon {
    position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%);
    font-size: 1.2rem; color: #64748b; pointer-events: none;
}
.hero-search-extreme .search-clear {
    position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; background: #fee2e2; color: #dc2626;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; text-decoration: none; transition: all 0.2s;
}
.hero-search-extreme .search-clear:hover { background: #dc2626; color: white; }

.hero-trust-row {
    display: flex; gap: 0.75rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;
}
.trust-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    border-radius: 999px; font-size: 0.82rem; font-weight: 600;
}

/* ===== STATS ===== */
.dl-stats-pub {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -3rem 0 3rem; position: relative; z-index: 10;
}
.dl-stat-pub {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.dl-stat-pub::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, #3b82f6), transparent);
}
.dl-stat-pub:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, #3b82f6); }
.dl-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.dl-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, #3b82f6); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.dl-stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.dl-toolbar-pub {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.dl-search { flex: 1; min-width: 240px; position: relative; }
.dl-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.dl-search input:focus { outline: none; border-color: #3b82f6; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
.dl-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }

.dl-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.dl-select:focus { outline: none; border-color: #3b82f6; }

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

/* ===== LIST VIEW ===== */
.file-list-pub { display: flex; flex-direction: column; gap: 1rem; max-width: 1000px; margin: 0 auto; }
.file-item-pub {
    display: flex; align-items: center; gap: 1.25rem; background: var(--bg-primary);
    border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
    transition: all 0.3s; cursor: pointer;
}
.file-item-pub:hover { transform: translateX(8px); border-color: #3b82f6; box-shadow: var(--shadow-md); }
.file-icon-pub {
    width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center;
    justify-content: center; font-size: 1.75rem; flex-shrink: 0;
}
.file-pdf { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
.file-doc { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
.file-xls { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
.file-zip { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
.file-ppt { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
.file-img { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4f46e5; }
.file-other { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; }
.file-info-pub { flex: 1; min-width: 0; }
.file-info-pub h4 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    color: var(--text-primary);
}
.file-meta-pub { display: flex; gap: 0.75rem; font-size: 0.78rem; color: var(--text-muted); flex-wrap: wrap; }
.file-meta-pub span { display: flex; align-items: center; gap: 0.3rem; }
.file-desc-pub {
    font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.4rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.btn-download-pub {
    display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.25rem;
    background: linear-gradient(135deg, #3b82f6, #1e40af); color: white;
    border-radius: var(--radius-md); text-decoration: none; font-weight: 600;
    font-size: 0.85rem; transition: all 0.3s; flex-shrink: 0; border: none; cursor: pointer;
    font-family: inherit;
}
.btn-download-pub:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); filter: brightness(1.1); }

/* ===== GRID VIEW ===== */
.file-grid-pub { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
.file-card-pub {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 1.5rem; transition: all 0.3s; display: flex; flex-direction: column;
    cursor: pointer;
}
.file-card-pub:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: #3b82f6; }
.file-card-header { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.file-card-info h4 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.file-card-meta {
    display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.75rem;
    font-size: 0.72rem; color: var(--text-muted);
}
.file-card-meta span {
    display: flex; align-items: center; gap: 0.25rem;
    background: var(--bg-secondary); padding: 0.2rem 0.55rem; border-radius: 999px;
}
.file-card-desc {
    font-size: 0.82rem; color: var(--text-secondary); line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; margin-bottom: 1rem; flex: 1;
}
.file-card-pub .btn-download-pub { width: 100%; justify-content: center; margin-top: auto; }

/* ===== COMPACT VIEW ===== */
.file-compact-pub {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);
}
.file-compact-item {
    display: grid; grid-template-columns: auto 1fr auto auto; gap: 1rem;
    align-items: center; padding: 0.85rem 1.25rem; border-bottom: 1px solid var(--border);
    transition: all 0.2s; cursor: pointer;
}
.file-compact-item:last-child { border-bottom: none; }
.file-compact-item:hover { background: rgba(59,130,246,0.03); }
.file-compact-icon {
    width: 36px; height: 36px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;
}
.file-compact-info { min-width: 0; }
.file-compact-info h5 {
    font-size: 0.88rem; font-weight: 600; margin-bottom: 0.15rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.file-compact-info span { font-size: 0.72rem; color: var(--text-muted); }
.file-compact-count {
    font-size: 0.78rem; color: var(--text-muted); font-weight: 600;
    display: flex; align-items: center; gap: 0.3rem;
}
.btn-dl-compact {
    width: 32px; height: 32px; border-radius: 8px; background: var(--bg-secondary);
    border: 1px solid var(--border); color: var(--text-secondary);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; text-decoration: none; font-size: 0.9rem;
}
.btn-dl-compact:hover { background: #3b82f6; color: white; border-color: #3b82f6; transform: translateY(-1px); }

/* ===== CHART VIEW ===== */
.chart-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem; margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}

/* ===== TOP DOWNLOADS ===== */
.top-downloads {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-top: 2rem;
    box-shadow: var(--shadow-sm);
}
.top-downloads h3 {
    font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}
.top-list { display: flex; flex-direction: column; gap: 0.75rem; }
.top-item {
    display: flex; gap: 0.75rem; padding: 0.75rem;
    background: var(--bg-secondary); border-radius: var(--radius-md);
    transition: all 0.2s; cursor: pointer; align-items: center;
}
.top-item:hover { background: var(--bg-tertiary); transform: translateX(3px); }
.top-rank {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
    font-family: var(--font-display);
}
.top-rank.top-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.top-rank.top-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.top-rank.top-3 { background: linear-gradient(135deg, #d97706, #92400e); }
.top-info { flex: 1; min-width: 0; }
.top-info h5 {
    font-size: 0.88rem; font-weight: 600; margin-bottom: 0.2rem;
    color: var(--text-primary); line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.top-info span { font-size: 0.72rem; color: var(--text-muted); }
.top-count {
    font-size: 0.85rem; font-weight: 800; color: #3b82f6;
    font-family: var(--font-display);
}

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

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-dl {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
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
.modal-header-dl {
    padding: 2.5rem 2rem; text-align: center; color: white; position: relative;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-dl::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-file-icon {
    width: 80px; height: 80px; border-radius: 20px; margin: 0 auto 1.25rem;
    background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; border: 3px solid rgba(255,255,255,0.3);
}
.modal-title {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.9rem; opacity: 0.95; }

.modal-body-dl { padding: 2rem; }
.modal-info-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem; margin-bottom: 1.5rem;
}
.modal-info-item {
    padding: 0.85rem 1rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
}
.modal-info-label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.25rem;
}
.modal-info-value {
    font-size: 0.92rem; color: var(--text-primary); font-weight: 600;
    word-break: break-word;
}

.modal-desc {
    padding: 1.15rem; background: var(--bg-secondary);
    border-radius: var(--radius-md); border-left: 4px solid #3b82f6;
    font-size: 0.95rem; line-height: 1.7; color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.modal-footer {
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

@media (max-width: 968px) {
    .chart-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .download-hero-extreme { padding: 7rem 0 5rem; }
    .dl-stats-pub { grid-template-columns: 1fr 1fr; margin: -2rem 1rem 2rem; }
    .dl-toolbar-pub { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .file-grid-pub { grid-template-columns: 1fr; }
    .file-item-pub { flex-direction: column; text-align: center; }
    .file-meta-pub { justify-content: center; }
    .file-compact-item { grid-template-columns: auto 1fr auto; }
    .file-compact-count { display: none; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .dl-stats-pub { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="download-hero-extreme">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Pusat Unduhan</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">
            Pusat
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Unduhan</span>
        </h1>
        <p class="page-subtitle" style="max-width: 640px; margin: 0 auto; opacity: 0.92; font-size: 1.1rem; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Akses mudah dan cepat ke berbagai dokumen akademik, formulir, pedoman, dan informasi PMB FKIP UNIMOF.
        </p>

        <div class="hero-search-extreme" data-aos="fade-up" data-aos-delay="200">
            <span class="search-icon">🔍</span>
            <form method="GET" action="" onsubmit="return false;">
                <?php if ($kat !== 'all'): ?><input type="hidden" name="kategori" value="<?= sanitize($kat) ?>"><?php endif; ?>
                <input type="text" id="heroSearch" placeholder="Cari judul dokumen, formulir, atau pedoman..." value="<?= sanitize($q) ?>" autocomplete="off">
                <?php if ($q !== ''): ?>
                    <a href="download.php?kategori=<?= urlencode($kat) ?>&view=<?= urlencode($view) ?>" class="search-clear" title="Clear">✕</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">📂 <?= $total_files ?> Dokumen</span>
            <span class="trust-pill">⬇️ <?= number_format($total_downloads) ?> Total Unduhan</span>
            <span class="trust-pill">🏷️ <?= count($kategori_dist) ?> Kategori</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="dl-stats-pub" data-aos="fade-up">
            <div class="dl-stat-pub" style="--stat-color: #3b82f6;">
                <div class="dl-stat-icon">📂</div>
                <div class="dl-stat-num count-up" data-target="<?= $total_files ?>">0</div>
                <div class="dl-stat-label">Total Dokumen</div>
            </div>
            <div class="dl-stat-pub" style="--stat-color: #10b981;">
                <div class="dl-stat-icon">⬇️</div>
                <div class="dl-stat-num"><?= $total_downloads > 1000 ? round($total_downloads/1000, 1) . 'K' : $total_downloads ?></div>
                <div class="dl-stat-label">Total Unduhan</div>
            </div>
            <div class="dl-stat-pub" style="--stat-color: #f59e0b;">
                <div class="dl-stat-icon">🏷️</div>
                <div class="dl-stat-num count-up" data-target="<?= count($kategori_dist) ?>">0</div>
                <div class="dl-stat-label">Kategori</div>
            </div>
            <div class="dl-stat-pub" style="--stat-color: #8b5cf6;">
                <div class="dl-stat-icon">📄</div>
                <div class="dl-stat-num count-up" data-target="<?= count($ext_dist) ?>">0</div>
                <div class="dl-stat-label">Jenis File</div>
            </div>
        </div>

        <?php if (empty($files)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📂</div>
                <h3>Tidak ada file ditemukan</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">
                    <?php if ($q || $kat !== 'all'): ?>
                        Coba ubah kata kunci pencarian atau pilih kategori lain.
                    <?php else: ?>
                        Belum ada dokumen yang diunggah.
                    <?php endif; ?>
                </p>
                <?php if ($q || $kat !== 'all'): ?>
                    <a href="download.php" class="modal-btn secondary">🔄 Reset Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="dl-toolbar-pub" data-aos="fade-up">
                <div class="dl-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="dlSearch" placeholder="Cari judul dokumen..." value="<?= sanitize($q) ?>">
                </div>
                <select class="dl-select" id="sortSelect">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>🕐 Terlama</option>
                    <?php if ($dl_cols['downloads_count']): ?>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>🔥 Terpopuler</option>
                    <?php endif; ?>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'compact' ? 'active' : '' ?>" onclick="switchView('compact')">📑 Compact</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $kat === 'all' ? 'active' : '' ?>" href="download.php?kategori=all&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    📂 Semua <span class="pill-count"><?= $total_files ?></span>
                </a>
                <?php
                $kategori_list = ['Akademik','PMB','Formulir','Pedoman','Lainnya'];
                foreach ($kategori_list as $k):
                    $cnt = $kategori_dist[$k] ?? 0;
                    if ($cnt === 0 && $kat !== $k) continue;
                ?>
                <a class="filter-pill <?= $kat === $k ? 'active' : '' ?>" href="download.php?kategori=<?= urlencode($k) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    <?= $k ?> <span class="pill-count"><?= $cnt ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="file-list-pub" data-aos="fade-up">
                <?php foreach ($files as $f):
                    $ext = $dl_cols['file_name'] ? strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION)) : '';
                    $file_class = 'file-other'; $icon = '📄';
                    if (in_array($ext, ['pdf'])) { $file_class = 'file-pdf'; $icon = '📕'; }
                    elseif (in_array($ext, ['doc','docx'])) { $file_class = 'file-doc'; $icon = '📘'; }
                    elseif (in_array($ext, ['xls','xlsx'])) { $file_class = 'file-xls'; $icon = '📗'; }
                    elseif (in_array($ext, ['zip','rar','7z'])) { $file_class = 'file-zip'; $icon = '📦'; }
                    elseif (in_array($ext, ['ppt','pptx'])) { $file_class = 'file-ppt'; $icon = '📙'; }
                    elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $file_class = 'file-img'; $icon = '🖼️'; }
                ?>
                <div class="file-item-pub" onclick='openFileModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($icon) ?>, <?= json_encode($file_class) ?>)'>
                    <div class="file-icon-pub <?= $file_class ?>"><?= $icon ?></div>
                    <div class="file-info-pub">
                        <h4><?= sanitize($f['judul']) ?></h4>
                        <div class="file-meta-pub">
                            <?php if ($dl_cols['kategori'] && !empty($f['kategori'])): ?>
                                <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                            <?php endif; ?>
                            <?php if ($dl_cols['file_size'] && !empty($f['file_size'])): ?>
                                <span>💾 <?= sanitize($f['file_size']) ?></span>
                            <?php endif; ?>
                            <?php if ($dl_cols['downloads_count']): ?>
                                <span>⬇️ <?= number_format($f['downloads_count'] ?? 0) ?></span>
                            <?php endif; ?>
                            <?php if ($dl_cols['created_at'] && !empty($f['created_at'])): ?>
                                <span>📅 <?= date('d M Y', strtotime($f['created_at'])) ?></span>
                            <?php endif; ?>
                            <?php if ($ext): ?>
                                <span>📎 <?= strtoupper($ext) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($dl_cols['deskripsi'] && !empty($f['deskripsi'])): ?>
                            <p class="file-desc-pub"><?= sanitize($f['deskripsi']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-download-pub" download onclick="event.stopPropagation(); incrementDownload(<?= $f['id'] ?>, this)">
                        ⬇️ Unduh
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="file-grid-pub" data-aos="fade-up">
                <?php foreach ($files as $f):
                    $ext = $dl_cols['file_name'] ? strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION)) : '';
                    $file_class = 'file-other'; $icon = '📄';
                    if (in_array($ext, ['pdf'])) { $file_class = 'file-pdf'; $icon = '📕'; }
                    elseif (in_array($ext, ['doc','docx'])) { $file_class = 'file-doc'; $icon = '📘'; }
                    elseif (in_array($ext, ['xls','xlsx'])) { $file_class = 'file-xls'; $icon = '📗'; }
                    elseif (in_array($ext, ['zip','rar','7z'])) { $file_class = 'file-zip'; $icon = '📦'; }
                    elseif (in_array($ext, ['ppt','pptx'])) { $file_class = 'file-ppt'; $icon = '📙'; }
                    elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $file_class = 'file-img'; $icon = '🖼️'; }
                ?>
                <div class="file-card-pub" onclick='openFileModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($icon) ?>, <?= json_encode($file_class) ?>)'>
                    <div class="file-card-header">
                        <div class="file-icon-pub <?= $file_class ?>"><?= $icon ?></div>
                        <div class="file-card-info">
                            <h4><?= sanitize($f['judul']) ?></h4>
                        </div>
                    </div>
                    <div class="file-card-meta">
                        <?php if ($dl_cols['kategori'] && !empty($f['kategori'])): ?>
                            <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                        <?php endif; ?>
                        <?php if ($dl_cols['file_size'] && !empty($f['file_size'])): ?>
                            <span>💾 <?= sanitize($f['file_size']) ?></span>
                        <?php endif; ?>
                        <?php if ($dl_cols['downloads_count']): ?>
                            <span>⬇️ <?= number_format($f['downloads_count'] ?? 0) ?></span>
                        <?php endif; ?>
                        <?php if ($ext): ?>
                            <span>📎 <?= strtoupper($ext) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($dl_cols['deskripsi'] && !empty($f['deskripsi'])): ?>
                        <p class="file-card-desc"><?= sanitize($f['deskripsi']) ?></p>
                    <?php endif; ?>
                    <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-download-pub" download onclick="event.stopPropagation(); incrementDownload(<?= $f['id'] ?>, this)">
                        ⬇️ Unduh Sekarang
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== COMPACT VIEW ===== -->
            <?php if ($view === 'compact'): ?>
            <div class="file-compact-pub" data-aos="fade-up">
                <?php foreach ($files as $f):
                    $ext = $dl_cols['file_name'] ? strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION)) : '';
                    $file_class = 'file-other'; $icon = '📄';
                    if (in_array($ext, ['pdf'])) { $file_class = 'file-pdf'; $icon = '📕'; }
                    elseif (in_array($ext, ['doc','docx'])) { $file_class = 'file-doc'; $icon = '📘'; }
                    elseif (in_array($ext, ['xls','xlsx'])) { $file_class = 'file-xls'; $icon = '📗'; }
                    elseif (in_array($ext, ['zip','rar','7z'])) { $file_class = 'file-zip'; $icon = '📦'; }
                    elseif (in_array($ext, ['ppt','pptx'])) { $file_class = 'file-ppt'; $icon = '📙'; }
                ?>
                <div class="file-compact-item" onclick='openFileModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($icon) ?>, <?= json_encode($file_class) ?>)'>
                    <div class="file-compact-icon <?= $file_class ?>"><?= $icon ?></div>
                    <div class="file-compact-info">
                        <h5><?= sanitize($f['judul']) ?></h5>
                        <span><?= sanitize($f['kategori'] ?? 'Lainnya') ?> • <?= sanitize($f['file_size'] ?? '') ?></span>
                    </div>
                    <div class="file-compact-count">
                        <?php if ($dl_cols['downloads_count']): ?>
                            ⬇️ <?= number_format($f['downloads_count'] ?? 0) ?>
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-dl-compact" download onclick="event.stopPropagation(); incrementDownload(<?= $f['id'] ?>, this)" title="Unduh">
                        ⬇️
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🏷️ Dokumen per Kategori</h3>
                    <div id="kategoriChart"></div>
                </div>
                <div class="chart-card">
                    <h3>📎 Jenis File</h3>
                    <div id="extChart"></div>
                </div>
            </div>

            <!-- Top Downloads -->
            <?php if (!empty($top_files)): ?>
            <div class="top-downloads" data-aos="fade-up">
                <h3>🔥 File Terpopuler</h3>
                <div class="top-list">
                    <?php foreach ($top_files as $i => $t):
                        $ext = strtolower(pathinfo($t['file_name'] ?? '', PATHINFO_EXTENSION));
                        $icon = '📄';
                        if (in_array($ext, ['pdf'])) $icon = '📕';
                        elseif (in_array($ext, ['doc','docx'])) $icon = '📘';
                        elseif (in_array($ext, ['xls','xlsx'])) $icon = '📗';
                        elseif (in_array($ext, ['zip','rar','7z'])) $icon = '📦';
                    ?>
                    <div class="top-item" onclick="window.location='<?= base_url('assets/downloads/' . urlencode($t['file_name'])) ?>'">
                        <div class="top-rank <?= $i < 3 ? 'top-' . ($i + 1) : '' ?>"><?= $i + 1 ?></div>
                        <div style="font-size:1.5rem;"><?= $icon ?></div>
                        <div class="top-info">
                            <h5><?= sanitize($t['judul']) ?></h5>
                            <span><?= sanitize($t['kategori'] ?? 'Lainnya') ?></span>
                        </div>
                        <div class="top-count">⬇️ <?= number_format($t['downloads_count']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="fileModal" onclick="if(event.target===this)closeFileModal()">
    <div class="modal-content-dl" id="fileModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const dlCols = <?= json_encode($dl_cols) ?>;
const kategoriDist = <?= json_encode($kategori_dist) ?>;
const extDist = <?= json_encode($ext_dist) ?>;

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
function setupSearch(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function() {
        clearTimeout(searchTimer);
        const v = this.value;
        searchTimer = setTimeout(() => {
            const url = new URL(window.location);
            if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
            window.location = url;
        }, 500);
    });
}
setupSearch('heroSearch');
setupSearch('dlSearch');

document.getElementById('sortSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('sort', this.value);
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== DOWNLOAD COUNTER =====
function incrementDownload(id, btn) {
    // Update UI first for instant feedback
    if (btn) {
        const original = btn.innerHTML;
        btn.innerHTML = '✅ Dimulai...';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    }

    // Background request
    fetch('<?= base_url('api/download-counter.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).catch(() => {});
}

// ===== HELPERS =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL =====
function openFileModal(f, icon, fileClass) {
    const ext = dlCols.file_name && f.file_name ? f.file_name.split('.').pop().toUpperCase() : '';

    let info_items = '';
    if (dlCols.kategori && f.kategori) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Kategori</div><div class="modal-info-value">${escapeHtml(f.kategori)}</div></div>`;
    }
    if (dlCols.file_size && f.file_size) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">💾 Ukuran</div><div class="modal-info-value">${escapeHtml(f.file_size)}</div></div>`;
    }
    if (dlCols.downloads_count) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">⬇️ Total Unduhan</div><div class="modal-info-value">${Number(f.downloads_count || 0).toLocaleString('id-ID')} kali</div></div>`;
    }
    if (ext) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📎 Tipe File</div><div class="modal-info-value">${ext}</div></div>`;
    }
    if (dlCols.versi && f.versi) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Versi</div><div class="modal-info-value">${escapeHtml(f.versi)}</div></div>`;
    }
    if (dlCols.author && f.author) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">✍️ Pengunggah</div><div class="modal-info-value">${escapeHtml(f.author)}</div></div>`;
    }
    if (dlCols.created_at && f.created_at) {
        info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📅 Diunggah</div><div class="modal-info-value">${new Date(f.created_at).toLocaleDateString('id-ID', {weekday:'long', day:'2-digit', month:'long', year:'numeric'})}</div></div>`;
    }

    const html = `
        <button class="modal-close" onclick="closeFileModal()">✕</button>
        <div class="modal-header-dl">
            <div class="modal-header-content">
                <div class="modal-file-icon ${fileClass}" style="background:rgba(255,255,255,0.2);">${icon}</div>
                <h2 class="modal-title">${escapeHtml(f.judul)}</h2>
                <div class="modal-subtitle">${dlCols.kategori && f.kategori ? escapeHtml(f.kategori) : 'Dokumen FKIP UNIMOF'}</div>
            </div>
        </div>
        <div class="modal-body-dl">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}
            ${dlCols.deskripsi && f.deskripsi ? `<div class="modal-desc">${escapeHtml(f.deskripsi)}</div>` : ''}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareFile(${JSON.stringify(f).replace(/"/g, "&quot;")})'>🔗 Share</button>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="closeFileModal()">Tutup</button>
                <a href="${'<?= base_url('assets/downloads/') ?>' + encodeURIComponent(f.file_name)}" class="modal-btn primary" download onclick="incrementDownload(${f.id}, this)">⬇️ Unduh Sekarang</a>
            </div>
        </div>
    `;
    document.getElementById('fileModalContent').innerHTML = html;
    document.getElementById('fileModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeFileModal() {
    document.getElementById('fileModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareFile(f) {
    const url = window.location.origin + '<?= base_url('assets/downloads/') ?>' + encodeURIComponent(f.file_name);
    const text = `📂 ${f.judul}\n${dlCols.kategori && f.kategori ? '🏷️ ' + f.kategori + '\n' : ''}${dlCols.file_size && f.file_size ? '💾 ' + f.file_size + '\n' : ''}\nUnduh dari Pusat Unduhan FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: f.judul, text, url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + '\n' + url);
        pubToast('Info file disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const katColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
const extColors = {
    'PDF': '#dc2626', 'DOC': '#2563eb', 'DOCX': '#2563eb',
    'XLS': '#16a34a', 'XLSX': '#16a34a', 'ZIP': '#d97706',
    'RAR': '#d97706', 'PPT': '#db2777', 'PPTX': '#db2777',
    'JPG': '#4f46e5', 'PNG': '#4f46e5'
};

if (Object.keys(kategoriDist).length > 0) {
    new ApexCharts(document.querySelector("#kategoriChart"), {
        series: Object.values(kategoriDist),
        labels: Object.keys(kategoriDist),
        chart: { type: 'donut', height: 320 },
        colors: katColors.slice(0, Object.keys(kategoriDist).length),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(kategoriDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(extDist).length > 0) {
    new ApexCharts(document.querySelector("#extChart"), {
        series: [{ data: Object.values(extDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: Object.keys(extDist).map(k => extColors[k] || '#6b7280'),
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(extDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('heroSearch')?.focus();
    }
    if (e.key === 'Escape') closeFileModal();
});

console.log('%c📂 Pusat Unduhan FKIP UNIMOF - EXTREME MULTIMATE', 'color:#3b82f6;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>