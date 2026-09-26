<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Fasilitas & Laboratorium';
$page_description = 'Sarana dan prasarana modern FKIP UNIMOF untuk mendukung kegiatan belajar mengajar dan penelitian.';

// Ambil data fasilitas
$stmt = $pdo->query("SELECT * FROM fasilitas WHERE status = 'Aktif' ORDER BY created_at DESC");
$fasilitas_list = $stmt->fetchAll();

// Statistik
$stat_total = count($fasilitas_list);
$stat_lab = count(array_filter($fasilitas_list, fn($f) => $f['kategori'] === 'Laboratorium'));
$stat_kelas = count(array_filter($fasilitas_list, fn($f) => $f['kategori'] === 'Ruang Kelas'));
$stat_kapasitas = (int)array_sum(array_column($fasilitas_list, 'kapasitas'));

// Kategori unik
$kategoris = array_unique(array_filter(array_column($fasilitas_list, 'kategori')));

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO ===== */
.fasilitas-hero-extreme {
    position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.fasilitas-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(16,185,129,0.25) 0%, transparent 50%),
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

/* ===== STATS ===== */
.fasilitas-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10;
}
.fasilitas-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.fasilitas-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.fasilitas-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.fasilitas-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.fasilitas-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.fasilitas-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== TOOLBAR ===== */
.fasilitas-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.fasilitas-filter-chip {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.85rem;
    font-weight: 600; cursor: pointer; transition: all 0.3s;
}
.fasilitas-filter-chip:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.fasilitas-filter-chip.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

/* ===== FASILITAS GRID ===== */
.fasilitas-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.fasilitas-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); overflow: hidden;
    transition: all 0.4s; display: flex; flex-direction: column;
}
.fasilitas-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary);
}
.fasilitas-image {
    height: 220px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    font-size: 4rem; position: relative; overflow: hidden;
}
.fasilitas-image img {
    width: 100%; height: 100%; object-fit: cover;
    transition: transform 0.6s;
}
.fasilitas-card:hover .fasilitas-image img { transform: scale(1.08); }
.fasilitas-kategori-badge {
    position: absolute; top: 1rem; left: 1rem;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
    padding: 0.4rem 1rem; border-radius: 999px;
    font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.fasilitas-content { padding: 1.75rem; flex: 1; display: flex; flex-direction: column; }
.fasilitas-title {
    font-family: var(--font-display); font-size: 1.25rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.3;
}
.fasilitas-desc {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.fasilitas-meta {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 1rem; border-top: 1px solid var(--border);
}
.fasilitas-kapasitas {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.85rem; font-weight: 700; color: var(--primary);
}
.btn-detail-fasilitas {
    padding: 0.5rem 1rem; background: var(--bg-secondary);
    color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s;
}
.btn-detail-fasilitas:hover { background: var(--primary); color: white; border-color: var(--primary); }

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
.modal-image-ext {
    height: 300px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center; font-size: 5rem;
    position: relative; overflow: hidden;
}
.modal-image-ext img { width: 100%; height: 100%; object-fit: cover; }
.modal-body-ext { padding: 2.5rem; }
.modal-title-ext {
    font-family: var(--font-display); font-size: 1.75rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 1rem;
}
.modal-meta-ext { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
.modal-meta-item {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 1rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
}
.modal-section-ext { margin-bottom: 2rem; }
.modal-section-ext h4 {
    font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;
    font-weight: 700;
}
.modal-section-ext p { font-size: 1rem; color: var(--text-primary); line-height: 1.8; text-align: justify; }

/* ===== CTA ===== */
.fasilitas-cta {
    background: linear-gradient(135deg, var(--primary) 0%, #064e34 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.fasilitas-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.fasilitas-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY ===== */
.empty-fasilitas-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 968px) {
    .fasilitas-stats-bar { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .fasilitas-hero-extreme { padding: 8rem 0 4rem; }
    .fasilitas-stats-bar { grid-template-columns: 1fr; margin: -3rem 1rem 2rem; }
    .fasilitas-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO ===== -->
<section class="fasilitas-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container" style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Fasilitas</span>
        </nav>
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.25rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1.5rem;" data-aos="fade-down" data-aos-delay="100">
            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; position: relative;">
                <span style="position: absolute; inset: 0; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span>
            </span>
            <span>Modern Learning Environment</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Fasilitas & <span style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Laboratorium</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Sarana dan prasarana modern yang mendukung kegiatan belajar mengajar, penelitian, dan pengembangan kompetensi mahasiswa FKIP UNIMOF.
        </p>
    </div>
</section>

<!-- ===== MAIN ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats -->
        <div class="fasilitas-stats-bar" data-aos="fade-up">
            <div class="fasilitas-stat-card" style="--stat-color: #3b82f6;">
                <div class="fasilitas-stat-icon">🏢</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="fasilitas-stat-label">Total Fasilitas</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #ef4444;">
                <div class="fasilitas-stat-icon">🧪</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_lab ?>">0</div>
                <div class="fasilitas-stat-label">Laboratorium</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #f59e0b;">
                <div class="fasilitas-stat-icon">🏫</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_kelas ?>">0</div>
                <div class="fasilitas-stat-label">Ruang Kelas</div>
            </div>
            <div class="fasilitas-stat-card" style="--stat-color: #10b981;">
                <div class="fasilitas-stat-icon">👥</div>
                <div class="fasilitas-stat-num count-up" data-target="<?= $stat_kapasitas ?>">0</div>
                <div class="fasilitas-stat-label">Total Kapasitas</div>
            </div>
        </div>

        <?php if (empty($fasilitas_list)): ?>
            <div class="empty-fasilitas-ext" data-aos="fade-up">
                <div class="empty-icon-lg">🏢</div>
                <h3>Belum ada data fasilitas</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Informasi fasilitas akan segera ditampilkan di sini.</p>
            </div>
        <?php else: ?>
            <!-- Filter -->
            <div class="fasilitas-toolbar" data-aos="fade-up">
                <button class="fasilitas-filter-chip active" onclick="filterFasilitas('all', this)">🏢 Semua</button>
                <?php foreach ($kategoris as $k): 
                    $icon = $k === 'Laboratorium' ? '🧪' : ($k === 'Ruang Kelas' ? '🏫' : ($k === 'Perpustakaan' ? '📚' : '🏢'));
                ?>
                <button class="fasilitas-filter-chip" onclick="filterFasilitas('<?= sanitize($k) ?>', this)"><?= $icon ?> <?= sanitize($k) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Grid -->
            <div class="fasilitas-grid" id="fasilitasGrid" data-aos="fade-up">
                <?php foreach ($fasilitas_list as $f): 
                    $icon = $f['kategori'] === 'Laboratorium' ? '🧪' : ($f['kategori'] === 'Perpustakaan' ? '📚' : ($f['kategori'] === 'Ruang Kelas' ? '🏫' : '🏢'));
                ?>
                <article class="fasilitas-card" data-kategori="<?= sanitize($f['kategori']) ?>">
                    <div class="fasilitas-image">
                        <?php if (!empty($f['gambar'])): ?>
                            <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>" loading="lazy">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                        <span class="fasilitas-kategori-badge"><?= sanitize($f['kategori']) ?></span>
                    </div>
                    <div class="fasilitas-content">
                        <h3 class="fasilitas-title"><?= sanitize($f['nama']) ?></h3>
                        <p class="fasilitas-desc"><?= excerpt($f['deskripsi'] ?? 'Fasilitas FKIP UNIMOF.', 120) ?></p>
                        <div class="fasilitas-meta">
                            <span class="fasilitas-kapasitas">👥 Kapasitas: <?= $f['kapasitas'] ?> orang</span>
                            <button class="btn-detail-fasilitas" onclick='openFasilitasModal(<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Detail</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CTA -->
        <div class="fasilitas-cta" data-aos="zoom-in">
            <div class="fasilitas-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Ingin Melihat Langsung?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Kunjungi kampus kami dan rasakan sendiri fasilitas modern yang siap mendukung perjalanan akademik Anda.
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📅 Jadwalkan Kunjungan
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL ===== -->
<div class="modal-overlay-ext" id="fasilitasModal" onclick="if(event.target===this)closeFasilitasModal()">
    <div class="modal-content-ext">
        <button class="modal-close-ext" onclick="closeFasilitasModal()">✕</button>
        <div class="modal-image-ext" id="modalImage">
            <span id="modalIcon"></span>
        </div>
        <div class="modal-body-ext">
            <h2 class="modal-title-ext" id="modalTitle"></h2>
            <div class="modal-meta-ext">
                <span class="modal-meta-item" id="modalKategori"></span>
                <span class="modal-meta-item" id="modalKapasitas"></span>
                <span class="modal-meta-item" id="modalStatus"></span>
            </div>
            <div class="modal-section-ext">
                <h4>📝 Deskripsi Lengkap</h4>
                <p id="modalDeskripsi"></p>
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
const fasilitasCards = document.querySelectorAll('.fasilitas-card');
let currentKategori = 'all';

function filterFasilitas(kategori, btn) {
    currentKategori = kategori;
    document.querySelectorAll('.fasilitas-filter-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    let visibleCount = 0;
    fasilitasCards.forEach(card => {
        const match = kategori === 'all' || card.dataset.kategori === kategori;
        card.style.display = match ? 'flex' : 'none';
        if (match) visibleCount++;
    });

    const grid = document.getElementById('fasilitasGrid');
    let emptyEl = grid.querySelector('.empty-filter-state');
    if (visibleCount === 0 && fasilitasCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-fasilitas-ext empty-filter-state';
            emptyEl.innerHTML = '<div class="empty-icon-lg">🔍</div><h3>Tidak ada fasilitas di kategori ini</h3><p style="color: var(--text-muted); margin-top: 0.5rem;">Coba pilih kategori lain.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

// Modal
function openFasilitasModal(f) {
    const icons = {'Laboratorium': '🧪', 'Perpustakaan': '📚', 'Ruang Kelas': '🏫', 'Fasilitas Umum': '🏢', 'Lainnya': '🏢'};
    const icon = icons[f.kategori] || '';
    
    const imageEl = document.getElementById('modalImage');
    if (f.gambar) {
        imageEl.innerHTML = '<img src="<?= asset("uploads/fasilitas/") ?>' + f.gambar + '" alt="' + f.nama + '">';
    } else {
        imageEl.innerHTML = '<span style="font-size: 5rem;">' + icon + '</span>';
    }
    
    document.getElementById('modalTitle').textContent = f.nama;
    document.getElementById('modalKategori').textContent = '🏷️ ' + f.kategori;
    document.getElementById('modalKapasitas').textContent = '👥 Kapasitas: ' + f.kapasitas + ' orang';
    document.getElementById('modalStatus').textContent = '✅ ' + f.status;
    document.getElementById('modalDeskripsi').textContent = f.deskripsi || 'Deskripsi belum tersedia.';
    
    document.getElementById('fasilitasModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeFasilitasModal() {
    document.getElementById('fasilitasModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeFasilitasModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>