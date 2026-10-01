<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Jurnal Ilmiah';
$page_description = 'Portal jurnal ilmiah FKIP UNIMOF yang terakreditasi dan bereputasi di tingkat nasional maupun internasional.';

// ===== SCHEMA-SAFE: deteksi kolom jurnal =====
$jurnal_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `jurnal`")->fetchAll(PDO::FETCH_COLUMN);
    $jurnal_cols = [
        'nama'          => in_array('nama', $cols, true),
        'penerbit'      => in_array('penerbit', $cols, true),
        'issn'          => in_array('issn', $cols, true),
        'eissn'         => in_array('eissn', $cols, true),
        'akreditasi'    => in_array('akreditasi', $cols, true),
        'deskripsi'     => in_array('deskripsi', $cols, true),
        'url'           => in_array('url', $cols, true),
        'logo'          => in_array('logo', $cols, true),
        'frekuensi'     => in_array('frekuensi', $cols, true),
        'bahasa'        => in_array('bahasa', $cols, true),
        'fokus'         => in_array('fokus', $cols, true),
        'status'        => in_array('status', $cols, true),
        'editor'        => in_array('editor', $cols, true),
        'email'         => in_array('email', $cols, true),
        'created_at'    => in_array('created_at', $cols, true),
    ];
} catch (Exception $e) {
    $jurnal_cols = array_fill_keys(['nama','penerbit','issn','eissn','akreditasi','deskripsi','url','logo','frekuensi','bahasa','fokus','status','editor','email','created_at'], false);
}

// ===== AMBIL DATA =====
$where = $jurnal_cols['status'] ? "WHERE status = 'Aktif'" : "WHERE 1=1";
$order_by = $jurnal_cols['created_at'] ? "created_at DESC" : "id DESC";
$stmt = $pdo->query("SELECT * FROM jurnal $where ORDER BY $order_by");
$jurnal_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($jurnal_list);
$stat_url = 0; $stat_sinta = 0; $stat_scopus = 0;
$akreditasi_dist = [];
$bahasa_dist = [];
$frekuensi_dist = [];

foreach ($jurnal_list as $j) {
    if ($jurnal_cols['url'] && !empty($j['url'])) $stat_url++;
    if ($jurnal_cols['akreditasi'] && !empty($j['akreditasi'])) {
        $akr = $j['akreditasi'];
        if (stripos($akr, 'Scopus') !== false) $stat_scopus++;
        if (stripos($akr, 'Sinta') !== false || stripos($akr, 'SINTA') !== false) $stat_sinta++;
        $akreditasi_dist[$akr] = ($akreditasi_dist[$akr] ?? 0) + 1;
    }
    if ($jurnal_cols['bahasa'] && !empty($j['bahasa'])) {
        $bahasa_dist[$j['bahasa']] = ($bahasa_dist[$j['bahasa']] ?? 0) + 1;
    }
    if ($jurnal_cols['frekuensi'] && !empty($j['frekuensi'])) {
        $frekuensi_dist[$j['frekuensi']] = ($frekuensi_dist[$j['frekuensi']] ?? 0) + 1;
    }
}

arsort($akreditasi_dist);

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_akreditasi = $_GET['akreditasi'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$view = $_GET['view'] ?? 'grid';

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.jurnal-hero-extreme {
    position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.jurnal-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.jurnal-hero-extreme::after {
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
.jurnal-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.jurnal-hero-extreme .breadcrumb { justify-content: center; }
.jurnal-hero-extreme .breadcrumb a, .jurnal-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.jurnal-hero-extreme .breadcrumb a:hover { color: white; }

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
.jurnal-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.jurnal-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.jurnal-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.jurnal-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.jurnal-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.jurnal-stat-num {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.jurnal-stat-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== AKREDITASI BADGES ===== */
.akreditasi-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em;
}
.badge-scopus { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-sinta1, .badge-sinta2 { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-sinta3, .badge-sinta4 { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-sinta5, .badge-sinta6 { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }
.badge-default { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }

/* ===== TOOLBAR ===== */
.jurnal-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.jurnal-search { flex: 1; min-width: 240px; position: relative; }
.jurnal-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.jurnal-search input:focus { outline: none; border-color: #4c1d95; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(76,29,149,0.1); }
.jurnal-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.jurnal-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.jurnal-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.jurnal-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.jurnal-select:focus { outline: none; border-color: #4c1d95; }

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
.jurnal-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.jurnal-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer;
}
.jurnal-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.jurnal-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95; }
.jurnal-card:hover::before { transform: scaleX(1); }

.jurnal-logo-wrap {
    width: 80px; height: 80px; border-radius: 16px; background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem; margin-bottom: 1rem; overflow: hidden;
    border: 1px solid var(--border);
}
.jurnal-logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; }

.jurnal-card h3 {
    font-family: var(--font-display); font-size: 1.2rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3;
}
.jurnal-penerbit {
    font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.75rem; font-weight: 600;
}
.jurnal-desc {
    color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.jurnal-meta {
    display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border); font-size: 0.78rem; color: var(--text-muted);
    margin-bottom: 1rem;
}
.jurnal-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }
.jurnal-card-footer {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.btn-action {
    padding: 0.5rem 0.9rem; border-radius: 8px; font-size: 0.78rem;
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

/* ===== LIST VIEW ===== */
.jurnal-list { display: flex; flex-direction: column; gap: 1rem; }
.jurnal-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.jurnal-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #4c1d95; }
.jurnal-list-logo {
    width: 64px; height: 64px; border-radius: 14px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 2rem; flex-shrink: 0;
    border: 1px solid var(--border); overflow: hidden;
}
.jurnal-list-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.4rem; }
.jurnal-list-info h3 {
    font-size: 1.05rem; font-weight: 700; margin-bottom: 0.3rem;
    color: var(--text-primary);
}
.jurnal-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary);
}
.jurnal-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.jurnal-list-actions { display: flex; gap: 0.4rem; }

/* ===== TABLE VIEW ===== */
.jurnal-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm);
}
.jurnal-table { width: 100%; border-collapse: collapse; }
.jurnal-table th {
    background: var(--bg-secondary); padding: 1rem 1.25rem; text-align: left;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
}
.jurnal-table td {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    font-size: 0.88rem; color: var(--text-primary); vertical-align: middle;
}
.jurnal-table tr:last-child td { border-bottom: none; }
.jurnal-table tbody tr { transition: all 0.2s; cursor: pointer; }
.jurnal-table tbody tr:hover { background: rgba(76,29,149,0.03); }
.jurnal-table-cell-name {
    display: flex; align-items: center; gap: 0.75rem;
}
.jurnal-table-logo {
    width: 40px; height: 40px; border-radius: 10px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 1.25rem; flex-shrink: 0;
    border: 1px solid var(--border); overflow: hidden;
}
.jurnal-table-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.25rem; }

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
.jurnal-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #4c1d95 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.jurnal-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.jurnal-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

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
.modal-content-jurnal {
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
.modal-header-jurnal {
    padding: 2.5rem 2rem; color: white; position: relative;
    background: linear-gradient(135deg, #1e3a8a, #312e81, #4c1d95);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-jurnal::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; display: flex; gap: 1.5rem; align-items: center; }
.modal-logo {
    width: 100px; height: 100px; border-radius: 20px;
    background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem; border: 3px solid rgba(255,255,255,0.3);
    flex-shrink: 0; overflow: hidden;
}
.modal-logo img { width: 100%; height: 100%; object-fit: contain; padding: 0.75rem; }
.modal-title {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 900;
    margin-bottom: 0.35rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.9rem; opacity: 0.95; margin-bottom: 0.75rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-jurnal { padding: 2rem; }
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

.fokus-list {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem;
}
.fokus-item {
    padding: 0.65rem 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.85rem; color: var(--text-primary); font-weight: 600;
    display: flex; align-items: center; gap: 0.5rem;
}
.fokus-item::before { content: '✓'; color: #4c1d95; font-weight: 800; font-size: 1rem; }

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
    .modal-header-content { flex-direction: column; text-align: center; }
}
@media (max-width: 768px) {
    .jurnal-hero-extreme { padding: 8rem 0 5rem; }
    .jurnal-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .jurnal-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .jurnal-grid { grid-template-columns: 1fr; }
    .jurnal-list-item { grid-template-columns: 1fr; text-align: center; }
    .jurnal-list-logo { margin: 0 auto; }
    .jurnal-list-actions { justify-content: center; }
    .jurnal-table-wrap { overflow-x: auto; }
    .jurnal-table { min-width: 800px; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .jurnal-stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="jurnal-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Jurnal Ilmiah</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>📚 Research Excellence Center</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Portal
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Jurnal Ilmiah</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Kumpulan jurnal ilmiah FKIP UNIMOF yang terakreditasi dan bereputasi, menjadi wadah publikasi penelitian berkualitas dari dosen dan mahasiswa.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">📚 <?= $stat_total ?> Jurnal</span>
            <span class="trust-pill">🏆 <?= $stat_sinta ?> SINTA</span>
            <span class="trust-pill">🌍 <?= $stat_scopus ?> Scopus</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="jurnal-stats-bar" data-aos="fade-up">
            <div class="jurnal-stat-card" style="--stat-color: #3b82f6;">
                <div class="jurnal-stat-icon">📚</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="jurnal-stat-label">Total Jurnal</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #10b981;">
                <div class="jurnal-stat-icon">🔗</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_url ?>">0</div>
                <div class="jurnal-stat-label">Akses Online</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #f59e0b;">
                <div class="jurnal-stat-icon">🏆</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_sinta ?>">0</div>
                <div class="jurnal-stat-label">SINTA</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #8b5cf6;">
                <div class="jurnal-stat-icon">🌍</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_scopus ?>">0</div>
                <div class="jurnal-stat-label">Scopus</div>
            </div>
        </div>

        <?php if (empty($jurnal_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📚</div>
                <h3>Belum ada jurnal terdaftar</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Jurnal ilmiah akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="jurnal-toolbar" data-aos="fade-up">
                <div class="jurnal-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="jurnalSearch" placeholder="Cari nama jurnal, penerbit, atau ISSN..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="jurnal.php?akreditasi=<?= urlencode($filter_akreditasi) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>" class="s-clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="jurnal-select" id="sortSelect">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>🕐 Terbaru</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>🕐 Terlama</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>🔤 A-Z</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📊 Tabel</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📈 Chart</button>
                </div>
                <a href="#" onclick="exportJurnal(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_akreditasi === 'all' ? 'active' : '' ?>" href="jurnal.php?akreditasi=all&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    📚 Semua <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php foreach ($akreditasi_dist as $a => $cnt):
                    $akr_lower = strtolower($a);
                    $badge = 'badge-default';
                    if (stripos($akr_lower, 'scopus') !== false) $badge = 'badge-scopus';
                    elseif (in_array($akr_lower, ['sinta 1','sinta 2'])) $badge = 'badge-sinta1';
                    elseif (in_array($akr_lower, ['sinta 3','sinta 4'])) $badge = 'badge-sinta3';
                    elseif (in_array($akr_lower, ['sinta 5','sinta 6'])) $badge = 'badge-sinta5';
                ?>
                <a class="filter-pill <?= $filter_akreditasi === $a ? 'active' : '' ?>" href="jurnal.php?akreditasi=<?= urlencode($a) ?>&sort=<?= urlencode($sort) ?>&view=<?= urlencode($view) ?>">
                    <span class="akreditasi-badge <?= $badge ?>" style="padding:0.2rem 0.6rem; font-size:0.7rem;"><?= sanitize($a) ?></span>
                    <span class="pill-count"><?= $cnt ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="jurnal-grid" data-aos="fade-up">
                <?php foreach ($jurnal_list as $j):
                    $akr = strtolower($j['akreditasi'] ?? '');
                    $badge_class = 'badge-default';
                    if (stripos($akr, 'scopus') !== false) $badge_class = 'badge-scopus';
                    elseif (in_array($akr, ['sinta 1','sinta 2'])) $badge_class = 'badge-sinta1';
                    elseif (in_array($akr, ['sinta 3','sinta 4'])) $badge_class = 'badge-sinta3';
                    elseif (in_array($akr, ['sinta 5','sinta 6'])) $badge_class = 'badge-sinta5';
                ?>
                <article class="jurnal-card" onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="jurnal-logo-wrap">
                        <?php if ($jurnal_cols['logo'] && !empty($j['logo'])): ?>
                            <img src="<?= asset('uploads/jurnal/' . basename($j['logo'])) ?>" alt="">
                        <?php else: ?>
                            📖
                        <?php endif; ?>
                    </div>
                    <span class="akreditasi-badge <?= $badge_class ?>"><?= sanitize($j['akreditasi'] ?? 'Belum Terakreditasi') ?></span>
                    <h3><?= sanitize($j['nama']) ?></h3>
                    <div class="jurnal-penerbit">🏢 <?= sanitize($j['penerbit'] ?? 'FKIP UNIMOF') ?></div>
                    <p class="jurnal-desc"><?= sanitize(excerpt($j['deskripsi'] ?? 'Jurnal ilmiah FKIP UNIMOF.', 150)) ?></p>
                    <div class="jurnal-meta">
                        <?php if ($jurnal_cols['issn'] && !empty($j['issn'])): ?>
                            <span>🔖 ISSN: <?= sanitize($j['issn']) ?></span>
                        <?php endif; ?>
                        <?php if ($jurnal_cols['frekuensi'] && !empty($j['frekuensi'])): ?>
                            <span>📅 <?= sanitize($j['frekuensi']) ?></span>
                        <?php endif; ?>
                        <?php if ($jurnal_cols['bahasa'] && !empty($j['bahasa'])): ?>
                            <span>🌐 <?= sanitize($j['bahasa']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="jurnal-card-footer" onclick="event.stopPropagation();">
                        <button class="btn-action primary" onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>👁️ Detail</button>
                        <?php if ($jurnal_cols['url'] && !empty($j['url'])): ?>
                            <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-action primary" onclick="event.stopPropagation();">🔗 Website</a>
                        <?php endif; ?>
                        <button class="btn-action" onclick='shareJurnal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="jurnal-list" data-aos="fade-up">
                <?php foreach ($jurnal_list as $j):
                    $akr = strtolower($j['akreditasi'] ?? '');
                    $badge_class = 'badge-default';
                    if (stripos($akr, 'scopus') !== false) $badge_class = 'badge-scopus';
                    elseif (in_array($akr, ['sinta 1','sinta 2'])) $badge_class = 'badge-sinta1';
                    elseif (in_array($akr, ['sinta 3','sinta 4'])) $badge_class = 'badge-sinta3';
                    elseif (in_array($akr, ['sinta 5','sinta 6'])) $badge_class = 'badge-sinta5';
                ?>
                <div class="jurnal-list-item" onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="jurnal-list-logo">
                        <?php if ($jurnal_cols['logo'] && !empty($j['logo'])): ?>
                            <img src="<?= asset('uploads/jurnal/' . basename($j['logo'])) ?>" alt="">
                        <?php else: ?>
                            📖
                        <?php endif; ?>
                    </div>
                    <div class="jurnal-list-info">
                        <h3><?= sanitize($j['nama']) ?></h3>
                        <div class="jurnal-list-meta">
                            <span class="akreditasi-badge <?= $badge_class ?>" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;">
                                <?= sanitize($j['akreditasi'] ?? '-') ?>
                            </span>
                            <span>🏢 <?= sanitize($j['penerbit'] ?? '-') ?></span>
                            <?php if ($jurnal_cols['issn'] && !empty($j['issn'])): ?>
                                <span>🔖 <?= sanitize($j['issn']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="jurnal-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='shareJurnal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                        <?php if ($jurnal_cols['url'] && !empty($j['url'])): ?>
                            <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-action primary" onclick="event.stopPropagation();">🌐</a>
                        <?php endif; ?>
                        <button class="btn-action primary" onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>👁️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== TABLE VIEW ===== -->
            <?php if ($view === 'table'): ?>
            <div class="jurnal-table-wrap" data-aos="fade-up">
                <table class="jurnal-table">
                    <thead>
                        <tr>
                            <th>Nama Jurnal</th>
                            <th>Penerbit</th>
                            <th>Akreditasi</th>
                            <th>ISSN</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jurnal_list as $j):
                            $akr = strtolower($j['akreditasi'] ?? '');
                            $badge_class = 'badge-default';
                            if (stripos($akr, 'scopus') !== false) $badge_class = 'badge-scopus';
                            elseif (in_array($akr, ['sinta 1','sinta 2'])) $badge_class = 'badge-sinta1';
                            elseif (in_array($akr, ['sinta 3','sinta 4'])) $badge_class = 'badge-sinta3';
                            elseif (in_array($akr, ['sinta 5','sinta 6'])) $badge_class = 'badge-sinta5';
                        ?>
                        <tr onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>
                            <td>
                                <div class="jurnal-table-cell-name">
                                    <div class="jurnal-table-logo">
                                        <?php if ($jurnal_cols['logo'] && !empty($j['logo'])): ?>
                                            <img src="<?= asset('uploads/jurnal/' . basename($j['logo'])) ?>" alt="">
                                        <?php else: ?>
                                            📖
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;"><?= sanitize($j['nama']) ?></div>
                                        <?php if ($jurnal_cols['deskripsi'] && !empty($j['deskripsi'])): ?>
                                            <div style="font-size:0.78rem; color:var(--text-muted);"><?= excerpt($j['deskripsi'], 80) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($j['penerbit'] ?? '-') ?></td>
                            <td>
                                <span class="akreditasi-badge <?= $badge_class ?>" style="margin:0; padding:0.2rem 0.6rem; font-size:0.68rem;">
                                    <?= sanitize($j['akreditasi'] ?? '-') ?>
                                </span>
                            </td>
                            <td style="font-family:monospace; font-size:0.85rem;"><?= sanitize($j['issn'] ?? '-') ?></td>
                            <td onclick="event.stopPropagation();">
                                <div style="display:flex; gap:0.35rem;">
                                    <button class="btn-action" onclick='openJurnalModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>👁️</button>
                                    <?php if ($jurnal_cols['url'] && !empty($j['url'])): ?>
                                        <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-action primary" onclick="event.stopPropagation();">🌐</a>
                                    <?php endif; ?>
                                    <button class="btn-action" onclick='shareJurnal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, "UTF-8") ?>)'>🔗</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- ===== CHART VIEW ===== -->
            <?php if ($view === 'chart'): ?>
            <div class="chart-grid" data-aos="fade-up">
                <div class="chart-card">
                    <h3>🏆 Distribusi Akreditasi</h3>
                    <div id="akreditasiChart"></div>
                </div>
                <?php if (!empty($bahasa_dist)): ?>
                <div class="chart-card">
                    <h3>🌐 Bahasa Publikasi</h3>
                    <div id="bahasaChart"></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($frekuensi_dist)): ?>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>📅 Frekuensi Terbit</h3>
                    <div id="frekuensiChart"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- CTA -->
        <div class="jurnal-cta" data-aos="zoom-in">
            <div class="jurnal-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Punya Penelitian Berkualitas?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Publikasikan hasil penelitian Anda di jurnal-jurnal kami yang terakreditasi dan terindeks nasional maupun internasional.
                </p>
                <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
                    <a href="<?= base_url('riset.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                        📝 Panduan Publikasi
                    </a>
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); font-weight: 800;">
                        📞 Hubungi Editor
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="jurnalModal" onclick="if(event.target===this)closeJurnalModal()">
    <div class="modal-content-jurnal" id="jurnalModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const jurnalCols = <?= json_encode($jurnal_cols) ?>;
const akreditasiDist = <?= json_encode($akreditasi_dist) ?>;
const bahasaDist = <?= json_encode($bahasa_dist) ?>;
const frekuensiDist = <?= json_encode($frekuensi_dist) ?>;

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
document.getElementById('jurnalSearch')?.addEventListener('input', function() {
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
function exportJurnal() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($jurnal_list) ?>;
    const headers = ['Nama','Penerbit','Akreditasi','ISSN','eISSN','URL'];
    const rows = data.map(d => [
        d.nama || '', d.penerbit || '', d.akreditasi || '',
        d.issn || '', d.eissn || '', d.url || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'jurnal-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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

function getBadgeClass(akr) {
    const a = (akr || '').toLowerCase();
    if (a.includes('scopus')) return 'badge-scopus';
    if (['sinta 1','sinta 2'].includes(a)) return 'badge-sinta1';
    if (['sinta 3','sinta 4'].includes(a)) return 'badge-sinta3';
    if (['sinta 5','sinta 6'].includes(a)) return 'badge-sinta5';
    return 'badge-default';
}

// ===== MODAL =====
function openJurnalModal(j) {
    const badgeClass = getBadgeClass(j.akreditasi);

    let info_items = '';
    if (j.penerbit) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏢 Penerbit</div><div class="modal-info-value">${escapeHtml(j.penerbit)}</div></div>`;
    if (j.akreditasi) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏆 Akreditasi</div><div class="modal-info-value">${escapeHtml(j.akreditasi)}</div></div>`;
    if (j.issn) info_items += `<div class="modal-info-item"><div class="modal-info-label">🔖 ISSN</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(j.issn)}</div></div>`;
    if (j.eissn) info_items += `<div class="modal-info-item"><div class="modal-info-label">🔖 e-ISSN</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(j.eissn)}</div></div>`;
    if (j.frekuensi) info_items += `<div class="modal-info-item"><div class="modal-info-label">📅 Frekuensi</div><div class="modal-info-value">${escapeHtml(j.frekuensi)}</div></div>`;
    if (j.bahasa) info_items += `<div class="modal-info-item"><div class="modal-info-label">🌐 Bahasa</div><div class="modal-info-value">${escapeHtml(j.bahasa)}</div></div>`;
    if (j.editor) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">👤 Editor in Chief</div><div class="modal-info-value">${escapeHtml(j.editor)}</div></div>`;
    if (j.email) info_items += `<div class="modal-info-item" style="grid-column:1/-1;"><div class="modal-info-label">📧 Email</div><div class="modal-info-value"><a href="mailto:${escapeHtml(j.email)}">${escapeHtml(j.email)}</a></div></div>`;

    // Fokus/Ruang Lingkup
    let fokus_html = '';
    if (j.fokus) {
        const fokusList = j.fokus.split('\n').map(s => s.trim()).filter(s => s);
        if (fokusList.length > 0) {
            fokus_html = `
                <div class="modal-section">
                    <h4>🎯 Fokus & Ruang Lingkup</h4>
                    <div class="fokus-list">
                        ${fokusList.map(f => `<div class="fokus-item">${escapeHtml(f.replace(/^[-*•]\s*/, ''))}</div>`).join('')}
                    </div>
                </div>
            `;
        }
    }

    const html = `
        <button class="modal-close" onclick="closeJurnalModal()">✕</button>
        <div class="modal-header-jurnal">
            <div class="modal-header-content">
                <div class="modal-logo">
                    ${jurnalCols.logo && j.logo ? `<img src="${'<?= asset('uploads/jurnal/') ?>' + encodeURIComponent(j.logo.split('/').pop())}" alt="">` : '📖'}
                </div>
                <div style="flex:1;">
                    <h2 class="modal-title">${escapeHtml(j.nama)}</h2>
                    <div class="modal-subtitle">🏢 ${escapeHtml(j.penerbit || 'FKIP UNIMOF')}</div>
                    <div class="modal-meta-ext">
                        <span class="${badgeClass}" style="background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3);">${escapeHtml(j.akreditasi || '-')}</span>
                        ${j.issn ? `<span>🔖 ${escapeHtml(j.issn)}</span>` : ''}
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-body-jurnal">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}

            ${j.deskripsi ? `
            <div class="modal-section">
                <h4>📝 Tentang Jurnal</h4>
                <p>${escapeHtml(j.deskripsi)}</p>
            </div>` : ''}

            ${fokus_html}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick='shareJurnal(${JSON.stringify(j).replace(/"/g, "&quot;")})'>🔗 Share</button>
                ${j.email ? `<a href="mailto:${escapeHtml(j.email)}" class="modal-btn secondary">📧 Email</a>` : ''}
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="closeJurnalModal()">Tutup</button>
                ${j.url ? `<a href="${escapeHtml(j.url)}" target="_blank" class="modal-btn primary">🌐 Kunjungi Website</a>` : ''}
            </div>
        </div>
    `;
    document.getElementById('jurnalModalContent').innerHTML = html;
    document.getElementById('jurnalModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeJurnalModal() {
    document.getElementById('jurnalModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareJurnal(j) {
    const text = `📚 ${j.nama}\n🏢 ${j.penerbit || 'FKIP UNIMOF'}\n🏆 ${j.akreditasi || '-'}\n${j.issn ? '🔖 ISSN: ' + j.issn + '\n' : ''}${j.url ? '🌐 ' + j.url + '\n' : ''}\nPortal Jurnal FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: j.nama, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info jurnal disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const akrColors = {
    'Scopus': '#1e40af', 'SINTA 1': '#166534', 'SINTA 2': '#10b981',
    'SINTA 3': '#92400e', 'SINTA 4': '#f59e0b', 'SINTA 5': '#64748b', 'SINTA 6': '#94a3b8'
};

if (Object.keys(akreditasiDist).length > 0) {
    new ApexCharts(document.querySelector("#akreditasiChart"), {
        series: Object.values(akreditasiDist),
        labels: Object.keys(akreditasiDist),
        chart: { type: 'donut', height: 320 },
        colors: Object.keys(akreditasiDist).map(k => akrColors[k] || '#6b7280'),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(akreditasiDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(bahasaDist).length > 0) {
    new ApexCharts(document.querySelector("#bahasaChart"), {
        series: Object.values(bahasaDist),
        labels: Object.keys(bahasaDist),
        chart: { type: 'pie', height: 320 },
        colors: ['#4c1d95', '#3b82f6', '#10b981', '#f59e0b'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(frekuensiDist).length > 0) {
    new ApexCharts(document.querySelector("#frekuensiChart"), {
        series: [{ data: Object.values(frekuensiDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#4c1d95'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(frekuensiDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('jurnalSearch')?.focus();
    }
    if (e.key === 'Escape') closeJurnalModal();
});

console.log('%c📚 Jurnal FKIP UNIMOF - EXTREME MULTIMATE', 'color:#4c1d95;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>