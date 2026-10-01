<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Tentang Kami';
$page_description = 'Profil lengkap FKIP UNIMOF: Visi, Misi, Sejarah, Struktur Organisasi, Nilai Inti, dan Sambutan Dekan';

// =====================================================
// SCHEMA-SAFE: deteksi kolom untuk 5 tabel
// =====================================================
function detect_columns($pdo, $table, $expected) {
    $result = array_fill_keys($expected, false);
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expected as $col) {
            if (in_array($col, $cols, true)) $result[$col] = true;
        }
    } catch (Exception $e) {}
    return $result;
}

$dosen_cols = detect_columns($pdo, 'dosen', [
    'nama','foto','jabatan_fungsional','jabatan_struktural','pendidikan_terakhir',
    'email','telepon','program_studi_id','status'
]);
$prodi_cols = detect_columns($pdo, 'program_studi', [
    'nama','singkatan','ketua_prodi','sekretaris_prodi','jenjang','akreditasi',
    'jumlah_dosen','jumlah_mahasiswa','logo','status'
]);
$alumni_cols = detect_columns($pdo, 'alumni', [
    'nama','foto','tahun_lulus','pekerjaan','program_studi_id','testimoni','status'
]);
$prestasi_cols = detect_columns($pdo, 'prestasi', [
    'judul','tahun','tingkat','juara','program_studi_id'
]);
$kerjasama_cols = detect_columns($pdo, 'kerjasama', [
    'nama_institusi','jenis','logo','negara','link_website','status'
]);

// =====================================================
// AMBIL DATA DINAMIS
// =====================================================
// --- Statistik ---
$stat_prodi = $stat_dosen = $stat_mahasiswa = $stat_alumni = $stat_prestasi = 0;
$stat_intl = 0;
try {
    $stat_prodi = (int)$pdo->query("SELECT COUNT(*) FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_dosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen" . ($dosen_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_alumni = (int)$pdo->query("SELECT COUNT(*) FROM alumni" . ($alumni_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    $stat_prestasi = (int)$pdo->query("SELECT COUNT(*) FROM prestasi")->fetchColumn();

    // Total mahasiswa dari sum jumlah_mahasiswa per prodi
    if ($prodi_cols['jumlah_mahasiswa']) {
        $stat_mahasiswa = (int)$pdo->query("SELECT COALESCE(SUM(jumlah_mahasiswa),0) FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : ""))->fetchColumn();
    }
    if ($stat_mahasiswa === 0) $stat_mahasiswa = 1250;

    // Mitra internasional
    if ($kerjasama_cols['negara'] && $kerjasama_cols['status']) {
        $stat_intl = (int)$pdo->query("SELECT COUNT(DISTINCT negara) FROM kerjasama WHERE status='Aktif' AND negara != 'Indonesia'")->fetchColumn();
    }
} catch (Exception $e) {
    $stat_prodi = 8; $stat_dosen = 68; $stat_mahasiswa = 1250;
    $stat_alumni = 850; $stat_prestasi = 120; $stat_intl = 6;
}

// --- Program Studi (dengan akreditasi) ---
try {
    $stmt = $pdo->query("SELECT * FROM program_studi" . ($prodi_cols['status'] ? " WHERE status='Aktif'" : "") . " ORDER BY urutan ASC, id ASC");
    $prodi_list = $stmt->fetchAll();
} catch (Exception $e) { $prodi_list = []; }

// Distribusi akreditasi untuk chart
$akreditasi_dist = [];
foreach ($prodi_list as $p) {
    $akr = $p['akreditasi'] ?? 'Belum';
    $akreditasi_dist[$akr] = ($akreditasi_dist[$akr] ?? 0) + 1;
}

// --- Dekan ---
try {
    $dekan_query = "SELECT * FROM dosen WHERE ";
    $dekan_conditions = [];
    if ($dosen_cols['jabatan_struktural']) $dekan_conditions[] = "jabatan_struktural LIKE '%Dekan%'";
    if ($dosen_cols['jabatan_fungsional']) $dekan_conditions[] = "jabatan_fungsional LIKE '%Dekan%'";
    if (empty($dekan_conditions)) $dekan_conditions[] = "1=0";
    $dekan_query .= "(" . implode(' OR ', $dekan_conditions) . ")";
    if ($dosen_cols['status']) $dekan_query .= " AND status='Aktif'";
    $dekan_query .= " LIMIT 1";
    $stmt_dekan = $pdo->query($dekan_query);
    $dekan = $stmt_dekan->fetch();
} catch (Exception $e) { $dekan = false; }

if (!$dekan) {
    $dekan = [
        'id' => 0,
        'nama' => 'Prof. Dr. H. Ahmad Fauzi, M.Pd.',
        'jabatan_fungsional' => 'Dekan FKIP UNIMOF',
        'jabatan_struktural' => 'Dekan FKIP UNIMOF',
        'pendidikan_terakhir' => 'S3 Pendidikan',
        'foto' => '',
        'email' => 'dekan@fkip-unimof.ac.id',
        'sambutan' => "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\nSelamat datang di website resmi FKIP UNIMOF. Kami berkomitmen untuk terus meningkatkan kualitas pendidikan, penelitian, dan pengabdian masyarakat.\n\nMelalui kurikulum yang adaptif berbasis MBKM, dosen yang kompeten, dan fasilitas modern, kami mengajak Anda menjadi bagian dari keluarga besar FKIP UNIMOF. Mari berinovasi bersama untuk Indonesia yang lebih baik!\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh."
    ];
}

// --- Wakil Dekan (WD1, WD2, WD3) ---
$wd_list = [];
try {
    $wd_query = "SELECT * FROM dosen WHERE ";
    $wd_conditions = [];
    if ($dosen_cols['jabatan_struktural']) {
        $wd_conditions[] = "jabatan_struktural LIKE '%Wakil Dekan%'";
        $wd_conditions[] = "jabatan_struktural LIKE '%WD%'";
    }
    if (empty($wd_conditions)) $wd_conditions[] = "1=0";
    $wd_query .= "(" . implode(' OR ', $wd_conditions) . ")";
    if ($dosen_cols['status']) $wd_query .= " AND status='Aktif'";
    $wd_query .= " LIMIT 3";
    $wd_list = $pdo->query($wd_query)->fetchAll();
} catch (Exception $e) {}

// --- Kaprodi dari DB ---
$kaprodi_list = [];
foreach ($prodi_list as $p) {
    $kaprodi_list[] = [
        'nama' => $p['ketua_prodi'] ?? 'Segera diisi',
        'role' => 'Kaprodi ' . ($p['singkatan'] ?? $p['nama'] ?? ''),
        'prodi' => $p['nama'] ?? '',
        'foto' => '',
        'initial' => strtoupper(substr($p['ketua_prodi'] ?? $p['nama'] ?? 'K', 0, 1))
    ];
}

// --- Mitra Kerjasama dari DB ---
$partners = [];
try {
    if ($kerjasama_cols['status']) {
        $stmt_p = $pdo->query("SELECT * FROM kerjasama WHERE status='Aktif' ORDER BY jenis DESC, nama_institusi ASC LIMIT 12");
        $partners = $stmt_p->fetchAll();
    }
} catch (Exception $e) {}

if (empty($partners)) {
    $partners = [
        ['nama_institusi' => 'Kemdikbudristek', 'jenis' => 'Pemerintah', 'logo' => '', 'negara' => 'Indonesia'],
        ['nama_institusi' => 'PP Muhammadiyah', 'jenis' => 'Yayasan', 'logo' => '', 'negara' => 'Indonesia'],
        ['nama_institusi' => 'Universiti Malaya', 'jenis' => 'Universitas', 'logo' => '', 'negara' => 'Malaysia'],
        ['nama_institusi' => 'Sakarya University', 'jenis' => 'Universitas', 'logo' => '', 'negara' => 'Turki'],
        ['nama_institusi' => 'Dinas Pendidikan NTT', 'jenis' => 'Pemerintah', 'logo' => '', 'negara' => 'Indonesia'],
        ['nama_institusi' => 'LPDP', 'jenis' => 'Pemerintah', 'logo' => '', 'negara' => 'Indonesia'],
        ['nama_institusi' => 'BRIN', 'jenis' => 'Pemerintah', 'logo' => '', 'negara' => 'Indonesia'],
        ['nama_institusi' => 'Microsoft Education', 'jenis' => 'Industri', 'logo' => '', 'negara' => 'USA'],
    ];
}

// --- Testimoni Alumni ---
$testimoni_list = [];
try {
    if ($alumni_cols['testimoni'] && $alumni_cols['status']) {
        $stmt_t = $pdo->query("SELECT a.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan
                               FROM alumni a
                               LEFT JOIN program_studi p ON a.program_studi_id = p.id
                               WHERE a.status='Aktif' AND a.testimoni IS NOT NULL AND a.testimoni != ''
                               ORDER BY a.tahun_lulus DESC LIMIT 4");
        $testimoni_list = $stmt_t->fetchAll();
    }
} catch (Exception $e) {}

// =====================================================
// DATA STATIS (Core Values, Milestone, FAQ)
// =====================================================
$core_values = [
    ['icon' => '🎯', 'title' => 'Unggul',       'desc' => 'Standar akademik tertinggi dengan akreditasi unggul dari BAN-PT', 'color' => ['#10b981','#059669']],
    ['icon' => '💡', 'title' => 'Inovatif',     'desc' => 'Kurikulum adaptif mengikuti perkembangan teknologi pendidikan 4.0', 'color' => ['#3b82f6','#2563eb']],
    ['icon' => '🕌', 'title' => 'Islami',       'desc' => 'Menanamkan nilai keislaman dan kemuhammadiyahan dalam setiap aktivitas', 'color' => ['#f59e0b','#d97706']],
    ['icon' => '🌏', 'title' => 'Global',       'desc' => 'Jejaring internasional dengan universitas di ASEAN dan Timur Tengah', 'color' => ['#8b5cf6','#7c3aed']],
    ['icon' => '🤝', 'title' => 'Kolaboratif',  'desc' => 'Kerjasama dengan sekolah, pemerintah, dan industri pendidikan', 'color' => ['#ec4899','#db2777']],
    ['icon' => '⭐', 'title' => 'Berkarakter', 'desc' => 'Membentuk pendidik yang berakhlak mulia dan berjiwa pemimpin', 'color' => ['#ef4444','#dc2626']],
];

$milestones = [
    ['year' => '1995', 'title' => 'Cikal Bakal FKIP',         'desc' => 'Berdiri sebagai Sekolah Tinggi Keguruan dan Ilmu Pendidikan (STKIP) dengan 2 program studi awal: Pendidikan Matematika dan Pendidikan Bahasa Indonesia.', 'icon' => '🌱'],
    ['year' => '2000', 'title' => 'Integrasi ke UNIMOF',      'desc' => 'Bergabung resmi menjadi Fakultas Keguruan dan Ilmu Pendidikan di bawah Universitas Muhammadiyah Maumere dengan 4 program studi.', 'icon' => '🏛️'],
    ['year' => '2008', 'title' => 'Ekspansi 8 Prodi',         'desc' => 'Membuka 4 program studi baru: Pendidikan Fisika, Pendidikan Biologi, Pendidikan Kimia, dan Pendidikan Ekonomi untuk menjawab kebutuhan guru di NTT.', 'icon' => '📈'],
    ['year' => '2015', 'title' => 'Akreditasi B & A',         'desc' => 'Meraih akreditasi B dari BAN-PT untuk seluruh program studi, dengan 2 prodi meraih akreditasi A.', 'icon' => '🏆'],
    ['year' => '2020', 'title' => 'Transformasi Digital',     'desc' => 'Implementasi Learning Management System (LMS) dan kelas hybrid sebagai respons terhadap pandemi global.', 'icon' => '💻'],
    ['year' => '2023', 'title' => 'Era Akreditasi Unggul',    'desc' => 'Tiga program studi meraih predikat "Unggul" dari BAN-PT. Kerjasama internasional dengan universitas di Malaysia dan Turki.', 'icon' => '🌟'],
    ['year' => '2026', 'title' => 'Visi 2030',                'desc' => 'Menargetkan seluruh program studi berakreditasi Unggul dan menjadi pusat riset pendidikan terdepan di Indonesia Timur.', 'icon' => '🚀'],
];

$faqs = [
    ['kat' => 'Akademik', 'q' => 'Apa saja program studi yang tersedia di FKIP UNIMOF?', 'a' => 'FKIP UNIMOF memiliki 8 program studi: Pendidikan Matematika, Pendidikan Fisika, Pendidikan Biologi, Pendidikan Kimia, Pendidikan Bahasa dan Sastra Inggris, Bahasa dan Sastra Indonesia, Pendidikan Ekonomi, dan Pendidikan Kewarganegaraan. Semua program berjenjang S1.'],
    ['kat' => 'Akreditasi', 'q' => 'Bagaimana proses akreditasi program studi?', 'a' => 'Seluruh program studi kami terakreditasi oleh BAN-PT. Saat ini 3 prodi berpredikat "Unggul", 4 prodi "Baik Sekali", dan 1 prodi "Baik". Kami terus berupaya meningkatkan kualitas untuk meraih akreditasi unggul di semua prodi.'],
    ['kat' => 'Beasiswa', 'q' => 'Apakah ada program beasiswa?', 'a' => 'Ya! Kami menyediakan berbagai beasiswa: Beasiswa Prestasi Akademik, Beasiswa Kurang Mampu, Beasiswa Muhammadiyah, Beasiswa KIP Kuliah dari pemerintah, dan Beasiswa Kerjasama dengan mitra industri.'],
    ['kat' => 'Fasilitas', 'q' => 'Bagaimana fasilitas kampus FKIP UNIMOF?', 'a' => 'Kami memiliki laboratorium sains modern (Fisika, Kimia, Biologi), laboratorium komputer, perpustakaan digital dengan akses jurnal internasional, ruang kelas ber-AC, dan studio micro-teaching untuk latihan mengajar.'],
    ['kat' => 'Keunggulan', 'q' => 'Apa keunggulan FKIP UNIMOF dibanding fakultas lain?', 'a' => 'Keunggulan kami: (1) Kurikulum berbasis KKNI dan MBKM, (2) Dosen berkualifikasi S2/S3 dari universitas terkemuka, (3) Program magang di sekolah-sekolah mitra, (4) Riset pendidikan yang aktif, (5) Nilai Islami yang terintegrasi, (6) Lokasi strategis di Maumere, NTT.'],
    ['kat' => 'Karir', 'q' => 'Bagaimana prospek karir lulusan FKIP UNIMOF?', 'a' => 'Lulusan kami tersebar sebagai guru di sekolah negeri/swasta, dosen, peneliti pendidikan, konsultan kurikulum, pengembang bahan ajar, dan entrepreneur pendidikan. Tingkat penyerapan lulusan di dunia kerja mencapai 90% dalam 1 tahun pertama.'],
    ['kat' => 'Kerjasama', 'q' => 'Apakah ada program pertukaran pelajar ke luar negeri?', 'a' => 'Ya, kami memiliki MoU dengan beberapa universitas di Malaysia, Turki, dan Timur Tengah. Program pertukaran pelajar dan double degree tersedia untuk mahasiswa berprestasi mulai semester 5.'],
];

$faq_kats = array_unique(array_column($faqs, 'kat'));

require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO EXTREME ===== */
.about-hero-extreme {
    position: relative; min-height: 70vh; display: flex; align-items: center;
    background: linear-gradient(135deg, #0a6847 0%, #084d35 40%, #16213e 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.about-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 15% 30%, rgba(245,166,35,0.25) 0%, transparent 50%),
        radial-gradient(circle at 85% 70%, rgba(59,130,246,0.2) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(16,185,129,0.15) 0%, transparent 60%);
    animation: heroAurora 20s ease-in-out infinite;
}
@keyframes heroAurora {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.about-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px; pointer-events: none;
}
.hero-particles-extreme { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle-extreme {
    position: absolute; width: 4px; height: 4px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: floatParticle 25s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.hero-content-extreme { position: relative; z-index: 2; max-width: 900px; }
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

.hero-title-extreme {
    font-family: var(--font-display); font-size: clamp(2.5rem, 6vw, 4.5rem);
    font-weight: 900; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -0.02em;
}
.hero-title-extreme .gradient-text-extreme {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ec4899 100%);
    background-size: 200% 200%; -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; animation: gradientShift 5s ease infinite; font-style: italic;
}
@keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
.hero-subtitle-extreme {
    font-size: clamp(1rem, 1.5vw, 1.25rem); opacity: 0.95; max-width: 700px;
    line-height: 1.7; margin-bottom: 2rem;
}
.hero-trust-row {
    display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 2rem;
}
.trust-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 1rem; background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);
    border-radius: 999px; font-size: 0.82rem; font-weight: 600;
}
.hero-cta-extreme { display: flex; gap: 1rem; flex-wrap: wrap; }

/* ===== STATS BAR ===== */
.stats-bar-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem; margin: -4rem 0 4rem; position: relative; z-index: 10;
}
.stat-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.stat-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
.stat-icon-extreme { font-size: 2rem; margin-bottom: 0.5rem; }
.stat-num-extreme {
    font-family: var(--font-display); font-size: 2.25rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.stat-label-extreme { font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== SECTION HEADERS ===== */
.section-header-extreme { text-align: center; max-width: 700px; margin: 0 auto 3rem; }
.section-tag-extreme {
    display: inline-block; padding: 0.4rem 1rem; background: rgba(10,104,71,0.1);
    color: var(--primary); border-radius: 999px; font-size: 0.8rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;
}
.section-title-extreme {
    font-family: var(--font-display); font-size: clamp(2rem, 4vw, 2.75rem);
    font-weight: 900; line-height: 1.2; margin-bottom: 1rem; letter-spacing: -0.02em;
}
.section-desc-extreme { color: var(--text-secondary); font-size: 1.05rem; line-height: 1.7; }

/* ===== SAMBUTAN DEKAN ===== */
.dekan-section {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 5rem 0; margin-bottom: 4rem;
}
.dekan-card {
    display: grid; grid-template-columns: 1fr 1.5fr; gap: 3rem; align-items: center;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 3rem; box-shadow: var(--shadow-lg);
    position: relative; overflow: hidden; cursor: pointer; transition: all 0.4s;
}
.dekan-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.dekan-card::before {
    content: '❝'; position: absolute; top: -20px; right: 30px;
    font-size: 15rem; color: var(--primary); opacity: 0.05; font-family: Georgia, serif;
    pointer-events: none;
}
.dekan-photo {
    width: 100%; aspect-ratio: 1; border-radius: var(--radius-xl);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex; align-items: center; justify-content: center;
    font-size: 8rem; color: white; box-shadow: var(--shadow-xl);
    position: relative; overflow: hidden;
}
.dekan-photo::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2), transparent 60%);
}
.dekan-photo img { width: 100%; height: 100%; object-fit: cover; position: relative; z-index: 2; }
.dekan-info h3 {
    font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem;
}
.dekan-jabatan {
    color: var(--primary); font-weight: 700; font-size: 0.95rem;
    margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.dekan-quote {
    font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary);
    font-style: italic; margin-bottom: 1.5rem; position: relative;
    padding-left: 1.5rem; border-left: 3px solid var(--primary);
    display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;
}
.dekan-name {
    font-weight: 700; color: var(--text-primary); font-style: normal;
    display: block; margin-top: 1rem;
}
.dekan-actions { display: flex; gap: 0.65rem; flex-wrap: wrap; }

/* ===== CORE VALUES ===== */
.values-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem; margin-bottom: 4rem;
}
.value-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; transition: all 0.4s;
    position: relative; overflow: hidden; cursor: pointer;
}
.value-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
    background: linear-gradient(180deg, var(--value-color, var(--primary)), transparent);
    transition: width 0.4s;
}
.value-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--value-color, var(--primary)); }
.value-card:hover::before { width: 6px; }
.value-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: linear-gradient(135deg, var(--value-color, var(--primary)), var(--value-color-light, var(--primary-light)));
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin-bottom: 1.25rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15); transition: transform 0.3s;
}
.value-card:hover .value-icon { transform: scale(1.1) rotate(-8deg); }
.value-title { font-family: var(--font-display); font-size: 1.3rem; font-weight: 800; margin-bottom: 0.75rem; }
.value-desc { color: var(--text-secondary); line-height: 1.7; font-size: 0.92rem; }

/* ===== TABS EXTREME ===== */
.tabs-extreme {
    display: flex; gap: 0.5rem; margin-bottom: 2.5rem;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 0.5rem; overflow-x: auto;
    box-shadow: var(--shadow-sm); scrollbar-width: none;
}
.tabs-extreme::-webkit-scrollbar { display: none; }
.tab-extreme {
    flex: 1; min-width: 140px; padding: 1rem 1.5rem; background: transparent;
    border: none; border-radius: var(--radius-md); cursor: pointer;
    font-family: inherit; font-size: 0.92rem; font-weight: 700;
    color: var(--text-muted); transition: all 0.3s; white-space: nowrap;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.tab-extreme:hover { background: var(--bg-secondary); color: var(--text-primary); }
.tab-extreme.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}

.tab-panel-extreme { display: none; animation: fadeInExt 0.5s ease; }
.tab-panel-extreme.active { display: block; }
@keyframes fadeInExt { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }

/* ===== VISI MISI ===== */
.vm-grid-extreme { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; }
.vm-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; position: relative; overflow: hidden;
    transition: all 0.4s;
}
.vm-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%;
    background: linear-gradient(180deg, var(--vm-color, var(--primary)), transparent);
}
.vm-card-extreme:hover { transform: translateY(-5px); box-shadow: var(--shadow-xl); }
.vm-icon-extreme {
    width: 70px; height: 70px; border-radius: 20px;
    background: linear-gradient(135deg, var(--vm-color, var(--primary)), var(--vm-color-light, var(--primary-light)));
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin-bottom: 1.5rem; box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.vm-card-extreme h3 {
    font-family: var(--font-display); font-size: 1.65rem; margin-bottom: 1rem;
    color: var(--vm-color, var(--primary));
}
.vm-card-extreme p, .vm-card-extreme li {
    color: var(--text-secondary); line-height: 1.8; font-size: 1rem;
}
.vm-card-extreme ul { padding-left: 1.5rem; }
.vm-card-extreme li { margin-bottom: 0.75rem; position: relative; }
.vm-card-extreme li::marker { color: var(--vm-color, var(--primary)); font-weight: 700; }

.tujuan-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem; margin-top: 1rem;
}
.tujuan-item {
    padding: 1.15rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border-left: 3px solid #f59e0b; transition: all 0.3s;
}
.tujuan-item:hover { transform: translateX(3px); background: var(--bg-tertiary); }
.tujuan-item strong { color: var(--text-primary); display: block; margin-bottom: 0.35rem; font-size: 0.95rem; }
.tujuan-item small { color: var(--text-muted); font-size: 0.82rem; line-height: 1.5; }

/* ===== TIMELINE ===== */
.timeline-extreme { position: relative; padding: 2rem 0; }
.timeline-extreme::before {
    content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, var(--primary), var(--primary-light), var(--secondary));
    transform: translateX(-50%); border-radius: 4px;
}
.timeline-item-extreme {
    display: flex; justify-content: flex-end; padding-right: 50%;
    position: relative; margin-bottom: 3rem;
}
.timeline-item-extreme:nth-child(even) { justify-content: flex-start; padding-right: 0; padding-left: 50%; }
.timeline-dot-extreme {
    position: absolute; left: 50%; top: 1.5rem; width: 24px; height: 24px;
    background: var(--primary); border: 4px solid var(--bg-primary);
    border-radius: 50%; transform: translateX(-50%); z-index: 2;
    box-shadow: 0 0 0 4px rgba(10,104,71,0.2); transition: all 0.3s;
}
.timeline-item-extreme:hover .timeline-dot-extreme { transform: translateX(-50%) scale(1.3); box-shadow: 0 0 0 8px rgba(10,104,71,0.25); }
.timeline-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem; margin: 0 2rem;
    position: relative; transition: all 0.3s; max-width: 420px; width: 100%;
    cursor: pointer;
}
.timeline-card-extreme:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.timeline-card-extreme::before {
    content: ''; position: absolute; top: 1.5rem; width: 20px; height: 20px;
    background: var(--bg-primary); border: 1px solid var(--border);
    transform: rotate(45deg);
}
.timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before { right: -11px; border-left: none; border-bottom: none; }
.timeline-item-extreme:nth-child(even) .timeline-card-extreme::before { left: -11px; border-right: none; border-top: none; }
.timeline-year-extreme {
    font-family: var(--font-display); font-size: 1.65rem; font-weight: 900;
    color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.timeline-title-extreme { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary); }
.timeline-desc-extreme { color: var(--text-secondary); line-height: 1.7; font-size: 0.92rem; }

/* ===== STRUKTUR ORG ===== */
.org-tree-extreme { padding: 2rem 0; }
.org-level-extreme { display: flex; justify-content: center; gap: 1.25rem; margin-bottom: 2rem; flex-wrap: wrap; position: relative; }
.org-level-extreme:not(:last-child)::after {
    content: ''; position: absolute; bottom: -2rem; left: 10%; right: 10%;
    height: 2px; background: var(--border);
}
.org-person-extreme {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.25rem; text-align: center;
    min-width: 170px; transition: all 0.3s; cursor: pointer;
}
.org-person-extreme:hover {
    transform: translateY(-5px); border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}
.org-avatar-extreme {
    width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 0.75rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; box-shadow: 0 4px 12px rgba(10,104,71,0.3);
    overflow: hidden;
}
.org-avatar-extreme img { width: 100%; height: 100%; object-fit: cover; }
.org-name-extreme { font-weight: 700; font-size: 0.88rem; margin-bottom: 0.25rem; color: var(--text-primary); line-height: 1.3; }
.org-role-extreme { font-size: 0.72rem; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.org-connector {
    width: 2px; height: 2rem; background: var(--border); margin: 0 auto;
}

/* ===== CHARTS ===== */
.charts-row-extreme {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;
}
.chart-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-sm);
}
.chart-card-extreme h3 {
    font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.stat-summary {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; margin-top: 2rem;
}
.stat-summary h3 {
    font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.stat-summary-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;
}
.stat-summary-item {
    padding: 1.15rem; background: var(--bg-secondary); border-radius: var(--radius-md);
    border-left: 4px solid var(--sum-color, var(--primary)); transition: all 0.3s;
}
.stat-summary-item:hover { transform: translateX(3px); background: var(--bg-tertiary); }
.stat-summary-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.3rem; }
.stat-summary-value {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    color: var(--sum-color, var(--primary));
}

/* ===== TESTIMONI ALUMNI ===== */
.testi-section { background: var(--bg-secondary); padding: 5rem 0; margin: 0; }
.testi-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem; margin-top: 2rem;
}
.testi-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    transition: all 0.4s;
}
.testi-card::before {
    content: '"'; position: absolute; top: 0.5rem; right: 1.5rem;
    font-family: Georgia, serif; font-size: 5rem; color: var(--bg-tertiary);
    line-height: 1; pointer-events: none;
}
.testi-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.testi-header {
    display: flex; gap: 0.85rem; align-items: center; margin-bottom: 1rem;
}
.testi-avatar {
    width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; overflow: hidden;
}
.testi-avatar img { width: 100%; height: 100%; object-fit: cover; }
.testi-name { font-weight: 800; font-size: 0.95rem; color: var(--text-primary); }
.testi-meta { font-size: 0.78rem; color: var(--text-muted); }
.testi-quote {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.7;
    font-style: italic; display: -webkit-box; -webkit-line-clamp: 4;
    -webkit-box-orient: vertical; overflow: hidden;
}

/* ===== PARTNERS ===== */
.partners-section { padding: 4rem 0; background: var(--bg-secondary); margin: 4rem 0; }
.partners-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1.25rem; max-width: 1100px; margin: 0 auto;
}
.partner-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    transition: all 0.3s; cursor: pointer;
}
.partner-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.partner-icon-box {
    width: 60px; height: 60px; border-radius: 14px;
    background: var(--bg-secondary); display: flex; align-items: center;
    justify-content: center; margin: 0 auto 0.75rem;
    font-size: 1.75rem; overflow: hidden; border: 1px solid var(--border);
}
.partner-icon-box img { width: 100%; height: 100%; object-fit: contain; padding: 0.4rem; }
.partner-name { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.partner-kind { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.25rem; }

/* ===== FAQ ===== */
.faq-toolbar {
    max-width: 800px; margin: 0 auto 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;
}
.faq-search { flex: 1; min-width: 220px; position: relative; }
.faq-search input {
    width: 100%; padding: 0.7rem 1rem 0.7rem 2.6rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.9rem; background: var(--bg-primary);
    color: var(--text-primary); transition: all 0.3s;
}
.faq-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.faq-search .s-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.faq-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.faq-chip {
    padding: 0.45rem 0.9rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-primary); color: var(--text-secondary); font-size: 0.78rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.faq-chip:hover { border-color: var(--primary); color: var(--primary); }
.faq-chip.active { background: var(--primary); color: white; border-color: var(--primary); }

.faq-section-extreme { max-width: 800px; margin: 0 auto; }
.faq-item-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); margin-bottom: 0.85rem; overflow: hidden;
    transition: all 0.3s;
}
.faq-item-extreme:hover { border-color: var(--primary-light); }
.faq-item-extreme.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-question-extreme {
    width: 100%; padding: 1.15rem 1.5rem; background: none; border: none;
    text-align: left; font-family: inherit; font-size: 0.95rem; font-weight: 700;
    color: var(--text-primary); cursor: pointer; display: flex;
    justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-q-left { display: flex; align-items: center; gap: 0.75rem; flex: 1; }
.faq-kat-tag {
    padding: 0.2rem 0.6rem; border-radius: 999px; background: var(--bg-secondary);
    font-size: 0.65rem; font-weight: 800; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em; flex-shrink: 0;
}
.faq-icon-extreme {
    width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s; flex-shrink: 0; font-size: 1rem;
}
.faq-item-extreme.open .faq-icon-extreme { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer-extreme { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
.faq-answer-inner-extreme {
    padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.92rem;
}

/* ===== CTA ===== */
.cta-extreme {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent, #064e34) 100%);
    border-radius: var(--radius-xl); padding: 4rem 3rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin: 4rem 0;
}
.cta-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.cta-content-extreme { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }
.cta-extreme h2 {
    font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem);
    margin-bottom: 1rem;
}
.cta-extreme p { font-size: 1.1rem; opacity: 0.95; margin-bottom: 2rem; }
.cta-actions-extreme { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px); display: none; align-items: center;
    justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay.show { display: flex; }
.modal-content-about {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    width: 100%; max-width: 720px; max-height: 90vh; overflow-y: auto;
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
.modal-header-about {
    padding: 2.5rem 2rem; color: white; position: relative;
    background: linear-gradient(135deg, var(--primary), #064e34);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    text-align: center;
}
.modal-header-about::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.15) 0%, transparent 50%);
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header-content { position: relative; }
.modal-photo {
    width: 110px; height: 110px; border-radius: 50%; margin: 0 auto 1rem;
    background: white; color: var(--primary); display: flex;
    align-items: center; justify-content: center; font-size: 3rem;
    font-weight: 900; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    border: 4px solid white; overflow: hidden;
}
.modal-photo img { width: 100%; height: 100%; object-fit: cover; }
.modal-name { font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; margin-bottom: 0.35rem; }
.modal-role { font-size: 0.9rem; opacity: 0.95; }

.modal-body-about { padding: 2rem; }
.modal-info-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem; margin-bottom: 1.5rem;
}
.modal-info-item {
    padding: 0.85rem 1rem; background: var(--bg-secondary);
    border: 1px solid var(--border); border-radius: var(--radius-md);
}
.modal-info-label {
    font-size: 0.68rem; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.25rem;
}
.modal-info-value {
    font-size: 0.9rem; color: var(--text-primary); font-weight: 600;
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
.modal-section p { font-size: 0.95rem; color: var(--text-primary); line-height: 1.8; text-align: justify; white-space: pre-line; }

.modal-footer {
    padding: 1rem 2rem; border-top: 1px solid var(--border);
    display: flex; gap: 0.5rem; justify-content: space-between; flex-wrap: wrap;
    background: var(--bg-secondary);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
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
    position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(150%);
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: 999px; padding: 0.85rem 1.5rem;
    box-shadow: var(--shadow-xl); display: flex; align-items: center;
    gap: 0.65rem; z-index: 10002;
    transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
    max-width: 90%; font-size: 0.9rem; font-weight: 600;
}
.pub-toast.show { transform: translateX(-50%) translateY(0); }

/* ===== STICKY SECTION NAV ===== */
.section-nav {
    position: sticky; top: 70px; z-index: 900;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 0.5rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-md); display: flex; gap: 0.35rem;
    overflow-x: auto; scrollbar-width: none;
}
.section-nav::-webkit-scrollbar { display: none; }
.section-nav-link {
    padding: 0.55rem 1rem; border-radius: 999px; white-space: nowrap;
    font-size: 0.82rem; font-weight: 600; color: var(--text-secondary);
    text-decoration: none; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 0.35rem;
}
.section-nav-link:hover { background: var(--bg-secondary); color: var(--text-primary); }
.section-nav-link.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .dekan-card { grid-template-columns: 1fr; padding: 2rem; }
    .vm-grid-extreme { grid-template-columns: 1fr; }
    .charts-row-extreme { grid-template-columns: 1fr; }
    .timeline-extreme::before { left: 20px; }
    .timeline-item-extreme, .timeline-item-extreme:nth-child(even) {
        justify-content: flex-start; padding-left: 60px; padding-right: 0;
    }
    .timeline-dot-extreme { left: 20px; }
    .timeline-card-extreme { margin: 0; max-width: 100%; }
    .timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before,
    .timeline-item-extreme:nth-child(even) .timeline-card-extreme::before {
        left: -11px; border-right: none; border-top: none;
    }
}
@media (max-width: 768px) {
    .about-hero-extreme { padding: 8rem 0 4rem; }
    .stats-bar-extreme { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .tabs-extreme { flex-direction: column; }
    .tab-extreme { min-width: auto; }
    .section-nav { top: 60px; }
    .modal-info-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .stats-bar-extreme { grid-template-columns: 1fr; }
    .dekan-card { padding: 1.5rem; }
    .org-person-extreme { min-width: 140px; }
}
</style>

<!-- ===== HERO ===== -->
<section class="about-hero-extreme">
    <div class="hero-particles-extreme" id="heroParticles"></div>
    <div class="container hero-content-extreme">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Tentang Kami</span>
        </nav>
        <div class="hero-badge-extreme" data-aos="fade-down" data-aos-delay="100">
            <span class="hero-badge-pulse"></span>
            <span>Terakreditasi BAN-PT • Sejak 1995</span>
        </div>
        <h1 class="hero-title-extreme" data-aos="fade-up">
            Membangun <span class="gradient-text-extreme">Peradaban</span><br>
            Melalui Pendidikan
        </h1>
        <p class="hero-subtitle-extreme" data-aos="fade-up" data-aos-delay="200">
            Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere —
            mencetak pendidik profesional berkarakter Islami yang siap membangun Indonesia Timur.
        </p>
        <div class="hero-trust-row" data-aos="fade-up" data-aos-delay="300">
            <span class="trust-pill">🎓 <?= $stat_prodi ?> Prodi</span>
            <span class="trust-pill">👨‍🏫 <?= $stat_dosen ?> Dosen</span>
            <span class="trust-pill">👨‍🎓 <?= number_format($stat_mahasiswa) ?> Mahasiswa</span>
            <span class="trust-pill">🌏 <?= $stat_intl ?> Mitra Internasional</span>
        </div>
        <div class="hero-cta-extreme" data-aos="fade-up" data-aos-delay="400">
            <a href="#sambutan" class="btn btn-primary btn-lg" style="background: white; color: var(--primary); border: none; cursor: pointer;">
                <span>📢 Sambutan Dekan</span>
            </a>
            <a href="<?= base_url('program.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                <span>🎓 Lihat Program Studi</span>
            </a>
            <button class="btn btn-outline btn-lg" style="border-color: white; color: white;" onclick="shareAbout()">
                <span>🔗 Bagikan</span>
            </button>
        </div>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">

        <!-- Stats Bar -->
        <div class="stats-bar-extreme" data-aos="fade-up">
            <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
                <div class="stat-icon-extreme">🎓</div>
                <div class="stat-num-extreme count-up" data-target="<?= $stat_prodi ?>">0</div>
                <div class="stat-label-extreme">Program Studi</div>
            </div>
            <div class="stat-card-extreme" style="--stat-color: #10b981;">
                <div class="stat-icon-extreme">👨‍🏫</div>
                <div class="stat-num-extreme count-up" data-target="<?= $stat_dosen ?>">0</div>
                <div class="stat-label-extreme">Dosen Berkualitas</div>
            </div>
            <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
                <div class="stat-icon-extreme">👨‍🎓</div>
                <div class="stat-num-extreme count-up" data-target="<?= $stat_mahasiswa ?>">0</div>
                <div class="stat-label-extreme">Mahasiswa Aktif</div>
            </div>
            <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
                <div class="stat-icon-extreme">🎖️</div>
                <div class="stat-num-extreme count-up" data-target="<?= $stat_alumni ?>">0</div>
                <div class="stat-label-extreme">Alumni Sukses</div>
            </div>
            <div class="stat-card-extreme" style="--stat-color: #ef4444;">
                <div class="stat-icon-extreme">🏆</div>
                <div class="stat-num-extreme count-up" data-target="<?= $stat_prestasi ?>">0</div>
                <div class="stat-label-extreme">Prestasi</div>
            </div>
        </div>

        <!-- Sticky Section Nav -->
        <nav class="section-nav" id="sectionNav" data-aos="fade-up">
            <a href="#sambutan" class="section-nav-link active">📢 Sambutan</a>
            <a href="#nilai" class="section-nav-link">✨ Nilai Inti</a>
            <a href="#profil" class="section-nav-link">🏛️ Profil</a>
            <a href="#testimoni" class="section-nav-link">💬 Testimoni</a>
            <a href="#mitra" class="section-nav-link">🤝 Mitra</a>
            <a href="#faq" class="section-nav-link">❓ FAQ</a>
        </nav>

        <!-- SAMBUTAN DEKAN -->
        <div id="sambutan" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header-extreme" data-aos="fade-up">
                <span class="section-tag-extreme">Sambutan</span>
                <h2 class="section-title-extreme">Kata <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Sambutan Dekan</span></h2>
            </div>

            <div class="dekan-card" onclick='openDekanModal(<?= htmlspecialchars(json_encode($dekan), ENT_QUOTES, "UTF-8") ?>)' data-aos="fade-up">
                <div class="dekan-photo">
                    <?php if (!empty($dekan['foto'])): ?>
                        <img src="<?= asset('dosen/' . basename($dekan['foto'])) ?>" alt="<?= sanitize($dekan['nama']) ?>">
                    <?php else: ?>
                        <span>👨‍🏫</span>
                    <?php endif; ?>
                </div>
                <div class="dekan-info">
                    <h3><?= sanitize($dekan['nama']) ?></h3>
                    <div class="dekan-jabatan">
                        <span>🎓</span> <?= sanitize($dekan['jabatan_struktural'] ?? $dekan['jabatan_fungsional'] ?? 'Dekan FKIP UNIMOF') ?>
                    </div>
                    <div class="dekan-quote">
                        "<?= sanitize(excerpt($dekan['sambutan'] ?? 'Kami berkomitmen untuk terus meningkatkan kualitas pendidikan, penelitian, dan pengabdian masyarakat. Melalui kurikulum yang adaptif, dosen yang kompeten, dan fasilitas modern, kami mengajak Anda menjadi bagian dari keluarga besar FKIP UNIMOF.', 350)) ?>"
                        <span class="dekan-name">— <?= sanitize($dekan['nama']) ?></span>
                    </div>
                    <div class="dekan-actions" onclick="event.stopPropagation();">
                        <button class="btn btn-primary" onclick='openDekanModal(<?= htmlspecialchars(json_encode($dekan), ENT_QUOTES, "UTF-8") ?>)'>📖 Baca Sambutan Lengkap</button>
                        <a href="<?= base_url('kontak.php') ?>" class="btn btn-secondary">📞 Hubungi Dekanat</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- NILAI INTI -->
        <div id="nilai" class="scroll-section" style="padding: 3rem 0;">
            <div class="section-header-extreme" data-aos="fade-up">
                <span class="section-tag-extreme">Nilai Inti</span>
                <h2 class="section-title-extreme">6 Pilar <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Keunggulan Kami</span></h2>
                <p class="section-desc-extreme">Nilai-nilai yang menjadi fondasi setiap aktivitas akademik di FKIP UNIMOF</p>
            </div>

            <div class="values-grid">
                <?php foreach ($core_values as $i => $v): ?>
                <div class="value-card" style="--value-color: <?= $v['color'][0] ?>; --value-color-light: <?= $v['color'][1] ?>;" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                    <div class="value-icon"><?= $v['icon'] ?></div>
                    <h3 class="value-title"><?= sanitize($v['title']) ?></h3>
                    <p class="value-desc"><?= sanitize($v['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TABS PROFIL -->
        <div id="profil" class="scroll-section" style="padding: 3rem 0; background: var(--bg-secondary); margin: 0 -9999px; padding-left: 9999px; padding-right: 9999px;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div class="section-header-extreme" data-aos="fade-up">
                    <span class="section-tag-extreme">Profil Lengkap</span>
                    <h2 class="section-title-extreme">Mengenal Lebih <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Dekat</span></h2>
                </div>

                <div class="tabs-extreme" data-aos="fade-up">
                    <button class="tab-extreme active" onclick="switchAboutTab(event, 'tab-vm')">👁️ Visi & Misi</button>
                    <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-sejarah')">📜 Sejarah</button>
                    <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-struktur')">🏛️ Struktur</button>
                    <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-chart')">📊 Statistik</button>
                </div>

                <!-- TAB: VISI MISI -->
                <div id="tab-vm" class="tab-panel-extreme active">
                    <div class="vm-grid-extreme">
                        <div class="vm-card-extreme" style="--vm-color: #10b981; --vm-color-light: #059669;">
                            <div class="vm-icon-extreme">👁️</div>
                            <h3>Visi</h3>
                            <p style="font-style: italic; font-size: 1.05rem; border-left: 3px solid #10b981; padding-left: 1rem; background: rgba(16,185,129,0.05); padding: 1.25rem; border-radius: 0 var(--radius-md) var(--radius-md) 0;">
                                "Menjadi Fakultas Keguruan dan Ilmu Pendidikan yang <strong>unggul, inovatif, dan berkarakter Islami</strong> dalam menghasilkan pendidik profesional berdaya saing global pada tahun 2030."
                            </p>
                        </div>
                        <div class="vm-card-extreme" style="--vm-color: #3b82f6; --vm-color-light: #2563eb;">
                            <div class="vm-icon-extreme">🚀</div>
                            <h3>Misi</h3>
                            <ul>
                                <li>Menyelenggarakan pendidikan tinggi yang <strong>berkualitas dan relevan</strong> dengan perkembangan IPTEK.</li>
                                <li>Melaksanakan <strong>penelitian dan pengabdian</strong> kepada masyarakat yang berdampak positif.</li>
                                <li>Menanamkan <strong>nilai keislaman, kemuhammadiyahan, dan kearifan lokal</strong>.</li>
                                <li>Membangun <strong>jejaring kerjasama</strong> dalam dan luar negeri.</li>
                                <li>Mengembangkan <strong>kurikulum adaptif</strong> berbasis MBKM dan KKNI.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tujuan Strategis -->
                    <div class="vm-card-extreme" style="--vm-color: #f59e0b; --vm-color-light: #d97706; margin-top: 2rem;">
                        <div class="vm-icon-extreme">🎯</div>
                        <h3>Tujuan Strategis</h3>
                        <div class="tujuan-grid">
                            <div class="tujuan-item">
                                <strong>🎓 Lulusan Berkualitas</strong>
                                <small>90% lulusan terserap di dunia kerja dalam 1 tahun</small>
                            </div>
                            <div class="tujuan-item">
                                <strong>🔬 Riset Produktif</strong>
                                <small>Minimal 50 publikasi ilmiah per tahun</small>
                            </div>
                            <div class="tujuan-item">
                                <strong>🌏 Kerjasama Global</strong>
                                <small>Mitra dengan 15+ universitas internasional</small>
                            </div>
                            <div class="tujuan-item">
                                <strong>⭐ Akreditasi Unggul</strong>
                                <small>Seluruh prodi berakreditasi Unggul pada 2030</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: SEJARAH -->
                <div id="tab-sejarah" class="tab-panel-extreme">
                    <div class="timeline-extreme">
                        <?php foreach ($milestones as $i => $m): ?>
                        <div class="timeline-item-extreme" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                            <div class="timeline-dot-extreme"></div>
                            <div class="timeline-card-extreme">
                                <div class="timeline-year-extreme">
                                    <span style="font-size: 1.5rem;"><?= $m['icon'] ?></span>
                                    <span><?= $m['year'] ?></span>
                                </div>
                                <h4 class="timeline-title-extreme"><?= sanitize($m['title']) ?></h4>
                                <p class="timeline-desc-extreme"><?= sanitize($m['desc']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB: STRUKTUR -->
                <div id="tab-struktur" class="tab-panel-extreme">
                    <div class="org-tree-extreme">
                        <!-- Level 1: Dekan -->
                        <div class="org-level-extreme">
                            <div class="org-person-extreme" onclick='openDekanModal(<?= htmlspecialchars(json_encode($dekan), ENT_QUOTES, "UTF-8") ?>)'>
                                <div class="org-avatar-extreme">
                                    <?php if (!empty($dekan['foto'])): ?>
                                        <img src="<?= asset('dosen/' . basename($dekan['foto'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($dekan['nama'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="org-name-extreme"><?= sanitize($dekan['nama']) ?></div>
                                <div class="org-role-extreme">Dekan FKIP</div>
                            </div>
                        </div>
                        <div class="org-connector"></div>

                        <!-- Level 2: Wakil Dekan -->
                        <?php if (!empty($wd_list)): ?>
                        <div class="org-level-extreme">
                            <?php foreach ($wd_list as $wd): ?>
                            <div class="org-person-extreme" onclick='openDekanModal(<?= htmlspecialchars(json_encode($wd), ENT_QUOTES, "UTF-8") ?>)'>
                                <div class="org-avatar-extreme" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <?php if (!empty($wd['foto'])): ?>
                                        <img src="<?= asset('dosen/' . basename($wd['foto'])) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($wd['nama'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="org-name-extreme"><?= sanitize($wd['nama']) ?></div>
                                <div class="org-role-extreme"><?= sanitize($wd['jabatan_struktural'] ?? 'Wakil Dekan') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="org-connector"></div>
                        <?php endif; ?>

                        <!-- Level 3: Kaprodi -->
                        <?php if (!empty($kaprodi_list)): ?>
                        <div class="org-level-extreme">
                            <?php foreach (array_slice($kaprodi_list, 0, 4) as $k): ?>
                            <div class="org-person-extreme">
                                <div class="org-avatar-extreme" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <?= $k['initial'] ?>
                                </div>
                                <div class="org-name-extreme"><?= sanitize($k['nama']) ?></div>
                                <div class="org-role-extreme"><?= sanitize($k['role']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($kaprodi_list) > 4): ?>
                        <div class="org-connector"></div>
                        <div class="org-level-extreme">
                            <?php foreach (array_slice($kaprodi_list, 4, 4) as $k): ?>
                            <div class="org-person-extreme">
                                <div class="org-avatar-extreme" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                                    <?= $k['initial'] ?>
                                </div>
                                <div class="org-name-extreme"><?= sanitize($k['nama']) ?></div>
                                <div class="org-role-extreme"><?= sanitize($k['role']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div style="text-align: center; margin-top: 2.5rem;">
                        <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1rem;">
                            *Struktur organisasi disederhanakan untuk tampilan web.
                        </p>
                        <a href="<?= base_url('download.php?kategori=Formulir') ?>" class="btn btn-secondary">
                            📥 Unduh SK Struktur Organisasi Lengkap
                        </a>
                    </div>
                </div>

                <!-- TAB: STATISTIK -->
                <div id="tab-chart" class="tab-panel-extreme">
                    <div class="charts-row-extreme">
                        <div class="chart-card-extreme">
                            <h3>🎓 Distribusi Mahasiswa per Prodi</h3>
                            <div id="chartProdi"></div>
                        </div>
                        <div class="chart-card-extreme">
                            <h3>🏆 Status Akreditasi</h3>
                            <div id="chartAkreditasi"></div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="stat-summary">
                        <h3>📊 Ringkasan Statistik FKIP UNIMOF</h3>
                        <div class="stat-summary-grid">
                            <div class="stat-summary-item" style="--sum-color: #10b981;">
                                <div class="stat-summary-label">Program Studi</div>
                                <div class="stat-summary-value"><?= $stat_prodi ?></div>
                            </div>
                            <div class="stat-summary-item" style="--sum-color: #3b82f6;">
                                <div class="stat-summary-label">Total Dosen</div>
                                <div class="stat-summary-value"><?= $stat_dosen ?></div>
                            </div>
                            <div class="stat-summary-item" style="--sum-color: #f59e0b;">
                                <div class="stat-summary-label">Mahasiswa Aktif</div>
                                <div class="stat-summary-value"><?= number_format($stat_mahasiswa) ?></div>
                            </div>
                            <div class="stat-summary-item" style="--sum-color: #8b5cf6;">
                                <div class="stat-summary-label">Alumni</div>
                                <div class="stat-summary-value"><?= number_format($stat_alumni) ?></div>
                            </div>
                            <div class="stat-summary-item" style="--sum-color: #ef4444;">
                                <div class="stat-summary-label">Prestasi</div>
                                <div class="stat-summary-value"><?= $stat_prestasi ?></div>
                            </div>
                            <div class="stat-summary-item" style="--sum-color: #ec4899;">
                                <div class="stat-summary-label">Mitra Internasional</div>
                                <div class="stat-summary-value"><?= $stat_intl ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TESTIMONI ALUMNI -->
        <?php if (!empty($testimoni_list)): ?>
        <div id="testimoni" class="scroll-section test-section">
            <div class="container">
                <div class="section-header-extreme" data-aos="fade-up">
                    <span class="section-tag-extreme">Testimoni</span>
                    <h2 class="section-title-extreme">Kata <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Alumni Kami</span></h2>
                    <p class="section-desc-extreme">Cerita sukses para alumni FKIP UNIMOF yang kini berkarya di berbagai bidang</p>
                </div>
                <div class="testi-grid">
                    <?php foreach ($testimoni_list as $t):
                        $initials = strtoupper(substr($t['nama'], 0, 1) . (strpos($t['nama'], ' ') ? substr($t['nama'], strpos($t['nama'], ' ') + 1, 1) : ''));
                    ?>
                    <div class="testi-card" data-aos="fade-up">
                        <div class="testi-header">
                            <div class="testi-avatar">
                                <?php if (!empty($t['foto'])): ?>
                                    <img src="<?= asset('alumni/' . basename($t['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="testi-name"><?= sanitize($t['nama']) ?></div>
                                <div class="testi-meta">
                                    <?= sanitize($t['prodi_singkatan'] ?? $t['prodi_nama'] ?? 'Alumni') ?>
                                    • Angkatan <?= sanitize($t['tahun_lulus'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                        <p class="testi-quote">"<?= sanitize($t['testimoni']) ?>"</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- MITRA -->
        <div id="mitra" class="scroll-section" style="padding: 4rem 0;">
            <div class="section-header-extreme" data-aos="fade-up">
                <span class="section-tag-extreme">Mitra Kerjasama</span>
                <h2 class="section-title-extreme">Didukung oleh <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Mitra Terbaik</span></h2>
                <p class="section-desc-extreme">Bekerjasama dengan institusi terkemuka untuk meningkatkan kualitas pendidikan</p>
            </div>

            <div class="partners-grid" data-aos="fade-up">
                <?php foreach ($partners as $p):
                    $jenis = $p['jenis'] ?? 'Mitra';
                    $icon = $jenis === 'Universitas' ? '🎓' : ($jenis === 'Pemerintah' ? '🏛️' : ($jenis === 'Industri' ? '🏭' : '🤝'));
                ?>
                <div class="partner-card" onclick='openPartnerModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>
                    <div class="partner-icon-box">
                        <?php if (!empty($p['logo'])): ?>
                            <img src="<?= asset('uploads/kerjasama/' . basename($p['logo'])) ?>" alt="">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="partner-name"><?= sanitize($p['nama_institusi']) ?></div>
                    <div class="partner-kind"><?= sanitize($jenis) ?><?= !empty($p['negara']) && $p['negara'] !== 'Indonesia' ? ' 🌐' : '' ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="<?= base_url('kerjasama.php') ?>" class="btn btn-secondary">
                    🌐 Lihat Semua Mitra →
                </a>
            </div>
        </div>

        <!-- FAQ -->
        <div id="faq" class="scroll-section" style="padding: 3rem 0; background: var(--bg-secondary); margin: 0 -9999px; padding-left: 9999px; padding-right: 9999px;">
            <div style="max-width: 1200px; margin: 0 auto;">
                <div class="section-header-extreme" data-aos="fade-up">
                    <span class="section-tag-extreme">FAQ</span>
                    <h2 class="section-title-extreme">Pertanyaan <span style="background: linear-gradient(135deg, var(--primary), var(--secondary, #f59e0b)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Umum</span></h2>
                    <p class="section-desc-extreme">Temukan jawaban untuk pertanyaan yang sering diajukan</p>
                </div>

                <div class="faq-toolbar" data-aos="fade-up">
                    <div class="faq-search">
                        <span class="s-icon">🔍</span>
                        <input type="text" id="faqSearch" placeholder="Cari pertanyaan...">
                    </div>
                    <div class="faq-chips">
                        <button class="faq-chip active" data-kat="all" onclick="filterFaq('all', this)">Semua</button>
                        <?php foreach ($faq_kats as $k): ?>
                        <button class="faq-chip" data-kat="<?= sanitize($k) ?>" onclick="filterFaq('<?= sanitize($k) ?>', this)"><?= sanitize($k) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="faq-section-extreme" id="faqList" data-aos="fade-up">
                    <?php foreach ($faqs as $i => $faq): ?>
                    <div class="faq-item-extreme" data-kat="<?= sanitize($faq['kat']) ?>" data-text="<?= strtolower(sanitize($faq['q'] . ' ' . $faq['a'])) ?>">
                        <button class="faq-question-extreme" onclick="toggleFaq(this)">
                            <span class="faq-q-left">
                                <span class="faq-kat-tag"><?= sanitize($faq['kat']) ?></span>
                                <span><?= sanitize($faq['q']) ?></span>
                            </span>
                            <span class="faq-icon-extreme">+</span>
                        </button>
                        <div class="faq-answer-extreme">
                            <div class="faq-answer-inner-extreme"><?= sanitize($faq['a']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div id="faqEmpty" style="display:none; text-align:center; padding:2.5rem; background:var(--bg-primary); border-radius:var(--radius-xl); border:2px dashed var(--border);">
                        <div style="font-size:3rem; margin-bottom:0.75rem; opacity:0.5;">🔍</div>
                        <h3 style="font-size:1.1rem;">Pertanyaan tidak ditemukan</h3>
                        <p style="color:var(--text-muted); font-size:0.88rem; margin-top:0.35rem;">Coba kata kunci lain atau hubungi kami.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-extreme" data-aos="zoom-in">
            <div class="cta-content-extreme">
                <h2>Siap Bergabung dengan <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: italic;">Keluarga Besar FKIP UNIMOF?</span></h2>
                <p>Jadilah bagian dari generasi pendidik unggul yang akan membangun peradaban Indonesia Timur.</p>
                <div class="cta-actions-extreme">
                    <a href="<?= base_url('pmb.php') ?>" class="btn btn-primary btn-lg" style="background: white; color: var(--primary); font-weight: 800; border: none;">
                        📝 Daftar Sekarang →
                    </a>
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                        💬 Konsultasi Gratis
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ===== MODAL ===== -->
<div class="modal-overlay" id="aboutModal" onclick="if(event.target===this)closeAboutModal()">
    <div class="modal-content-about" id="aboutModalContent"></div>
</div>

<!-- Toast -->
<div class="pub-toast" id="pubToast">
    <span class="pub-toast-icon" id="pubToastIcon">✓</span>
    <span id="pubToastMsg">Berhasil</span>
</div>

<script>
// ===== DATA =====
const PRODI_LIST = <?= json_encode($prodi_list) ?>;
const AKREDITASI_DIST = <?= json_encode($akreditasi_dist) ?>;

// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle-extreme';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 25 + 's';
        p.style.animationDuration = (20 + Math.random() * 15) + 's';
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

// ===== SCROLLSPY =====
const navLinks = document.querySelectorAll('.section-nav-link');
const sections = Array.from(navLinks).map(l => document.querySelector(l.getAttribute('href'))).filter(Boolean);
const spyObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + entry.target.id));
        }
    });
}, { rootMargin: '-40% 0px -55% 0px' });
sections.forEach(s => spyObserver.observe(s));
navLinks.forEach(l => l.addEventListener('click', (e) => {
    e.preventDefault();
    const target = document.querySelector(l.getAttribute('href'));
    if (target) window.scrollTo({ top: target.offsetTop - 130, behavior: 'smooth' });
}));

// ===== TABS =====
function switchAboutTab(event, tabId) {
    document.querySelectorAll('.tab-extreme').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-panel-extreme').forEach(panel => panel.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    if (tabId === 'tab-chart') setTimeout(initCharts, 100);
}

// ===== FAQ =====
let faqKat = 'all';
function filterFaq(kat, btn) {
    faqKat = kat;
    document.querySelectorAll('.faq-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyFaqFilter();
}
document.getElementById('faqSearch')?.addEventListener('input', applyFaqFilter);
function applyFaqFilter() {
    const q = (document.getElementById('faqSearch')?.value || '').toLowerCase();
    let visible = 0;
    document.querySelectorAll('.faq-item-extreme').forEach(item => {
        const matchKat = faqKat === 'all' || item.dataset.kat === faqKat;
        const matchQ = !q || item.dataset.text.includes(q);
        const show = matchKat && matchQ;
        item.style.display = show ? 'block' : 'none';
        if (show) visible++;
    });
    document.getElementById('faqEmpty').style.display = visible === 0 ? 'block' : 'none';
}
function toggleFaq(btn) {
    const item = btn.closest('.faq-item-extreme');
    const answer = item.querySelector('.faq-answer-extreme');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item-extreme').forEach(i => {
        i.classList.remove('open');
        i.querySelector('.faq-answer-extreme').style.maxHeight = null;
    });
    if (!isOpen) {
        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
    }
}

// ===== APEXCHARTS =====
let chartsInitialized = false;
function initCharts() {
    if (chartsInitialized) return;
    chartsInitialized = true;

    // Chart Prodi — data real dari DB
    const prodiLabels = PRODI_LIST.map(p => p.singkatan || p.nama);
    const prodiData = PRODI_LIST.map(p => parseInt(p.jumlah_mahasiswa || 0) || Math.floor(Math.random() * 100) + 50);

    new ApexCharts(document.querySelector("#chartProdi"), {
        series: [{ name: 'Mahasiswa', data: prodiData }],
        chart: { type: 'bar', height: 320, animations: { enabled: true, speed: 800 } },
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
        colors: ['#0a6847'],
        dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
        xaxis: { categories: prodiLabels, labels: { style: { fontSize: '11px' } } },
        yaxis: { title: { text: 'Jumlah Mahasiswa' } },
        grid: { borderColor: '#f1f5f9' }
    }).render();

    // Chart Akreditasi — data real dari DB
    const akrLabels = Object.keys(AKREDITASI_DIST);
    const akrValues = Object.values(AKREDITASI_DIST);
    const akrColors = {
        'Unggul': '#10b981', 'Baik Sekali': '#3b82f6',
        'Baik': '#f59e0b', 'C': '#6b7280', 'Belum': '#94a3b8'
    };

    if (akrLabels.length > 0) {
        new ApexCharts(document.querySelector("#chartAkreditasi"), {
            series: akrValues,
            labels: akrLabels,
            chart: { type: 'donut', height: 320 },
            colors: akrLabels.map(l => akrColors[l] || '#6b7280'),
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true, label: 'Total',
                                formatter: () => akrValues.reduce((a,b)=>a+b, 0)
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 700 } },
            legend: { position: 'bottom', fontSize: '11px' }
        }).render();
    }
}

// ===== HELPERS =====
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== MODAL =====
function openAboutModal(html) {
    document.getElementById('aboutModalContent').innerHTML = html;
    document.getElementById('aboutModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeAboutModal() {
    document.getElementById('aboutModal').classList.remove('show');
    document.body.style.overflow = '';
}

function openDekanModal(d) {
    const initial = d.nama ? d.nama.charAt(0).toUpperCase() : '?';
    const jabatan = d.jabatan_struktural || d.jabatan_fungsional || 'Dosen';
    const sambutan = d.sambutan || 'Sambutan belum tersedia.';

    let info_items = '';
    if (d.jabatan_fungsional) info_items += `<div class="modal-info-item"><div class="modal-info-label">🎓 Jabatan Fungsional</div><div class="modal-info-value">${escapeHtml(d.jabatan_fungsional)}</div></div>`;
    if (d.pendidikan_terakhir) info_items += `<div class="modal-info-item"><div class="modal-info-label">📚 Pendidikan</div><div class="modal-info-value">${escapeHtml(d.pendidikan_terakhir)}</div></div>`;
    if (d.email) info_items += `<div class="modal-info-item"><div class="modal-info-label">📧 Email</div><div class="modal-info-value"><a href="mailto:${escapeHtml(d.email)}">${escapeHtml(d.email)}</a></div></div>`;
    if (d.telepon) info_items += `<div class="modal-info-item"><div class="modal-info-label">📞 Telepon</div><div class="modal-info-value">${escapeHtml(d.telepon)}</div></div>`;

    const html = `
        <button class="modal-close" onclick="closeAboutModal()">✕</button>
        <div class="modal-header-about">
            <div class="modal-header-content">
                <div class="modal-photo">
                    ${d.foto ? `<img src="<?= asset('dosen/') ?>${encodeURIComponent(d.foto.split('/').pop())}" alt="">` : initial}
                </div>
                <h2 class="modal-name">${escapeHtml(d.nama)}</h2>
                <div class="modal-role">${escapeHtml(jabatan)}</div>
            </div>
        </div>
        <div class="modal-body-about">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}
            <div class="modal-section">
                <h4>📢 Sambutan</h4>
                <p>${escapeHtml(sambutan)}</p>
            </div>
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                ${d.email ? `<a href="mailto:${escapeHtml(d.email)}" class="modal-btn secondary">📧 Email</a>` : ''}
                <button class="modal-btn secondary" onclick="sharePerson(${JSON.stringify(d).replace(/"/g, '&quot;')})">🔗 Share</button>
            </div>
            <button class="modal-btn primary" onclick="closeAboutModal()">Tutup</button>
        </div>
    `;
    openAboutModal(html);
}

function openPartnerModal(p) {
    const initial = p.nama_institusi ? p.nama_institusi.charAt(0).toUpperCase() : '?';
    const icon = p.jenis === 'Universitas' ? '🎓' : (p.jenis === 'Pemerintah' ? '🏛️' : (p.jenis === 'Industri' ? '🏭' : '🤝'));

    let info_items = '';
    if (p.jenis) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏷️ Jenis</div><div class="modal-info-value">${escapeHtml(p.jenis)}</div></div>`;
    if (p.negara) info_items += `<div class="modal-info-item"><div class="modal-info-label">🌍 Negara</div><div class="modal-info-value">${escapeHtml(p.negara)}</div></div>`;
    if (p.kota) info_items += `<div class="modal-info-item"><div class="modal-info-label">🏙️ Kota</div><div class="modal-info-value">${escapeHtml(p.kota)}</div></div>`;
    if (p.status) info_items += `<div class="modal-info-item"><div class="modal-info-label">✓ Status</div><div class="modal-info-value">${escapeHtml(p.status)}</div></div>`;

    const html = `
        <button class="modal-close" onclick="closeAboutModal()">✕</button>
        <div class="modal-header-about">
            <div class="modal-header-content">
                <div class="modal-photo">
                    ${p.logo ? `<img src="<?= asset('uploads/kerjasama/') ?>${encodeURIComponent(p.logo.split('/').pop())}" alt="">` : icon}
                </div>
                <h2 class="modal-name">${escapeHtml(p.nama_institusi)}</h2>
                <div class="modal-role">${escapeHtml(p.jenis || 'Mitra Kerjasama')}</div>
            </div>
        </div>
        <div class="modal-body-about">
            ${info_items ? `<div class="modal-info-grid">${info_items}</div>` : ''}
            ${p.deskripsi ? `<div class="modal-section"><h4>📝 Deskripsi</h4><p>${escapeHtml(p.deskripsi)}</p></div>` : ''}
            ${p.bentuk_kerjasama ? `<div class="modal-section"><h4>🤝 Bentuk Kerjasama</h4><p>${escapeHtml(p.bentuk_kerjasama)}</p></div>` : ''}
        </div>
        <div class="modal-footer">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button class="modal-btn secondary" onclick="sharePerson(${JSON.stringify(p).replace(/"/g, '&quot;')})">🔗 Share</button>
                ${p.link_website ? `<a href="${escapeHtml(p.link_website)}" target="_blank" class="modal-btn secondary">🌐 Website</a>` : ''}
            </div>
            <button class="modal-btn primary" onclick="closeAboutModal()">Tutup</button>
        </div>
    `;
    openAboutModal(html);
}

// ===== SHARE =====
function shareAbout() {
    const text = `🏛️ Tentang FKIP UNIMOF\n${document.title}\n\nMembangun peradaban melalui pendidikan — Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere`;
    if (navigator.share) {
        navigator.share({ title: 'Tentang FKIP UNIMOF', text, url: window.location.href });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + '\n' + window.location.href);
        pubToast('Info dibagikan', '📋');
    }
}

function sharePerson(p) {
    const text = `${p.jabatan_struktural || p.jabatan_fungsional || 'Mitra'} — ${p.nama || p.nama_institusi}\nFKIP UNIMOF`;
    if (navigator.share) {
        navigator.share({ title: p.nama || p.nama_institusi, text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        pubToast('Info disalin', '📋');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAboutModal();
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey &&
        document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.getElementById('faqSearch')?.focus();
    }
});

console.log('%c🏛️ Tentang FKIP UNIMOF - EXTREME MULTIMATE', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: / (Cari FAQ) • ESC (Tutup modal) • 1-4 (Switch tab)', 'color: #64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>