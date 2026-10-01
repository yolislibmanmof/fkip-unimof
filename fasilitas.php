<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Fasilitas & Laboratorium';
$page_description = 'Sarana dan prasarana modern FKIP UNIMOF untuk mendukung kegiatan belajar mengajar dan penelitian.';

// ===== SCHEMA-SAFE: deteksi kolom fasilitas =====
$fas_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `fasilitas`")->fetchAll(PDO::FETCH_COLUMN);
    $fas_cols = [
        'nama'                => in_array('nama', $cols, true),
        'gambar'              => in_array('gambar', $cols, true),
        'gambar_gallery'      => in_array('gambar_gallery', $cols, true),
        'deskripsi'           => in_array('deskripsi', $cols, true),
        'kategori'            => in_array('kategori', $cols, true),
        'kapasitas'           => in_array('kapasitas', $cols, true),
        'status'              => in_array('status', $cols, true),
        'lokasi'              => in_array('lokasi', $cols, true),
        'luas'                => in_array('luas', $cols, true),
        'fasilitas_pendukung' => in_array('fasilitas_pendukung', $cols, true),
        'is_featured'         => in_array('is_featured', $cols, true),
        'created_at'          => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $fas_cols = array_fill_keys(['nama','gambar','gambar_gallery','deskripsi','kategori','kapasitas','status','lokasi','luas','fasilitas_pendukung','is_featured','created_at'], false);
}

// ===== AMBIL DATA FASILITAS =====
$where = $fas_cols['status'] ? "WHERE status = 'Aktif'" : "WHERE 1=1";
$order_by = $fas_cols['is_featured'] ? "is_featured DESC, " . ($fas_cols['created_at'] ? "created_at DESC" : "id DESC") : ($fas_cols['created_at'] ? "created_at DESC" : "id DESC");

$stmt = $pdo->query("SELECT * FROM fasilitas $where ORDER BY $order_by");
$fasilitas_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($fasilitas_list);
$stat_lab = 0; $stat_kelas = 0; $stat_perpustakaan = 0;
$stat_kapasitas = 0; $stat_luas = 0; $stat_featured = 0;
$kategori_dist = [];
$kapasitas_bins = ['1-20' => 0, '21-50' => 0, '51-100' => 0, '100+' => 0];

foreach ($fasilitas_list as $f) {
    $kat = $f['kategori'] ?? '';
    if ($kat === 'Laboratorium') $stat_lab++;
    elseif ($kat === 'Ruang Kelas') $stat_kelas++;
    elseif (stripos($kat, 'Perpustakaan') !== false) $stat_perpustakaan++;

    if ($kat !== '') $kategori_dist[$kat] = ($kategori_dist[$kat] ?? 0) + 1;

    if ($fas_cols['kapasitas'] && !empty($f['kapasitas'])) {
        $kap = (int)$f['kapasitas'];
        $stat_kapasitas += $kap;
        if ($kap <= 20) $kapasitas_bins['1-20']++;
        elseif ($kap <= 50) $kapasitas_bins['21-50']++;
        elseif ($kap <= 100) $kapasitas_bins['51-100']++;
        else $kapasitas_bins['100+']++;
    }

    if ($fas_cols['luas'] && !empty($f['luas'])) {
        $luas_num = (int)preg_replace('/[^0-9]/', '', $f['luas']);
        $stat_luas += $luas_num;
    }

    if ($fas_cols['is_featured'] && !empty($f['is_featured'])) $stat_featured++;
}

// ===== FEATURED SPOTLIGHT =====
$featured = null;
if ($fas_cols['is_featured']) {
    foreach ($fasilitas_list as $f) {
        if (!empty($f['is_featured'])) { $featured = $f; break; }
    }
}

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_kategori = $_GET['kategori'] ?? 'all';
$view = $_GET['view'] ?? 'grid';
$sort = $_GET['sort'] ?? 'default';

// Kategori unik
$kategoris = array_keys($kategori_dist);
sort($kategoris);

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.fasilitas-hero-extreme {
    position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.fasilitas-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(16,185,129,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.fasilitas-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: floatParticle 30s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.fasilitas-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.fasilitas-hero-extreme .breadcrumb a, .fasilitas-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.fasilitas-hero-extreme .breadcrumb a:hover { color: white; }

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

/* ===== STATS ===== */
.fasilitas-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.fasilitas-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.fasilitas-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.fasilitas-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.fasilitas-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.fasilitas-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.fasilitas-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SPOTLIGHT ===== */
.spotlight-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: 1fr 1fr;
    min-height: 380px; position: relative;
}
.spotlight-section::before {
    content: ''; position: absolute; top: 1rem; left: 1rem; z-index: 5;
    background: linear-gradient(135deg, #10b981, #059669); color: white;
    padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.72rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    display: flex; align-items: center; gap: 0.3rem;
}
.spotlight-image {
    position: relative; overflow: hidden; min-height: 380px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center; font-size: 5rem;
}
.spotlight-image img {
    width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;
    transition: transform 0.6s ease;
}
.spotlight-section:hover .spotlight-image img { transform: scale(1.05); }
.spotlight-content {
    padding: 2.5rem; display: flex; flex-direction: column; justify-content: center;
}
.spotlight-kategori {
    display: inline-flex; padding: 0.35rem 0.85rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.72rem; font-weight: 700; color: var(--primary);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.spotlight-title {
    font-family: var(--font-display); font-size: 1.85rem; font-weight: 900;
    line-height: 1.2; margin-bottom: 1rem; color: var(--text-primary);
}
.spotlight-desc {
    color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7;
    margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3;
    -webkit-box-orient: vertical; overflow: hidden;
}
.spotlight-meta-row {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem;
}
.spotlight-meta-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.85rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.82rem; font-weight: 600;
    color: var(--text-secondary); border: 1px solid var(--border);
}
.spotlight-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

/* ===== TOOLBAR ===== */
.fasilitas-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.fasilitas-search { flex: 1; min-width: 240px; position: relative; }
.fasilitas-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.fasilitas-search input:focus { outline: none; border-color: #10b981; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }
.fasilitas-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.fasilitas-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.fasilitas-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.fasilitas-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.fasilitas-select:focus { outline: none; border-color: #10b981; }

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
.view-btn.active { background: linear-gradient(135deg, #10b981, #059669); color: white; }
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
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; border-color: #059669;
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.fasilitas-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.fasilitas-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer; position: relative;
}
.fasilitas-card.featured-card {
    grid-column: span 2;
}
.fasilitas-card.featured-card .fasilitas-image { height: 320px; }
.fasilitas-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #10b981;
}
.fasilitas-image {
    height: 220px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    font-size: 4rem; position: relative; overflow: hidden;
}
.fasilitas-image img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.fasilitas-card:hover .fasilitas-image img { transform: scale(1.08); }
.fasilitas-kategori-badge {
    position: absolute; top: 1rem; left: 1rem;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; color: #10b981;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 2;
}
.fasilitas-featured-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: linear-gradient(135deg, #10b981, #059669); color: white;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; display: flex; align-items: center; gap: 0.3rem;
    z-index: 2; box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}
.fasilitas-content { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
.fasilitas-title {
    font-family: var(--font-display); font-size: 1.15rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.65rem; line-height: 1.3;
}
.fasilitas-desc {
    color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.fasilitas-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem;
    font-size: 0.78rem; color: var(--text-muted);
}
.fasilitas-meta span { display: flex; align-items: center; gap: 0.3rem; }
.fasilitas-footer {
    display: flex; gap: 0.5rem; flex-wrap: wrap; padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.btn-action {
    padding: 0.5rem 0.9rem; border-radius: 8px; font-size: 0.78rem;
    font-weight: 600; border: 1px solid var(--border); background: var(--bg-secondary);
    color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none;
    font-family: inherit;
}
.btn-action:hover { background: #10b981; color: white; border-color: #10b981; transform: translateY(-1px); }
.btn-action.primary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; border-color: #059669;
}
.btn-action.primary:hover { filter: brightness(1.1); }

/* ===== LIST VIEW ===== */
.fasilitas-list { display: flex; flex-direction: column; gap: 1rem; max-width: 1000px; margin: 0 auto; }
.fasilitas-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; transition: all 0.3s;
    display: grid; grid-template-columns: 220px 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.fasilitas-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #10b981; }
.fasilitas-list-image {
    height: 160px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem; overflow: hidden; position: relative;
}
.fasilitas-list-image img { width: 100%; height: 100%; object-fit: cover; }
.fasilitas-list-content { padding: 1rem 0; }
.fasilitas-list-content h3 {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-primary);
}
.fasilitas-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary); margin-bottom: 0.5rem;
}
.fasilitas-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.fasilitas-list-desc {
    font-size: 0.85rem; color: var(--text-muted);
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.fasilitas-list-actions {
    padding: 1rem 1rem 1rem 0; display: flex; flex-direction: column; gap: 0.4rem;
}

/* ===== GALLERY VIEW ===== */
.gallery-view {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1rem; grid-auto-rows: 220px;
}
.gallery-item {
    position: relative; overflow: hidden; border-radius: var(--radius-lg);
    cursor: pointer; transition: all 0.3s;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem;
}
.gallery-item:hover { transform: scale(1.02); box-shadow: var(--shadow-xl); }
.gallery-item img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.gallery-item:hover img { transform: scale(1.1); }
.gallery-item.large { grid-column: span 2; grid-row: span 2; }
.gallery-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.2) 60%, transparent 100%);
    padding: 1.25rem; display: flex; flex-direction: column; justify-content: flex-end;
    color: white; opacity: 0; transition: opacity 0.3s;
}
.gallery-item:hover .gallery-overlay { opacity: 1; }
.gallery-overlay h4 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.gallery-overlay span {
    font-size: 0.78rem; opacity: 0.9; display: flex; align-items: center; gap: 0.3rem;
}

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

/* ===== CTA ===== */
.fasilitas-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #064e34 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.fasilitas-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.fasilitas-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY ===== */
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
.modal-overlay-ext {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay-ext.show { display: flex; }
.modal-content-ext {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto;
    position: relative; animation: modalPopExt 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
}
@keyframes modalPopExt {
    from { transform: scale(0.9) translateY(20px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.modal-close-ext {
    position: absolute; top: 1rem; right: 1rem; width: 40px; height: 40px;
    background: rgba(255,255,255,0.2); border: none; border-radius: 50%;
    cursor: pointer; font-size: 1.1rem; display: flex; align-items: center;
    justify-content: center; transition: all 0.2s; color: white; z-index: 10;
}
.modal-close-ext:hover { background: #dc2626; transform: rotate(90deg); }

.modal-image-ext {
    height: 360px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center; font-size: 5rem;
    position: relative; overflow: hidden;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-image-ext img { width: 100%; height: 100%; object-fit: cover; }
.modal-image-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(15,23,42,0.6) 0%, transparent 40%);
    pointer-events: none;
}

.modal-gallery-thumbs {
    display: flex; gap: 0.5rem; padding: 1rem 2rem 0;
    overflow-x: auto; scrollbar-width: thin;
}
.modal-gallery-thumb {
    width: 80px; height: 60px; border-radius: 8px; overflow: hidden;
    cursor: pointer; flex-shrink: 0; border: 2px solid transparent;
    transition: all 0.2s; opacity: 0.7;
}
.modal-gallery-thumb:hover { opacity: 1; }
.modal-gallery-thumb.active { border-color: #10b981; opacity: 1; }
.modal-gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }

.modal-body-ext { padding: 2rem; }
.modal-title-ext {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle-ext {
    font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
}

.modal-info-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem; margin-bottom: 1.5rem;
}
.modal-info-item {
    padding: 1rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    text-align: center;
}
.modal-info-label {
    font-size: 0.68rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.35rem;
}
.modal-info-value {
    font-size: 1.1rem; font-weight: 800; color: var(--text-primary);
    font-family: var(--font-display);
}

.modal-section-ext { margin-bottom: 1.5rem; }
.modal-section-ext h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section-ext p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }

.modal-features-list {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}
.modal-feature-item {
    padding: 0.65rem 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.88rem; color: var(--text-primary);
    display: flex; align-items: center; gap: 0.5rem;
    transition: all 0.2s;
}
.modal-feature-item:hover { border-color: #10b981; transform: translateX(3px); }
.modal-feature-item::before {
    content: '✓'; color: #10b981; font-weight: 800; font-size: 1rem;
}

.modal-footer-ext {
    padding: 1rem 2rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: space-between; flex-wrap: wrap;
    background: var(--bg-secondary);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
}
.modal-btn {
    padding: 0.7rem 1.25rem; border-radius: 8px; border: none;
    font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    transition: all 0.2s;
}
.modal-btn.primary { background: linear-gradient(135deg, #10b981, #059669); color: white; }
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
    .spotlight-section { grid-template-columns: 1fr; }
    .spotlight-image { min-height: 250px; }
    .fasilitas-card.featured-card { grid-column: span 1; }
    .gallery-item.large { grid-column: span 1; grid-row: span 1; }
}
@media (max-width: 768px) {
    .fasilitas-hero-extreme { padding: 8rem 0 5rem; }
    .fasilitas-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .fasilitas-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .fasilitas-grid { grid-template-columns: 1fr; }
    .fasilitas-list-item { grid-template-columns: 1fr; text-align: center; }
    .fasilitas-list-image { height: 200px; }
    .fasilitas-list-actions { padding: 0 1rem 1rem; flex-direction: row; justify-content: center; }
    .gallery-view { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); grid-auto-rows: 160px; }
    .modal-info-grid { grid-template-columns: 1fr 1fr; }
    .modal-features-list { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .fasilitas-stats-bar { grid-template-columns: 1fr; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="fasilitas-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Fasilitas</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>Modern Learning Environment</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Fasilitas &
            <span style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Laboratorium</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Sarana dan prasarana modern yang mendukung kegiatan belajar mengajar, penelitian, dan pengembangan kompetensi mahasiswa FKIP UNIMOF.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">🏢 <?= $stat_total ?> Fasilitas</span>
            <span class="trust-pill">🧪 <?= $stat_lab ?> Laboratorium</span>
            <span class="trust-pill">👥 <?= number_format($stat_kapasitas) ?> Kapasitas</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="fasilitas-stats-bar" data-aos="fade-up">
            <div class="fasilitas-stat-card" style="--stat-color: #3b82f6;">
                <div class="fasilitas-stat-icon">🏢</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="fasilitas-stat-label">Total Fasilitas</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #ef4444;">
                <div class="fasilitas-stat-icon">🧪</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_lab ?>">0</div>
                <div class="fasilitas-stat-label">Laboratorium</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #f59e0b;">
                <div class="fasilitas-stat-icon">🏫</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_kelas ?>">0</div>
                <div class="fasilitas-stat-label">Ruang Kelas</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #10b981;">
                <div class="fasilitas-stat-icon">👥</div>
                <div class="fasilitas-stat-num"><?= $stat_kapasitas > 1000 ? round($stat_kapasitas/1000, 1) . 'K' : $stat_kapasitas ?></div>
                <div class="fasilitas-stat-label">Total Kapasitas</div>
            </div>
            <?php if ($stat_luas > 0): ?>
            <div class="fasilitas-stat-card" style="--stat-color: #8b5cf6;">
                <div class="fasilitas-stat-icon">📐</div>
                <div class="fasilitas-stat-num"><?= $stat_luas > 1000 ? round($stat_luas/1000, 1) . 'K' : $stat_luas ?></div>
                <div class="fasilitas-stat-label">Total Luas (m²)</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Spotlight -->
        <?php if ($featured):
            $icon = $featured['kategori'] === 'Laboratorium' ? '🧪' : ($featured['kategori'] === 'Perpustakaan' ? '📚' : ($featured['kategori'] === 'Ruang Kelas' ? '🏫' : '🏢'));
        ?>
        <div class="spotlight-section" data-aos="fade-up" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($featured), ENT_QUOTES, "UTF-8") ?>)'>
            <span style="position:absolute;top:1rem;left:1rem;z-index:5;background:linear-gradient(135deg,#10b981,#059669);color:white;padding:0.4rem 1rem;border-radius:999px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;display:flex;align-items:center;gap:0.3rem;">⭐ Fasilitas Unggulan</span>
            <div class="spotlight-image">
                <?php if ($fas_cols['gambar'] && !empty($featured['gambar'])): ?>
                    <img src="<?= asset('uploads/fasilitas/' . basename($featured['gambar'])) ?>" alt="<?= sanitize($featured['nama']) ?>" loading="lazy">
                <?php else: ?>
                    <span><?= $icon ?></span>
                <?php endif; ?>
            </div>
            <div class="spotlight-content">
                <span class="spotlight-kategori"><?= sanitize($featured['kategori'] ?? 'Fasilitas') ?></span>
                <h2 class="spotlight-title"><?= sanitize($featured['nama']) ?></h2>
                <p class="spotlight-desc"><?= sanitize(excerpt($featured['deskripsi'] ?? 'Fasilitas modern FKIP UNIMOF.', 200)) ?></p>
                <div class="spotlight-meta-row">
                    <?php if ($fas_cols['kapasitas'] && !empty($featured['kapasitas'])): ?>
                        <span class="spotlight-meta-pill">👥 <?= sanitize($featured['kapasitas']) ?> orang</span>
                    <?php endif; ?>
                    <?php if ($fas_cols['luas'] && !empty($featured['luas'])): ?>
                        <span class="spotlight-meta-pill">📐 <?= sanitize($featured['luas']) ?></span>
                    <?php endif; ?>
                    <?php if ($fas_cols['lokasi'] && !empty($featured['lokasi'])): ?>
                        <span class="spotlight-meta-pill">📍 <?= excerpt($featured['lokasi'], 30) ?></span>
                    <?php endif; ?>
                </div>
                <div class="spotlight-actions">
                    <button class="btn-action primary" onclick='event.stopPropagation(); openFasilitasModal(<?= htmlspecialchars(json_encode($featured), ENT_QUOTES, "UTF-8") ?>)'>👁️ Lihat Detail</button>
                    <button class="btn-action" onclick='event.stopPropagation(); shareFasilitas(<?= htmlspecialchars(json_encode($featured), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($fasilitas_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🏢</div>
                <h3>Belum ada data fasilitas</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Informasi fasilitas akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="fasilitas-toolbar" data-aos="fade-up">
                <div class="fasilitas-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="fasilitasSearch" placeholder="Cari nama fasilitas atau lokasi..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="fasilitas.php?kategori=<?= urlencode($filter_kategori) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>" class="s-clear" title="Clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="fasilitas-select" id="sortSelect">
                    <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>🔥 Default</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                    <option value="kapasitas" <?= $sort === 'kapasitas' ? 'selected' : '' ?>>👥 Kapasitas</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'gallery' ? 'active' : '' ?>" onclick="switchView('gallery')">🖼️ Gallery</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
                </div>
            </div>

            <!-- Filter Pills -->
            <?php if (!empty($kategoris)): ?>
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_kategori === 'all' ? 'active' : '' ?>" href="fasilitas.php?kategori=all&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    🏢 Semua <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php
                $kategori_icons = ['Laboratorium'=>'🧪','Ruang Kelas'=>'🏫','Perpustakaan'=>'📚','Fasilitas Umum'=>'🏢','Aula'=>'🎭','Masjid'=>'🕌','Kantin'=>'🍽️','Parkir'=>'🅿️'];
                foreach ($kategoris as $k):
                    $icon = $kategori_icons[$k] ?? '🏢';
                ?>
                <a class="filter-pill <?= $filter_kategori === $k ? 'active' : '' ?>" href="fasilitas.php?kategori=<?= urlencode($k) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    <?= $icon ?> <?= sanitize($k) ?> <span class="pill-count"><?= $kategori_dist[$k] ?? 0 ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="fasilitas-grid" data-aos="fade-up">
                <?php foreach ($fasilitas_list as $idx => $f):
                    $icon = $f['kategori'] === 'Laboratorium' ? '🧪' : ($f['kategori'] === 'Perpustakaan' ? '📚' : ($f['kategori'] === 'Ruang Kelas' ? '🏫' : ($kategori_icons[$f['kategori']] ?? '🏢')));
                    $is_featured = $fas_cols['is_featured'] && !empty($f['is_featured']);
                ?>
                <article class="fasilitas-card <?= $is_featured && $idx === 0 ? 'featured-card' : '' ?>" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="fasilitas-image">
                        <?php if ($fas_cols['gambar'] && !empty($f['gambar'])): ?>
                            <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>" loading="lazy">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                        <?php if ($fas_cols['kategori'] && !empty($f['kategori'])): ?>
                            <span class="fasilitas-kategori-badge"><?= sanitize($f['kategori']) ?></span>
                        <?php endif; ?>
                        <?php if ($is_featured): ?>
                            <span class="fasilitas-featured-badge">⭐ Unggulan</span>
                        <?php endif; ?>
                    </div>
                    <div class="fasilitas-content">
                        <h3 class="fasilitas-title"><?= sanitize($f['nama']) ?></h3>
                        <p class="fasilitas-desc"><?= excerpt($f['deskripsi'] ?? 'Fasilitas FKIP UNIMOF.', 120) ?></p>
                        <div class="fasilitas-meta">
                            <?php if ($fas_cols['kapasitas'] && !empty($f['kapasitas'])): ?>
                                <span>👥 <?= sanitize($f['kapasitas']) ?> orang</span>
                            <?php endif; ?>
                            <?php if ($fas_cols['luas'] && !empty($f['luas'])): ?>
                                <span>📐 <?= sanitize($f['luas']) ?></span>
                            <?php endif; ?>
                            <?php if ($fas_cols['lokasi'] && !empty($f['lokasi'])): ?>
                                <span>📍 <?= excerpt($f['lokasi'], 25) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="fasilitas-footer" onclick="event.stopPropagation();">
                            <button class="btn-action primary" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                            <button class="btn-action" onclick='shareFasilitas(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                            <?php if ($fas_cols['lokasi'] && !empty($f['lokasi'])): ?>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($f['lokasi']) ?>" target="_blank" class="btn-action" onclick="event.stopPropagation();">🗺️ Peta</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="fasilitas-list" data-aos="fade-up">
                <?php foreach ($fasilitas_list as $f):
                    $icon = $f['kategori'] === 'Laboratorium' ? '🧪' : ($f['kategori'] === 'Perpustakaan' ? '📚' : ($f['kategori'] === 'Ruang Kelas' ? '🏫' : ($kategori_icons[$f['kategori']] ?? '🏢')));
                ?>
                <div class="fasilitas-list-item" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="fasilitas-list-image">
                        <?php if ($fas_cols['gambar'] && !empty($f['gambar'])): ?>
                            <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>" loading="lazy">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="fasilitas-list-content">
                        <h3><?= sanitize($f['nama']) ?></h3>
                        <div class="fasilitas-list-meta">
                            <span class="fasilitas-kategori-badge" style="position:static; padding:0.2rem 0.6rem; font-size:0.68rem;"><?= sanitize($f['kategori'] ?? '-') ?></span>
                            <?php if ($fas_cols['kapasitas'] && !empty($f['kapasitas'])): ?>
                                <span>👥 <?= sanitize($f['kapasitas']) ?> orang</span>
                            <?php endif; ?>
                            <?php if ($fas_cols['lokasi'] && !empty($f['lokasi'])): ?>
                                <span>📍 <?= sanitize($f['lokasi']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="fasilitas-list-desc"><?= sanitize($f['deskripsi'] ?? '') ?></p>
                    </div>
                    <div class="fasilitas-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='shareFasilitas(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                        <button class="btn-action primary" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== GALLERY VIEW ===== -->
            <?php if ($view === 'gallery'): ?>
            <div class="gallery-view" data-aos="fade-up">
                <?php foreach ($fasilitas_list as $idx => $f):
                    $icon = $f['kategori'] === 'Laboratorium' ? '🧪' : ($f['kategori'] === 'Perpustakaan' ? '📚' : ($f['kategori'] === 'Ruang Kelas' ? '🏫' : ($kategori_icons[$f['kategori']] ?? '🏢')));
                    $is_large = $idx === 0 || $idx === 3;
                ?>
                <div class="gallery-item <?= $is_large ? 'large' : '' ?>" onclick='openFasilitasModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>
                    <?php if ($fas_cols['gambar'] && !empty($f['gambar'])): ?>
                        <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>" loading="lazy">
                    <?php else: ?>
                        <span><?= $icon ?></span>
                    <?php endif; ?>
                    <div class="gallery-overlay">
                        <h4><?= sanitize($f['nama']) ?></h4>
                        <span>
                            <?php if ($fas_cols['kategori'] && !empty($f['kategori'])): ?><?= sanitize($f['kategori']) ?> <?php endif; ?>
                            <?php if ($fas_cols['kapasitas'] && !empty($f['kapasitas'])): ?>• 👥 <?= sanitize($f['kapasitas']) ?><?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🏷️ Distribusi Kategori</h3>
                    <div id="kategoriChart"></div>
                </div>
                <div class="chart-card">
                    <h3>👥 Kapasitas per Kategori</h3>
                    <div id="kapasitasChart"></div>
                </div>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>📊 Rentang Kapasitas Fasilitas</h3>
                    <div id="rangeChart"></div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="fasilitas-cta" data-aos="zoom-in">
            <div class="fasilitas-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Ingin Melihat Langsung?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Kunjungi kampus kami dan rasakan sendiri fasilitas modern yang siap mendukung perjalanan akademik Anda.
                </p>
                <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                        📅 Jadwalkan Kunjungan
                    </a>
                    <a href="https://www.google.com/maps/search/?api=1&query=FKIP+UNIMOF+Maumere" target="_blank" class="btn btn-lg" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); font-weight: 800;">
                        🗺️ Lihat di Peta
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay-ext" id="fasilitasModal" onclick="if(event.target===this)closeFasilitasModal()">
    <div class="modal-content-ext" id="fasilitasModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const fasCols = <?= json_encode($fas_cols) ?>;
const kategoriDist = <?= json_encode($kategori_dist) ?>;
const kapasitasBins = <?= json_encode($kapasitas_bins) ?>;
const kategoriIcons = <?= json_encode($kategori_icons ?? []) ?>;

// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 30 + 's';
        p.style.animationDuration = (25 + Math.random() * 20) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
        container.appendChild(p);
    }
})();

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
document.getElementById('fasilitasSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

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

// ===== HELPERS =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL =====
function openFasilitasModal(f) {
    const icons = {'Laboratorium':'🧪','Perpustakaan':'📚','Ruang Kelas':'🏫','Fasilitas Umum':'🏢','Aula':'🎭','Masjid':'🕌','Kantin':'🍽️','Parkir':'🅿️'};
    const icon = icons[f.kategori] || '🏢';

    // Parse gallery
    let gallery_html = '';
    let gallery_images = [];
    if (fasCols.gambar_gallery && f.gambar_gallery) {
        try {
            gallery_images = JSON.parse(f.gambar_gallery);
        } catch (e) {
            gallery_images = f.gambar_gallery.split(',').map(s => s.trim()).filter(s => s);
        }
    }
    if (fasCols.gambar && f.gambar) gallery_images.unshift(f.gambar);

    // Info grid
    let info_items = '';
    if (fasCols.kategori && f.kategori) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Kategori</div><div class="modal-info-value">${escapeHtml(f.kategori)}</div></div>`;
    }
    if (fasCols.kapasitas && f.kapasitas) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">👥 Kapasitas</div><div class="modal-info-value">${escapeHtml(f.kapasitas)} orang</div></div>`;
    }
    if (fasCols.luas && f.luas) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📐 Luas</div><div class="modal-info-value">${escapeHtml(f.luas)}</div></div>`;
    }
    if (fasCols.status && f.status) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">✓ Status</div><div class="modal-info-value">${escapeHtml(f.status)}</div></div>`;
    }

    // Lokasi section
    let lokasi_html = '';
    if (fasCols.lokasi && f.lokasi) {
        lokasi_html = `
            <div class="modal-section-ext">
                <h4>📍 Lokasi</h4>
                <p>${escapeHtml(f.lokasi)}</p>
                <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(f.lokasi)}" target="_blank" class="modal-btn secondary" style="margin-top:0.75rem; display:inline-flex;">🗺️ Buka di Google Maps</a>
            </div>
        `;
    }

    // Fasilitas pendukung
    let features_html = '';
    if (fasCols.fasilitas_pendukung && f.fasilitas_pendukung) {
        const features = f.fasilitas_pendukung.split('\n').map(s => s.trim()).filter(s => s);
        if (features.length > 0) {
            features_html = `
                <div class="modal-section-ext">
                    <h4>🛠️ Fasilitas Pendukung</h4>
                    <div class="modal-features-list">
                        ${features.map(feat => `<div class="modal-feature-item">${escapeHtml(feat.replace(/^[-*•]\s*/, ''))}</div>`).join('')}
                    </div>
                </div>
            `;
        }
    }

    const html = `
        <button class="modal-close-ext" onclick="closeFasilitasModal()">✕</button>
        <div class="modal-image-ext" id="mainModalImage">
            ${fasCols.gambar && f.gambar
                ? `<img src="${'<?= asset('uploads/fasilitas/') ?>' + encodeURIComponent(f.gambar.split('/').pop())}" alt="${escapeHtml(f.nama)}" id="mainImage">`
                : `<span>${icon}</span>`}
            <div class="modal-image-overlay"></div>
        </div>
        ${gallery_images.length > 1 ? `
        <div class="modal-gallery-thumbs">
            ${gallery_images.map((img, i) => `
                <div class="modal-gallery-thumb ${i === 0 ? 'active' : ''}" onclick="switchImage(this, '${'<?= asset('uploads/fasilitas/') ?>' + encodeURIComponent(img.split('/').pop())}')">
                    <img src="${'<?= asset('uploads/fasilitas/') ?>' + encodeURIComponent(img.split('/').pop())}" alt="">
                </div>
            `).join('')}
        </div>` : ''}
        <div class="modal-body-ext">
            <h2 class="modal-title-ext">${escapeHtml(f.nama)}</h2>
            <div class="modal-subtitle-ext">
                ${fasCols.kategori && f.kategori ? `<span class="fasilitas-kategori-badge" style="position:static; padding:0.25rem 0.7rem; font-size:0.72rem;">${icon} ${escapeHtml(f.kategori)}</span>` : ''}
                ${fasCols.status && f.status ? `<span>✓ ${escapeHtml(f.status)}</span>` : ''}
            </div>

            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${fasCols.deskripsi && f.deskripsi ? `
            <div class="modal-section-ext">
                <h4>📝 Deskripsi Lengkap</h4>
                <p>${escapeHtml(f.deskripsi)}</p>
            </div>` : ''}

            ${lokasi_html}
            ${features_html}
        </div>
        <div class="modal-footer-ext">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareFasilitas(${JSON.stringify(f).replace(/"/g, "&quot;")})'>🔗 Share</button>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="closeFasilitasModal()">Tutup</button>
                ${fasCols.lokasi && f.lokasi ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(f.lokasi)}" target="_blank" class="modal-btn primary">🗺️ Lihat di Peta</a>` : ''}
            </div>
        </div>
    `;
    document.getElementById('fasilitasModalContent').innerHTML = html;
    document.getElementById('fasilitasModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function switchImage(thumb, src) {
    document.querySelectorAll('.modal-gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
    document.getElementById('mainImage').src = src;
}

function closeFasilitasModal() {
    document.getElementById('fasilitasModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareFasilitas(f) {
    const text = `🏢 ${f.nama}\n${fasCols.kategori && f.kategori ? '🏷️ ' + f.kategori + '\n' : ''}${fasCols.kapasitas && f.kapasitas ? '👥 Kapasitas: ' + f.kapasitas + ' orang\n' : ''}${fasCols.lokasi && f.lokasi ? '📍 ' + f.lokasi + '\n' : ''}\nFasilitas FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: f.nama, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info fasilitas disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const katColors = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#ef4444', '#06b6d4'];

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

// Kapasitas per kategori
const kapasitasPerKat = {};
const fasData = <?= json_encode($fasilitas_list) ?>;
fasData.forEach(f => {
    if (f.kategori && f.kapasitas) {
        kapasitasPerKat[f.kategori] = (kapasitasPerKat[f.kategori] || 0) + parseInt(f.kapasitas);
    }
});
if (Object.keys(kapasitasPerKat).length > 0) {
    new ApexCharts(document.querySelector("#kapasitasChart"), {
        series: [{ name: 'Kapasitas', data: Object.values(kapasitasPerKat) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#10b981'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(kapasitasPerKat), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}

if (Object.keys(kapasitasBins).length > 0) {
    new ApexCharts(document.querySelector("#rangeChart"), {
        series: [{ name: 'Jumlah Fasilitas', data: Object.values(kapasitasBins) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        colors: ['#3b82f6'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '50%' } },
        dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(kapasitasBins), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('fasilitasSearch')?.focus();
    }
    if (e.key === 'Escape') closeFasilitasModal();
});

console.log('%c🏢 Fasilitas FKIP UNIMOF - EXTREME MULTIMATE', 'color:#10b981;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>