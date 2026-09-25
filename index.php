<?php
// index.php - Halaman Beranda FKIP UNIMOF (EXTREME MULTIMATE VERSION)
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
/* ===== HERO EXTREME ===== */
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
    animation: heroMesh 20s ease-in-out infinite;
    filter: blur(60px);
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
    10% { opacity: 0.8; }
    90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}

.hero-content-extreme { position: relative; z-index: 2; text-align: center; max-width: 1000px; margin: 0 auto; }
.hero-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2); color: white;
    padding: 0.6rem 1.5rem; border-radius: 999px; font-size: 0.9rem; font-weight: 700;
    margin-bottom: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.pulse-dot-extreme {
    width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative;
}
.pulse-dot-extreme::after {
    content: ''; position: absolute; inset: 0; background: #10b981; border-radius: 50%;
    animation: pulse 2s infinite;
}
@keyframes pulse { to { transform: scale(3); opacity: 0; } }

.hero-title-extreme {
    font-family: var(--font-display); font-size: clamp(3rem, 7vw, 5.5rem);
    font-weight: 900; line-height: 1.05; margin-bottom: 1.5rem; letter-spacing: -0.03em; color: white;
}
.title-line-extreme { display: block; }
.title-line-extreme.highlight .gradient-text-extreme {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ec4899 100%);
    background-size: 200% 200%; -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; animation: gradientShift 5s ease infinite;
}
@keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

.hero-subtitle-extreme {
    font-size: clamp(1.1rem, 1.5vw, 1.35rem); color: rgba(255,255,255,0.85);
    max-width: 750px; margin: 0 auto 3rem; line-height: 1.7; font-weight: 400;
}
.hero-actions-extreme { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 4rem; }
.btn-hero-primary {
    padding: 1rem 2.5rem; background: white; color: var(--primary);
    border-radius: 999px; font-weight: 800; font-size: 1.05rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.75rem; transition: all 0.3s;
    box-shadow: 0 10px 30px rgba(255,255,255,0.2);
}
.btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(255,255,255,0.3); }
.btn-hero-outline {
    padding: 1rem 2.5rem; background: rgba(255,255,255,0.1); color: white;
    border: 2px solid rgba(255,255,255,0.3); border-radius: 999px;
    font-weight: 700; font-size: 1.05rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.75rem; transition: all 0.3s;
    backdrop-filter: blur(10px);
}
.btn-hero-outline:hover { background: rgba(255,255,255,0.2); border-color: white; transform: translateY(-3px); }

.hero-scroll-extreme {
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;
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

/* ===== STATS BAR EXTREME ===== */
.stats-bar-extreme {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem; max-width: 1100px; margin: -5rem auto 0; position: relative; z-index: 10;
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
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.stat-icon-extreme { font-size: 2.5rem; margin-bottom: 1rem; }
.stat-number-extreme {
    font-family: var(--font-display); font-size: 3.5rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.stat-label-extreme { font-size: 0.9rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== ABOUT SECTION EXTREME ===== */
.section-about-extreme { background: var(--bg-secondary); padding: 8rem 0; }
.about-grid-extreme { display: grid; grid-template-columns: 1fr 1.2fr; gap: 5rem; align-items: center; }
.about-content-extreme h3 { font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 1.5rem; line-height: 1.2; }
.about-content-extreme > p { color: var(--text-secondary); margin-bottom: 2.5rem; line-height: 1.8; font-size: 1.1rem; }

.features-list-extreme { display: grid; gap: 1.5rem; margin-bottom: 2.5rem; }
.feature-item-extreme {
    display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.5rem;
    background: var(--bg-primary); border-radius: var(--radius-lg);
    border: 1px solid var(--border); transition: all 0.3s;
}
.feature-item-extreme:hover { transform: translateX(8px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.feature-icon-extreme {
    width: 50px; height: 50px; background: rgba(10,104,71,0.1); border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;
}
.feature-item-extreme h4 { font-size: 1.1rem; margin-bottom: 0.25rem; font-weight: 700; }
.feature-item-extreme p { font-size: 0.9rem; color: var(--text-secondary); margin: 0; line-height: 1.6; }

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
.img-placeholder-extreme p { font-size: 1.1rem; margin-top: 0.75rem; font-weight: 700; letter-spacing: 0.05em; }

.stats-floating-extreme {
    position: absolute; bottom: -5%; right: 5%; z-index: 3;
    display: flex; gap: 1rem; background: var(--bg-primary);
    padding: 1.25rem 2rem; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl); border: 1px solid var(--border);
    backdrop-filter: blur(10px);
}
.stats-floating-extreme .stat-item { text-align: center; padding: 0 1.5rem; border-right: 1px solid var(--border); }
.stats-floating-extreme .stat-item:last-child { border: 0; }
.stats-floating-extreme strong { display: block; font-size: 2rem; color: var(--primary); font-family: var(--font-display); font-weight: 900; line-height: 1; }
.stats-floating-extreme span { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; display: block; }

/* ===== PROGRAMS SECTION EXTREME ===== */
.section-programs-extreme { padding: 8rem 0; }
.programs-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;
}
.program-card-extreme {
    position: relative; background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;
    display: flex; flex-direction: column;
}
.program-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--card-color-1, var(--primary)), var(--card-color-2, var(--primary-light)));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.program-card-extreme:hover {
    transform: translateY(-12px); box-shadow: 0 25px 50px rgba(0,0,0,0.1); border-color: var(--card-color-1, var(--primary));
}
.program-card-extreme:hover::before { transform: scaleX(1); }

.program-number-extreme {
    font-family: var(--font-display); font-size: 4rem; font-weight: 900;
    color: var(--bg-tertiary); line-height: 1; margin-bottom: 1rem; transition: all 0.4s;
}
.program-card-extreme:hover .program-number-extreme {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    transform: scale(1.1); transform-origin: left;
}
.program-icon-extreme { font-size: 3rem; margin-bottom: 1.5rem; transition: transform 0.4s; }
.program-card-extreme:hover .program-icon-extreme { transform: scale(1.2) rotate(-10deg); }

.program-badge-extreme {
    display: inline-block; padding: 0.4rem 1rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 1rem; background: rgba(10,104,71,0.1); color: var(--primary); width: fit-content;
}
.program-title-extreme { font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; transition: color 0.3s; line-height: 1.3; }
.program-card-extreme:hover .program-title-extreme { color: var(--card-color-1, var(--primary)); }
.program-level-extreme { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; font-weight: 600; }

.program-link-extreme {
    display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary);
    font-weight: 700; font-size: 0.95rem; text-decoration: none; transition: gap 0.3s;
    margin-top: auto;
}
.program-link-extreme:hover { gap: 0.8rem; }

/* ===== NEWS SECTION EXTREME ===== */
.section-news-extreme { background: var(--bg-secondary); padding: 8rem 0; }
.news-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 2rem;
}
.news-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column;
}
.news-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary-light); }
.news-card-extreme.featured { grid-column: span 2; }
.news-card-extreme.featured .news-image-extreme { height: 300px; }
.news-card-extreme.featured .news-title-extreme { font-size: 1.75rem; }

.news-image-extreme { position: relative; height: 220px; overflow: hidden; }
.news-image-extreme .img-placeholder, .news-image-extreme img {
    width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s;
}
.news-card-extreme:hover .news-image-extreme .img-placeholder,
.news-card-extreme:hover .news-image-extreme img { transform: scale(1.08); }

.news-category-extreme {
    position: absolute; top: 1.25rem; left: 1.25rem; background: rgba(255,255,255,0.95);
    backdrop-filter: blur(8px); color: var(--primary); padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; box-shadow: var(--shadow-sm); z-index: 2;
}
.news-content-extreme { padding: 2rem; display: flex; flex-direction: column; flex: 1; }
.news-meta-extreme { display: flex; gap: 1.25rem; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem; font-weight: 600; }
.news-title-extreme { font-size: 1.25rem; font-weight: 800; line-height: 1.4; margin-bottom: 1rem; }
.news-title-extreme a {
    color: var(--text-primary); text-decoration: none;
    background-image: linear-gradient(var(--primary), var(--primary));
    background-size: 0% 2px; background-position: 0 100%; background-repeat: no-repeat;
    transition: background-size 0.3s, color 0.3s;
}
.news-title-extreme a:hover { color: var(--primary); background-size: 100% 2px; }
.news-excerpt-extreme {
    color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7;
    margin-bottom: 1.5rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.read-more-extreme {
    color: var(--primary); font-weight: 700; font-size: 0.95rem; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.5rem; transition: gap 0.3s;
}
.read-more-extreme:hover { gap: 0.8rem; }

/* ===== CALENDAR SECTION EXTREME ===== */
.section-calendar-extreme { padding: 8rem 0; }
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
.calendar-item-extreme:hover { background: var(--bg-primary); border-color: var(--primary); transform: translateX(8px); box-shadow: var(--shadow-md); }
.calendar-date-extreme {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; padding: 1rem 1.25rem; border-radius: var(--radius-md);
    text-align: center; min-width: 80px; box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.date-day-extreme { display: block; font-size: 2rem; font-weight: 900; line-height: 1; }
.date-month-extreme { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 0.25rem; display: block; font-weight: 700; }
.calendar-type-extreme {
    display: inline-block; padding: 0.35rem 0.75rem; border-radius: 6px;
    font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.5rem;
}
.calendar-type-extreme.ujian { background: #fee2e2; color: #dc2626; }
.calendar-type-extreme.seminar { background: #dbeafe; color: #2563eb; }
.calendar-type-extreme.wisuda { background: #fef3c7; color: #d97706; }
.calendar-info-extreme h4 { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.25rem; }
.calendar-arrow-extreme { color: var(--primary); font-size: 1.5rem; transition: transform 0.3s; opacity: 0; }
.calendar-item-extreme:hover .calendar-arrow-extreme { opacity: 1; transform: translateX(5px); }

/* ===== CTA SECTION EXTREME ===== */
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
.cta-content-extreme h2 { font-family: var(--font-display); font-size: clamp(2rem, 4vw, 3rem); margin-bottom: 1.5rem; color: white; line-height: 1.2; }
.cta-content-extreme p { font-size: 1.2rem; margin-bottom: 2.5rem; opacity: 0.9; line-height: 1.7; }
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

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .stats-bar-extreme { grid-template-columns: repeat(2, 1fr); }
    .about-grid-extreme { grid-template-columns: 1fr; gap: 3rem; }
    .image-stack-extreme { height: 400px; }
    .news-card-extreme.featured { grid-column: span 1; }
    .calendar-item-extreme { grid-template-columns: auto 1fr; gap: 1.5rem; }
    .calendar-arrow-extreme { display: none; }
}
@media (max-width: 640px) {
    .hero-extreme { padding: 8rem 0 4rem; }
    .stats-bar-extreme { grid-template-columns: 1fr; margin-top: -3rem; }
    .stat-number-extreme { font-size: 2.5rem; }
    .cta-wrapper-extreme { padding: 3rem 1.5rem; }
    .calendar-wrapper-extreme { padding: 2rem 1.5rem; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="hero-extreme" id="hero">
    <div class="hero-bg-extreme"></div>
    <div class="hero-particles-extreme" id="particles"></div>
    
    <div class="container hero-content-extreme">
        <div class="hero-badge-extreme" data-aos="fade-down">
            <span class="pulse-dot-extreme"></span>
            Penerimaan Mahasiswa Baru 2026/2027 Telah Dibuka
        </div>
        
        <h1 class="hero-title-extreme" data-aos="fade-up">
            <span class="title-line-extreme">Menyalakan</span>
            <span class="title-line-extreme highlight">
                <span class="gradient-text-extreme">Terang Pendidikan</span>
            </span>
            <span class="title-line-extreme">Menghadirkan Harapan</span>
        </h1>
        
        <p class="hero-subtitle-extreme" data-aos="fade-up" data-aos-delay="200">
            Fakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere
            membentuk generasi pendidik yang cerdas, berkarakter, dan inovatif untuk Indonesia Timur.
        </p>
        
        <div class="hero-actions-extreme" data-aos="fade-up" data-aos-delay="400">
            <a href="<?= base_url('program.php') ?>" class="btn-hero-primary">
                <span>Jelajahi Program Studi</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="<?= base_url('kontak.php') ?>" class="btn-hero-outline">
                <span>Hubungi Kami</span>
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

<!-- ===== STATS BAR EXTREME ===== -->
<div class="container">
    <div class="stats-bar-extreme" data-aos="fade-up">
        <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
            <div class="stat-icon-extreme">👨‍🎓</div>
            <div class="stat-number-extreme count-up" data-count="<?= $statistik['total_mahasiswa'] ?? 1250 ?>">0</div>
            <div class="stat-label-extreme">Mahasiswa Aktif</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #10b981;">
            <div class="stat-icon-extreme">🎓</div>
            <div class="stat-number-extreme count-up" data-count="<?= $statistik['total_prodi'] ?? 8 ?>">0</div>
            <div class="stat-label-extreme">Program Studi</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
            <div class="stat-icon-extreme">👨‍🏫</div>
            <div class="stat-number-extreme count-up" data-count="<?= $statistik['total_dosen'] ?? 68 ?>">0</div>
            <div class="stat-label-extreme">Dosen Berkualitas</div>
        </div>
        <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
            <div class="stat-icon-extreme">🏆</div>
            <div class="stat-number-extreme count-up" data-count="<?= $statistik['total_alumni'] ?? 3200 ?>">0</div>
            <div class="stat-label-extreme">Alumni Sukses</div>
        </div>
    </div>
</div>

<!-- ===== ABOUT SECTION EXTREME ===== -->
<section class="section-about-extreme" id="about">
    <div class="container">
        <div class="about-grid-extreme">
            <div class="about-content-extreme" data-aos="fade-right">
                <span class="section-tag">Tentang Kami</span>
                <h3>Rumah bagi <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">calon pendidik</span> masa depan</h3>
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
                
                <a href="<?= base_url('about.php') ?>" class="btn btn-primary btn-lg">Pelajari Lebih Lanjut →</a>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PROGRAMS SECTION EXTREME ===== -->
<section class="section-programs-extreme" id="programs">
    <div class="container">
        <div class="section-header" data-aos="fade-up" style="text-align: center; max-width: 700px; margin: 0 auto 4rem;">
            <span class="section-tag">Program Studi</span>
            <h2 class="section-title" style="font-size: clamp(2rem, 4vw, 2.75rem);">Pilih jalur <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">pendidikan</span> terbaikmu</h2>
            <p class="section-desc" style="color: var(--text-secondary); font-size: 1.1rem; line-height: 1.7;">Kurikulum kami dirancang mengikuti standar nasional dan internasional, dibimbing oleh dosen berkualifikasi tinggi.</p>
        </div>
        
        <div class="programs-grid-extreme">
            <?php 
            $prodi_colors = [
                ['#3b82f6', '#1d4ed8'], ['#8b5cf6', '#6d28d9'], ['#10b981', '#059669'], ['#f59e0b', '#d97706'],
                ['#ec4899', '#db2777'], ['#ef4444', '#dc2626'], ['#14b8a6', '#0d9488'], ['#f97316', '#ea580c']
            ];
            $icons = ['📐', '⚛️', '🧬', '🧪', '🌐', '📖', '💰', '⚖️'];
            
            foreach ($prodi as $index => $p): 
                $colors = $prodi_colors[$index % count($prodi_colors)];
            ?>
            <article class="program-card-extreme" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>"
                style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;">
                <div class="program-number-extreme"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="program-icon-extreme"><?= $icons[$index] ?? '🎓' ?></div>
                <div class="program-content">
                    <div class="program-badge-extreme"><?= sanitize($p['akreditasi']) ?></div>
                    <h3 class="program-title-extreme"><?= sanitize($p['nama']) ?></h3>
                    <p class="program-level-extreme"><?= sanitize($p['jenjang']) ?> • <?= sanitize($p['singkatan']) ?></p>
                    <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" class="program-link-extreme">
                        Detail Program
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== NEWS SECTION EXTREME ===== -->
<section class="section-news-extreme" id="berita">
    <div class="container">
        <div class="section-header section-header-split" data-aos="fade-up" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 3rem; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <span class="section-tag">Berita Terkini</span>
                <h2 class="section-title" style="font-size: clamp(2rem, 4vw, 2.75rem); margin-bottom: 0.5rem;">Kabar dari <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">FKIP</span></h2>
            </div>
            <a href="<?= base_url('berita.php') ?>" class="btn btn-secondary">Lihat Semua Berita →</a>
        </div>
        
        <?php if (empty($berita_terbaru)): ?>
            <div class="empty-state" style="text-align:center; padding:4rem; background:var(--bg-primary); border-radius:var(--radius-xl); border: 1px solid var(--border);">
                <p style="font-size:1.2rem; color: var(--text-muted);">📭 Belum ada berita yang dipublikasikan.</p>
            </div>
        <?php else: ?>
            <div class="news-grid-extreme">
                <?php foreach ($berita_terbaru as $index => $berita): ?>
                <article class="news-card-extreme <?= $index === 0 ? 'featured' : '' ?>" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                    <div class="news-image-extreme">
                        <?php if (!empty($berita['gambar'])): ?>
                            <img src="<?= asset('uploads/' . basename($berita['gambar'])) ?>" alt="<?= sanitize($berita['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="img-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <span>📰</span>
                            </div>
                        <?php endif; ?>
                        <span class="news-category-extreme"><?= sanitize($berita['kategori']) ?></span>
                    </div>
                    <div class="news-content-extreme">
                        <div class="news-meta-extreme">
                            <span>📅 <?= format_tanggal_singkat($berita['published_at'] ?? $berita['created_at']) ?></span>
                            <span>👁 <?= number_format($berita['views']) ?> Views</span>
                        </div>
                        <h3 class="news-title-extreme">
                            <a href="<?= base_url('berita-detail.php?slug=' . urlencode($berita['slug'])) ?>">
                                <?= sanitize($berita['judul']) ?>
                            </a>
                        </h3>
                        <p class="news-excerpt-extreme"><?= excerpt($berita['excerpt'] ?? $berita['konten'], 120) ?></p>
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

<!-- ===== CALENDAR SECTION EXTREME ===== -->
<section class="section-calendar-extreme">
    <div class="container">
        <div class="calendar-wrapper-extreme" data-aos="fade-up">
            <div class="calendar-header" style="text-align: center; margin-bottom: 3rem;">
                <span class="section-tag">Jadwal Penting</span>
                <h2 class="section-title" style="font-size: clamp(2rem, 4vw, 2.75rem); margin-bottom: 0.5rem;">Kalender <span class="gradient-text-extreme" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Akademik</span></h2>
                <p style="color: var(--text-muted); font-size: 1.1rem;">Agenda penting yang tidak boleh Anda lewatkan</p>
            </div>
            
            <div class="calendar-list-extreme">
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
                    <div class="calendar-item-extreme">
                        <div class="calendar-date-extreme">
                            <span class="date-day-extreme"><?= date('d', strtotime($ag['tanggal'])) ?></span>
                            <span class="date-month-extreme"><?= strtoupper(date('M', strtotime($ag['tanggal']))) ?></span>
                        </div>
                        <div class="calendar-info-extreme">
                            <span class="calendar-type-extreme <?= $ag['jenis'] ?>"><?= ucfirst($ag['jenis']) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                        </div>
                        <div class="calendar-arrow-extreme">→</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($agenda as $ag): ?>
                    <div class="calendar-item-extreme">
                        <div class="calendar-date-extreme">
                            <span class="date-day-extreme"><?= date('d', strtotime($ag['tanggal_mulai'])) ?></span>
                            <span class="date-month-extreme"><?= strtoupper(date('M', strtotime($ag['tanggal_mulai']))) ?></span>
                        </div>
                        <div class="calendar-info-extreme">
                            <span class="calendar-type-extreme <?= $ag['jenis'] ?>"><?= ucfirst($ag['jenis']) ?></span>
                            <h4><?= sanitize($ag['judul']) ?></h4>
                        </div>
                        <div class="calendar-arrow-extreme">→</div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA SECTION EXTREME ===== -->
<section class="section-cta-extreme">
    <div class="container">
        <div class="cta-wrapper-extreme" data-aos="zoom-in">
            <div class="cta-content-extreme">
                <h2>Siap menjadi bagian dari <span class="gradient-text-extreme" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">perubahan?</span></h2>
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

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// 1. Generate Hero Particles
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

// 2. Animated Counters (Intersection Observer)
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