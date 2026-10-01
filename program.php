<?php
require_once __DIR__ . '/includes/config.php';

// ===== SCHEMA-SAFE: deteksi kolom program_studi =====
$prodi_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `program_studi`")->fetchAll(PDO::FETCH_COLUMN);
    $prodi_cols = [
        'nama'               => in_array('nama', $cols, true),
        'singkatan'          => in_array('singkatan', $cols, true),
        'jenjang'            => in_array('jenjang', $cols, true),
        'akreditasi'         => in_array('akreditasi', $cols, true),
        'deskripsi'          => in_array('deskripsi', $cols, true),
        'visi'               => in_array('visi', $cols, true),
        'misi'               => in_array('misi', $cols, true),
        'kurikulum'          => in_array('kurikulum', $cols, true),
        'prospek_kerja'      => in_array('prospek_kerja', $cols, true),
        'logo'               => in_array('logo', $cols, true),
        'banner'             => in_array('banner', $cols, true),
        'ketua_prodi'        => in_array('ketua_prodi', $cols, true),
        'kode'               => in_array('kode', $cols, true),
        'jumlah_dosen'       => in_array('jumlah_dosen', $cols, true),
        'jumlah_mahasiswa'   => in_array('jumlah_mahasiswa', $cols, true),
        'status'             => in_array('status', $cols, true),
        'urutan'             => in_array('urutan', $cols, true),
        'updated_at'         => in_array('updated_at', $cols, true),
        'website'            => in_array('website', $cols, true),
        'email'              => in_array('email', $cols, true),
    ];
} catch (Exception $e) {
    $prodi_cols = array_fill_keys(['nama','singkatan','jenjang','akreditasi','deskripsi','visi','misi','kurikulum','prospek_kerja','logo','banner','ketua_prodi','kode','jumlah_dosen','jumlah_mahasiswa','status','urutan','updated_at','website','email'], false);
}

// ===== AMBIL DATA =====
$where = $prodi_cols['status'] ? "WHERE status = 'Aktif'" : "WHERE 1=1";
$order_by = ($prodi_cols['urutan'] ? "urutan ASC, " : "") . "id ASC";
$stmt = $pdo->query("SELECT * FROM program_studi $where ORDER BY $order_by");
$prodi = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($prodi);
$stat_mahasiswa = 0;
$stat_dosen = 0;
$jenjang_dist = [];
$akreditasi_dist = [];

foreach ($prodi as $p) {
    if ($prodi_cols['jumlah_mahasiswa'] && !empty($p['jumlah_mahasiswa'])) {
        $stat_mahasiswa += (int)$p['jumlah_mahasiswa'];
    }
    if ($prodi_cols['jumlah_dosen'] && !empty($p['jumlah_dosen'])) {
        $stat_dosen += (int)$p['jumlah_dosen'];
    }
    if ($prodi_cols['jenjang'] && !empty($p['jenjang'])) {
        $jenjang_dist[$p['jenjang']] = ($jenjang_dist[$p['jenjang']] ?? 0) + 1;
    }
    if ($prodi_cols['akreditasi'] && !empty($p['akreditasi'])) {
        $akreditasi_dist[$p['akreditasi']] = ($akreditasi_dist[$p['akreditasi']] ?? 0) + 1;
    }
}

$stat_akreditasi_pct = $stat_total > 0 ? round(($stat_total / max(1, $stat_total)) * 100) : 0;

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_jenjang = $_GET['jenjang'] ?? 'all';
$filter_akreditasi = $_GET['akreditasi'] ?? 'all';
$sort = $_GET['sort'] ?? 'default';
$view = $_GET['view'] ?? 'grid';

$jenjang_list = array_keys($jenjang_dist);
sort($jenjang_list);
$akreditasi_list = array_keys($akreditasi_dist);
sort($akreditasi_list);

$page_title = 'Program Studi';
$page_description = count($prodi) . ' Program Studi unggulan FKIP UNIMOF dengan akreditasi terbaik';

$icons = ['🧮', '⚛️', '🧬', '🧪', '🌍', '📚', '💰', '⚖️', '🎓', '🔬', '📐', '🎨', '🎭', '🖥️', '🏛️'];

$prodi_colors = [
    0 => ['#3b82f6', '#1d4ed8'], 1 => ['#8b5cf6', '#6d28d9'],
    2 => ['#10b981', '#059669'], 3 => ['#f59e0b', '#d97706'],
    4 => ['#ec4899', '#db2777'], 5 => ['#ef4444', '#dc2626'],
    6 => ['#14b8a6', '#0d9488'], 7 => ['#f97316', '#ea580c'],
    8 => ['#06b6d4', '#0891b2'], 9 => ['#84cc16', '#65a30d'],
];

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.program-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, #0a6847 0%, #084d35 50%, #16213e 100%);
    color: white; padding: 8rem 0 5rem;
}
.program-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.2) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(16,185,129,0.15) 0%, transparent 60%);
    animation: auroraShift 20s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.1); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.program-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px; pointer-events: none;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 4px; height: 4px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: particleFloat 20s infinite linear;
}
@keyframes particleFloat {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.program-hero .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.program-hero .breadcrumb { justify-content: center; }
.program-hero .breadcrumb a, .program-hero .breadcrumb span { color: rgba(255,255,255,0.75); }
.program-hero .breadcrumb a:hover { color: white; }

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
.stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -3rem 0 3rem; position: relative; z-index: 3;
}
.stat-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-md); transition: all 0.4s; position: relative; overflow: hidden;
}
.stat-item::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-item:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); border-color: var(--stat-color, var(--primary)); }
.stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.stat-number {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.filter-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.search-wrap { flex: 1; min-width: 240px; position: relative; }
.search-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.search-input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.search-input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.search-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.search-clear:hover { background: #fee2e2; color: #dc2626; }

.prodi-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.prodi-select:focus { outline: none; border-color: var(--primary); }

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
.view-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

.compare-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    font-size: 0.82rem; font-weight: 700; cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
    border: none;
}
.compare-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
.compare-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.compare-btn .compare-count {
    background: rgba(255,255,255,0.3); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 18px;
}

.export-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: var(--bg-secondary); border: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.export-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* ===== FILTER PILLS ===== */
.filter-pills-row {
    display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;
}
.filter-pills-group {
    display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center;
    padding: 0.4rem; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.filter-pills-group-label {
    font-size: 0.72rem; color: var(--text-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em; padding: 0 0.5rem;
}
.filter-pill {
    padding: 0.45rem 0.85rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.78rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem;
}
.filter-pill:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.filter-pill.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.45rem;
    border-radius: 999px; font-size: 0.65rem; font-weight: 800; min-width: 18px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.programs-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.75rem;
}
.program-card {
    position: relative; background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; overflow: hidden; cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column;
}
.program-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--card-color-1), var(--card-color-2));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.program-card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 20px 40px rgba(0,0,0,0.15); border-color: var(--card-color-1); }
.program-card:hover::before { transform: scaleX(1); }

.program-card.compare-selected { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.2); }

.compare-checkbox {
    position: absolute; top: 1rem; right: 1rem; z-index: 5;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--bg-secondary); border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s; font-size: 0.85rem;
}
.compare-checkbox:hover { border-color: #f59e0b; }
.compare-checkbox.checked {
    background: #f59e0b; border-color: #f59e0b; color: white;
}

.program-number {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--bg-tertiary); line-height: 1; margin-bottom: 1rem; transition: color 0.3s;
}
.program-card:hover .program-number {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.program-icon { font-size: 3rem; margin-bottom: 1rem; transition: transform 0.3s; }
.program-card:hover .program-icon { transform: scale(1.2) rotate(-10deg); }
.program-content { flex: 1; display: flex; flex-direction: column; }

.program-badge {
    display: inline-block; padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.85rem; width: fit-content;
}
.badge-unggul { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.badge-baik-sekali { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
.badge-baik { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.badge-terakreditasi { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }

.program-title {
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;
    margin-bottom: 0.4rem; line-height: 1.3; color: var(--text-primary); transition: color 0.3s;
}
.program-card:hover .program-title { color: var(--card-color-1); }
.program-level { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.85rem; font-weight: 600; }

.program-desc {
    font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;
    margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em;
}

.program-stats {
    display: flex; gap: 1rem; margin-bottom: 1.25rem; padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.program-stat { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-secondary); }
.program-stat strong { font-size: 1.05rem; color: var(--text-primary); font-weight: 800; }

.program-footer {
    display: flex; gap: 0.5rem; margin-top: auto; flex-wrap: wrap;
}
.program-link {
    flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.65rem 1rem; background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    color: white; font-weight: 700; font-size: 0.85rem; text-decoration: none;
    border-radius: var(--radius-md); transition: all 0.3s;
}
.program-link:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.program-link svg { transition: transform 0.3s; }
.program-link:hover svg { transform: translateX(3px); }
.program-action-btn {
    padding: 0.65rem 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); cursor: pointer;
    font-size: 0.85rem; transition: all 0.2s; display: inline-flex; align-items: center;
}
.program-action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== LIST VIEW ===== */
.programs-list { display: flex; flex-direction: column; gap: 1rem; }
.program-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer; position: relative;
}
.program-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.program-list-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; flex-shrink: 0;
}
.program-list-content h3 {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 0.3rem;
    color: var(--text-primary);
}
.program-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary); margin-bottom: 0.35rem;
}
.program-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.program-list-desc {
    font-size: 0.82rem; color: var(--text-muted);
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.program-list-actions { display: flex; gap: 0.4rem; flex-direction: column; }

/* ===== COMPARE VIEW ===== */
.compare-view { margin-top: 2rem; }
.compare-empty {
    text-align: center; padding: 3rem 2rem; background: var(--bg-secondary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.compare-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow-x: auto; box-shadow: var(--shadow-sm);
}
.compare-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.compare-table th, .compare-table td {
    padding: 1rem 1.25rem; text-align: left; vertical-align: top;
    border-bottom: 1px solid var(--border);
}
.compare-table th {
    background: var(--bg-secondary); font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
    position: sticky; top: 0; z-index: 5;
}
.compare-table th.compare-prodi-head {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    color: white; text-align: center; min-width: 200px;
}
.compare-prodi-head-content { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
.compare-prodi-icon {
    width: 50px; height: 50px; border-radius: 12px; background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.compare-table td:first-child {
    font-weight: 700; color: var(--text-primary); background: var(--bg-secondary);
    min-width: 180px;
}
.compare-table td { font-size: 0.9rem; color: var(--text-secondary); }
.compare-remove-btn {
    background: #fee2e2; color: #dc2626; border: none; width: 28px; height: 28px;
    border-radius: 50%; cursor: pointer; font-size: 0.85rem; transition: all 0.2s;
    display: inline-flex; align-items: center; justify-content: center;
}
.compare-remove-btn:hover { background: #dc2626; color: white; }

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
.program-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #064e34 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.program-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.program-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY ===== */
.empty-state-pro {
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
.modal-content-prodi {
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
.modal-header-prodi {
    padding: 2.5rem 2rem; text-align: center; color: white; position: relative;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-prodi::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-icon-box {
    width: 80px; height: 80px; border-radius: 20px; margin: 0 auto 1.25rem;
    background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; border: 3px solid rgba(255,255,255,0.3);
}
.modal-title {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.92rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-prodi { padding: 2rem; }
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

.modal-section { margin-bottom: 1.5rem; }
.modal-section h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }
.modal-section ul { padding-left: 1.5rem; }
.modal-section li { margin-bottom: 0.5rem; color: var(--text-secondary); line-height: 1.6; }

.prospek-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem;
}
.prospek-item {
    padding: 0.65rem 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.85rem; color: var(--text-primary); font-weight: 600;
    display: flex; align-items: center; gap: 0.5rem;
}
.prospek-item::before { content: '✓'; color: var(--primary); font-weight: 800; }

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
.modal-btn.primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
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
    .filter-pills-row { flex-direction: column; }
}
@media (max-width: 768px) {
    .program-hero { padding: 7rem 0 4rem; }
    .stats-bar { grid-template-columns: 1fr 1fr; margin: -2rem 1rem 2rem; }
    .filter-section { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .programs-grid { grid-template-columns: 1fr; }
    .program-list-item { grid-template-columns: 1fr; text-align: center; }
    .program-list-icon { margin: 0 auto; }
    .program-list-actions { flex-direction: row; justify-content: center; }
    .modal-info-grid { grid-template-columns: 1fr; }
    .compare-btn { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="program-hero">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Program Studi</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>🎓 <?= count($prodi) ?> Program Studi Terakreditasi</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Pilih Jalur
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Pendidikanmu</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Temukan program studi yang sesuai dengan passion dan tujuan karirmu. Semua program kami terakreditasi dan siap membentukmu menjadi pendidik profesional.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">🎓 <?= $stat_total ?> Prodi</span>
            <span class="trust-pill">👨‍🎓 <?= number_format($stat_mahasiswa) ?> Mahasiswa</span>
            <span class="trust-pill">👨‍🏫 <?= $stat_dosen ?> Dosen</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="stats-bar" data-aos="fade-up">
            <div class="stat-item" style="--stat-color: #10b981;">
                <div class="stat-icon">🎓</div>
                <div class="stat-number count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="stat-label">Program Studi</div>
            </div>
            <div class="stat-item" style="--stat-color: #3b82f6;">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-number count-up" data-target="<?= $stat_mahasiswa ?>">0</div>
                <div class="stat-label">Mahasiswa Aktif</div>
            </div>
            <div class="stat-item" style="--stat-color: #f59e0b;">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-number count-up" data-target="<?= $stat_dosen ?>">0</div>
                <div class="stat-label">Dosen Berkualitas</div>
            </div>
            <div class="stat-item" style="--stat-color: #8b5cf6;">
                <div class="stat-icon">🏆</div>
                <div class="stat-number"><?= $stat_akreditasi_pct ?>%</div>
                <div class="stat-label">Terakreditasi</div>
            </div>
        </div>

        <?php if (empty($prodi)): ?>
            <div class="empty-state-pro" data-aos="fade-up">
                <div class="empty-icon-lg">📚</div>
                <h3>Belum Ada Program Studi</h3>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">Data program studi sedang dalam proses input.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="filter-section" data-aos="fade-up">
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari program studi..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="program.php?jenjang=<?= urlencode($filter_jenjang) ?>&akreditasi=<?= urlencode($filter_akreditasi) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>" class="search-clear" title="Clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="prodi-select" id="sortSelect">
                    <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>🔥 Default</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                    <option value="mahasiswa" <?= $sort === 'mahasiswa' ? 'selected' : '' ?>>👨‍🎓 Mahasiswa Terbanyak</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'compare' ? 'active' : '' ?>" onclick="switchView('compare')">⚖️ Compare</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
                </div>
                <button class="compare-btn" id="compareBtn" onclick="showCompare()" disabled>
                    ⚖️ Compare <span class="compare-count" id="compareCount">0</span>
                </button>
                <a href="#" onclick="exportProdi(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills-row" data-aos="fade-up">
                <div class="filter-pills-group">
                    <span class="filter-pills-group-label">Jenjang:</span>
                    <a class="filter-pill <?= $filter_jenjang === 'all' ? 'active' : '' ?>" href="program.php?jenjang=all&akreditasi=<?= urlencode($filter_akreditasi) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                        🎓 Semua
                    </a>
                    <?php foreach ($jenjang_list as $j): ?>
                    <a class="filter-pill <?= $filter_jenjang === $j ? 'active' : '' ?>" href="program.php?jenjang=<?= urlencode($j) ?>&akreditasi=<?= urlencode($filter_akreditasi) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                        <?= sanitize($j) ?> <span class="pill-count"><?= $jenjang_dist[$j] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="filter-pills-group">
                    <span class="filter-pills-group-label">Akreditasi:</span>
                    <a class="filter-pill <?= $filter_akreditasi === 'all' ? 'active' : '' ?>" href="program.php?jenjang=<?= urlencode($filter_jenjang) ?>&akreditasi=all&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                        🏆 Semua
                    </a>
                    <?php foreach ($akreditasi_list as $a): ?>
                    <a class="filter-pill <?= $filter_akreditasi === $a ? 'active' : '' ?>" href="program.php?jenjang=<?= urlencode($filter_jenjang) ?>&akreditasi=<?= urlencode($a) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                        <?= sanitize($a) ?> <span class="pill-count"><?= $akreditasi_dist[$a] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="programs-grid" data-aos="fade-up">
                <?php foreach ($prodi as $i => $p):
                    $colors = $prodi_colors[$i % count($prodi_colors)];
                    $badge_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi'));
                    $icon = $icons[$i % count($icons)] ?? '🎓';
                ?>
                <article class="program-card"
                    data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 100 ?>"
                    data-id="<?= $p['id'] ?>"
                    data-nama="<?= strtolower(sanitize($p['nama'])) ?>"
                    data-jenjang="<?= sanitize($p['jenjang'] ?? '') ?>"
                    data-akreditasi="<?= sanitize($p['akreditasi'] ?? '') ?>"
                    data-json='<?= htmlspecialchars(json_encode(array_merge($p, ['icon' => $icon, 'color1' => $colors[0], 'color2' => $colors[1]])), ENT_QUOTES, "UTF-8") ?>'
                    style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;">

                    <div class="compare-checkbox" onclick="event.stopPropagation(); toggleCompare(<?= $p['id'] ?>, this)" title="Tambah ke perbandingan">
                        <span class="check-icon"></span>
                    </div>

                    <div class="program-number"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="program-icon"><?= $icon ?></div>

                    <div class="program-content">
                        <div class="program-badge <?= $badge_class ?>"><?= sanitize($p['akreditasi'] ?? 'Terakreditasi') ?></div>
                        <h3 class="program-title"><?= sanitize($p['nama']) ?></h3>
                        <p class="program-level"><?= sanitize($p['jenjang'] ?? '') ?> • <?= sanitize($p['singkatan'] ?? '') ?></p>

                        <?php if (!empty($p['deskripsi'])): ?>
                        <p class="program-desc"><?= sanitize($p['deskripsi']) ?></p>
                        <?php endif; ?>

                        <div class="program-stats">
                            <?php if ($prodi_cols['jumlah_dosen'] && !empty($p['jumlah_dosen'])): ?>
                            <div class="program-stat">
                                <span>👨‍🏫</span>
                                <strong><?= (int)$p['jumlah_dosen'] ?></strong>
                                <span>Dosen</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($prodi_cols['jumlah_mahasiswa'] && !empty($p['jumlah_mahasiswa'])): ?>
                            <div class="program-stat">
                                <span>👨‍🎓</span>
                                <strong><?= (int)$p['jumlah_mahasiswa'] ?></strong>
                                <span>Mahasiswa</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="program-footer" onclick="event.stopPropagation();">
                            <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" class="program-link">
                                Detail Program
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                            <button class="program-action-btn" onclick='openProdiModal(<?= htmlspecialchars(json_encode(array_merge($p, ["icon"=>$icon,"color1"=>$colors[0],"color2"=>$colors[1]])), ENT_QUOTES, "UTF-8") ?>)' title="Quick View">👁️</button>
                            <button class="program-action-btn" onclick='shareProdi(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)' title="Share">🔗</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="programs-list" data-aos="fade-up">
                <?php foreach ($prodi as $i => $p):
                    $colors = $prodi_colors[$i % count($prodi_colors)];
                    $icon = $icons[$i % count($icons)] ?? '🎓';
                ?>
                <div class="program-list-item" onclick="window.location='<?= base_url('program-detail.php?id=' . $p['id']) ?>'" style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;">
                    <div class="program-list-icon"><?= $icon ?></div>
                    <div class="program-list-content">
                        <h3><?= sanitize($p['nama']) ?></h3>
                        <div class="program-list-meta">
                            <span class="program-badge badge-<?= strtolower(str_replace(' ', '-', $p['akreditasi'] ?? 'terakreditasi')) ?>" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;"><?= sanitize($p['akreditasi'] ?? '-') ?></span>
                            <span>🎓 <?= sanitize($p['jenjang'] ?? '-') ?></span>
                            <?php if ($prodi_cols['jumlah_dosen'] && !empty($p['jumlah_dosen'])): ?>
                                <span>👨‍🏫 <?= (int)$p['jumlah_dosen'] ?> Dosen</span>
                            <?php endif; ?>
                            <?php if ($prodi_cols['jumlah_mahasiswa'] && !empty($p['jumlah_mahasiswa'])): ?>
                                <span>👨‍🎓 <?= (int)$p['jumlah_mahasiswa'] ?> Mhs</span>
                            <?php endif; ?>
                        </div>
                        <p class="program-list-desc"><?= sanitize($p['deskripsi'] ?? '') ?></p>
                    </div>
                    <div class="program-list-actions" onclick="event.stopPropagation();">
                        <button class="program-action-btn" onclick='shareProdi(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <button class="program-action-btn" onclick='openProdiModal(<?= htmlspecialchars(json_encode(array_merge($p, ["icon"=>$icon,"color1"=>$colors[0],"color2"=>$colors[1]])), ENT_QUOTES, "UTF-8") ?>)'>👁️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== COMPARE VIEW ===== -->
            <?php if ($view === 'compare'): ?>
            <div class="compare-view" data-aos="fade-up">
                <div style="background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-lg); padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                    <span style="font-size:1.25rem;">💡</span>
                    <div style="flex:1; min-width:200px;">
                        <div style="font-weight:700; margin-bottom:0.2rem;">Bandingkan Program Studi</div>
                        <div style="font-size:0.82rem; color:var(--text-muted);">Klik checkbox pada card di view Grid untuk memilih hingga 3 prodi yang ingin dibandingkan, atau pilih langsung di bawah ini.</div>
                    </div>
                </div>

                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
                    <?php foreach ($prodi as $i => $p):
                        $colors = $prodi_colors[$i % count($prodi_colors)];
                        $icon = $icons[$i % count($icons)] ?? '🎓';
                    ?>
                    <button class="filter-pill compare-select-pill" data-id="<?= $p['id'] ?>" style="--pill-color: <?= $colors[0] ?>;" onclick="toggleComparePill(<?= $p['id'] ?>, this)">
                        <?= $icon ?> <?= sanitize($p['singkatan'] ?? $p['nama']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div id="compareContent">
                    <div class="compare-empty">
                        <div class="empty-icon-lg">⚖️</div>
                        <h3>Pilih 2-3 Program Studi</h3>
                        <p style="color: var(--text-muted); margin-top: 0.5rem;">Klik tombol di atas untuk memilih program studi yang ingin dibandingkan.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🎓 Distribusi Jenjang</h3>
                    <div id="jenjangChart"></div>
                </div>
                <div class="chart-card">
                    <h3>🏆 Distribusi Akreditasi</h3>
                    <div id="akreditasiChart"></div>
                </div>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>👨‍🎓 Jumlah Mahasiswa per Prodi</h3>
                    <div id="mahasiswaChart"></div>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="program-cta" data-aos="zoom-in">
            <div class="program-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Masih Bingung Memilih?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Tim admisi kami siap membantu Anda menemukan program studi yang paling sesuai dengan minat dan bakat Anda.
                </p>
                <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                        💬 Konsultasi Gratis
                    </a>
                    <a href="<?= base_url('pmb.php') ?>" class="btn btn-lg" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); font-weight: 800;">
                        📝 Daftar Sekarang
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="prodiModal" onclick="if(event.target===this)closeProdiModal()">
    <div class="modal-content-prodi" id="prodiModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const prodiCols = <?= json_encode($prodi_cols) ?>;
const jenjangDist = <?= json_encode($jenjang_dist) ?>;
const akreditasiDist = <?= json_encode($akreditasi_dist) ?>;
const prodiData = <?= json_encode($prodi) ?>;
const prodiColors = <?= json_encode($prodi_colors) ?>;
const prodiIcons = <?= json_encode($icons) ?>;

let compareList = [];
const COMPARE_MAX = 3;

// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 20 + 's';
        p.style.animationDuration = (15 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
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
document.getElementById('searchInput')?.addEventListener('input', function() {
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

// ===== EXPORT CSV =====
function exportProdi() {
    pubToast('Menyiapkan export...', '📥');
    const headers = ['Nama','Singkatan','Jenjang','Akreditasi','Jumlah Dosen','Jumlah Mahasiswa','Ketua Prodi'];
    const rows = prodiData.map(d => [
        d.nama || '', d.singkatan || '', d.jenjang || '', d.akreditasi || '',
        d.jumlah_dosen || '', d.jumlah_mahasiswa || '', d.ketua_prodi || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'program-studi-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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

// ===== COMPARE =====
function toggleCompare(id, el) {
    const idx = compareList.indexOf(id);
    if (idx > -1) {
        compareList.splice(idx, 1);
        el.classList.remove('checked');
        el.querySelector('.check-icon').textContent = '';
    } else {
        if (compareList.length >= COMPARE_MAX) {
            pubToast('Maksimal ' + COMPARE_MAX + ' prodi untuk dibandingkan', '⚠️');
            return;
        }
        compareList.push(id);
        el.classList.add('checked');
        el.querySelector('.check-icon').textContent = '✓';
    }
    updateCompareUI();
}

function toggleComparePill(id, el) {
    const idx = compareList.indexOf(id);
    if (idx > -1) {
        compareList.splice(idx, 1);
        el.classList.remove('active');
    } else {
        if (compareList.length >= COMPARE_MAX) {
            pubToast('Maksimal ' + COMPARE_MAX + ' prodi', '⚠️');
            return;
        }
        compareList.push(id);
        el.classList.add('active');
    }
    updateCompareUI();
    renderCompareTable();
}

function updateCompareUI() {
    const btn = document.getElementById('compareBtn');
    const count = document.getElementById('compareCount');
    if (btn) {
        btn.disabled = compareList.length < 2;
    }
    if (count) count.textContent = compareList.length;

    document.querySelectorAll('.program-card').forEach(card => {
        const id = parseInt(card.dataset.id);
        card.classList.toggle('compare-selected', compareList.includes(id));
    });
}

function showCompare() {
    if (compareList.length < 2) {
        pubToast('Pilih minimal 2 prodi', '⚠️');
        return;
    }
    switchView('compare');
}

function renderCompareTable() {
    const container = document.getElementById('compareContent');
    if (!container) return;

    if (compareList.length < 2) {
        container.innerHTML = `
            <div class="compare-empty">
                <div class="empty-icon-lg">⚖️</div>
                <h3>Pilih 2-3 Program Studi</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Klik tombol di atas untuk memilih program studi yang ingin dibandingkan.</p>
            </div>
        `;
        return;
    }

    const selected = compareList.map(id => prodiData.find(p => p.id == id)).filter(Boolean);

    const headerCells = selected.map((p, i) => {
        const colors = prodiColors[i % prodiColors.length];
        const icon = prodiIcons[i % prodiIcons.length];
        return `
            <th class="compare-prodi-head" style="--card-color-1: ${colors[0]}; --card-color-2: ${colors[1]};">
                <div class="compare-prodi-head-content">
                    <div class="compare-prodi-icon">${icon}</div>
                    <div style="font-weight:800;">${escapeHtml(p.singkatan || p.nama)}</div>
                    <button class="compare-remove-btn" onclick="removeCompare(${p.id})" title="Hapus">✕</button>
                </div>
            </th>
        `;
    }).join('');

    const rows = [
        { label: 'Nama Lengkap', key: 'nama' },
        { label: 'Jenjang', key: 'jenjang' },
        { label: 'Akreditasi', key: 'akreditasi' },
        { label: 'Kode Prodi', key: 'kode' },
        { label: 'Ketua Prodi', key: 'ketua_prodi' },
        { label: 'Jumlah Dosen', key: 'jumlah_dosen', suffix: ' orang' },
        { label: 'Jumlah Mahasiswa', key: 'jumlah_mahasiswa', suffix: ' orang' },
    ];

    const bodyRows = rows.map(row => {
        const cells = selected.map(p => {
            let val = p[row.key] ?? '-';
            if (row.suffix && val !== '-') val = val + row.suffix;
            return `<td>${escapeHtml(String(val))}</td>`;
        }).join('');
        return `<tr><td>${row.label}</td>${cells}</tr>`;
    }).join('');

    const descRow = `<tr><td>Deskripsi</td>${selected.map(p => `<td style="font-size:0.82rem;">${escapeHtml((p.deskripsi || '').substring(0, 120))}${(p.deskripsi||'').length > 120 ? '...' : ''}</td>`).join('')}</tr>`;

    const actionRow = `<tr><td>Aksi</td>${selected.map(p => `<td><a href="<?= base_url('program-detail.php?id=') ?>${p.id}" class="modal-btn primary" style="font-size:0.78rem; padding:0.5rem 0.85rem;">Lihat Detail →</a></td>`).join('')}</tr>`;

    container.innerHTML = `
        <div class="compare-table-wrap">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th>Atribut</th>
                        ${headerCells}
                    </tr>
                </thead>
                <tbody>
                    ${bodyRows}
                    ${descRow}
                    ${actionRow}
                </tbody>
            </table>
        </div>
    `;
}

function removeCompare(id) {
    const idx = compareList.indexOf(id);
    if (idx > -1) compareList.splice(idx, 1);
    document.querySelectorAll('.compare-select-pill').forEach(el => {
        if (parseInt(el.dataset.id) === id) el.classList.remove('active');
    });
    updateCompareUI();
    renderCompareTable();
}

// Auto-render compare table if in compare view
<?php if ($view === 'compare'): ?>
document.addEventListener('DOMContentLoaded', () => { renderCompareTable(); });
<?php endif; ?>

// ===== MODAL =====
function openProdiModal(p) {
    // Info grid
    let info_items = '';
    if (p.jenjang) info_items += `<div class="modal-info-item"><div class="modal-info-label">🎓 Jenjang</div><div class="modal-info-value">${escapeHtml(p.jenjang)}</div></div>`;
    if (p.akreditasi) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏆 Akreditasi</div><div class="modal-info-value">${escapeHtml(p.akreditasi)}</div></div>`;
    if (p.kode) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Kode</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(p.kode)}</div></div>`;
    if (p.ketua_prodi) info_items += `<div class="modal-info-item"><div class="modal-info-label">👤 Ketua Prodi</div><div class="modal-info-value">${escapeHtml(p.ketua_prodi)}</div></div>`;
    if (p.jumlah_dosen) info_items += `<div class="modal-info-item"><div class="modal-info-label">👨‍🏫 Dosen</div><div class="modal-info-value">${Number(p.jumlah_dosen).toLocaleString('id-ID')} orang</div></div>`;
    if (p.jumlah_mahasiswa) info_items += `<div class="modal-info-item"><div class="modal-info-label">👨‍🎓 Mahasiswa</div><div class="modal-info-value">${Number(p.jumlah_mahasiswa).toLocaleString('id-ID')} orang</div></div>`;
    if (p.website) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">🌐 Website</div><div class="modal-info-value"><a href="${escapeHtml(p.website)}" target="_blank">${escapeHtml(p.website)}</a></div></div>`;
    if (p.email) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📧 Email</div><div class="modal-info-value"><a href="mailto:${escapeHtml(p.email)}">${escapeHtml(p.email)}</a></div></div>`;

    // Visi
    let visi_html = '';
    if (p.visi) {
        visi_html = `
            <div class="modal-section">
                <h4>🎯 Visi</h4>
                <p style="font-style:italic; border-left:3px solid ${p.color1}; padding-left:1rem;">${escapeHtml(p.visi)}</p>
            </div>
        `;
    }

    // Misi
    let misi_html = '';
    if (p.misi) {
        const misiLines = p.misi.split('\n').filter(l => l.trim());
        if (misiLines.length > 0) {
            misi_html = `
                <div class="modal-section">
                    <h4>🚀 Misi</h4>
                    <ul>${misiLines.map(l => `<li>${escapeHtml(l.replace(/^[\d\.\)\-\*]+\s*/, ''))}</li>`).join('')}</ul>
                </div>
            `;
        }
    }

    // Prospek
    let prospek_html = '';
    if (p.prospek_kerja) {
        const prospekList = p.prospek_kerja.split('\n').filter(l => l.trim());
        if (prospekList.length > 0) {
            prospek_html = `
                <div class="modal-section">
                    <h4>💼 Prospek Karir</h4>
                    <div class="prospek-grid">
                        ${prospekList.map(pr => `<div class="prospek-item">${escapeHtml(pr.replace(/^[\-\*•]\s*/, ''))}</div>`).join('')}
                    </div>
                </div>
            `;
        }
    }

    const html = `
        <button class="modal-close" onclick="closeProdiModal()">✕</button>
        <div class="modal-header-prodi" style="background: linear-gradient(135deg, ${p.color1}, ${p.color2});">
            <div class="modal-header-content">
                <div class="modal-icon-box">${p.icon || '🎓'}</div>
                <h2 class="modal-title">${escapeHtml(p.nama)}</h2>
                <div class="modal-subtitle">${escapeHtml(p.jenjang || '')} • ${escapeHtml(p.singkatan || '')}</div>
                <div class="modal-meta-ext">
                    ${p.akreditasi ? `<span>🏆 ${escapeHtml(p.akreditasi)}</span>` : ''}
                    ${p.kode ? `<span>🏷️ ${escapeHtml(p.kode)}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-prodi">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${p.deskripsi ? `
            <div class="modal-section">
                <h4>📝 Deskripsi</h4>
                <p>${escapeHtml(p.deskripsi)}</p>
            </div>` : ''}

            ${visi_html}
            ${misi_html}
            ${prospek_html}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareProdi(${JSON.stringify(p).replace(/"/g, "&quot;")})'>🔗 Share</button>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="closeProdiModal()">Tutup</button>
                <a href="<?= base_url('program-detail.php?id=') ?>${p.id}" class="modal-btn primary">📖 Detail Lengkap</a>
            </div>
        </div>
    `;
    document.getElementById('prodiModalContent').innerHTML = html;
    document.getElementById('prodiModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeProdiModal() {
    document.getElementById('prodiModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareProdi(p) {
    const url = window.location.origin + '<?= base_url('program-detail.php?id=') ?>' + p.id;
    const text = `🎓 ${p.nama}\n${p.jenjang ? '📚 Jenjang: ' + p.jenjang + '\n' : ''}${p.akreditasi ? '🏆 Akreditasi: ' + p.akreditasi + '\n' : ''}${p.singkatan ? '🏷️ ' + p.singkatan + '\n' : ''}\nFKIP UNIMOF - Program Studi`;
    if (navigator.share) {
        navigator.share({ title: p.nama, text, url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + '\n' + url);
        pubToast('Info prodi disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const jenjangColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
const akreditasiColors = {
    'Unggul': '#f59e0b', 'Baik Sekali': '#3b82f6', 'Baik': '#10b981',
    'C': '#6b7280', 'Terakreditasi': '#8b5cf6'
};

if (Object.keys(jenjangDist).length > 0) {
    new ApexCharts(document.querySelector("#jenjangChart"), {
        series: Object.values(jenjangDist),
        labels: Object.keys(jenjangDist),
        chart: { type: 'donut', height: 300 },
        colors: jenjangColors.slice(0, Object.keys(jenjangDist).length),
        plotOptions: {
            pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(jenjangDist).reduce((a,b)=>a+b,0) } } } }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(akreditasiDist).length > 0) {
    new ApexCharts(document.querySelector("#akreditasiChart"), {
        series: Object.values(akreditasiDist),
        labels: Object.keys(akreditasiDist),
        chart: { type: 'pie', height: 300 },
        colors: Object.keys(akreditasiDist).map(k => akreditasiColors[k] || '#6b7280'),
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

// Mahasiswa per prodi
const mhsData = prodiData
    .filter(p => p.jumlah_mahasiswa > 0)
    .sort((a,b) => b.jumlah_mahasiswa - a.jumlah_mahasiswa)
    .slice(0, 10);

if (mhsData.length > 0) {
    new ApexCharts(document.querySelector("#mahasiswaChart"), {
        series: [{ name: 'Mahasiswa', data: mhsData.map(p => p.jumlah_mahasiswa) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#10b981'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: mhsData.map(p => p.singkatan || p.nama), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== CARD CLICK → MODAL =====
document.querySelectorAll('.program-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.closest('a, button, .compare-checkbox')) return;
        try {
            const data = JSON.parse(this.dataset.json);
            openProdiModal(data);
        } catch (err) { console.error(err); }
    });
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();
    }
    if (e.key === 'Escape') closeProdiModal();
});

console.log('%c🎓 Program Studi FKIP UNIMOF - EXTREME MULTIMATE', 'color:#10b981;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>