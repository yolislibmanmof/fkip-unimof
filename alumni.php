<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Alumni Sukses';
$page_description = 'Jejaring alumni FKIP UNIMOF yang berkarir gemilang di berbagai bidang pendidikan, bisnis, dan pemerintahan.';

// Ambil data alumni
$stmt = $pdo->query("SELECT a.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status = 'Aktif' ORDER BY a.tahun_lulus DESC, a.nama ASC");
$alumni_list = $stmt->fetchAll();

// Statistik alumni
$stat_total = count($alumni_list);
$stat_bekerja = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND (pekerjaan LIKE '%Bekerja%' OR pekerjaan LIKE '%Guru%' OR pekerjaan LIKE '%Dosen%' OR pekerjaan LIKE '%PNS%')")->fetchColumn();
$stat_wirausaha = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND (pekerjaan LIKE '%Wirausaha%' OR pekerjaan LIKE '%Founder%' OR pekerjaan LIKE '%CEO%')")->fetchColumn();
$stat_lanjut = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND pekerjaan LIKE '%Lanjut Studi%'")->fetchColumn();

// Testimoni (ambil 3 alumni dengan testimoni)
$stmtTesti = $pdo->query("SELECT a.*, p.nama as prodi_nama FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status='Aktif' AND a.testimoni IS NOT NULL AND a.testimoni != '' ORDER BY RAND() LIMIT 3");
$testimoni_list = $stmtTesti->fetchAll();

// Tahun unik untuk filter
$years = array_unique(array_filter(array_column($alumni_list, 'tahun_lulus')));
rsort($years);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.alumni-hero-extreme {
    position: relative; background: linear-gradient(135deg, #059669 0%, #0a6847 40%, #064e34 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.alumni-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(255,255,255,0.15) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-20px, 20px) scale(1.05); } }
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px; background: rgba(255,255,255,0.6);
    border-radius: 50%; animation: floatParticle 30s infinite linear;
}
@keyframes floatParticle {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; } 90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}
.hero-content-extreme { position: relative; z-index: 2; max-width: 900px; margin: 0 auto; text-align: center; }

/* ===== STATS BAR ===== */
.alumni-stats-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem 0 4rem; position: relative; z-index: 10;
}
.alumni-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.alumni-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.alumni-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.alumni-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.alumni-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.alumni-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== FEATURED ALUMNI ===== */
.featured-alumni-section { margin-bottom: 4rem; }
.featured-alumni-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; }
.featured-alumni-card {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
}
.featured-alumni-card::before {
    content: '⭐ ALUMNI TERBAIK'; position: absolute; top: 1.5rem; right: -2.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.3rem 3rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg); letter-spacing: 0.1em;
}
.featured-alumni-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.featured-quote {
    font-size: 1.1rem; line-height: 1.7; color: var(--text-secondary);
    font-style: italic; margin-bottom: 2rem; position: relative; padding-left: 1.5rem;
    border-left: 3px solid var(--primary); flex: 1;
}
.featured-author { display: flex; align-items: center; gap: 1rem; margin-top: auto; }
.featured-avatar {
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 800; flex-shrink: 0;
}
.featured-info h4 { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.25rem; }
.featured-info p { font-size: 0.85rem; color: var(--text-muted); }

/* ===== TOOLBAR ===== */
.alumni-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.alumni-search { flex: 1; min-width: 250px; position: relative; }
.alumni-search input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; transition: all 0.3s;
}
.alumni-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.alumni-search .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.alumni-filter {
    padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s;
}
.alumni-filter:focus { outline: none; border-color: var(--primary); }

/* ===== ALUMNI GRID ===== */
.alumni-grid-extreme { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
.alumni-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    transition: all 0.4s; position: relative; overflow: hidden;
}
.alumni-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.alumni-card-extreme:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary);
}
.alumni-card-extreme:hover::before { transform: scaleX(1); }

.alumni-avatar-ext {
    width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; box-shadow: 0 8px 20px rgba(10,104,71,0.25);
    border: 4px solid var(--bg-primary);
}
.alumni-card-extreme h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-primary); }
.alumni-year-ext { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem; font-weight: 600; }
.alumni-prodi-ext {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    background: var(--bg-secondary); border-radius: 999px; font-size: 0.85rem;
    color: var(--text-secondary); font-weight: 600; margin-bottom: 1rem;
}
.alumni-job-ext {
    font-size: 0.95rem; color: var(--primary); font-weight: 700;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    margin-bottom: 1rem;
}
.alumni-prestasi-ext {
    font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;
    padding-top: 1rem; border-top: 1px solid var(--border); font-style: italic;
}

/* ===== TESTIMONIALS ===== */
.testimoni-section-extreme { margin: 5rem 0; }
.testimoni-grid-extreme { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; }
.testimoni-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; position: relative;
    transition: all 0.4s;
}
.testimoni-card-extreme:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary-light); }
.testimoni-card-extreme::before {
    content: '❝'; position: absolute; top: 1.5rem; right: 2rem;
    font-size: 5rem; color: var(--primary); opacity: 0.1; font-family: Georgia, serif; line-height: 1;
}
.testimoni-text-extreme {
    font-size: 1.05rem; color: var(--text-secondary); line-height: 1.8;
    font-style: italic; margin-bottom: 2rem; position: relative; z-index: 2;
}
.testimoni-author-extreme { display: flex; align-items: center; gap: 1rem; }
.testimoni-avatar-ext {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.25rem; flex-shrink: 0;
}
.testimoni-info-extreme strong { display: block; font-size: 1rem; color: var(--text-primary); margin-bottom: 0.25rem; }
.testimoni-info-extreme span { font-size: 0.85rem; color: var(--text-muted); }

/* ===== CTA EXTREME ===== */
.alumni-cta-extreme {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.alumni-cta-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.2) 0%, transparent 50%);
}
.alumni-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY STATE ===== */
.empty-alumni-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .featured-alumni-grid { grid-template-columns: 1fr; }
    .alumni-stats-extreme { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .alumni-hero-extreme { padding: 8rem 0 4rem; }
    .alumni-stats-extreme { grid-template-columns: 1fr; margin: -3rem 1rem 2rem; }
    .alumni-toolbar { flex-direction: column; align-items: stretch; }
    .alumni-filter { width: 100%; }
    .alumni-grid-extreme { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="alumni-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container hero-content-extreme">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Alumni Sukses</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Hall of <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Fame Alumni</span> 🎓
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Lulusan FKIP UNIMOF yang telah berkarir gemilang, menjadi pemimpin, dan menginspirasi generasi berikutnya di seluruh Indonesia.
        </p>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats Bar -->
        <div class="alumni-stats-extreme" data-aos="fade-up">
            <div class="alumni-stat-card" style="--stat-color: #3b82f6;">
                <div class="alumni-stat-icon">🎓</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="alumni-stat-label">Total Alumni</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #10b981;">
                <div class="alumni-stat-icon">💼</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_bekerja ?>">0</div>
                <div class="alumni-stat-label">Bekerja</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #f59e0b;">
                <div class="alumni-stat-icon">🚀</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_wirausaha ?>">0</div>
                <div class="alumni-stat-label">Wirausaha</div>
            </div>
            <div class="alumni-stat-card" style="--stat-color: #8b5cf6;">
                <div class="alumni-stat-icon">📚</div>
                <div class="alumni-stat-num count-up" data-target="<?= $stat_lanjut ?>">0</div>
                <div class="alumni-stat-label">Lanjut Studi</div>
            </div>
        </div>

        <!-- Featured Alumni (Testimoni) -->
        <?php if (!empty($testimoni_list)): ?>
        <div class="featured-alumni-section" data-aos="fade-up">
            <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
                <span class="section-tag">Cerita Sukses</span>
                <h2 class="section-title">Kata <span class="gradient-text">Mereka</span></h2>
            </div>
            <div class="featured-alumni-grid">
                <?php foreach ($testimoni_list as $t): 
                    $initials = strtoupper(substr($t['nama'], 0, 1) . (strpos($t['nama'], ' ') ? substr($t['nama'], strpos($t['nama'], ' ') + 1, 1) : ''));
                ?>
                <div class="featured-alumni-card">
                    <p class="featured-quote">"<?= sanitize($t['testimoni']) ?>"</p>
                    <div class="featured-author">
                        <div class="featured-avatar"><?= $initials ?></div>
                        <div class="featured-info">
                            <h4><?= sanitize($t['nama']) ?></h4>
                            <p>Alumni <?= sanitize($t['tahun_lulus']) ?> • <?= sanitize($t['prodi_nama'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Toolbar Filter -->
        <div class="alumni-toolbar" data-aos="fade-up">
            <div class="alumni-search">
                <span class="icon">🔍</span>
                <input type="text" id="alumniSearch" placeholder="Cari nama alumni...">
            </div>
            <select class="alumni-filter" id="yearFilter">
                <option value="all">Semua Angkatan</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <select class="alumni-filter" id="jobFilter">
                <option value="all">Semua Profesi</option>
                <option value="Bekerja">💼 Bekerja</option>
                <option value="Wirausaha">🚀 Wirausaha</option>
                <option value="Lanjut Studi">📚 Lanjut Studi</option>
            </select>
        </div>

        <!-- Alumni Grid -->
        <div class="alumni-grid-header" data-aos="fade-up">
            <span class="section-tag">Direktori</span>
            <h2 class="section-title">Alumni <span class="gradient-text">Inspiratif</span></h2>
            <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Berbagai profesi dan pencapaian yang diraih oleh lulusan kami di seluruh Indonesia.</p>
        </div>

        <div class="alumni-grid-extreme" id="alumniGrid" data-aos="fade-up">
            <?php if (empty($alumni_list)): ?>
                <div class="empty-alumni-ext">
                    <div class="empty-icon-lg">🎓</div>
                    <h3>Belum ada data alumni</h3>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">Direktori alumni akan segera diperbarui.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alumni_list as $a): 
                    $initials = strtoupper(substr($a['nama'], 0, 1) . (strpos($a['nama'], ' ') ? substr($a['nama'], strpos($a['nama'], ' ') + 1, 1) : ''));
                    $job_icons = ['Bekerja' => '💼', 'Wirausaha' => '🚀', 'Lanjut Studi' => '📚', 'Guru' => '👨‍🏫', 'Dosen' => '🎓', 'PNS' => '🏛️'];
                    $job_icon = '⭐';
                    foreach ($job_icons as $key => $icon) {
                        if (stripos($a['pekerjaan'], $key) !== false) {
                            $job_icon = $icon;
                            break;
                        }
                    }
                ?>
                <div class="alumni-card-extreme" 
                     data-nama="<?= strtolower(sanitize($a['nama'])) ?>"
                     data-tahun="<?= sanitize($a['tahun_lulus']) ?>"
                     data-job="<?= strtolower(sanitize($a['pekerjaan'] ?? '')) ?>">
                    
                    <div class="alumni-avatar-ext"><?= $initials ?></div>
                    <h3><?= sanitize($a['nama']) ?></h3>
                    <div class="alumni-year-ext">Angkatan <?= sanitize($a['tahun_lulus']) ?></div>
                    <div class="alumni-prodi-ext">🎓 <?= sanitize($a['prodi_singkatan'] ?? $a['prodi_nama'] ?? 'Umum') ?></div>
                    <div class="alumni-job-ext">
                        <span class="job-icon"><?= $job_icon ?></span>
                        <span><?= sanitize($a['pekerjaan'] ?? 'Profesional') ?></span>
                    </div>
                    <?php if (!empty($a['prestasi'])): ?>
                        <p class="alumni-prestasi-ext">
                            "<?= excerpt($a['prestasi'], 80) ?>"
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- CTA Section -->
        <div class="alumni-cta-extreme" data-aos="zoom-in">
            <div class="alumni-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Apakah Anda Alumni FKIP UNIMOF?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Bergabunglah dengan jejaring alumni kami dan bagikan kisah sukses Anda untuk menginspirasi adik-adik tingkat. Mari tetap terhubung!
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📩 Daftarkan Diri Anda
                </a>
            </div>
        </div>
    </div>
</section>

<script>
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

// ===== FILTER FUNCTIONALITY =====
const alumniSearch = document.getElementById('alumniSearch');
const yearFilter = document.getElementById('yearFilter');
const jobFilter = document.getElementById('jobFilter');
const alumniCards = document.querySelectorAll('.alumni-card-extreme');

function filterAlumni() {
    const query = alumniSearch.value.toLowerCase();
    const year = yearFilter.value;
    const job = jobFilter.value.toLowerCase();
    let visibleCount = 0;

    alumniCards.forEach(card => {
        const nama = card.dataset.nama;
        const cardYear = card.dataset.tahun;
        const cardJob = card.dataset.job;
        
        const matchSearch = !query || nama.includes(query);
        const matchYear = year === 'all' || cardYear === year;
        const matchJob = job === 'all' || cardJob.includes(job);

        if (matchSearch && matchYear && matchJob) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide empty state
    const grid = document.getElementById('alumniGrid');
    let emptyEl = grid.querySelector('.empty-alumni-dynamic');
    if (visibleCount === 0 && alumniCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-alumni-ext empty-alumni-dynamic';
            emptyEl.innerHTML = '<div class="empty-icon-lg">🔍</div><h3>Tidak ada alumni ditemukan</h3><p style="color: var(--text-muted); margin-top: 0.5rem;">Coba ubah kata kunci atau filter pencarian.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

alumniSearch.addEventListener('input', filterAlumni);
yearFilter.addEventListener('change', filterAlumni);
jobFilter.addEventListener('change', filterAlumni);

console.log('%c🎓 Alumni FKIP UNIMOF', 'color:#059669;font-size:16px;font-weight:bold');
console.log('%cTerima kasih telah menjadi bagian dari keluarga besar kami!', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>