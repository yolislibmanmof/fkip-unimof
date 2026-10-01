<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Kerjasama & Mitra';
$page_description = 'Jaringan kerjasama FKIP UNIMOF dengan berbagai universitas, industri, dan pemerintah dalam negeri maupun internasional.';

// ===== SCHEMA-SAFE: deteksi kolom kerjasama =====
$mitra_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `kerjasama`")->fetchAll(PDO::FETCH_COLUMN);
    $mitra_cols = [
        'nama_institusi'  => in_array('nama_institusi', $cols, true),
        'logo'            => in_array('logo', $cols, true),
        'jenis'           => in_array('jenis', $cols, true),
        'bentuk_kerjasama'=> in_array('bentuk_kerjasama', $cols, true),
        'deskripsi'       => in_array('deskripsi', $cols, true),
        'negara'          => in_array('negara', $cols, true),
        'kota'            => in_array('kota', $cols, true),
        'tanggal_mulai'   => in_array('tanggal_mulai', $cols, true),
        'tanggal_selesai' => in_array('tanggal_selesai', $cols, true),
        'status'          => in_array('status', $cols, true),
        'kontak'          => in_array('kontak', $cols, true),
        'link_website'    => in_array('link_website', $cols, true),
        'nomor_mou'       => in_array('nomor_mou', $cols, true),
        'kategori'        => in_array('kategori', $cols, true),
        'created_at'      => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $mitra_cols = array_fill_keys(['nama_institusi','logo','jenis','bentuk_kerjasama','deskripsi','negara','kota','tanggal_mulai','tanggal_selesai','status','kontak','link_website','nomor_mou','kategori','created_at'], false);
}

// ===== AMBIL DATA MITRA =====
$where = $mitra_cols['status'] ? "WHERE status = 'Aktif'" : "WHERE 1=1";
$order_by = $mitra_cols['created_at'] ? "created_at DESC" : "id DESC";
$stmt = $pdo->query("SELECT * FROM kerjasama $where ORDER BY $order_by");
$mitra_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($mitra_list);
$stat_univ = $stat_industri = $stat_pemerintah = $stat_intl = 0;
$stat_aktif = 0;
$jenis_dist = [];
$negara_dist = [];
$year_dist = [];
$kategori_dist = [];

foreach ($mitra_list as $m) {
    $jenis = $m['jenis'] ?? '';
    if ($jenis === 'Universitas') $stat_univ++;
    elseif ($jenis === 'Industri') $stat_industri++;
    elseif (stripos($jenis, 'Pemerintah') !== false || $jenis === 'Pemerintah') $stat_pemerintah++;
    if ($jenis !== '') $jenis_dist[$jenis] = ($jenis_dist[$jenis] ?? 0) + 1;

    if ($mitra_cols['negara'] && !empty($m['negara'])) {
        $negara_dist[$m['negara']] = ($negara_dist[$m['negara']] ?? 0) + 1;
        if (strtolower($m['negara']) !== 'indonesia') $stat_intl++;
    }

    if ($mitra_cols['tanggal_mulai'] && !empty($m['tanggal_mulai'])) {
        $y = date('Y', strtotime($m['tanggal_mulai']));
        $year_dist[$y] = ($year_dist[$y] ?? 0) + 1;
    }

    if ($mitra_cols['kategori'] && !empty($m['kategori'])) {
        $kategori_dist[$m['kategori']] = ($kategori_dist[$m['kategori']] ?? 0) + 1;
    }

    if ($mitra_cols['status'] && ($m['status'] ?? '') === 'Aktif') $stat_aktif++;
}

arsort($negara_dist);
$top_negara = array_slice($negara_dist, 0, 8, true);
ksort($year_dist);

// ===== SPOTLIGHT (mitra terbaru / featured) =====
$spotlight = $mitra_list[0] ?? null;

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_jenis = $_GET['jenis'] ?? 'all';
$filter_negara = $_GET['negara'] ?? 'all';
$view = $_GET['view'] ?? 'grid';
$sort = $_GET['sort'] ?? 'newest';

$jenis_list = array_keys($jenis_dist);
sort($jenis_list);
$negara_list = array_keys($negara_dist);
sort($negara_list);

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.kerjasama-hero {
    position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.kerjasama-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-30px, 20px) scale(1.05); }
}
.kerjasama-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px; pointer-events: none;
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
.kerjasama-hero .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.kerjasama-hero .breadcrumb a, .kerjasama-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.kerjasama-hero .breadcrumb a:hover { color: white; }

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
.mitra-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.mitra-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.mitra-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.mitra-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.mitra-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.mitra-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.mitra-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SPOTLIGHT ===== */
.spotlight-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: auto 1fr auto;
    gap: 2rem; padding: 2rem; align-items: center; position: relative;
}
.spotlight-section::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706, #1e3a8a);
}
.spotlight-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.7rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    display: flex; align-items: center; gap: 0.3rem;
}
.spotlight-logo {
    width: 110px; height: 110px; border-radius: 24px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 3.5rem; flex-shrink: 0;
    border: 2px solid var(--border); overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.spotlight-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.75rem; }
.spotlight-info h2 {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800;
    margin-bottom: 0.5rem; color: var(--text-primary); line-height: 1.3;
}
.spotlight-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem;
    font-size: 0.85rem; color: var(--text-secondary);
}
.spotlight-meta span { display: flex; align-items: center; gap: 0.35rem; }
.spotlight-desc {
    font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.spotlight-actions { display: flex; flex-direction: column; gap: 0.5rem; }

/* ===== WORLD MAP ===== */
.world-map-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}
.world-map-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;
}
.world-map-header h3 {
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;
    display: flex; align-items: center; gap: 0.5rem;
}
.world-map-container {
    position: relative; background: linear-gradient(135deg, #0f172a, #1e293b);
    border-radius: var(--radius-lg); padding: 2rem; overflow: hidden;
    min-height: 300px;
}
.world-map-svg { width: 100%; height: auto; opacity: 0.3; }
.map-pin {
    position: absolute; width: 16px; height: 16px;
    background: #f59e0b; border: 2px solid white; border-radius: 50%;
    transform: translate(-50%, -50%); cursor: pointer;
    box-shadow: 0 0 0 0 rgba(245,158,11,0.7);
    animation: mapPulse 2s infinite; transition: all 0.3s;
}
.map-pin:hover { transform: translate(-50%, -50%) scale(1.5); z-index: 10; }
.map-pin::before {
    content: attr(data-count); position: absolute; top: -22px; left: 50%;
    transform: translateX(-50%); background: white; color: #1e293b;
    padding: 0.15rem 0.45rem; border-radius: 999px; font-size: 0.65rem;
    font-weight: 800; white-space: nowrap; opacity: 0;
    transition: opacity 0.2s; pointer-events: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.map-pin:hover::before { opacity: 1; }
.map-pin.intl { background: #3b82f6; box-shadow: 0 0 0 0 rgba(59,130,246,0.7); }
@keyframes mapPulse {
    0% { box-shadow: 0 0 0 0 rgba(245,158,11,0.7); }
    70% { box-shadow: 0 0 0 10px rgba(245,158,11,0); }
    100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
}
.map-legend {
    display: flex; gap: 1.5rem; justify-content: center; margin-top: 1.25rem;
    flex-wrap: wrap; font-size: 0.82rem; color: var(--text-secondary);
}
.map-legend-item { display: flex; align-items: center; gap: 0.4rem; }
.map-legend-dot {
    width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;
}
.map-legend-dot.domestic { background: #f59e0b; }
.map-legend-dot.intl { background: #3b82f6; }

/* ===== TOOLBAR ===== */
.mitra-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.mitra-search { flex: 1; min-width: 240px; position: relative; }
.mitra-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.mitra-search input:focus { outline: none; border-color: #4c1d95; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(76,29,149,0.1); }
.mitra-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.mitra-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.mitra-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.mitra-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.mitra-select:focus { outline: none; border-color: #4c1d95; }

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
.view-btn.active { background: linear-gradient(135deg, #4c1d95, #1e3a8a); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.export-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: var(--bg-secondary); border: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.export-btn:hover { background: #4c1d95; color: white; border-color: #4c1d95; transform: translateY(-1px); }

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
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    color: white; border-color: #1e3a8a;
    box-shadow: 0 4px 12px rgba(76,29,149,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.mitra-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;
}
.mitra-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    transition: all 0.4s; display: flex; flex-direction: column; align-items: center;
    cursor: pointer; position: relative;
}
.mitra-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.mitra-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95; }
.mitra-card:hover::before { transform: scaleX(1); }

.mitra-logo-box {
    width: 100px; height: 100px; background: var(--bg-secondary);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 3rem; margin-bottom: 1.25rem; overflow: hidden;
    border: 2px solid var(--border); transition: all 0.3s;
}
.mitra-card:hover .mitra-logo-box { transform: scale(1.05); border-color: #4c1d95; }
.mitra-logo-box img { width: 100%; height: 100%; object-fit: contain; padding: 0.75rem; }

.mitra-title {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3;
}
.mitra-jenis {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.3rem 0.85rem; background: rgba(76,29,149,0.1);
    color: #4c1d95; border-radius: 999px; font-size: 0.72rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.85rem;
}
.mitra-desc {
    color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.mitra-meta {
    display: flex; gap: 0.75rem; font-size: 0.82rem; color: var(--text-muted);
    flex-wrap: wrap; justify-content: center; margin-bottom: 1rem;
    padding-top: 1rem; border-top: 1px solid var(--border); width: 100%;
}
.mitra-meta span { display: flex; align-items: center; gap: 0.3rem; }
.mitra-card-actions {
    display: flex; gap: 0.4rem; width: 100%; justify-content: center;
}
.btn-action {
    padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.78rem;
    font-weight: 600; border: 1px solid var(--border); background: var(--bg-secondary);
    color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none;
    font-family: inherit;
}
.btn-action:hover { background: #4c1d95; color: white; border-color: #4c1d95; transform: translateY(-1px); }
.btn-action.primary {
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    color: white; border-color: #1e3a8a;
}
.btn-action.primary:hover { filter: brightness(1.1); }

.intl-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: linear-gradient(135deg, #3b82f6, #1e40af); color: white;
    padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.68rem;
    font-weight: 700; display: flex; align-items: center; gap: 0.25rem;
}

/* ===== LIST VIEW ===== */
.mitra-list { display: flex; flex-direction: column; gap: 1rem; }
.mitra-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.mitra-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #4c1d95; }
.mitra-list-logo {
    width: 64px; height: 64px; border-radius: 14px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 2rem; flex-shrink: 0;
    border: 1px solid var(--border); overflow: hidden;
}
.mitra-list-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; }
.mitra-list-content h3 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.3rem;
    color: var(--text-primary);
}
.mitra-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary); margin-bottom: 0.35rem;
}
.mitra-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.mitra-list-desc {
    font-size: 0.82rem; color: var(--text-muted);
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.mitra-list-actions { display: flex; gap: 0.4rem; }

/* ===== TIMELINE VIEW ===== */
.timeline-view { max-width: 900px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-view::before {
    content: ''; position: absolute; left: 24px; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #4c1d95, #1e3a8a, transparent);
    border-radius: 4px;
}
.timeline-year-group { margin-bottom: 2.5rem; }
.timeline-year-label {
    position: sticky; top: 80px; z-index: 5; background: var(--bg-primary);
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;
    padding: 0.65rem 0.75rem 0.65rem 3.5rem; border-radius: var(--radius-md);
    border: 1px solid var(--border); box-shadow: var(--shadow-sm);
}
.timeline-year-label::before {
    content: ''; position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; border-radius: 50%;
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    border: 3px solid var(--bg-primary); box-shadow: 0 0 0 2px #1e3a8a;
}
.timeline-year-label h3 {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    color: var(--text-primary); margin: 0;
}
.timeline-year-label .year-count {
    background: linear-gradient(135deg, #4c1d95, #1e3a8a); color: white;
    padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
}
.timeline-items { display: flex; flex-direction: column; gap: 1rem; padding-left: 3.5rem; }
.timeline-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 1rem 1.25rem; transition: all 0.3s;
    cursor: pointer; position: relative; display: flex; gap: 1rem; align-items: center;
}
.timeline-item::before {
    content: ''; position: absolute; left: -2.35rem; top: 1.25rem;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--bg-primary); border: 3px solid #4c1d95;
}
.timeline-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #4c1d95; }
.timeline-item-logo {
    width: 48px; height: 48px; border-radius: 12px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 1.5rem; flex-shrink: 0;
    border: 1px solid var(--border); overflow: hidden;
}
.timeline-item-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.35rem; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-info h4 {
    font-size: 0.95rem; font-weight: 700; margin-bottom: 0.25rem;
    color: var(--text-primary); line-height: 1.3;
}
.timeline-item-info span { font-size: 0.78rem; color: var(--text-muted); }

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
.mitra-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #4c1d95 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.mitra-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.mitra-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

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
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-mitra {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 760px; max-height: 90vh; overflow-y: auto;
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
.modal-header-mitra {
    padding: 2.5rem 2rem; text-align: center; color: white; position: relative;
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-mitra::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-logo {
    width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 1.25rem;
    background: white; display: flex; align-items: center; justify-content: center;
    font-size: 3.5rem; position: relative; overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 4px solid white;
}
.modal-logo img { width: 100%; height: 100%; object-fit: contain; padding: 1rem; }
.modal-title {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.9rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-mitra { padding: 2rem; }
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
.modal-info-value a { color: #4c1d95; text-decoration: none; }
.modal-info-value a:hover { text-decoration: underline; }

.modal-section { margin-bottom: 1.5rem; }
.modal-section h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }

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
.modal-btn.primary { background: linear-gradient(135deg, #4c1d95, #1e3a8a); color: white; }
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
    .spotlight-section { grid-template-columns: 1fr; text-align: center; }
    .spotlight-logo { margin: 0 auto; }
    .spotlight-actions { flex-direction: row; justify-content: center; }
}
@media (max-width: 768px) {
    .kerjasama-hero { padding: 8rem 0 5rem; }
    .mitra-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .mitra-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .mitra-grid { grid-template-columns: 1fr; }
    .mitra-list-item { grid-template-columns: 1fr; text-align: center; }
    .mitra-list-logo { margin: 0 auto; }
    .mitra-list-actions { justify-content: center; }
    .modal-info-grid { grid-template-columns: 1fr; }
    .timeline-view::before { left: 18px; }
    .timeline-items { padding-left: 2.75rem; }
    .timeline-item::before { left: -2.05rem; }
    .timeline-year-label { padding-left: 2.75rem; }
    .timeline-year-label::before { left: 12px; }
}
@media (max-width: 480px) {
    .mitra-stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="kerjasama-hero">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Kerjasama</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>🌐 Global Partnership Network</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">
            Jaringan
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Kerjasama Global</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            FKIP UNIMOF berkomitmen membangun ekosistem pendidikan yang kolaboratif melalui kemitraan strategis dengan universitas, industri, dan pemerintah.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">🤝 <?= $stat_total ?> Mitra Aktif</span>
            <span class="trust-pill">🌍 <?= $stat_intl ?> Internasional</span>
            <span class="trust-pill">🎓 <?= $stat_univ ?> Universitas</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="mitra-stats-bar" data-aos="fade-up">
            <div class="mitra-stat-card" style="--stat-color: #3b82f6;">
                <div class="mitra-stat-icon">🤝</div>
                <div class="mitra-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="mitra-stat-label">Total Mitra</div>
            </div>
            <div class="mitra-stat-card" style="--stat-color: #10b981;">
                <div class="mitra-stat-icon">🎓</div>
                <div class="mitra-stat-num count-up" data-target="<?= $stat_univ ?>">0</div>
                <div class="mitra-stat-label">Universitas</div>
            </div>
            <div class="mitra-stat-card" style="--stat-color: #f59e0b;">
                <div class="mitra-stat-icon">🏭</div>
                <div class="mitra-stat-num count-up" data-target="<?= $stat_industri ?>">0</div>
                <div class="mitra-stat-label">Industri</div>
            </div>
            <div class="mitra-stat-card" style="--stat-color: #8b5cf6;">
                <div class="mitra-stat-icon">🏛️</div>
                <div class="mitra-stat-num count-up" data-target="<?= $stat_pemerintah ?>">0</div>
                <div class="mitra-stat-label">Pemerintah</div>
            </div>
            <div class="mitra-stat-card" style="--stat-color: #ec4899;">
                <div class="mitra-stat-icon">🌍</div>
                <div class="mitra-stat-num count-up" data-target="<?= $stat_intl ?>">0</div>
                <div class="mitra-stat-label">Internasional</div>
            </div>
        </div>

        <!-- Spotlight -->
        <?php if ($spotlight):
            $icon = $spotlight['jenis'] === 'Universitas' ? '🎓' : ($spotlight['jenis'] === 'Industri' ? '🏭' : ($spotlight['jenis'] === 'Pemerintah' ? '🏛️' : '🤝'));
            $is_intl = $mitra_cols['negara'] && !empty($spotlight['negara']) && strtolower($spotlight['negara']) !== 'indonesia';
        ?>
        <div class="spotlight-section" data-aos="fade-up" onclick='openMitraModal(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>
            <span class="spotlight-badge">⭐ Mitra Terbaru</span>
            <div class="spotlight-logo">
                <?php if ($mitra_cols['logo'] && !empty($spotlight['logo'])): ?>
                    <img src="<?= asset('uploads/kerjasama/' . basename($spotlight['logo'])) ?>" alt="<?= sanitize($spotlight['nama_institusi']) ?>">
                <?php else: ?>
                    <span><?= $icon ?></span>
                <?php endif; ?>
            </div>
            <div class="spotlight-info">
                <h2><?= sanitize($spotlight['nama_institusi']) ?></h2>
                <div class="spotlight-meta">
                    <span class="mitra-jenis" style="margin:0;"><?= sanitize($spotlight['jenis'] ?? 'Mitra') ?></span>
                    <?php if ($mitra_cols['negara'] && !empty($spotlight['negara'])): ?>
                        <span>🌍 <?= sanitize($spotlight['negara']) ?><?= $is_intl ? ' 🌐' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($mitra_cols['tanggal_mulai'] && !empty($spotlight['tanggal_mulai'])): ?>
                        <span>📅 Sejak <?= date('Y', strtotime($spotlight['tanggal_mulai'])) ?></span>
                    <?php endif; ?>
                </div>
                <p class="spotlight-desc"><?= sanitize(excerpt($spotlight['bentuk_kerjasama'] ?? $spotlight['deskripsi'] ?? 'Kerjasama strategis untuk pengembangan pendidikan dan penelitian.', 180)) ?></p>
            </div>
            <div class="spotlight-actions" onclick="event.stopPropagation();">
                <button class="btn-action primary" onclick='openMitraModal(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                <button class="btn-action" onclick='shareMitra(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($mitra_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🤝</div>
                <h3>Belum ada data kerjasama</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Informasi mitra akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>

            <!-- World Map -->
            <?php if (!empty($negara_dist)): ?>
            <div class="world-map-section" data-aos="fade-up">
                <div class="world-map-header">
                    <h3>🗺️ Jangkauan Kerjasama</h3>
                    <div style="font-size:0.85rem; color:var(--text-muted);">
                        Tersebar di <strong><?= count($negara_dist) ?> negara</strong>
                    </div>
                </div>
                <div class="world-map-container" id="worldMapContainer">
                    <!-- Simple World Map SVG (simplified) -->
                    <svg class="world-map-svg" viewBox="0 0 1000 500" xmlns="http://www.w3.org/2000/svg">
                        <!-- North America -->
                        <path d="M150,120 L280,100 L300,180 L250,220 L180,200 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- South America -->
                        <path d="M230,280 L290,260 L310,380 L260,420 L220,380 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- Europe -->
                        <path d="M450,120 L560,110 L580,170 L520,190 L460,170 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- Africa -->
                        <path d="M470,220 L560,210 L580,350 L510,390 L470,340 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- Asia -->
                        <path d="M600,100 L800,90 L820,230 L720,260 L610,220 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- Indonesia/SE Asia -->
                        <path d="M750,280 L820,275 L830,310 L770,315 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                        <!-- Australia -->
                        <path d="M780,360 L870,355 L880,420 L800,425 Z" fill="#475569" stroke="#1e293b" stroke-width="1"/>
                    </svg>
                    <?php
                    // Approximate country positions on map (x%, y%)
                    $country_positions = [
                        'indonesia' => ['x' => 80, 'y' => 60],
                        'malaysia' => ['x' => 77, 'y' => 55],
                        'singapura' => ['x' => 78, 'y' => 57],
                        'thailand' => ['x' => 74, 'y' => 50],
                        'vietnam' => ['x' => 76, 'y' => 48],
                        'filipina' => ['x' => 82, 'y' => 52],
                        'jepang' => ['x' => 86, 'y' => 38],
                        'korea' => ['x' => 83, 'y' => 38],
                        'china' => ['x' => 75, 'y' => 35],
                        'india' => ['x' => 68, 'y' => 45],
                        'australia' => ['x' => 84, 'y' => 78],
                        'amerika serikat' => ['x' => 22, 'y' => 30],
                        'kanada' => ['x' => 22, 'y' => 22],
                        'mexico' => ['x' => 20, 'y' => 40],
                        'brazil' => ['x' => 30, 'y' => 65],
                        'argentina' => ['x' => 28, 'y' => 78],
                        'inggris' => ['x' => 48, 'y' => 26],
                        'perancis' => ['x' => 50, 'y' => 28],
                        'jerman' => ['x' => 52, 'y' => 27],
                        'belanda' => ['x' => 51, 'y' => 25],
                        'italia' => ['x' => 53, 'y' => 31],
                        'spanyol' => ['x' => 48, 'y' => 32],
                        'turki' => ['x' => 57, 'y' => 33],
                        'arab saudi' => ['x' => 60, 'y' => 42],
                        'mesir' => ['x' => 55, 'y' => 44],
                        'afrika selatan' => ['x' => 55, 'y' => 72],
                        'rusia' => ['x' => 70, 'y' => 18],
                    ];
                    foreach ($negara_dist as $negara => $count):
                        $key = strtolower($negara);
                        $pos = $country_positions[$key] ?? null;
                        if (!$pos) continue;
                        $is_intl_country = $key !== 'indonesia';
                    ?>
                    <div class="map-pin <?= $is_intl_country ? 'intl' : '' ?>"
                         style="left: <?= $pos['x'] ?>%; top: <?= $pos['y'] ?>%;"
                         data-count="<?= sanitize($negara) ?>: <?= $count ?>"
                         title="<?= sanitize($negara) ?> (<?= $count ?> mitra)"></div>
                    <?php endforeach; ?>
                </div>
                <div class="map-legend">
                    <div class="map-legend-item"><span class="map-legend-dot domestic"></span>Domestik</div>
                    <div class="map-legend-item"><span class="map-legend-dot intl"></span>Internasional</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="mitra-toolbar" data-aos="fade-up">
                <div class="mitra-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="mitraSearch" placeholder="Cari nama institusi, negara, atau jenis..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="kerjasama.php?jenis=<?= urlencode($filter_jenis) ?>&negara=<?= urlencode($filter_negara) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>" class="s-clear" title="Clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="mitra-select" id="negaraSelect">
                    <option value="all">🌍 Semua Negara</option>
                    <?php foreach ($negara_list as $n): ?>
                        <option value="<?= urlencode($n) ?>" <?= $filter_negara === $n ? 'selected' : '' ?>>
                            <?= sanitize($n) ?> (<?= $negara_dist[$n] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="mitra-select" id="sortSelect">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>🕐 Terlama</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Timeline</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📈 Chart</button>
                </div>
                <a href="#" onclick="exportMitra(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_jenis === 'all' ? 'active' : '' ?>" href="kerjasama.php?jenis=all&negara=<?= urlencode($filter_negara) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>">
                    🌐 Semua <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php
                $jenis_icons = ['Universitas'=>'🎓','Industri'=>'🏭','Pemerintah'=>'🏛️','NGO'=>'🤝','Lainnya'=>'🏢'];
                foreach ($jenis_list as $j):
                    $icon = $jenis_icons[$j] ?? '🤝';
                ?>
                <a class="filter-pill <?= $filter_jenis === $j ? 'active' : '' ?>" href="kerjasama.php?jenis=<?= urlencode($j) ?>&negara=<?= urlencode($filter_negara) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>">
                    <?= $icon ?> <?= sanitize($j) ?> <span class="pill-count"><?= $jenis_dist[$j] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="mitra-grid" data-aos="fade-up">
                <?php foreach ($mitra_list as $m):
                    $icon = $m['jenis'] === 'Universitas' ? '🎓' : ($m['jenis'] === 'Industri' ? '🏭' : ($m['jenis'] === 'Pemerintah' ? '🏛️' : '🤝'));
                    $is_intl = $mitra_cols['negara'] && !empty($m['negara']) && strtolower($m['negara']) !== 'indonesia';
                ?>
                <div class="mitra-card" onclick='openMitraModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>
                    <?php if ($is_intl): ?>
                        <span class="intl-badge">🌐 Intl</span>
                    <?php endif; ?>
                    <div class="mitra-logo-box">
                        <?php if ($mitra_cols['logo'] && !empty($m['logo'])): ?>
                            <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="<?= sanitize($m['nama_institusi']) ?>">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="mitra-title"><?= sanitize($m['nama_institusi']) ?></h3>
                    <span class="mitra-jenis"><?= $icon ?> <?= sanitize($m['jenis'] ?? 'Mitra') ?></span>
                    <p class="mitra-desc"><?= sanitize(excerpt($m['bentuk_kerjasama'] ?? $m['deskripsi'] ?? 'Kerjasama strategis', 120)) ?></p>
                    <div class="mitra-meta">
                        <?php if ($mitra_cols['negara'] && !empty($m['negara'])): ?>
                            <span>🌍 <?= sanitize($m['negara']) ?></span>
                        <?php endif; ?>
                        <?php if ($mitra_cols['tanggal_mulai'] && !empty($m['tanggal_mulai'])): ?>
                            <span>📅 Sejak <?= date('Y', strtotime($m['tanggal_mulai'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mitra-card-actions" onclick="event.stopPropagation();">
                        <button class="btn-action primary" onclick='openMitraModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                        <button class="btn-action" onclick='shareMitra(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                        <?php if ($mitra_cols['link_website'] && !empty($m['link_website'])): ?>
                            <a href="<?= sanitize($m['link_website']) ?>" target="_blank" class="btn-action" onclick="event.stopPropagation();">🌐 Web</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="mitra-list" data-aos="fade-up">
                <?php foreach ($mitra_list as $m):
                    $icon = $m['jenis'] === 'Universitas' ? '🎓' : ($m['jenis'] === 'Industri' ? '🏭' : ($m['jenis'] === 'Pemerintah' ? '🏛️' : '🤝'));
                ?>
                <div class="mitra-list-item" onclick='openMitraModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="mitra-list-logo">
                        <?php if ($mitra_cols['logo'] && !empty($m['logo'])): ?>
                            <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mitra-list-content">
                        <h3><?= sanitize($m['nama_institusi']) ?></h3>
                        <div class="mitra-list-meta">
                            <span class="mitra-jenis" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;"><?= sanitize($m['jenis'] ?? 'Mitra') ?></span>
                            <?php if ($mitra_cols['negara'] && !empty($m['negara'])): ?>
                                <span>🌍 <?= sanitize($m['negara']) ?></span>
                            <?php endif; ?>
                            <?php if ($mitra_cols['tanggal_mulai'] && !empty($m['tanggal_mulai'])): ?>
                                <span>📅 <?= date('d M Y', strtotime($m['tanggal_mulai'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="mitra-list-desc"><?= sanitize($m['bentuk_kerjasama'] ?? '') ?></p>
                    </div>
                    <div class="mitra-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='shareMitra(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <button class="btn-action primary" onclick='openMitraModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>👁️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== TIMELINE VIEW ===== -->
            <?php if ($view === 'timeline'): ?>
            <div class="timeline-view" data-aos="fade-up">
                <?php
                $grouped = [];
                foreach ($mitra_list as $m) {
                    if ($mitra_cols['tanggal_mulai'] && !empty($m['tanggal_mulai'])) {
                        $y = date('Y', strtotime($m['tanggal_mulai']));
                    } else {
                        $y = 'Unknown';
                    }
                    $grouped[$y][] = $m;
                }
                krsort($grouped);
                foreach ($grouped as $year => $items):
                ?>
                <div class="timeline-year-group">
                    <div class="timeline-year-label">
                        <h3>📅 <?= $year ?></h3>
                        <span class="year-count"><?= count($items) ?> mitra</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $m):
                            $icon = $m['jenis'] === 'Universitas' ? '🎓' : ($m['jenis'] === 'Industri' ? '🏭' : ($m['jenis'] === 'Pemerintah' ? '🏛️' : '🤝'));
                        ?>
                        <div class="timeline-item" onclick='openMitraModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, "UTF-8") ?>)'>
                            <div class="timeline-item-logo">
                                <?php if ($mitra_cols['logo'] && !empty($m['logo'])): ?>
                                    <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="">
                                <?php else: ?>
                                    <span><?= $icon ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-item-info">
                                <h4><?= sanitize($m['nama_institusi']) ?></h4>
                                <span>
                                    <?= sanitize($m['jenis'] ?? '-') ?>
                                    <?php if ($mitra_cols['negara'] && !empty($m['negara'])): ?> • 🌍 <?= sanitize($m['negara']) ?><?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🏷️ Distribusi Jenis Mitra</h3>
                    <div id="jenisChart"></div>
                </div>
                <div class="chart-card">
                    <h3>🌍 Top Negara</h3>
                    <div id="negaraChart"></div>
                </div>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>📈 Tren Kerjasama per Tahun</h3>
                    <div id="yearChart"></div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="mitra-cta" data-aos="zoom-in">
            <div class="mitra-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Tertarik Menjadi Mitra Kami?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Kami membuka peluang kerjasama dengan institusi pendidikan, perusahaan, dan organisasi yang memiliki visi sama dalam memajukan pendidikan.
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    🤝 Ajukan Kerjasama
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="mitraModal" onclick="if(event.target===this)closeMitraModal()">
    <div class="modal-content-mitra" id="mitraModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const mitraCols = <?= json_encode($mitra_cols) ?>;
const jenisDist = <?= json_encode($jenis_dist) ?>;
const negaraDist = <?= json_encode($negara_dist) ?>;
const yearDist = <?= json_encode($year_dist) ?>;

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

// ===== SEARCH & FILTERS =====
let searchTimer;
document.getElementById('mitraSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

document.getElementById('negaraSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('negara', this.value);
    window.location = url;
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

// ===== EXPORT CSV =====
function exportMitra() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($mitra_list) ?>;
    const headers = ['Nama Institusi','Jenis','Negara','Bentuk Kerjasama','Tanggal Mulai','Website'];
    const rows = data.map(d => [
        d.nama_institusi || '', d.jenis || '', d.negara || '',
        d.bentuk_kerjasama || '', d.tanggal_mulai || '', d.link_website || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'mitra-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
    pubToast('File CSV berhasil diunduh', '📥');
}

// ===== HELPERS =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL =====
function openMitraModal(m) {
    const icon = m.jenis === 'Universitas' ? '🎓' : (m.jenis === 'Industri' ? '🏭' : (m.jenis === 'Pemerintah' ? '🏛️' : '🤝'));
    const isIntl = mitraCols.negara && m.negara && m.negara.toLowerCase() !== 'indonesia';

    // Info grid
    let info_items = '';
    if (mitraCols.jenis && m.jenis) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Jenis Mitra</div><div class="modal-info-value">${escapeHtml(m.jenis)}</div></div>`;
    }
    if (mitraCols.negara && m.negara) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🌍 Negara</div><div class="modal-info-value">${escapeHtml(m.negara)} ${isIntl ? '🌐' : ''}</div></div>`;
    }
    if (mitraCols.kota && m.kota) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏙️ Kota</div><div class="modal-info-value">${escapeHtml(m.kota)}</div></div>`;
    }
    if (mitraCols.tanggal_mulai && m.tanggal_mulai) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📅 Mulai</div><div class="modal-info-value">${new Date(m.tanggal_mulai).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</div></div>`;
    }
    if (mitraCols.tanggal_selesai && m.tanggal_selesai) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📅 Selesai</div><div class="modal-info-value">${new Date(m.tanggal_selesai).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</div></div>`;
    }
    if (mitraCols.status && m.status) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">✓ Status</div><div class="modal-info-value">${escapeHtml(m.status)}</div></div>`;
    }
    if (mitraCols.nomor_mou && m.nomor_mou) {
        info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📄 Nomor MoU</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(m.nomor_mou)}</div></div>`;
    }
    if (mitraCols.kontak && m.kontak) {
        info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📞 Kontak</div><div class="modal-info-value">${escapeHtml(m.kontak)}</div></div>`;
    }
    if (mitraCols.link_website && m.link_website) {
        info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🌐 Website</div><div class="modal-info-value"><a href="${escapeHtml(m.link_website)}" target="_blank">${escapeHtml(m.link_website)}</a></div></div>`;
    }

    const html = `
        <button class="modal-close" onclick="closeMitraModal()">✕</button>
        <div class="modal-header-mitra">
            <div class="modal-header-content">
                <div class="modal-logo">
                    ${mitraCols.logo && m.logo ? `<img src="${'<?= asset('uploads/kerjasama/') ?>' + encodeURIComponent(m.logo.split('/').pop())}" alt="${escapeHtml(m.nama_institusi)}">` : icon}
                </div>
                <h2 class="modal-title">${escapeHtml(m.nama_institusi)}</h2>
                <div class="modal-subtitle">${mitraCols.jenis && m.jenis ? escapeHtml(m.jenis) : 'Mitra Kerjasama'} ${mitraCols.negara && m.negara ? ' • ' + escapeHtml(m.negara) : ''}</div>
                <div class="modal-meta-ext">
                    ${mitraCols.status && m.status ? `<span>✓ ${escapeHtml(m.status)}</span>` : ''}
                    ${mitraCols.tanggal_mulai && m.tanggal_mulai ? `<span>📅 Sejak ${new Date(m.tanggal_mulai).getFullYear()}</span>` : ''}
                    ${isIntl ? `<span>🌐 Internasional</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-mitra">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${mitraCols.bentuk_kerjasama && m.bentuk_kerjasama ? `
            <div class="modal-section">
                <h4>🤝 Bentuk Kerjasama</h4>
                <p>${escapeHtml(m.bentuk_kerjasama)}</p>
            </div>` : ''}

            ${mitraCols.deskripsi && m.deskripsi ? `
            <div class="modal-section">
                <h4>📝 Deskripsi</h4>
                <p>${escapeHtml(m.deskripsi)}</p>
            </div>` : ''}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareMitra(${JSON.stringify(m).replace(/"/g, "&quot;")})'>🔗 Share</button>
                ${mitraCols.link_website && m.link_website ? `<a href="${escapeHtml(m.link_website)}" target="_blank" class="modal-btn secondary">🌐 Website</a>` : ''}
                ${mitraCols.kontak && m.kontak && m.kontak.includes('@') ? `<a href="mailto:${escapeHtml(m.kontak)}" class="modal-btn secondary">✉️ Email</a>` : ''}
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn primary" onclick="closeMitraModal()">Tutup</button>
            </div>
        </div>
    `;
    document.getElementById('mitraModalContent').innerHTML = html;
    document.getElementById('mitraModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeMitraModal() {
    document.getElementById('mitraModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareMitra(m) {
    const text = `🤝 ${m.nama_institusi}\n${m.jenis ? '🏷️ ' + m.jenis + '\n' : ''}${m.negara ? '🌍 ' + m.negara + '\n' : ''}${m.bentuk_kerjasama ? '📝 ' + m.bentuk_kerjasama.substring(0, 100) + '\n' : ''}\nJaringan Kerjasama FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: m.nama_institusi, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info mitra disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const jenisColors = ['#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#3b82f6', '#06b6d4'];
const negaraColors = ['#4c1d95', '#1e3a8a', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'];

if (Object.keys(jenisDist).length > 0) {
    new ApexCharts(document.querySelector("#jenisChart"), {
        series: Object.values(jenisDist),
        labels: Object.keys(jenisDist),
        chart: { type: 'donut', height: 320 },
        colors: jenisColors.slice(0, Object.keys(jenisDist).length),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(jenisDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

const topNegara = Object.entries(negaraDist).sort((a,b) => b[1]-a[1]).slice(0, 8);
if (topNegara.length > 0) {
    new ApexCharts(document.querySelector("#negaraChart"), {
        series: [{ data: topNegara.map(n => n[1]) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: negaraColors.slice(0, topNegara.length),
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: topNegara.map(n => n[0]), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}

if (Object.keys(yearDist).length > 0) {
    new ApexCharts(document.querySelector("#yearChart"), {
        series: [{ name: 'Mitra Baru', data: Object.values(yearDist) }],
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        colors: ['#4c1d95'],
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        xaxis: { categories: Object.keys(yearDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { title: { text: 'Mitra Baru', style: { fontSize: '11px' } }, min: 0 },
        grid: { borderColor: '#f1f5f9' },
        tooltip: { y: { formatter: (val) => val + ' mitra' } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('mitraSearch')?.focus();
    }
    if (e.key === 'Escape') closeMitraModal();
});

console.log('%c🤝 Kerjasama FKIP UNIMOF - EXTREME MULTIMATE', 'color:#4c1d95;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>