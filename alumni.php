<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Alumni Sukses';
$page_description = 'Jejaring alumni FKIP UNIMOF yang berkarir gemilang di berbagai bidang pendidikan, bisnis, dan pemerintahan.';

// ===== SCHEMA-SAFE: deteksi kolom alumni =====
$alumni_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `alumni`")->fetchAll(PDO::FETCH_COLUMN);
    $alumni_cols = [
        'foto'              => in_array('foto', $cols, true),
        'linkedin'          => in_array('linkedin', $cols, true),
        'perusahaan'        => in_array('perusahaan', $cols, true),
        'lokasi'            => in_array('lokasi', $cols, true),
        'testimoni'         => in_array('testimoni', $cols, true),
        'prestasi'          => in_array('prestasi', $cols, true),
        'email'             => in_array('email', $cols, true),
        'telepon'           => in_array('telepon', $cols, true),
    ];
} catch (Exception $e) {
    $alumni_cols = array_fill_keys(['foto','linkedin','perusahaan','lokasi','testimoni','prestasi','email','telepon'], false);
}

// ===== AMBIL DATA ALUMNI =====
$stmt = $pdo->query("SELECT a.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status = 'Aktif' ORDER BY a.tahun_lulus DESC, a.nama ASC");
$alumni_list = $stmt->fetchAll();

// ===== STATISTIK ALUMNI =====
$stat_total = count($alumni_list);
$stat_bekerja = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND (pekerjaan LIKE '%Bekerja%' OR pekerjaan LIKE '%Guru%' OR pekerjaan LIKE '%Dosen%' OR pekerjaan LIKE '%PNS%')")->fetchColumn();
$stat_wirausaha = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND (pekerjaan LIKE '%Wirausaha%' OR pekerjaan LIKE '%Founder%' OR pekerjaan LIKE '%CEO%')")->fetchColumn();
$stat_lanjut = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND pekerjaan LIKE '%Lanjut Studi%'")->fetchColumn();

// Distribusi profesi
$job_dist = [];
foreach ($alumni_list as $a) {
    $job = $a['pekerjaan'] ?? 'Lainnya';
    if (stripos($job, 'Guru') !== false) $job = 'Guru';
    elseif (stripos($job, 'Dosen') !== false) $job = 'Dosen';
    elseif (stripos($job, 'PNS') !== false) $job = 'PNS';
    elseif (stripos($job, 'Wirausaha') !== false || stripos($job, 'Founder') !== false) $job = 'Wirausaha';
    elseif (stripos($job, 'Lanjut Studi') !== false) $job = 'Lanjut Studi';
    else $job = 'Lainnya';
    $job_dist[$job] = ($job_dist[$job] ?? 0) + 1;
}

// Distribusi tahun lulus
$year_dist = [];
foreach ($alumni_list as $a) {
    $y = $a['tahun_lulus'] ?? 'Unknown';
    $year_dist[$y] = ($year_dist[$y] ?? 0) + 1;
}
ksort($year_dist);

// Distribusi prodi
$prodi_dist = [];
foreach ($alumni_list as $a) {
    $p = $a['prodi_singkatan'] ?? $a['prodi_nama'] ?? 'Lainnya';
    $prodi_dist[$p] = ($prodi_dist[$p] ?? 0) + 1;
}

// ===== TESTIMONI =====
$testimoni_list = [];
if ($alumni_cols['testimoni']) {
    try {
        $stmtTesti = $pdo->query("SELECT a.*, p.nama as prodi_nama FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status='Aktif' AND a.testimoni IS NOT NULL AND a.testimoni != '' ORDER BY RAND() LIMIT 3");
        $testimoni_list = $stmtTesti->fetchAll();
    } catch (Exception $e) {}
}

// ===== ALUMNI SPOTLIGHT (dengan prestasi) =====
$spotlight_alumni = [];
if ($alumni_cols['prestasi']) {
    try {
        $stmtSpot = $pdo->query("SELECT a.*, p.nama as prodi_nama FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status='Aktif' AND a.prestasi IS NOT NULL AND a.prestasi != '' ORDER BY a.tahun_lulus DESC LIMIT 1");
        $spotlight_alumni = $stmtSpot->fetch();
    } catch (Exception $e) {}
}

// Tahun unik untuk filter
$years = array_unique(array_filter(array_column($alumni_list, 'tahun_lulus')));
rsort($years);

// Filter params
$search = trim($_GET['q'] ?? '');
$year_filter = $_GET['year'] ?? 'all';
$job_filter = $_GET['job'] ?? 'all';
$prodi_filter = $_GET['prodi'] ?? 'all';
$view = $_GET['view'] ?? 'grid';

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.alumni-hero-extreme {
    position: relative; background: linear-gradient(135deg, #059669 0%, #0a6847 40%, #064e34 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.alumni-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.15) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-20px, 20px) scale(1.05); } }
.alumni-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px; background: rgba(255,255,255,0.6);
    border-radius: 50%; animation: floatParticle 30s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.hero-content-extreme { position: relative; z-index: 2; max-width: 900px; margin: 0 auto; text-align: center; }
.alumni-hero-extreme .breadcrumb a, .alumni-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.alumni-hero-extreme .breadcrumb a:hover { color: white; }

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
.alumni-stats-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem 0 4rem; position: relative; z-index: 10;
}
.alumni-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.alumni-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.alumni-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.alumni-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.alumni-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
    font-variant-numeric: tabular-nums;
}
.alumni-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SPOTLIGHT ===== */
.spotlight-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: auto 1fr; gap: 2rem;
    align-items: center; position: relative; overflow: hidden;
}
.spotlight-section::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706);
}
.spotlight-badge {
    position: absolute; top: 1.5rem; right: 1.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.spotlight-avatar {
    width: 140px; height: 140px; border-radius: 50%;
    background: linear-gradient(135deg, #059669, #0a6847);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 3.5rem; font-weight: 800; flex-shrink: 0;
    box-shadow: 0 10px 30px rgba(5,150,105,0.3); border: 4px solid white;
}
.spotlight-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.spotlight-info h2 {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 800;
    margin-bottom: 0.5rem; color: var(--text-primary);
}
.spotlight-meta {
    display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
    font-size: 0.88rem; color: var(--text-secondary);
}
.spotlight-meta span { display: flex; align-items: center; gap: 0.4rem; }
.spotlight-achievement {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d; border-radius: var(--radius-md);
    padding: 1rem 1.25rem; margin-top: 1rem; position: relative;
}
.spotlight-achievement::before {
    content: '🏆'; position: absolute; left: -1rem; top: 50%; transform: translateY(-50%);
    font-size: 2rem;
}
.spotlight-achievement-label {
    font-size: 0.72rem; color: #92400e; text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.35rem;
}
.spotlight-achievement-text {
    font-size: 0.95rem; color: #78350f; font-weight: 600; line-height: 1.6;
}

/* ===== TESTIMONI CAROUSEL ===== */
.testimoni-section { margin-bottom: 4rem; }
.testimoni-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}
.testimoni-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    transition: all 0.4s; display: flex; flex-direction: column;
}
.testimoni-card::before {
    content: '❝'; position: absolute; top: 1rem; right: 1.5rem;
    font-size: 4rem; color: var(--primary); opacity: 0.1; font-family: Georgia, serif;
}
.testimoni-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--primary-light); }
.testimoni-text {
    font-size: 1rem; line-height: 1.8; color: var(--text-secondary);
    font-style: italic; margin-bottom: 1.5rem; flex: 1; position: relative; z-index: 2;
}
.testimoni-author { display: flex; align-items: center; gap: 1rem; }
.testimoni-avatar {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.25rem; flex-shrink: 0; overflow: hidden;
}
.testimoni-avatar img { width: 100%; height: 100%; object-fit: cover; }
.testimoni-info strong { display: block; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.2rem; }
.testimoni-info span { font-size: 0.82rem; color: var(--text-muted); }

/* ===== TOOLBAR ===== */
.alumni-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.alumni-search { flex: 1; min-width: 240px; position: relative; }
.alumni-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.alumni-search input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.alumni-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.alumni-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.alumni-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.alumni-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.alumni-select:focus { outline: none; border-color: var(--primary); }

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
.view-btn.active { background: linear-gradient(135deg, #059669, #0a6847); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.export-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: var(--bg-secondary); border: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.export-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

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
    background: linear-gradient(135deg, #059669, #0a6847);
    color: white; border-color: #059669;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.alumni-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}
.alumni-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    transition: all 0.4s; position: relative; overflow: hidden; cursor: pointer;
}
.alumni-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--accent, #f59e0b));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.alumni-card-extreme:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary);
}
.alumni-card-extreme:hover::before { transform: scaleX(1); }

.alumni-avatar-ext {
    width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; box-shadow: 0 8px 20px rgba(10,104,71,0.25);
    border: 4px solid var(--bg-primary); overflow: hidden;
}
.alumni-avatar-ext img { width: 100%; height: 100%; object-fit: cover; }
.alumni-card-extreme h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-primary); }
.alumni-year-ext { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem; font-weight: 600; }
.alumni-prodi-ext {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    background: var(--bg-secondary); border-radius: 999px; font-size: 0.85rem;
    color: var(--text-secondary); font-weight: 600; margin-bottom: 1rem;
}
.alumni-job-ext {
    font-size: 0.95rem; color: var(--primary); font-weight: 700;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    margin-bottom: 1rem;
}
.alumni-company {
    font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.5rem;
}
.alumni-location {
    font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1rem;
}
.alumni-prestasi-ext {
    font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;
    padding-top: 1rem; border-top: 1px solid var(--border); font-style: italic;
}
.alumni-actions {
    display: flex; gap: 0.4rem; justify-content: center; margin-top: 1rem;
}
.alumni-action-btn {
    padding: 0.4rem 0.85rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
    background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border);
    text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem;
    transition: all 0.2s; cursor: pointer;
}
.alumni-action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* ===== LIST VIEW ===== */
.alumni-list {
    display: flex; flex-direction: column; gap: 1rem;
}
.alumni-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.alumni-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: var(--primary-light); }
.alumni-list-avatar {
    width: 70px; height: 70px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; flex-shrink: 0; overflow: hidden;
}
.alumni-list-avatar img { width: 100%; height: 100%; object-fit: cover; }
.alumni-list-info h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem; }
.alumni-list-meta {
    display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem;
    color: var(--text-secondary); margin-bottom: 0.5rem;
}
.alumni-list-meta span { display: flex; align-items: center; gap: 0.35rem; }
.alumni-list-actions { display: flex; gap: 0.4rem; }

/* ===== TIMELINE VIEW ===== */
.timeline-view { max-width: 900px; margin: 0 auto; }
.timeline-year-group { margin-bottom: 3rem; }
.timeline-year-label {
    position: sticky; top: 80px; z-index: 5; background: var(--bg-primary);
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;
    padding: 0.75rem 1rem; border-radius: var(--radius-md);
    border: 2px solid var(--primary); box-shadow: var(--shadow-md);
}
.timeline-year-label h3 {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800;
    color: var(--primary); margin: 0;
}
.timeline-year-label .year-count {
    background: var(--primary); color: white;
    padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.78rem;
    font-weight: 700;
}
.timeline-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.timeline-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 1.25rem; transition: all 0.3s;
    cursor: pointer; display: flex; gap: 1rem; align-items: center;
}
.timeline-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--primary-light); }
.timeline-item-avatar {
    width: 50px; height: 50px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; flex-shrink: 0; overflow: hidden;
}
.timeline-item-avatar img { width: 100%; height: 100%; object-fit: cover; }
.timeline-item-info { flex: 1; min-width: 0; }
.timeline-item-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.2rem; }
.timeline-item-meta { font-size: 0.78rem; color: var(--text-muted); }

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

/* ===== EMPTY STATE ===== */
.empty-state-premium {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg {
    font-size: 5rem; margin-bottom: 1rem; opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* ===== CTA ===== */
.alumni-cta-extreme {
    background: linear-gradient(135deg, var(--primary) 0%, #f59e0b 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.alumni-cta-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.2) 0%, transparent 50%);
}
.alumni-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-alumni {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.modal-header-alumni {
    padding: 2.5rem 2rem; position: relative; overflow: hidden;
    background: linear-gradient(135deg, #059669, #0a6847);
    color: white; text-align: center; border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-alumni::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
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
.modal-avatar {
    width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 1.25rem;
    background: white; color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem; font-weight: 900; position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 4px solid white;
    overflow: hidden;
}
.modal-avatar img { width: 100%; height: 100%; object-fit: cover; }
.modal-name {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    margin-bottom: 0.35rem; position: relative;
}
.modal-subtitle { font-size: 0.95rem; opacity: 0.9; position: relative; }

.modal-body { padding: 2rem; }
.modal-info-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem; margin-bottom: 1.5rem;
}
.modal-info-item {
    padding: 1rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
}
.modal-info-label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.3rem;
}
.modal-info-value {
    font-size: 0.95rem; color: var(--text-primary); font-weight: 600;
}

.modal-testimoni {
    margin-top: 1.25rem; padding: 1.25rem;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #86efac; border-radius: var(--radius-md);
    position: relative;
}
[data-theme="dark"] .modal-testimoni {
    background: linear-gradient(135deg, #052e1633, #14532d33);
    border-color: #166534;
}
.modal-testimoni::before {
    content: '❝'; position: absolute; top: -0.5rem; left: 1rem;
    font-size: 3rem; color: var(--primary); opacity: 0.2; font-family: Georgia, serif;
}
.modal-testimoni-label {
    font-size: 0.72rem; color: var(--primary); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.5rem;
}
.modal-testimoni-text {
    font-size: 0.95rem; line-height: 1.7; color: var(--text-secondary);
    font-style: italic; position: relative; z-index: 2;
}

.modal-footer {
    padding: 1rem 2rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;
    background: var(--bg-secondary);
}
.modal-btn {
    padding: 0.65rem 1.15rem; border-radius: 8px; border: none;
    font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    transition: all 0.2s;
}
.modal-btn.primary { background: linear-gradient(135deg, #059669, #0a6847); color: white; }
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
    .spotlight-avatar { margin: 0 auto; }
}
@media (max-width: 768px) {
    .alumni-hero-extreme { padding: 8rem 0 5rem; }
    .alumni-stats-extreme { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .alumni-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .alumni-grid-extreme { grid-template-columns: 1fr; }
    .alumni-list-item { grid-template-columns: 1fr; text-align: center; }
    .alumni-list-avatar { margin: 0 auto; }
    .alumni-list-actions { justify-content: center; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .alumni-stats-extreme { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="alumni-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container hero-content-extreme">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Alumni Sukses</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Hall of <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Fame Alumni</span> 🎓
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Lulusan FKIP UNIMOF yang telah berkarir gemilang, menjadi pemimpin, dan menginspirasi generasi berikutnya di seluruh Indonesia.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="200">
            <span class="trust-pill">🎓 <?= $stat_total ?>+ Alumni</span>
            <span class="trust-pill">💼 Tersebar di <?= count($prodi_dist) ?> Prodi</span>
            <span class="trust-pill">🏆 Prestasi Nasional</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats Bar -->
        <div class="alumni-stats-extreme" data-aos="fade-up">
            <div class="alumni-stat-card" style="--stat-color: #3b82f6;">
                <div class="alumni-stat-icon">🎓</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="alumni-stat-label">Total Alumni</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #10b981;">
                <div class="alumni-stat-icon">💼</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_bekerja ?>">0</div>
                <div class="alumni-stat-label">Bekerja</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #f59e0b;">
                <div class="alumni-stat-icon">🚀</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_wirausaha ?>">0</div>
                <div class="alumni-stat-label">Wirausaha</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #8b5cf6;">
                <div class="alumni-stat-icon">📚</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_lanjut ?>">0</div>
                <div class="alumni-stat-label">Lanjut Studi</div>
            </div>
        </div>

        <!-- Spotlight Alumni -->
        <?php if ($spotlight_alumni): ?>
        <div class="spotlight-section" data-aos="fade-up">
            <span class="spotlight-badge">⭐ Alumni Spotlight</span>
            <div class="spotlight-avatar">
                <?php if ($alumni_cols['foto'] && !empty($spotlight_alumni['foto'])): ?>
                    <img src="<?= asset('alumni/' . basename($spotlight_alumni['foto'])) ?>" alt="<?= sanitize($spotlight_alumni['nama']) ?>">
                <?php else: ?>
                    <?= strtoupper(substr($spotlight_alumni['nama'], 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="spotlight-info">
                <h2><?= sanitize($spotlight_alumni['nama']) ?></h2>
                <div class="spotlight-meta">
                    <span>🎓 Angkatan <?= sanitize($spotlight_alumni['tahun_lulus']) ?></span>
                    <span>📚 <?= sanitize($spotlight_alumni['prodi_nama'] ?? 'FKIP UNIMOF') ?></span>
                    <span>💼 <?= sanitize($spotlight_alumni['pekerjaan'] ?? 'Profesional') ?></span>
                    <?php if ($alumni_cols['perusahaan'] && !empty($spotlight_alumni['perusahaan'])): ?>
                        <span>🏢 <?= sanitize($spotlight_alumni['perusahaan']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($alumni_cols['prestasi'] && !empty($spotlight_alumni['prestasi'])): ?>
                <div class="spotlight-achievement">
                    <div class="spotlight-achievement-label">🏆 Prestasi & Pencapaian</div>
                    <div class="spotlight-achievement-text"><?= sanitize($spotlight_alumni['prestasi']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Testimoni Section -->
        <?php if (!empty($testimoni_list)): ?>
        <div class="testimoni-section" data-aos="fade-up">
            <div class="section-header" style="text-align: center; margin-bottom: 2.5rem;">
                <span class="section-tag">Cerita Sukses</span>
                <h2 class="section-title">Kata <span class="gradient-text">Mereka</span></h2>
                <p style="color: var(--text-muted); max-width: 600px; margin: 0.5rem auto 0;">Testimoni dari alumni yang telah merasakan manfaat pendidikan di FKIP UNIMOF</p>
            </div>
            <div class="testimoni-grid">
                <?php foreach ($testimoni_list as $t):
                    $initials = strtoupper(substr($t['nama'], 0, 1) . (strpos($t['nama'], ' ') ? substr($t['nama'], strpos($t['nama'], ' ') + 1, 1) : ''));
                ?>
                <div class="testimoni-card">
                    <p class="testimoni-text">"<?= sanitize($t['testimoni']) ?>"</p>
                    <div class="testimoni-author">
                        <div class="testimoni-avatar">
                            <?php if ($alumni_cols['foto'] && !empty($t['foto'])): ?>
                                <img src="<?= asset('alumni/' . basename($t['foto'])) ?>" alt="<?= sanitize($t['nama']) ?>">
                            <?php else: ?>
                                <?= $initials ?>
                            <?php endif; ?>
                        </div>
                        <div class="testimoni-info">
                            <strong><?= sanitize($t['nama']) ?></strong>
                            <span>Alumni <?= sanitize($t['tahun_lulus']) ?> • <?= sanitize($t['prodi_nama'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="alumni-toolbar" data-aos="fade-up">
            <div class="alumni-search">
                <span class="s-icon">🔍</span>
                <input type="text" id="alumniSearch" placeholder="Cari nama alumni..." value="<?= sanitize($search) ?>">
                <?php if ($search !== ''): ?>
                    <a href="alumni.php?year=<?= urlencode($year_filter) ?>&job=<?= urlencode($job_filter) ?>&prodi=<?= urlencode($prodi_filter) ?>&view=<?= urlencode($view) ?>" class="s-clear" title="Clear">✕</a>
                <?php endif; ?>
            </div>
            <select class="alumni-select" id="yearFilter">
                <option value="all">📅 Semua Angkatan</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year_filter === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <select class="alumni-select" id="jobFilter">
                <option value="all">💼 Semua Profesi</option>
                <?php foreach ($job_dist as $job => $cnt): ?>
                    <option value="<?= urlencode($job) ?>" <?= $job_filter === $job ? 'selected' : '' ?>><?= $job ?> (<?= $cnt ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="view-toggle">
                <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📊 Timeline</button>
                <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📈 Chart</button>
            </div>
            <a href="#" onclick="exportAlumni(); return false;" class="export-btn">📥 Export</a>
        </div>

        <!-- Filter Pills (by Prodi) -->
        <div class="filter-pills" data-aos="fade-up">
            <a class="filter-pill <?= $prodi_filter === 'all' ? 'active' : '' ?>" href="alumni.php?prodi=all&year=<?= urlencode($year_filter) ?>&job=<?= urlencode($job_filter) ?>&view=<?= urlencode($view) ?>">
                🎓 Semua Prodi <span class="pill-count"><?= $stat_total ?></span>
            </a>
            <?php foreach ($prodi_dist as $prodi => $cnt): ?>
            <a class="filter-pill <?= $prodi_filter === $prodi ? 'active' : '' ?>" href="alumni.php?prodi=<?= urlencode($prodi) ?>&year=<?= urlencode($year_filter) ?>&job=<?= urlencode($job_filter) ?>&view=<?= urlencode($view) ?>">
                <?= sanitize($prodi) ?> <span class="pill-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($alumni_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🎓</div>
                <h3>Belum ada data alumni</h3>
                <p>Direktori alumni akan segera diperbarui.</p>
            </div>
        <?php endif; ?>

        <!-- ===== GRID VIEW ===== -->
        <?php if ($view === 'grid' && !empty($alumni_list)): ?>
        <div class="alumni-grid-extreme" data-aos="fade-up">
            <?php foreach ($alumni_list as $a):
                $initials = strtoupper(substr($a['nama'], 0, 1) . (strpos($a['nama'], ' ') ? substr($a['nama'], strpos($a['nama'], ' ') + 1, 1) : ''));
                $job_icons = ['Bekerja' => '💼', 'Wirausaha' => '🚀', 'Lanjut Studi' => '📚', 'Guru' => '👨‍🏫', 'Dosen' => '🎓', 'PNS' => '🏛️'];
                $job_icon = '⭐';
                foreach ($job_icons as $key => $icon) {
                    if (stripos($a['pekerjaan'] ?? '', $key) !== false) {
                        $job_icon = $icon;
                        break;
                    }
                }
            ?>
            <div class="alumni-card-extreme" onclick='showAlumniDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                <div class="alumni-avatar-ext">
                    <?php if ($alumni_cols['foto'] && !empty($a['foto'])): ?>
                        <img src="<?= asset('alumni/' . basename($a['foto'])) ?>" alt="<?= sanitize($a['nama']) ?>">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <h3><?= sanitize($a['nama']) ?></h3>
                <div class="alumni-year-ext">Angkatan <?= sanitize($a['tahun_lulus']) ?></div>
                <div class="alumni-prodi-ext">🎓 <?= sanitize($a['prodi_singkatan'] ?? $a['prodi_nama'] ?? 'Umum') ?></div>
                <div class="alumni-job-ext">
                    <span class="job-icon"><?= $job_icon ?></span>
                    <span><?= sanitize($a['pekerjaan'] ?? 'Profesional') ?></span>
                </div>
                <?php if ($alumni_cols['perusahaan'] && !empty($a['perusahaan'])): ?>
                    <div class="alumni-company">🏢 <?= sanitize($a['perusahaan']) ?></div>
                <?php endif; ?>
                <?php if ($alumni_cols['lokasi'] && !empty($a['lokasi'])): ?>
                    <div class="alumni-location">📍 <?= sanitize($a['lokasi']) ?></div>
                <?php endif; ?>
                <?php if ($alumni_cols['prestasi'] && !empty($a['prestasi'])): ?>
                    <p class="alumni-prestasi-ext">"<?= excerpt($a['prestasi'], 80) ?>"</p>
                <?php endif; ?>
                <div class="alumni-actions">
                    <button class="alumni-action-btn" onclick="event.stopPropagation(); showAlumniDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">👁️ Detail</button>
                    <button class="alumni-action-btn" onclick="event.stopPropagation(); shareAlumni(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">🔗 Share</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== LIST VIEW ===== -->
        <?php if ($view === 'list' && !empty($alumni_list)): ?>
        <div class="alumni-list" data-aos="fade-up">
            <?php foreach ($alumni_list as $a):
                $initials = strtoupper(substr($a['nama'], 0, 1) . (strpos($a['nama'], ' ') ? substr($a['nama'], strpos($a['nama'], ' ') + 1, 1) : ''));
            ?>
            <div class="alumni-list-item" onclick='showAlumniDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                <div class="alumni-list-avatar">
                    <?php if ($alumni_cols['foto'] && !empty($a['foto'])): ?>
                        <img src="<?= asset('alumni/' . basename($a['foto'])) ?>" alt="<?= sanitize($a['nama']) ?>">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <div class="alumni-list-info">
                    <h3><?= sanitize($a['nama']) ?></h3>
                    <div class="alumni-list-meta">
                        <span>🎓 Angkatan <?= sanitize($a['tahun_lulus']) ?></span>
                        <span>📚 <?= sanitize($a['prodi_singkatan'] ?? $a['prodi_nama'] ?? 'FKIP') ?></span>
                        <span>💼 <?= sanitize($a['pekerjaan'] ?? 'Profesional') ?></span>
                        <?php if ($alumni_cols['perusahaan'] && !empty($a['perusahaan'])): ?>
                            <span>🏢 <?= sanitize($a['perusahaan']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($alumni_cols['prestasi'] && !empty($a['prestasi'])): ?>
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">"<?= excerpt($a['prestasi'], 100) ?>"</div>
                    <?php endif; ?>
                </div>
                <div class="alumni-list-actions">
                    <button class="alumni-action-btn" onclick="event.stopPropagation(); shareAlumni(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">🔗 Share</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== TIMELINE VIEW ===== -->
        <?php if ($view === 'timeline' && !empty($alumni_list)): ?>
        <div class="timeline-view" data-aos="fade-up">
            <?php
            $grouped = [];
            foreach ($alumni_list as $a) {
                $y = $a['tahun_lulus'] ?? 'Unknown';
                $grouped[$y][] = $a;
            }
            krsort($grouped);
            foreach ($grouped as $year => $items):
            ?>
            <div class="timeline-year-group">
                <div class="timeline-year-label">
                    <h3>🎓 Angkatan <?= $year ?></h3>
                    <span class="year-count"><?= count($items) ?> alumni</span>
                </div>
                <div class="timeline-items">
                    <?php foreach ($items as $a):
                        $initials = strtoupper(substr($a['nama'], 0, 1) . (strpos($a['nama'], ' ') ? substr($a['nama'], strpos($a['nama'], ' ') + 1, 1) : ''));
                    ?>
                    <div class="timeline-item" onclick='showAlumniDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                        <div class="timeline-item-avatar">
                            <?php if ($alumni_cols['foto'] && !empty($a['foto'])): ?>
                                <img src="<?= asset('alumni/' . basename($a['foto'])) ?>" alt="<?= sanitize($a['nama']) ?>">
                            <?php else: ?>
                                <?= $initials ?>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-item-info">
                            <div class="timeline-item-name"><?= sanitize($a['nama']) ?></div>
                            <div class="timeline-item-meta">
                                💼 <?= sanitize($a['pekerjaan'] ?? 'Profesional') ?>
                                <?php if ($alumni_cols['perusahaan'] && !empty($a['perusahaan'])): ?>
                                    • 🏢 <?= sanitize($a['perusahaan']) ?>
                                <?php endif; ?>
                            </div>
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
                <h3>💼 Distribusi Profesi</h3>
                <div id="jobChart"></div>
            </div>
            <div class="chart-card">
                <h3>📅 Alumni per Tahun</h3>
                <div id="yearChart"></div>
            </div>
            <div class="chart-card">
                <h3>🎓 Alumni per Prodi</h3>
                <div id="prodiChart"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CTA Section -->
        <div class="alumni-cta-extreme" data-aos="zoom-in">
            <div class="alumni-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Apakah Anda Alumni FKIP UNIMOF?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Bergabunglah dengan jejaring alumni kami dan bagikan kisah sukses Anda untuk menginspirasi adik-adik tingkat. Mari tetap terhubung!
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📩 Daftarkan Diri Anda
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="alumniModal" onclick="if(event.target===this)closeAlumniModal()">
    <div class="modal-content-alumni" id="alumniModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const alumniCols = <?= json_encode($alumni_cols) ?>;
const jobDist = <?= json_encode($job_dist) ?>;
const yearDist = <?= json_encode($year_dist) ?>;
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
    const duration = 2000; const start = performance.now();
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
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== SEARCH & FILTER =====
let searchTimer;
document.getElementById('alumniSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

document.getElementById('yearFilter')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('year', this.value);
    window.location = url;
});

document.getElementById('jobFilter')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('job', this.value);
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== EXPORT CSV =====
function exportAlumni() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($alumni_list) ?>;
    const headers = ['Nama','Tahun Lulus','Prodi','Pekerjaan','Perusahaan','Lokasi'];
    const rows = data.map(d => [
        d.nama || '', d.tahun_lulus || '', d.prodi_nama || '',
        d.pekerjaan || '', d.perusahaan || '', d.lokasi || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'alumni-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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
function showAlumniDetail(a) {
    const initials = (a.nama || 'A').charAt(0).toUpperCase() + (a.nama.split(' ')[1] ? a.nama.split(' ')[1].charAt(0).toUpperCase() : '');
    
    const html = `
        <div class="modal-header-alumni">
            <button class="modal-close" onclick="closeAlumniModal()">✕</button>
            <div class="modal-avatar">
                ${alumniCols.foto && a.foto ? `<img src="${'<?= asset('alumni/') ?>' + encodeURIComponent(a.foto.split('/').pop())}" alt="${escapeHtml(a.nama)}">` : initials}
            </div>
            <h2 class="modal-name">${escapeHtml(a.nama)}</h2>
            <div class="modal-subtitle">Alumni ${escapeHtml(a.tahun_lulus)} • ${escapeHtml(a.prodi_nama || 'FKIP UNIMOF')}</div>
        </div>
        <div class="modal-body">
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <div class="modal-info-label">🎓 Tahun Lulus</div>
                    <div class="modal-info-value">${escapeHtml(a.tahun_lulus)}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">📚 Program Studi</div>
                    <div class="modal-info-value">${escapeHtml(a.prodi_singkatan || a.prodi_nama || '-')}</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">💼 Pekerjaan</div>
                    <div class="modal-info-value">${escapeHtml(a.pekerjaan || 'Profesional')}</div>
                </div>
                ${alumniCols.perusahaan && a.perusahaan ? `
                <div class="modal-info-item">
                    <div class="modal-info-label">🏢 Perusahaan</div>
                    <div class="modal-info-value">${escapeHtml(a.perusahaan)}</div>
                </div>` : ''}
                ${alumniCols.lokasi && a.lokasi ? `
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                    <div class="modal-info-label">📍 Lokasi</div>
                    <div class="modal-info-value">${escapeHtml(a.lokasi)}</div>
                </div>` : ''}
                ${alumniCols.linkedin && a.linkedin ? `
                <div class="modal-info-item" style="grid-column: 1 / -1;">
                    <div class="modal-info-label">🔗 LinkedIn</div>
                    <div class="modal-info-value"><a href="${escapeHtml(a.linkedin)}" target="_blank" style="color:var(--primary);">${escapeHtml(a.linkedin)}</a></div>
                </div>` : ''}
            </div>

            ${alumniCols.prestasi && a.prestasi ? `
            <div class="modal-testimoni">
                <div class="modal-testimoni-label">🏆 Prestasi & Pencapaian</div>
                <div class="modal-testimoni-text">${escapeHtml(a.prestasi)}</div>
            </div>` : ''}

            ${alumniCols.testimoni && a.testimoni ? `
            <div class="modal-testimoni" style="margin-top: 1rem;">
                <div class="modal-testimoni-label">💬 Testimoni</div>
                <div class="modal-testimoni-text">"${escapeHtml(a.testimoni)}"</div>
            </div>` : ''}
        </div>
        <div class="modal-footer">
            <button class="modal-btn secondary" onclick="shareAlumni(${JSON.stringify(a).replace(/"/g, '&quot;')})">🔗 Share</button>
            ${alumniCols.linkedin && a.linkedin ? `<a href="${escapeHtml(a.linkedin)}" target="_blank" class="modal-btn secondary">🔗 LinkedIn</a>` : ''}
            ${alumniCols.email && a.email ? `<a href="mailto:${escapeHtml(a.email)}" class="modal-btn secondary">✉️ Email</a>` : ''}
            <button class="modal-btn primary" onclick="closeAlumniModal()">Tutup</button>
        </div>
    `;
    document.getElementById('alumniModalContent').innerHTML = html;
    document.getElementById('alumniModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAlumniModal() {
    document.getElementById('alumniModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareAlumni(a) {
    const text = `🎓 ${a.nama}\n📚 Alumni ${a.tahun_lulus} - ${a.prodi_nama || 'FKIP UNIMOF'}\n💼 ${a.pekerjaan || 'Profesional'}${alumniCols.perusahaan && a.perusahaan ? '\n🏢 ' + a.perusahaan : ''}\n\nHall of Fame Alumni FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: a.nama, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info alumni disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const jobColors = {
    'Guru': '#10b981', 'Dosen': '#3b82f6', 'PNS': '#8b5cf6',
    'Wirausaha': '#f59e0b', 'Lanjut Studi': '#ec4899', 'Lainnya': '#6b7280'
};

if (Object.keys(jobDist).length > 0) {
    new ApexCharts(document.querySelector("#jobChart"), {
        series: Object.values(jobDist),
        labels: Object.keys(jobDist),
        chart: { type: 'donut', height: 300 },
        colors: Object.keys(jobDist).map(k => jobColors[k] || '#6b7280'),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(jobDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(yearDist).length > 0) {
    new ApexCharts(document.querySelector("#yearChart"), {
        series: [{ name: 'Alumni', data: Object.values(yearDist) }],
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        colors: ['#059669'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(yearDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        stroke: { curve: 'smooth', width: 3 }
    }).render();
}

if (Object.keys(prodiDist).length > 0) {
    new ApexCharts(document.querySelector("#prodiChart"), {
        series: [{ data: Object.values(prodiDist) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        colors: ['#0a6847'],
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
        document.getElementById('alumniSearch')?.focus();
    }
    if (e.key === 'Escape') closeAlumniModal();
});

console.log('%c🎓 Alumni FKIP UNIMOF - EXTREME MULTIMATE', 'color:#059669;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>