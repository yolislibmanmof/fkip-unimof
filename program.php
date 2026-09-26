<?php
require_once __DIR__ . '/includes/config.php';

// ===== AMBIL DATA LANGSUNG DARI DATABASE =====
// Mengambil hanya yang status='Aktif', diurutkan berdasarkan kolom 'urutan' lalu 'id'
$stmt = $pdo->query("SELECT * FROM program_studi WHERE status = 'Aktif' ORDER BY urutan ASC, id ASC");
$prodi = $stmt->fetchAll();

$page_title = 'Program Studi';
$page_description = count($prodi) . ' Program Studi unggulan FKIP UNIMOF dengan akreditasi terbaik';

// Warna unik per prodi (gradient)
$prodi_colors = [
    0 => ['#3b82f6', '#1d4ed8'], // Biru
    1 => ['#8b5cf6', '#6d28d9'], // Ungu
    2 => ['#10b981', '#059669'], // Hijau
    3 => ['#f59e0b', '#d97706'], // Kuning
    4 => ['#ec4899', '#db2777'], // Pink
    5 => ['#ef4444', '#dc2626'], // Merah
    6 => ['#14b8a6', '#0d9488'], // Teal
    7 => ['#f97316', '#ea580c'], // Orange
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Hero Enhancement */
.program-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, #0a6847 0%, #084d35 50%, #16213e 100%);
    color: white; padding: 8rem 0 5rem;
}
.program-hero::before {
    content: ''; position: absolute; inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(245,166,35,0.25) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,0.2) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(16,185,129,0.15) 0%, transparent 60%);
    animation: auroraShift 20s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.1); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.program-hero::after {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px;
    pointer-events: none;
}
.program-hero .container { position: relative; z-index: 2; }
.program-hero .breadcrumb a, .program-hero .breadcrumb span { color: rgba(255,255,255,0.75); }
.program-hero .breadcrumb a:hover { color: white; }
.program-hero .page-title {
    font-family: var(--font-display); font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;
}
.program-hero .page-subtitle {
    font-size: 1.2rem; opacity: 0.9; max-width: 700px; line-height: 1.7;
}

/* Floating Particles */
.hero-particles {
    position: absolute; inset: 0; overflow: hidden; pointer-events: none;
}
.hero-particle {
    position: absolute; width: 4px; height: 4px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: particleFloat 20s infinite linear;
}
@keyframes particleFloat {
    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
    10% { opacity: 0.8; }
    90% { opacity: 0.8; }
    100% { transform: translateY(-10vh) translateX(50px); opacity: 0; }
}

/* Stats Bar */
.stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -3rem 0 3rem; position: relative; z-index: 3;
}
.stat-item {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-md); transition: all 0.3s;
}
.stat-item:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.stat-number {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; line-height: 1; margin-bottom: 0.5rem;
}
.stat-label { font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; }

/* Filter Section */
.filter-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-sm);
}
.filter-row {
    display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.search-wrap {
    flex: 1; min-width: 250px; position: relative;
}
.search-icon {
    position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); pointer-events: none; font-size: 1.1rem;
}
.search-input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary);
    transition: all 0.3s;
}
.search-input:focus {
    outline: none; border-color: var(--primary); background: var(--bg-primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.filter-chips {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.filter-chip {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary);
    font-size: 0.85rem; font-weight: 600; cursor: pointer;
    transition: all 0.3s; user-select: none;
}
.filter-chip:hover { border-color: var(--primary); color: var(--primary); }
.filter-chip.active {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10,104,71,0.25);
}

/* Programs Grid */
.programs-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}

/* Program Card Premium */
.program-card {
    position: relative; background: var(--bg-primary);
    border: 2px solid var(--border); border-radius: var(--radius-xl);
    padding: 2rem; overflow: hidden; cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex; flex-direction: column;
}
.program-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--card-color-1), var(--card-color-2));
    transform: scaleX(0); transition: transform 0.4s;
    transform-origin: left;
}
.program-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    border-color: var(--card-color-1);
}
.program-card:hover::before { transform: scaleX(1); }

.program-number {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--bg-tertiary); line-height: 1; margin-bottom: 1rem;
    transition: color 0.3s;
}
.program-card:hover .program-number {
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}

.program-icon {
    font-size: 3rem; margin-bottom: 1rem;
    transition: transform 0.3s;
}
.program-card:hover .program-icon { transform: scale(1.2) rotate(-10deg); }

.program-content { flex: 1; display: flex; flex-direction: column; }

.program-badge {
    display: inline-block; padding: 0.35rem 0.85rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    margin-bottom: 1rem; width: fit-content;
}
.badge-unggul { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.badge-baik-sekali { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }
.badge-baik { background: linear-gradient(135deg, #cd7f32, #b87333); color: white; }
.badge-terakreditasi { background: var(--bg-secondary); color: var(--text-secondary); }

.program-title {
    font-family: var(--font-display); font-size: 1.35rem; font-weight: 800;
    margin-bottom: 0.5rem; line-height: 1.3; color: var(--text-primary);
    transition: color 0.3s;
}
.program-card:hover .program-title { color: var(--card-color-1); }

.program-level {
    font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem;
    font-weight: 600;
}

.program-stats {
    display: flex; gap: 1rem; margin-bottom: 1.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.program-stat {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.8rem; color: var(--text-secondary);
}
.program-stat strong {
    font-size: 1.1rem; color: var(--text-primary); font-weight: 800;
}

.program-link {
    display: inline-flex; align-items: center; gap: 0.5rem;
    color: var(--primary); font-weight: 700; font-size: 0.95rem;
    text-decoration: none; transition: all 0.3s;
    margin-top: auto;
}
.program-link:hover { gap: 0.8rem; }
.program-link svg { transition: transform 0.3s; }
.program-card:hover .program-link svg { transform: translateX(4px); }

/* Quick View Modal */
.quick-view-modal {
    position: fixed; inset: 0; background: rgba(15,23,42,0.8);
    backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center;
    z-index: 10000; padding: 2rem;
}
.quick-view-modal.show { display: flex; }
.quick-view-box {
    background: var(--bg-primary); border-radius: var(--radius-xl);
    max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto;
    position: relative; animation: modalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalPop {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.quick-view-header {
    padding: 2rem; background: linear-gradient(135deg, var(--modal-color-1), var(--modal-color-2));
    color: white; position: relative;
}
.quick-view-close {
    position: absolute; top: 1rem; right: 1rem;
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,0.2); border: none; color: white;
    font-size: 1.2rem; cursor: pointer; transition: all 0.2s;
}
.quick-view-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
.quick-view-header h2 {
    font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem;
}
.quick-view-header p { opacity: 0.9; font-size: 0.95rem; }
.quick-view-body { padding: 2rem; }
.quick-view-info { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
.quick-view-info-item {
    padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md);
}
.quick-view-info-item strong {
    display: block; font-size: 0.75rem; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;
}
.quick-view-info-item span { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.quick-view-actions { display: flex; gap: 1rem; }
.quick-view-actions .btn { flex: 1; justify-content: center; }

/* Empty State */
.empty-state-pro {
    text-align: center; padding: 4rem 2rem; background: var(--bg-secondary);
    border-radius: var(--radius-xl); border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

@media (max-width: 768px) {
    .programs-grid { grid-template-columns: 1fr; }
    .filter-row { flex-direction: column; align-items: stretch; }
    .quick-view-info { grid-template-columns: 1fr; }
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- ===== HERO SECTION ===== -->
<section class="program-hero">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container">
        <nav class="breadcrumb" data-aos="fade-down">
            <a href="<?= base_url() ?>">Beranda</a><span>›</span><span>Program Studi</span>
        </nav>
        <div class="page-hero-content" data-aos="fade-up">
            <span class="page-badge">🎓 <?= count($prodi) ?> Program Studi Terakreditasi</span>
            <h1 class="page-title">Pilih Jalur Pendidikanmu</h1>
            <p class="page-subtitle">Temukan program studi yang sesuai dengan passion dan tujuan karirmu. Semua program kami terakreditasi dan siap membentukmu menjadi pendidik profesional.</p>
        </div>
    </div>
</section>

<!-- ===== STATS BAR ===== -->
<div class="container">
    <div class="stats-bar" data-aos="fade-up">
        <div class="stat-item">
            <div class="stat-number" data-count="<?= count($prodi) ?>">0</div>
            <div class="stat-label">Program Studi</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="1250">0</div>
            <div class="stat-label">Mahasiswa Aktif</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="68">0</div>
            <div class="stat-label">Dosen Berkualitas</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" data-count="100">0</div>
            <div class="stat-label">% Terakreditasi</div>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Filter Section -->
        <div class="filter-section" data-aos="fade-up">
            <div class="filter-row">
                <div class="search-wrap">
                    <span class="search-icon"></span>
                    <input type="text" id="searchInput" class="search-input" 
                        placeholder="Cari program studi...">
                </div>
                <div class="filter-chips">
                    <button class="filter-chip active" data-filter="all">Semua</button>
                    <button class="filter-chip" data-filter="S1">S1</button>
                    <button class="filter-chip" data-filter="Unggul">Unggul</button>
                    <button class="filter-chip" data-filter="Baik Sekali">Baik Sekali</button>
                </div>
            </div>
        </div>

        <!-- Programs Grid -->
        <div class="programs-grid" id="programsGrid">
            <?php foreach ($prodi as $i => $p): 
                $colors = $prodi_colors[$i % count($prodi_colors)];
                $badge_class = 'badge-' . strtolower(str_replace(' ', '-', $p['akreditasi']));
            ?>
            <article class="program-card" 
                data-aos="fade-up" 
                data-aos-delay="<?= ($i % 4) * 100 ?>"
                data-nama="<?= strtolower(sanitize($p['nama'])) ?>"
                data-jenjang="<?= sanitize($p['jenjang']) ?>"
                data-akreditasi="<?= sanitize($p['akreditasi']) ?>"
                style="--card-color-1: <?= $colors[0] ?>; --card-color-2: <?= $colors[1] ?>;"
                onclick="openQuickView(<?= $p['id'] ?>, <?= json_encode(sanitize($p['nama'])) ?>, <?= json_encode(sanitize($p['jenjang'])) ?>, <?= json_encode(sanitize($p['akreditasi'])) ?>, <?= json_encode(sanitize($p['singkatan'])) ?>, <?= json_encode($colors[0]) ?>, <?= json_encode($colors[1]) ?>)">
                
                <div class="program-number"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="program-icon"><?= $icons[$i] ?? '🎓' ?></div>
                
                <div class="program-content">
                    <div class="program-badge <?= $badge_class ?>"><?= sanitize($p['akreditasi']) ?></div>
                    <h3 class="program-title"><?= sanitize($p['nama']) ?></h3>
                    <p class="program-level"><?= sanitize($p['jenjang']) ?> • <?= sanitize($p['singkatan']) ?></p>
                    
                    <div class="program-stats">
                        <div class="program-stat">
                            <span>👨‍🏫</span>
                            <strong><?= $p['jumlah_dosen'] ?? 15 ?></strong>
                            <span>Dosen</span>
                        </div>
                        <div class="program-stat">
                            <span>👨‍🎓</span>
                            <strong><?= $p['jumlah_mahasiswa'] ?? 150 ?></strong>
                            <span>Mahasiswa</span>
                        </div>
                    </div>
                    
                    <a href="<?= base_url('program-detail.php?id=' . $p['id']) ?>" class="program-link" onclick="event.stopPropagation()">
                        Detail Program
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Empty State (hidden by default) -->
        <div class="empty-state-pro" id="emptyState" style="display: none;">
            <div class="empty-icon-lg">🔍</div>
            <h3>Tidak ada program studi ditemukan</h3>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Coba ubah kata kunci pencarian atau filter.</p>
        </div>
    </div>
</section>

<!-- ===== QUICK VIEW MODAL ===== -->
<div class="quick-view-modal" id="quickViewModal" onclick="if(event.target===this)closeQuickView()">
    <div class="quick-view-box">
        <div class="quick-view-header" id="quickViewHeader">
            <button class="quick-view-close" onclick="closeQuickView()">✕</button>
            <h2 id="qvTitle">Nama Program</h2>
            <p id="qvSubtitle">Jenjang • Singkatan</p>
        </div>
        <div class="quick-view-body">
            <div class="quick-view-info">
                <div class="quick-view-info-item">
                    <strong>Jenjang</strong>
                    <span id="qvJenjang">S1</span>
                </div>
                <div class="quick-view-info-item">
                    <strong>Akreditasi</strong>
                    <span id="qvAkreditasi">Unggul</span>
                </div>
                <div class="quick-view-info-item">
                    <strong>Kode Prodi</strong>
                    <span id="qvKode">PMAT</span>
                </div>
                <div class="quick-view-info-item">
                    <strong>Status</strong>
                    <span style="color: var(--primary);">Aktif</span>
                </div>
            </div>
            <p style="color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                Program studi ini dirancang untuk menghasilkan lulusan yang kompeten di bidangnya, dengan kurikulum berbasis KKNI dan dukungan dosen berkualifikasi tinggi.
            </p>
            <div class="quick-view-actions">
                <a href="#" id="qvDetailLink" class="btn btn-primary">
                    Lihat Detail Lengkap →
                </a>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-secondary">
                    Tanya Info
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ===== INTERACTIVE SCRIPTS ===== -->
<script>
// Generate particles
(function() {
    const container = document.getElementById('heroParticles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const p = document.createElement('div');
        p.className = 'hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 20 + 's';
        p.style.animationDuration = (15 + Math.random() * 15) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        container.appendChild(p);
    }
})();

// Counter animation
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

// Search & Filter
const searchInput = document.getElementById('searchInput');
const filterChips = document.querySelectorAll('.filter-chip');
const programCards = document.querySelectorAll('.program-card');
const emptyState = document.getElementById('emptyState');
let currentFilter = 'all';

function filterPrograms() {
    const query = searchInput.value.toLowerCase();
    let visibleCount = 0;
    
    programCards.forEach(card => {
        const nama = card.dataset.nama;
        const jenjang = card.dataset.jenjang;
        const akreditasi = card.dataset.akreditasi;
        
        const matchSearch = !query || nama.includes(query);
        const matchFilter = currentFilter === 'all' || 
                           (currentFilter === 'S1' && jenjang === 'S1') ||
                           (currentFilter === 'Unggul' && akreditasi === 'Unggul') ||
                           (currentFilter === 'Baik Sekali' && akreditasi === 'Baik Sekali');
        
        if (matchSearch && matchFilter) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
}

searchInput.addEventListener('input', filterPrograms);

filterChips.forEach(chip => {
    chip.addEventListener('click', function() {
        filterChips.forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        filterPrograms();
    });
});

// Quick View Modal
function openQuickView(id, nama, jenjang, akreditasi, singkatan, color1, color2) {
    document.getElementById('qvTitle').textContent = nama;
    document.getElementById('qvSubtitle').textContent = `${jenjang} • ${singkatan}`;
    document.getElementById('qvJenjang').textContent = jenjang;
    document.getElementById('qvAkreditasi').textContent = akreditasi;
    document.getElementById('qvKode').textContent = singkatan;
    document.getElementById('qvDetailLink').href = `<?= base_url('program-detail.php?id=') ?>${id}`;
    
    const header = document.getElementById('quickViewHeader');
    header.style.setProperty('--modal-color-1', color1);
    header.style.setProperty('--modal-color-2', color2);
    header.style.background = `linear-gradient(135deg, ${color1}, ${color2})`;
    
    document.getElementById('quickViewModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeQuickView() {
    document.getElementById('quickViewModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeQuickView();
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        searchInput.focus();
    }
});

console.log('%c🎓 Program Studi FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cShortcut: Ctrl+/ untuk fokus pencarian', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>