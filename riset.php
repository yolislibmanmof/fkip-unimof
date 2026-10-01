<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Riset & Publikasi';
$page_description = 'Penelitian dan publikasi ilmiah dosen serta mahasiswa FKIP UNIMOF di berbagai bidang pendidikan dan sains.';

// ===== SCHEMA-SAFE: deteksi kolom riset =====
$riset_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `riset`")->fetchAll(PDO::FETCH_COLUMN);
    $riset_cols = [
        'judul'            => in_array('judul', $cols, true),
        'abstrak'          => in_array('abstrak', $cols, true),
        'deskripsi'        => in_array('deskripsi', $cols, true),
        'jenis'            => in_array('jenis', $cols, true),
        'kategori'         => in_array('kategori', $cols, true),
        'tahun'            => in_array('tahun', $cols, true),
        'status'           => in_array('status', $cols, true),
        'ketua_id'         => in_array('ketua_id', $cols, true),
        'anggota'          => in_array('anggota', $cols, true),
        'program_studi_id' => in_array('program_studi_id', $cols, true),
        'doi'              => in_array('doi', $cols, true),
        'jurnal'           => in_array('jurnal', $cols, true),
        'link'             => in_array('link', $cols, true),
        'keywords'         => in_array('keywords', $cols, true),
        'created_at'       => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $riset_cols = array_fill_keys(['judul','abstrak','deskripsi','jenis','kategori','tahun','status','ketua_id','anggota','program_studi_id','doi','jurnal','link','keywords','created_at'], false);
}

// ===== AMBIL DATA RISET =====
$join = "";
$select = "r.*";
if ($riset_cols['program_studi_id']) {
    $join .= " LEFT JOIN program_studi p ON r.program_studi_id = p.id";
    $select .= ", p.nama as prodi_nama, p.singkatan as prodi_singkatan";
}
if ($riset_cols['ketua_id']) {
    $join .= " LEFT JOIN dosen d ON r.ketua_id = d.id";
    $select .= ", d.nama as ketua_nama";
}

$where = $riset_cols['status'] ? "WHERE r.status = 'Published'" : "WHERE 1=1";
$order_by = ($riset_cols['tahun'] ? "r.tahun DESC, " : "") . ($riset_cols['created_at'] ? "r.created_at DESC" : "r.id DESC");

$stmt = $pdo->query("SELECT $select FROM riset r $join $where ORDER BY $order_by");
$riset_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($riset_list);
$stat_publikasi = $stat_hibah = $stat_pengabdian = 0;
$jenis_dist = [];
$kategori_dist = [];
$year_dist = [];
$prodi_dist = [];
$researcher_dist = [];

foreach ($riset_list as $r) {
    $jenis = $r['jenis'] ?? '';
    if ($jenis === 'Publikasi') $stat_publikasi++;
    elseif ($jenis === 'Hibah') $stat_hibah++;
    elseif ($jenis === 'Pengabdian') $stat_pengabdian++;
    if ($jenis !== '') $jenis_dist[$jenis] = ($jenis_dist[$jenis] ?? 0) + 1;

    if ($riset_cols['kategori'] && !empty($r['kategori'])) {
        $kategori_dist[$r['kategori']] = ($kategori_dist[$r['kategori']] ?? 0) + 1;
    }
    if ($riset_cols['tahun'] && !empty($r['tahun'])) {
        $year_dist[$r['tahun']] = ($year_dist[$r['tahun']] ?? 0) + 1;
    }
    if ($riset_cols['program_studi_id'] && !empty($r['prodi_nama'] ?? '')) {
        $prodi_dist[$r['prodi_nama']] = ($prodi_dist[$r['prodi_nama']] ?? 0) + 1;
    }
    if ($riset_cols['ketua_id'] && !empty($r['ketua_nama'] ?? '')) {
        $researcher_dist[$r['ketua_nama']] = ($researcher_dist[$r['ketua_nama']] ?? 0) + 1;
    }
}

// Sort researchers by count
arsort($researcher_dist);
$top_researchers = array_slice($researcher_dist, 0, 5, true);

// Sort years
$years = array_keys($year_dist);
rsort($years);

// Featured (dengan abstrak)
$featured = array_filter($riset_list, fn($r) => !empty($r['abstrak']));
$featured = array_slice($featured, 0, 2);

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_jenis = $_GET['jenis'] ?? 'all';
$filter_kategori = $_GET['kategori'] ?? 'all';
$filter_tahun = $_GET['tahun'] ?? 'all';
$view = $_GET['view'] ?? 'grid';
$sort = $_GET['sort'] ?? 'newest';

$kategoris = array_keys($kategori_dist);
sort($kategoris);

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.riset-hero-extreme {
    position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.riset-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(139,92,246,0.2) 0%, transparent 60%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.riset-hero-extreme::after {
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
.riset-hero-extreme .container { position: relative; z-index: 2; max-width: 900px; margin: 0 auto; }
.riset-hero-extreme .breadcrumb a, .riset-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.riset-hero-extreme .breadcrumb a:hover { color: white; }

.hero-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.25rem;
    border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;
}
.hero-badge-pulse { width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative; }
.hero-badge-pulse::after {
    content: ''; position: absolute; inset: 0; background: #10b981; border-radius: 50%;
    animation: badgePulse 2s infinite;
}
@keyframes badgePulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(3); opacity: 0; } }

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
.riset-stats-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem 0 3rem; position: relative; z-index: 10;
}
.riset-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.riset-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.riset-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.riset-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.riset-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.riset-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== FEATURED ===== */
.featured-section { margin-bottom: 4rem; }
.featured-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; }
.featured-card {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer;
}
.featured-card::before {
    content: '⭐ FEATURED'; position: absolute; top: 1.5rem; right: -2.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.3rem 3rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg); letter-spacing: 0.1em;
}
.featured-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95; }
.featured-type-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.type-publikasi-ext { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.type-hibah-ext { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.type-pengabdian-ext { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.type-lainnya-ext { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }
.featured-year {
    font-family: var(--font-display); font-size: 0.88rem; font-weight: 700;
    color: var(--text-muted); margin-bottom: 0.75rem;
}
.featured-title {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 1rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.featured-desc {
    color: var(--text-secondary); font-size: 0.92rem; line-height: 1.7;
    margin-bottom: 1.5rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
}
.featured-meta {
    display: flex; flex-wrap: wrap; gap: 0.75rem; padding-top: 1.5rem;
    border-top: 1px solid var(--border); font-size: 0.82rem; color: var(--text-muted);
    margin-bottom: 1.25rem;
}
.featured-meta span { display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-action {
    padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.82rem;
    font-weight: 600; border: 1px solid var(--border); background: var(--bg-secondary);
    color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    font-family: inherit;
}
.btn-action:hover { background: #4c1d95; color: white; border-color: #4c1d95; transform: translateY(-1px); }
.btn-action.primary {
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    color: white; border-color: #1e3a8a;
}
.btn-action.primary:hover { filter: brightness(1.1); }

/* ===== CHART ===== */
.chart-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}
.chart-section h3 {
    font-family: var(--font-display); font-size: 1.1rem; margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 0.5rem;
}

/* ===== TOP RESEARCHERS ===== */
.top-researchers {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}
.top-researchers h3 {
    font-size: 1.1rem; font-weight: 800; margin-bottom: 1.25rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
    display: flex; align-items: center; gap: 0.5rem; font-family: var(--font-display);
}
.researcher-list { display: flex; flex-direction: column; gap: 0.75rem; }
.researcher-item {
    display: flex; gap: 0.75rem; padding: 0.75rem;
    background: var(--bg-secondary); border-radius: var(--radius-md);
    transition: all 0.2s; align-items: center;
}
.researcher-item:hover { background: var(--bg-tertiary); transform: translateX(3px); }
.researcher-rank {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #4c1d95, #1e3a8a); color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
    font-family: var(--font-display);
}
.researcher-rank.top-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.researcher-rank.top-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.researcher-rank.top-3 { background: linear-gradient(135deg, #d97706, #92400e); }
.researcher-info { flex: 1; min-width: 0; }
.researcher-info h5 {
    font-size: 0.88rem; font-weight: 600; margin-bottom: 0.2rem;
    color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.researcher-info span { font-size: 0.72rem; color: var(--text-muted); }
.researcher-count {
    font-size: 0.88rem; font-weight: 800; color: #4c1d95;
    font-family: var(--font-display);
}

/* ===== TOOLBAR ===== */
.riset-toolbar-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.riset-search-extreme { flex: 1; min-width: 240px; position: relative; }
.riset-search-extreme input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.riset-search-extreme input:focus { outline: none; border-color: #4c1d95; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(76,29,149,0.1); }
.riset-search-extreme .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.riset-search-extreme .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.riset-search-extreme .s-clear:hover { background: #fee2e2; color: #dc2626; }

.riset-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.riset-select:focus { outline: none; border-color: #4c1d95; }

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
.riset-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.riset-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer;
}
.riset-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.riset-card-extreme:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95;
}
.riset-card-extreme:hover::before { transform: scaleX(1); }

.riset-type-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem;
    border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.85rem; width: fit-content;
}
.riset-year-ext {
    font-family: var(--font-display); font-size: 0.82rem; font-weight: 700;
    color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;
}
.riset-title-ext {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.65rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-desc-ext {
    color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-meta-ext {
    display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border); font-size: 0.78rem; color: var(--text-muted);
    margin-bottom: 1rem;
}
.riset-meta-ext span { display: inline-flex; align-items: center; gap: 0.3rem; }
.riset-card-actions {
    display: flex; gap: 0.4rem; flex-wrap: wrap;
}

/* ===== LIST VIEW ===== */
.riset-list { display: flex; flex-direction: column; gap: 1rem; }
.riset-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.riset-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #4c1d95; }
.riset-list-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: linear-gradient(135deg, #4c1d95, #1e3a8a);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
}
.riset-list-content h3 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem;
    color: var(--text-primary); line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary);
}
.riset-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.riset-list-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }

/* ===== TABLE VIEW ===== */
.riset-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm);
}
.riset-table { width: 100%; border-collapse: collapse; }
.riset-table th {
    background: var(--bg-secondary); padding: 1rem 1.25rem; text-align: left;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
}
.riset-table td {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    font-size: 0.88rem; color: var(--text-primary); vertical-align: middle;
}
.riset-table tr:last-child td { border-bottom: none; }
.riset-table tbody tr { transition: all 0.2s; cursor: pointer; }
.riset-table tbody tr:hover { background: rgba(76,29,149,0.03); }
.riset-table-title {
    font-weight: 600; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* ===== CTA ===== */
.riset-cta-ext {
    background: linear-gradient(135deg, var(--primary) 0%, #4c1d95 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.riset-cta-ext::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.riset-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY ===== */
.empty-riset-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
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
    width: 100%; max-width: 760px; max-height: 90vh; overflow-y: auto;
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
.modal-header-ext {
    background: linear-gradient(135deg, #1e3a8a, #4c1d95);
    padding: 2.5rem 2rem; color: white; position: relative;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-ext::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-title-ext {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.9rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-ext { padding: 2rem; }
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

.modal-section-ext { margin-bottom: 1.5rem; }
.modal-section-ext h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section-ext p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }

.modal-keywords { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.keyword-tag {
    padding: 0.35rem 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: 999px;
    font-size: 0.78rem; color: var(--text-secondary); font-weight: 600;
}

.modal-footer-ext {
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
    .featured-grid { grid-template-columns: 1fr; }
    .chart-section, .top-researchers { margin-bottom: 1.5rem; }
}
@media (max-width: 768px) {
    .riset-hero-extreme { padding: 8rem 0 5rem; }
    .riset-stats-extreme { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .riset-toolbar-extreme { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .riset-grid-extreme { grid-template-columns: 1fr; }
    .riset-list-item { grid-template-columns: 1fr; text-align: center; }
    .riset-list-icon { margin: 0 auto; }
    .riset-list-actions { justify-content: center; }
    .riset-table-wrap { overflow-x: auto; }
    .riset-table { min-width: 800px; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .riset-stats-extreme { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="riset-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Riset & Publikasi</span>
        </nav>
        <div style="text-align: center;">
            <div class="hero-badge-extreme" data-aos="fade-down" data-aos-delay="100">
                <span class="hero-badge-pulse"></span>
                <span>Research Excellence Center</span>
            </div>
            <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
                Pusat
                <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Riset & Publikasi</span>
            </h1>
            <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
                Kontribusi ilmiah dosen dan mahasiswa FKIP UNIMOF dalam memajukan ilmu pengetahuan dan teknologi pendidikan melalui penelitian inovatif dan pengabdian masyarakat.
            </p>
            <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
                <span class="trust-pill">🔬 <?= $stat_total ?> Total Riset</span>
                <span class="trust-pill">📚 <?= $stat_publikasi ?> Publikasi</span>
                <span class="trust-pill">👥 <?= count($researcher_dist) ?> Peneliti</span>
            </div>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="riset-stats-extreme" data-aos="fade-up">
            <div class="riset-stat-card" style="--stat-color: #3b82f6;">
                <div class="riset-stat-icon">🔬</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="riset-stat-label">Total Riset</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #10b981;">
                <div class="riset-stat-icon">📚</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_publikasi ?>">0</div>
                <div class="riset-stat-label">Publikasi</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #f59e0b;">
                <div class="riset-stat-icon">💰</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_hibah ?>">0</div>
                <div class="riset-stat-label">Hibah</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #8b5cf6;">
                <div class="riset-stat-icon">🤝</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_pengabdian ?>">0</div>
                <div class="riset-stat-label">Pengabdian</div>
            </div>
        </div>

        <!-- Featured -->
        <?php if (count($featured) > 0): ?>
        <div class="featured-section" data-aos="fade-up">
            <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
                <span class="section-tag">Unggulan</span>
                <h2 class="section-title">Riset <span class="gradient-text">Pilihan</span></h2>
            </div>
            <div class="featured-grid">
                <?php foreach ($featured as $f):
                    $type_class = 'type-' . strtolower($f['jenis'] ?? 'lainnya') . '-ext';
                    $icon = $f['jenis'] === 'Publikasi' ? '📚' : ($f['jenis'] === 'Hibah' ? '💰' : ($f['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                ?>
                <div class="featured-card" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>
                    <span class="featured-type-badge <?= $type_class ?>"><?= $icon ?> <?= strtoupper($f['jenis'] ?? 'Riset') ?></span>
                    <div class="featured-year">📅 Tahun <?= sanitize($f['tahun'] ?? '-') ?></div>
                    <h3 class="featured-title"><?= sanitize($f['judul']) ?></h3>
                    <p class="featured-desc"><?= excerpt($f['abstrak'] ?? $f['deskripsi'] ?? '', 200) ?></p>
                    <div class="featured-meta">
                        <span>👤 <?= sanitize($f['ketua_nama'] ?? 'Tim Peneliti') ?></span>
                        <span>🎓 <?= sanitize($f['prodi_singkatan'] ?? $f['prodi_nama'] ?? 'Umum') ?></span>
                        <?php if (!empty($f['kategori'])): ?>
                            <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;" onclick="event.stopPropagation();">
                        <button class="btn-action primary" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>📖 Baca Abstrak</button>
                        <button class="btn-action" onclick='shareRiset(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts + Top Researchers Row -->
        <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; margin-bottom: 2rem;" data-aos="fade-up">
            <div class="chart-section">
                <h3>📊 Distribusi Riset per Tahun</h3>
                <div id="risetChart"></div>
            </div>
            <?php if (!empty($top_researchers)): ?>
            <div class="top-researchers">
                <h3>🏆 Top Peneliti</h3>
                <div class="researcher-list">
                    <?php $rank = 1; foreach ($top_researchers as $name => $count): ?>
                    <div class="researcher-item">
                        <div class="researcher-rank <?= $rank <= 3 ? 'top-' . $rank : '' ?>"><?= $rank ?></div>
                        <div class="researcher-info">
                            <h5><?= sanitize($name) ?></h5>
                            <span>Peneliti Aktif</span>
                        </div>
                        <div class="researcher-count"><?= $count ?></div>
                    </div>
                    <?php $rank++; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($riset_list)): ?>
            <div class="empty-riset-ext" data-aos="fade-up">
                <div class="empty-icon-lg">🔬</div>
                <h3>Belum ada data riset</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Publikasi dan penelitian akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="riset-toolbar-extreme" data-aos="fade-up">
                <div class="riset-search-extreme">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="risetSearch" placeholder="Cari judul, ketua peneliti, atau kata kunci..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="riset.php?jenis=<?= urlencode($filter_jenis) ?>&kategori=<?= urlencode($filter_kategori) ?>&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>" class="s-clear" title="Clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="riset-select" id="sortSelect">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>🕐 Terlama</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📊 Tabel</button>
                </div>
                <a href="#" onclick="exportRiset(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills (by Jenis) -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_jenis === 'all' ? 'active' : '' ?>" href="riset.php?jenis=all&kategori=<?= urlencode($filter_kategori) ?>&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>">
                    🔬 Semua Jenis <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php
                $jenis_icons = ['Publikasi'=>'📚','Hibah'=>'💰','Pengabdian'=>'🤝'];
                foreach ($jenis_dist as $j => $cnt):
                    $icon = $jenis_icons[$j] ?? '📄';
                ?>
                <a class="filter-pill <?= $filter_jenis === $j ? 'active' : '' ?>" href="riset.php?jenis=<?= urlencode($j) ?>&kategori=<?= urlencode($filter_kategori) ?>&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>&sort=<?= urlencode($sort) ?>">
                    <?= $icon ?> <?= sanitize($j) ?> <span class="pill-count"><?= $cnt ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Secondary Filters (Kategori + Tahun) -->
            <?php if (!empty($kategoris) || count($years) > 1): ?>
            <div style="display:flex; gap:0.75rem; margin-bottom:1.5rem; flex-wrap:wrap;" data-aos="fade-up">
                <?php if (!empty($kategoris)): ?>
                <select class="riset-select" id="katSelect" style="min-width:200px;">
                    <option value="all">🏷️ Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?= urlencode($k) ?>" <?= $filter_kategori === $k ? 'selected' : '' ?>><?= sanitize($k) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if (count($years) > 1): ?>
                <select class="riset-select" id="yearSelect" style="min-width:150px;">
                    <option value="all">📅 Semua Tahun</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="riset-grid-extreme" data-aos="fade-up">
                <?php foreach ($riset_list as $r):
                    if (in_array($r, $featured, true)) continue;
                    $type_class = 'type-' . strtolower($r['jenis'] ?? 'lainnya') . '-ext';
                    $icon = $r['jenis'] === 'Publikasi' ? '📚' : ($r['jenis'] === 'Hibah' ? '💰' : ($r['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                ?>
                <article class="riset-card-extreme" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
                    <span class="riset-type-badge-extreme <?= $type_class ?>"><?= $icon ?> <?= strtoupper($r['jenis'] ?? 'Riset') ?></span>
                    <div class="riset-year-ext">📅 Tahun <?= sanitize($r['tahun'] ?? '-') ?></div>
                    <h3 class="riset-title-ext"><?= sanitize($r['judul']) ?></h3>
                    <p class="riset-desc-ext"><?= excerpt($r['deskripsi'] ?? $r['abstrak'] ?? 'Tidak ada deskripsi tersedia.', 150) ?></p>
                    <div class="riset-meta-ext">
                        <span>👤 <?= sanitize($r['ketua_nama'] ?? 'Tim Peneliti') ?></span>
                        <span>🎓 <?= sanitize($r['prodi_singkatan'] ?? $r['prodi_nama'] ?? 'Umum') ?></span>
                        <?php if (!empty($r['kategori'])): ?>
                            <span>🏷️ <?= sanitize($r['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="riset-card-actions" onclick="event.stopPropagation();">
                        <?php if (!empty($r['abstrak'])): ?>
                            <button class="btn-action primary" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>📖 Abstrak</button>
                        <?php endif; ?>
                        <button class="btn-action" onclick='shareRiset(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                        <?php if ($riset_cols['doi'] && !empty($r['doi'])): ?>
                            <a href="https://doi.org/<?= sanitize($r['doi']) ?>" target="_blank" class="btn-action" onclick="event.stopPropagation();">🔗 DOI</a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="riset-list" data-aos="fade-up">
                <?php foreach ($riset_list as $r):
                    $icon = $r['jenis'] === 'Publikasi' ? '📚' : ($r['jenis'] === 'Hibah' ? '💰' : ($r['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                ?>
                <div class="riset-list-item" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="riset-list-icon"><?= $icon ?></div>
                    <div class="riset-list-content">
                        <h3><?= sanitize($r['judul']) ?></h3>
                        <div class="riset-list-meta">
                            <span class="riset-type-badge-extreme type-<?= strtolower($r['jenis'] ?? 'lainnya') ?>-ext" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;">
                                <?= sanitize($r['jenis'] ?? 'Riset') ?>
                            </span>
                            <span>📅 <?= sanitize($r['tahun'] ?? '-') ?></span>
                            <span>👤 <?= sanitize($r['ketua_nama'] ?? 'Tim') ?></span>
                            <span>🎓 <?= sanitize($r['prodi_singkatan'] ?? $r['prodi_nama'] ?? 'Umum') ?></span>
                        </div>
                    </div>
                    <div class="riset-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='shareRiset(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <button class="btn-action primary" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>📖</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== TABLE VIEW ===== -->
            <?php if ($view === 'table'): ?>
            <div class="riset-table-wrap" data-aos="fade-up">
                <table class="riset-table">
                    <thead>
                        <tr>
                            <th>Judul Riset</th>
                            <th>Jenis</th>
                            <th>Tahun</th>
                            <th>Ketua</th>
                            <th>Prodi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riset_list as $r):
                            $icon = $r['jenis'] === 'Publikasi' ? '📚' : ($r['jenis'] === 'Hibah' ? '💰' : ($r['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                        ?>
                        <tr onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
                            <td>
                                <div class="riset-table-title"><?= sanitize($r['judul']) ?></div>
                                <?php if (!empty($r['kategori'])): ?>
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">🏷️ <?= sanitize($r['kategori']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="riset-type-badge-extreme type-<?= strtolower($r['jenis'] ?? 'lainnya') ?>-ext" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;">
                                    <?= $icon ?> <?= sanitize($r['jenis'] ?? '-') ?>
                                </span>
                            </td>
                            <td><?= sanitize($r['tahun'] ?? '-') ?></td>
                            <td><?= sanitize($r['ketua_nama'] ?? '-') ?></td>
                            <td><?= sanitize($r['prodi_singkatan'] ?? $r['prodi_nama'] ?? '-') ?></td>
                            <td onclick="event.stopPropagation();">
                                <div style="display:flex; gap:0.35rem;">
                                    <button class="btn-action" onclick='openAbstractModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>📖</button>
                                    <button class="btn-action" onclick='shareRiset(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="riset-cta-ext" data-aos="zoom-in">
            <div class="riset-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Tertarik Berkolaborasi dalam Riset?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Kami terbuka untuk kerjasama penelitian dengan institusi lain, baik dalam negeri maupun internasional. Mari bersama-sama memajukan ilmu pengetahuan!
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    🤝 Hubungi Kami untuk Kolaborasi
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay-ext" id="abstractModal" onclick="if(event.target===this)closeAbstractModal()">
    <div class="modal-content-ext" id="abstractModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const risetCols = <?= json_encode($riset_cols) ?>;
const jenisDist = <?= json_encode($jenis_dist) ?>;
const kategoriDist = <?= json_encode($kategori_dist) ?>;
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
document.getElementById('risetSearch')?.addEventListener('input', function() {
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

document.getElementById('katSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('kategori', this.value);
    window.location = url;
});

document.getElementById('yearSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('tahun', this.value);
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== EXPORT CSV =====
function exportRiset() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($riset_list) ?>;
    const headers = ['Judul','Jenis','Tahun','Ketua','Prodi','Kategori','DOI'];
    const rows = data.map(d => [
        d.judul || '', d.jenis || '', d.tahun || '',
        d.ketua_nama || '', d.prodi_nama || '', d.kategori || '', d.doi || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'riset-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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
function openAbstractModal(r) {
    const icon = r.jenis === 'Publikasi' ? '📚' : (r.jenis === 'Hibah' ? '💰' : (r.jenis === 'Pengabdian' ? '🤝' : '🔬'));

    // Info grid
    let info_items = '';
    if (risetCols.tahun && r.tahun) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📅 Tahun</div><div class="modal-info-value">${escapeHtml(r.tahun)}</div></div>`;
    }
    if (risetCols.jenis && r.jenis) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🔬 Jenis</div><div class="modal-info-value">${escapeHtml(r.jenis)}</div></div>`;
    }
    if (r.ketua_nama) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">👤 Ketua Peneliti</div><div class="modal-info-value">${escapeHtml(r.ketua_nama)}</div></div>`;
    }
    if (r.prodi_nama) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🎓 Program Studi</div><div class="modal-info-value">${escapeHtml(r.prodi_nama)}</div></div>`;
    }
    if (risetCols.kategori && r.kategori) {
        info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🏷️ Kategori</div><div class="modal-info-value">${escapeHtml(r.kategori)}</div></div>`;
    }

    // DOI section
    let doi_html = '';
    if (risetCols.doi && r.doi) {
        doi_html = `
            <div class="modal-section-ext">
                <h4>🔗 DOI (Digital Object Identifier)</h4>
                <div class="modal-info-value"><a href="https://doi.org/${escapeHtml(r.doi)}" target="_blank">https://doi.org/${escapeHtml(r.doi)}</a></div>
            </div>
        `;
    }

    // Jurnal section
    let jurnal_html = '';
    if (risetCols.jurnal && r.jurnal) {
        jurnal_html = `
            <div class="modal-section-ext">
                <h4>📰 Jurnal / Penerbit</h4>
                <p>${escapeHtml(r.jurnal)}</p>
            </div>
        `;
    }

    // Link section
    let link_html = '';
    if (risetCols.link && r.link) {
        link_html = `
            <div class="modal-section-ext">
                <h4>🔗 Link Publikasi</h4>
                <div class="modal-info-value"><a href="${escapeHtml(r.link)}" target="_blank">${escapeHtml(r.link)}</a></div>
            </div>
        `;
    }

    // Anggota section
    let anggota_html = '';
    if (risetCols.anggota && r.anggota) {
        const members = r.anggota.split('\n').map(s => s.trim()).filter(s => s);
        if (members.length > 0) {
            anggota_html = `
                <div class="modal-section-ext">
                    <h4>👥 Anggota Tim</h4>
                    <ul style="list-style:none; padding:0; margin:0;">
                        ${members.map(m => `<li style="padding:0.5rem 0.75rem; background:var(--bg-secondary); border-radius:8px; margin-bottom:0.4rem; font-size:0.9rem;">• ${escapeHtml(m)}</li>`).join('')}
                    </ul>
                </div>
            `;
        }
    }

    // Keywords
    let keywords = [];
    if (risetCols.keywords && r.keywords) {
        keywords = r.keywords.split(',').map(s => s.trim()).filter(s => s);
    }
    if (r.kategori) keywords.push(r.kategori);
    if (r.jenis) keywords.push(r.jenis);
    keywords = [...new Set(keywords)];

    const keywords_html = keywords.length > 0
        ? `<div class="modal-keywords">${keywords.map(k => `<span class="keyword-tag">${escapeHtml(k)}</span>`).join('')}</div>`
        : '';

    const html = `
        <button class="modal-close-ext" onclick="closeAbstractModal()">✕</button>
        <div class="modal-header-ext">
            <div class="modal-header-content">
                <div style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.35rem 0.85rem; background:rgba(255,255,255,0.15); backdrop-filter:blur(10px); border-radius:999px; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">
                    ${icon} ${escapeHtml(r.jenis || 'Riset')}
                </div>
                <h2 class="modal-title-ext">${escapeHtml(r.judul)}</h2>
                <div class="modal-subtitle">
                    ${r.ketua_nama ? '👤 ' + escapeHtml(r.ketua_nama) : ''}
                    ${r.prodi_nama ? ' • 🎓 ' + escapeHtml(r.prodi_nama) : ''}
                </div>
                <div class="modal-meta-ext">
                    ${r.tahun ? `<span>📅 ${escapeHtml(r.tahun)}</span>` : ''}
                    ${r.kategori ? `<span>🏷️ ${escapeHtml(r.kategori)}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-ext">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${risetCols.abstrak && r.abstrak ? `
            <div class="modal-section-ext">
                <h4>📝 Abstrak</h4>
                <p>${escapeHtml(r.abstrak)}</p>
            </div>` : ''}

            ${risetCols.deskripsi && r.deskripsi ? `
            <div class="modal-section-ext">
                <h4>📋 Deskripsi</h4>
                <p>${escapeHtml(r.deskripsi)}</p>
            </div>` : ''}

            ${doi_html}
            ${jurnal_html}
            ${link_html}
            ${anggota_html}

            ${keywords_html ? `
            <div class="modal-section-ext">
                <h4>🏷️ Kata Kunci</h4>
                ${keywords_html}
            </div>` : ''}
        </div>
        <div class="modal-footer-ext">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareRiset(${JSON.stringify(r).replace(/"/g, "&quot;")})'>🔗 Share</button>
                ${risetCols.doi && r.doi ? `<a href="https://doi.org/${escapeHtml(r.doi)}" target="_blank" class="modal-btn secondary">🔗 DOI</a>` : ''}
                ${risetCols.link && r.link ? `<a href="${escapeHtml(r.link)}" target="_blank" class="modal-btn secondary">🌐 Link</a>` : ''}
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn primary" onclick="closeAbstractModal()">Tutup</button>
            </div>
        </div>
    `;
    document.getElementById('abstractModalContent').innerHTML = html;
    document.getElementById('abstractModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAbstractModal() {
    document.getElementById('abstractModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareRiset(r) {
    const text = `🔬 ${r.judul}\n${r.jenis ? '📚 Jenis: ' + r.jenis + '\n' : ''}${r.tahun ? '📅 Tahun: ' + r.tahun + '\n' : ''}${r.ketua_nama ? '👤 Ketua: ' + r.ketua_nama + '\n' : ''}${r.prodi_nama ? '🎓 Prodi: ' + r.prodi_nama + '\n' : ''}${r.doi ? '🔗 DOI: https://doi.org/' + r.doi + '\n' : ''}\nPusat Riset FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: r.judul, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info riset disalin', '📋');
    }
}

// ===== CHARTS =====
const chartYears = Object.keys(yearDist).sort();
const chartValues = chartYears.map(y => yearDist[y]);

if (chartYears.length > 0) {
    new ApexCharts(document.querySelector("#risetChart"), {
        series: [{ name: 'Jumlah Riset', data: chartValues }],
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        colors: ['#4c1d95'],
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        xaxis: { categories: chartYears, labels: { style: { fontSize: '11px', fontWeight: 600 } } },
        yaxis: { title: { text: 'Jumlah Riset', style: { fontSize: '11px' } }, min: 0, tickAmount: 5 },
        grid: { borderColor: '#f1f5f9' },
        tooltip: { y: { formatter: (val) => val + ' riset' } }
    }).render();
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('risetSearch')?.focus();
    }
    if (e.key === 'Escape') closeAbstractModal();
});

console.log('%c🔬 Pusat Riset FKIP UNIMOF - EXTREME MULTIMATE', 'color:#4c1d95;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>