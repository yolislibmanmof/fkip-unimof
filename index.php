<?php
// index.php - Halaman Beranda FKIP UNIMOF (ULTIMATE VERSION)
require_once __DIR__ . '/includes/config.php';

$page_title = 'Beranda';
$page_description = 'Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere - Mencetak pendidik profesional berkarakter Islam untuk Indonesia Timur';

$statistik = get_statistik();
$prodi = get_prodi();
$berita_terbaru = get_featured_berita(3);
$agenda = get_agenda_mendatang(4);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES FOR HOMEPAGE ===== -->
<style>
/* Hero Enhancement */
.hero {
    position: relative; min-height: 100vh; display: flex; align-items: center;
    padding: 8rem 0 6rem; overflow: hidden; background: var(--bg-primary);
}
.hero-bg {
    position: absolute; inset: 0; z-index: 0;
    background: 
        radial-gradient(circle at 15% 50%, rgba(10,104,71,0.15) 0%, transparent 50%),
        radial-gradient(circle at 85% 30%, rgba(245,166,35,0.12) 0%, transparent 50%),
        radial-gradient(circle at 50% 80%, rgba(59,130,246,0.1) 0%, transparent 50%);
    animation: heroMesh 20s ease-in-out infinite;
}
@keyframes heroMesh {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.hero-particles { position: absolute; inset: 0; pointer-events: none; }
.particle {
    position: absolute; width: 4px; height: 4px; background: var(--primary);
    border-radius: 50%; opacity: 0.3; animation: floatParticle 25s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.5; }
    90% { opacity: 0.5; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}

.hero-content { position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; }
.hero-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(10,104,71,0.1); border: 1px solid rgba(10,104,71,0.2);
    color: var(--primary); padding: 0.5rem 1.25rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 700; margin-bottom: 2rem;
}
.pulse-dot {
    width: 8px; height: 8px; background: var(--primary); border-radius: 50%;
    position: relative;
}
.pulse-dot::after {
    content: ''; position: absolute; inset: 0; background: var(--primary);
    border-radius: 50%; animation: pulse 2s infinite;
}
@keyframes pulse { to { transform: scale(3); opacity: 0; } }

.hero-title {
    font-family: var(--font-display); font-size: clamp(2.5rem, 6vw, 4.5rem);
    font-weight: 900; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -0.02em;
}
.title-line { display: block; }
.title-line.highlight .gradient-text {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent-light) 100%);
    background-size: 200% 200%; -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; animation: gradientShift 5s ease infinite;
}
@keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

.hero-subtitle {
    font-size: clamp(1rem, 1.5vw, 1.25rem); color: var(--text-secondary);
    max-width: 700px; margin: 0 auto 2.5rem; line-height: 1.7;
}
.hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 4rem; }

.hero-scroll {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    color: var(--text-muted); font-size: 0.85rem; margin-top: 2rem;
}
.scroll-indicator {
    width: 24px; height: 40px; border: 2px solid var(--text-muted);
    border-radius: 12px; position: relative;
}
.scroll-dot {
    width: 4px; height: 4px; background: var(--text-muted); border-radius: 50%;
    position: absolute; top: 6px; left: 50%; transform: translateX(-50%);
    animation: scrollDown 2s infinite;
}
@keyframes scrollDown { 0% { top: 6px; opacity: 1; } 100% { top: 26px; opacity: 0; } }

.hero-meta {
    position: relative; z-index: 2; display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 2rem; max-width: 1000px; margin: 4rem auto 0; padding: 2rem;
    background: rgba(255,255,255,0.8); backdrop-filter: blur(10px);
    border: 1px solid var(--border); border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);
}
[data-theme="dark"] .hero-meta { background: rgba(15,23,42,0.8); }
.meta-item { text-align: center; }
.meta-number {
    display: block; font-family: var(--font-display); font-size: 2.5rem;
    font-weight: 900; color: var(--primary); line-height: 1;
}
.meta-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.5rem; }

/* About Section */
.section-about { background: var(--bg-secondary); }
.about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.about-content h3 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 1rem; }
.about-content > p { color: var(--text-secondary); margin-bottom: 2rem; line-height: 1.7; }

.features-list { display: grid; gap: 1.25rem; margin-bottom: 2rem; }
.feature-item {
    display: flex; gap: 1rem; align-items: flex-start; padding: 1.25rem;
    background: var(--bg-primary); border-radius: var(--radius-md);
    border: 1px solid var(--border); transition: all 0.3s;
}
.feature-item:hover { transform: translateX(8px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.feature-icon { font-size: 1.75rem; flex-shrink: 0; }
.feature-item h4 { font-size: 1rem; margin-bottom: 0.25rem; }
.feature-item p { font-size: 0.85rem; color: var(--text-secondary); margin: 0; }

.image-stack { position: relative; height: 500px; }
.img-main {
    position: absolute; width: 75%; height: 100%; border-radius: var(--radius-lg);
    overflow: hidden; box-shadow: var(--shadow-xl); z-index: 1;
}
.img-accent {
    position: absolute; width: 45%; height: 45%; border-radius: var(--radius-md);
    overflow: hidden; box-shadow: var(--shadow-lg); border: 4px solid var(--bg-primary); z-index: 2;
}
.img-1 { bottom: 5%; left: -5%; animation: floatY 6s ease-in-out infinite; }
.img-2 { top: 5%; right: -5%; animation: floatY 6s ease-in-out infinite 2s; }
@keyframes floatY { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

.img-placeholder {
    width: 100%; height: 100%; display: flex; flex-direction: column;
    align-items: center; justify-content: center; color: white; font-size: 3rem;
}
.img-placeholder p { font-size: 1rem; margin-top: 0.5rem; font-weight: 600; }

.stats-floating {
    position: absolute; bottom: -5%; right: 5%; z-index: 3;
    display: flex; gap: 0.75rem; background: var(--bg-primary);
    padding: 1rem 1.5rem; border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg); border: 1px solid var(--border);
}
.stats-floating .stat-item { text-align: center; padding: 0 1rem; border-right: 1px solid var(--border); }
.stats-floating .stat-item:last-child { border: 0; }
.stats-floating strong { display: block; font-size: 1.5rem; color: var(--primary); font-family: var(--font-display); }
.stats-floating span { font-size: 0.75rem; color: var(--text-muted); }

/* Programs Section */
.programs-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;
}
.program-card {
    position: relative; background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
}
.program-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--card-color-1, var(--primary)), var(--card-color-2, var(--primary-light)));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.program-card:hover {
    transform: translateY(-10px); box-shadow: var(--shadow-xl); border-color: var(--card-color-1, var(--primary));
}
.program-card:hover::before { transform: scaleX(1); }

.program-number {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--bg-tertiary); line-height: 1; margin-bottom: 1rem; transition: color 0.3s;
}
.program-card:hover .program-number {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.program-icon { font-size: 2.5rem; margin-bottom: 1rem; transition: transform 0.3s; }
.program-card:hover .program-icon { transform: scale(1.2) rotate(-10deg); }

.program-badge {
    display: inline-block; padding: 0.3rem 0.8rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    margin-bottom: 0.75rem; background: rgba(10,104,71,0.1); color: var(--primary);
}
.program-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 0.25rem; transition: color 0.3s; }
.program-card:hover .program-title { color: var(--card-color-1, var(--primary)); }
.program-level { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem; font-weight: 600; }

.program-link {
    display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary);
    font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: gap 0.3s;
}
.program-link:hover { gap: 0.8rem; }

/* Stats Section */
.section-stats {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    color: white; position: relative; overflow: hidden;
}
.section-stats::before {
    content: ''; position: absolute; inset: 0;
    background-image: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.1) 0%, transparent 40%),
                      radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 40%);
}
.stats-wrapper { display: grid; grid-template-columns: 1fr 1.5fr; gap: 4rem; align-items: center; position: relative; z-index: 2; }
.stats-info h2 { font-family: var(--font-display); font-size: clamp(2rem, 3.5vw, 2.75rem); margin-bottom: 1.5rem; color: white; }
.stats-info p { font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.9; }

.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
.stat-box {
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15); border-radius: var(--radius-lg);
    padding: 2rem; text-align: center; transition: all 0.3s;
}
.stat-box:hover { transform: translateY(-5px); background: rgba(255,255,255,0.15); }
.stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.stat-number {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    line-height: 1; margin-bottom: 0.5rem; color: var(--secondary);
}
.stat-label { font-size: 0.9rem; opacity: 0.9; font-weight: 600; }

/* News Section */
.news-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;
}
.news-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column;
}
.news-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary-light); }
.news-card.featured { grid-column: span 2; }
.news-card.featured .news-image { height: 280px; }
.news-card.featured .news-title { font-size: 1.5rem; }

.news-image { position: relative; height: 200px; overflow: hidden; }
.news-image .img-placeholder, .news-image img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.news-card:hover .news-image .img-placeholder,
.news-card:hover .news-image img { transform: scale(1.08); }

.news-category {
    position: absolute; top: 1rem; left: 1rem; background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px); color: var(--primary); padding: 0.35rem 0.85rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: var(--shadow-sm); z-index: 2;
}
.news-content { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
.news-meta { display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.75rem; }
.news-title { font-size: 1.15rem; font-weight: 700; line-height: 1.4; margin-bottom: 0.75rem; }
.news-title a {
    color: var(--text-primary); text-decoration: none;
    background-image: linear-gradient(var(--primary), var(--primary));
    background-size: 0% 2px; background-position: 0 100%; background-repeat: no-repeat;
    transition: background-size 0.3s, color 0.3s;
}
.news-title a:hover { color: var(--primary); background-size: 100% 2px; }
.news-excerpt {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.read-more {
    color: var(--primary); font-weight: 600; font-size: 0.9rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.4rem; transition: gap 0.3s;
}
.read-more:hover { gap: 0.8rem; }

/* Calendar Section */
.section-calendar { background: var(--bg-secondary); }
.calendar-wrapper {
    max-width: 900px; margin: 0 auto; background: var(--bg-primary);
    border-radius: var(--radius-xl); padding: 3rem;
    box-shadow: var(--shadow-lg); border: 1px solid var(--border);
}
.calendar-header { text-align: center; margin-bottom: 2.5rem; }
.calendar-header h2 { font-family: var(--font-display); font-size: 2rem; margin-bottom: 0.5rem; }
.calendar-list { display: flex; flex-direction: column; gap: 1rem; }
.calendar-item {
    display: grid; grid-template-columns: auto 1fr auto; gap: 1.5rem;
    align-items: center; padding: 1.25rem; background: var(--bg-secondary);
    border-radius: var(--radius-md); transition: all 0.3s; cursor: pointer;
}
.calendar-item:hover { background: var(--bg-tertiary); transform: translateX(5px); }
.calendar-date {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; padding: 0.75rem 1rem; border-radius: var(--radius-md);
    text-align: center; min-width: 70px;
}
.date-day { display: block; font-size: 1.5rem; font-weight: 900; line-height: 1; }
.date-month { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.25rem; display: block; }
.calendar-type {
    display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px;
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.25rem;
}
.calendar-type.ujian { background: #fee2e2; color: #dc2626; }
.calendar-type.seminar { background: #dbeafe; color: #2563eb; }
.calendar-type.wisuda { background: #fef3c7; color: #d97706; }
.calendar-info h4 { font-size: 1rem; font-weight: 700; }
.calendar-arrow { color: var(--primary); font-size: 1.25rem; transition: transform 0.3s; }
.calendar-item:hover .calendar-arrow { transform: translateX(5px); }

/* CTA Section */
.section-cta { padding: 4rem 0; }
.cta-wrapper {
    position: relative; background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: var(--radius-xl); padding: 4rem 3rem; overflow: hidden; color: white;
}
.cta-content { position: relative; z-index: 2; max-width: 700px; }
.cta-content h2 { font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem; color: white; }
.cta-content p { font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95; }
.cta-actions { display: flex; gap: 1rem; flex-wrap: wrap; }

.cta-decoration { position: absolute; top: 0; right: 0; width: 400px; height: 400px; pointer-events: none; }
.cta-orb {
    position: absolute; border-radius: 50%; background: rgba(255,255,255,0.1);
    filter: blur(20px); animation: orbFloat 10s ease-in-out infinite;
}
.orb-1 { width: 200px; height: 200px; top: 20%; right: 10%; }
.orb-2 { width: 150px; height: 150px; top: 60%; right: 20%; animation-delay: -3s; }
.orb-3 { width: 100px; height: 100px; top: 10%; right: 35%; animation-delay: -6s; }
@keyframes orbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -20px) scale(1.1); } }

/* Responsive */
@media (max-width: 968px) {
    .hero-meta { grid-template-columns: repeat(2, 1fr); }
    .about-grid, .stats-wrapper { grid-template-columns: 1fr; gap: 3rem; }
    .image-stack { height: 400px; }
    .news-card.featured { grid-column: span 1; }
    .calendar-item { grid-template-columns: auto 1fr; gap: 1rem; }
    .calendar-arrow { display: none; }
}
@media (max-width: 640px) {
    .hero-meta { grid-template-columns: 1fr; gap: 1.5rem; }
    .stats-grid { grid-template-columns: 1fr; }
    .cta-wrapper { padding: 3rem 1.5rem; }
}
</style>

<!-- ===== HERO SECTION ===== -->
<section class="hero" id="hero">
    <div class="hero-bg">
        <div class="hero-particles" id="particles"></div>
    </div>
    
    <div class="container hero-content">
        <div class="hero-badge" data-aos="fade-down">
            <span class="pulse-dot"></span>
            Penerimaan Mahasiswa Baru 2026/2027 Dibuka
        </div>
        
        <h1 class="hero-title" data-aos="fade-up">
            <span class="title-line">Menyalakan</span>
            <span class="title-line highlight">
                <span class="gradient-text">Terang Pendidikan</span>
            </span>
            <span class="title-line">Menghadirkan Harapan</span>
        </h1>
        
        <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">
            Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere
            membentuk generasi pendidik yang cerdas, berkarakter, dan inovatif untuk Indonesia Timur.
        </p>
        
        <div class="hero-actions" data-aos="fade-up" data-aos-delay="400">
            <a href="<?= base_url('program.php') ?>" class="btn btn-primary btn-lg">
                <span>Jelajahi Program Studi</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-lg">
                <span>Hubungi Kami</span>
            </a>
        </div>
        
        <div class="hero-scroll" data-aos="fade-up" data-aos-delay="600">
            <span>Scroll untuk menjelajahi</span>
            <div class="scroll-indicator">
                <div class="scroll-dot"></div>
            </div>
        </div>
    </div>
    
    <div class="hero-meta" data-aos="fade-up" data-aos-delay="800">
        <div class="meta-item">
            <span class="meta-number" data-count="<?= $statistik['total_mahasiswa'] ?? 1250 ?>">0</span>
            <span class="meta-label">Mahasiswa Aktif</span>
        </div>
        <div class="meta-item">
            <span class="meta-number" data-count="<?= $statistik['total_prodi'] ?? 8 ?>">0</span>
            <span class="meta-label">Program Studi</span>
        </div>
        <div class="meta-item">
            <span class="meta-number" data-count="<?= $statistik['total_dosen'] ?? 68 ?>">0</span>
            <span class="meta-label">Dosen Berkualitas</span>
        </div>
        <div class="meta-item">
            <span class="meta-number" data-count="<?= $statistik['total_alumni'] ?? 3200 ?>">0</span>
            <span class="meta-label">Alumni Sukses</span>
        </div>
    </div>
</section>

<!-- ===== ABOUT SECTION ===== -->
<section class="section section-about" id="about">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Tentang Kami</span>
            <h2 class="section-title">Rumah bagi <span class="gradient-text">calon pendidik</span> masa depan</h2>
        </div>
        
        <div class="about-grid">
            <div class="about-content" data-aos="fade-right">
                <h3>FKIP UNIMOF - Fakultas Keguruan dan Ilmu Pendidikan</h3>
                <p>Sebagai fakultas unggulan di Universitas Muhammadiyah Maumere, kami berkomitmen mencetak lulusan yang tidak hanya kompeten dalam bidang akademik, tetapi juga memiliki karakter Islami dan siap menghadapi tantangan pendidikan modern.</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon">🎓</div>
                        <div>
                            <h4>Kurikulum Modern</h4>
                            <p>Disusun mengikuti standar KKNI dan kebutuhan industri pendidikan 4.0</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🌏</div>
                        <div>
                            <h4>Jaringan Global</h4>
                            <p>Kerjasama dengan universitas dan lembaga pendidikan di ASEAN</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">💼</div>
                        <div>
                            <h4>Siap Kerja</h4>
                            <p>Program magang, sertifikasi, dan career center terintegrasi</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔬</div>
                        <div>
                            <h4>Research-Driven</h4>
                            <p>Pusat riset pendidikan dengan jurnal ilmiah bereputasi</p>
                        </div>
                    </div>
                </div>
                
                <a href="<?= base_url('about.php') ?>" class="btn btn-secondary">Pelajari Lebih Lanjut →</a>
            </div>
            
            <div class="about-visual" data-aos="fade-left">
                <div class="image-stack">
                    <div class="img-main">
                        <div class="img-placeholder" style="background: linear-gradient(135deg, #0a6847 0%, #16a34a 100%);">
                            <span>🏛️</span>
                            <p>Kampus FKIP</p>
                        </div>
                    </div>
                    <div class="img-accent img-1">
                        <div class="img-placeholder" style="background: linear-gradient(135deg, #f5a623 0%, #fbbf24 100%);">
                            <span>📚</span>
                            <p>Perpustakaan</p>
                        </div>
                    </div>
                    <div class="img-accent img-2">
                        <div class="img-placeholder" style="background: linear-gradient(135deg, #16213e 0%, #3b82f6 100%);">
                            <span>🧪</span>
                            <p>Laboratorium</p>
                        </div>
                    </div>
                    <div class="stats-floating">
                        <div class="stat-item">
                            <strong>A</strong>
                            <span>Akreditasi Unggul</span>
                        </div>
                        <div class="stat-item">
                            <strong>25+</strong>
                            <span>Tahun Berdiri</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PROGRAMS SECTION ===== -->
<section class="section section-programs" id="programs">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="section-tag">Program Studi</span>
            <h2 class="section-title">Pilih jalur <span class="gradient-text">pendidikan</span> terbaikmu</h2>
            <p class="section-desc">Kurikulum kami dirancang mengikuti standar nasional dan internasional, dibimbing oleh dosen berkualifikasi tinggi.</p>
        </div>
        
        <div class="programs-grid">
            <?php 
            $prodi_colors = [
                ['#3b82f6', '#1d4ed8'], ['#8b5cf6', '#6d28d9'], ['#10b981', '#059669'], ['#f59e0b', '#d97706'],
                ['#ec4899', '#db2777'], ['#ef4444', '#dc2626'], ['#14b8a6', '#0d9488'], ['#f97316', '#ea580c']
            ];
            $icons = ['📐', '⚛️', '🧬', '🧪', '🌐', '📖', '💰', '⚖️'];
            
            foreach ($prodi as $index => $p): 
                $colors = $prodi_colors[$index % count($prodi_colors)];
            ?>
            <article class="program-card" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>"
                style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;">
                <div class="program-number"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="program-icon"><?= $icons[$index] ?? '🎓' ?></div>
                <div class="program-content">
                    <div class="program-badge"><?= sanitize($p['akreditasi']) ?></div>
                    <h3 class="program-title"><?= sanitize($p['nama']) ?></h3>
                    <p class="program-level"><?= sanitize($p['jenjang']) ?> • <?= sanitize($p['singkatan']) ?></p>
                    <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" class="program-link">
                        Detail Program
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== STATS SECTION ===== -->
<section class="section section-stats">
    <div class="container">
        <div class="stats-wrapper">
            <div class="stats-info" data-aos="fade-right">
                <h2>Angka yang <span class="gradient-text" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">berbicara</span></h2>
                <p>Setiap tahun, FKIP UNIMOF konsisten melahirkan lulusan yang siap mengabdi, berinovasi, dan menginspirasi masyarakat Indonesia Timur.</p>
                <a href="<?= base_url('prestasi.php') ?>" class="btn btn-outline btn-light">Lihat Prestasi Kami →</a>
            </div>
            
            <div class="stats-grid" data-aos="fade-left">
                <div class="stat-box">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-number" data-count="1250">0</div>
                    <div class="stat-label">Mahasiswa Aktif</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-number" data-count="68">0</div>
                    <div class="stat-label">Dosen Berkualitas</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number" data-count="8">0</div>
                    <div class="stat-label">Program Studi</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">🔬</div>
                    <div class="stat-number" data-count="45">0</div>
                    <div class="stat-label">Penelitian Aktif</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== NEWS SECTION ===== -->
<section class="section section-news" id="berita">
    <div class="container">
        <div class="section-header section-header-split" data-aos="fade-up">
            <div>
                <span class="section-tag">Berita Terkini</span>
                <h2 class="section-title">Kabar dari <span class="gradient-text">FKIP</span></h2>
            </div>
            <a href="<?= base_url('berita.php') ?>" class="btn btn-secondary">Lihat Semua Berita →</a>
        </div>
        
        <?php if (empty($berita_terbaru)): ?>
            <div class="empty-state" style="text-align:center; padding:3rem; background:var(--bg-secondary); border-radius:var(--radius-xl);">
                <p style="font-size:1.2rem;">📭 Belum ada berita yang dipublikasikan.</p>
            </div>
        <?php else: ?>
            <div class="news-grid">
                <?php foreach ($berita_terbaru as $index => $berita): ?>
                <article class="news-card <?= $index === 0 ? 'featured' : '' ?>" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                    <div class="news-image">
                        <?php if (!empty($berita['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($berita['gambar'])) ?>" alt="<?= sanitize($berita['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <span>📰</span>
                            </div>
                        <?php endif; ?>
                        <span class="news-category"><?= sanitize($berita['kategori']) ?></span>
                    </div>
                    <div class="news-content">
                        <div class="news-meta">
                            <span>📅 <?= format_tanggal_singkat($berita['published_at'] ?? $berita['created_at']) ?></span>
                            <span>👁 <?= number_format($berita['views']) ?></span>
                        </div>
                        <h3 class="news-title">
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($berita['slug'])) ?>">
                                <?= sanitize($berita['judul']) ?>
                            </a>
                        </h3>
                        <p class="news-excerpt"><?= excerpt($berita['excerpt'] ?? $berita['konten'], 120) ?></p>
                        <a href="<?= base_url('berita-detail.php?slug=' . urlencode($berita['slug'])) ?>" class="read-more">
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
<section class="section section-calendar">
    <div class="container">
        <div class="calendar-wrapper" data-aos="fade-up">
            <div class="calendar-header">
                <h2>Kalender <span class="gradient-text">Akademik</span></h2>
                <p>Agenda penting yang tidak boleh Anda lewatkan</p>
            </div>
            
            <div class="calendar-list">
                <?php if (empty($agenda)): ?>
                    <?php
                    $default_agenda = [
                        ['tanggal' => date('Y-m-d', strtotime('+14 days')), 'judul' => 'Ujian Tengah Semester Ganjil', 'jenis' => 'ujian'],
                        ['tanggal' => date('Y-m-d', strtotime('+30 days')), 'judul' => 'Seminar Nasional Pendidikan', 'jenis' => 'seminar'],
                        ['tanggal' => date('Y-m-d', strtotime('+60 days')), 'judul' => 'Ujian Akhir Semester', 'jenis' => 'ujian'],
                        ['tanggal' => date('Y-m-d', strtotime('+90 days')), 'judul' => 'Wisuda Periode I', 'jenis' => 'wisuda'],
                    ];
                    foreach ($default_agenda as $ag):
                    ?>
                    <div class="calendar-item">
                        <div class="calendar-date">
                            <span class="date-day"><?= date('d', strtotime($ag['tanggal'])) ?></span>
                            <span class="date-month"><?= strtoupper(date('M', strtotime($ag['tanggal']))) ?></span>
                        </div>
                        <div class="calendar-info">
                            <span class="calendar-type <?= $ag['jenis'] ?>"><?= ucfirst($ag['jenis']) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                        </div>
                        <div class="calendar-arrow">→</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($agenda as $ag): ?>
                    <div class="calendar-item">
                        <div class="calendar-date">
                            <span class="date-day"><?= date('d', strtotime($ag['tanggal_mulai'])) ?></span>
                            <span class="date-month"><?= strtoupper(date('M', strtotime($ag['tanggal_mulai']))) ?></span>
                        </div>
                        <div class="calendar-info">
                            <span class="calendar-type <?= $ag['jenis'] ?>"><?= ucfirst($ag['jenis']) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                        </div>
                        <div class="calendar-arrow">→</div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA SECTION ===== -->
<section class="section section-cta">
    <div class="container">
        <div class="cta-wrapper" data-aos="zoom-in">
            <div class="cta-content">
                <h2>Siap menjadi bagian dari <span class="gradient-text" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">perubahan?</span></h2>
                <p>Bergabunglah dengan ribuan mahasiswa yang telah memilih FKIP UNIMOF sebagai rumah akademik mereka. Wujudkan mimpi menjadi pendidik profesional mulai dari sini.</p>
                <div class="cta-actions">
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary btn-lg" style="background: white; color: var(--primary);">Daftar Sekarang</a>
                    <a href="<?= base_url('kontak.php') ?>" class="btn btn-outline btn-light">Konsultasi Gratis</a>
                </div>
            </div>
            <div class="cta-decoration">
                <div class="cta-orb orb-1"></div>
                <div class="cta-orb orb-2"></div>
                <div class="cta-orb orb-3"></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// 1. Generate Hero Particles
(function() {
    const container = document.getElementById('particles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 25 + 's';
        p.style.animationDuration = (20 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        container.appendChild(p);
    }
})();

// 2. Animated Counters (Intersection Observer)
function animateCounter(el) {
    const target = parseInt(el.dataset.count) || 0;
    const duration = 2000;
    const steps = 60;
    const stepValue = target / steps;
    let current = 0;
    
    const interval = setInterval(() => {
        current += stepValue;
        if (current >= target) {
            el.textContent = target.toLocaleString('id-ID');
            clearInterval(interval);
        } else {
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }
    }, duration / steps);
}

const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('[data-count]').forEach(el => counterObserver.observe(el));

// 3. Smooth Scroll for Anchor Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId !== '#' && document.querySelector(targetId)) {
            e.preventDefault();
            document.querySelector(targetId).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

console.log('%c🏠 Beranda FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cWebsite siap dengan performa optimal!', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>