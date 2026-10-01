<?php
// index.php - Beranda FKIP UNIMOF (EXTREME MULTIMATE VERSION)
require_once __DIR__ . '/includes/config.php';

$page_title = 'Beranda';
$page_description = 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere — Mencetak pendidik profesional berkarakter Islami untuk Indonesia Timur';

// =====================================================
// SCHEMA-SAFE: deteksi kolom untuk semua tabel terkait
// =====================================================
function detect_columns_safe($pdo, $table, $expected) {
    $result = array_fill_keys($expected, false);
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expected as $col) {
            if (in_array($col, $cols, true)) $result[$col] = true;
        }
    } catch (Exception $e) {}
    return $result;
}

$prodi_cols = detect_columns_safe($pdo, 'program_studi', [
    'nama','singkatan','jenjang','akreditasi','deskripsi','logo',
    'jumlah_dosen','jumlah_mahasiswa','status','urutan'
]);
$berita_cols = detect_columns_safe($pdo, 'berita', [
    'judul','slug','kategori','gambar','excerpt','konten',
    'published_at','created_at','views','status','author'
]);
$agenda_cols = detect_columns_safe($pdo, 'agenda', [
    'judul','jenis','tanggal_mulai','tanggal_selesai','lokasi','status'
]);
$prestasi_cols = detect_columns_safe($pdo, 'prestasi', [
    'judul','mahasiswa','juara','lomba','tingkat','tahun','foto',
    'program_studi_id','status'
]);
$alumni_cols = detect_columns_safe($pdo, 'alumni', [
    'nama','foto','pekerjaan','tahun_lulus','testimoni',
    'program_studi_id','status'
]);
$galeri_cols = detect_columns_safe($pdo, 'galeri', [
    'judul','gambar','kategori','tanggal','status'
]);
$kerjasama_cols = detect_columns_safe($pdo, 'kerjasama', [
    'nama_institusi','logo','jenis','negara','link_website','status'
]);
$pengumuman_cols = detect_columns_safe($pdo, 'pengumuman', [
    'judul','konten','tanggal','kategori','status'
]);

// =====================================================
// AMBIL DATA DINAMIS
// =====================================================
// --- Statistik ---
$stat_mahasiswa = $stat_prodi = $stat_dosen = $stat_alumni = $stat_prestasi = 0;
try {
    $stat_prodi    = (int)$pdo->query("SELECT COUNT(*) FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_dosen    = (int)$pdo->query("SELECT COUNT(*) FROM dosen" . (detect_columns_safe($pdo,'dosen',['status'])['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_alumni   = (int)$pdo->query("SELECT COUNT(*) FROM alumni" . ($alumni_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_prestasi = (int)$pdo->query("SELECT COUNT(*) FROM prestasi" . ($prestasi_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();

    if ($prodi_cols['jumlah_mahasiswa']) {
        $stat_mahasiswa = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_mahasiswa),0) FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    }
    // ✅ ANGKA RESMI: prioritaskan tabel statistik (diisi dari admin)
    $statistik_resmi = function_exists('get_statistik') ? get_statistik() : [];
    if (!empty($statistik_resmi['total_mahasiswa'])) $stat_mahasiswa = (int)$statistik_resmi['total_mahasiswa'];
    if (!empty($statistik_resmi['total_dosen']))     $stat_dosen     = (int)$statistik_resmi['total_dosen'];
    if (!empty($statistik_resmi['total_alumni']))    $stat_alumni    = (int)$statistik_resmi['total_alumni'];
    if ($stat_mahasiswa === 0) $stat_mahasiswa = 1250;
    if ($stat_dosen === 0) $stat_dosen = 68;
    if ($stat_alumni === 0) $stat_alumni = 3200;
    if ($stat_prestasi === 0) $stat_prestasi = 120;
} catch (Exception $e) {
    $stat_mahasiswa = 1250; $stat_prodi = 8; $stat_dosen = 68;
    $stat_alumni = 3200; $stat_prestasi = 120;
}

// --- Program Studi ---
try {
    $prodi_stmt = $pdo->query("SELECT * FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : "") . " ORDER BY urutan ASC, id ASC LIMIT 8");
    $prodi = $prodi_stmt->fetchAll();
} catch (Exception $e) { $prodi = []; }

// --- Berita Terbaru ---
$berita_terbaru = [];
try {
    $where_berita = $berita_cols['status'] ? " WHERE status='Published'" : " WHERE 1=1";
    $order_berita = ($berita_cols['published_at'] ? "published_at DESC" : ($berita_cols['created_at'] ? "created_at DESC" : "id DESC"));
    $berita_stmt = $pdo->query("SELECT * FROM berita $where_berita ORDER BY $order_berita LIMIT 4");
    $berita_terbaru = $berita_stmt->fetchAll();
} catch (Exception $e) {}

// --- Agenda Mendatang ---
$agenda = [];
try {
    $where_agenda = "WHERE 1=1";
    if ($agenda_cols['status']) $where_agenda .= " AND status='Aktif'";
    if ($agenda_cols['tanggal_mulai']) $where_agenda .= " AND tanggal_mulai >= CURDATE()";
    $order_agenda = $agenda_cols['tanggal_mulai'] ? "tanggal_mulai ASC" : "id ASC";
    $agenda_stmt = $pdo->query("SELECT * FROM agenda $where_agenda ORDER BY $order_agenda LIMIT 4");
    $agenda = $agenda_stmt->fetchAll();
} catch (Exception $e) {}

// --- Prestasi Terbaru (untuk showcase) ---
$prestasi_latest = [];
try {
    $where_p = $prestasi_cols['status'] ? " WHERE pr.status='Aktif'" : " WHERE 1=1";
    $join_p = $prestasi_cols['program_studi_id'] ? "LEFT JOIN program_studi p ON pr.program_studi_id = p.id" : "";
    $select_p = "pr.*" . ($prestasi_cols['program_studi_id'] ? ", p.singkatan as prodi_singkatan" : "");
    $order_p = ($prestasi_cols['tahun'] ? "pr.tahun DESC, " : "") . ($prestasi_cols['tingkat'] ? "FIELD(pr.tingkat, 'Internasional', 'Nasional', 'Wilayah', 'Universitas') ASC" : "pr.id DESC");
    $prestasi_stmt = $pdo->query("SELECT $select_p FROM prestasi pr $join_p $where_p ORDER BY $order_p LIMIT 4");
    $prestasi_latest = $prestasi_stmt->fetchAll();
} catch (Exception $e) {}

// --- Testimoni (dari tabel testimoni) ---
$testimoni = [];
try {
    $testi_stmt = $pdo->query("SELECT * FROM testimoni WHERE status='Published' ORDER BY rating DESC, created_at DESC LIMIT 6");
    $testimoni = $testi_stmt->fetchAll();
} catch (Exception $e) {}

// --- Galeri Foto ---
$galeri = [];
try {
    $where_g = $galeri_cols['status'] ? " WHERE status='Aktif'" : "";
    $order_g = $galeri_cols['tanggal'] ? "tanggal DESC" : "id DESC";
    $galeri_stmt = $pdo->query("SELECT * FROM galeri $where_g ORDER BY $order_g LIMIT 6");
    $galeri = $galeri_stmt->fetchAll();
} catch (Exception $e) {}

// --- Mitra Kerjasama (untuk marquee) ---
$mitra = [];
try {
    $where_m = $kerjasama_cols['status'] ? " WHERE status='Aktif'" : "";
    $mitra_stmt = $pdo->query("SELECT * FROM kerjasama $where_m ORDER BY jenis DESC, nama_institusi ASC LIMIT 12");
    $mitra = $mitra_stmt->fetchAll();
} catch (Exception $e) {}

// --- Pengumuman Ticker ---
$ticker = [];
try {
    if (!empty($pengumuman_cols['status'])) {
        $ticker_stmt = $pdo->query("SELECT * FROM pengumuman WHERE status='Aktif' ORDER BY tanggal DESC LIMIT 5");
        $ticker = $ticker_stmt->fetchAll();
    }
} catch (Exception $e) {}

// Fallback ticker jika kosong
if (empty($ticker)) {
    $ticker = [
        ['judul' => '🔥 Penerimaan Mahasiswa Baru 2026/2027 Gelombang 1 Telah Dibuka!'],
        ['judul' => '📅 Pendaftaran Wisuda Periode I ditutup 31 Desember 2026'],
        ['judul' => '🏆 Selamat kepada mahasiswa berprestasi di Kompetisi Nasional'],
        ['judul' => '📢 Seminar Nasional Pendidikan akan dilaksanakan bulan depan'],
    ];
}

// Konfigurasi hero
$hero_config = [
    'headline_1'   => 'Menyalakan',
    'headline_2'   => 'Terang Pendidikan',
    'headline_3'   => 'Menghadirkan Harapan',
    'badge_text'   => 'Penerimaan Mahasiswa Baru 2026/2027 Telah Dibuka',
    'badge_link'   => base_url('pmb.php'),
    'primary_cta'  => ['text' => 'Jelajahi Program Studi', 'url' => base_url('program.php'), 'icon' => '🎓'],
    'secondary_cta' => ['text' => 'Hubungi Kami', 'url' => base_url('kontak.php'), 'icon' => '💬'],
];

// =====================================================
// HERO VIDEO BACKGROUND (dari admin → Pengaturan → tab Hero Video)
// =====================================================
$hero_video_url  = '';
$hero_poster_url = '';
$hero_video_mime = 'video/mp4';
try {
    $hv_active = get_setting('hero_video_active', '1') === '1';
    $hv_path   = trim((string)get_setting('hero_video', ''));
    $hp_path   = trim((string)get_setting('hero_video_poster', ''));

    if ($hv_active && $hv_path !== '') {
        $abs = (defined('APP_DIR') ? APP_DIR : __DIR__) . '/' . ltrim($hv_path, '/');
        if (is_file($abs)) {
            $hero_video_url = base_url(ltrim($hv_path, '/'));
            $ext = strtolower(pathinfo($hv_path, PATHINFO_EXTENSION));
            $hero_video_mime = ($ext === 'webm') ? 'video/webm' : 'video/mp4';
        }
    }
    if ($hp_path !== '') {
        $abs_p = (defined('APP_DIR') ? APP_DIR : __DIR__) . '/' . ltrim($hp_path, '/');
        if (is_file($abs_p)) $hero_poster_url = base_url(ltrim($hp_path, '/'));
    }
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* =====================================================
   HERO EXTREME
   ===================================================== */
.hero-extreme {
    position: relative; min-height: 100vh; display: flex; align-items: center;
    padding: 10rem 0 6rem; overflow: hidden; background: #0f172a;
}
.hero-bg-extreme {
    position: absolute; inset: 0; z-index: 0;
    background:
        radial-gradient(circle at 15% 50%, rgba(10,104,71,0.4) 0%, transparent 50%),
        radial-gradient(circle at 85% 30%, rgba(245,166,35,0.3) 0%, transparent 50%),
        radial-gradient(circle at 50% 80%, rgba(59,130,246,0.3) 0%, transparent 50%);
    animation: heroMesh 20s ease-in-out infinite; filter: blur(60px);
}
@keyframes heroMesh {
    0%, 100% { transform: scale(1) translate(0, 0); }
    33% { transform: scale(1.1) translate(-20px, 20px); }
    66% { transform: scale(0.9) translate(20px, -20px); }
}
.hero-particles-extreme { position: absolute; inset: 0; pointer-events: none; z-index: 1; }
.particle-extreme {
    position: absolute; width: 4px; height: 4px; background: rgba(255,255,255,0.5);
    border-radius: 50%; animation: floatParticle 25s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.hero-grid-overlay {
    position: absolute; inset: 0; z-index: 1; pointer-events: none;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px;
}

.hero-content-extreme { position: relative; z-index: 2; text-align: center; max-width: 1000px; margin: 0 auto; }
.hero-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); color: white;
    padding: 0.6rem 1.5rem; border-radius: 999px; font-size: 0.88rem; font-weight: 700;
    margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    text-decoration: none; transition: all 0.3s;
}
.hero-badge-extreme:hover { background: rgba(255,255,255,0.18); transform: translateY(-2px); }
.pulse-dot-extreme { width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative; }
.pulse-dot-extreme::after {
    content: ''; position: absolute; inset: 0; background: #10b981; border-radius: 50%;
    animation: pulse 2s infinite;
}
@keyframes pulse { to { transform: scale(3); opacity: 0; } }

.hero-title-extreme {
    font-family: var(--font-display); font-size: clamp(3rem, 7vw, 5.5rem);
    font-weight: 900; line-height: 1.05; margin-bottom: 1.5rem;
    letter-spacing: -0.03em; color: white;
}
.title-line-extreme { display: block; }
.title-line-extreme.highlight .gradient-text-extreme {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ec4899 100%);
    background-size: 200% 200%; -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; animation: gradientShift 5s ease infinite; font-style: italic;
}
@keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

.hero-subtitle-extreme {
    font-size: clamp(1.1rem, 1.5vw, 1.35rem); color: rgba(255,255,255,0.85);
    max-width: 750px; margin: 0 auto 2rem; line-height: 1.7; font-weight: 400;
}

.hero-trust-row {
    display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 2.5rem; flex-wrap: wrap;
}
.hero-trust-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    border-radius: 999px; font-size: 0.82rem; font-weight: 600; color: white;
}

.hero-actions-extreme { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 3.5rem; }
.btn-hero-primary {
    padding: 1rem 2.5rem; background: white; color: var(--primary);
    border-radius: 999px; font-weight: 800; font-size: 1.05rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.75rem; transition: all 0.3s;
    box-shadow: 0 10px 30px rgba(255,255,255,0.2); border: none; cursor: pointer;
    font-family: inherit;
}
.btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(255,255,255,0.3); }
.btn-hero-outline {
    padding: 1rem 2.5rem; background: rgba(255,255,255,0.1); color: white;
    border: 2px solid rgba(255,255,255,0.3); border-radius: 999px;
    font-weight: 700; font-size: 1.05rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.75rem; transition: all 0.3s;
    backdrop-filter: blur(10px); cursor: pointer; font-family: inherit;
}
.btn-hero-outline:hover { background: rgba(255,255,255,0.2); border-color: white; transform: translateY(-3px); }

.hero-scroll-extreme {
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 600;
    letter-spacing: 0.1em; text-transform: uppercase;
}
.scroll-indicator-extreme {
    width: 28px; height: 48px; border: 2px solid rgba(255,255,255,0.3);
    border-radius: 14px; position: relative;
}
.scroll-dot-extreme {
    width: 6px; height: 6px; background: white; border-radius: 50%;
    position: absolute; top: 8px; left: 50%; transform: translateX(-50%);
    animation: scrollDown 2s infinite;
}
@keyframes scrollDown { 0% { top: 8px; opacity: 1; } 100% { top: 32px; opacity: 0; } }

/* =====================================================
   HERO VIDEO BACKGROUND (ala FEB UGM)
   ===================================================== */
.hero-video-wrap {
    position: absolute; inset: 0; z-index: 0; overflow: hidden;
    background: #0f172a;
}
.hero-video-bg {
    position: absolute; top: 50%; left: 50%;
    min-width: 100%; min-height: 100%;
    width: auto; height: auto;
    transform: translate(-50%, -50%);
    object-fit: cover;
}
/* Overlay gelap agar teks tetap terbaca di atas video */
.hero-video-overlay {
    position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(180deg,
        rgba(15,23,42,0.60) 0%,
        rgba(15,23,42,0.35) 45%,
        rgba(15,23,42,0.80) 100%);
}

/* ===== RE-STACKING saat video aktif ===== */
.hero-extreme.has-video .hero-bg-extreme {
    z-index: 1; opacity: 0.30; mix-blend-mode: screen;
}
.hero-extreme.has-video .hero-particles-extreme,
.hero-extreme.has-video .hero-grid-overlay { z-index: 2; }
.hero-extreme.has-video .hero-content-extreme { z-index: 3; }

/* ===== Tombol Mute/Unmute ===== */
.hero-sound-toggle {
    position: absolute; left: 1.5rem; bottom: 1.5rem; z-index: 4;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(15,23,42,0.55); color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
    backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    cursor: pointer; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.25s ease;
}
.hero-sound-toggle:hover { background: rgba(15,23,42,0.85); transform: scale(1.08); }
.hero-sound-toggle.unmuted {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.25);
}

/* Saat video dipause (reduced-motion / hemat data) → poster tetap tampil rapi */
.hero-video-wrap.video-paused .hero-video-bg { object-fit: cover; }

@media (max-width: 640px) {
.hero-sound-toggle { left: 1rem; bottom: 1rem; width: 40px; height: 40px; }
}

/* =====================================================
   TICKER PENGUMUMAN
   ===================================================== */
.ticker-bar {
    position: relative; background: linear-gradient(90deg, #064e34, var(--primary));
    color: white; padding: 0.75rem 0; overflow: hidden; z-index: 5;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.ticker-label {
    position: absolute; left: 0; top: 0; bottom: 0; z-index: 3;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    padding: 0 1.25rem; display: flex; align-items: center; gap: 0.4rem;
    font-weight: 800; font-size: 0.82rem; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: 4px 0 12px rgba(0,0,0,0.15);
}
.ticker-label::after {
    content: ''; position: absolute; right: -12px; top: 0; bottom: 0;
    width: 12px; background: linear-gradient(45deg, #d97706 50%, transparent 50%);
}
.ticker-track {
    display: flex; white-space: nowrap; animation: tickerScroll 45s linear infinite;
    padding-left: 180px;
}
.ticker-item {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0 2rem; font-size: 0.88rem; font-weight: 500;
}
.ticker-item::after {
    content: '•'; color: rgba(255,255,255,0.5); margin-left: 1rem;
}
.ticker-item:last-child::after { display: none; }
@keyframes tickerScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.ticker-bar:hover .ticker-track { animation-play-state: paused; }

/* =====================================================
   STATS BAR
   ===================================================== */
.stats-bar-extreme {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem; max-width: 1100px; margin: 2.5rem auto 0; position: relative; z-index: 10;
    padding: 0 1rem;
}
.stat-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-xl); transition: all 0.4s; position: relative; overflow: hidden;
}
.stat-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); border-color: var(--stat-color, var(--primary)); }
.stat-icon-extreme { font-size: 2.5rem; margin-bottom: 1rem; transition: transform 0.3s; }
.stat-card-extreme:hover .stat-icon-extreme { transform: scale(1.15) rotate(-8deg); }
.stat-number-extreme {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-extreme { font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* =====================================================
   SECTION HEADERS
   ===================================================== */
.section-header-split {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-bottom: 3rem; flex-wrap: wrap; gap: 1.5rem;
}
.section-tag {
    display: inline-block; padding: 0.4rem 1rem; background: rgba(10,104,71,0.1);
    color: var(--primary); border-radius: 999px; font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;
}
.section-title {
    font-family: var(--font-display); font-size: clamp(2rem, 4vw, 2.75rem);
    font-weight: 900; line-height: 1.2; margin-bottom: 0.5rem; letter-spacing: -0.02em;
}
.section-desc { color: var(--text-secondary); font-size: 1.05rem; line-height: 1.7; max-width: 600px; }

/* =====================================================
   ABOUT SECTION
   ===================================================== */
.section-about-extreme { background: var(--bg-secondary); padding: 7rem 0; }
.about-grid-extreme { display: grid; grid-template-columns: 1fr 1.2fr; gap: 5rem; align-items: center; }
.about-content-extreme h3 { font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 1.5rem; line-height: 1.2; }
.about-content-extreme > p { color: var(--text-secondary); margin-bottom: 2.5rem; line-height: 1.8; font-size: 1.05rem; }

.features-list-extreme { display: grid; gap: 1.25rem; margin-bottom: 2.5rem; }
.feature-item-extreme {
    display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem;
    background: var(--bg-primary); border-radius: var(--radius-lg);
    border: 1px solid var(--border); transition: all 0.3s;
}
.feature-item-extreme:hover { transform: translateX(8px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.feature-icon-extreme {
    width: 50px; height: 50px; background: rgba(10,104,71,0.1); border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;
    transition: all 0.3s;
}
.feature-item-extreme:hover .feature-icon-extreme { background: var(--primary); color: white; transform: scale(1.1); }
.feature-item-extreme h4 { font-size: 1.05rem; margin-bottom: 0.25rem; font-weight: 700; }
.feature-item-extreme p { font-size: 0.88rem; color: var(--text-secondary); margin: 0; line-height: 1.6; }

.image-stack-extreme { position: relative; height: 550px; perspective: 1000px; }
.img-main-extreme {
    position: absolute; width: 75%; height: 100%; border-radius: var(--radius-xl);
    overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.2); z-index: 1;
    transform: rotateY(-5deg) rotateX(2deg); transition: transform 0.5s;
}
.image-stack-extreme:hover .img-main-extreme { transform: rotateY(0) rotateX(0); }
.img-accent-extreme {
    position: absolute; width: 50%; height: 50%; border-radius: var(--radius-lg);
    overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.15); border: 4px solid var(--bg-primary); z-index: 2;
}
.img-1-extreme { bottom: 5%; left: -5%; animation: floatY 6s ease-in-out infinite; }
.img-2-extreme { top: 5%; right: -5%; animation: floatY 6s ease-in-out infinite 2s; }
@keyframes floatY { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

.img-placeholder-extreme {
    width: 100%; height: 100%; display: flex; flex-direction: column;
    align-items: center; justify-content: center; color: white; font-size: 3.5rem;
}
.img-placeholder-extreme p { font-size: 1rem; margin-top: 0.75rem; font-weight: 700; letter-spacing: 0.05em; }

.stats-floating-extreme {
    position: absolute; bottom: -5%; right: 5%; z-index: 3;
    display: flex; gap: 0; background: var(--bg-primary);
    padding: 1.25rem 2rem; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl); border: 1px solid var(--border);
    backdrop-filter: blur(10px);
}
.stats-floating-extreme .stat-item { text-align: center; padding: 0 1.5rem; border-right: 1px solid var(--border); }
.stats-floating-extreme .stat-item:first-child { padding-left: 0; }
.stats-floating-extreme .stat-item:last-child { border: 0; padding-right: 0; }
.stats-floating-extreme strong {
    display: block; font-size: 1.75rem; color: var(--primary);
    font-family: var(--font-display); font-weight: 900; line-height: 1;
}
.stats-floating-extreme span {
    font-size: 0.72rem; color: var(--text-muted); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; display: block;
}

/* =====================================================
   PROGRAMS SECTION
   ===================================================== */
.section-programs-extreme { padding: 7rem 0; }
.programs-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.75rem;
}
.program-card-extreme {
    position: relative; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.25rem; overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
    display: flex; flex-direction: column;
}
.program-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--card-color-1, var(--primary)), var(--card-color-2, var(--primary-light)));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.program-card-extreme:hover {
    transform: translateY(-12px); box-shadow: 0 25px 50px rgba(0,0,0,0.1);
    border-color: var(--card-color-1, var(--primary));
}
.program-card-extreme:hover::before { transform: scaleX(1); }

.program-number-extreme {
    font-family: var(--font-display); font-size: 3.5rem; font-weight: 900;
    color: var(--bg-tertiary); line-height: 1; margin-bottom: 0.75rem; transition: all 0.4s;
}
.program-card-extreme:hover .program-number-extreme {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    transform: scale(1.1); transform-origin: left;
}
.program-icon-extreme { font-size: 2.5rem; margin-bottom: 1.25rem; transition: transform 0.4s; }
.program-card-extreme:hover .program-icon-extreme { transform: scale(1.2) rotate(-10deg); }

.program-badge-extreme {
    display: inline-block; padding: 0.35rem 0.85rem; border-radius: 999px;
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.85rem; background: rgba(10,104,71,0.1); color: var(--primary); width: fit-content;
}
.program-title-extreme {
    font-size: 1.2rem; font-weight: 800; margin-bottom: 0.4rem;
    transition: color 0.3s; line-height: 1.3; color: var(--text-primary);
}
.program-card-extreme:hover .program-title-extreme { color: var(--card-color-1, var(--primary)); }
.program-level-extreme { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.75rem; font-weight: 600; }
.program-meta-extreme {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem;
    font-size: 0.78rem; color: var(--text-muted);
}
.program-meta-extreme span { display: inline-flex; align-items: center; gap: 0.3rem; }
.program-link-extreme {
    display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary);
    font-weight: 700; font-size: 0.88rem; text-decoration: none; transition: gap 0.3s;
    margin-top: auto;
}
.program-link-extreme:hover { gap: 0.8rem; }

/* =====================================================
   PRESTASI SHOWCASE
   ===================================================== */
.section-prestasi-extreme {
    padding: 7rem 0; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 50%, #fde68a 100%);
    position: relative; overflow: hidden;
}
.section-prestasi-extreme::before {
    content: '🏆'; position: absolute; font-size: 25rem; opacity: 0.05;
    top: 50%; right: -5%; transform: translateY(-50%) rotate(-15deg);
    pointer-events: none;
}
.prestasi-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem; margin-bottom: 2rem;
}
.prestasi-card-extreme {
    background: white; border: 1px solid rgba(0,0,0,0.06);
    border-radius: var(--radius-xl); padding: 2rem; transition: all 0.4s;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative; overflow: hidden;
}
.prestasi-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.prestasi-card-extreme:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(245,158,11,0.15); }
.prestasi-card-extreme:hover::before { transform: scaleX(1); }

.prestasi-year-extreme {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    color: #f59e0b; margin-bottom: 0.5rem;
}
.prestasi-level-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.8rem; border-radius: 999px; font-size: 0.68rem;
    font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 0.85rem;
}
.badge-intl-ext { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-nas-ext { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-reg-ext { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-default-ext { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }

.prestasi-title-extreme {
    font-size: 1.05rem; font-weight: 800; margin-bottom: 0.65rem;
    line-height: 1.4; color: #0f172a;
}
.prestasi-meta-extreme {
    display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.78rem;
    color: #64748b;
}
.prestasi-meta-extreme span { display: inline-flex; align-items: center; gap: 0.3rem; }

/* =====================================================
   NEWS SECTION
   ===================================================== */
.section-news-extreme { background: var(--bg-secondary); padding: 7rem 0; }
.news-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;
}
.news-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column;
}
.news-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary-light); }
.news-card-extreme.featured { grid-column: span 2; }
.news-card-extreme.featured .news-image-extreme { height: 300px; }
.news-card-extreme.featured .news-title-extreme { font-size: 1.65rem; }

.news-image-extreme { position: relative; height: 220px; overflow: hidden; }
.news-image-extreme .img-placeholder, .news-image-extreme img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.news-card-extreme:hover .news-image-extreme .img-placeholder,
.news-card-extreme:hover .news-image-extreme img { transform: scale(1.08); }

.news-category-extreme {
    position: absolute; top: 1.25rem; left: 1.25rem; background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px); color: var(--primary); padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: var(--shadow-sm); z-index: 2;
}
.news-content-extreme { padding: 2rem; display: flex; flex-direction: column; flex: 1; }
.news-meta-extreme {
    display: flex; gap: 1.25rem; color: var(--text-muted); font-size: 0.82rem;
    margin-bottom: 1rem; font-weight: 600;
}
.news-title-extreme { font-size: 1.2rem; font-weight: 800; line-height: 1.4; margin-bottom: 1rem; }
.news-title-extreme a {
    color: var(--text-primary); text-decoration: none;
    background-image: linear-gradient(var(--primary), var(--primary));
    background-size: 0% 2px; background-position: 0 100%; background-repeat: no-repeat;
    transition: background-size 0.3s, color 0.3s;
}
.news-title-extreme a:hover { color: var(--primary); background-size: 100% 2px; }
.news-excerpt-extreme {
    color: var(--text-secondary); font-size: 0.92rem; line-height: 1.7;
    margin-bottom: 1.5rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.read-more-extreme {
    color: var(--primary); font-weight: 700; font-size: 0.88rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.5rem; transition: gap 0.3s;
}
.read-more-extreme:hover { gap: 0.8rem; }

/* =====================================================
   CALENDAR
   ===================================================== */
.section-calendar-extreme { padding: 7rem 0; }
.calendar-wrapper-extreme {
    max-width: 1000px; margin: 0 auto; background: var(--bg-primary);
    border-radius: var(--radius-xl); padding: 3.5rem;
    box-shadow: var(--shadow-xl); border: 1px solid var(--border);
}
.calendar-list-extreme { display: flex; flex-direction: column; gap: 1.25rem; }
.calendar-item-extreme {
    display: grid; grid-template-columns: auto 1fr auto; gap: 2rem;
    align-items: center; padding: 1.5rem; background: var(--bg-secondary);
    border-radius: var(--radius-lg); transition: all 0.3s; cursor: pointer;
    border: 1px solid transparent;
}
.calendar-item-extreme:hover {
    background: var(--bg-primary); border-color: var(--primary);
    transform: translateX(8px); box-shadow: var(--shadow-md);
}
.calendar-date-extreme {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; padding: 1rem 1.25rem; border-radius: var(--radius-md);
    text-align: center; min-width: 80px; box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.date-day-extreme { display: block; font-size: 2rem; font-weight: 900; line-height: 1; }
.date-month-extreme {
    font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;
    margin-top: 0.25rem; display: block; font-weight: 700;
}
.calendar-type-extreme {
    display: inline-block; padding: 0.3rem 0.7rem; border-radius: 6px;
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 0.4rem;
}
.calendar-type-extreme.ujian { background: #fee2e2; color: #dc2626; }
.calendar-type-extreme.seminar { background: #dbeafe; color: #2563eb; }
.calendar-type-extreme.wisuda { background: #fef3c7; color: #d97706; }
.calendar-type-extreme.rapat { background: #dcfce7; color: #166534; }
.calendar-type-extreme.lainnya { background: #f3f4f6; color: #4b5563; }
.calendar-info-extreme h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.25rem; }
.calendar-info-extreme p { font-size: 0.82rem; color: var(--text-muted); margin: 0; }
.calendar-arrow-extreme { color: var(--primary); font-size: 1.5rem; transition: transform 0.3s; opacity: 0; }
.calendar-item-extreme:hover .calendar-arrow-extreme { opacity: 1; transform: translateX(5px); }

/* =====================================================
   TESTIMONI CAROUSEL
   ===================================================== */
.section-testi-extreme { padding: 7rem 0; background: var(--bg-secondary); }
.testi-carousel-extreme { max-width: 800px; margin: 0 auto; position: relative; }
.testi-track-extreme { overflow: hidden; border-radius: var(--radius-xl); }
.testi-slides-extreme { display: flex; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
.testi-slide-extreme { min-width: 100%; padding: 0.5rem; }
.testi-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 3rem; text-align: center;
    box-shadow: var(--shadow-md); position: relative;
}
.testi-card-extreme::before {
    content: '"'; position: absolute; top: 0.5rem; left: 1.5rem;
    font-family: Georgia, serif; font-size: 6rem; color: var(--bg-tertiary);
    line-height: 1; pointer-events: none;
}
.testi-avatar-extreme {
    width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 800; border: 4px solid var(--bg-primary);
    box-shadow: 0 4px 15px rgba(10,104,71,0.25); overflow: hidden;
}
.testi-avatar-extreme img { width: 100%; height: 100%; object-fit: cover; }
.testi-quote-extreme {
    font-size: 1.05rem; color: var(--text-primary); line-height: 1.8;
    font-style: italic; margin-bottom: 1.5rem; position: relative; z-index: 1;
}
.testi-name-extreme { font-weight: 800; font-size: 1rem; color: var(--text-primary); }
.testi-meta-extreme { font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem; }
.testi-stars-extreme { color: #f59e0b; font-size: 1.1rem; margin-top: 0.75rem; letter-spacing: 0.15em; }
.testi-nav-extreme {
    display: flex; justify-content: center; align-items: center;
    gap: 1rem; margin-top: 1.5rem;
}
.testi-arrow-extreme {
    width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
    transition: all 0.2s;
}
.testi-arrow-extreme:hover { background: var(--primary); color: white; border-color: var(--primary); }
.testi-dots-extreme { display: flex; gap: 0.4rem; }
.testi-dot-extreme {
    width: 10px; height: 10px; border-radius: 999px; background: var(--bg-tertiary);
    cursor: pointer; transition: all 0.3s; border: none; padding: 0;
}
.testi-dot-extreme.active { width: 28px; background: var(--primary); }

/* =====================================================
   GALERI
   ===================================================== */
.section-galeri-extreme { padding: 7rem 0; }
.galeri-grid-extreme {
    display: grid; grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(2, 220px); gap: 1rem;
}
.galeri-item-extreme {
    position: relative; overflow: hidden; border-radius: var(--radius-lg);
    cursor: pointer; background: var(--bg-secondary);
    transition: all 0.4s;
}
.galeri-item-extreme:nth-child(1) { grid-column: span 2; grid-row: span 2; }
.galeri-item-extreme:hover { transform: scale(1.02); box-shadow: var(--shadow-xl); }
.galeri-item-extreme img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s; }
.galeri-item-extreme:hover img { transform: scale(1.08); }
.galeri-placeholder {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; font-size: 3rem; color: var(--text-muted); opacity: 0.5;
    background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));
}
.galeri-overlay {
    position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
    display: flex; align-items: flex-end; padding: 1.25rem;
    color: white; opacity: 0; transition: opacity 0.3s;
}
.galeri-item-extreme:hover .galeri-overlay { opacity: 1; }
.galeri-caption { font-weight: 700; font-size: 0.92rem; }

/* =====================================================
   MITRA MARQUEE
   ===================================================== */
.section-mitra-extreme { padding: 5rem 0; background: var(--bg-secondary); overflow: hidden; }
.marquee-extreme {
    position: relative; padding: 2rem 0; overflow: hidden;
}
.marquee-extreme::before, .marquee-extreme::after {
    content: ''; position: absolute; top: 0; bottom: 0; width: 150px; z-index: 2; pointer-events: none;
}
.marquee-extreme::before {
    left: 0; background: linear-gradient(to right, var(--bg-secondary), transparent);
}
.marquee-extreme::after {
    right: 0; background: linear-gradient(to left, var(--bg-secondary), transparent);
}
.marquee-track-extreme {
    display: flex; gap: 3rem; animation: marqueeScroll 40s linear infinite;
    width: fit-content;
}
.marquee-track-extreme:hover { animation-play-state: paused; }
@keyframes marqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.mitra-item-extreme {
    display: flex; align-items: center; justify-content: center;
    padding: 1rem 2rem; background: var(--bg-primary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    min-width: 180px; height: 80px; transition: all 0.3s;
    flex-shrink: 0;
}
.mitra-item-extreme:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); }
.mitra-item-logo {
    max-width: 120px; max-height: 50px; object-fit: contain;
}
.mitra-item-text {
    font-weight: 700; font-size: 0.9rem; color: var(--text-primary);
    text-align: center;
}

/* =====================================================
   CTA EXTREME
   ===================================================== */
.section-cta-extreme { padding: 4rem 0 8rem; }
.cta-wrapper-extreme {
    position: relative; background: linear-gradient(135deg, var(--primary) 0%, #064e34 100%);
    border-radius: var(--radius-2xl); padding: 5rem 3rem; overflow: hidden; color: white;
    box-shadow: 0 25px 50px rgba(10,104,71,0.3);
}
.cta-wrapper-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.cta-content-extreme { position: relative; z-index: 2; max-width: 800px; margin: 0 auto; text-align: center; }
.cta-content-extreme h2 {
    font-family: var(--font-display); font-size: clamp(2rem, 4vw, 3rem);
    margin-bottom: 1.5rem; color: white; line-height: 1.2;
}
.cta-content-extreme p { font-size: 1.15rem; margin-bottom: 2.5rem; opacity: 0.95; line-height: 1.7; }
.cta-actions-extreme { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

.cta-decoration-extreme { position: absolute; top: 0; right: 0; width: 500px; height: 500px; pointer-events: none; }
.cta-orb-extreme {
    position: absolute; border-radius: 50%; background: rgba(255,255,255,0.1);
    filter: blur(40px); animation: orbFloat 10s ease-in-out infinite;
}
.orb-1-extreme { width: 250px; height: 250px; top: 20%; right: 10%; }
.orb-2-extreme { width: 180px; height: 180px; top: 60%; right: 20%; animation-delay: -3s; }
.orb-3-extreme { width: 120px; height: 120px; top: 10%; right: 35%; animation-delay: -6s; }
@keyframes orbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } }

/* =====================================================
   FLOATING ACTIONS
   ===================================================== */
.floating-wa {
    position: fixed; bottom: 2rem; right: 2rem; z-index: 9000;
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, #25D366, #128C7E);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; text-decoration: none; box-shadow: 0 8px 25px rgba(37,211,102,0.4);
    transition: all 0.3s; animation: waFloat 3s ease-in-out infinite;
}
.floating-wa:hover { transform: scale(1.1); }
@keyframes waFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
.floating-wa::after {
    content: ''; position: absolute; inset: 0; border-radius: 50%;
    border: 2px solid #25D366; animation: waRing 2s infinite;
}
@keyframes waRing { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }

.floating-pmb {
    position: fixed; bottom: 6.5rem; right: 2rem; z-index: 9000;
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; text-decoration: none;
    box-shadow: 0 8px 25px rgba(10,104,71,0.4);
    transition: all 0.3s;
}
.floating-pmb:hover { transform: scale(1.1); }
.floating-pmb::before {
    content: 'DAFTAR'; position: absolute; right: 70px; top: 50%;
    transform: translateY(-50%); background: var(--bg-primary);
    color: var(--primary); padding: 0.4rem 0.75rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 800; white-space: nowrap;
    box-shadow: var(--shadow-md); opacity: 0; pointer-events: none;
    transition: opacity 0.3s;
}
.floating-pmb:hover::before { opacity: 1; }

.back-to-top {
    position: fixed; bottom: 11rem; right: 2rem; z-index: 9000;
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--bg-primary); border: 1px solid var(--border);
    color: var(--text-secondary); display: flex; align-items: center;
    justify-content: center; font-size: 1.1rem; cursor: pointer;
    box-shadow: var(--shadow-md); transition: all 0.3s;
    opacity: 0; pointer-events: none; transform: translateY(20px);
}
.back-to-top.show { opacity: 1; pointer-events: auto; transform: none; }
.back-to-top:hover { background: var(--primary); color: white; border-color: var(--primary); }

/* =====================================================
   TOAST
   ===================================================== */
.pub-toast {
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(150%);
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: 999px; padding: 0.85rem 1.5rem;
    box-shadow: var(--shadow-xl); display: flex; align-items: center;
    gap: 0.65rem; z-index: 10002;
    transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    max-width: 90%; font-size: 0.88rem; font-weight: 600;
}
.pub-toast.show { transform: translateX(-50%) translateY(0); }

/* =====================================================
   EMPTY STATES
   ===================================================== */
.empty-state-premium {
    text-align: center; padding: 3.5rem 2rem; background: var(--bg-primary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.empty-icon-lg {
    font-size: 4rem; margin-bottom: 0.85rem; opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

/* =====================================================
   RESPONSIVE
   ===================================================== */
@media (max-width: 1024px) {
    .stats-bar-extreme { grid-template-columns: repeat(2, 1fr); }
    .about-grid-extreme { grid-template-columns: 1fr; gap: 3rem; }
    .image-stack-extreme { height: 400px; }
    .news-card-extreme.featured { grid-column: span 1; }
    .calendar-item-extreme { grid-template-columns: auto 1fr; gap: 1.5rem; }
    .calendar-arrow-extreme { display: none; }
    .galeri-grid-extreme { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(3, 200px); }
    .galeri-item-extreme:nth-child(1) { grid-column: span 2; grid-row: span 1; }
}
@media (max-width: 640px) {
    .hero-extreme { padding: 8rem 0 4rem; }
    .stats-bar-extreme { grid-template-columns: 1fr; margin-top: -3rem; }
    .stat-number-extreme { font-size: 2.5rem; }
    .cta-wrapper-extreme { padding: 3rem 1.5rem; }
    .calendar-wrapper-extreme { padding: 2rem 1.5rem; }
    .testi-card-extreme { padding: 2rem 1.5rem; }
    .galeri-grid-extreme { grid-template-columns: 1fr; grid-template-rows: repeat(6, 180px); }
    .galeri-item-extreme:nth-child(1) { grid-column: span 1; }
    .floating-pmb, .floating-wa { width: 54px; height: 54px; right: 1.25rem; }
    .floating-pmb { bottom: 5.5rem; }
    .floating-wa { bottom: 1.25rem; }
    .back-to-top { bottom: 9.5rem; right: 1.25rem; }
    .ticker-label { font-size: 0.72rem; padding: 0 0.9rem; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="hero-extreme <?= $hero_video_url ? 'has-video' : '' ?>" id="hero">
    <?php if ($hero_video_url): ?>
    <!-- 🎥 Layer Video Background -->
    <div class="hero-video-wrap" id="heroVideoWrap">
        <video class="hero-video-bg" id="heroVideo"
               autoplay muted loop playsinline preload="metadata"
               <?= $hero_poster_url ? 'poster="' . $hero_poster_url . '"' : '' ?>>
            <source src="<?= $hero_video_url ?>" type="<?= $hero_video_mime ?>">
        </video>
        <div class="hero-video-overlay"></div>
    </div>
    <button class="hero-sound-toggle" id="heroSoundToggle"
            aria-label="Aktifkan atau nonaktifkan suara video" title="Suara video">🔇</button>
    <?php endif; ?>
    <div class="hero-bg-extreme"></div>
    <div class="hero-particles-extreme" id="particles"></div>
    <div class="hero-grid-overlay"></div>

    <div class="container hero-content-extreme">
        <a href="<?= $hero_config['badge_link'] ?>" class="hero-badge-extreme" data-aos="fade-down">
            <span class="pulse-dot-extreme"></span>
            <span><?= sanitize($hero_config['badge_text']) ?></span>
            <span>→</span>
        </a>

        <h1 class="hero-title-extreme" data-aos="fade-up">
            <span class="title-line-extreme"><?= sanitize($hero_config['headline_1']) ?></span>
            <span class="title-line-extreme highlight">
                <span class="gradient-text-extreme"><?= sanitize($hero_config['headline_2']) ?></span>
            </span>
            <span class="title-line-extreme"><?= sanitize($hero_config['headline_3']) ?></span>
        </h1>

        <p class="hero-subtitle-extreme" data-aos="fade-up" data-aos-delay="200">
            Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere
            membentuk generasi pendidik yang cerdas, berkarakter, dan inovatif untuk Indonesia Timur.
        </p>

        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="hero-trust-pill">🎓 <?= $stat_prodi ?> Program Studi</span>
            <span class="hero-trust-pill">👨‍🎓 <?= number_format($stat_mahasiswa) ?> Mahasiswa</span>
            <span class="hero-trust-pill">🏆 <?= $stat_prestasi ?> Prestasi</span>
            <span class="hero-trust-pill">⭐ Akreditasi Unggul</span>
        </div>

        <div class="hero-actions-extreme" data-aos="fade-up" data-aos-delay="400">
            <a href="<?= $hero_config['primary_cta']['url'] ?>" class="btn-hero-primary">
                <span><?= sanitize($hero_config['primary_cta']['text']) ?></span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="<?= $hero_config['secondary_cta']['url'] ?>" class="btn-hero-outline">
                <span><?= sanitize($hero_config['secondary_cta']['text']) ?></span>
            </a>
        </div>

        <div class="hero-scroll-extreme" data-aos="fade-up" data-aos-delay="600">
            <span>Scroll untuk menjelajahi</span>
            <div class="scroll-indicator-extreme">
                <div class="scroll-dot-extreme"></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== TICKER PENGUMUMAN ===== -->
<?php if (!empty($ticker)): ?>
<div class="ticker-bar">
    <div class="ticker-label">📢 Pengumuman</div>
    <div class="ticker-track">
        <?php
        // Duplikasi agar loop seamless
        $ticker_double = array_merge($ticker, $ticker);
        foreach ($ticker_double as $t):
        ?>
        <span class="ticker-item"><?= sanitize($t['judul']) ?></span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== STATS BAR ===== -->
<div class="container">
    <div class="stats-bar-extreme" data-aos="fade-up">
        <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
            <div class="stat-icon-extreme">👨‍🎓</div>
            <div class="stat-number-extreme count-up" data-count="<?= $stat_mahasiswa ?>">0</div>
            <div class="stat-label-extreme">Mahasiswa Aktif</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #10b981;">
            <div class="stat-icon-extreme">🎓</div>
            <div class="stat-number-extreme count-up" data-count="<?= $stat_prodi ?>">0</div>
            <div class="stat-label-extreme">Program Studi</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
            <div class="stat-icon-extreme">👨‍🏫</div>
            <div class="stat-number-extreme count-up" data-count="<?= $stat_dosen ?>">0</div>
            <div class="stat-label-extreme">Dosen Berkualitas</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
            <div class="stat-icon-extreme">🏆</div>
            <div class="stat-number-extreme count-up" data-count="<?= $stat_alumni ?>">0</div>
            <div class="stat-label-extreme">Alumni Sukses</div>
        </div>
    </div>
</div>

<!-- ===== ABOUT SECTION ===== -->
<section class="section-about-extreme" id="about">
    <div class="container">
        <div class="about-grid-extreme">
            <div class="about-content-extreme" data-aos="fade-right">
                <span class="section-tag">Tentang Kami</span>
                <h3>Rumah bagi <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">calon pendidik</span> masa depan</h3>
                <p>Sebagai fakultas unggulan di Universitas Muhammadiyah Maumere, kami berkomitmen mencetak lulusan yang tidak hanya kompeten dalam bidang akademik, tetapi juga memiliki karakter Islami dan siap menghadapi tantangan pendidikan modern.</p>

                <div class="features-list-extreme">
                    <div class="feature-item-extreme">
                        <div class="feature-icon-extreme">🎓</div>
                        <div>
                            <h4>Kurikulum Modern</h4>
                            <p>Disusun mengikuti standar KKNI dan kebutuhan industri pendidikan 4.0</p>
                        </div>
                    </div>
                    <div class="feature-item-extreme">
                        <div class="feature-icon-extreme">🌏</div>
                        <div>
                            <h4>Jaringan Global</h4>
                            <p>Kerjasama dengan universitas dan lembaga pendidikan di ASEAN</p>
                        </div>
                    </div>
                    <div class="feature-item-extreme">
                        <div class="feature-icon-extreme">💼</div>
                        <div>
                            <h4>Siap Kerja</h4>
                            <p>Program magang, sertifikasi, dan career center terintegrasi</p>
                        </div>
                    </div>
                    <div class="feature-item-extreme">
                        <div class="feature-icon-extreme">🔬</div>
                        <div>
                            <h4>Research-Driven</h4>
                            <p>Pusat riset pendidikan dengan jurnal ilmiah bereputasi</p>
                        </div>
                    </div>
                </div>

                <a href="<?= base_url('tentang.php') ?>" class="btn btn-primary btn-lg">Pelajari Lebih Lanjut →</a>
            </div>

            <div class="about-visual" data-aos="fade-left">
                <div class="image-stack-extreme">
                    <div class="img-main-extreme">
                        <div class="img-placeholder-extreme" style="background: linear-gradient(135deg, #0a6847 0%, #16a34a 100%);">
                            <span>🏛️</span>
                            <p>Kampus FKIP</p>
                        </div>
                    </div>
                    <div class="img-accent-extreme img-1-extreme">
                        <div class="img-placeholder-extreme" style="background: linear-gradient(135deg, #f5a623 0%, #fbbf24 100%);">
                            <span>📚</span>
                            <p>Perpustakaan</p>
                        </div>
                    </div>
                    <div class="img-accent-extreme img-2-extreme">
                        <div class="img-placeholder-extreme" style="background: linear-gradient(135deg, #16213e 0%, #3b82f6 100%);">
                            <span>🧪</span>
                            <p>Laboratorium</p>
                        </div>
                    </div>
                    <div class="stats-floating-extreme">
                        <div class="stat-item">
                            <strong>A</strong>
                            <span>Akreditasi Unggul</span>
                        </div>
                        <div class="stat-item">
                            <strong>25+</strong>
                            <span>Tahun Berdiri</span>
                        </div>
                        <div class="stat-item">
                            <strong>15+</strong>
                            <span>Mitra Global</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PROGRAMS SECTION ===== -->
<section class="section-programs-extreme" id="programs">
    <div class="container">
        <div class="section-header-split" data-aos="fade-up">
            <div>
                <span class="section-tag">Program Studi</span>
                <h2 class="section-title">Pilih jalur <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">pendidikan</span> terbaikmu</h2>
                <p class="section-desc">Kurikulum kami dirancang mengikuti standar nasional dan internasional, dibimbing oleh dosen berkualifikasi tinggi.</p>
            </div>
            <a href="<?= base_url('program.php') ?>" class="btn btn-secondary">Lihat Semua Prodi →</a>
        </div>

        <div class="programs-grid-extreme">
            <?php
            $prodi_colors = [
                ['#3b82f6', '#1d4ed8'], ['#8b5cf6', '#6d28d9'],
                ['#10b981', '#059669'], ['#f59e0b', '#d97706'],
                ['#ec4899', '#db2777'], ['#ef4444', '#dc2626'],
                ['#14b8a6', '#0d9488'], ['#f97316', '#ea580c']
            ];
            $icons = ['📐', '⚛️', '🧬', '🧪', '🌐', '📖', '💰', '⚖️', '🎨', '🎭'];

            foreach ($prodi as $index => $p):
                $colors = $prodi_colors[$index % count($prodi_colors)];
                $icon = $icons[$index % count($icons)] ?? '🎓';
            ?>
            <article class="program-card-extreme" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>"
                onclick="window.location='<?= base_url('program-detail.php?id=' . $p['id']) ?>'"
                style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;">
                <div class="program-number-extreme"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="program-icon-extreme"><?= $icon ?></div>
                <div class="program-badge-extreme"><?= sanitize($p['akreditasi'] ?? 'Terakreditasi') ?></div>
                <h3 class="program-title-extreme"><?= sanitize($p['nama']) ?></h3>
                <p class="program-level-extreme"><?= sanitize($p['jenjang'] ?? 'S1') ?> • <?= sanitize($p['singkatan'] ?? '') ?></p>
                <div class="program-meta-extreme">
                    <?php if ($prodi_cols['jumlah_dosen'] && !empty($p['jumlah_dosen'])): ?>
                        <span>👨‍🏫 <?= (int)$p['jumlah_dosen'] ?> Dosen</span>
                    <?php endif; ?>
                    <?php if ($prodi_cols['jumlah_mahasiswa'] && !empty($p['jumlah_mahasiswa'])): ?>
                        <span>👨‍🎓 <?= (int)$p['jumlah_mahasiswa'] ?> Mhs</span>
                    <?php endif; ?>
                </div>
                <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" class="program-link-extreme" onclick="event.stopPropagation()">
                    Detail Program
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== PRESTASI SHOWCASE ===== -->
<?php if (!empty($prestasi_latest)): ?>
<section class="section-prestasi-extreme" id="prestasi">
    <div class="container">
        <div class="section-header-split" data-aos="fade-up">
            <div>
                <span class="section-tag" style="background: rgba(245,158,11,0.15); color: #d97706;">Hall of Fame</span>
                <h2 class="section-title">Prestasi <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Membanggakan</span></h2>
                <p class="section-desc">Mahasiswa FKIP UNIMOF terus menorehkan prestasi gemilang di berbagai kompetisi nasional dan internasional.</p>
            </div>
            <a href="<?= base_url('prestasi.php') ?>" class="btn btn-secondary">Lihat Semua Prestasi →</a>
        </div>

        <div class="prestasi-grid-extreme">
            <?php
            $tingkat_icons = [
                'Internasional' => ['icon' => '🌍', 'badge' => 'badge-intl-ext'],
                'Nasional'      => ['icon' => '🇮🇩', 'badge' => 'badge-nas-ext'],
                'Wilayah'       => ['icon' => '🏛️', 'badge' => 'badge-reg-ext'],
                'Universitas'   => ['icon' => '🏫', 'badge' => 'badge-default-ext'],
            ];
            foreach ($prestasi_latest as $pr):
                $t = $pr['tingkat'] ?? 'Nasional';
                $meta = $tingkat_icons[$t] ?? $tingkat_icons['Nasional'];
            ?>
            <div class="prestasi-card-extreme" data-aos="fade-up">
                <div class="prestasi-year-extreme"><?= sanitize($pr['tahun'] ?? '-') ?></div>
                <span class="prestasi-level-badge <?= $meta['badge'] ?>">
                    <?= $meta['icon'] ?> <?= strtoupper(sanitize($t)) ?>
                </span>
                <h3 class="prestasi-title-extreme"><?= sanitize($pr['judul']) ?></h3>
                <div class="prestasi-meta-extreme">
                    <?php if (!empty($pr['mahasiswa'])): ?>
                        <span>👤 <?= sanitize($pr['mahasiswa']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pr['juara'])): ?>
                        <span>🥇 <?= sanitize($pr['juara']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pr['prodi_singkatan'])): ?>
                        <span>🎓 <?= sanitize($pr['prodi_singkatan']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== NEWS SECTION ===== -->
<section class="section-news-extreme" id="berita">
    <div class="container">
        <div class="section-header-split" data-aos="fade-up">
            <div>
                <span class="section-tag">Berita Terkini</span>
                <h2 class="section-title">Kabar dari <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">FKIP</span></h2>
                <p class="section-desc">Informasi terbaru seputar kegiatan, prestasi, dan agenda akademik FKIP UNIMOF.</p>
            </div>
            <a href="<?= base_url('berita.php') ?>" class="btn btn-secondary">Lihat Semua Berita →</a>
        </div>

        <?php if (empty($berita_terbaru)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📭</div>
                <h3>Belum ada berita yang dipublikasikan</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Kunjungi lagi nanti untuk update terbaru.</p>
            </div>
        <?php else: ?>
            <div class="news-grid-extreme">
                <?php foreach ($berita_terbaru as $index => $berita): ?>
                <article class="news-card-extreme <?= $index === 0 ? 'featured' : '' ?>" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                    <div class="news-image-extreme">
                        <?php if (!empty($berita['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($berita['gambar'])) ?>" alt="<?= sanitize($berita['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display:flex; align-items:center; justify-content:center; font-size:4rem; color:white;">
                                📰
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($berita['kategori'])): ?>
                            <span class="news-category-extreme"><?= sanitize($berita['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="news-content-extreme">
                        <div class="news-meta-extreme">
                            <span>📅 <?= format_tanggal_singkat($berita['published_at'] ?? $berita['created_at']) ?></span>
                            <?php if ($berita_cols['views']): ?>
                                <span>👁 <?= number_format($berita['views'] ?? 0) ?> Views</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="news-title-extreme">
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($berita['slug'])) ?>">
                                <?= sanitize($berita['judul']) ?>
                            </a>
                        </h3>
                        <p class="news-excerpt-extreme"><?= sanitize(excerpt($berita['excerpt'] ?? $berita['konten'] ?? '', 120)) ?></p>
                        <a href="<?= base_url('berita-detail.php?slug=' . urlencode($berita['slug'])) ?>" class="read-more-extreme">
                            Baca Selengkapnya
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== CALENDAR SECTION ===== -->
<section class="section-calendar-extreme" id="agenda">
    <div class="container">
        <div class="calendar-wrapper-extreme" data-aos="fade-up">
            <div style="text-align: center; margin-bottom: 3rem;">
                <span class="section-tag">Jadwal Penting</span>
                <h2 class="section-title">Kalender <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Akademik</span></h2>
                <p style="color: var(--text-muted); font-size: 1.05rem; margin-top: 0.5rem;">Agenda penting yang tidak boleh Anda lewatkan</p>
            </div>

            <div class="calendar-list-extreme">
                <?php if (empty($agenda)):
                    $default_agenda = [
                        ['tanggal' => date('Y-m-d', strtotime('+14 days')), 'judul' => 'Ujian Tengah Semester Ganjil', 'jenis' => 'ujian'],
                        ['tanggal' => date('Y-m-d', strtotime('+30 days')), 'judul' => 'Seminar Nasional Pendidikan', 'jenis' => 'seminar'],
                        ['tanggal' => date('Y-m-d', strtotime('+60 days')), 'judul' => 'Ujian Akhir Semester', 'jenis' => 'ujian'],
                        ['tanggal' => date('Y-m-d', strtotime('+90 days')), 'judul' => 'Wisuda Periode I', 'jenis' => 'wisuda'],
                    ];
                    foreach ($default_agenda as $ag):
                ?>
                    <div class="calendar-item-extreme">
                        <div class="calendar-date-extreme">
                            <span class="date-day-extreme"><?= date('d', strtotime($ag['tanggal'])) ?></span>
                            <span class="date-month-extreme"><?= strtoupper(date('M', strtotime($ag['tanggal']))) ?></span>
                        </div>
                        <div class="calendar-info-extreme">
                            <span class="calendar-type-extreme <?= sanitize($ag['jenis']) ?>"><?= ucfirst(sanitize($ag['jenis'])) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                        </div>
                        <div class="calendar-arrow-extreme">→</div>
                    </div>
                <?php endforeach; else:
                    foreach ($agenda as $ag):
                        $jenis = strtolower($ag['jenis'] ?? 'lainnya');
                        if (!in_array($jenis, ['ujian','seminar','wisuda','rapat'])) $jenis = 'lainnya';
                ?>
                    <div class="calendar-item-extreme">
                        <div class="calendar-date-extreme">
                            <span class="date-day-extreme"><?= date('d', strtotime($ag['tanggal_mulai'])) ?></span>
                            <span class="date-month-extreme"><?= strtoupper(date('M', strtotime($ag['tanggal_mulai']))) ?></span>
                        </div>
                        <div class="calendar-info-extreme">
                            <span class="calendar-type-extreme <?= $jenis ?>"><?= ucfirst(sanitize($ag['jenis'] ?? 'Agenda')) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                            <?php if (!empty($ag['lokasi'])): ?>
                                <p>📍 <?= sanitize($ag['lokasi']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="calendar-arrow-extreme">→</div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== TESTIMONI SECTION ===== -->
<?php if (!empty($testimoni)): ?>
<section class="section-testi-extreme" id="testimoni">
    <div class="container">
        <div class="section-header-split" data-aos="fade-up" style="justify-content: center; text-align: center;">
            <div style="max-width: 700px; margin: 0 auto;">
                <span class="section-tag">Testimoni</span>
                <h2 class="section-title">Kata <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Mereka</span></h2>
                <p class="section-desc" style="margin: 0 auto;">Cerita sukses alumni dan mahasiswa FKIP UNIMOF yang kini berkarya di berbagai bidang.</p>
            </div>
        </div>

        <div class="testi-carousel-extreme" data-aos="fade-up">
            <div class="testi-track-extreme">
                <div class="testi-slides-extreme" id="testiSlides">
                    <?php foreach ($testimoni as $t):
                        $initials = strtoupper(substr($t['nama'], 0, 1) . (strpos($t['nama'], ' ') !== false ? substr($t['nama'], strpos($t['nama'], ' ') + 1, 1) : ''));
                        $rating = max(1, min(5, (int)($t['rating'] ?? 5)));
                    ?>
                    <div class="testi-slide-extreme">
                        <div class="testi-card-extreme">
                            <div class="testi-avatar-extreme">
                                <?php if (!empty($t['foto'])): ?>
                                    <img src="<?= base_url($t['foto']) ?>" alt="<?= sanitize($t['nama']) ?>">
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <p class="testi-quote-extreme">"<?= sanitize($t['pesan']) ?>"</p>
                            <div class="testi-name-extreme"><?= sanitize($t['nama']) ?></div>
                            <div class="testi-meta-extreme">
                                💬 <?= sanitize($t['jabatan'] ?: 'Alumni / Mahasiswa FKIP') ?>
                            </div>
                            <div class="testi-stars-extreme"><?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $rating ? '★' : '☆' ?><?php endfor; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="testi-nav-extreme">
                <button class="testi-arrow-extreme" onclick="moveTesti(-1)">←</button>
                <div class="testi-dots-extreme" id="testiDots"></div>
                <button class="testi-arrow-extreme" onclick="moveTesti(1)">→</button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== GALERI SECTION ===== -->
<?php if (!empty($galeri)): ?>
<section class="section-galeri-extreme" id="galeri">
    <div class="container">
        <div class="section-header-split" data-aos="fade-up">
            <div>
                <span class="section-tag">Galeri</span>
                <h2 class="section-title">Momen <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Berkesan</span></h2>
                <p class="section-desc">Dokumentasi kegiatan akademik dan kemahasiswaan FKIP UNIMOF.</p>
            </div>
            <a href="<?= base_url('galeri.php') ?>" class="btn btn-secondary">Lihat Semua Galeri →</a>
        </div>

        <div class="galeri-grid-extreme" data-aos="fade-up">
            <?php foreach ($galeri as $i => $g): ?>
            <div class="galeri-item-extreme">
                <?php if (!empty($g['gambar'])): ?>
                    <img src="<?= asset('uploads/galeri/' . basename($g['gambar'])) ?>" alt="<?= sanitize($g['judul'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                    <div class="galeri-placeholder">📸</div>
                <?php endif; ?>
                <div class="galeri-overlay">
                    <span class="galeri-caption"><?= sanitize($g['judul'] ?? 'Kegiatan FKIP UNIMOF') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== MITRA MARQUEE ===== -->
<?php if (!empty($mitra)): ?>
<section class="section-mitra-extreme" id="mitra">
    <div class="container">
        <div style="text-align: center; margin-bottom: 2rem;" data-aos="fade-up">
            <span class="section-tag">Mitra Kerjasama</span>
            <h2 class="section-title">Didukung <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Mitra Terbaik</span></h2>
        </div>
    </div>

    <div class="marquee-extreme">
        <div class="marquee-track-extreme" id="marqueeTrack">
            <?php
            // Duplikasi untuk seamless loop
            $mitra_double = array_merge($mitra, $mitra);
            foreach ($mitra_double as $m):
                $jenis = $m['jenis'] ?? 'Mitra';
                $icon = $jenis === 'Universitas' ? '🎓' : ($jenis === 'Pemerintah' ? '🏛️' : ($jenis === 'Industri' ? '🏭' : '🤝'));
            ?>
            <div class="mitra-item-extreme">
                <?php if (!empty($m['logo'])): ?>
                    <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="<?= sanitize($m['nama_institusi']) ?>" class="mitra-item-logo">
                <?php else: ?>
                    <span class="mitra-item-text"><?= $icon ?> <?= sanitize($m['nama_institusi']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== CTA SECTION ===== -->
<section class="section-cta-extreme">
    <div class="container">
        <div class="cta-wrapper-extreme" data-aos="zoom-in">
            <div class="cta-content-extreme">
                <h2>Siap menjadi bagian dari <span class="gradient-text-extreme" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">perubahan?</span></h2>
                <p>Bergabunglah dengan ribuan mahasiswa yang telah memilih FKIP UNIMOF sebagai rumah akademik mereka. Wujudkan mimpi menjadi pendidik profesional mulai dari sini.</p>
                <div class="cta-actions-extreme">
                    <a href="<?= base_url('pmb.php') ?>" class="btn-hero-primary" style="color: var(--primary);">
                        Daftar Sekarang
                    </a>
                    <a href="<?= base_url('kontak.php') ?>" class="btn-hero-outline">
                        Konsultasi Gratis
                    </a>
                </div>
            </div>
            <div class="cta-decoration-extreme">
                <div class="cta-orb-extreme orb-1-extreme"></div>
                <div class="cta-orb-extreme orb-2-extreme"></div>
                <div class="cta-orb-extreme orb-3-extreme"></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FLOATING BUTTONS ===== -->
<a href="<?= base_url('pmb.php') ?>" class="floating-pmb" title="Daftar Sekarang">📝</a>
<a href="https://wa.me/6281234567890?text=Halo%20Admin%20FKIP%20UNIMOF,%20saya%20ingin%20bertanya" target="_blank" class="floating-wa" title="Chat Admin">💬</a>
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0, behavior:'smooth'})" title="Kembali ke atas">↑</button>

<!-- ===== TOAST ===== -->
<div class="pub-toast" id="pubToast">
    <span class="pub-toast-icon" id="pubToastIcon">✓</span>
    <span id="pubToastMsg">Berhasil</span>
</div>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('particles');
    if (!container) return;
    for (let i = 0; i < 40; i++) {
        const p = document.createElement('div');
        p.className = 'particle-extreme';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 25 + 's';
        p.style.animationDuration = (20 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        container.appendChild(p);
    }
})();

// ===== ANIMATED COUNTERS =====
function animateCounter(el) {
    const target = parseInt(el.dataset.count) || 0;
    const duration = 2000;
    const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => counterObserver.observe(el));

// ===== SMOOTH SCROLL =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId !== '#' && document.querySelector(targetId)) {
            e.preventDefault();
            document.querySelector(targetId).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ===== BACK TO TOP =====
window.addEventListener('scroll', () => {
    const btn = document.getElementById('backToTop');
    if (btn) btn.classList.toggle('show', window.scrollY > 600);
});

// ===== TOAST =====
function pubToast(msg, icon = '✓') {
    const t = document.getElementById('pubToast');
    if (!t) return;
    document.getElementById('pubToastMsg').textContent = msg;
    document.getElementById('pubToastIcon').textContent = icon;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== TESTIMONI CAROUSEL =====
<?php if (!empty($testimoni)): ?>
let testiIndex = 0;
const testiSlides = document.getElementById('testiSlides');
const testiCount = testiSlides ? testiSlides.children.length : 0;
(function initTesti() {
    const dots = document.getElementById('testiDots');
    if (!dots) return;
    for (let i = 0; i < testiCount; i++) {
        const d = document.createElement('button');
        d.className = 'testi-dot-extreme' + (i === 0 ? ' active' : '');
        d.onclick = () => goTesti(i);
        dots.appendChild(d);
    }
})();
function goTesti(i) {
    testiIndex = (i + testiCount) % testiCount;
    testiSlides.style.transform = `translateX(-${testiIndex * 100}%)`;
    document.querySelectorAll('.testi-dot-extreme').forEach((d, idx) => d.classList.toggle('active', idx === testiIndex));
}
function moveTesti(dir) { goTesti(testiIndex + dir); }
setInterval(() => moveTesti(1), 6000);
<?php endif; ?>

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    }
});

// ===== HERO VIDEO BACKGROUND CONTROLLER =====
(function() {
    const video   = document.getElementById('heroVideo');
    const wrap    = document.getElementById('heroVideoWrap');
    const soundBtn= document.getElementById('heroSoundToggle');
    const hero    = document.getElementById('hero');
    if (!video || !wrap || !hero) return;

    // 1) Hormati prefers-reduced-motion & mode hemat data
    const reduced  = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const saveData = !!(navigator.connection && navigator.connection.saveData);
    if (reduced || saveData) {
        video.pause();
        wrap.classList.add('video-paused');
    }

    // 2) Pause otomatis saat hero tidak terlihat (hemat CPU/baterai)
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) {
                    if (!reduced && !saveData) video.play().catch(() => {});
                } else {
                    video.pause();
                }
            });
        }, { threshold: 0.15 });
        io.observe(hero);
    }

    // 3) Fallback graceful jika video gagal dimuat → kembali ke gradient mesh
    const killVideo = () => {
        wrap.style.display = 'none';
        hero.classList.remove('has-video');
        if (soundBtn) soundBtn.style.display = 'none';
    };
    video.addEventListener('error', killVideo, true);
    const srcEl = video.querySelector('source');
    if (srcEl) srcEl.addEventListener('error', killVideo);

    // 4) Tombol mute / unmute
    if (soundBtn) {
        soundBtn.addEventListener('click', () => {
            video.muted = !video.muted;
            soundBtn.textContent = video.muted ? '🔇' : '🔊';
            soundBtn.classList.toggle('unmuted', !video.muted);
            if (!video.muted) video.play().catch(() => {});
        });
    }

    // 5) Paksa autoplay muted (beberapa browser menolak autoplay bersuara)
    const pr = video.play();
    if (pr && pr.catch) pr.catch(() => { video.muted = true; video.play().catch(() => {}); });
})();

console.log('%c🏠 Beranda FKIP UNIMOF - EXTREME MULTIMATE', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cWebsite siap dengan performa optimal!', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>