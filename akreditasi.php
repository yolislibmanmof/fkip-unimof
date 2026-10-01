<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Akreditasi & Sertifikasi';
$page_description = 'Status akreditasi resmi seluruh program studi di FKIP UNIMOF dari BAN-PT dan LAMDIK.';

// ===== SCHEMA-SAFE: deteksi kolom akreditasi =====
$akred_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `akreditasi`")->fetchAll(PDO::FETCH_COLUMN);
    $akred_cols = [
        'peringkat'         => in_array('peringkat', $cols, true),
        'nomor_sk'          => in_array('nomor_sk', $cols, true),
        'tanggal_berlaku'   => in_array('tanggal_berlaku', $cols, true),
        'sertifikat_file'   => in_array('sertifikat_file', $cols, true),
        'status'            => in_array('status', $cols, true),
        'badan_akreditasi'  => in_array('badan_akreditasi', $cols, true),
    ];
} catch (Exception $e) {}

// ===== FILTER & SEARCH =====
$search = trim($_GET['q'] ?? '');
$filter = $_GET['peringkat'] ?? 'all';
$view   = $_GET['view']   ?? 'table'; // table, cards, chart

$where = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (nama_prodi LIKE ?" . ($akred_cols['nomor_sk'] ? " OR nomor_sk LIKE ?" : "") . ")";
    $params[] = "%$search%";
    if ($akred_cols['nomor_sk']) $params[] = "%$search%";
}
if ($filter !== 'all') {
    $where .= " AND peringkat = ?";
    $params[] = $filter;
}

// ORDER BY peringkat (prioritas: Unggul > Baik Sekali > Baik > C)
$order_clause = "";
if ($akred_cols['peringkat']) {
    $order_clause = "ORDER BY FIELD(peringkat, 'Unggul', 'Baik Sekali', 'Baik', 'C') IS NULL, FIELD(peringkat, 'Unggul', 'Baik Sekali', 'Baik', 'C'), nama_prodi ASC";
} else {
    $order_clause = "ORDER BY nama_prodi ASC";
}

$stmt = $pdo->prepare("SELECT * FROM akreditasi $where $order_clause");
$stmt->execute($params);
$akreditasi_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($akreditasi_list);
$stat_unggul = 0; $stat_baik_sekali = 0; $stat_baik = 0; $stat_aktif = 0; $stat_expired = 0;
$peringkat_dist = [];
$badan_dist = [];

foreach ($akreditasi_list as $a) {
    $peringkat = $a['peringkat'] ?? '-';
    $peringkat_dist[$peringkat] = ($peringkat_dist[$peringkat] ?? 0) + 1;

    if ($peringkat === 'Unggul') $stat_unggul++;
    elseif ($peringkat === 'Baik Sekali') $stat_baik_sekali++;
    elseif ($peringkat === 'Baik') $stat_baik++;

    if ($akred_cols['tanggal_berlaku'] && !empty($a['tanggal_berlaku'])) {
        if (strtotime($a['tanggal_berlaku']) < time()) $stat_expired++;
    }
    if ($akred_cols['status']) {
        if (strtolower($a['status'] ?? '') === 'aktif') $stat_aktif++;
    }

    if ($akred_cols['badan_akreditasi'] && !empty($a['badan_akreditasi'])) {
        $badan_dist[$a['badan_akreditasi']] = ($badan_dist[$a['badan_akreditasi']] ?? 0) + 1;
    }
}

$validity_pct = $stat_total > 0 ? round(($stat_aktif / $stat_total) * 100) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.akreditasi-hero {
    position: relative;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.akreditasi-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(16,185,129,0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.35) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0); opacity: 1; }
    50% { transform: translate(-30px, 30px); opacity: 0.85; }
}
.akreditasi-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.akreditasi-hero .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.akreditasi-hero .breadcrumb a, .akreditasi-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.akreditasi-hero .breadcrumb a:hover { color: white; }
.akreditasi-hero .page-title {
    font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem;
}
.akreditasi-hero .page-subtitle {
    font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;
}

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
.akreditasi-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.akreditasi-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s;
    position: relative; overflow: hidden;
}
.akreditasi-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--stat-color, var(--primary));
}
.akreditasi-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.akreditasi-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.akreditasi-stat-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.akreditasi-stat-label {
    font-size: 0.75rem; color: var(--text-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
}

/* ===== VALIDITY BAR ===== */
.validity-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
}
.validity-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;
}
.validity-title {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    display: flex; align-items: center; gap: 0.5rem;
}
.validity-score {
    font-family: 'JetBrains Mono', monospace; font-size: 1.75rem;
    font-weight: 900; color: var(--primary);
}
.validity-bar-wrap { height: 12px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.validity-bar-fill {
    height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 1.5s cubic-bezier(0.4,0,0.2,1);
    position: relative;
}
.validity-bar-fill::after {
    content: ''; position: absolute; right: -4px; top: 50%; transform: translateY(-50%);
    width: 16px; height: 16px; border-radius: 50%; background: white;
    border: 3px solid #10b981; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.validity-stats {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 1rem; margin-top: 1.25rem;
}
.vs-item {
    padding: 0.85rem; background: var(--bg-secondary);
    border-radius: var(--radius-md); border: 1px solid var(--border);
    text-align: center;
}
.vs-item-num {
    font-family: var(--font-display); font-size: 1.4rem; font-weight: 900;
    color: var(--text-primary); line-height: 1;
}
.vs-item-label {
    font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-top: 0.25rem;
}

/* ===== TOOLBAR ===== */
.akred-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.akred-search { flex: 1; min-width: 240px; position: relative; }
.akred-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.akred-search input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }
.akred-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.akred-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.akred-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

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

.export-btn {
    padding: 0.65rem 1rem; border-radius: var(--radius-md);
    background: var(--bg-secondary); border: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: inherit; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.export-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* ===== FILTER PILLS ===== */
.filter-pills-akred {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem;
    padding: 0.5rem; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.filter-pill-akred {
    padding: 0.5rem 0.95rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.filter-pill-akred:hover { background: var(--bg-tertiary); color: var(--text-primary); transform: translateY(-1px); }
.filter-pill-akred.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; border-color: #059669;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
}
.filter-pill-akred .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill-akred:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== TABLE VIEW ===== */
.akreditasi-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm);
}
.akreditasi-table { width: 100%; border-collapse: collapse; }
.akreditasi-table th {
    background: var(--bg-secondary); padding: 1.15rem 1.5rem; text-align: left;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 5;
}
.akreditasi-table td {
    padding: 1.15rem 1.5rem; border-bottom: 1px solid var(--border);
    color: var(--text-primary); font-size: 0.92rem; vertical-align: middle;
}
.akreditasi-table tr:last-child td { border-bottom: none; }
.akreditasi-table tbody tr { transition: all 0.2s; cursor: pointer; }
.akreditasi-table tbody tr:hover { background: rgba(16,185,129,0.04); transform: translateX(2px); }

.prodi-cell {
    display: flex; align-items: center; gap: 0.75rem;
}
.prodi-avatar {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, #10b981, #059669); color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1rem; flex-shrink: 0;
    font-family: var(--font-display);
}
.prodi-name { font-weight: 700; color: var(--text-primary); }
.prodi-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }

/* ===== VALIDITY PROGRESS ===== */
.validity-progress {
    display: flex; flex-direction: column; gap: 0.3rem; min-width: 140px;
}
.vp-info { display: flex; justify-content: space-between; font-size: 0.78rem; }
.vp-date { font-weight: 700; color: var(--text-primary); }
.vp-remaining { font-weight: 600; font-size: 0.7rem; }
.vp-remaining.safe { color: #10b981; }
.vp-remaining.warn { color: #f59e0b; }
.vp-remaining.danger { color: #ef4444; }
.vp-remaining.expired { color: #6b7280; }
.vp-bar-wrap { height: 6px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
.vp-bar-fill {
    height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 1s ease;
}
.vp-bar-fill.warn { background: linear-gradient(90deg, #f59e0b, #d97706); }
.vp-bar-fill.danger { background: linear-gradient(90deg, #ef4444, #dc2626); }

/* ===== BADGES ===== */
.badge-peringkat {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.9rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-unggul { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-baik-sekali { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-baik { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-c { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

.badge-status {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.3rem 0.7rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-proses { background: #dbeafe; color: #1e40af; }
.badge-expired { background: #fee2e2; color: #991b1b; }

/* ===== CARDS VIEW ===== */
.akred-cards {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.25rem;
}
.akred-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.3s;
    cursor: pointer; position: relative; overflow: hidden;
}
.akred-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--card-accent, #10b981);
}
.akred-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); border-color: var(--card-accent, #10b981); }
.akred-card-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 0.75rem; margin-bottom: 1rem;
}
.akred-card-title {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800;
    color: var(--text-primary); line-height: 1.3; margin-bottom: 0.25rem;
}
.akred-card-badge { flex-shrink: 0; }
.akred-card-meta {
    display: flex; flex-direction: column; gap: 0.4rem;
    font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 1rem;
}
.akred-card-meta span { display: flex; align-items: center; gap: 0.4rem; }
.akred-card-footer {
    padding-top: 1rem; border-top: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.akred-card-actions { display: flex; gap: 0.4rem; }
.akred-card-btn {
    padding: 0.35rem 0.7rem; border-radius: 6px; background: var(--bg-secondary);
    border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.75rem;
    font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.akred-card-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== CHART VIEW ===== */
.chart-grid {
    display: grid; grid-template-columns: 1fr 1fr;
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
.modal-content-akred {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4); border: 1px solid var(--border);
    animation: modalPop 0.4s cubic-bezier(0.2,0.9,0.3,1.2);
}
@keyframes modalPop {
    from { transform: translateY(30px) scale(0.96); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.modal-header-akred {
    padding: 2rem; position: relative; overflow: hidden;
    background: linear-gradient(135deg, var(--modal-color, #10b981), var(--modal-color-light, #059669));
    color: white; border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-akred::before {
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
.modal-title {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    line-height: 1.3; margin-bottom: 0.5rem; position: relative;
}
.modal-subtitle { font-size: 0.9rem; opacity: 0.9; position: relative; }

.modal-body { padding: 1.75rem 2rem; }
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

.certificate-visual {
    margin-top: 1.25rem; padding: 1.25rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 2px dashed #f59e0b; border-radius: var(--radius-md);
    text-align: center; color: #92400e;
}
[data-theme="dark"] .certificate-visual {
    background: linear-gradient(135deg, #78350f33, #92400e33);
    border-color: #f59e0b; color: #fcd34d;
}
.certificate-visual .cert-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.certificate-visual h4 {
    font-family: var(--font-display); font-size: 1rem; font-weight: 800; margin-bottom: 0.5rem;
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
}
@media (max-width: 768px) {
    .akreditasi-hero { padding: 8rem 0 5rem; }
    .akreditasi-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .validity-stats { grid-template-columns: 1fr; }
    .akred-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .akreditasi-table-wrap { overflow-x: auto; }
    .akreditasi-table { min-width: 800px; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .akreditasi-stats-bar { grid-template-columns: 1fr; }
    .akred-cards { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="akreditasi-hero">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Akreditasi</span>
        </nav>
        <h1 class="page-title" data-aos="fade-up">
            Akreditasi &
            <span style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Sertifikasi</span>
        </h1>
        <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">
            Komitmen kami terhadap kualitas pendidikan dibuktikan melalui akreditasi resmi dari badan akreditasi nasional yang diakui.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="200">
            <span class="trust-pill">🏆 BAN-PT Recognized</span>
            <span class="trust-pill">✓ LAMDIK Accredited</span>
            <span class="trust-pill">🛡️ Official Certification</span>
        </div>
    </div>
</section>

<!-- ===== STATS BAR ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="akreditasi-stats-bar" data-aos="fade-up">
            <div class="akreditasi-stat-card" style="--stat-color: #f59e0b;">
                <div class="akreditasi-stat-icon">🏆</div>
                <div class="akreditasi-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="akreditasi-stat-label">Total Prodi</div>
            </div>
            <div class="akreditasi-stat-card" style="--stat-color: #10b981;">
                <div class="akreditasi-stat-icon">🥇</div>
                <div class="akreditasi-stat-num count-up" data-target="<?= $stat_unggul ?>">0</div>
                <div class="akreditasi-stat-label">Peringkat Unggul</div>
            </div>
            <div class="akreditasi-stat-card" style="--stat-color: #3b82f6;">
                <div class="akreditasi-stat-icon">🥈</div>
                <div class="akreditasi-stat-num count-up" data-target="<?= $stat_baik_sekali ?>">0</div>
                <div class="akreditasi-stat-label">Baik Sekali</div>
            </div>
            <div class="akreditasi-stat-card" style="--stat-color: #8b5cf6;">
                <div class="akreditasi-stat-icon">✓</div>
                <div class="akreditasi-stat-num count-up" data-target="<?= $stat_aktif ?>">0</div>
                <div class="akreditasi-stat-label">Status Aktif</div>
            </div>
        </div>

        <!-- Validity Health -->
        <?php if ($akred_cols['tanggal_berlaku']): ?>
        <div class="validity-section" data-aos="fade-up">
            <div class="validity-header">
                <div class="validity-title">
                    <span>🛡️</span>
                    <span>Health Index Akreditasi</span>
                </div>
                <div class="validity-score"><?= $validity_pct ?>%</div>
            </div>
            <div class="validity-bar-wrap">
                <div class="validity-bar-fill" id="validityFill" style="width: 0%;" data-target="<?= $validity_pct ?>"></div>
            </div>
            <div class="validity-stats">
                <div class="vs-item">
                    <div class="vs-item-num"><?= $stat_aktif ?></div>
                    <div class="vs-item-label">✓ Masih Berlaku</div>
                </div>
                <div class="vs-item">
                    <div class="vs-item-num"><?= $stat_expired ?></div>
                    <div class="vs-item-label">⚠️ Kadaluarsa</div>
                </div>
                <div class="vs-item">
                    <div class="vs-item-num"><?= $stat_total ?></div>
                    <div class="vs-item-label">📊 Total</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="akred-toolbar" data-aos="fade-up">
            <div class="akred-search">
                <span class="s-icon">🔍</span>
                <input type="text" id="akredSearch" placeholder="Cari program studi atau nomor SK..." value="<?= sanitize($search) ?>">
                <?php if ($search !== ''): ?>
                    <a href="akreditasi.php?peringkat=<?= urlencode($filter) ?>&view=<?= urlencode($view) ?>" class="s-clear" title="Clear">✕</a>
                <?php endif; ?>
            </div>
            <div class="view-toggle">
                <button class="view-btn <?= $view === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📋 Tabel</button>
                <button class="view-btn <?= $view === 'cards' ? 'active' : '' ?>" onclick="switchView('cards')">🎴 Kartu</button>
                <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📊 Chart</button>
            </div>
            <a href="#" onclick="exportData(); return false;" class="export-btn">📥 Export</a>
        </div>

        <!-- Filter Pills -->
        <div class="filter-pills-akred" data-aos="fade-up">
            <a class="filter-pill-akred <?= $filter === 'all' ? 'active' : '' ?>" href="akreditasi.php?peringkat=all&view=<?= urlencode($view) ?>">
                📋 Semua <span class="pill-count"><?= $stat_total ?></span>
            </a>
            <?php
            $peringkat_list = [
                'Unggul'       => ['icon'=>'🏆','cnt'=>$stat_unggul],
                'Baik Sekali'  => ['icon'=>'🥈','cnt'=>$stat_baik_sekali],
                'Baik'         => ['icon'=>'✓','cnt'=>$stat_baik],
            ];
            foreach ($peringkat_list as $p => $info):
                if ($info['cnt'] === 0 && $filter !== $p) continue;
            ?>
            <a class="filter-pill-akred <?= $filter === $p ? 'active' : '' ?>" href="akreditasi.php?peringkat=<?= urlencode($p) ?>&view=<?= urlencode($view) ?>">
                <?= $info['icon'] ?> <?= $p ?> <span class="pill-count"><?= $info['cnt'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Empty state -->
        <?php if (empty($akreditasi_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🏆</div>
                <h3>Tidak ada data akreditasi</h3>
                <p>Silakan coba filter lain atau cek kembali nanti.</p>
                <a href="akreditasi.php" class="akred-card-btn" style="display:inline-block;">🔄 Reset Filter</a>
            </div>
        <?php endif; ?>

        <!-- ===== TABLE VIEW ===== -->
        <?php if ($view === 'table' && !empty($akreditasi_list)): ?>
        <div class="akreditasi-table-wrap" data-aos="fade-up">
            <table class="akreditasi-table">
                <thead>
                    <tr>
                        <th>Program Studi</th>
                        <?php if ($akred_cols['badan_akreditasi']): ?><th>Badan</th><?php endif; ?>
                        <?php if ($akred_cols['peringkat']): ?><th>Peringkat</th><?php endif; ?>
                        <?php if ($akred_cols['nomor_sk']): ?><th>Nomor SK</th><?php endif; ?>
                        <?php if ($akred_cols['tanggal_berlaku']): ?><th>Berlaku Sampai</th><?php endif; ?>
                        <?php if ($akred_cols['status']): ?><th>Status</th><?php endif; ?>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($akreditasi_list as $a):
                        $peringkat = $a['peringkat'] ?? '-';
                        $badge_class = strtolower(str_replace(' ', '-', $peringkat));
                        if (!in_array($badge_class, ['unggul','baik-sekali','baik','c'])) $badge_class = 'baik';

                        $validity_html = '-';
                        $validity_status = 'expired';
                        $validity_pct_item = 0;
                        if ($akred_cols['tanggal_berlaku'] && !empty($a['tanggal_berlaku'])) {
                            $expire_ts = strtotime($a['tanggal_berlaku']);
                            $now_ts = time();
                            $diff_days = floor(($expire_ts - $now_ts) / 86400);
                            $date_str = date('d M Y', $expire_ts);

                            if ($diff_days < 0) {
                                $validity_status = 'expired';
                                $remaining_txt = 'Telah lewat ' . abs($diff_days) . ' hari';
                            } elseif ($diff_days < 90) {
                                $validity_status = 'danger';
                                $remaining_txt = 'Sisa ' . $diff_days . ' hari';
                            } elseif ($diff_days < 365) {
                                $validity_status = 'warn';
                                $remaining_txt = 'Sisa ' . $diff_days . ' hari';
                            } else {
                                $validity_status = 'safe';
                                $years = floor($diff_days / 365);
                                $remaining_txt = 'Sisa ' . $years . ' tahun';
                            }
                            // Bar width: assume 5 tahun max validity
                            $total_days = 365 * 5;
                            $validity_pct_item = max(0, min(100, ($diff_days / $total_days) * 100));

                            $validity_html = "
                                <div class='validity-progress'>
                                    <div class='vp-info'>
                                        <span class='vp-date'>$date_str</span>
                                        <span class='vp-remaining $validity_status'>$remaining_txt</span>
                                    </div>
                                    <div class='vp-bar-wrap'>
                                        <div class='vp-bar-fill $validity_status' style='width: $validity_pct_item%'></div>
                                    </div>
                                </div>
                            ";
                        }
                    ?>
                    <tr onclick='showAkredDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                        <td>
                            <div class="prodi-cell">
                                <div class="prodi-avatar"><?= strtoupper(substr($a['nama_prodi'] ?? 'P', 0, 2)) ?></div>
                                <div>
                                    <div class="prodi-name"><?= sanitize($a['nama_prodi']) ?></div>
                                    <div class="prodi-sub">FKIP UNIMOF</div>
                                </div>
                            </div>
                        </td>
                        <?php if ($akred_cols['badan_akreditasi']): ?>
                            <td><?= sanitize($a['badan_akreditasi'] ?? '-') ?></td>
                        <?php endif; ?>
                        <?php if ($akred_cols['peringkat']): ?>
                            <td>
                                <span class="badge-peringkat badge-<?= $badge_class ?>">
                                    <?php
                                    $icon = '✓';
                                    if ($peringkat === 'Unggul') $icon = '🏆';
                                    elseif ($peringkat === 'Baik Sekali') $icon = '🥈';
                                    elseif ($peringkat === 'Baik') $icon = '✓';
                                    ?>
                                    <?= $icon ?> <?= sanitize($peringkat) ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <?php if ($akred_cols['nomor_sk']): ?>
                            <td style="font-family: monospace; font-size: 0.82rem;"><?= sanitize($a['nomor_sk'] ?? '-') ?></td>
                        <?php endif; ?>
                        <?php if ($akred_cols['tanggal_berlaku']): ?>
                            <td><?= $validity_html ?></td>
                        <?php endif; ?>
                        <?php if ($akred_cols['status']): ?>
                            <td>
                                <?php
                                $st = strtolower($a['status'] ?? '');
                                $status_class = 'aktif';
                                if ($st === 'proses') $status_class = 'proses';
                                elseif ($st === 'expired' || $st === 'kadaluarsa') $status_class = 'expired';
                                ?>
                                <span class="badge-status badge-<?= $status_class ?>">
                                    <?= $status_class === 'aktif' ? '✓' : ($status_class === 'proses' ? '⏳' : '⚠️') ?>
                                    <?= sanitize($a['status']) ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:0.35rem; justify-content:flex-end;">
                                <button class="akred-card-btn" onclick="event.stopPropagation(); showAkredDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">👁️ Detail</button>
                                <?php if ($akred_cols['sertifikat_file'] && !empty($a['sertifikat_file'])): ?>
                                    <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="akred-card-btn" onclick="event.stopPropagation();">📄 SK</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ===== CARDS VIEW ===== -->
        <?php if ($view === 'cards' && !empty($akreditasi_list)): ?>
        <div class="akred-cards" data-aos="fade-up">
            <?php
            $card_colors = [
                'Unggul' => '#10b981', 'Baik Sekali' => '#3b82f6',
                'Baik' => '#f59e0b', 'C' => '#6b7280', '-' => '#8b5cf6'
            ];
            foreach ($akreditasi_list as $a):
                $peringkat = $a['peringkat'] ?? '-';
                $color = $card_colors[$peringkat] ?? '#8b5cf6';
            ?>
            <div class="akred-card" style="--card-accent: <?= $color ?>;" onclick='showAkredDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                <div class="akred-card-header">
                    <div>
                        <h3 class="akred-card-title"><?= sanitize($a['nama_prodi']) ?></h3>
                        <div class="akred-card-meta">
                            <span>🏛️ FKIP UNIMOF</span>
                            <?php if ($akred_cols['badan_akreditasi'] && !empty($a['badan_akreditasi'])): ?>
                                <span>📋 <?= sanitize($a['badan_akreditasi']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="akred-card-badge">
                        <?php
                        $badge_class = strtolower(str_replace(' ', '-', $peringkat));
                        if (!in_array($badge_class, ['unggul','baik-sekali','baik','c'])) $badge_class = 'baik';
                        ?>
                        <span class="badge-peringkat badge-<?= $badge_class ?>">
                            <?= $peringkat === 'Unggul' ? '🏆' : ($peringkat === 'Baik Sekali' ? '🥈' : '✓') ?>
                            <?= sanitize($peringkat) ?>
                        </span>
                    </div>
                </div>

                <?php if ($akred_cols['tanggal_berlaku'] && !empty($a['tanggal_berlaku'])):
                    $expire_ts = strtotime($a['tanggal_berlaku']);
                    $diff_days = floor(($expire_ts - time()) / 86400);
                    if ($diff_days < 0) $v_status = 'expired';
                    elseif ($diff_days < 90) $v_status = 'danger';
                    elseif ($diff_days < 365) $v_status = 'warn';
                    else $v_status = 'safe';
                    $pct = max(0, min(100, ($diff_days / (365*5)) * 100));
                ?>
                <div class="validity-progress" style="margin-bottom: 1rem;">
                    <div class="vp-info">
                        <span class="vp-date">Berlaku s/d <?= date('d M Y', $expire_ts) ?></span>
                        <span class="vp-remaining <?= $v_status ?>">
                            <?php
                            if ($diff_days < 0) echo 'Telah lewat ' . abs($diff_days) . ' hari';
                            elseif ($diff_days < 365) echo 'Sisa ' . $diff_days . ' hari';
                            else echo 'Sisa ' . floor($diff_days/365) . ' tahun';
                            ?>
                        </span>
                    </div>
                    <div class="vp-bar-wrap">
                        <div class="vp-bar-fill <?= $v_status ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="akred-card-footer">
                    <span class="akred-card-meta" style="margin:0;">
                        <span>🏷️ <?= sanitize($a['status'] ?? 'Aktif') ?></span>
                    </span>
                    <div class="akred-card-actions">
                        <?php if ($akred_cols['sertifikat_file'] && !empty($a['sertifikat_file'])): ?>
                            <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="akred-card-btn" onclick="event.stopPropagation();">📄 SK</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== CHART VIEW ===== -->
        <?php if ($view === 'chart'): ?>
        <div class="chart-grid" data-aos="fade-up">
            <div class="chart-card">
                <h3>🏆 Distribusi Peringkat</h3>
                <div id="peringkatChart"></div>
            </div>
            <div class="chart-card">
                <h3>📋 Badan Akreditasi</h3>
                <div id="badanChart"></div>
            </div>
        </div>

        <?php if (!empty($akreditasi_list)): ?>
        <div class="akreditasi-table-wrap" data-aos="fade-up">
            <table class="akreditasi-table">
                <thead>
                    <tr>
                        <th>Program Studi</th>
                        <?php if ($akred_cols['peringkat']): ?><th>Peringkat</th><?php endif; ?>
                        <?php if ($akred_cols['tanggal_berlaku']): ?><th>Berlaku</th><?php endif; ?>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($akreditasi_list as $a):
                        $peringkat = $a['peringkat'] ?? '-';
                        $badge_class = strtolower(str_replace(' ', '-', $peringkat));
                        if (!in_array($badge_class, ['unggul','baik-sekali','baik','c'])) $badge_class = 'baik';
                    ?>
                    <tr onclick='showAkredDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, "UTF-8") ?>)'>
                        <td>
                            <div class="prodi-cell">
                                <div class="prodi-avatar"><?= strtoupper(substr($a['nama_prodi'] ?? 'P', 0, 2)) ?></div>
                                <div class="prodi-name"><?= sanitize($a['nama_prodi']) ?></div>
                            </div>
                        </td>
                        <?php if ($akred_cols['peringkat']): ?>
                            <td><span class="badge-peringkat badge-<?= $badge_class ?>"><?= sanitize($peringkat) ?></span></td>
                        <?php endif; ?>
                        <?php if ($akred_cols['tanggal_berlaku']): ?>
                            <td><?= !empty($a['tanggal_berlaku']) ? date('d M Y', strtotime($a['tanggal_berlaku'])) : '-' ?></td>
                        <?php endif; ?>
                        <td><button class="akred-card-btn" onclick="event.stopPropagation(); showAkredDetail(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">👁️ Detail</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="akredModal" onclick="if(event.target===this)closeAkredModal()">
    <div class="modal-content-akred" id="akredModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const akredCols = <?= json_encode($akred_cols) ?>;
const peringkatDist = <?= json_encode($peringkat_dist) ?>;
const badanDist = <?= json_encode($badan_dist) ?>;
const peringkatColors = {
    'Unggul': '#10b981', 'Baik Sekali': '#3b82f6',
    'Baik': '#f59e0b', 'C': '#6b7280', '-': '#8b5cf6'
};

// ===== COUNT-UP =====
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

// ===== VALIDITY BAR ANIMATION =====
const validityFill = document.getElementById('validityFill');
if (validityFill) {
    setTimeout(() => {
        validityFill.style.width = validityFill.dataset.target + '%';
    }, 500);
}

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
document.getElementById('akredSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

// ===== VIEW SWITCH =====
function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== EXPORT =====
function exportData() {
    pubToast('Menyiapkan export...', '📥');
    // Simple CSV export via data URI
    const data = <?= json_encode($akreditasi_list) ?>;
    const headers = ['Nama Prodi','Peringkat','Badan Akreditasi','Nomor SK','Tanggal Berlaku','Status'];
    const rows = data.map(d => [
        d.nama_prodi || '', d.peringkat || '', d.badan_akreditasi || '',
        d.nomor_sk || '', d.tanggal_berlaku || '', d.status || ''
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.map(c => '"' + String(c).replace(/"/g,'""') + '"').join(','))].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'akreditasi-fkip-' + new Date().toISOString().slice(0,10) + '.csv';
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
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

// ===== MODAL =====
function showAkredDetail(a) {
    const peringkat = a.peringkat || '-';
    const color = peringkatColors[peringkat] || '#8b5cf6';
    const lightColor = color + 'cc';

    let validity_html = '';
    if (akred_cols.tanggal_berlaku && a.tanggal_berlaku) {
        const expire_ts = new Date(a.tanggal_berlaku).getTime();
        const now_ts = Date.now();
        const diff_days = Math.floor((expire_ts - now_ts) / 86400000);
        let v_status, remaining_txt;
        if (diff_days < 0) { v_status = 'expired'; remaining_txt = 'Telah lewat ' + Math.abs(diff_days) + ' hari'; }
        else if (diff_days < 90) { v_status = 'danger'; remaining_txt = 'Sisa ' + diff_days + ' hari'; }
        else if (diff_days < 365) { v_status = 'warn'; remaining_txt = 'Sisa ' + diff_days + ' hari'; }
        else { v_status = 'safe'; remaining_txt = 'Sisa ' + Math.floor(diff_days/365) + ' tahun'; }
        const pct = Math.max(0, Math.min(100, (diff_days / (365*5)) * 100));

        validity_html = `
            <div class="validity-progress" style="padding:1rem; background:var(--bg-secondary); border-radius:var(--radius-md); margin-bottom:1rem;">
                <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; font-weight:700; margin-bottom:0.5rem;">⏰ Masa Berlaku</div>
                <div class="vp-info">
                    <span class="vp-date">${formatDate(a.tanggal_berlaku)}</span>
                    <span class="vp-remaining ${v_status}">${remaining_txt}</span>
                </div>
                <div class="vp-bar-wrap" style="height:8px; margin-top:0.35rem;">
                    <div class="vp-bar-fill ${v_status}" style="width:${pct}%"></div>
                </div>
            </div>
        `;
    }

    const html = `
        <div class="modal-header-akred" style="--modal-color: ${color}; --modal-color-light: ${lightColor}">
            <button class="modal-close" onclick="closeAkredModal()">✕</button>
            <div style="position:relative;">
                <h2 class="modal-title">${escapeHtml(a.nama_prodi)}</h2>
                <div class="modal-subtitle">FKIP UNIMOF ${akred_cols.badan_akreditasi && a.badan_akreditasi ? ' • ' + escapeHtml(a.badan_akreditasi) : ''}</div>
            </div>
        </div>
        <div class="modal-body">
            ${validity_html}
            <div class="modal-info-grid">
                ${akred_cols.peringkat ? `
                <div class="modal-info-item">
                    <div class="modal-info-label">🏆 Peringkat</div>
                    <div class="modal-info-value">${escapeHtml(peringkat)}</div>
                </div>` : ''}
                ${akred_cols.nomor_sk ? `
                <div class="modal-info-item">
                    <div class="modal-info-label">📄 Nomor SK</div>
                    <div class="modal-info-value" style="font-family:monospace;">${escapeHtml(a.nomor_sk || '-')}</div>
                </div>` : ''}
                ${akred_cols.status ? `
                <div class="modal-info-item">
                    <div class="modal-info-label">✓ Status</div>
                    <div class="modal-info-value">${escapeHtml(a.status || 'Aktif')}</div>
                </div>` : ''}
                ${akred_cols.tanggal_berlaku ? `
                <div class="modal-info-item">
                    <div class="modal-info-label">📅 Tanggal Berlaku</div>
                    <div class="modal-info-value">${formatDate(a.tanggal_berlaku)}</div>
                </div>` : ''}
            </div>

            ${akred_cols.sertifikat_file && a.sertifikat_file ? `
            <div class="certificate-visual">
                <div class="cert-icon">📜</div>
                <h4>Sertifikat Akreditasi Tersedia</h4>
                <p style="font-size:0.85rem; margin-bottom:0.75rem;">Dokumen SK akreditasi resmi dapat diunduh</p>
                <a href="${'<?= asset('akreditasi/') ?>' + encodeURIComponent(a.sertifikat_file.split('/').pop())}" target="_blank" class="modal-btn primary" style="display:inline-flex;">📄 Unduh SK Akreditasi</a>
            </div>` : ''}
        </div>
        <div class="modal-footer">
            <button class="modal-btn secondary" onclick="shareAkred(${JSON.stringify(a).replace(/"/g, '&quot;')})">🔗 Share</button>
            <button class="modal-btn primary" onclick="closeAkredModal()">Tutup</button>
        </div>
    `;
    document.getElementById('akredModalContent').innerHTML = html;
    document.getElementById('akredModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAkredModal() {
    document.getElementById('akredModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareAkred(a) {
    const text = `🏆 Akreditasi ${a.nama_prodi}\n🏆 Peringkat: ${a.peringkat || '-'}${akred_cols.nomor_sk && a.nomor_sk ? '\n📄 SK: ' + a.nomor_sk : ''}\n\nFKIP UNIMOF - Akreditasi & Sertifikasi`;
    if (navigator.share) {
        navigator.share({ title: 'Akreditasi ' + a.nama_prodi, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info akreditasi disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
if (Object.keys(peringkatDist).length > 0) {
    new ApexCharts(document.querySelector("#peringkatChart"), {
        series: Object.values(peringkatDist),
        labels: Object.keys(peringkatDist),
        chart: { type: 'donut', height: 320 },
        colors: Object.keys(peringkatDist).map(k => peringkatColors[k] || '#8b5cf6'),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(peringkatDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(badanDist).length > 0) {
    new ApexCharts(document.querySelector("#badanChart"), {
        series: [{ data: Object.values(badanDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: ['#10b981'],
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(badanDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('akredSearch')?.focus();
    }
    if (e.key === 'Escape') closeAkredModal();
});

console.log('%c🏆 Akreditasi FKIP UNIMOF - EXTREME MULTIMATE', 'color:#10b981;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>