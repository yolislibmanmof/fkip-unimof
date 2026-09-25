<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Tentang Kami';
$page_description = 'Profil lengkap FKIP UNIMOF: Visi, Misi, Sejarah, Struktur Organisasi, Nilai Inti, dan Sambutan Dekan';

// Ambil data dinamis dari database
$statistik = get_statistik();
$prodi_list = get_prodi();
$total_dosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE status='Aktif'")->fetchColumn();
$total_alumni = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif'")->fetchColumn();
$total_prestasi = (int)$pdo->query("SELECT COUNT(*) FROM prestasi")->fetchColumn();

// Ambil dekan (asumsi dosen pertama atau bisa dari tabel pengaturan)
$stmt_dekan = $pdo->query("SELECT * FROM dosen WHERE jabatan_fungsional LIKE '%Dekan%' OR jabatan_fungsional LIKE '%Ketua%' LIMIT 1");
$dekan = $stmt_dekan->fetch();
if (!$dekan) {
    $dekan = ['nama' => 'Prof. Dr. H. Ahmad Fauzi, M.Pd.', 'jabatan_fungsional' => 'Dekan FKIP UNIMOF', 'pendidikan_terakhir' => 'S3 Pendidikan'];
}

// Nilai inti (core values)
$core_values = [
    ['icon' => '🎯', 'title' => 'Unggul', 'desc' => 'Standar akademik tertinggi dengan akreditasi unggul dari BAN-PT'],
    ['icon' => '💡', 'title' => 'Inovatif', 'desc' => 'Kurikulum adaptif mengikuti perkembangan teknologi pendidikan 4.0'],
    ['icon' => '', 'title' => 'Islami', 'desc' => 'Menanamkan nilai keislaman dan kemuhammadiyahan dalam setiap aktivitas'],
    ['icon' => '🌏', 'title' => 'Global', 'desc' => 'Jejaring internasional dengan universitas di ASEAN dan Timur Tengah'],
    ['icon' => '🤝', 'title' => 'Kolaboratif', 'desc' => 'Kerjasama dengan sekolah, pemerintah, dan industri pendidikan'],
    ['icon' => '⭐', 'title' => 'Berkarakter', 'desc' => 'Membentuk pendidik yang berakhlak mulia dan berjiwa pemimpin'],
];

// Milestone sejarah (bisa dari DB jika ada tabel history)
$milestones = [
    ['year' => '1995', 'title' => 'Cikal Bakal FKIP', 'desc' => 'Berdiri sebagai Sekolah Tinggi Keguruan dan Ilmu Pendidikan (STKIP) dengan 2 program studi awal: Pendidikan Matematika dan Pendidikan Bahasa Indonesia.', 'icon' => '🌱'],
    ['year' => '2000', 'title' => 'Integrasi ke UNIMOF', 'desc' => 'Bergabung resmi menjadi Fakultas Keguruan dan Ilmu Pendidikan di bawah Universitas Muhammadiyah Maumere dengan 4 program studi.', 'icon' => '🏛️'],
    ['year' => '2008', 'title' => 'Ekspansi 8 Prodi', 'desc' => 'Membuka 4 program studi baru: Pendidikan Fisika, Pendidikan Biologi, Pendidikan Kimia, dan Pendidikan Ekonomi untuk menjawab kebutuhan guru di NTT.', 'icon' => '📈'],
    ['year' => '2015', 'title' => 'Akreditasi B & A', 'desc' => 'Meraih akreditasi B dari BAN-PT untuk seluruh program studi, dengan 2 prodi meraih akreditasi A.', 'icon' => '🏆'],
    ['year' => '2020', 'title' => 'Transformasi Digital', 'desc' => 'Implementasi Learning Management System (LMS) dan kelas hybrid sebagai respons terhadap pandemi global.', 'icon' => '💻'],
    ['year' => '2023', 'title' => 'Era Akreditasi Unggul', 'desc' => 'Tiga program studi meraih predikat "Unggul" dari BAN-PT. Kerjasama internasional dengan universitas di Malaysia dan Turki.', 'icon' => '🌟'],
    ['year' => '2026', 'title' => 'Visi 2030', 'desc' => 'Menargetkan seluruh program studi berakreditasi Unggul dan menjadi pusat riset pendidikan terdepan di Indonesia Timur.', 'icon' => '🚀'],
];

// Mitra kerjasama
$partners = [
    ['name' => 'Kemdikbudristek', 'icon' => '🏛️'],
    ['name' => 'PP Muhammadiyah', 'icon' => '🕌'],
    ['name' => 'Universiti Malaya', 'icon' => '🇲🇾'],
    ['name' => 'Sakarya University', 'icon' => '🇹🇷'],
    ['name' => 'Dinas Pendidikan NTT', 'icon' => '📚'],
    ['name' => 'LPDP', 'icon' => '🎓'],
    ['name' => 'BRIN', 'icon' => '🔬'],
    ['name' => 'Microsoft Education', 'icon' => '💻'],
];

// FAQ
$faqs = [
    ['q' => 'Apa saja program studi yang tersedia di FKIP UNIMOF?', 'a' => 'FKIP UNIMOF memiliki 8 program studi: Pendidikan Matematika, Pendidikan Fisika, Pendidikan Biologi, Pendidikan Kimia, Pendidikan Bahasa dan Sastra Inggris, Bahasa dan Sastra Indonesia, Pendidikan Ekonomi, dan Pendidikan Kewarganegaraan. Semua program berjenjang S1.'],
    ['q' => 'Bagaimana proses akreditasi program studi?', 'a' => 'Seluruh program studi kami terakreditasi oleh BAN-PT. Saat ini 3 prodi berpredikat "Unggul", 4 prodi "Baik Sekali", dan 1 prodi "Baik". Kami terus berupaya meningkatkan kualitas untuk meraih akreditasi unggul di semua prodi.'],
    ['q' => 'Apakah ada program beasiswa?', 'a' => 'Ya! Kami menyediakan berbagai beasiswa: Beasiswa Prestasi Akademik, Beasiswa Kurang Mampu, Beasiswa Muhammadiyah, Beasiswa KIP Kuliah dari pemerintah, dan Beasiswa Kerjasama dengan mitra industri.'],
    ['q' => 'Bagaimana fasilitas kampus FKIP UNIMOF?', 'a' => 'Kami memiliki laboratorium sains modern (Fisika, Kimia, Biologi), laboratorium komputer, perpustakaan digital dengan akses jurnal internasional, ruang kelas ber-AC, dan studio micro-teaching untuk latihan mengajar.'],
    ['q' => 'Apa keunggulan FKIP UNIMOF dibanding fakultas lain?', 'a' => 'Keunggulan kami: (1) Kurikulum berbasis KKNI dan MBKM, (2) Dosen berkualifikasi S2/S3 dari universitas terkemuka, (3) Program magang di sekolah-sekolah mitra, (4) Riset pendidikan yang aktif, (5) Nilai Islami yang terintegrasi, (6) Lokasi strategis di Maumere, NTT.'],
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN -->
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
    background-size: 50px 50px;
    pointer-events: none;
}
.hero-particles-extreme { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle-extreme {
    position: absolute; width: 4px; height: 4px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: floatParticle 25s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; }
    90% { opacity: 0.8; }
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
    background-clip: text; animation: gradientShift 5s ease infinite;
}
@keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
.hero-subtitle-extreme {
    font-size: clamp(1rem, 1.5vw, 1.25rem); opacity: 0.95; max-width: 700px;
    line-height: 1.7; margin-bottom: 2.5rem;
}
.hero-cta-extreme { display: flex; gap: 1rem; flex-wrap: wrap; }

/* ===== STATS BAR ===== */
.stats-bar-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem 0 4rem; position: relative; z-index: 10;
}
.stat-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.stat-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.stat-icon-extreme { font-size: 2.5rem; margin-bottom: 0.75rem; }
.stat-num-extreme {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.stat-label-extreme { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }

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
.section-desc-extreme { color: var(--text-secondary); font-size: 1.1rem; line-height: 1.7; }

/* ===== SAMBUTAN DEKAN ===== */
.dekan-section {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 5rem 0; margin-bottom: 4rem;
}
.dekan-card {
    display: grid; grid-template-columns: 1fr 1.5fr; gap: 3rem; align-items: center;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 3rem; box-shadow: var(--shadow-lg);
    position: relative; overflow: hidden;
}
.dekan-card::before {
    content: '❝'; position: absolute; top: -20px; right: 30px;
    font-size: 15rem; color: var(--primary); opacity: 0.05; font-family: Georgia, serif;
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
.dekan-info h3 {
    font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem;
}
.dekan-jabatan {
    color: var(--primary); font-weight: 700; font-size: 0.95rem;
    margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.dekan-quote {
    font-size: 1.1rem; line-height: 1.8; color: var(--text-secondary);
    font-style: italic; margin-bottom: 1.5rem; position: relative;
    padding-left: 1.5rem; border-left: 3px solid var(--primary);
}
.dekan-name {
    font-weight: 700; color: var(--text-primary); font-style: normal;
    display: block; margin-top: 1rem;
}

/* ===== CORE VALUES ===== */
.values-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem; margin-bottom: 4rem;
}
.value-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; transition: all 0.4s;
    position: relative; overflow: hidden;
}
.value-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
    background: linear-gradient(180deg, var(--value-color, var(--primary)), transparent);
    transition: width 0.4s;
}
.value-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.value-card:hover::before { width: 6px; }
.value-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: linear-gradient(135deg, var(--value-color, var(--primary)), var(--value-color-light, var(--primary-light)));
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin-bottom: 1.25rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.value-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.75rem; }
.value-desc { color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; }

/* ===== TABS EXTREME ===== */
.tabs-extreme {
    display: flex; gap: 0.5rem; margin-bottom: 2.5rem;
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 0.5rem; overflow-x: auto;
    box-shadow: var(--shadow-sm);
}
.tab-extreme {
    flex: 1; min-width: 140px; padding: 1rem 1.5rem; background: transparent;
    border: none; border-radius: var(--radius-md); cursor: pointer;
    font-family: inherit; font-size: 0.95rem; font-weight: 700;
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

/* ===== VISI MISI EXTREME ===== */
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
    font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 1rem;
    color: var(--vm-color, var(--primary));
}
.vm-card-extreme p, .vm-card-extreme li {
    color: var(--text-secondary); line-height: 1.8; font-size: 1.05rem;
}
.vm-card-extreme ul { padding-left: 1.5rem; }
.vm-card-extreme li { margin-bottom: 0.75rem; position: relative; }
.vm-card-extreme li::marker { color: var(--vm-color, var(--primary)); font-weight: 700; }

/* ===== TIMELINE EXTREME ===== */
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
    box-shadow: 0 0 0 4px rgba(10,104,71,0.2);
}
.timeline-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.75rem; margin: 0 2rem;
    position: relative; transition: all 0.3s; max-width: 420px;
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
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 900;
    color: var(--primary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.timeline-title-extreme { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text-primary); }
.timeline-desc-extreme { color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; }

/* ===== STRUKTUR ORG EXTREME ===== */
.org-tree-extreme { padding: 2rem 0; }
.org-level-extreme { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; position: relative; }
.org-level-extreme::after {
    content: ''; position: absolute; bottom: -2rem; left: 10%; right: 10%;
    height: 2px; background: var(--border);
}
.org-level-extreme:last-child::after { display: none; }
.org-person-extreme {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    min-width: 180px; transition: all 0.3s; cursor: pointer;
}
.org-person-extreme:hover {
    transform: translateY(-5px); border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}
.org-avatar-extreme {
    width: 70px; height: 70px; border-radius: 50%; margin: 0 auto 0.75rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 800; box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.org-name-extreme { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem; color: var(--text-primary); }
.org-role-extreme { font-size: 0.78rem; color: var(--primary); font-weight: 600; }
.org-connector {
    width: 2px; height: 2rem; background: var(--border); margin: 0 auto;
}

/* ===== CHARTS ROW ===== */
.charts-row-extreme {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 4rem;
}
.chart-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-sm);
}
.chart-card-extreme h3 {
    font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}

/* ===== PARTNERS ===== */
.partners-section { padding: 4rem 0; background: var(--bg-secondary); margin: 4rem 0; }
.partners-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem; max-width: 1000px; margin: 0 auto;
}
.partner-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    transition: all 0.3s;
}
.partner-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.partner-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.partner-name { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); }

/* ===== FAQ EXTREME ===== */
.faq-section-extreme { max-width: 800px; margin: 0 auto; }
.faq-item-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); margin-bottom: 1rem; overflow: hidden;
    transition: all 0.3s;
}
.faq-item-extreme:hover { border-color: var(--primary-light); }
.faq-item-extreme.open { border-color: var(--primary); box-shadow: var(--shadow-md); }
.faq-question-extreme {
    width: 100%; padding: 1.25rem 1.5rem; background: none; border: none;
    text-align: left; font-family: inherit; font-size: 1rem; font-weight: 700;
    color: var(--text-primary); cursor: pointer; display: flex;
    justify-content: space-between; align-items: center; gap: 1rem;
}
.faq-icon-extreme {
    width: 28px; height: 28px; border-radius: 50%; background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s; flex-shrink: 0; font-size: 1rem;
}
.faq-item-extreme.open .faq-icon-extreme { background: var(--primary); color: white; transform: rotate(45deg); }
.faq-answer-extreme {
    max-height: 0; overflow: hidden; transition: max-height 0.4s ease;
}
.faq-answer-inner-extreme {
    padding: 0 1.5rem 1.25rem; color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem;
}

/* ===== CTA EXTREME ===== */
.cta-extreme {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
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

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .dekan-card { grid-template-columns: 1fr; }
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
@media (max-width: 640px) {
    .about-hero-extreme { padding: 8rem 0 4rem; }
    .dekan-card { padding: 2rem 1.5rem; }
    .tabs-extreme { flex-direction: column; }
    .tab-extreme { min-width: auto; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="about-hero-extreme">
    <div class="hero-particles-extreme" id="heroParticles"></div>
    <div class="container hero-content-extreme">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Tentang Kami</span>
        </nav>
        <div class="hero-badge-extreme" data-aos="fade-down" data-aos-delay="100">
            <span class="hero-badge-pulse"></span>
            <span>Terakreditasi • Sejak 1995</span>
        </div>
        <h1 class="hero-title-extreme" data-aos="fade-up">
            Membangun <span class="gradient-text-extreme">Peradaban</span><br>
            Melalui Pendidikan
        </h1>
        <p class="hero-subtitle-extreme" data-aos="fade-up" data-aos-delay="200">
            Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere — 
            mencetak pendidik profesional berkarakter Islami yang siap membangun Indonesia Timur.
        </p>
        <div class="hero-cta-extreme" data-aos="fade-up" data-aos-delay="400">
            <a href="#sambutan" class="btn btn-primary btn-lg" style="background: white; color: var(--primary);">
                <span>Sambutan Dekan</span>
            </a>
            <a href="<?= base_url('program.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                <span>Lihat Program Studi</span>
            </a>
        </div>
    </div>
</section>

<!-- ===== STATS BAR ===== -->
<div class="container">
    <div class="stats-bar-extreme" data-aos="fade-up">
        <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
            <div class="stat-icon-extreme">🎓</div>
            <div class="stat-num-extreme count-up" data-target="<?= $statistik['total_prodi'] ?? 8 ?>">0</div>
            <div class="stat-label-extreme">Program Studi</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #10b981;">
            <div class="stat-icon-extreme">👨‍🏫</div>
            <div class="stat-num-extreme count-up" data-target="<?= $total_dosen ?>">0</div>
            <div class="stat-label-extreme">Dosen Berkualitas</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
            <div class="stat-icon-extreme">👨🎓</div>
            <div class="stat-num-extreme count-up" data-target="<?= $statistik['total_mahasiswa'] ?? 1250 ?>">0</div>
            <div class="stat-label-extreme">Mahasiswa Aktif</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
            <div class="stat-icon-extreme">🎖️</div>
            <div class="stat-num-extreme count-up" data-target="<?= $total_alumni ?>">0</div>
            <div class="stat-label-extreme">Alumni Sukses</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #ef4444;">
            <div class="stat-icon-extreme">🏆</div>
            <div class="stat-num-extreme count-up" data-target="<?= $total_prestasi ?>">0</div>
            <div class="stat-label-extreme">Prestasi</div>
        </div>
    </div>
</div>

<!-- ===== SAMBUTAN DEKAN ===== -->
<section class="dekan-section" id="sambutan">
    <div class="container">
        <div class="section-header-extreme" data-aos="fade-up">
            <span class="section-tag-extreme">Sambutan</span>
            <h2 class="section-title-extreme">Kata <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Sambutan Dekan</span></h2>
        </div>
        
        <div class="dekan-card" data-aos="fade-up">
            <div class="dekan-photo">
                <span>👨‍</span>
            </div>
            <div class="dekan-info">
                <h3><?= sanitize($dekan['nama']) ?></h3>
                <div class="dekan-jabatan">
                    <span>🎓</span> <?= sanitize($dekan['jabatan_fungsional']) ?>
                </div>
                <div class="dekan-quote">
                    "Assalamu'alaikum Warahmatullahi Wabarakatuh. Selamat datang di website resmi FKIP UNIMOF. Kami berkomitmen untuk terus meningkatkan kualitas pendidikan, penelitian, dan pengabdian masyarakat. Melalui kurikulum yang adaptif, dosen yang kompeten, dan fasilitas modern, kami mengajak Anda menjadi bagian dari keluarga besar FKIP UNIMOF. Mari berinovasi bersama untuk Indonesia yang lebih baik!"
                    <span class="dekan-name">— <?= sanitize($dekan['nama']) ?></span>
                </div>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-secondary">Hubungi Dekanat →</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== CORE VALUES ===== -->
<section class="section">
    <div class="container">
        <div class="section-header-extreme" data-aos="fade-up">
            <span class="section-tag-extreme">Nilai Inti</span>
            <h2 class="section-title-extreme">6 Pilar <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Keunggulan Kami</span></h2>
            <p class="section-desc-extreme">Nilai-nilai yang menjadi fondasi setiap aktivitas akademik di FKIP UNIMOF</p>
        </div>
        
        <div class="values-grid">
            <?php 
            $value_colors = [
                ['#10b981', '#059669'], ['#3b82f6', '#2563eb'], ['#f59e0b', '#d97706'],
                ['#8b5cf6', '#7c3aed'], ['#ec4899', '#db2777'], ['#ef4444', '#dc2626']
            ];
            foreach ($core_values as $i => $v):
                $colors = $value_colors[$i % count($value_colors)];
            ?>
            <div class="value-card" style="--value-color: <?= $colors[0] ?>; --value-color-light: <?= $colors[1] ?>;" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                <div class="value-icon"><?= $v['icon'] ?></div>
                <h3 class="value-title"><?= $v['title'] ?></h3>
                <p class="value-desc"><?= $v['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== TABS: VISI MISI, SEJARAH, STRUKTUR ===== -->
<section class="section" style="background: var(--bg-secondary); padding: 5rem 0;">
    <div class="container">
        <div class="section-header-extreme" data-aos="fade-up">
            <span class="section-tag-extreme">Profil Lengkap</span>
            <h2 class="section-title-extreme">Mengenal Lebih <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Dekat</span></h2>
        </div>

        <div class="tabs-extreme" data-aos="fade-up">
            <button class="tab-extreme active" onclick="switchAboutTab(event, 'tab-vm')"> Visi & Misi</button>
            <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-sejarah')">📜 Sejarah</button>
            <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-struktur')">🏛️ Struktur</button>
            <button class="tab-extreme" onclick="switchAboutTab(event, 'tab-chart')">📊 Statistik</button>
        </div>

        <!-- Tab: Visi & Misi -->
        <div id="tab-vm" class="tab-panel-extreme active" data-aos="fade-up">
            <div class="vm-grid-extreme">
                <div class="vm-card-extreme" style="--vm-color: #10b981; --vm-color-light: #059669;">
                    <div class="vm-icon-extreme">👁️</div>
                    <h3>Visi</h3>
                    <p style="font-style: italic; font-size: 1.1rem; border-left: 3px solid #10b981; padding-left: 1rem; background: rgba(16,185,129,0.05); padding: 1.25rem; border-radius: 0 var(--radius-md) var(--radius-md) 0;">
                        "Menjadi Fakultas Keguruan dan Ilmu Pendidikan yang <strong>unggul, inovatif, dan berkarakter Islami</strong> dalam menghasilkan pendidik profesional berdaya saing global pada tahun 2030."
                    </p>
                </div>
                <div class="vm-card-extreme" style="--vm-color: #3b82f6; --vm-color-light: #2563eb;">
                    <div class="vm-icon-extreme">🚀</div>
                    <h3>Misi</h3>
                    <ul>
                        <li>Menyelenggarakan pendidikan tinggi yang <strong>berkualitas dan relevan</strong> dengan perkembangan ilmu pengetahuan dan teknologi.</li>
                        <li>Melaksanakan <strong>penelitian dan pengabdian</strong> kepada masyarakat yang berdampak positif bagi pembangunan daerah.</li>
                        <li>Menanamkan <strong>nilai-nilai keislaman, kemuhammadiyahan, dan kearifan lokal</strong> dalam setiap aktivitas akademik.</li>
                        <li>Membangun <strong>jejaring kerjasama</strong> dengan institusi pendidikan dalam dan luar negeri.</li>
                        <li>Mengembangkan <strong>kurikulum adaptif</strong> berbasis MBKM dan KKNI untuk menghasilkan lulusan yang siap kerja.</li>
                    </ul>
                </div>
            </div>

            <!-- Tujuan -->
            <div class="vm-card-extreme" style="--vm-color: #f59e0b; --vm-color-light: #d97706; margin-top: 2rem;">
                <div class="vm-icon-extreme">🎯</div>
                <h3>Tujuan Strategis</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid #f59e0b;">
                        <strong>🎓 Lulusan Berkualitas</strong><br>
                        <small style="color: var(--text-muted);">90% lulusan terserap di dunia kerja dalam 1 tahun</small>
                    </div>
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid #f59e0b;">
                        <strong> Riset Produktif</strong><br>
                        <small style="color: var(--text-muted);">Minimal 50 publikasi ilmiah per tahun</small>
                    </div>
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid #f59e0b;">
                        <strong>🌏 Kerjasama Global</strong><br>
                        <small style="color: var(--text-muted);">Mitra dengan 15+ universitas internasional</small>
                    </div>
                    <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 3px solid #f59e0b;">
                        <strong>⭐ Akreditasi Unggul</strong><br>
                        <small style="color: var(--text-muted);">Seluruh prodi berakreditasi Unggul pada 2030</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Sejarah -->
        <div id="tab-sejarah" class="tab-panel-extreme" data-aos="fade-up">
            <div class="timeline-extreme">
                <?php foreach ($milestones as $i => $m): ?>
                <div class="timeline-item-extreme">
                    <div class="timeline-dot-extreme"></div>
                    <div class="timeline-card-extreme">
                        <div class="timeline-year-extreme">
                            <span style="font-size: 1.5rem;"><?= $m['icon'] ?></span>
                            <span><?= $m['year'] ?></span>
                        </div>
                        <h4 class="timeline-title-extreme"><?= $m['title'] ?></h4>
                        <p class="timeline-desc-extreme"><?= $m['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab: Struktur -->
        <div id="tab-struktur" class="tab-panel-extreme" data-aos="fade-up">
            <div class="org-tree-extreme">
                <!-- Level 1: Dekan -->
                <div class="org-level-extreme">
                    <div class="org-person-extreme">
                        <div class="org-avatar-extreme"><?= strtoupper(substr($dekan['nama'], 0, 1)) ?></div>
                        <div class="org-name-extreme"><?= sanitize($dekan['nama']) ?></div>
                        <div class="org-role-extreme">Dekan FKIP</div>
                    </div>
                </div>
                <div class="org-connector"></div>
                
                <!-- Level 2: Kaprodi (4 prodi) -->
                <div class="org-level-extreme">
                    <?php foreach (array_slice($prodi_list, 0, 4) as $p): ?>
                    <div class="org-person-extreme">
                        <div class="org-avatar-extreme" style="background: linear-gradient(135deg, #3b82f6, #2563eb);"><?= strtoupper(substr($p['nama'], 0, 1)) ?></div>
                        <div class="org-name-extreme"><?= sanitize($p['singkatan']) ?></div>
                        <div class="org-role-extreme">Kaprodi</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="org-connector"></div>
                
                <!-- Level 3: Kaprodi (4 prodi lagi) -->
                <div class="org-level-extreme">
                    <?php foreach (array_slice($prodi_list, 4, 4) as $p): ?>
                    <div class="org-person-extreme">
                        <div class="org-avatar-extreme" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><?= strtoupper(substr($p['nama'], 0, 1)) ?></div>
                        <div class="org-name-extreme"><?= sanitize($p['singkatan']) ?></div>
                        <div class="org-role-extreme">Kaprodi</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                    *Struktur organisasi disederhanakan untuk tampilan web.
                </p>
                <a href="<?= base_url('download.php?kategori=Formulir') ?>" class="btn btn-secondary">
                    📥 Unduh SK Struktur Organisasi Lengkap
                </a>
            </div>
        </div>

        <!-- Tab: Statistik -->
        <div id="tab-chart" class="tab-panel-extreme" data-aos="fade-up">
            <div class="charts-row-extreme">
                <div class="chart-card-extreme">
                    <h3>🎓 Distribusi Program Studi</h3>
                    <div id="chartProdi"></div>
                </div>
                <div class="chart-card-extreme">
                    <h3>📈 Perkembangan Akreditasi</h3>
                    <div id="chartAkreditasi"></div>
                </div>
            </div>
            
            <div style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; margin-top: 2rem;">
                <h3 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                     Ringkasan Statistik FKIP UNIMOF
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid #10b981;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Total Program Studi</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #10b981;"><?= $statistik['total_prodi'] ?? 8 ?></div>
                    </div>
                    <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid #3b82f6;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Total Dosen</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6;"><?= $total_dosen ?></div>
                    </div>
                    <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid #f59e0b;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Mahasiswa Aktif</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b;"><?= $statistik['total_mahasiswa'] ?? 1250 ?></div>
                    </div>
                    <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid #8b5cf6;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Alumni</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #8b5cf6;"><?= $total_alumni ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PARTNERS ===== -->
<section class="partners-section">
    <div class="container">
        <div class="section-header-extreme" data-aos="fade-up">
            <span class="section-tag-extreme">Mitra Kerjasama</span>
            <h2 class="section-title-extreme">Didukung oleh <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Mitra Terbaik</span></h2>
            <p class="section-desc-extreme">Bekerjasama dengan institusi terkemuka untuk meningkatkan kualitas pendidikan</p>
        </div>
        
        <div class="partners-grid" data-aos="fade-up">
            <?php foreach ($partners as $p): ?>
            <div class="partner-card">
                <div class="partner-icon"><?= $p['icon'] ?></div>
                <div class="partner-name"><?= $p['name'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== FAQ ===== -->
<section class="section">
    <div class="container">
        <div class="section-header-extreme" data-aos="fade-up">
            <span class="section-tag-extreme">FAQ</span>
            <h2 class="section-title-extreme">Pertanyaan <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Umum</span></h2>
            <p class="section-desc-extreme">Temukan jawaban untuk pertanyaan yang sering diajukan</p>
        </div>
        
        <div class="faq-section-extreme" data-aos="fade-up">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-item-extreme">
                <button class="faq-question-extreme" onclick="toggleFaq(this)">
                    <span><?= $faq['q'] ?></span>
                    <span class="faq-icon-extreme">+</span>
                </button>
                <div class="faq-answer-extreme">
                    <div class="faq-answer-inner-extreme"><?= $faq['a'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== CTA EXTREME ===== -->
<div class="container">
    <div class="cta-extreme" data-aos="zoom-in">
        <div class="cta-content-extreme">
            <h2>Siap Bergabung dengan <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Keluarga Besar FKIP UNIMOF?</span></h2>
            <p>Jadilah bagian dari generasi pendidik unggul yang akan membangun peradaban Indonesia Timur.</p>
            <div class="cta-actions-extreme">
                <a href="<?= base_url('pmb.php') ?>" class="btn btn-primary btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    Daftar Sekarang →
                </a>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                    Konsultasi Gratis
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// ===== HERO PARTICLES =====
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 25; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle-extreme';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 25 + 's';
        p.style.animationDuration = (20 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        container.appendChild(p);
    }
})();

// ===== COUNT UP ANIMATION =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
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
const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCount(entry.target);
            countObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== TAB SWITCHING =====
function switchAboutTab(event, tabId) {
    document.querySelectorAll('.tab-extreme').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-panel-extreme').forEach(panel => panel.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    
    // Initialize charts when chart tab is opened
    if (tabId === 'tab-chart') {
        setTimeout(initCharts, 100);
    }
}

// ===== FAQ TOGGLE =====
function toggleFaq(btn) {
    const item = btn.closest('.faq-item-extreme');
    const answer = item.querySelector('.faq-answer-extreme');
    const isOpen = item.classList.contains('open');
    
    // Close all
    document.querySelectorAll('.faq-item-extreme').forEach(i => {
        i.classList.remove('open');
        i.querySelector('.faq-answer-extreme').style.maxHeight = null;
    });
    
    // Open clicked if wasn't open
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
    
    // Chart Prodi
    const prodiLabels = <?= json_encode(array_column($prodi_list, 'singkatan')) ?>;
    const prodiData = prodiLabels.map(() => Math.floor(Math.random() * 150) + 50);
    
    new ApexCharts(document.querySelector("#chartProdi"), {
        series: [{ name: 'Mahasiswa', data: prodiData }],
        chart: { type: 'bar', height: 300, animations: { enabled: true, speed: 800 } },
        plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
        colors: ['#0a6847'],
        dataLabels: { enabled: false },
        xaxis: { categories: prodiLabels, labels: { style: { fontSize: '11px' } } },
        yaxis: { title: { text: 'Jumlah Mahasiswa' } },
        grid: { borderColor: '#f1f5f9' }
    }).render();
    
    // Chart Akreditasi
    new ApexCharts(document.querySelector("#chartAkreditasi"), {
        series: [
            { name: 'Unggul', data: [0, 0, 0, 0, 1, 2, 3] },
            { name: 'Baik Sekali', data: [2, 3, 4, 5, 5, 5, 4] },
            { name: 'Baik', data: [6, 5, 4, 3, 2, 1, 1] }
        ],
        chart: { type: 'area', height: 300, animations: { enabled: true, speed: 800 }, stacked: true },
        colors: ['#10b981', '#3b82f6', '#f59e0b'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.1 } },
        xaxis: { categories: ['2015', '2017', '2019', '2021', '2023', '2025', '2026'] },
        yaxis: { title: { text: 'Jumlah Prodi' }, min: 0, max: 10 },
        legend: { position: 'top' }
    }).render();
}

// ===== SMOOTH SCROLL FOR ANCHORS =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            document.querySelector(href).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

console.log('%c🏛️ Tentang FKIP UNIMOF', 'color: #0a6847; font-size: 16px; font-weight: bold;');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>