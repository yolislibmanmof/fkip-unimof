<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Jurnal Ilmiah';
$page_description = 'Portal jurnal ilmiah FKIP UNIMOF yang terakreditasi dan bereputasi di tingkat nasional maupun internasional.';

// Ambil data jurnal
$stmt = $pdo->query("SELECT * FROM jurnal WHERE status = 'Aktif' ORDER BY created_at DESC");
$jurnal_list = $stmt->fetchAll();

// Statistik
$stat_total = count($jurnal_list);
$stat_url = count(array_filter($jurnal_list, fn($j) => !empty($j['url'])));
$stat_sinta = count(array_filter($jurnal_list, fn($j) => stripos($j['akreditasi'], 'Sinta') !== false));
$stat_scopus = count(array_filter($jurnal_list, fn($j) => stripos($j['akreditasi'], 'Scopus') !== false));

// Akreditasi unik untuk filter
$akreditasis = array_unique(array_filter(array_column($jurnal_list, 'akreditasi')));

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.jurnal-hero-extreme {
    position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.jurnal-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%);
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

/* ===== STATS BAR ===== */
.jurnal-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10;
}
.jurnal-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.jurnal-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.jurnal-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.jurnal-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.jurnal-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.jurnal-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.jurnal-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.jurnal-search { flex: 1; min-width: 250px; position: relative; }
.jurnal-search input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; transition: all 0.3s;
}
.jurnal-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.jurnal-search .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.jurnal-filter {
    padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s;
}
.jurnal-filter:focus { outline: none; border-color: var(--primary); }

/* ===== JURNAL GRID ===== */
.jurnal-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.jurnal-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
}
.jurnal-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.jurnal-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95;
}
.jurnal-card:hover::before { transform: scaleX(1); }

.akreditasi-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.badge-scopus { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-sinta1, .badge-sinta2 { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-sinta3, .badge-sinta4 { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-sinta5, .badge-sinta6 { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }
.badge-default { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }

.jurnal-icon { font-size: 3rem; margin-bottom: 1rem; }
.jurnal-title {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.3;
}
.jurnal-penerbit {
    font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem; font-weight: 600;
}
.jurnal-desc {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.5rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.jurnal-meta {
    display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-muted);
}
.jurnal-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }
.btn-visit-jurnal {
    margin-top: 1rem; padding: 0.75rem; background: var(--primary);
    color: white; border: none; border-radius: var(--radius-md); font-weight: 700;
    font-size: 0.9rem; cursor: pointer; transition: all 0.3s; text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.btn-visit-jurnal:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

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
    background: linear-gradient(135deg, #1e3a8a, #4c1d95);
    padding: 3rem 2.5rem 2rem; color: white; position: relative;
}
.modal-header-ext h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 1rem; line-height: 1.4; }
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.9rem; opacity: 0.9; }
.modal-meta-ext span { display: inline-flex; align-items: center; gap: 0.4rem; }
.modal-body-ext { padding: 2.5rem; }
.modal-section-ext { margin-bottom: 2rem; }
.modal-section-ext h4 {
    font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;
    font-weight: 700;
}
.modal-section-ext p { font-size: 1rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }

/* ===== CTA ===== */
.jurnal-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #4c1d95 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.jurnal-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.jurnal-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY STATE ===== */
.empty-jurnal-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 968px) {
    .jurnal-stats-bar { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .jurnal-hero-extreme { padding: 8rem 0 4rem; }
    .jurnal-stats-bar { grid-template-columns: 1fr; margin: -3rem 1rem 2rem; }
    .jurnal-toolbar { flex-direction: column; align-items: stretch; }
    .jurnal-filter { width: 100%; }
    .jurnal-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="jurnal-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container" style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Jurnal Ilmiah</span>
        </nav>
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.25rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;" data-aos="fade-down" data-aos-delay="100">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative;">
                <span style="position: absolute; inset: 0; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span>
            </span>
            <span>Research Excellence Center</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Portal <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Jurnal Ilmiah</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Kumpulan jurnal ilmiah FKIP UNIMOF yang terakreditasi dan bereputasi, menjadi wadah publikasi penelitian berkualitas dari dosen dan mahasiswa.
        </p>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats -->
        <div class="jurnal-stats-bar" data-aos="fade-up">
            <div class="jurnal-stat-card" style="--stat-color: #3b82f6;">
                <div class="jurnal-stat-icon">📚</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="jurnal-stat-label">Total Jurnal</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #10b981;">
                <div class="jurnal-stat-icon">🔗</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_url ?>">0</div>
                <div class="jurnal-stat-label">Akses Online</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #f59e0b;">
                <div class="jurnal-stat-icon"></div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_sinta ?>">0</div>
                <div class="jurnal-stat-label">Terakreditasi Sinta</div>
            </div>
            <div class="jurnal-stat-card" style="--stat-color: #8b5cf6;">
                <div class="jurnal-stat-icon">🌍</div>
                <div class="jurnal-stat-num count-up" data-target="<?= $stat_scopus ?>">0</div>
                <div class="jurnal-stat-label">Terindeks Scopus</div>
            </div>
        </div>

        <?php if (empty($jurnal_list)): ?>
            <div class="empty-jurnal-ext" data-aos="fade-up">
                <div class="empty-icon-lg">📚</div>
                <h3>Belum ada jurnal terdaftar</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Jurnal ilmiah akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>
            <!-- Toolbar -->
            <div class="jurnal-toolbar" data-aos="fade-up">
                <div class="jurnal-search">
                    <span class="icon">🔍</span>
                    <input type="text" id="jurnalSearch" placeholder="Cari nama jurnal, penerbit...">
                </div>
                <select class="jurnal-filter" id="akreditasiFilter">
                    <option value="all">Semua Akreditasi</option>
                    <?php foreach ($akreditasis as $a): ?>
                        <option value="<?= sanitize($a) ?>"><?= sanitize($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grid -->
            <div class="jurnal-grid" id="jurnalGrid" data-aos="fade-up">
                <?php foreach ($jurnal_list as $j): 
                    $akr = strtolower($j['akreditasi'] ?? '');
                    $badge_class = 'badge-default';
                    if (stripos($akr, 'scopus') !== false) $badge_class = 'badge-scopus';
                    elseif (in_array($akr, ['sinta 1', 'sinta 2'])) $badge_class = 'badge-sinta1';
                    elseif (in_array($akr, ['sinta 3', 'sinta 4'])) $badge_class = 'badge-sinta3';
                    elseif (in_array($akr, ['sinta 5', 'sinta 6'])) $badge_class = 'badge-sinta5';
                ?>
                <article class="jurnal-card" 
                    data-nama="<?= strtolower(sanitize($j['nama'])) ?>"
                    data-penerbit="<?= strtolower(sanitize($j['penerbit'])) ?>"
                    data-akreditasi="<?= sanitize($j['akreditasi'] ?? '') ?>">
                    
                    <span class="akreditasi-badge <?= $badge_class ?>"><?= sanitize($j['akreditasi']) ?></span>
                    <div class="jurnal-icon">📖</div>
                    <h3 class="jurnal-title"><?= sanitize($j['nama']) ?></h3>
                    <div class="jurnal-penerbit">Penerbit: <?= sanitize($j['penerbit']) ?></div>
                    <p class="jurnal-desc"><?= excerpt($j['deskripsi'] ?? 'Jurnal ilmiah FKIP UNIMOF.', 150) ?></p>
                    
                    <div class="jurnal-meta">
                        <?php if (!empty($j['issn'])): ?>
                            <span> ISSN: <?= sanitize($j['issn']) ?></span>
                        <?php endif; ?>
                        <span>📅 <?= date('Y', strtotime($j['created_at'])) ?></span>
                    </div>
                    
                    <?php if (!empty($j['url'])): ?>
                    <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-visit-jurnal">
                        <span>🔗</span> Kunjungi Jurnal
                    </a>
                    <?php else: ?>
                    <button class="btn-visit-jurnal" onclick='openJurnalModal(<?= json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="background: var(--bg-secondary); color: var(--text-primary);">
                        <span>📖</span> Lihat Detail
                    </button>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CTA -->
        <div class="jurnal-cta" data-aos="zoom-in">
            <div class="jurnal-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Punya Penelitian Berkualitas?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Publikasikan hasil penelitian Anda di jurnal-jurnal kami yang terakreditasi dan terindeks nasional maupun internasional.
                </p>
                <a href="<?= base_url('riset.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📝 Lihat Panduan Publikasi
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL ===== -->
<div class="modal-overlay-ext" id="jurnalModal" onclick="if(event.target===this)closeJurnalModal()">
    <div class="modal-content-ext">
        <button class="modal-close-ext" onclick="closeJurnalModal()">✕</button>
        <div class="modal-header-ext">
            <h2 id="modalTitle"></h2>
            <div class="modal-meta-ext">
                <span id="modalPenerbit"></span>
                <span id="modalAkreditasi"></span>
            </div>
        </div>
        <div class="modal-body-ext">
            <div class="modal-section-ext">
                <h4>📝 Tentang Jurnal</h4>
                <p id="modalDeskripsi"></p>
            </div>
            <div class="modal-section-ext">
                <h4>🔖 Informasi Teknis</h4>
                <p id="modalInfo"></p>
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
const jurnalSearch = document.getElementById('jurnalSearch');
const akreditasiFilter = document.getElementById('akreditasiFilter');
const jurnalCards = document.querySelectorAll('.jurnal-card');

function filterJurnal() {
    const query = jurnalSearch.value.toLowerCase();
    const akr = akreditasiFilter.value;
    let visibleCount = 0;

    jurnalCards.forEach(card => {
        const nama = card.dataset.nama;
        const penerbit = card.dataset.penerbit;
        const cardAkr = card.dataset.akreditasi;
        
        const matchSearch = !query || nama.includes(query) || penerbit.includes(query);
        const matchAkr = akr === 'all' || cardAkr === akr;

        if (matchSearch && matchAkr) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const grid = document.getElementById('jurnalGrid');
    let emptyEl = grid.querySelector('.empty-filter-state');
    if (visibleCount === 0 && jurnalCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-jurnal-ext empty-filter-state';
            emptyEl.innerHTML = '<div class="empty-icon-lg"></div><h3>Tidak ada jurnal ditemukan</h3><p style="color: var(--text-muted); margin-top: 0.5rem;">Coba ubah kata kunci atau filter pencarian.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

jurnalSearch.addEventListener('input', filterJurnal);
akreditasiFilter.addEventListener('change', filterJurnal);

// Modal
function openJurnalModal(j) {
    document.getElementById('modalTitle').textContent = j.nama;
    document.getElementById('modalPenerbit').textContent = '🏢 ' + (j.penerbit || '-');
    document.getElementById('modalAkreditasi').textContent = '🏆 ' + (j.akreditasi || 'Belum Terakreditasi');
    document.getElementById('modalDeskripsi').textContent = j.deskripsi || 'Deskripsi belum tersedia.';
    
    let info = '';
    if (j.issn) info += 'ISSN: ' + j.issn + '<br>';
    if (j.url) info += 'URL: <a href="' + j.url + '" target="_blank" style="color: var(--primary);">' + j.url + '</a><br>';
    info += 'Status: ' + j.status;
    document.getElementById('modalInfo').innerHTML = info;
    
    document.getElementById('jurnalModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeJurnalModal() {
    document.getElementById('jurnalModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeJurnalModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>