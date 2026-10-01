<?php
require_once __DIR__ . '/includes/config.php';

// ===== SCHEMA-SAFE =====
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
        'updated_at'         => in_array('updated_at', $cols, true),
        'website'            => in_array('website', $cols, true),
        'email'              => in_array('email', $cols, true),
        'telepon'            => in_array('telepon', $cols, true),
        'biaya_kuliah'       => in_array('biaya_kuliah', $cols, true),
        'durasi'             => in_array('durasi', $cols, true),
        'total_sks'          => in_array('total_sks', $cols, true),
        'fasilitas'          => in_array('fasilitas', $cols, true),
        'gallery'            => in_array('gallery', $cols, true),
    ];
} catch (Exception $e) {
    $prodi_cols = array_fill_keys(['nama','singkatan','jenjang','akreditasi','deskripsi','visi','misi','kurikulum','prospek_kerja','logo','banner','ketua_prodi','kode','jumlah_dosen','jumlah_mahasiswa','status','updated_at','website','email','telepon','biaya_kuliah','durasi','total_sks','fasilitas','gallery'], false);
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { header('Location: ' . base_url('program.php')); exit; }

try {
    $where = "WHERE id = ?";
    if ($prodi_cols['status']) $where .= " AND status = 'Aktif'";
    $stmt = $pdo->prepare("SELECT * FROM program_studi $where");
    $stmt->execute([$id]);
    $prodi = $stmt->fetch();

    if (!$prodi) { header('Location: ' . base_url('program.php')); exit; }

    // Dosen (top 6)
    $dosen = [];
    try {
        $stmtDosen = $pdo->prepare("SELECT * FROM dosen WHERE program_studi_id = ? AND status = 'Aktif' ORDER BY jabatan_fungsional DESC LIMIT 6");
        $stmtDosen->execute([$id]);
        $dosen = $stmtDosen->fetchAll();
    } catch (Exception $e) {}

    // Prestasi (top 5)
    $prestasi = [];
    try {
        $stmtPrestasi = $pdo->prepare("SELECT * FROM prestasi WHERE program_studi_id = ? ORDER BY tahun DESC, tingkat DESC LIMIT 5");
        $stmtPrestasi->execute([$id]);
        $prestasi = $stmtPrestasi->fetchAll();
    } catch (Exception $e) {}

    // Alumni (top 3)
    $alumni = [];
    try {
        $stmtAlumni = $pdo->prepare("SELECT * FROM alumni WHERE program_studi_id = ? AND status = 'Aktif' ORDER BY tahun_lulus DESC LIMIT 3");
        $stmtAlumni->execute([$id]);
        $alumni = $stmtAlumni->fetchAll();
    } catch (Exception $e) {}

    // Berita terkait (top 3)
    $berita = [];
    try {
        $stmtBerita = $pdo->prepare("SELECT * FROM berita WHERE status = 'Published' ORDER BY published_at DESC LIMIT 3");
        $stmtBerita->execute();
        $berita = $stmtBerita->fetchAll();
    } catch (Exception $e) {}

} catch (Exception $e) { error_log($e->getMessage()); die('Terjadi kesalahan.'); }

$page_title = sanitize($prodi['nama']);
$page_description = "Program Studi " . $prodi['nama'] . " - " . ($prodi['jenjang'] ?? '') . " - FKIP UNIMOF";

$theme_colors = [
    1 => ['#3b82f6', '#1d4ed8'], 2 => ['#8b5cf6', '#6d28d9'],
    3 => ['#10b981', '#059669'], 4 => ['#f59e0b', '#d97706'],
    5 => ['#ec4899', '#db2777'], 6 => ['#ef4444', '#dc2626'],
    7 => ['#14b8a6', '#0d9488'], 8 => ['#f97316', '#ea580c'],
    9 => ['#06b6d4', '#0891b2'], 10 => ['#84cc16', '#65a30d'],
];
$colors = $theme_colors[$prodi['id'] % 11] ?? ['#0a6847', '#084d35'];

// Default active tab from hash
$default_tab = 'visi';

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO ===== */
.detail-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, <?= $colors[0] ?> 0%, <?= $colors[1] ?> 100%);
    color: white; padding: 8rem 0 5rem;
}
.detail-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 40%),
                      radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 40%);
    pointer-events: none;
}
.detail-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px; pointer-events: none;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px;
    background: rgba(255,255,255,0.5); border-radius: 50%;
    animation: particleFloat 25s infinite linear;
}
@keyframes particleFloat {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.7; } 90% { opacity: 0.7; }
    100% { transform: translateY(-10vh) translateX(30px); opacity: 0; }
}
.detail-hero .container { position: relative; z-index: 2; }
.detail-hero .breadcrumb a, .detail-hero .breadcrumb span { color: rgba(255,255,255,0.8); }
.detail-hero .breadcrumb a:hover { color: white; }
.detail-hero .page-title {
    font-family: var(--font-display); font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;
}
.detail-hero .page-subtitle { font-size: 1.2rem; opacity: 0.95; max-width: 700px; line-height: 1.7; }

.page-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.5rem 1rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;
    text-transform: uppercase; letter-spacing: 0.05em;
}

.update-chip {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.25rem 0.65rem; background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
    border-radius: 999px; font-size: 0.72rem; font-weight: 600; color: white;
    margin-left: 0.75rem;
}
.update-chip .dot {
    width: 6px; height: 6px; border-radius: 50%; background: #fbbf24;
    animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.hero-stats-row {
    display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 2rem;
}
.hero-stat-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; background: rgba(255,255,255,0.12);
    backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
    border-radius: 999px; font-size: 0.85rem; font-weight: 600;
}
.hero-stat-pill strong { font-size: 1.05rem; font-weight: 800; }

.hero-actions {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 2rem;
}
.hero-btn {
    padding: 0.85rem 1.5rem; border-radius: var(--radius-md);
    font-weight: 700; font-size: 0.95rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.5rem;
    text-decoration: none; transition: all 0.3s; font-family: inherit;
    border: none;
}
.hero-btn.primary {
    background: white; color: <?= $colors[0] ?>;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}
.hero-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
.hero-btn.secondary {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px);
}
.hero-btn.secondary:hover { background: rgba(255,255,255,0.25); }

.detail-hero-banner { position: absolute; inset: 0; z-index: 0; }
.detail-hero-banner img { width: 100%; height: 100%; object-fit: cover; opacity: 0.25; }
.detail-hero-banner::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, <?= $colors[0] ?>cc 0%, <?= $colors[1] ?>cc 100%);
}

/* ===== LAYOUT ===== */
.detail-layout {
    display: grid; grid-template-columns: 1fr 360px; gap: 2.5rem;
    margin-top: -3rem; position: relative; z-index: 10;
}

/* ===== TABS ===== */
.detail-tabs {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-lg);
}
.tab-nav {
    display: flex; border-bottom: 1px solid var(--border);
    background: var(--bg-secondary); overflow-x: auto; scrollbar-width: none;
}
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn {
    flex: 1; padding: 1.15rem 1.25rem; background: none; border: none;
    font-family: inherit; font-size: 0.88rem; font-weight: 600;
    color: var(--text-muted); cursor: pointer; position: relative;
    transition: all 0.3s; white-space: nowrap;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
}
.tab-btn:hover { color: var(--text-primary); background: rgba(0,0,0,0.02); }
.tab-btn.active { color: <?= $colors[0] ?>; background: var(--bg-primary); }
.tab-btn.active::after {
    content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    background: <?= $colors[0] ?>; border-radius: 3px 3px 0 0;
}
.tab-content { padding: 2rem; display: none; animation: fadeIn 0.4s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

.tab-content h3 {
    font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary);
    margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
}
.tab-content p, .tab-content li { color: var(--text-secondary); line-height: 1.8; font-size: 1rem; margin-bottom: 1rem; }
.tab-content ul { padding-left: 1.5rem; }
.tab-content li { margin-bottom: 0.5rem; }
.tab-content li::marker { color: <?= $colors[0] ?>; }

.visi-box {
    font-style: italic; font-size: 1.1rem; color: var(--text-primary);
    border-left: 4px solid <?= $colors[0] ?>; padding: 1.5rem;
    background: var(--bg-secondary); border-radius: 0 var(--radius-md) var(--radius-md) 0;
    line-height: 1.7; margin-bottom: 2rem;
}

/* ===== KURIKULUM INFO ===== */
.kurikulum-info-box {
    background: var(--bg-secondary); border: 1px dashed var(--border);
    border-radius: var(--radius-md); padding: 1.5rem; text-align: center;
    margin-top: 1.5rem;
}
.kurikulum-info-box h4 {
    font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-primary);
}
.kurikulum-info-box p { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem; }

/* ===== PROSPEK GRID ===== */
.prospek-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem; margin-top: 1.5rem;
}
.prospek-card {
    padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    display: flex; align-items: flex-start; gap: 0.75rem; border: 1px solid var(--border);
    transition: all 0.3s;
}
.prospek-card:hover { border-color: <?= $colors[0] ?>; transform: translateY(-3px); box-shadow: var(--shadow-md); }
.prospek-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; flex-shrink: 0;
}
.prospek-text h5 { font-size: 0.92rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary); }
.prospek-text p { font-size: 0.78rem; color: var(--text-muted); margin: 0; line-height: 1.4; }

/* ===== DOSEN GRID ===== */
.dosen-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
}
.dosen-card {
    display: flex; gap: 1rem; padding: 1.25rem; background: var(--bg-secondary);
    border-radius: var(--radius-lg); border: 1px solid var(--border);
    transition: all 0.3s;
}
.dosen-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: <?= $colors[0] ?>; background: var(--bg-primary); }
.dosen-avatar {
    width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
}
.dosen-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dosen-info h4 { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; line-height: 1.3; }
.dosen-info p { font-size: 0.78rem; color: var(--text-muted); margin: 0; line-height: 1.4; }
.dosen-info .dosen-edu { font-size: 0.72rem; color: <?= $colors[0] ?>; font-weight: 600; margin-top: 0.25rem; }

/* ===== FASILITAS ===== */
.fasilitas-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.75rem;
}
.fasilitas-item {
    padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border: 1px solid var(--border); text-align: center; transition: all 0.2s;
}
.fasilitas-item:hover { border-color: <?= $colors[0] ?>; transform: translateY(-2px); }
.fasilitas-icon { font-size: 1.75rem; margin-bottom: 0.4rem; }
.fasilitas-name { font-size: 0.82rem; font-weight: 600; color: var(--text-primary); }

/* ===== GALLERY ===== */
.gallery-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem; margin-top: 1rem;
}
.gallery-item {
    aspect-ratio: 4/3; border-radius: var(--radius-md); overflow: hidden;
    cursor: pointer; transition: all 0.3s; position: relative;
    background: var(--bg-secondary);
}
.gallery-item:hover { transform: scale(1.03); box-shadow: var(--shadow-md); }
.gallery-item img { width: 100%; height: 100%; object-fit: cover; }
.gallery-placeholder {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; font-size: 2rem; opacity: 0.5;
}

/* ===== ALUMNI LIST ===== */
.alumni-list { display: flex; flex-direction: column; gap: 1rem; }
.alumni-item {
    display: flex; gap: 1rem; padding: 1.25rem; background: var(--bg-secondary);
    border-radius: var(--radius-lg); border: 1px solid var(--border);
    transition: all 0.3s;
}
.alumni-item:hover { transform: translateX(4px); border-color: <?= $colors[0] ?>; }
.alumni-avatar {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; flex-shrink: 0; overflow: hidden;
}
.alumni-avatar img { width: 100%; height: 100%; object-fit: cover; }
.alumni-info { flex: 1; min-width: 0; }
.alumni-info h5 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary); }
.alumni-info p { font-size: 0.82rem; color: var(--text-muted); margin: 0; line-height: 1.4; }
.alumni-info .alumni-job { color: <?= $colors[0] ?>; font-weight: 600; font-size: 0.78rem; margin-top: 0.25rem; }

/* ===== FAQ ===== */
.faq-list { display: flex; flex-direction: column; gap: 0.75rem; }
.faq-item {
    background: var(--bg-secondary); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
}
.faq-question {
    padding: 1rem 1.25rem; cursor: pointer; font-weight: 600;
    color: var(--text-primary); display: flex; justify-content: space-between;
    align-items: center; gap: 0.75rem; transition: all 0.2s;
}
.faq-question:hover { background: var(--bg-tertiary); }
.faq-question .arrow { transition: transform 0.3s; font-size: 1.1rem; }
.faq-item.open .faq-question .arrow { transform: rotate(180deg); }
.faq-answer {
    max-height: 0; overflow: hidden; transition: max-height 0.3s ease;
    padding: 0 1.25rem;
}
.faq-item.open .faq-answer {
    max-height: 500px; padding: 0 1.25rem 1.25rem;
}
.faq-answer p { margin: 0; font-size: 0.92rem; color: var(--text-secondary); line-height: 1.7; }

/* ===== SIDEBAR ===== */
.detail-sidebar { position: sticky; top: 100px; height: fit-content; }
.sidebar-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm); transition: all 0.3s;
}
.sidebar-card:hover { box-shadow: var(--shadow-md); }
.sidebar-card h3 {
    font-family: var(--font-display); font-size: 1.1rem; margin-bottom: 1.25rem;
    padding-bottom: 0.75rem; border-bottom: 2px solid var(--bg-tertiary);
    display: flex; align-items: center; gap: 0.5rem;
}

.info-list { list-style: none; padding: 0; margin: 0; }
.info-list li {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.85rem 0; border-bottom: 1px dashed var(--border); font-size: 0.88rem;
}
.info-list li:last-child { border-bottom: none; }
.info-list li span:first-child { color: var(--text-muted); }
.info-list li strong { color: var(--text-primary); text-align: right; }

.prodi-logo-wrap {
    display: flex; justify-content: center; margin-bottom: 1.25rem;
    padding-bottom: 1.25rem; border-bottom: 1px dashed var(--border);
}
.prodi-logo {
    width: 90px; height: 90px; border-radius: 20px; object-fit: cover;
    box-shadow: var(--shadow-md); border: 2px solid var(--border);
}

/* ===== PRESTASI ===== */
.prestasi-list { display: flex; flex-direction: column; gap: 0.85rem; }
.prestasi-item {
    display: flex; gap: 0.85rem; align-items: flex-start; padding: 0.85rem;
    background: var(--bg-secondary); border-radius: var(--radius-md);
    border-left: 3px solid <?= $colors[0] ?>; transition: all 0.3s;
}
.prestasi-item:hover { background: var(--bg-tertiary); transform: translateX(4px); }
.prestasi-medal {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0; box-shadow: 0 4px 10px rgba(245,158,11,0.3);
}
.prestasi-info h5 {
    font-size: 0.88rem; font-weight: 700; color: var(--text-primary);
    margin-bottom: 0.2rem; line-height: 1.3;
}
.prestasi-info small { font-size: 0.72rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.3rem; }

/* ===== CTA CARD ===== */
.cta-card {
    background: linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>);
    color: white; border: none; text-align: center;
}
.cta-card h3 { color: white; border-bottom-color: rgba(255,255,255,0.2); justify-content: center; }
.cta-card p { font-size: 0.88rem; opacity: 0.95; margin-bottom: 1.25rem; line-height: 1.6; }
.cta-card .btn-primary {
    background: white; color: <?= $colors[0] ?>; font-weight: 700;
    width: 100%; justify-content: center;
}
.cta-card .btn-primary:hover { background: var(--bg-secondary); transform: translateY(-2px); }

/* ===== SHARE BOX ===== */
.share-box {
    padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    margin-top: 1rem;
}
.share-box h4 { font-size: 0.82rem; margin-bottom: 0.75rem; color: var(--text-muted); }
.share-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.share-btn {
    width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
    transition: all 0.2s; color: white;
}
.share-btn.wa { background: #25D366; }
.share-btn.fb { background: #1877F2; }
.share-btn.tw { background: #1DA1F2; }
.share-btn.li { background: #0A66C2; }
.share-btn.copy { background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border); }
.share-btn:hover { transform: translateY(-2px); filter: brightness(1.1); box-shadow: var(--shadow-sm); }

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

/* ===== EMPTY ===== */
.empty-state-pro { text-align: center; padding: 2rem; }

@media (max-width: 968px) {
    .detail-layout { grid-template-columns: 1fr; }
    .detail-sidebar { position: static; order: -1; }
    .detail-hero { padding: 6rem 0 4rem; }
    .dosen-grid, .prospek-grid { grid-template-columns: 1fr; }
    .hero-stats-row { justify-content: flex-start; }
}
</style>

<!-- ===== HERO ===== -->
<section class="detail-hero">
    <div class="hero-particles" id="heroParticles"></div>
    <?php if ($prodi_cols['banner'] && !empty($prodi['banner'])): ?>
    <div class="detail-hero-banner">
        <img src="<?= asset('uploads/prodi_banner/' . basename($prodi['banner'])) ?>" alt="Banner">
    </div>
    <?php endif; ?>
    <div class="container">
        <nav class="breadcrumb" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span>
            <a href="<?= base_url('program.php') ?>">Program Studi</a><span>›</span>
            <span><?= sanitize($prodi['singkatan'] ?? $prodi['nama']) ?></span>
        </nav>

        <div class="page-hero-content" data-aos="fade-up">
            <span class="page-badge">
                <span>🎓</span>
                <?= sanitize($prodi['jenjang'] ?? 'Program Studi') ?>
                <?php if ($prodi_cols['akreditasi'] && !empty($prodi['akreditasi'])): ?>
                    • Akreditasi: <?= sanitize($prodi['akreditasi']) ?>
                <?php endif; ?>
                <?php if ($prodi_cols['updated_at'] && !empty($prodi['updated_at'])): ?>
                <span class="update-chip">
                    <span class="dot"></span>
                    Update: <?= date('d M Y', strtotime($prodi['updated_at'])) ?>
                </span>
                <?php endif; ?>
            </span>
            <h1 class="page-title"><?= sanitize($prodi['nama']) ?></h1>
            <p class="page-subtitle">
                <?= !empty($prodi['deskripsi']) ? sanitize(excerpt($prodi['deskripsi'], 200)) : 'Membentuk pendidik profesional yang menguasai keilmuan, inovatif, dan memiliki karakter Islami untuk membangun peradaban.' ?>
            </p>

            <div class="hero-stats-row" data-aos="fade-up" data-aos-delay="100">
                <?php if ($prodi_cols['jumlah_dosen'] && !empty($prodi['jumlah_dosen'])): ?>
                <span class="hero-stat-pill">👨‍🏫 <strong><?= (int)$prodi['jumlah_dosen'] ?></strong> Dosen</span>
                <?php endif; ?>
                <?php if ($prodi_cols['jumlah_mahasiswa'] && !empty($prodi['jumlah_mahasiswa'])): ?>
                <span class="hero-stat-pill">👨‍🎓 <strong><?= (int)$prodi['jumlah_mahasiswa'] ?></strong> Mahasiswa</span>
                <?php endif; ?>
                <?php if ($prodi_cols['total_sks'] && !empty($prodi['total_sks'])): ?>
                <span class="hero-stat-pill">📚 <strong><?= (int)$prodi['total_sks'] ?></strong> SKS</span>
                <?php endif; ?>
                <?php if ($prodi_cols['durasi'] && !empty($prodi['durasi'])): ?>
                <span class="hero-stat-pill">⏱️ <strong><?= sanitize($prodi['durasi']) ?></strong></span>
                <?php endif; ?>
            </div>

            <div class="hero-actions" data-aos="fade-up" data-aos-delay="200">
                <a href="<?= base_url('pmb.php') ?>" class="hero-btn primary">📝 Daftar Sekarang</a>
                <a href="https://wa.me/6281234567890?text=Halo,%20saya%20ingin%20bertanya%20tentang%20Prodi%20<?= urlencode($prodi['nama']) ?>" target="_blank" class="hero-btn secondary">💬 Tanya via WA</a>
                <button class="hero-btn secondary" onclick="shareProdi()">🔗 Share</button>
            </div>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="detail-layout">

            <!-- MAIN CONTENT -->
            <main class="detail-main">
                <div class="detail-tabs" data-aos="fade-up">
                    <div class="tab-nav">
                        <button class="tab-btn active" data-tab="tab-visi" onclick="switchTab(event, 'tab-visi')">🎯 Visi & Misi</button>
                        <button class="tab-btn" data-tab="tab-kurikulum" onclick="switchTab(event, 'tab-kurikulum')">📚 Kurikulum</button>
                        <button class="tab-btn" data-tab="tab-prospek" onclick="switchTab(event, 'tab-prospek')">💼 Prospek Karir</button>
                        <button class="tab-btn" data-tab="tab-dosen" onclick="switchTab(event, 'tab-dosen')">👨‍🏫 Dosen</button>
                        <button class="tab-btn" data-tab="tab-fasilitas" onclick="switchTab(event, 'tab-fasilitas')">🏢 Fasilitas</button>
                        <button class="tab-btn" data-tab="tab-alumni" onclick="switchTab(event, 'tab-alumni')">🎓 Alumni</button>
                        <button class="tab-btn" data-tab="tab-faq" onclick="switchTab(event, 'tab-faq')">❓ FAQ</button>
                    </div>

                    <!-- VISI & MISI -->
                    <div id="tab-visi" class="tab-content active">
                        <h3>🎯 Visi</h3>
                        <div class="visi-box">
                            <?= nl2br(sanitize($prodi['visi'] ?? 'Menjadi program studi unggul yang menghasilkan lulusan berkompeten, berkarakter Islami, dan berdaya saing global pada tahun 2030.')) ?>
                        </div>

                        <h3>🚀 Misi</h3>
                        <ul>
                            <?php
                            $misi_lines = explode("\n", sanitize($prodi['misi'] ?? "1. Menyelenggarakan pendidikan yang berkualitas.\n2. Melaksanakan penelitian yang inovatif.\n3. Mengabdi kepada masyarakat.\n4. Menanamkan nilai-nilai Islam dan kearifan lokal."));
                            foreach ($misi_lines as $line):
                                if (trim($line) !== ''):
                            ?>
                                <li><?= trim($line) ?></li>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </ul>

                        <h3 style="margin-top: 2rem;">🎯 Tujuan Program Studi</h3>
                        <ul>
                            <li>Menghasilkan lulusan yang kompeten dan siap bersaing di dunia kerja</li>
                            <li>Menghasilkan karya penelitian yang bermanfaat bagi masyarakat</li>
                            <li>Menjalin kerjasama strategis dengan berbagai pemangku kepentingan</li>
                            <li>Menanamkan nilai-nilai keislaman dan kebangsaan</li>
                        </ul>
                    </div>

                    <!-- KURIKULUM -->
                    <div id="tab-kurikulum" class="tab-content">
                        <h3>📚 Struktur Kurikulum</h3>
                        <p><?= nl2br(sanitize($prodi['kurikulum'] ?? 'Kurikulum program studi ini dirancang berbasis KKNI dengan total 144-146 SKS yang ditempuh dalam 8 semester. Kurikulum terdiri dari Mata Kuliah Wajib Universitas, Mata Kuliah Dasar Keilmuan, Mata Kuliah Keahlian, Magang, dan Skripsi.')) ?></p>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-top:1.5rem;">
                            <div style="padding:1.25rem; background:var(--bg-secondary); border-radius:var(--radius-md); text-align:center; border:1px solid var(--border);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">📖</div>
                                <div style="font-weight:800; font-size:1.5rem; color:<?= $colors[0] ?>;"><?= (int)($prodi['total_sks'] ?? 144) ?></div>
                                <div style="font-size:0.82rem; color:var(--text-muted);">Total SKS</div>
                            </div>
                            <div style="padding:1.25rem; background:var(--bg-secondary); border-radius:var(--radius-md); text-align:center; border:1px solid var(--border);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">📅</div>
                                <div style="font-weight:800; font-size:1.5rem; color:<?= $colors[0] ?>;">8</div>
                                <div style="font-size:0.82rem; color:var(--text-muted);">Semester</div>
                            </div>
                            <div style="padding:1.25rem; background:var(--bg-secondary); border-radius:var(--radius-md); text-align:center; border:1px solid var(--border);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">🎓</div>
                                <div style="font-weight:800; font-size:1.5rem; color:<?= $colors[0] ?>;"><?= sanitize($prodi['jenjang'] ?? 'S1') ?></div>
                                <div style="font-size:0.82rem; color:var(--text-muted);">Jenjang</div>
                            </div>
                            <div style="padding:1.25rem; background:var(--bg-secondary); border-radius:var(--radius-md); text-align:center; border:1px solid var(--border);">
                                <div style="font-size:2rem; margin-bottom:0.5rem;">💼</div>
                                <div style="font-weight:800; font-size:1.5rem; color:<?= $colors[0] ?>;">1</div>
                                <div style="font-size:0.82rem; color:var(--text-muted);">Semester Magang</div>
                            </div>
                        </div>

                        <div class="kurikulum-info-box">
                            <h4>📥 Kurikulum Lengkap</h4>
                            <p>Ingin melihat detail mata kuliah per semester? Hubungi prodi untuk mendapatkan dokumen kurikulum lengkap.</p>
                            <a href="<?= base_url('kontak.php') ?>" class="btn btn-secondary btn-sm">Hubungi Prodi</a>
                        </div>
                    </div>

                    <!-- PROSPEK KARIR -->
                    <div id="tab-prospek" class="tab-content">
                        <h3>💼 Prospek Karir Lulusan</h3>
                        <p>Lulusan program studi ini memiliki kompetensi yang luas dan siap berkarir di berbagai bidang, antara lain:</p>

                        <div class="prospek-grid">
                            <?php
                            $prospek_icons = ['👨‍🏫', '🔬', '📚', '✍️', '🚀', '🎓', '🏛️', '🤝', '💼', '📊'];
                            $prospek_list = explode("\n", sanitize($prodi['prospek_kerja'] ?? "Guru/Dosen Profesional\nPeneliti Pendidikan\nKonsultan Kurikulum\nPengembang Bahan Ajar\nEntrepreneur Pendidikan\nTenaga Pendidik Non-Formal"));
                            foreach ($prospek_list as $i => $prospek):
                                if (trim($prospek) !== ''):
                                    $icon = $prospek_icons[$i % count($prospek_icons)];
                            ?>
                                <div class="prospek-card">
                                    <div class="prospek-icon"><?= $icon ?></div>
                                    <div class="prospek-text">
                                        <h5><?= trim($prospek) ?></h5>
                                        <p>Peluang karir menjanjikan</p>
                                    </div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <!-- DOSEN -->
                    <div id="tab-dosen" class="tab-content">
                        <h3>👨‍🏫 Tenaga Pengajar</h3>
                        <p style="margin-bottom: 1.5rem;">Dibimbing oleh dosen-dosen berkualifikasi S2 dan S3 dari universitas terkemuka.</p>
                        <?php if (empty($dosen)): ?>
                            <div class="empty-state-pro">
                                <div style="font-size:3rem; opacity:0.5;">👨‍🏫</div>
                                <p style="color:var(--text-muted);">Data dosen sedang dalam proses pemutakhiran.</p>
                            </div>
                        <?php else: ?>
                            <div class="dosen-grid">
                                <?php foreach ($dosen as $d): ?>
                                <div class="dosen-card">
                                    <div class="dosen-avatar">
                                        <?php if (!empty($d['foto'])): ?>
                                            <img src="<?= asset('dosen/' . basename($d['foto'])) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dosen-info">
                                        <h4><?= sanitize(($d['gelar_depan'] ?? '') . ' ' . $d['nama'] . ' ' . ($d['gelar_belakang'] ?? '')) ?></h4>
                                        <p><?= sanitize($d['jabatan_fungsional'] ?? 'Dosen') ?></p>
                                        <?php if (!empty($d['pendidikan_terakhir'])): ?>
                                            <div class="dosen-edu">🎓 <?= sanitize($d['pendidikan_terakhir']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:1.5rem; text-align:center;">
                                <a href="<?= base_url('dosen.php?prodi=' . urlencode($prodi['nama'])) ?>" class="btn btn-secondary">Lihat Semua Dosen →</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FASILITAS -->
                    <div id="tab-fasilitas" class="tab-content">
                        <h3>🏢 Fasilitas Penunjang</h3>
                        <p>Program studi ini didukung oleh berbagai fasilitas modern untuk menunjang proses belajar mengajar.</p>

                        <?php
                        $fasilitas_list = [];
                        if ($prodi_cols['fasilitas'] && !empty($prodi['fasilitas'])) {
                            $fasilitas_list = array_filter(array_map('trim', explode("\n", $prodi['fasilitas'])));
                        }
                        if (empty($fasilitas_list)) {
                            $fasilitas_list = ['Laboratorium Komputer', 'Perpustakaan Digital', 'Ruang Kelas Ber-AC', 'Studio Microteaching', 'Wi-Fi Kampus', 'Ruang Diskusi'];
                        }
                        $fas_icons = ['💻', '📚', '❄️', '🎤', '📶', '🗣️', '🧪', '🎨', '🎭', '🏟️'];
                        ?>
                        <div class="fasilitas-grid">
                            <?php foreach ($fasilitas_list as $i => $f): ?>
                            <div class="fasilitas-item">
                                <div class="fasilitas-icon"><?= $fas_icons[$i % count($fas_icons)] ?></div>
                                <div class="fasilitas-name"><?= sanitize($f) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="margin-top: 2rem;">🖼️ Galeri Kegiatan</h3>
                        <div class="gallery-grid">
                            <?php
                            $gallery = [];
                            if ($prodi_cols['gallery'] && !empty($prodi['gallery'])) {
                                try { $gallery = json_decode($prodi['gallery'], true) ?: []; } catch (Exception $e) {}
                            }
                            if (empty($gallery)):
                                for ($i = 0; $i < 6; $i++):
                            ?>
                            <div class="gallery-item">
                                <div class="gallery-placeholder">📸</div>
                            </div>
                            <?php
                                endfor;
                            else:
                                foreach ($gallery as $img):
                            ?>
                            <div class="gallery-item">
                                <img src="<?= asset('uploads/prodi_gallery/' . basename($img)) ?>" alt="" loading="lazy">
                            </div>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </div>

                    <!-- ALUMNI -->
                    <div id="tab-alumni" class="tab-content">
                        <h3>🎓 Alumni Sukses</h3>
                        <p>Lulusan kami telah berkarir gemilang di berbagai bidang. Berikut adalah beberapa alumni inspiratif:</p>

                        <?php if (empty($alumni)): ?>
                            <div class="empty-state-pro">
                                <div style="font-size:3rem; opacity:0.5;">🎓</div>
                                <p style="color:var(--text-muted);">Data alumni sedang dalam proses pengumpulan.</p>
                            </div>
                        <?php else: ?>
                            <div class="alumni-list">
                                <?php foreach ($alumni as $a): ?>
                                <div class="alumni-item">
                                    <div class="alumni-avatar">
                                        <?php if (!empty($a['foto'])): ?>
                                            <img src="<?= asset('alumni/' . basename($a['foto'])) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($a['nama'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alumni-info">
                                        <h5><?= sanitize($a['nama']) ?></h5>
                                        <p>Alumni <?= sanitize($a['tahun_lulus'] ?? '-') ?></p>
                                        <div class="alumni-job">💼 <?= sanitize($a['pekerjaan'] ?? 'Profesional') ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:1.5rem; text-align:center;">
                                <a href="<?= base_url('alumni.php') ?>" class="btn btn-secondary">Lihat Semua Alumni →</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FAQ -->
                    <div id="tab-faq" class="tab-content">
                        <h3>❓ Pertanyaan Umum (FAQ)</h3>
                        <p>Berikut adalah beberapa pertanyaan yang sering diajukan calon mahasiswa:</p>

                        <div class="faq-list">
                            <?php
                            $faqs = [
                                ['q' => 'Apa syarat pendaftaran program studi ini?', 'a' => 'Calon mahasiswa harus lulus SMA/sederajat, memiliki nilai rapor yang memenuhi syarat, dan lulus tes seleksi masuk. Detail lengkap dapat dilihat di halaman PMB.'],
                                ['q' => 'Berapa lama masa studi normal?', 'a' => 'Masa studi normal untuk jenjang ' . sanitize($prodi['jenjang'] ?? 'S1') . ' adalah ' . sanitize($prodi['durasi'] ?? '8 semester (4 tahun)') . '.'],
                                ['q' => 'Apakah tersedia beasiswa?', 'a' => 'Ya, kami menyediakan berbagai program beasiswa baik dari internal kampus maupun dari pihak eksternal. Silakan kunjungi halaman Beasiswa untuk informasi lengkap.'],
                                ['q' => 'Bagaimana prospek kerja lulusan?', 'a' => 'Lulusan memiliki prospek kerja yang sangat baik di bidang pendidikan, penelitian, konsultan, dan wirausaha. Tingkat penyerapan lulusan kami di dunia kerja mencapai 90% dalam 6 bulan pertama.'],
                                ['q' => 'Apakah ada program magang?', 'a' => 'Ya, program magang menjadi bagian wajib dari kurikulum dan biasanya dilaksanakan pada semester 6-7 di mitra industri dan institusi pendidikan.'],
                                ['q' => 'Bagaimana sistem pembelajaran?', 'a' => 'Kami menggunakan blended learning (kombinasi tatap muka dan online) dengan pendekatan student-centered learning dan project-based learning.'],
                            ];
                            foreach ($faqs as $i => $faq):
                            ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)">
                                    <span><?= $faq['q'] ?></span>
                                    <span class="arrow">▼</span>
                                </div>
                                <div class="faq-answer">
                                    <p><?= $faq['a'] ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>

            <!-- SIDEBAR -->
            <aside class="detail-sidebar" data-aos="fade-left" data-aos-delay="200">

                <!-- Info Card -->
                <div class="sidebar-card info-card">
                    <?php if ($prodi_cols['logo'] && !empty($prodi['logo'])): ?>
                    <div class="prodi-logo-wrap">
                        <img src="<?= asset('uploads/prodi_logo/' . basename($prodi['logo'])) ?>" alt="Logo" class="prodi-logo">
                    </div>
                    <?php endif; ?>

                    <h3>📊 Informasi Program</h3>
                    <ul class="info-list">
                        <li><span>Jenjang</span> <strong><?= sanitize($prodi['jenjang'] ?? '-') ?></strong></li>
                        <?php if ($prodi_cols['akreditasi'] && !empty($prodi['akreditasi'])): ?>
                        <li><span>Akreditasi</span> <strong style="color: <?= $colors[0] ?>;"><?= sanitize($prodi['akreditasi']) ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['kode'] && !empty($prodi['kode'])): ?>
                        <li><span>Kode Prodi</span> <strong><?= sanitize($prodi['kode']) ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['ketua_prodi'] && !empty($prodi['ketua_prodi'])): ?>
                        <li><span>Ketua Prodi</span> <strong><?= sanitize($prodi['ketua_prodi']) ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['jumlah_dosen'] && !empty($prodi['jumlah_dosen'])): ?>
                        <li><span>Jumlah Dosen</span> <strong><?= (int)$prodi['jumlah_dosen'] ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['jumlah_mahasiswa'] && !empty($prodi['jumlah_mahasiswa'])): ?>
                        <li><span>Mahasiswa Aktif</span> <strong><?= (int)$prodi['jumlah_mahasiswa'] ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['durasi'] && !empty($prodi['durasi'])): ?>
                        <li><span>Durasi</span> <strong><?= sanitize($prodi['durasi']) ?></strong></li>
                        <?php endif; ?>
                        <?php if ($prodi_cols['biaya_kuliah'] && !empty($prodi['biaya_kuliah'])): ?>
                        <li><span>Biaya/Smt</span> <strong><?= sanitize($prodi['biaya_kuliah']) ?></strong></li>
                        <?php endif; ?>
                    </ul>

                    <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <a href="<?= base_url('pmb.php') ?>" class="btn btn-primary btn-block">📝 Daftar Sekarang</a>
                        <a href="https://wa.me/6281234567890?text=Halo,%20saya%20ingin%20bertanya%20tentang%20Prodi%20<?= urlencode($prodi['nama']) ?>" target="_blank" class="btn btn-block" style="background: #25D366; color: white; border: none; font-weight: 700;">💬 Tanya via WhatsApp</a>
                    </div>

                    <!-- Share Box -->
                    <div class="share-box">
                        <h4>🔗 Bagikan Prodi Ini</h4>
                        <div class="share-buttons">
                            <button class="share-btn wa" onclick="shareTo('wa')" title="WhatsApp">💬</button>
                            <button class="share-btn fb" onclick="shareTo('fb')" title="Facebook">📘</button>
                            <button class="share-btn tw" onclick="shareTo('tw')" title="Twitter">🐦</button>
                            <button class="share-btn li" onclick="shareTo('li')" title="LinkedIn">💼</button>
                            <button class="share-btn copy" onclick="copyLink()" title="Salin Link">🔗</button>
                        </div>
                    </div>
                </div>

                <!-- Prestasi -->
                <?php if (!empty($prestasi)): ?>
                <div class="sidebar-card">
                    <h3>🏆 Prestasi Terbaru</h3>
                    <div class="prestasi-list">
                        <?php foreach ($prestasi as $p): ?>
                        <div class="prestasi-item">
                            <div class="prestasi-medal">🥇</div>
                            <div class="prestasi-info">
                                <h5><?= sanitize($p['judul']) ?></h5>
                                <small>📅 <?= sanitize($p['tahun']) ?> • <?= sanitize($p['tingkat']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Berita Terkait -->
                <?php if (!empty($berita)): ?>
                <div class="sidebar-card">
                    <h3>📰 Berita Terbaru</h3>
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <?php foreach ($berita as $b): ?>
                        <a href="<?= base_url('berita-detail.php?slug=' . urlencode($b['slug'])) ?>" style="display:flex; gap:0.75rem; padding:0.75rem; background:var(--bg-secondary); border-radius:var(--radius-md); text-decoration:none; color:inherit; transition:all 0.2s; border:1px solid var(--border);" onmouseover="this.style.borderColor='<?= $colors[0] ?>'" onmouseout="this.style.borderColor='var(--border)'">
                            <div style="width:50px; height:50px; border-radius:8px; background:linear-gradient(135deg, <?= $colors[0] ?>, <?= $colors[1] ?>); color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0;">📰</div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.85rem; font-weight:600; color:var(--text-primary); line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:0.2rem;"><?= sanitize($b['judul']) ?></div>
                                <div style="font-size:0.72rem; color:var(--text-muted);">📅 <?= date('d M Y', strtotime($b['published_at'])) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CTA -->
                <div class="sidebar-card cta-card">
                    <h3>🚀 Siap Bergabung?</h3>
                    <p>Jadilah bagian dari generasi pendidik unggul bersama FKIP UNIMOF.</p>
                    <a href="<?= base_url('pmb.php') ?>" class="btn btn-primary">Daftar Sekarang</a>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <div class="pub-toast-icon" id="pubToastIcon">✓</div>
    <div id="pubToastMsg">Berhasil</div>
</div>

<script>
// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 25; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 25 + 's';
        p.style.animationDuration = (20 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
        container.appendChild(p);
    }
})();

// ===== TAB SWITCH =====
function switchTab(event, tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    if (history.pushState) {
        history.pushState(null, null, '#' + tabId.replace('tab-', ''));
    }
}

// ===== AUTO-OPEN TAB FROM HASH =====
(function() {
    const hash = window.location.hash.replace('#', '');
    if (!hash) return;
    const validTabs = ['visi', 'kurikulum', 'prospek', 'dosen', 'fasilitas', 'alumni', 'faq'];
    if (!validTabs.includes(hash)) return;

    const targetBtn = document.querySelector(`.tab-btn[data-tab="tab-${hash}"]`);
    const targetContent = document.getElementById('tab-' + hash);
    if (targetBtn && targetContent) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        targetBtn.classList.add('active');
        targetContent.classList.add('active');
    }
})();

// ===== FAQ TOGGLE =====
function toggleFaq(el) {
    const item = el.closest('.faq-item');
    item.classList.toggle('open');
}

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== SHARE =====
function shareProdi() {
    const url = window.location.href;
    const text = `🎓 <?= addslashes($prodi['nama']) ?>\n📚 <?= addslashes($prodi['jenjang'] ?? '') ?>\n🏆 Akreditasi: <?= addslashes($prodi['akreditasi'] ?? '-') ?>\n\nFKIP UNIMOF - Program Studi`;
    if (navigator.share) {
        navigator.share({ title: '<?= addslashes($prodi['nama']) ?>', text, url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + '\n' + url);
        pubToast('Link prodi disalin', '📋');
    }
}

function shareTo(platform) {
    const url = encodeURIComponent(window.location.href);
    const text = encodeURIComponent('<?= addslashes($prodi['nama']) ?> - FKIP UNIMOF');
    let shareUrl = '';
    if (platform === 'wa') shareUrl = `https://wa.me/?text=${text}%20${url}`;
    if (platform === 'fb') shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
    if (platform === 'tw') shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
    if (platform === 'li') shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
    if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        pubToast('Link berhasil disalin', '✓');
    });
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    // Number keys 1-7 to switch tabs
    if (!e.ctrlKey && !e.metaKey && !e.altKey && document.activeElement.tagName !== 'INPUT') {
        const tabMap = {'1':'visi', '2':'kurikulum', '3':'prospek', '4':'dosen', '5':'fasilitas', '6':'alumni', '7':'faq'};
        if (tabMap[e.key]) {
            const btn = document.querySelector(`.tab-btn[data-tab="tab-${tabMap[e.key]}"]`);
            if (btn) btn.click();
        }
    }
});

console.log('%c🎓 Program Detail FKIP UNIMOF - EXTREME MULTIMATE', 'color:<?= $colors[0] ?>;font-size:16px;font-weight:bold');
console.log('%cShortcuts: 1-7 (Switch tabs)', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>