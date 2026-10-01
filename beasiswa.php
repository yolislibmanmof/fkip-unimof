<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Beasiswa';
$page_description = 'Informasi lengkap program beasiswa FKIP UNIMOF untuk membantu mahasiswa berprestasi dan kurang mampu.';

// ===== SCHEMA-SAFE: deteksi kolom beasiswa =====
$beasiswa_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `beasiswa`")->fetchAll(PDO::FETCH_COLUMN);
    $beasiswa_cols = [
        'nama'             => in_array('nama', $cols, true),
        'jenis'            => in_array('jenis', $cols, true),
        'sumber'           => in_array('sumber', $cols, true),
        'nominal'          => in_array('nominal', $cols, true),
        'deadline'         => in_array('deadline', $cols, true),
        'syarat'           => in_array('syarat', $cols, true),
        'status'           => in_array('status', $cols, true),
        'deskripsi'        => in_array('deskripsi', $cols, true),
        'kuota'            => in_array('kuota', $cols, true),
        'link_pendaftaran' => in_array('link_pendaftaran', $cols, true),
        'kontak'           => in_array('kontak', $cols, true),
        'cakupan'          => in_array('cakupan', $cols, true),
    ];
} catch (Exception $e) {
    $beasiswa_cols = array_fill_keys(['nama','jenis','sumber','nominal','deadline','syarat','status','deskripsi','kuota','link_pendaftaran','kontak','cakupan'], false);
}

// ===== AMBIL DATA BEASISWA =====
$stmt = $pdo->query("SELECT * FROM beasiswa ORDER BY " . ($beasiswa_cols['deadline'] ? "deadline ASC" : "id DESC"));
$beasiswa_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($beasiswa_list);
$stat_terbuka = $stat_tertutup = $stat_urgent = $stat_expired = 0;
$jenis_dist = [];
$sumber_dist = [];

foreach ($beasiswa_list as $b) {
    $status = $b['status'] ?? 'Terbuka';
    if ($status === 'Terbuka') $stat_terbuka++;
    elseif ($status === 'Tertutup') $stat_tertutup++;

    if ($beasiswa_cols['deadline'] && !empty($b['deadline'])) {
        $diff = strtotime($b['deadline']) - time();
        $days = floor($diff / 86400);
        if ($status === 'Terbuka' && $days >= 0 && $days < 30) $stat_urgent++;
        if ($days < 0) $stat_expired++;
    }

    if ($beasiswa_cols['jenis'] && !empty($b['jenis'])) {
        $jenis_dist[$b['jenis']] = ($jenis_dist[$b['jenis']] ?? 0) + 1;
    }
    if ($beasiswa_cols['sumber'] && !empty($b['sumber'])) {
        $sumber_dist[$b['sumber']] = ($sumber_dist[$b['sumber']] ?? 0) + 1;
    }
}

// ===== BEASISWA TERDEKAT (SPOTLIGHT) =====
$spotlight = null;
if ($beasiswa_cols['deadline']) {
    foreach ($beasiswa_list as $b) {
        if (($b['status'] ?? '') === 'Terbuka' && !empty($b['deadline'])) {
            $days = floor((strtotime($b['deadline']) - time()) / 86400);
            if ($days >= 0 && $days <= 60) {
                $spotlight = $b;
                break;
            }
        }
    }
}

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$filter_jenis = $_GET['jenis'] ?? 'all';
$view = $_GET['view'] ?? 'grid';

// Jenis unik
$jenis_list = array_keys($jenis_dist);
sort($jenis_list);

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.beasiswa-hero-extreme {
    position: relative; background: linear-gradient(135deg, #f59e0b 0%, #d97706 40%, #92400e 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.beasiswa-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(245,158,11,0.25) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.beasiswa-hero-extreme::after {
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
.beasiswa-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.beasiswa-hero-extreme .breadcrumb a, .beasiswa-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.beasiswa-hero-extreme .breadcrumb a:hover { color: white; }

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

/* Countdown Hero */
.hero-countdown-box {
    display: inline-flex; gap: 1.25rem; align-items: center;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.85rem 1.5rem; border-radius: 16px;
    margin-top: 1.5rem; flex-wrap: wrap; justify-content: center;
}
.countdown-item { text-align: center; min-width: 48px; }
.countdown-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.5rem; font-weight: 900; line-height: 1;
    font-variant-numeric: tabular-nums;
}
.countdown-label {
    font-size: 0.62rem; text-transform: uppercase;
    letter-spacing: 0.08em; opacity: 0.85; margin-top: 0.25rem;
}
.countdown-sep { font-size: 1.25rem; opacity: 0.6; font-weight: 800; }
.countdown-target {
    font-size: 0.82rem; opacity: 0.95; margin-left: 0.5rem;
    font-weight: 600;
}

/* ===== STATS ===== */
.beasiswa-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.beasiswa-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.beasiswa-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.beasiswa-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.beasiswa-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.beasiswa-stat-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.beasiswa-stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SPOTLIGHT ===== */
.spotlight-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-lg); display: grid; grid-template-columns: auto 1fr auto;
    gap: 2rem; align-items: center; position: relative; overflow: hidden;
}
.spotlight-section::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #ef4444, #f59e0b, #10b981);
}
.spotlight-badge {
    position: absolute; top: 1.25rem; right: 1.25rem;
    background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
    padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.72rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    animation: pulseBadge 2s infinite;
}
@keyframes pulseBadge {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
    50% { box-shadow: 0 0 0 10px rgba(239,68,68,0); }
}
.spotlight-icon {
    width: 100px; height: 100px; border-radius: 24px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 3rem; flex-shrink: 0;
    box-shadow: 0 10px 30px rgba(245,158,11,0.3);
}
.spotlight-info h2 {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800;
    margin-bottom: 0.5rem; color: var(--text-primary);
}
.spotlight-meta {
    display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.75rem;
    font-size: 0.85rem; color: var(--text-secondary);
}
.spotlight-meta span { display: flex; align-items: center; gap: 0.35rem; }
.spotlight-desc {
    font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;
}
.spotlight-countdown {
    background: var(--bg-secondary); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 1rem 1.25rem; text-align: center;
    min-width: 180px;
}
.spotlight-cd-label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.5rem;
}
.spotlight-cd-time {
    font-family: 'JetBrains Mono', monospace; font-size: 1.35rem;
    font-weight: 900; color: #ef4444; line-height: 1;
    font-variant-numeric: tabular-nums;
}
.spotlight-cd-date {
    font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem;
}

/* ===== TOOLBAR ===== */
.beasiswa-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.beasiswa-search { flex: 1; min-width: 240px; position: relative; }
.beasiswa-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.beasiswa-search input:focus { outline: none; border-color: #f59e0b; background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
.beasiswa-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.beasiswa-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.beasiswa-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.beasiswa-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.beasiswa-select:focus { outline: none; border-color: #f59e0b; }

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

/* ===== GRID VIEW ===== */
.beasiswa-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}
.beasiswa-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
    cursor: pointer;
}
.beasiswa-card.urgent {
    border-color: #ef4444;
    box-shadow: 0 0 0 1px #ef4444, 0 4px 12px rgba(239,68,68,0.15);
}
.beasiswa-card.urgent::before {
    content: '⏰ SEGERA'; position: absolute; top: 1rem; right: -2.25rem;
    background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
    padding: 0.3rem 3rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg); letter-spacing: 0.1em; z-index: 2;
}
.beasiswa-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #f59e0b;
}
.status-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.85rem; width: fit-content;
}
.badge-terbuka { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-tertutup { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }

.jenis-badge {
    display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.85rem;
    background: rgba(245,158,11,0.1); color: #d97706;
    border: 1px solid rgba(245,158,11,0.2);
}
.beasiswa-title {
    font-family: var(--font-display); font-size: 1.2rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.65rem; line-height: 1.3;
}
.beasiswa-sumber {
    font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;
    font-weight: 600; display: flex; align-items: center; gap: 0.35rem;
}

.nominal-box {
    background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(217,119,6,0.08));
    border: 1px solid rgba(245,158,11,0.2); border-radius: var(--radius-md);
    padding: 1rem; margin-bottom: 1rem; text-align: center;
}
.nominal-label {
    font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;
    font-weight: 700; margin-bottom: 0.25rem; letter-spacing: 0.05em;
}
.nominal-value {
    font-family: var(--font-display); font-size: 1.15rem; font-weight: 800;
    color: #d97706;
}

.deadline-box {
    background: var(--bg-secondary); border-radius: var(--radius-md);
    padding: 0.75rem 1rem; margin-bottom: 1rem;
    border: 1px solid var(--border);
}
.deadline-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 0.4rem;
}
.deadline-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
.deadline-date { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
.deadline-date.urgent { color: #ef4444; }
.deadline-progress-wrap {
    height: 6px; background: var(--bg-tertiary); border-radius: 999px;
    overflow: hidden; margin-bottom: 0.25rem;
}
.deadline-progress-fill {
    height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 1s ease;
}
.deadline-progress-fill.warn { background: linear-gradient(90deg, #f59e0b, #d97706); }
.deadline-progress-fill.danger { background: linear-gradient(90deg, #ef4444, #dc2626); }
.deadline-progress-fill.expired { background: #6b7280; }
.deadline-remaining {
    font-size: 0.72rem; font-weight: 600; text-align: right;
}
.deadline-remaining.safe { color: #10b981; }
.deadline-remaining.warn { color: #f59e0b; }
.deadline-remaining.danger { color: #ef4444; }
.deadline-remaining.expired { color: #6b7280; }

.syarat-preview {
    color: var(--text-secondary); font-size: 0.85rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.card-footer {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.btn-action {
    padding: 0.5rem 0.9rem; border-radius: 8px; font-size: 0.78rem;
    font-weight: 600; border: 1px solid var(--border); background: var(--bg-secondary);
    color: var(--text-secondary); cursor: pointer; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none;
}
.btn-action:hover { background: #f59e0b; color: white; border-color: #f59e0b; transform: translateY(-1px); }
.btn-action.primary {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; border-color: #d97706;
}
.btn-action.primary:hover { filter: brightness(1.1); }
.btn-action:disabled {
    background: var(--bg-tertiary); color: var(--text-muted);
    cursor: not-allowed; border-color: var(--border);
}
.btn-action:disabled:hover { transform: none; filter: none; background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== LIST VIEW ===== */
.beasiswa-list {
    display: flex; flex-direction: column; gap: 1rem;
}
.beasiswa-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer; position: relative;
}
.beasiswa-list-item.urgent { border-color: #ef4444; }
.beasiswa-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #f59e0b; }
.beasiswa-list-icon {
    width: 70px; height: 70px; border-radius: 16px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}
.beasiswa-list-info h3 {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 0.35rem;
    color: var(--text-primary);
}
.beasiswa-list-meta {
    display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem;
    color: var(--text-secondary); margin-bottom: 0.5rem;
}
.beasiswa-list-meta span { display: flex; align-items: center; gap: 0.35rem; }
.beasiswa-list-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }

/* ===== TIMELINE VIEW ===== */
.timeline-view { max-width: 900px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-view::before {
    content: ''; position: absolute; left: 24px; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #f59e0b, #d97706, transparent);
    border-radius: 4px;
}
.timeline-month-group { margin-bottom: 2.5rem; }
.timeline-month-label {
    position: sticky; top: 80px; z-index: 5; background: var(--bg-primary);
    display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;
    padding: 0.65rem 0.75rem 0.65rem 3.5rem; border-radius: var(--radius-md);
    border: 1px solid var(--border); box-shadow: var(--shadow-sm);
}
.timeline-month-label::before {
    content: ''; position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border: 3px solid var(--bg-primary); box-shadow: 0 0 0 2px #d97706;
}
.timeline-month-label h3 {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    color: var(--text-primary); margin: 0;
}
.timeline-month-label .month-count {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
}
.timeline-items { display: flex; flex-direction: column; gap: 1rem; padding-left: 3.5rem; }
.timeline-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 1.25rem; transition: all 0.3s;
    cursor: pointer; position: relative;
}
.timeline-item::before {
    content: ''; position: absolute; left: -2.35rem; top: 1.25rem;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--bg-primary); border: 3px solid #f59e0b;
}
.timeline-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #f59e0b; }
.timeline-item-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;
}
.timeline-item h4 {
    font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0;
}
.timeline-item-meta {
    font-size: 0.82rem; color: var(--text-muted);
    display: flex; gap: 0.75rem; flex-wrap: wrap;
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
.beasiswa-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #064e34 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.beasiswa-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.beasiswa-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY ===== */
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
    background: linear-gradient(135deg, #f59e0b, #d97706);
    padding: 2.5rem 2.5rem 2rem; color: white; position: relative;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-ext::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-title {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    margin-bottom: 0.5rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.92rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.85rem; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-ext { padding: 2rem 2.5rem; }

.modal-countdown-hero {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d; border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; text-align: center;
}
[data-theme="dark"] .modal-countdown-hero {
    background: linear-gradient(135deg, #78350f33, #92400e33);
    border-color: #f59e0b;
}
.modal-cd-label {
    font-size: 0.72rem; color: #92400e; text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.5rem;
}
[data-theme="dark"] .modal-cd-label { color: #fcd34d; }
.modal-cd-time {
    font-family: 'JetBrains Mono', monospace; font-size: 1.75rem;
    font-weight: 900; color: #dc2626; line-height: 1;
    font-variant-numeric: tabular-nums;
}
[data-theme="dark"] .modal-cd-time { color: #fca5a5; }
.modal-cd-date {
    font-size: 0.85rem; color: #92400e; margin-top: 0.5rem; font-weight: 600;
}
[data-theme="dark"] .modal-cd-date { color: #fcd34d; }

.modal-nominal-box {
    background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.1));
    border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-lg);
    padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;
}
.modal-nominal-label {
    font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;
    font-weight: 700; margin-bottom: 0.35rem; letter-spacing: 0.05em;
}
.modal-nominal-value {
    font-family: var(--font-display); font-size: 1.85rem; font-weight: 900;
    color: #d97706;
}

.modal-section-ext { margin-bottom: 1.75rem; }
.modal-section-ext h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.85rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section-ext p {
    font-size: 0.95rem; color: var(--text-primary); line-height: 1.8;
}

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
    font-size: 0.9rem; color: var(--text-primary); font-weight: 600;
    word-break: break-word;
}

.syarat-list { list-style: none; padding: 0; margin: 0; }
.syarat-list li {
    padding: 0.85rem 1rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    margin-bottom: 0.5rem;
    display: flex; align-items: flex-start; gap: 0.75rem;
    font-size: 0.92rem; color: var(--text-primary); line-height: 1.6;
    transition: all 0.2s;
}
.syarat-list li:hover { border-color: #f59e0b; transform: translateX(3px); }
.syarat-list li::before {
    content: '✓'; color: #10b981; font-weight: 800; flex-shrink: 0;
    font-size: 1.1rem;
}
.syarat-list li .syarat-num {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white; width: 24px; height: 24px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800; flex-shrink: 0;
}
.syarat-list li .syarat-num::before { content: '' !important; }

.modal-footer {
    padding: 1.25rem 2.5rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: space-between;
    flex-wrap: wrap; background: var(--bg-secondary);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
}
.modal-footer-left { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.modal-footer-right { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.modal-btn {
    padding: 0.7rem 1.25rem; border-radius: 8px; border: none;
    font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none;
    transition: all 0.2s;
}
.modal-btn.primary {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}
.modal-btn.secondary { background: var(--bg-tertiary); color: var(--text-primary); }
.modal-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.modal-btn:disabled {
    background: var(--bg-tertiary); color: var(--text-muted);
    cursor: not-allowed; box-shadow: none; transform: none;
}

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
    .spotlight-icon { margin: 0 auto; }
    .spotlight-countdown { margin: 0 auto; }
}
@media (max-width: 768px) {
    .beasiswa-hero-extreme { padding: 8rem 0 5rem; }
    .beasiswa-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .beasiswa-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .beasiswa-grid { grid-template-columns: 1fr; }
    .beasiswa-list-item { grid-template-columns: 1fr; text-align: center; }
    .beasiswa-list-icon { margin: 0 auto; }
    .beasiswa-list-actions { justify-content: center; }
    .modal-info-grid { grid-template-columns: 1fr; }
    .modal-header-ext, .modal-body-ext, .modal-footer { padding-left: 1.5rem; padding-right: 1.5rem; }
    .timeline-view::before { left: 18px; }
    .timeline-items { padding-left: 2.75rem; }
    .timeline-item::before { left: -2.05rem; }
    .timeline-month-label { padding-left: 2.75rem; }
    .timeline-month-label::before { left: 12px; }
}
@media (max-width: 480px) {
    .beasiswa-stats-bar { grid-template-columns: 1fr; }
    .hero-countdown-box { gap: 0.75rem; }
    .countdown-num { font-size: 1.25rem; }
}
</style>

<!-- ===== HERO ===== -->
<section class="beasiswa-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Beasiswa</span>
        </nav>
        <div class="hero-badge-pill" data-aos="fade-down" data-aos-delay="100">
            <span class="pulse-dot"></span>
            <span>Student Financial Aid Center</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Portal
            <span style="background: linear-gradient(135deg, #fef3c7, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Beasiswa</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Wujudkan impian akademik Anda dengan berbagai program beasiswa dari FKIP UNIMOF, Yayasan Muhammadiyah, hingga pemerintah.
        </p>

        <?php if ($spotlight && $beasiswa_cols['deadline'] && !empty($spotlight['deadline'])):
            $days_left = max(0, floor((strtotime($spotlight['deadline']) - time()) / 86400));
            $hours_left = max(0, floor(((strtotime($spotlight['deadline']) - time()) % 86400) / 3600));
        ?>
        <div class="hero-countdown-box" id="heroCountdown" data-aos="fade-up" data-aos-delay="300">
            <div class="countdown-item">
                <div class="countdown-num" id="hcdDays"><?= $days_left ?></div>
                <div class="countdown-label">Hari</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-item">
                <div class="countdown-num" id="hcdHours"><?= str_pad($hours_left, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="countdown-label">Jam</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-item">
                <div class="countdown-num" id="hcdMins">00</div>
                <div class="countdown-label">Menit</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-item">
                <div class="countdown-num" id="hcdSecs">00</div>
                <div class="countdown-label">Detik</div>
            </div>
            <span class="countdown-target">hingga <strong><?= sanitize($spotlight['nama']) ?></strong></span>
        </div>
        <?php endif; ?>

        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="400">
            <span class="trust-pill">🎓 <?= $stat_total ?>+ Program</span>
            <span class="trust-pill">✅ <?= $stat_terbuka ?> Terbuka</span>
            <span class="trust-pill">💰 Full & Parsial</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="beasiswa-stats-bar" data-aos="fade-up">
            <div class="beasiswa-stat-card" style="--stat-color: #f59e0b;">
                <div class="beasiswa-stat-icon">🎓</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="beasiswa-stat-label">Total Program</div>
            </div>
            <div class="beasiswa-stat-card" style="--stat-color: #10b981;">
                <div class="beasiswa-stat-icon">✅</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_terbuka ?>">0</div>
                <div class="beasiswa-stat-label">Terbuka</div>
            </div>
            <div class="beasiswa-stat-card" style="--stat-color: #ef4444;">
                <div class="beasiswa-stat-icon">⏰</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_urgent ?>">0</div>
                <div class="beasiswa-stat-label">Segera Tutup</div>
            </div>
            <div class="beasiswa-stat-card" style="--stat-color: #64748b;">
                <div class="beasiswa-stat-icon">🔒</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_tertutup ?>">0</div>
                <div class="beasiswa-stat-label">Ditutup</div>
            </div>
        </div>

        <!-- Spotlight -->
        <?php if ($spotlight):
            $deadline_ts = strtotime($spotlight['deadline']);
            $days_left = max(0, floor(($deadline_ts - time()) / 86400));
        ?>
        <div class="spotlight-section" data-aos="fade-up">
            <span class="spotlight-badge">🔥 Pendaftaran Segera</span>
            <div class="spotlight-icon">💰</div>
            <div class="spotlight-info">
                <h2><?= sanitize($spotlight['nama']) ?></h2>
                <div class="spotlight-meta">
                    <?php if ($beasiswa_cols['jenis'] && !empty($spotlight['jenis'])): ?>
                        <span>🏷️ <?= sanitize($spotlight['jenis']) ?></span>
                    <?php endif; ?>
                    <?php if ($beasiswa_cols['sumber'] && !empty($spotlight['sumber'])): ?>
                        <span>🏢 <?= sanitize($spotlight['sumber']) ?></span>
                    <?php endif; ?>
                    <?php if ($beasiswa_cols['nominal'] && !empty($spotlight['nominal'])): ?>
                        <span>💰 <?= sanitize($spotlight['nominal']) ?></span>
                    <?php endif; ?>
                </div>
                <p class="spotlight-desc">
                    <?= sanitize(excerpt($spotlight['deskripsi'] ?? $spotlight['syarat'] ?? 'Program beasiswa ini akan segera ditutup. Segera daftarkan diri Anda!', 180)) ?>
                </p>
            </div>
            <div class="spotlight-countdown">
                <div class="spotlight-cd-label">⏰ Sisa Waktu</div>
                <div class="spotlight-cd-time" id="spotlightCdTime"><?= $days_left ?>h</div>
                <div class="spotlight-cd-date"><?= date('d M Y', $deadline_ts) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($beasiswa_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">💰</div>
                <h3>Belum ada program beasiswa</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Program beasiswa akan segera diumumkan di sini.</p>
            </div>
        <?php else: ?>
            <!-- Toolbar -->
            <div class="beasiswa-toolbar" data-aos="fade-up">
                <div class="beasiswa-search">
                    <span class="s-icon">🔍</span>
                    <input type="text" id="beasiswaSearch" placeholder="Cari nama beasiswa, jenis, atau sumber..." value="<?= sanitize($search) ?>">
                    <?php if ($search !== ''): ?>
                        <a href="beasiswa.php?status=<?= urlencode($filter_status) ?>&jenis=<?= urlencode($filter_jenis) ?>&view=<?= urlencode($view) ?>" class="s-clear" title="Clear">✕</a>
                    <?php endif; ?>
                </div>
                <select class="beasiswa-select" id="statusSelect">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>📋 Semua Status</option>
                    <option value="Terbuka" <?= $filter_status === 'Terbuka' ? 'selected' : '' ?>>✅ Terbuka</option>
                    <option value="Tertutup" <?= $filter_status === 'Tertutup' ? 'selected' : '' ?>>🔒 Tertutup</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                    <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                    <button class="view-btn <?= $view === 'timeline' ? 'active' : '' ?>" onclick="switchView('timeline')">📅 Timeline</button>
                    <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
                </div>
                <a href="#" onclick="exportBeasiswa(); return false;" class="export-btn">📥 Export</a>
            </div>

            <!-- Filter Pills -->
            <div class="filter-pills" data-aos="fade-up">
                <a class="filter-pill <?= $filter_jenis === 'all' ? 'active' : '' ?>" href="beasiswa.php?jenis=all&status=<?= urlencode($filter_status) ?>&view=<?= urlencode($view) ?>">
                    🎓 Semua Jenis <span class="pill-count"><?= $stat_total ?></span>
                </a>
                <?php foreach ($jenis_list as $j): ?>
                <a class="filter-pill <?= $filter_jenis === $j ? 'active' : '' ?>" href="beasiswa.php?jenis=<?= urlencode($j) ?>&status=<?= urlencode($filter_status) ?>&view=<?= urlencode($view) ?>">
                    <?= sanitize($j) ?> <span class="pill-count"><?= $jenis_dist[$j] ?? 0 ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="beasiswa-grid" data-aos="fade-up">
                <?php foreach ($beasiswa_list as $b):
                    $is_urgent = $beasiswa_cols['deadline'] && !empty($b['deadline']) && strtotime($b['deadline']) < strtotime('+30 days') && ($b['status'] ?? '') === 'Terbuka' && strtotime($b['deadline']) >= time();
                    $is_expired = $beasiswa_cols['deadline'] && !empty($b['deadline']) && strtotime($b['deadline']) < time();
                    $badge_class = strtolower($b['status'] ?? 'tertutup') === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup';

                    // Deadline progress
                    $deadline_progress = 0;
                    $deadline_status = 'safe';
                    $deadline_remaining = '';
                    if ($beasiswa_cols['deadline'] && !empty($b['deadline'])) {
                        $deadline_ts = strtotime($b['deadline']);
                        $diff_days = floor(($deadline_ts - time()) / 86400);
                        if ($diff_days < 0) {
                            $deadline_status = 'expired';
                            $deadline_remaining = 'Telah lewat ' . abs($diff_days) . ' hari';
                            $deadline_progress = 100;
                        } elseif ($diff_days < 14) {
                            $deadline_status = 'danger';
                            $deadline_remaining = 'Sisa ' . $diff_days . ' hari';
                            $deadline_progress = max(0, min(100, 100 - ($diff_days / 30 * 100)));
                        } elseif ($diff_days < 60) {
                            $deadline_status = 'warn';
                            $deadline_remaining = 'Sisa ' . $diff_days . ' hari';
                            $deadline_progress = max(0, min(100, 100 - ($diff_days / 90 * 100)));
                        } else {
                            $deadline_status = 'safe';
                            $deadline_remaining = 'Sisa ' . $diff_days . ' hari';
                            $deadline_progress = max(0, min(100, 100 - ($diff_days / 180 * 100)));
                        }
                    }
                ?>
                <article class="beasiswa-card <?= $is_urgent ? 'urgent' : '' ?>" onclick='openBeasiswaModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>
                    <span class="status-badge <?= $badge_class ?>">
                        <?= ($b['status'] ?? '') === 'Terbuka' ? '✅' : '🔒' ?>
                        <?= sanitize($b['status'] ?? 'Tertutup') ?>
                    </span>
                    <?php if ($beasiswa_cols['jenis'] && !empty($b['jenis'])): ?>
                        <span class="jenis-badge"><?= sanitize($b['jenis']) ?></span>
                    <?php endif; ?>
                    <h3 class="beasiswa-title"><?= sanitize($b['nama'] ?? 'Beasiswa') ?></h3>
                    <?php if ($beasiswa_cols['sumber'] && !empty($b['sumber'])): ?>
                        <div class="beasiswa-sumber">🏢 Sumber: <?= sanitize($b['sumber']) ?></div>
                    <?php endif; ?>

                    <?php if ($beasiswa_cols['nominal'] && !empty($b['nominal'])): ?>
                    <div class="nominal-box">
                        <div class="nominal-label">💰 Cakupan Beasiswa</div>
                        <div class="nominal-value"><?= sanitize($b['nominal']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($beasiswa_cols['deadline'] && !empty($b['deadline'])): ?>
                    <div class="deadline-box">
                        <div class="deadline-header">
                            <span class="deadline-label">⏰ Deadline</span>
                            <span class="deadline-date <?= $is_urgent ? 'urgent' : '' ?>"><?= date('d M Y', strtotime($b['deadline'])) ?></span>
                        </div>
                        <div class="deadline-progress-wrap">
                            <div class="deadline-progress-fill <?= $deadline_status ?>" style="width: <?= $deadline_progress ?>%"></div>
                        </div>
                        <div class="deadline-remaining <?= $deadline_status ?>"><?= $deadline_remaining ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($beasiswa_cols['syarat'] && !empty($b['syarat'])): ?>
                        <p class="syarat-preview"><?= sanitize($b['syarat']) ?></p>
                    <?php endif; ?>

                    <div class="card-footer" onclick="event.stopPropagation();">
                        <button class="btn-action primary" onclick='openBeasiswaModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>
                            📋 Lihat Detail
                        </button>
                        <button class="btn-action" onclick='shareBeasiswa(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>
                            🔗 Share
                        </button>
                        <?php if ($beasiswa_cols['link_pendaftaran'] && !empty($b['link_pendaftaran']) && ($b['status'] ?? '') === 'Terbuka'): ?>
                            <a href="<?= sanitize($b['link_pendaftaran']) ?>" target="_blank" class="btn-action primary" onclick="event.stopPropagation();">
                                📝 Daftar
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="beasiswa-list" data-aos="fade-up">
                <?php foreach ($beasiswa_list as $b):
                    $is_urgent = $beasiswa_cols['deadline'] && !empty($b['deadline']) && strtotime($b['deadline']) < strtotime('+30 days') && ($b['status'] ?? '') === 'Terbuka' && strtotime($b['deadline']) >= time();
                ?>
                <div class="beasiswa-list-item <?= $is_urgent ? 'urgent' : '' ?>" onclick='openBeasiswaModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="beasiswa-list-icon">💰</div>
                    <div class="beasiswa-list-info">
                        <h3>
                            <?= sanitize($b['nama'] ?? 'Beasiswa') ?>
                            <?php if ($is_urgent): ?><span style="color:#ef4444; font-size:0.85rem;">🔥 SEGERA</span><?php endif; ?>
                        </h3>
                        <div class="beasiswa-list-meta">
                            <span class="status-badge <?= strtolower($b['status'] ?? '') === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup' ?>" style="margin:0;">
                                <?= ($b['status'] ?? '') === 'Terbuka' ? '✅' : '🔒' ?> <?= sanitize($b['status'] ?? 'Tertutup') ?>
                            </span>
                            <?php if ($beasiswa_cols['jenis'] && !empty($b['jenis'])): ?>
                                <span>🏷️ <?= sanitize($b['jenis']) ?></span>
                            <?php endif; ?>
                            <?php if ($beasiswa_cols['sumber'] && !empty($b['sumber'])): ?>
                                <span>🏢 <?= sanitize($b['sumber']) ?></span>
                            <?php endif; ?>
                            <?php if ($beasiswa_cols['nominal'] && !empty($b['nominal'])): ?>
                                <span>💰 <?= sanitize($b['nominal']) ?></span>
                            <?php endif; ?>
                            <?php if ($beasiswa_cols['deadline'] && !empty($b['deadline'])): ?>
                                <span>📅 <?= date('d M Y', strtotime($b['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($beasiswa_cols['syarat'] && !empty($b['syarat'])): ?>
                            <div style="font-size:0.85rem; color:var(--text-muted);"><?= excerpt($b['syarat'], 120) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="beasiswa-list-actions" onclick="event.stopPropagation();">
                        <button class="btn-action" onclick='shareBeasiswa(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                        <?php if ($beasiswa_cols['link_pendaftaran'] && !empty($b['link_pendaftaran']) && ($b['status'] ?? '') === 'Terbuka'): ?>
                            <a href="<?= sanitize($b['link_pendaftaran']) ?>" target="_blank" class="btn-action primary" onclick="event.stopPropagation();">📝 Daftar</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== TIMELINE VIEW ===== -->
            <?php if ($view === 'timeline' && $beasiswa_cols['deadline']): ?>
            <div class="timeline-view" data-aos="fade-up">
                <?php
                $months_id = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                $grouped = [];
                foreach ($beasiswa_list as $b) {
                    if (!empty($b['deadline'])) {
                        $key = date('Y-m', strtotime($b['deadline']));
                        $grouped[$key][] = $b;
                    } else {
                        $grouped['no-deadline'][] = $b;
                    }
                }
                ksort($grouped);
                foreach ($grouped as $ym => $items):
                    if ($ym === 'no-deadline') {
                        $label = 'Tanpa Deadline';
                    } else {
                        list($y, $m) = explode('-', $ym);
                        $label = $months_id[$m] . ' ' . $y;
                    }
                ?>
                <div class="timeline-month-group">
                    <div class="timeline-month-label">
                        <h3>📅 <?= $label ?></h3>
                        <span class="month-count"><?= count($items) ?> program</span>
                    </div>
                    <div class="timeline-items">
                        <?php foreach ($items as $b):
                            $is_urgent = $beasiswa_cols['deadline'] && !empty($b['deadline']) && strtotime($b['deadline']) < strtotime('+30 days') && ($b['status'] ?? '') === 'Terbuka' && strtotime($b['deadline']) >= time();
                        ?>
                        <div class="timeline-item" onclick='openBeasiswaModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, "UTF-8") ?>)'>
                            <div class="timeline-item-header">
                                <h4>
                                    <?= sanitize($b['nama'] ?? 'Beasiswa') ?>
                                    <?php if ($is_urgent): ?><span style="color:#ef4444; font-size:0.78rem;">🔥</span><?php endif; ?>
                                </h4>
                                <span class="status-badge <?= strtolower($b['status'] ?? '') === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup' ?>" style="margin:0; font-size:0.65rem;">
                                    <?= sanitize($b['status'] ?? '-') ?>
                                </span>
                            </div>
                            <div class="timeline-item-meta">
                                <?php if ($beasiswa_cols['jenis'] && !empty($b['jenis'])): ?>
                                    <span>🏷️ <?= sanitize($b['jenis']) ?></span>
                                <?php endif; ?>
                                <?php if ($beasiswa_cols['sumber'] && !empty($b['sumber'])): ?>
                                    <span>🏢 <?= sanitize($b['sumber']) ?></span>
                                <?php endif; ?>
                                <?php if ($beasiswa_cols['deadline'] && !empty($b['deadline'])): ?>
                                    <span>📅 <?= date('d M Y', strtotime($b['deadline'])) ?></span>
                                <?php endif; ?>
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
                    <h3>🏷️ Distribusi Jenis Beasiswa</h3>
                    <div id="jenisChart"></div>
                </div>
                <div class="chart-card">
                    <h3>🏢 Sumber Beasiswa</h3>
                    <div id="sumberChart"></div>
                </div>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>📊 Status Pendaftaran</h3>
                    <div id="statusChart"></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- CTA -->
        <div class="beasiswa-cta" data-aos="zoom-in">
            <div class="beasiswa-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Butuh Bantuan Finansial?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Tim kami siap membantu Anda menemukan program beasiswa yang paling sesuai dengan profil dan kebutuhan Anda.
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    💬 Konsultasi Gratis
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL ===== -->
<div class="modal-overlay-ext" id="beasiswaModal" onclick="if(event.target===this)closeBeasiswaModal()">
    <div class="modal-content-ext" id="beasiswaModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const beasiswaCols = <?= json_encode($beasiswa_cols) ?>;
const jenisDist = <?= json_encode($jenis_dist) ?>;
const sumberDist = <?= json_encode($sumber_dist) ?>;
const statTotal = <?= $stat_total ?>;
const statTerbuka = <?= $stat_terbuka ?>;
const statTertutup = <?= $stat_tertutup ?>;

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

// ===== HERO COUNTDOWN =====
<?php if ($spotlight && $beasiswa_cols['deadline'] && !empty($spotlight['deadline'])):
    $deadline_ts = strtotime($spotlight['deadline']);
?>
let heroCdSeconds = <?= max(0, $deadline_ts - time()) ?>;
function updateHeroCountdown() {
    if (heroCdSeconds <= 0) {
        document.getElementById('heroCountdown')?.remove();
        return;
    }
    heroCdSeconds--;
    const d = Math.floor(heroCdSeconds / 86400);
    const h = Math.floor((heroCdSeconds % 86400) / 3600);
    const m = Math.floor((heroCdSeconds % 3600) / 60);
    const s = heroCdSeconds % 60;
    const pad = (n) => String(n).padStart(2, '0');
    const de = document.getElementById('hcdDays');
    const he = document.getElementById('hcdHours');
    const me = document.getElementById('hcdMins');
    const se = document.getElementById('hcdSecs');
    if (de) de.textContent = d;
    if (he) he.textContent = pad(h);
    if (me) me.textContent = pad(m);
    if (se) se.textContent = pad(s);
}
setInterval(updateHeroCountdown, 1000);
<?php endif; ?>

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
document.getElementById('beasiswaSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

document.getElementById('statusSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('status', this.value);
    window.location = url;
});

function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== EXPORT CSV =====
function exportBeasiswa() {
    pubToast('Menyiapkan export...', '📥');
    const data = <?= json_encode($beasiswa_list) ?>;
    const headers = ['Nama','Jenis','Sumber','Nominal','Deadline','Status'];
    const rows = data.map(d => [
        d.nama || '', d.jenis || '', d.sumber || '',
        d.nominal || '', d.deadline || '', d.status || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'beasiswa-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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
function formatDate(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
}

// ===== MODAL =====
let modalCountdownInterval = null;

function openBeasiswaModal(b) {
    if (modalCountdownInterval) {
        clearInterval(modalCountdownInterval);
        modalCountdownInterval = null;
    }

    const isOpen = (b.status || '') === 'Terbuka';
    const statusIcon = isOpen ? '✅' : '🔒';
    const statusClass = isOpen ? 'badge-terbuka' : 'badge-tertutup';

    // Countdown HTML
    let countdown_html = '';
    if (beasiswaCols.deadline && b.deadline) {
        const deadline_ts = new Date(b.deadline).getTime();
        const now_ts = Date.now();
        const diff = Math.floor((deadline_ts - now_ts) / 1000);

        if (diff > 0 && isOpen) {
            countdown_html = `
                <div class="modal-countdown-hero">
                    <div class="modal-cd-label">⏰ Sisa Waktu Pendaftaran</div>
                    <div class="modal-cd-time" id="modalCdTime">--:--:--:--</div>
                    <div class="modal-cd-date">📅 ${formatDate(b.deadline)}</div>
                </div>
            `;
        } else if (diff <= 0) {
            countdown_html = `
                <div class="modal-countdown-hero" style="background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                    <div class="modal-cd-label" style="color:#991b1b;">⏰ Pendaftaran Telah Ditutup</div>
                    <div class="modal-cd-time" style="color:#6b7280;">CLOSED</div>
                    <div class="modal-cd-date" style="color:#991b1b;">📅 ${formatDate(b.deadline)}</div>
                </div>
            `;
        } else {
            countdown_html = `
                <div class="modal-countdown-hero">
                    <div class="modal-cd-label">📅 Deadline Pendaftaran</div>
                    <div class="modal-cd-time">${formatDate(b.deadline)}</div>
                </div>
            `;
        }
    }

    // Info grid
    let info_items = '';
    if (beasiswaCols.jenis && b.jenis) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Jenis</div><div class="modal-info-value">${escapeHtml(b.jenis)}</div></div>`;
    }
    if (beasiswaCols.sumber && b.sumber) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏢 Sumber</div><div class="modal-info-value">${escapeHtml(b.sumber)}</div></div>`;
    }
    if (beasiswaCols.kuota && b.kuota) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">👥 Kuota</div><div class="modal-info-value">${escapeHtml(b.kuota)} penerima</div></div>`;
    }
    if (beasiswaCols.kontak && b.kontak) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📞 Kontak</div><div class="modal-info-value">${escapeHtml(b.kontak)}</div></div>`;
    }

    // Syarat list
    let syarat_html = '';
    if (beasiswaCols.syarat && b.syarat) {
        const lines = b.syarat.split('\n').filter(l => l.trim());
        if (lines.length > 0) {
            syarat_html = `
                <div class="modal-section-ext">
                    <h4>📋 Syarat & Ketentuan</h4>
                    <ul class="syarat-list">
                        ${lines.map((line, i) => {
                            const clean = line.replace(/^[\d\.\)\-\*]+\s*/, '').trim();
                            return `<li><span class="syarat-num">${i+1}</span><span>${escapeHtml(clean)}</span></li>`;
                        }).join('')}
                    </ul>
                </div>
            `;
        }
    }

    // Deskripsi
    let desc_html = '';
    if (beasiswaCols.deskripsi && b.deskripsi) {
        desc_html = `
            <div class="modal-section-ext">
                <h4>📝 Deskripsi Program</h4>
                <p>${escapeHtml(b.deskripsi)}</p>
            </div>
        `;
    }

    const html = `
        <button class="modal-close-ext" onclick="closeBeasiswaModal()">✕</button>
        <div class="modal-header-ext">
            <div class="modal-header-content">
                <h2 class="modal-title">${escapeHtml(b.nama || 'Beasiswa')}</h2>
                <div class="modal-subtitle">
                    ${beasiswaCols.sumber && b.sumber ? '🏢 ' + escapeHtml(b.sumber) : 'Program Beasiswa FKIP UNIMOF'}
                </div>
                <div class="modal-meta-ext">
                    <span>${statusIcon} ${escapeHtml(b.status || '-')}</span>
                    ${beasiswaCols.jenis && b.jenis ? `<span>🏷️ ${escapeHtml(b.jenis)}</span>` : ''}
                    ${beasiswaCols.deadline && b.deadline ? `<span>📅 ${formatDate(b.deadline).split(',')[0] || ''}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-ext">
            ${countdown_html}
            ${beasiswaCols.nominal && b.nominal ? `
            <div class="modal-nominal-box">
                <div class="modal-nominal-label">💰 Cakupan Beasiswa</div>
                <div class="modal-nominal-value">${escapeHtml(b.nominal)}</div>
            </div>` : ''}

            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}
            ${desc_html}
            ${syarat_html}
        </div>
        <div class="modal-footer">
            <div class="modal-footer-left">
                <button class="modal-btn secondary" onclick='shareBeasiswa(${JSON.stringify(b).replace(/"/g, "&quot;")})'>🔗 Share</button>
                ${beasiswaCols.kontak && b.kontak ? `<a href="mailto:${escapeHtml(b.kontak)}" class="modal-btn secondary">✉️ Kontak</a>` : ''}
            </div>
            <div class="modal-footer-right">
                <button class="modal-btn secondary" onclick="closeBeasiswaModal()">Tutup</button>
                ${beasiswaCols.link_pendaftaran && b.link_pendaftaran && isOpen
                    ? `<a href="${escapeHtml(b.link_pendaftaran)}" target="_blank" class="modal-btn primary">📝 Daftar Sekarang</a>`
                    : `<button class="modal-btn primary" disabled>${isOpen ? '📋 Info Lengkap' : '🔒 Ditutup'}</button>`}
            </div>
        </div>
    `;

    document.getElementById('beasiswaModalContent').innerHTML = html;
    document.getElementById('beasiswaModal').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Start countdown
    if (beasiswaCols.deadline && b.deadline && isOpen) {
        const deadline_ts = new Date(b.deadline).getTime();
        const updateCdTime = () => {
            const diff = Math.floor((deadline_ts - Date.now()) / 1000);
            const el = document.getElementById('modalCdTime');
            if (!el) return;
            if (diff <= 0) {
                el.textContent = 'CLOSED';
                clearInterval(modalCountdownInterval);
                return;
            }
            const d = Math.floor(diff / 86400);
            const h = Math.floor((diff % 86400) / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;
            const pad = (n) => String(n).padStart(2, '0');
            el.textContent = `${d}h ${pad(h)}:${pad(m)}:${pad(s)}`;
        };
        updateCdTime();
        modalCountdownInterval = setInterval(updateCdTime, 1000);
    }
}

function closeBeasiswaModal() {
    if (modalCountdownInterval) {
        clearInterval(modalCountdownInterval);
        modalCountdownInterval = null;
    }
    document.getElementById('beasiswaModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareBeasiswa(b) {
    const text = `💰 Beasiswa: ${b.nama || '-'}\n${beasiswaCols.jenis && b.jenis ? '🏷️ Jenis: ' + b.jenis + '\n' : ''}${beasiswaCols.sumber && b.sumber ? '🏢 Sumber: ' + b.sumber + '\n' : ''}${beasiswaCols.nominal && b.nominal ? '💵 Cakupan: ' + b.nominal + '\n' : ''}${beasiswaCols.deadline && b.deadline ? '📅 Deadline: ' + formatDate(b.deadline) + '\n' : ''}Status: ${b.status || '-'}\n\nPortal Beasiswa FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: b.nama, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info beasiswa disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const jenisColors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#ef4444', '#06b6d4'];

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

if (Object.keys(sumberDist).length > 0) {
    new ApexCharts(document.querySelector("#sumberChart"), {
        series: [{ data: Object.values(sumberDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#f59e0b'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(sumberDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_terbuka ?>, <?= $stat_tertutup ?>],
    labels: ['Terbuka', 'Tertutup'],
    chart: { type: 'pie', height: 300 },
    colors: ['#10b981', '#6b7280'],
    dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 700 } },
    legend: { position: 'bottom', fontSize: '12px' }
}).render();
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('beasiswaSearch')?.focus();
    }
    if (e.key === 'Escape') closeBeasiswaModal();
});

console.log('%c💰 Portal Beasiswa FKIP UNIMOF - EXTREME MULTIMATE', 'color:#f59e0b;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>