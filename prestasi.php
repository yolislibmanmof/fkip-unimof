<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Prestasi Mahasiswa';
$page_description = 'Deretan prestasi gemilang mahasiswa FKIP UNIMOF di tingkat regional, nasional, dan internasional.';

// ===== SCHEMA-SAFE: deteksi kolom prestasi =====
$prestasi_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `prestasi`")->fetchAll(PDO::FETCH_COLUMN);
    $prestasi_cols = [
        'judul'              => in_array('judul', $cols, true),
        'mahasiswa'          => in_array('mahasiswa', $cols, true),
        'juara'              => in_array('juara', $cols, true),
        'lomba'              => in_array('lomba', $cols, true),
        'tingkat'            => in_array('tingkat', $cols, true),
        'tahun'              => in_array('tahun', $cols, true),
        'deskripsi'          => in_array('deskripsi', $cols, true),
        'foto'               => in_array('foto', $cols, true),
        'tanggal'            => in_array('tanggal', $cols, true),
        'penyelenggara'      => in_array('penyelenggara', $cols, true),
        'program_studi_id'   => in_array('program_studi_id', $cols, true),
        'kategori_lomba'     => in_array('kategori_lomba', $cols, true),
        'link_berita'        => in_array('link_berita', $cols, true),
    ];
} catch (Exception $e) {
    $prestasi_cols = array_fill_keys(['judul','mahasiswa','juara','lomba','tingkat','tahun','deskripsi','foto','tanggal','penyelenggara','program_studi_id','kategori_lomba','link_berita'], false);
}

// ===== AMBIL DATA =====
$join = $prestasi_cols['program_studi_id'] ? "LEFT JOIN program_studi p ON pr.program_studi_id = p.id" : "";
$select = "pr.*";
if ($prestasi_cols['program_studi_id']) $select .= ", p.nama as prodi_nama, p.singkatan as prodi_singkatan";

$tingkat_order = "FIELD(pr.tingkat, 'Internasional', 'Nasional', 'Wilayah', 'Universitas') ASC";
$order_by = ($prestasi_cols['tahun'] ? "pr.tahun DESC, " : "") . $tingkat_order;

$stmt = $pdo->query("SELECT $select FROM prestasi pr $join ORDER BY $order_by");
$prestasi_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($prestasi_list);
$stat_int = $stat_nas = $stat_wil = $stat_univ = 0;
$tingkat_dist = [];
$tahun_dist = [];
$kategori_dist = [];
$prodi_dist = [];

foreach ($prestasi_list as $p) {
    $t = $p['tingkat'] ?? '';
    if ($t === 'Internasional') $stat_int++;
    elseif ($t === 'Nasional') $stat_nas++;
    elseif ($t === 'Wilayah') $stat_wil++;
    elseif (stripos($t, 'Universitas') !== false) $stat_univ++;
    if ($t !== '') $tingkat_dist[$t] = ($tingkat_dist[$t] ?? 0) + 1;

    if ($prestasi_cols['tahun'] && !empty($p['tahun'])) {
        $tahun_dist[$p['tahun']] = ($tahun_dist[$p['tahun']] ?? 0) + 1;
    }
    if ($prestasi_cols['kategori_lomba'] && !empty($p['kategori_lomba'])) {
        $kategori_dist[$p['kategori_lomba']] = ($kategori_dist[$p['kategori_lomba']] ?? 0) + 1;
    }
    if ($prestasi_cols['program_studi_id'] && !empty($p['prodi_nama'] ?? '')) {
        $prodi_dist[$p['prodi_nama']] = ($prodi_dist[$p['prodi_nama']] ?? 0) + 1;
    }
}

arsort($tingkat_dist);
krsort($tahun_dist);

$years = array_keys($tahun_dist);

// ===== SPOTLIGHT (prestasi tertinggi) =====
$spotlight = null;
foreach ($prestasi_list as $p) {
    if (($p['tingkat'] ?? '') === 'Internasional') { $spotlight = $p; break; }
}
if (!$spotlight) $spotlight = $prestasi_list[0] ?? null;

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_tingkat = $_GET['tingkat'] ?? 'all';
$filter_tahun = $_GET['tahun'] ?? 'all';
$filter_kategori = $_GET['kategori'] ?? 'all';
$view = $_GET['view'] ?? 'timeline';

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.prestasi-hero-extreme {
    position: relative; background: linear-gradient(135deg, #f59e0b 0%, #d97706 40%, #92400e 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.prestasi-hero-extreme::before {
    content: '🏆'; position: absolute; font-size: 25rem; opacity: 0.08;
    top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;
    animation: floatTrophy 6s ease-in-out infinite;
}
@keyframes floatTrophy { 0%, 100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-50%, -55%) scale(1.05); } }
.prestasi-hero-extreme::after {
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
.prestasi-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.prestasi-hero-extreme .breadcrumb { justify-content: center; }
.prestasi-hero-extreme .breadcrumb a, .prestasi-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.prestasi-hero-extreme .breadcrumb a:hover { color: white; }

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
.prestasi-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.prestasi-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.prestasi-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--stat-color, var(--secondary));
}
.prestasi-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--secondary)); }
.prestasi-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.prestasi-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--secondary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.prestasi-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SPOTLIGHT ===== */
.spotlight-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: auto 1fr auto;
    gap: 2rem; padding: 2rem; align-items: center; position: relative;
}
.spotlight-section::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706, #92400e);
}
.spotlight-badge {
    position: absolute; top: 1rem; right: 1rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.7rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    display: flex; align-items: center; gap: 0.3rem;
    animation: pulseBadge 2s infinite;
}
@keyframes pulseBadge {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.4); }
    50% { box-shadow: 0 0 0 10px rgba(245,158,11,0); }
}
.spotlight-icon {
    width: 100px; height: 100px; border-radius: 24px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 3rem; flex-shrink: 0;
    box-shadow: 0 10px 30px rgba(245,158,11,0.3);
}
.spotlight-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 24px; }
.spotlight-info h2 {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    margin-bottom: 0.5rem; color: var(--text-primary); line-height: 1.3;
}
.spotlight-meta {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;
}
.spotlight-desc {
    font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.spotlight-actions { display: flex; flex-direction: column; gap: 0.5rem; }

/* ===== BADGES ===== */
.tingkat-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em;
}
.badge-internasional { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-nasional { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-wilayah { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-universitas { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }
.badge-default { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }

/* ===== TOOLBAR ===== */
.prestasi-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.prestasi-search { flex: 1; min-width: 240px; position: relative; }
.prestasi-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.prestasi-search input:focus { outline: none; border-color: #f59e0b; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
.prestasi-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.prestasi-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.prestasi-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.prestasi-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.prestasi-select:focus { outline: none; border-color: #f59e0b; }

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
.view-btn.active { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.export-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: var(--bg-secondary); border: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.export-btn:hover { background: #f59e0b; color: white; border-color: #f59e0b; transform: translateY(-1px); }

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
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border-color: #d97706;
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TIMELINE VIEW ===== */
.timeline-extreme { max-width: 1000px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-extreme::before {
    content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #f59e0b, #d97706, transparent);
    transform: translateX(-50%); border-radius: 4px;
}
.timeline-item-extreme {
    display: flex; justify-content: flex-end; padding-right: 50%;
    position: relative; margin-bottom: 3rem;
}
.timeline-item-extreme:nth-child(even) {
    justify-content: flex-start; padding-right: 0; padding-left: 50%;
}
.timeline-dot-extreme {
    position: absolute; left: 50%; top: 1.5rem; width: 28px; height: 28px;
    background: var(--bg-primary); border: 4px solid #f59e0b;
    border-radius: 50%; transform: translateX(-50%); z-index: 2;
    box-shadow: 0 0 0 6px rgba(245,158,11,0.15); transition: all 0.3s;
}
.timeline-item-extreme:hover .timeline-dot-extreme {
    transform: translateX(-50%) scale(1.2); box-shadow: 0 0 0 8px rgba(245,158,11,0.25);
}
.timeline-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; margin: 0 2.5rem;
    position: relative; transition: all 0.4s; width: 100%; max-width: 450px;
    cursor: pointer;
}
.timeline-card-extreme:hover {
    transform: translateY(-8px) scale(1.02); box-shadow: var(--shadow-xl);
    border-color: #f59e0b;
}
.timeline-card-extreme::before {
    content: ''; position: absolute; top: 1.5rem; width: 20px; height: 20px;
    background: var(--bg-primary); border: 1px solid var(--border); transform: rotate(45deg);
}
.timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before { right: -11px; border-left: none; border-bottom: none; }
.timeline-item-extreme:nth-child(even) .timeline-card-extreme::before { left: -11px; border-right: none; border-top: none; }

.prestasi-year-extreme {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    color: #f59e0b; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.prestasi-title-extreme {
    font-size: 1.15rem; font-weight: 800; color: var(--text-primary);
    margin-bottom: 0.85rem; line-height: 1.4;
}
.prestasi-meta-extreme { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.meta-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.75rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.78rem;
    color: var(--text-secondary); font-weight: 600;
}

/* ===== GRID VIEW ===== */
.prestasi-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;
}
.prestasi-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer;
}
.prestasi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.prestasi-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #f59e0b; }
.prestasi-card:hover::before { transform: scaleX(1); }

.prestasi-card-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin-bottom: 1rem; flex-shrink: 0;
}
.prestasi-card-icon.intl { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.prestasi-card-icon.nat { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
.prestasi-card-icon.reg { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; }

.prestasi-card h3 {
    font-size: 1.1rem; font-weight: 800; color: var(--text-primary);
    margin-bottom: 0.5rem; line-height: 1.3;
}
.prestasi-card-year {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900;
    color: #f59e0b; margin-bottom: 0.5rem;
}
.prestasi-card-desc {
    font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.prestasi-card-footer {
    display: flex; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid var(--border);
}

/* ===== LIST VIEW ===== */
.prestasi-list { display: flex; flex-direction: column; gap: 1rem; }
.prestasi-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.25rem; align-items: center;
    cursor: pointer;
}
.prestasi-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #f59e0b; }
.prestasi-list-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
}
.prestasi-list-info h3 {
    font-size: 1rem; font-weight: 700; margin-bottom: 0.3rem;
    color: var(--text-primary); line-height: 1.3;
}
.prestasi-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary);
}
.prestasi-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.prestasi-list-actions { display: flex; gap: 0.4rem; }

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
.prestasi-cta {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.prestasi-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.prestasi-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

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
.modal-content-prestasi {
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
.modal-header-prestasi {
    padding: 2.5rem 2rem; color: white; position: relative;
    background: linear-gradient(135deg, #f59e0b, #d97706, #92400e);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-prestasi::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-icon-box {
    width: 80px; height: 80px; border-radius: 20px; margin-bottom: 1.25rem;
    background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; border: 3px solid rgba(255,255,255,0.3);
}
.modal-title {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.92rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-prestasi { padding: 2rem; }
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
.modal-info-value a { color: #f59e0b; text-decoration: none; }
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
.modal-btn.primary { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
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

.btn-action {
    padding: 0.5rem 0.9rem; border-radius: 8px; font-size: 0.78rem;
    font-weight: 600; border: 1px solid var(--border); background: var(--bg-secondary);
    color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none;
    font-family: inherit;
}
.btn-action:hover { background: #f59e0b; color: white; border-color: #f59e0b; transform: translateY(-1px); }
.btn-action.primary {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border-color: #d97706;
}
.btn-action.primary:hover { filter: brightness(1.1); }

@media (max-width: 968px) {
    .chart-grid { grid-template-columns: 1fr; }
    .spotlight-section { grid-template-columns: 1fr; text-align: center; }
    .spotlight-icon { margin: 0 auto; }
    .spotlight-actions { flex-direction: row; justify-content: center; }
}
@media (max-width: 768px) {
    .timeline-extreme::before { left: 24px; }
    .timeline-item-extreme, .timeline-item-extreme:nth-child(even) {
        justify-content: flex-start; padding-left: 70px; padding-right: 0;
    }
    .timeline-dot-extreme { left: 24px; }
    .timeline-card-extreme { margin: 0; max-width: 100%; }
    .timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before,
    .timeline-item-extreme:nth-child(even) .timeline-card-extreme::before {
        left: -11px; border-right: none; border-top: none;
    }
    .prestasi-hero-extreme { padding: 8rem 0 5rem; }
    .prestasi-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .prestasi-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .prestasi-grid { grid-template-columns: 1fr; }
    .prestasi-list-item { grid-template-columns: 1fr; text-align: center; }
    .prestasi-list-icon { margin: 0 auto; }
    .prestasi-list-actions { justify-content: center; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .prestasi-stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="prestasi-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Prestasi Mahasiswa</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>🏆 Hall of Fame</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">
            Prestasi
            <span style="background: linear-gradient(135deg, #fef3c7, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Membanggakan</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto;" data-aos="fade-up" data-aos-delay="200">
            Deretan pencapaian gemilang mahasiswa FKIP UNIMOF yang mengharumkan nama almamater di berbagai kompetisi regional, nasional, dan internasional.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">🏆 <?= $stat_total ?> Prestasi</span>
            <span class="trust-pill">🌍 <?= $stat_int ?> Internasional</span>
            <span class="trust-pill">🇮🇩 <?= $stat_nas ?> Nasional</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="prestasi-stats-bar" data-aos="fade-up">
            <div class="prestasi-stat-card" style="--stat-color: #f59e0b;">
                <div class="prestasi-stat-icon">🏆</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="prestasi-stat-label">Total Prestasi</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #92400e;">
                <div class="prestasi-stat-icon">🌍</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $stat_int ?>">0</div>
                <div class="prestasi-stat-label">Internasional</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #1e40af;">
                <div class="prestasi-stat-icon">🇮🇩</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $stat_nas ?>">0</div>
                <div class="prestasi-stat-label">Nasional</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #166534;">
                <div class="prestasi-stat-icon">🏛️</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $stat_wil ?>">0</div>
                <div class="prestasi-stat-label">Wilayah</div>
            </div>
            <?php if ($stat_univ > 0): ?>
            <div class="prestasi-stat-card" style="--stat-color: #64748b;">
                <div class="prestasi-stat-icon">🏫</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $stat_univ ?>">0</div>
                <div class="prestasi-stat-label">Universitas</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Spotlight -->
        <?php if ($spotlight):
            $tingkat = $spotlight['tingkat'] ?? 'Nasional';
            $icon = $tingkat === 'Internasional' ? '🌍' : ($tingkat === 'Nasional' ? '🇮🇩' : ($tingkat === 'Wilayah' ? '🏛️' : '🏫'));
            $badge_class = 'badge-' . strtolower($tingkat);
        ?>
        <div class="spotlight-section" data-aos="fade-up" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>
            <span class="spotlight-badge">⭐ Prestasi Tertinggi</span>
            <div class="spotlight-icon">
                <?php if ($prestasi_cols['foto'] && !empty($spotlight['foto'])): ?>
                    <img src="<?= asset('uploads/prestasi/' . basename($spotlight['foto'])) ?>" alt="">
                <?php else: ?>
                    <?= $icon ?>
                <?php endif; ?>
            </div>
            <div class="spotlight-info">
                <span class="tingkat-badge <?= $badge_class ?>"><?= $icon ?> <?= strtoupper($tingkat) ?></span>
                <h2><?= sanitize($spotlight['judul']) ?></h2>
                <div class="spotlight-meta">
                    <span class="meta-pill">👤 <?= sanitize($spotlight['mahasiswa'] ?? 'Tim') ?></span>
                    <span class="meta-pill">🥇 <?= sanitize($spotlight['juara'] ?? '-') ?></span>
                    <span class="meta-pill">📅 <?= sanitize($spotlight['tahun'] ?? '-') ?></span>
                    <span class="meta-pill">🎓 <?= sanitize($spotlight['prodi_singkatan'] ?? $spotlight['prodi_nama'] ?? 'Umum') ?></span>
                </div>
                <p class="spotlight-desc"><?= sanitize(excerpt($spotlight['deskripsi'] ?? $spotlight['lomba'] ?? '', 180)) ?></p>
            </div>
            <div class="spotlight-actions" onclick="event.stopPropagation();">
                <button class="btn-action primary" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                <button class="btn-action" onclick='sharePrestasi(<?= htmlspecialchars(json_encode($spotlight), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($prestasi_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🏆</div>
                <h3>Belum ada data prestasi</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Prestasi terbaru akan segera diupdate di sini.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="prestasi-toolbar" data-aos="fade-up">
                <div class="prestasi-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="prestasiSearch" placeholder="Cari judul, mahasiswa, atau lomba..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="prestasi.php?tingkat=<?= urlencode($filter_tingkat) ?>&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>" class="s-clear">✕</a>
                    <?php endif; ?>
                </div>
                <?php if (count($years) > 1): ?>
                <select class="prestasi-select" id="yearSelect">
                    <option value="all">📅 Semua Tahun</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Timeline</button>
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📈 Chart</button>
                </div>
                <a href="#" onclick="exportPrestasi(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_tingkat === 'all' ? 'active' : '' ?>" href="prestasi.php?tingkat=all&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>">
                    🌟 Semua <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php
                $tingkat_icons = ['Internasional'=>'🌍','Nasional'=>'🇮🇩','Wilayah'=>'🏛️','Universitas'=>'🏫'];
                foreach ($tingkat_dist as $t => $cnt):
                    $icon = $tingkat_icons[$t] ?? '🏆';
                ?>
                <a class="filter-pill <?= $filter_tingkat === $t ? 'active' : '' ?>" href="prestasi.php?tingkat=<?= urlencode($t) ?>&tahun=<?= urlencode($filter_tahun) ?>&view=<?= urlencode($view) ?>">
                    <?= $icon ?> <?= sanitize($t) ?> <span class="pill-count"><?= $cnt ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ===== TIMELINE VIEW ===== -->
            <?php if ($view === 'timeline'): ?>
            <div class="timeline-extreme" data-aos="fade-up">
                <?php foreach ($prestasi_list as $index => $p):
                    $tingkat = $p['tingkat'] ?? 'Nasional';
                    $badge_class = 'badge-' . strtolower($tingkat);
                    if ($tingkat !== 'Internasional' && $tingkat !== 'Nasional' && $tingkat !== 'Wilayah' && $tingkat !== 'Universitas') $badge_class = 'badge-default';
                    $icon = $tingkat_icons[$tingkat] ?? '🏆';
                ?>
                <div class="timeline-item-extreme" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="timeline-dot-extreme"></div>
                    <div class="timeline-card-extreme">
                        <span class="tingkat-badge <?= $badge_class ?>"><?= $icon ?> <?= strtoupper($tingkat) ?></span>
                        <div class="prestasi-year-extreme">📅 <?= sanitize($p['tahun'] ?? '-') ?></div>
                        <h3 class="prestasi-title-extreme"><?= sanitize($p['judul']) ?></h3>
                        <div class="prestasi-meta-extreme">
                            <?php if ($prestasi_cols['mahasiswa'] && !empty($p['mahasiswa'])): ?>
                                <span class="meta-pill">👤 <?= sanitize($p['mahasiswa']) ?></span>
                            <?php endif; ?>
                            <span class="meta-pill">🎓 <?= sanitize($p['prodi_singkatan'] ?? $p['prodi_nama'] ?? 'Umum') ?></span>
                            <?php if ($prestasi_cols['juara'] && !empty($p['juara'])): ?>
                                <span class="meta-pill">🥇 <?= sanitize($p['juara']) ?></span>
                            <?php endif; ?>
                            <?php if ($prestasi_cols['lomba'] && !empty($p['lomba'])): ?>
                                <span class="meta-pill">🏆 <?= excerpt($p['lomba'], 30) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="prestasi-grid" data-aos="fade-up">
                <?php foreach ($prestasi_list as $p):
                    $tingkat = $p['tingkat'] ?? 'Nasional';
                    $badge_class = 'badge-' . strtolower($tingkat);
                    if (!in_array($tingkat, ['Internasional','Nasional','Wilayah','Universitas'])) $badge_class = 'badge-default';
                    $icon = $tingkat_icons[$tingkat] ?? '🏆';
                    $icon_class = $tingkat === 'Internasional' ? 'intl' : ($tingkat === 'Nasional' ? 'nat' : 'reg');
                ?>
                <div class="prestasi-card" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="prestasi-card-icon <?= $icon_class ?>"><?= $icon ?></div>
                    <span class="tingkat-badge <?= $badge_class ?>"><?= strtoupper($tingkat) ?></span>
                    <div class="prestasi-card-year"><?= sanitize($p['tahun'] ?? '-') ?></div>
                    <h3><?= sanitize($p['judul']) ?></h3>
                    <p class="prestasi-card-desc"><?= sanitize(excerpt($p['deskripsi'] ?? $p['lomba'] ?? '', 120)) ?></p>
                    <div class="prestasi-card-footer" onclick="event.stopPropagation();">
                        <button class="btn-action primary" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                        <button class="btn-action" onclick='sharePrestasi(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <?php if ($prestasi_cols['link_berita'] && !empty($p['link_berita'])): ?>
                            <a href="<?= sanitize($p['link_berita']) ?>" target="_blank" class="btn-action" onclick="event.stopPropagation();">📰</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="prestasi-list" data-aos="fade-up">
                <?php foreach ($prestasi_list as $p):
                    $tingkat = $p['tingkat'] ?? 'Nasional';
                    $icon = $tingkat_icons[$tingkat] ?? '🏆';
                    $badge_class = 'badge-' . strtolower($tingkat);
                    if (!in_array($tingkat, ['Internasional','Nasional','Wilayah','Universitas'])) $badge_class = 'badge-default';
                ?>
                <div class="prestasi-list-item" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="prestasi-list-icon"><?= $icon ?></div>
                    <div class="prestasi-list-info">
                        <h3><?= sanitize($p['judul']) ?></h3>
                        <div class="prestasi-list-meta">
                            <span class="tingkat-badge <?= $badge_class ?>" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;"><?= sanitize($tingkat) ?></span>
                            <span>📅 <?= sanitize($p['tahun'] ?? '-') ?></span>
                            <?php if ($prestasi_cols['mahasiswa'] && !empty($p['mahasiswa'])): ?>
                                <span>👤 <?= sanitize($p['mahasiswa']) ?></span>
                            <?php endif; ?>
                            <?php if ($prestasi_cols['juara'] && !empty($p['juara'])): ?>
                                <span>🥇 <?= sanitize($p['juara']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="prestasi-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='sharePrestasi(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <button class="btn-action primary" onclick='openPrestasiModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>👁️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🏆 Distribusi Tingkat Prestasi</h3>
                    <div id="tingkatChart"></div>
                </div>
                <div class="chart-card">
                    <h3>📅 Tren Prestasi per Tahun</h3>
                    <div id="yearChart"></div>
                </div>
                <?php if (!empty($prodi_dist)): ?>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>🎓 Prestasi per Program Studi</h3>
                    <div id="prodiChart"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="prestasi-cta" data-aos="zoom-in">
            <div class="prestasi-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Punya Prestasi Membanggakan?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Jangan biarkan pencapaianmu tidak tercatat! Laporkan prestasimu ke bagian kemahasiswaan untuk didokumentasikan dan menjadi inspirasi bagi adik tingkat.
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📩 Laporkan Prestasi Saya
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="prestasiModal" onclick="if(event.target===this)closePrestasiModal()">
    <div class="modal-content-prestasi" id="prestasiModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const prestasiCols = <?= json_encode($prestasi_cols) ?>;
const tingkatDist = <?= json_encode($tingkat_dist) ?>;
const tahunDist = <?= json_encode($tahun_dist) ?>;
const prodiDist = <?= json_encode($prodi_dist) ?>;

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
document.getElementById('prestasiSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
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
function exportPrestasi() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($prestasi_list) ?>;
    const headers = ['Judul','Tingkat','Tahun','Mahasiswa','Juara','Lomba','Prodi'];
    const rows = data.map(d => [
        d.judul || '', d.tingkat || '', d.tahun || '',
        d.mahasiswa || '', d.juara || '', d.lomba || '', d.prodi_nama || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'prestasi-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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
function openPrestasiModal(p) {
    const tingkat = p.tingkat || 'Nasional';
    const icon = tingkat === 'Internasional' ? '🌍' : (tingkat === 'Nasional' ? '🇮🇩' : (tingkat === 'Wilayah' ? '🏛️' : '🏫'));
    const badgeClass = 'badge-' + tingkat.toLowerCase();

    let info_items = '';
    if (p.tahun) info_items += `<div class="modal-info-item"><div class="modal-info-label">📅 Tahun</div><div class="modal-info-value">${escapeHtml(p.tahun)}</div></div>`;
    if (p.tingkat) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏆 Tingkat</div><div class="modal-info-value">${escapeHtml(p.tingkat)}</div></div>`;
    if (p.mahasiswa) info_items += `<div class="modal-info-item"><div class="modal-info-label">👤 Mahasiswa</div><div class="modal-info-value">${escapeHtml(p.mahasiswa)}</div></div>`;
    if (p.juara) info_items += `<div class="modal-info-item"><div class="modal-info-label">🥇 Juara</div><div class="modal-info-value">${escapeHtml(p.juara)}</div></div>`;
    if (p.prodi_nama) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🎓 Program Studi</div><div class="modal-info-value">${escapeHtml(p.prodi_nama)}</div></div>`;
    if (p.penyelenggara) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🏢 Penyelenggara</div><div class="modal-info-value">${escapeHtml(p.penyelenggara)}</div></div>`;
    if (p.tanggal) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📅 Tanggal</div><div class="modal-info-value">${new Date(p.tanggal).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'})}</div></div>`;
    if (p.kategori_lomba) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🏷️ Kategori</div><div class="modal-info-value">${escapeHtml(p.kategori_lomba)}</div></div>`;

    const html = `
        <button class="modal-close" onclick="closePrestasiModal()">✕</button>
        <div class="modal-header-prestasi">
            <div class="modal-header-content">
                <div class="modal-icon-box">${icon}</div>
                <h2 class="modal-title">${escapeHtml(p.judul)}</h2>
                <div class="modal-subtitle">
                    ${p.lomba ? '🏆 ' + escapeHtml(p.lomba) : 'Prestasi Mahasiswa FKIP UNIMOF'}
                </div>
                <div class="modal-meta-ext">
                    <span class="${badgeClass}" style="background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3);">${escapeHtml(p.tingkat || '-')}</span>
                    ${p.tahun ? `<span>📅 ${escapeHtml(p.tahun)}</span>` : ''}
                    ${p.juara ? `<span>🥇 ${escapeHtml(p.juara)}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-prestasi">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${p.deskripsi ? `
            <div class="modal-section">
                <h4>📝 Deskripsi Prestasi</h4>
                <p>${escapeHtml(p.deskripsi)}</p>
            </div>` : ''}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='sharePrestasi(${JSON.stringify(p).replace(/"/g, "&quot;")})'>🔗 Share</button>
                ${p.link_berita ? `<a href="${escapeHtml(p.link_berita)}" target="_blank" class="modal-btn secondary">📰 Berita</a>` : ''}
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn primary" onclick="closePrestasiModal()">Tutup</button>
            </div>
        </div>
    `;
    document.getElementById('prestasiModalContent').innerHTML = html;
    document.getElementById('prestasiModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closePrestasiModal() {
    document.getElementById('prestasiModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function sharePrestasi(p) {
    const text = `🏆 ${p.judul}\n${p.tingkat ? '📍 Tingkat: ' + p.tingkat + '\n' : ''}${p.tahun ? '📅 Tahun: ' + p.tahun + '\n' : ''}${p.mahasiswa ? '👤 ' + p.mahasiswa + '\n' : ''}${p.juara ? '🥇 ' + p.juara + '\n' : ''}${p.prodi_nama ? '🎓 ' + p.prodi_nama + '\n' : ''}\nHall of Fame FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: p.judul, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info prestasi disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const tingkatColors = {
    'Internasional': '#92400e', 'Nasional': '#1e40af',
    'Wilayah': '#166534', 'Universitas': '#64748b'
};

if (Object.keys(tingkatDist).length > 0) {
    new ApexCharts(document.querySelector("#tingkatChart"), {
        series: Object.values(tingkatDist),
        labels: Object.keys(tingkatDist),
        chart: { type: 'donut', height: 320 },
        colors: Object.keys(tingkatDist).map(k => tingkatColors[k] || '#6b7280'),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(tingkatDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(tahunDist).length > 0) {
    new ApexCharts(document.querySelector("#yearChart"), {
        series: [{ name: 'Prestasi', data: Object.values(tahunDist) }],
        chart: { type: 'area', height: 320, toolbar: { show: false } },
        colors: ['#f59e0b'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(tahunDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}

if (Object.keys(prodiDist).length > 0) {
    new ApexCharts(document.querySelector("#prodiChart"), {
        series: [{ data: Object.values(prodiDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#f59e0b'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(prodiDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('prestasiSearch')?.focus();
    }
    if (e.key === 'Escape') closePrestasiModal();
});

console.log('%c🏆 Prestasi FKIP UNIMOF - EXTREME MULTIMATE', 'color:#f59e0b;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>