<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Beasiswa';
$page_description = 'Informasi lengkap program beasiswa FKIP UNIMOF untuk membantu mahasiswa berprestasi dan kurang mampu.';

// Ambil data beasiswa
$stmt = $pdo->query("SELECT * FROM beasiswa ORDER BY deadline ASC");
$beasiswa_list = $stmt->fetchAll();

// Statistik
$stat_total = count($beasiswa_list);
$stat_terbuka = count(array_filter($beasiswa_list, fn($b) => $b['status'] === 'Terbuka'));
$stat_tertutup = count(array_filter($beasiswa_list, fn($b) => $b['status'] === 'Tertutup'));
$stat_urgent = count(array_filter($beasiswa_list, fn($b) => $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days') && $b['status'] === 'Terbuka'));

// Jenis unik
$jenis_list = array_unique(array_filter(array_column($beasiswa_list, 'jenis')));

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO ===== */
.beasiswa-hero-extreme {
    position: relative; background: linear-gradient(135deg, #f59e0b 0%, #d97706 40%, #92400e 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.beasiswa-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(251,191,36,0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(245,158,11,0.25) 0%, transparent 50%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
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

/* ===== STATS ===== */
.beasiswa-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10;
}
.beasiswa-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.beasiswa-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.beasiswa-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.beasiswa-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.beasiswa-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.beasiswa-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.beasiswa-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.beasiswa-filter-chip {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.85rem;
    font-weight: 600; cursor: pointer; transition: all 0.3s;
}
.beasiswa-filter-chip:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.beasiswa-filter-chip.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

/* ===== BEASISWA GRID ===== */
.beasiswa-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.beasiswa-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
}
.beasiswa-card.urgent {
    border-color: #ef4444;
    box-shadow: 0 0 0 1px #ef4444, 0 4px 12px rgba(239,68,68,0.1);
}
.beasiswa-card.urgent::before {
    content: '⏰ SEGERA'; position: absolute; top: 1rem; right: -2rem;
    background: linear-gradient(135deg, #ef4444, #dc2626); color: white;
    padding: 0.3rem 3rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg); letter-spacing: 0.1em;
}
.beasiswa-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary);
}

.status-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.badge-terbuka { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-tertutup { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }

.jenis-badge {
    display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; background: rgba(245,158,11,0.1); color: #d97706;
}
.beasiswa-title {
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.3;
}
.beasiswa-sumber {
    font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem; font-weight: 600;
}
.beasiswa-nominal {
    background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.1));
    border: 1px solid rgba(245,158,11,0.2); border-radius: var(--radius-md);
    padding: 1rem; margin-bottom: 1rem; text-align: center;
}
.beasiswa-nominal-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem; }
.beasiswa-nominal-value { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: #d97706; }

.deadline-box {
    background: var(--bg-secondary); border-radius: var(--radius-md);
    padding: 0.75rem 1rem; margin-bottom: 1rem; display: flex;
    justify-content: space-between; align-items: center;
}
.deadline-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
.deadline-value { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
.deadline-value.urgent { color: #ef4444; }

.syarat-preview {
    color: var(--text-secondary); font-size: 0.85rem; line-height: 1.6;
    margin-bottom: 1rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.btn-detail-beasiswa {
    padding: 0.75rem; background: var(--primary);
    color: white; border: none; border-radius: var(--radius-md); font-weight: 700;
    font-size: 0.9rem; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.btn-detail-beasiswa:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }
.btn-detail-beasiswa:disabled { background: var(--bg-secondary); color: var(--text-muted); cursor: not-allowed; transform: none; box-shadow: none; }

/* ===== MODAL ===== */
.modal-overlay-ext {
    position: fixed; inset: 0; background: rgba(15,23,42,0.85); backdrop-filter: blur(10px);
    display: none; align-items: center; justify-content: center; z-index: 10000; padding: 1.5rem;
}
.modal-overlay-ext.show { display: flex; }
.modal-content-ext {
    background: var(--bg-primary); border-radius: var(--radius-xl); width: 100%; max-width: 700px;
    max-height: 90vh; overflow-y: auto; position: relative; animation: modalPopExt 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
}
@keyframes modalPopExt { from { transform: scale(0.9) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
.modal-close-ext {
    position: absolute; top: 1rem; right: 1rem; width: 40px; height: 40px;
    background: var(--bg-secondary); border: none; border-radius: 50%; cursor: pointer;
    font-size: 1.25rem; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; color: var(--text-muted); z-index: 10;
}
.modal-close-ext:hover { background: #fee2e2; color: #dc2626; transform: rotate(90deg); }
.modal-header-ext {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    padding: 3rem 2.5rem 2rem; color: white; position: relative;
}
.modal-header-ext h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 1rem; line-height: 1.4; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.9rem; opacity: 0.95; }
.modal-meta-ext span { display: inline-flex; align-items: center; gap: 0.4rem; }
.modal-body-ext { padding: 2.5rem; }
.modal-nominal-box {
    background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.1));
    border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-lg);
    padding: 1.5rem; text-align: center; margin-bottom: 2rem;
}
.modal-nominal-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; }
.modal-nominal-value { font-family: var(--font-display); font-size: 2rem; font-weight: 900; color: #d97706; }
.modal-section-ext { margin-bottom: 2rem; }
.modal-section-ext h4 {
    font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;
    font-weight: 700;
}
.modal-section-ext p { font-size: 1rem; color: var(--text-primary); line-height: 1.8; }
.syarat-list { list-style: none; padding: 0; margin: 0; }
.syarat-list li {
    padding: 0.75rem 1rem; background: var(--bg-secondary);
    border-radius: var(--radius-md); margin-bottom: 0.5rem;
    display: flex; align-items: flex-start; gap: 0.75rem;
    font-size: 0.95rem; color: var(--text-primary); line-height: 1.6;
}
.syarat-list li::before {
    content: '✓'; color: var(--primary); font-weight: 800; flex-shrink: 0;
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
.empty-beasiswa-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 968px) {
    .beasiswa-stats-bar { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .beasiswa-hero-extreme { padding: 8rem 0 4rem; }
    .beasiswa-stats-bar { grid-template-columns: 1fr; margin: -3rem 1rem 2rem; }
    .beasiswa-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="beasiswa-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container" style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Beasiswa</span>
        </nav>
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.25rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;" data-aos="fade-down" data-aos-delay="100">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative;">
                <span style="position: absolute; inset: 0; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span>
            </span>
            <span>Student Financial Aid Center</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Portal <span style="background: linear-gradient(135deg, #fef3c7, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Beasiswa</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Wujudkan impian akademik Anda dengan berbagai program beasiswa dari FKIP UNIMOF, Yayasan Muhammadiyah, hingga pemerintah.
        </p>
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
                <div class="beasiswa-stat-label">Pendaftaran Terbuka</div>
            </div>
            <div class="beasiswa-stat-card" style="--stat-color: #64748b;">
                <div class="beasiswa-stat-icon">🔒</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_tertutup ?>">0</div>
                <div class="beasiswa-stat-label">Pendaftaran Ditutup</div>
            </div>
            <div class="beasiswa-stat-card" style="--stat-color: #ef4444;">
                <div class="beasiswa-stat-icon">⏰</div>
                <div class="beasiswa-stat-num count-up" data-target="<?= $stat_urgent ?>">0</div>
                <div class="beasiswa-stat-label">Segera Tutup</div>
            </div>
        </div>

        <?php if (empty($beasiswa_list)): ?>
            <div class="empty-beasiswa-ext" data-aos="fade-up">
                <div class="empty-icon-lg"></div>
                <h3>Belum ada program beasiswa</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Program beasiswa akan segera diumumkan di sini.</p>
            </div>
        <?php else: ?>
            <!-- Filter -->
            <div class="beasiswa-toolbar" data-aos="fade-up">
                <button class="beasiswa-filter-chip active" onclick="filterBeasiswa('all', this)">🎓 Semua</button>
                <button class="beasiswa-filter-chip" onclick="filterBeasiswa('Terbuka', this)">✅ Terbuka</button>
                <button class="beasiswa-filter-chip" onclick="filterBeasiswa('Tertutup', this)">🔒 Tertutup</button>
                <?php foreach ($jenis_list as $j): ?>
                <button class="beasiswa-filter-chip" onclick="filterBeasiswa('<?= sanitize($j) ?>', this)"><?= sanitize($j) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Grid -->
            <div class="beasiswa-grid" id="beasiswaGrid" data-aos="fade-up">
                <?php foreach ($beasiswa_list as $b): 
                    $is_urgent = $b['deadline'] && strtotime($b['deadline']) < strtotime('+30 days') && $b['status'] === 'Terbuka';
                    $badge_class = strtolower($b['status']) === 'terbuka' ? 'badge-terbuka' : 'badge-tertutup';
                ?>
                <article class="beasiswa-card <?= $is_urgent ? 'urgent' : '' ?>" 
                    data-status="<?= sanitize($b['status']) ?>"
                    data-jenis="<?= sanitize($b['jenis'] ?? '') ?>">
                    
                    <span class="status-badge <?= $badge_class ?>"><?= $b['status'] === 'Terbuka' ? '✅' : '🔒' ?> <?= $b['status'] ?></span>
                    <span class="jenis-badge"><?= sanitize($b['jenis'] ?? 'Beasiswa') ?></span>
                    <h3 class="beasiswa-title"><?= sanitize($b['nama']) ?></h3>
                    <div class="beasiswa-sumber">Sumber: <?= sanitize($b['sumber'] ?? 'FKIP UNIMOF') ?></div>
                    
                    <div class="beasiswa-nominal">
                        <div class="beasiswa-nominal-label">Cakupan Beasiswa</div>
                        <div class="beasiswa-nominal-value"><?= sanitize($b['nominal'] ?? '-') ?></div>
                    </div>
                    
                    <?php if ($b['deadline']): ?>
                    <div class="deadline-box">
                        <span class="deadline-label">⏰ Deadline</span>
                        <span class="deadline-value <?= $is_urgent ? 'urgent' : '' ?>"><?= date('d M Y', strtotime($b['deadline'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <p class="syarat-preview"><?= excerpt($b['syarat'] ?? 'Syarat belum tersedia.', 150) ?></p>
                    
                    <button class="btn-detail-beasiswa" onclick='openBeasiswaModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' <?= $b['status'] !== 'Terbuka' ? 'disabled' : '' ?>>
                        <span>📋</span> <?= $b['status'] === 'Terbuka' ? 'Lihat Syarat & Daftar' : 'Pendaftaran Ditutup' ?>
                    </button>
                </article>
                <?php endforeach; ?>
            </div>
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
    <div class="modal-content-ext">
        <button class="modal-close-ext" onclick="closeBeasiswaModal()">✕</button>
        <div class="modal-header-ext">
            <h2 id="modalTitle"></h2>
            <div class="modal-meta-ext">
                <span id="modalJenis"></span>
                <span id="modalSumber"></span>
                <span id="modalStatus"></span>
            </div>
        </div>
        <div class="modal-body-ext">
            <div class="modal-nominal-box">
                <div class="modal-nominal-label">💰 Cakupan Beasiswa</div>
                <div class="modal-nominal-value" id="modalNominal"></div>
            </div>
            
            <?php if (!empty($b['deadline'])): ?>
            <div class="modal-section-ext">
                <h4>⏰ Deadline Pendaftaran</h4>
                <p id="modalDeadline" style="font-size: 1.25rem; font-weight: 700; color: #d97706;"></p>
            </div>
            <?php endif; ?>
            
            <div class="modal-section-ext">
                <h4>📋 Syarat & Ketentuan</h4>
                <ul class="syarat-list" id="modalSyarat"></ul>
            </div>
        </div>
    </div>
</div>

<script>
// Particles
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

// Count Up
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

// Filter
const beasiswaCards = document.querySelectorAll('.beasiswa-card');
let currentFilter = 'all';

function filterBeasiswa(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.beasiswa-filter-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    let visibleCount = 0;
    beasiswaCards.forEach(card => {
        const status = card.dataset.status;
        const jenis = card.dataset.jenis;
        const match = filter === 'all' || status === filter || jenis === filter;
        card.style.display = match ? 'flex' : 'none';
        if (match) visibleCount++;
    });

    const grid = document.getElementById('beasiswaGrid');
    let emptyEl = grid.querySelector('.empty-filter-state');
    if (visibleCount === 0 && beasiswaCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-beasiswa-ext empty-filter-state';
            emptyEl.innerHTML = '<div class="empty-icon-lg">🔍</div><h3>Tidak ada beasiswa di filter ini</h3><p style="color: var(--text-muted); margin-top: 0.5rem;">Coba pilih filter lain.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

// Modal
function openBeasiswaModal(b) {
    document.getElementById('modalTitle').textContent = b.nama;
    document.getElementById('modalJenis').textContent = '🏷️ ' + (b.jenis || '-');
    document.getElementById('modalSumber').textContent = '🏢 ' + (b.sumber || '-');
    document.getElementById('modalStatus').textContent = (b.status === 'Terbuka' ? '✅' : '🔒') + ' ' + b.status;
    document.getElementById('modalNominal').textContent = b.nominal || '-';
    
    if (b.deadline) {
        const deadlineEl = document.getElementById('modalDeadline');
        deadlineEl.textContent = ' ' + new Date(b.deadline).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        deadlineEl.parentElement.style.display = 'block';
    } else {
        document.getElementById('modalDeadline').parentElement.style.display = 'none';
    }
    
    const syaratList = document.getElementById('modalSyarat');
    syaratList.innerHTML = '';
    if (b.syarat) {
        const lines = b.syarat.split('\n').filter(l => l.trim());
        lines.forEach(line => {
            const li = document.createElement('li');
            li.textContent = line.replace(/^\d+[\.\)]\s*/, '');
            syaratList.appendChild(li);
        });
    } else {
        syaratList.innerHTML = '<li>Syarat belum tersedia.</li>';
    }
    
    document.getElementById('beasiswaModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBeasiswaModal() {
    document.getElementById('beasiswaModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeBeasiswaModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>