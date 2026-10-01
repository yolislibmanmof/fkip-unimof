<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Dosen & Tenaga Pengajar';
$page_description = 'Profil lengkap dosen dan tenaga pengajar berkualitas FKIP UNIMOF yang aktif dalam penelitian dan pengabdian masyarakat.';

// ===== SCHEMA-SAFE: deteksi kolom dosen =====
$dosen_cols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `dosen`")->fetchAll(PDO::FETCH_COLUMN);
    $dosen_cols = [
        'nama'                 => in_array('nama', $cols, true),
        'gelar_depan'          => in_array('gelar_depan', $cols, true),
        'gelar_belakang'       => in_array('gelar_belakang', $cols, true),
        'jabatan_fungsional'   => in_array('jabatan_fungsional', $cols, true),
        'pendidikan_terakhir'  => in_array('pendidikan_terakhir', $cols, true),
        'bidang_keahlian'      => in_array('bidang_keahlian', $cols, true),
        'email'                => in_array('email', $cols, true),
        'telepon'              => in_array('telepon', $cols, true),
        'foto'                 => in_array('foto', $cols, true),
        'nidn'                 => in_array('nidn', $cols, true),
        'nip'                  => in_array('nip', $cols, true),
        'status'               => in_array('status', $cols, true),
        'program_studi_id'     => in_array('program_studi_id', $cols, true),
        'riwayat_pendidikan'   => in_array('riwayat_pendidikan', $cols, true),
        'publikasi'            => in_array('publikasi', $cols, true),
        'penelitian'           => in_array('penelitian', $cols, true),
        'pengabdian'           => in_array('pengabdian', $cols, true),
        'sertifikasi'          => in_array('sertifikasi', $cols, true),
    ];
} catch (Exception $e) {
    $dosen_cols = array_fill_keys(['nama','gelar_depan','gelar_belakang','jabatan_fungsional','pendidikan_terakhir','bidang_keahlian','email','telepon','foto','nidn','nip','status','program_studi_id','riwayat_pendidikan','publikasi','penelitian','pengabdian','sertifikasi'], false);
}

// ===== AMBIL DATA DOSEN =====
$join = $dosen_cols['program_studi_id'] ? "LEFT JOIN program_studi p ON d.program_studi_id = p.id" : "";
$select = "d.*";
if ($dosen_cols['program_studi_id']) $select .= ", p.nama as prodi_nama, p.singkatan as prodi_singkatan";

$where = $dosen_cols['status'] ? "WHERE d.status = 'Aktif'" : "WHERE 1=1";
$stmt = $pdo->query("SELECT $select FROM dosen d $join $where ORDER BY d.nama ASC");
$dosen_list = $stmt->fetchAll();

// ===== DAFTAR PRODI =====
$prodi_list = [];
try {
    $prodi_list = $pdo->query("SELECT id, nama, singkatan FROM program_studi WHERE status='Aktif' ORDER BY nama ASC")->fetchAll();
} catch (Exception $e) {}

// ===== STATISTIK =====
$total_dosen = count($dosen_list);
$dosen_s3 = $dosen_s2 = $dosen_s1 = 0;
$jabatan_dist = [];
$pendidikan_dist = [];
$prodi_dist = [];

foreach ($dosen_list as $d) {
    $pend = $d['pendidikan_terakhir'] ?? '';
    if (stripos($pend, 'S3') !== false || stripos($pend, 'Doctor') !== false || stripos($pend, 'Dr.') !== false) {
        $dosen_s3++;
        $pendidikan_dist['S3 (Doktor)'] = ($pendidikan_dist['S3 (Doktor)'] ?? 0) + 1;
    } elseif (stripos($pend, 'S2') !== false || stripos($pend, 'Master') !== false) {
        $dosen_s2++;
        $pendidikan_dist['S2 (Magister)'] = ($pendidikan_dist['S2 (Magister)'] ?? 0) + 1;
    } else {
        $dosen_s1++;
        $pendidikan_dist['S1 (Sarjana)'] = ($pendidikan_dist['S1 (Sarjana)'] ?? 0) + 1;
    }

    if ($dosen_cols['jabatan_fungsional'] && !empty($d['jabatan_fungsional'])) {
        $jabatan_dist[$d['jabatan_fungsional']] = ($jabatan_dist[$d['jabatan_fungsional']] ?? 0) + 1;
    }

    if ($dosen_cols['program_studi_id'] && !empty($d['prodi_nama'] ?? '')) {
        $prodi_dist[$d['prodi_nama']] = ($prodi_dist[$d['prodi_nama']] ?? 0) + 1;
    }
}

// ===== PARAMS =====
$search = trim($_GET['q'] ?? '');
$filter_prodi = $_GET['prodi'] ?? 'all';
$filter_pendidikan = $_GET['pendidikan'] ?? 'all';
$filter_jabatan = $_GET['jabatan'] ?? 'all';
$view = $_GET['view'] ?? 'grid';
$sort = $_GET['sort'] ?? 'name';

// Unique values
$jabatan_list = array_keys($jabatan_dist);
sort($jabatan_list);

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.dosen-hero-extreme {
    position: relative; background: linear-gradient(135deg, #16213e 0%, #0a6847 50%, #064e34 100%);
    color: white; padding: 8rem 0 6rem; overflow: hidden;
}
.dosen-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(245,166,35,0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(59,130,246,0.15) 0%, transparent 50%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
.dosen-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
}
.dosen-hero-extreme .container { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.dosen-hero-extreme .breadcrumb a, .dosen-hero-extreme .breadcrumb span { color: rgba(255,255,255,0.8); }
.dosen-hero-extreme .breadcrumb a:hover { color: white; }

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
.dosen-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem; margin: -4rem auto 3rem; max-width: 1100px;
    position: relative; z-index: 10; padding: 0 1rem;
}
.dosen-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.dosen-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.dosen-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.dosen-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.dosen-stat-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.dosen-stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.dosen-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;
}
.dosen-search { flex: 1; min-width: 240px; position: relative; }
.dosen-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary);
    color: var(--text-primary); transition: all 0.3s;
}
.dosen-search input:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.dosen-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.dosen-search .s-clear {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    width: 22px; height: 22px; background: var(--bg-tertiary); color: var(--text-muted);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; text-decoration: none; transition: all 0.2s;
}
.dosen-search .s-clear:hover { background: #fee2e2; color: #dc2626; }

.dosen-select {
    padding: 0.7rem 1rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.88rem;
    background: var(--bg-secondary); color: var(--text-primary); cursor: pointer;
}
.dosen-select:focus { outline: none; border-color: var(--primary); }

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
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.filter-pill .pill-count {
    background: rgba(255,255,255,0.25); padding: 0.1rem 0.5rem;
    border-radius: 999px; font-size: 0.68rem; font-weight: 800; min-width: 20px;
    text-align: center;
}
.filter-pill:not(.active) .pill-count { background: var(--bg-tertiary); color: var(--text-muted); }

/* ===== GRID VIEW ===== */
.dosen-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}
.dosen-card-pro {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    transition: all 0.4s; position: relative; overflow: hidden;
    display: flex; flex-direction: column; align-items: center; cursor: pointer;
}
.dosen-card-pro::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.dosen-card-pro:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.dosen-card-pro:hover::before { transform: scaleX(1); }

.dosen-avatar-lg {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2.25rem; font-weight: 800; margin-bottom: 1.25rem;
    box-shadow: 0 8px 20px rgba(10,104,71,0.25); border: 4px solid var(--bg-primary);
    overflow: hidden;
}
.dosen-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
.dosen-card-pro h3 {
    font-size: 1.15rem; font-weight: 800; margin-bottom: 0.5rem;
    color: var(--text-primary); line-height: 1.3;
}
.dosen-jabatan {
    display: inline-block; padding: 0.3rem 0.8rem; background: rgba(10,104,71,0.1);
    color: var(--primary); border-radius: 999px; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;
}
.dosen-prodi-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    background: var(--bg-secondary); border-radius: 999px; font-size: 0.85rem;
    color: var(--text-secondary); font-weight: 600; margin-bottom: 0.75rem;
}
.dosen-pendidikan {
    font-size: 0.88rem; color: var(--text-muted); display: flex;
    align-items: center; justify-content: center; gap: 0.4rem; margin-bottom: 0.75rem;
}
.dosen-keahlian {
    font-size: 0.82rem; color: var(--text-secondary); font-style: italic;
    margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}
.btn-view-profile {
    margin-top: auto; width: 100%; padding: 0.75rem; background: var(--bg-secondary);
    color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.btn-view-profile:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* ===== LIST VIEW ===== */
.dosen-list { display: flex; flex-direction: column; gap: 1rem; }
.dosen-list-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s;
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem; align-items: center;
    cursor: pointer;
}
.dosen-list-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.dosen-list-avatar {
    width: 70px; height: 70px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.5rem; flex-shrink: 0; overflow: hidden;
}
.dosen-list-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dosen-list-info h3 {
    font-size: 1.05rem; font-weight: 700; margin-bottom: 0.35rem; color: var(--text-primary);
}
.dosen-list-meta {
    display: flex; gap: 0.75rem; flex-wrap: wrap; font-size: 0.82rem;
    color: var(--text-secondary); margin-bottom: 0.5rem;
}
.dosen-list-meta span { display: flex; align-items: center; gap: 0.3rem; }
.dosen-list-keahlian {
    font-size: 0.82rem; color: var(--text-muted); font-style: italic;
    display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
}
.dosen-list-actions { display: flex; gap: 0.4rem; }
.dosen-action-btn {
    padding: 0.4rem 0.85rem; border-radius: 8px; background: var(--bg-secondary);
    border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.78rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.3rem;
}
.dosen-action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-1px); }

/* ===== TABLE VIEW ===== */
.dosen-table-wrap {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm);
}
.dosen-table { width: 100%; border-collapse: collapse; }
.dosen-table th {
    background: var(--bg-secondary); padding: 1rem 1.25rem; text-align: left;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
}
.dosen-table td {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    font-size: 0.9rem; color: var(--text-primary); vertical-align: middle;
}
.dosen-table tr:last-child td { border-bottom: none; }
.dosen-table tbody tr { transition: all 0.2s; cursor: pointer; }
.dosen-table tbody tr:hover { background: rgba(10,104,71,0.03); }
.dosen-table-cell-name {
    display: flex; align-items: center; gap: 0.75rem;
}
.dosen-table-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.95rem; flex-shrink: 0; overflow: hidden;
}
.dosen-table-avatar img { width: 100%; height: 100%; object-fit: cover; }

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
.modal-content-dosen {
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
.modal-header-dosen {
    padding: 2.5rem 2rem; text-align: center; color: white; position: relative;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-dosen::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
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
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    margin-bottom: 0.35rem; line-height: 1.3;
}
.modal-subtitle { font-size: 0.92rem; opacity: 0.95; margin-bottom: 1rem; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
.modal-meta-ext span {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 600; font-size: 0.82rem;
    border: 1px solid rgba(255,255,255,0.2);
}

.modal-body-dosen { padding: 2rem; }
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
.modal-info-value a { color: var(--primary); text-decoration: none; }
.modal-info-value a:hover { text-decoration: underline; }

.modal-section { margin-bottom: 1.5rem; }
.modal-section h4 {
    font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem;
    display: flex; align-items: center; gap: 0.5rem; font-weight: 700;
}
.modal-section p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.7; }
.modal-section-list { list-style: none; padding: 0; margin: 0; }
.modal-section-list li {
    padding: 0.65rem 0.85rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    margin-bottom: 0.4rem; font-size: 0.88rem; color: var(--text-primary);
    display: flex; align-items: flex-start; gap: 0.65rem;
}
.modal-section-list li::before {
    content: '•'; color: var(--primary); font-weight: 800; font-size: 1.25rem; line-height: 1;
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
}
@media (max-width: 768px) {
    .dosen-hero-extreme { padding: 7rem 0 5rem; }
    .dosen-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .dosen-toolbar { flex-direction: column; align-items: stretch; }
    .view-toggle { width: 100%; }
    .view-btn { flex: 1; justify-content: center; }
    .dosen-grid { grid-template-columns: 1fr; }
    .dosen-list-item { grid-template-columns: 1fr; text-align: center; }
    .dosen-list-avatar { margin: 0 auto; }
    .dosen-list-actions { justify-content: center; }
    .dosen-table-wrap { overflow-x: auto; }
    .dosen-table { min-width: 800px; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .dosen-stats-bar { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="dosen-hero-extreme">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Dosen & Pengajar</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">
            Dosen & Tenaga
            <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Pengajar</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto;" data-aos="fade-up" data-aos-delay="100">
            Dibimbing oleh akademisi berkualifikasi S2 dan S3 dari universitas terkemuka, yang aktif dalam penelitian dan pengabdian masyarakat.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="200">
            <span class="trust-pill">👨‍🏫 <?= $total_dosen ?> Dosen Aktif</span>
            <span class="trust-pill">🎓 <?= $dosen_s3 ?> Doktor</span>
            <span class="trust-pill">📚 <?= count($prodi_list) ?> Program Studi</span>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats -->
        <div class="dosen-stats-bar" data-aos="fade-up">
            <div class="dosen-stat-card" style="--stat-color: #0a6847;">
                <div class="dosen-stat-icon">👨‍🏫</div>
                <div class="dosen-stat-num count-up" data-target="<?= $total_dosen ?>">0</div>
                <div class="dosen-stat-label">Total Dosen</div>
            </div>
            <div class="dosen-stat-card" style="--stat-color: #8b5cf6;">
                <div class="dosen-stat-icon">🎓</div>
                <div class="dosen-stat-num count-up" data-target="<?= $dosen_s3 ?>">0</div>
                <div class="dosen-stat-label">Doktor (S3)</div>
            </div>
            <div class="dosen-stat-card" style="--stat-color: #3b82f6;">
                <div class="dosen-stat-icon">📘</div>
                <div class="dosen-stat-num count-up" data-target="<?= $dosen_s2 ?>">0</div>
                <div class="dosen-stat-label">Magister (S2)</div>
            </div>
            <div class="dosen-stat-card" style="--stat-color: #f59e0b;">
                <div class="dosen-stat-icon">📚</div>
                <div class="dosen-stat-num count-up" data-target="<?= count($prodi_list) ?>">0</div>
                <div class="dosen-stat-label">Program Studi</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="dosen-toolbar" data-aos="fade-up">
            <div class="dosen-search">
                <span class="s-icon">🔍</span>
                <input type="text" id="dosenSearch" placeholder="Cari nama dosen atau bidang keahlian..." value="<?= sanitize($search) ?>">
                <?php if ($search !== ''): ?>
                    <a href="dosen.php?prodi=<?= urlencode($filter_prodi) ?>&pendidikan=<?= urlencode($filter_pendidikan) ?>&jabatan=<?= urlencode($filter_jabatan) ?>&view=<?= urlencode($view) ?>" class="s-clear" title="Clear">✕</a>
                <?php endif; ?>
            </div>
            <select class="dosen-select" id="pendidikanSelect">
                <option value="all">🎓 Semua Pendidikan</option>
                <option value="S3" <?= $filter_pendidikan === 'S3' ? 'selected' : '' ?>>🎓 S3 (Doktor)</option>
                <option value="S2" <?= $filter_pendidikan === 'S2' ? 'selected' : '' ?>>📘 S2 (Magister)</option>
                <option value="S1" <?= $filter_pendidikan === 'S1' ? 'selected' : '' ?>>📗 S1 (Sarjana)</option>
            </select>
            <?php if (!empty($jabatan_list)): ?>
            <select class="dosen-select" id="jabatanSelect">
                <option value="all">🏛️ Semua Jabatan</option>
                <?php foreach ($jabatan_list as $j): ?>
                    <option value="<?= urlencode($j) ?>" <?= $filter_jabatan === $j ? 'selected' : '' ?>><?= sanitize($j) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <div class="view-toggle">
                <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')">🎴 Grid</button>
                <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
                <button class="view-btn <?= $view === 'table' ? 'active' : '' ?>" onclick="switchView('table')">📊 Tabel</button>
                <button class="view-btn <?= $view === 'chart' ? 'active' : '' ?>" onclick="switchView('chart')">📈 Chart</button>
            </div>
        </div>

        <!-- Filter Pills (by Prodi) -->
        <?php if (!empty($prodi_list)): ?>
        <div class="filter-pills" data-aos="fade-up">
            <a class="filter-pill <?= $filter_prodi === 'all' ? 'active' : '' ?>" href="dosen.php?prodi=all&pendidikan=<?= urlencode($filter_pendidikan) ?>&jabatan=<?= urlencode($filter_jabatan) ?>&view=<?= urlencode($view) ?>">
                🎓 Semua Prodi <span class="pill-count"><?= $total_dosen ?></span>
            </a>
            <?php foreach ($prodi_list as $p):
                $cnt = $prodi_dist[$p['nama']] ?? 0;
                if ($cnt === 0 && $filter_prodi !== $p['nama']) continue;
            ?>
            <a class="filter-pill <?= $filter_prodi === $p['nama'] ? 'active' : '' ?>" href="dosen.php?prodi=<?= urlencode($p['nama']) ?>&pendidikan=<?= urlencode($filter_pendidikan) ?>&jabatan=<?= urlencode($filter_jabatan) ?>&view=<?= urlencode($view) ?>">
                <?= sanitize($p['singkatan'] ?? $p['nama']) ?> <span class="pill-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($dosen_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">👨‍🏫</div>
                <h3>Belum ada data dosen</h3>
                <p>Direktori dosen akan segera diperbarui.</p>
            </div>
        <?php else: ?>

            <!-- ===== GRID VIEW ===== -->
            <?php if ($view === 'grid'): ?>
            <div class="dosen-grid" id="dosenGrid" data-aos="fade-up">
                <?php foreach ($dosen_list as $d):
                    $initials = strtoupper(substr($d['nama'], 0, 1) . (strpos($d['nama'], ' ') ? substr($d['nama'], strpos($d['nama'], ' ') + 1, 1) : ''));
                    $full_name = trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? ''));
                ?>
                <div class="dosen-card-pro" onclick='openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($initials) ?>)'>
                    <div class="dosen-avatar-lg">
                        <?php if ($dosen_cols['foto'] && !empty($d['foto'])): ?>
                            <img src="<?= asset('dosen/' . basename($d['foto'])) ?>" alt="<?= sanitize($d['nama']) ?>">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>
                    <h3><?= sanitize($full_name) ?></h3>
                    <?php if ($dosen_cols['jabatan_fungsional'] && !empty($d['jabatan_fungsional'])): ?>
                        <div class="dosen-jabatan"><?= sanitize($d['jabatan_fungsional']) ?></div>
                    <?php endif; ?>
                    <div class="dosen-prodi-badge">🎓 <?= sanitize($d['prodi_nama'] ?? 'Umum') ?></div>
                    <?php if ($dosen_cols['pendidikan_terakhir'] && !empty($d['pendidikan_terakhir'])): ?>
                        <div class="dosen-pendidikan">🎓 <?= sanitize($d['pendidikan_terakhir']) ?></div>
                    <?php endif; ?>
                    <?php if ($dosen_cols['bidang_keahlian'] && !empty($d['bidang_keahlian'])): ?>
                        <div class="dosen-keahlian"><?= sanitize($d['bidang_keahlian']) ?></div>
                    <?php endif; ?>
                    <button class="btn-view-profile" onclick="event.stopPropagation(); openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>, <?= json_encode($initials) ?>)">
                        <span>👁️</span> Lihat Profil Lengkap
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== LIST VIEW ===== -->
            <?php if ($view === 'list'): ?>
            <div class="dosen-list" data-aos="fade-up">
                <?php foreach ($dosen_list as $d):
                    $initials = strtoupper(substr($d['nama'], 0, 1) . (strpos($d['nama'], ' ') ? substr($d['nama'], strpos($d['nama'], ' ') + 1, 1) : ''));
                    $full_name = trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? ''));
                ?>
                <div class="dosen-list-item" onclick='openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($initials) ?>)'>
                    <div class="dosen-list-avatar">
                        <?php if ($dosen_cols['foto'] && !empty($d['foto'])): ?>
                            <img src="<?= asset('dosen/' . basename($d['foto'])) ?>" alt="<?= sanitize($d['nama']) ?>">
                        <?php else: ?>
                            <?= $initials ?>
                        <?php endif; ?>
                    </div>
                    <div class="dosen-list-info">
                        <h3><?= sanitize($full_name) ?></h3>
                        <div class="dosen-list-meta">
                            <?php if ($dosen_cols['jabatan_fungsional'] && !empty($d['jabatan_fungsional'])): ?>
                                <span>🏛️ <?= sanitize($d['jabatan_fungsional']) ?></span>
                            <?php endif; ?>
                            <span>🎓 <?= sanitize($d['prodi_nama'] ?? 'Umum') ?></span>
                            <?php if ($dosen_cols['pendidikan_terakhir'] && !empty($d['pendidikan_terakhir'])): ?>
                                <span>📘 <?= sanitize($d['pendidikan_terakhir']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($dosen_cols['bidang_keahlian'] && !empty($d['bidang_keahlian'])): ?>
                            <div class="dosen-list-keahlian">💡 <?= sanitize($d['bidang_keahlian']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dosen-list-actions" onclick="event.stopPropagation();">
                        <?php if ($dosen_cols['email'] && !empty($d['email'])): ?>
                            <a href="mailto:<?= sanitize($d['email']) ?>" class="dosen-action-btn" onclick="event.stopPropagation();">✉️ Email</a>
                        <?php endif; ?>
                        <button class="dosen-action-btn" onclick='shareDosen(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>)'>🔗 Share</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ===== TABLE VIEW ===== -->
            <?php if ($view === 'table'): ?>
            <div class="dosen-table-wrap" data-aos="fade-up">
                <table class="dosen-table">
                    <thead>
                        <tr>
                            <th>Nama Dosen</th>
                            <?php if ($dosen_cols['jabatan_fungsional']): ?><th>Jabatan</th><?php endif; ?>
                            <?php if ($dosen_cols['program_studi_id']): ?><th>Prodi</th><?php endif; ?>
                            <?php if ($dosen_cols['pendidikan_terakhir']): ?><th>Pendidikan</th><?php endif; ?>
                            <?php if ($dosen_cols['nidn']): ?><th>NIDN</th><?php endif; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dosen_list as $d):
                            $initials = strtoupper(substr($d['nama'], 0, 1));
                            $full_name = trim(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? ''));
                        ?>
                        <tr onclick='openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($initials) ?>)'>
                            <td>
                                <div class="dosen-table-cell-name">
                                    <div class="dosen-table-avatar">
                                        <?php if ($dosen_cols['foto'] && !empty($d['foto'])): ?>
                                            <img src="<?= asset('dosen/' . basename($d['foto'])) ?>" alt="">
                                        <?php else: ?>
                                            <?= $initials ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;"><?= sanitize($full_name) ?></div>
                                        <?php if ($dosen_cols['bidang_keahlian'] && !empty($d['bidang_keahlian'])): ?>
                                            <div style="font-size:0.78rem; color:var(--text-muted);"><?= excerpt($d['bidang_keahlian'], 50) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <?php if ($dosen_cols['jabatan_fungsional']): ?>
                                <td><span class="dosen-jabatan" style="margin:0;"><?= sanitize($d['jabatan_fungsional'] ?? '-') ?></span></td>
                            <?php endif; ?>
                            <?php if ($dosen_cols['program_studi_id']): ?>
                                <td><?= sanitize($d['prodi_singkatan'] ?? $d['prodi_nama'] ?? '-') ?></td>
                            <?php endif; ?>
                            <?php if ($dosen_cols['pendidikan_terakhir']): ?>
                                <td><?= sanitize($d['pendidikan_terakhir'] ?? '-') ?></td>
                            <?php endif; ?>
                            <?php if ($dosen_cols['nidn']): ?>
                                <td style="font-family:monospace; font-size:0.85rem;"><?= sanitize($d['nidn'] ?? '-') ?></td>
                            <?php endif; ?>
                            <td onclick="event.stopPropagation();">
                                <div style="display:flex; gap:0.35rem;">
                                    <button class="dosen-action-btn" onclick='openDosenModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>, <?= json_encode($initials) ?>)'>👁️</button>
                                    <?php if ($dosen_cols['email'] && !empty($d['email'])): ?>
                                        <a href="mailto:<?= sanitize($d['email']) ?>" class="dosen-action-btn" onclick="event.stopPropagation();">✉️</a>
                                    <?php endif; ?>
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
                    <h3>🎓 Distribusi Pendidikan</h3>
                    <div id="pendidikanChart"></div>
                </div>
                <div class="chart-card">
                    <h3>📚 Dosen per Prodi</h3>
                    <div id="prodiChart"></div>
                </div>
                <?php if (!empty($jabatan_list)): ?>
                <div class="chart-card" style="grid-column: 1 / -1;">
                    <h3>🏛️ Distribusi Jabatan Fungsional</h3>
                    <div id="jabatanChart"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<!-- Modal -->
<div class="modal-overlay" id="dosenModal" onclick="if(event.target===this)closeDosenModal()">
    <div class="modal-content-dosen" id="dosenModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== DATA =====
const dosenCols = <?= json_encode($dosen_cols) ?>;
const pendidikanDist = <?= json_encode($pendidikan_dist) ?>;
const prodiDist = <?= json_encode($prodi_dist) ?>;
const jabatanDist = <?= json_encode($jabatan_dist) ?>;

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

// ===== SEARCH & FILTER =====
let searchTimer;
document.getElementById('dosenSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const v = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (v) url.searchParams.set('q', v); else url.searchParams.delete('q');
        window.location = url;
    }, 500);
});

document.getElementById('pendidikanSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('pendidikan', this.value);
    window.location = url;
});

document.getElementById('jabatanSelect')?.addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('jabatan', this.value);
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
function openDosenModal(d, initials) {
    const fullName = ((d.gelar_depan ? d.gelar_depan + ' ' : '') + d.nama + (d.gelar_belakang ? ' ' + d.gelar_belakang : '')).trim();

    // Info grid
    let info_items = '';
    if (dosenCols.program_studi_id && d.prodi_nama) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🎓 Program Studi</div><div class="modal-info-value">${escapeHtml(d.prodi_nama)}</div></div>`;
    }
    if (dosenCols.pendidikan_terakhir && d.pendidikan_terakhir) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📘 Pendidikan</div><div class="modal-info-value">${escapeHtml(d.pendidikan_terakhir)}</div></div>`;
    }
    if (dosenCols.jabatan_fungsional && d.jabatan_fungsional) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🏛️ Jabatan</div><div class="modal-info-value">${escapeHtml(d.jabatan_fungsional)}</div></div>`;
    }
    if (dosenCols.nidn && d.nidn) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🆔 NIDN</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(d.nidn)}</div></div>`;
    }
    if (dosenCols.nip && d.nip) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">🆔 NIP</div><div class="modal-info-value" style="font-family:monospace;">${escapeHtml(d.nip)}</div></div>`;
    }
    if (dosenCols.email && d.email) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📧 Email</div><div class="modal-info-value"><a href="mailto:${escapeHtml(d.email)}">${escapeHtml(d.email)}</a></div></div>`;
    }
    if (dosenCols.telepon && d.telepon) {
        info_items += `<div class="modal-info-item"><div class="modal-info-label">📞 Telepon</div><div class="modal-info-value"><a href="tel:${escapeHtml(d.telepon)}">${escapeHtml(d.telepon)}</a></div></div>`;
    }

    // Sections
    let sections_html = '';
    if (dosenCols.bidang_keahlian && d.bidang_keahlian) {
        sections_html += `<div class="modal-section"><h4>💡 Bidang Keahlian</h4><p>${escapeHtml(d.bidang_keahlian)}</p></div>`;
    }
    if (dosenCols.riwayat_pendidikan && d.riwayat_pendidikan) {
        const lines = d.riwayat_pendidikan.split('\n').filter(l => l.trim());
        if (lines.length > 0) {
            sections_html += `<div class="modal-section"><h4>🎓 Riwayat Pendidikan</h4><ul class="modal-section-list">${lines.map(l => `<li>${escapeHtml(l)}</li>`).join('')}</ul></div>`;
        }
    }
    if (dosenCols.penelitian && d.penelitian) {
        const lines = d.penelitian.split('\n').filter(l => l.trim());
        if (lines.length > 0) {
            sections_html += `<div class="modal-section"><h4>🔬 Penelitian</h4><ul class="modal-section-list">${lines.map(l => `<li>${escapeHtml(l)}</li>`).join('')}</ul></div>`;
        }
    }
    if (dosenCols.publikasi && d.publikasi) {
        const lines = d.publikasi.split('\n').filter(l => l.trim());
        if (lines.length > 0) {
            sections_html += `<div class="modal-section"><h4>📄 Publikasi</h4><ul class="modal-section-list">${lines.map(l => `<li>${escapeHtml(l)}</li>`).join('')}</ul></div>`;
        }
    }
    if (dosenCols.pengabdian && d.pengabdian) {
        const lines = d.pengabdian.split('\n').filter(l => l.trim());
        if (lines.length > 0) {
            sections_html += `<div class="modal-section"><h4>🤝 Pengabdian Masyarakat</h4><ul class="modal-section-list">${lines.map(l => `<li>${escapeHtml(l)}</li>`).join('')}</ul></div>`;
        }
    }
    if (dosenCols.sertifikasi && d.sertifikasi) {
        sections_html += `<div class="modal-section"><h4>🏆 Sertifikasi</h4><p>${escapeHtml(d.sertifikasi)}</p></div>`;
    }

    const html = `
        <button class="modal-close" onclick="closeDosenModal()">✕</button>
        <div class="modal-header-dosen">
            <div class="modal-header-content">
                <div class="modal-avatar">
                    ${dosenCols.foto && d.foto ? `<img src="${'<?= asset('dosen/') ?>' + encodeURIComponent(d.foto.split('/').pop())}" alt="${escapeHtml(d.nama)}">` : initials}
                </div>
                <h2 class="modal-name">${escapeHtml(fullName)}</h2>
                <div class="modal-subtitle">${dosenCols.jabatan_fungsional && d.jabatan_fungsional ? escapeHtml(d.jabatan_fungsional) : 'Dosen FKIP UNIMOF'}</div>
                <div class="modal-meta-ext">
                    ${dosenCols.program_studi_id && d.prodi_nama ? `<span>🎓 ${escapeHtml(d.prodi_nama)}</span>` : ''}
                    ${dosenCols.pendidikan_terakhir && d.pendidikan_terakhir ? `<span>📘 ${escapeHtml(d.pendidikan_terakhir)}</span>` : ''}
                </div>
            </div>
        </div>
        <div class="modal-body-dosen">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}
            ${sections_html}
        </div>
        <div class="modal-footer">
            <button class="modal-btn secondary" onclick='shareDosen(${JSON.stringify(d).replace(/"/g, "&quot;")})'>🔗 Share</button>
            ${dosenCols.email && d.email ? `<a href="mailto:${escapeHtml(d.email)}" class="modal-btn secondary">✉️ Email</a>` : ''}
            ${dosenCols.telepon && d.telepon ? `<a href="tel:${escapeHtml(d.telepon)}" class="modal-btn secondary">📞 Telepon</a>` : ''}
            <button class="modal-btn primary" onclick="closeDosenModal()">Tutup</button>
        </div>
    `;
    document.getElementById('dosenModalContent').innerHTML = html;
    document.getElementById('dosenModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDosenModal() {
    document.getElementById('dosenModal').classList.remove('show');
    document.body.style.overflow = '';
}

// ===== SHARE =====
function shareDosen(d) {
    const fullName = ((d.gelar_depan ? d.gelar_depan + ' ' : '') + d.nama + (d.gelar_belakang ? ' ' + d.gelar_belakang : '')).trim();
    const text = `👨‍🏫 ${fullName}\n${dosenCols.jabatan_fungsional && d.jabatan_fungsional ? '🏛️ ' + d.jabatan_fungsional + '\n' : ''}${dosenCols.program_studi_id && d.prodi_nama ? '🎓 ' + d.prodi_nama + '\n' : ''}${dosenCols.pendidikan_terakhir && d.pendidikan_terakhir ? '📘 ' + d.pendidikan_terakhir + '\n' : ''}\nDosen FKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: fullName, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info dosen disalin', '📋');
    }
}

// ===== CHARTS =====
<?php if ($view === 'chart'): ?>
const pendColors = {'S3 (Doktor)': '#8b5cf6', 'S2 (Magister)': '#3b82f6', 'S1 (Sarjana)': '#10b981'};
const prodiColors = ['#0a6847', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899', '#06b6d4'];
const jabatanColors = ['#0a6847', '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899'];

if (Object.keys(pendidikanDist).length > 0) {
    new ApexCharts(document.querySelector("#pendidikanChart"), {
        series: Object.values(pendidikanDist),
        labels: Object.keys(pendidikanDist),
        chart: { type: 'donut', height: 320 },
        colors: Object.keys(pendidikanDist).map(k => pendColors[k] || '#6b7280'),
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: { show: true, total: { show: true, label: 'Total', formatter: () => Object.values(pendidikanDist).reduce((a,b)=>a+b,0) } }
                }
            }
        },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        legend: { position: 'bottom', fontSize: '11px' }
    }).render();
}

if (Object.keys(prodiDist).length > 0) {
    new ApexCharts(document.querySelector("#prodiChart"), {
        series: [{ data: Object.values(prodiDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: prodiColors.slice(0, Object.keys(prodiDist).length),
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%', horizontal: true } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(prodiDist), labels: { style: { fontSize: '11px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}

if (Object.keys(jabatanDist).length > 0) {
    new ApexCharts(document.querySelector("#jabatanChart"), {
        series: [{ name: 'Dosen', data: Object.values(jabatanDist) }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        colors: jabatanColors.slice(0, Object.keys(jabatanDist).length),
        plotOptions: { bar: { borderRadius: 8, columnWidth: '50%' } },
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: Object.keys(jabatanDist), labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { style: { fontSize: '11px' } } }
    }).render();
}
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('dosenSearch')?.focus();
    }
    if (e.key === 'Escape') closeDosenModal();
});

console.log('%c👨‍🏫 Dosen FKIP UNIMOF - EXTREME MULTIMATE', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcuts: / (Search) • ESC (Close modal)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>