<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Prestasi Mahasiswa';
$page_description = 'Deretan prestasi gemilang mahasiswa FKIP UNIMOF di tingkat regional, nasional, dan internasional.';

// Ambil data prestasi
$stmt = $pdo->query("SELECT pr.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan FROM prestasi pr LEFT JOIN program_studi p ON pr.program_studi_id = p.id ORDER BY pr.tahun DESC, FIELD(pr.tingkat, 'Internasional', 'Nasional', 'Wilayah', 'Universitas') ASC");
$prestasi_list = $stmt->fetchAll();

// Statistik Dinamis
$total_prestasi = count($prestasi_list);
$int_count = count(array_filter($prestasi_list, fn($p) => $p['tingkat'] === 'Internasional'));
$nas_count = count(array_filter($prestasi_list, fn($p) => $p['tingkat'] === 'Nasional'));
$wil_count = count(array_filter($prestasi_list, fn($p) => $p['tingkat'] === 'Wilayah'));

// Daftar tahun unik untuk filter
$years = array_unique(array_column($prestasi_list, 'tahun'));
rsort($years);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO EXTREME ===== */
.prestasi-hero-extreme {
    position: relative; background: linear-gradient(135deg, #f59e0b 0%, #d97706 40%, #92400e 100%);
    color: white; padding: 8rem 0 6rem; text-align: center; overflow: hidden;
}
.prestasi-hero-extreme::before {
    content: '🏆'; position: absolute; font-size: 25rem; opacity: 0.08;
    top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;
    animation: floatTrophy 6s ease-in-out infinite;
}
@keyframes floatTrophy { 0%, 100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-50%, -55%) scale(1.05); } }

/* ===== STATS BAR ===== */
.prestasi-stats-bar {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10;
}
.prestasi-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.prestasi-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--stat-color, var(--secondary));
}
.prestasi-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.prestasi-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.prestasi-stat-num { font-family: var(--font-display); font-size: 2.75rem; font-weight: 900; color: var(--stat-color, var(--secondary)); line-height: 1; margin-bottom: 0.5rem; }
.prestasi-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== FILTER TOOLBAR ===== */
.filter-toolbar {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: center;
}
.filter-chip {
    padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.85rem;
    font-weight: 600; cursor: pointer; transition: all 0.3s;
}
.filter-chip:hover { border-color: var(--secondary); color: var(--secondary); transform: translateY(-2px); }
.filter-chip.active { background: var(--secondary); color: white; border-color: var(--secondary); box-shadow: 0 4px 12px rgba(245,166,35,0.3); }

/* ===== TIMELINE EXTREME ===== */
.timeline-extreme { max-width: 1000px; margin: 0 auto; position: relative; padding: 2rem 0; }
.timeline-extreme::before {
    content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, var(--secondary), var(--primary), transparent);
    transform: translateX(-50%); border-radius: 4px;
}

.timeline-item-extreme { display: flex; justify-content: flex-end; padding-right: 50%; position: relative; margin-bottom: 3rem; }
.timeline-item-extreme:nth-child(even) { justify-content: flex-start; padding-right: 0; padding-left: 50%; }

.timeline-dot-extreme {
    position: absolute; left: 50%; top: 1.5rem; width: 28px; height: 28px;
    background: var(--bg-primary); border: 4px solid var(--secondary);
    border-radius: 50%; transform: translateX(-50%); z-index: 2;
    box-shadow: 0 0 0 6px rgba(245,166,35,0.15); transition: all 0.3s;
}
.timeline-item-extreme:hover .timeline-dot-extreme { transform: translateX(-50%) scale(1.2); box-shadow: 0 0 0 8px rgba(245,166,35,0.25); }

.timeline-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; margin: 0 2.5rem;
    position: relative; transition: all 0.4s; width: 100%; max-width: 450px;
}
.timeline-card-extreme:hover {
    transform: translateY(-8px) scale(1.02); box-shadow: var(--shadow-xl);
    border-color: var(--secondary);
}
.timeline-card-extreme::before {
    content: ''; position: absolute; top: 1.5rem; width: 20px; height: 20px;
    background: var(--bg-primary); border: 1px solid var(--border); transform: rotate(45deg);
}
.timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before { right: -11px; border-left: none; border-bottom: none; }
.timeline-item-extreme:nth-child(even) .timeline-card-extreme::before { left: -11px; border-right: none; border-top: none; }

.tingkat-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem;
}
.badge-internasional { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-nasional { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-wilayah { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-universitas { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; border: 1px solid #d1d5db; }

.prestasi-year-extreme {
    font-family: var(--font-display); font-size: 2rem; font-weight: 900;
    color: var(--secondary); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;
}
.prestasi-title-extreme { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 1rem; line-height: 1.4; }
.prestasi-meta-extreme { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.meta-pill {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.85rem;
    background: var(--bg-secondary); border-radius: 999px; font-size: 0.85rem;
    color: var(--text-secondary); font-weight: 600;
}

/* ===== CTA SECTION ===== */
.prestasi-cta {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.prestasi-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.prestasi-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY STATE ===== */
.empty-state-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .timeline-extreme::before { left: 24px; }
    .timeline-item-extreme, .timeline-item-extreme:nth-child(even) {
        justify-content: flex-start; padding-left: 70px; padding-right: 0;
    }
    .timeline-dot-extreme { left: 24px; }
    .timeline-card-extreme { margin: 0; max-width: 100%; }
    .timeline-item-extreme:nth-child(odd) .timeline-card-extreme::before,
    .timeline-item-extreme:nth-child(even) .timeline-card-extreme::before {
        left: -11px; border-right: none; border-top: none;
    }
}
@media (max-width: 640px) {
    .prestasi-stats-bar { grid-template-columns: repeat(2, 1fr); margin: -3rem 1rem 2rem; }
    .filter-toolbar { flex-direction: column; align-items: stretch; }
    .filter-chip { text-align: center; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="prestasi-hero-extreme">
    <div class="container" style="position: relative; z-index: 2;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Prestasi Mahasiswa</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Hall of Fame 🏆</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto;" data-aos="fade-up" data-aos-delay="100">
            Membanggakan! Berikut adalah deretan pencapaian gemilang mahasiswa FKIP UNIMOF yang mengharumkan nama almamater di berbagai kompetisi.
        </p>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <!-- Stats Bar -->
        <div class="prestasi-stats-bar">
            <div class="prestasi-stat-card" style="--stat-color: #f59e0b;" data-aos="fade-up" data-aos-delay="0">
                <div class="prestasi-stat-icon">🏆</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $total_prestasi ?>">0</div>
                <div class="prestasi-stat-label">Total Prestasi</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #92400e;" data-aos="fade-up" data-aos-delay="100">
                <div class="prestasi-stat-icon">🌍</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $int_count ?>">0</div>
                <div class="prestasi-stat-label">Internasional</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #1e40af;" data-aos="fade-up" data-aos-delay="200">
                <div class="prestasi-stat-icon">🇮🇩</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $nas_count ?>">0</div>
                <div class="prestasi-stat-label">Nasional</div>
            </div>
            <div class="prestasi-stat-card" style="--stat-color: #166534;" data-aos="fade-up" data-aos-delay="300">
                <div class="prestasi-stat-icon">🏛️</div>
                <div class="prestasi-stat-num count-up" data-target="<?= $wil_count ?>">0</div>
                <div class="prestasi-stat-label">Wilayah/Provinsi</div>
            </div>
        </div>

        <?php if (empty($prestasi_list)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">🏆</div>
                <h3>Belum ada data prestasi</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Prestasi terbaru akan segera diupdate di sini. Tetap semangat berprestasi!</p>
            </div>
        <?php else: ?>
            <!-- Filter Toolbar -->
            <div class="filter-toolbar" data-aos="fade-up">
                <button class="filter-chip active" onclick="filterPrestasi('all', this)">🌟 Semua</button>
                <button class="filter-chip" onclick="filterPrestasi('Internasional', this)">🌍 Internasional</button>
                <button class="filter-chip" onclick="filterPrestasi('Nasional', this)">🇮🇩 Nasional</button>
                <button class="filter-chip" onclick="filterPrestasi('Wilayah', this)">🏛️ Wilayah</button>
                <?php if (count($years) > 1): ?>
                <select class="filter-chip" style="appearance: none; padding-right: 2rem; cursor: pointer;" onchange="filterPrestasiYear(this.value)">
                    <option value="all">Semua Tahun</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Timeline -->
            <div class="timeline-extreme" id="prestasiTimeline">
                <?php foreach ($prestasi_list as $index => $p): 
                    $tingkat = $p['tingkat'] ?? 'Nasional';
                    $badge_class = 'badge-' . strtolower($tingkat);
                    $icon = $tingkat === 'Internasional' ? '🌍' : ($tingkat === 'Nasional' ? '🇮🇩' : ($tingkat === 'Wilayah' ? '🏛️' : '🏫'));
                ?>
                <div class="timeline-item-extreme" data-tingkat="<?= $tingkat ?>" data-tahun="<?= $p['tahun'] ?>" data-aos="fade-up" data-aos-delay="<?= ($index % 2) * 100 ?>">
                    <div class="timeline-dot-extreme"></div>
                    <div class="timeline-card-extreme">
                        <span class="tingkat-badge-extreme <?= $badge_class ?>"><?= $icon ?> <?= strtoupper($tingkat) ?></span>
                        <div class="prestasi-year-extreme"><?= sanitize($p['tahun']) ?></div>
                        <h3 class="prestasi-title-extreme"><?= sanitize($p['judul']) ?></h3>
                        <div class="prestasi-meta-extreme">
                            <span class="meta-pill">👤 <?= sanitize($p['mahasiswa']) ?></span>
                            <span class="meta-pill">🎓 <?= sanitize($p['prodi_singkatan'] ?? $p['prodi_nama'] ?? 'Umum') ?></span>
                            <span class="meta-pill">🥇 <?= sanitize($p['juara']) ?></span>
                            <?php if (!empty($p['lomba'])): ?>
                                <span class="meta-pill">🏆 <?= sanitize($p['lomba']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CTA Section -->
        <div class="prestasi-cta" data-aos="zoom-in">
            <div class="prestasi-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Punya Prestasi Membanggakan?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Jangan biarkan pencapaianmu tidak tercatat! Laporkan prestasimu ke bagian kemahasiswaan untuk didokumentasikan dan menjadi inspirasi bagi adik tingkat.
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    📩 Laporkan Prestasi Saya
                </a>
            </div>
        </div>
    </div>
</section>

<script>
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
let currentTingkat = 'all';
let currentTahun = 'all';

function filterPrestasi(tingkat, btn) {
    currentTingkat = tingkat;
    
    // Update active button
    document.querySelectorAll('.filter-chip').forEach(chip => {
        if (!chip.tagName.toLowerCase().includes('select')) {
            chip.classList.remove('active');
        }
    });
    if (btn) btn.classList.add('active');
    
    applyFilters();
}

function filterPrestasiYear(tahun) {
    currentTahun = tahun;
    applyFilters();
}

function applyFilters() {
    const items = document.querySelectorAll('.timeline-item-extreme');
    let visibleCount = 0;
    
    items.forEach(item => {
        const matchTingkat = currentTingkat === 'all' || item.dataset.tingkat === currentTingkat;
        const matchTahun = currentTahun === 'all' || item.dataset.tahun === currentTahun;
        
        if (matchTingkat && matchTahun) {
            item.style.display = 'flex';
            visibleCount++;
            // Re-trigger AOS animation
            item.classList.remove('aos-animate');
            setTimeout(() => item.classList.add('aos-animate'), 50);
        } else {
            item.style.display = 'none';
        }
    });
    
    // Show/hide empty state if needed
    const timeline = document.getElementById('prestasiTimeline');
    let emptyState = timeline.querySelector('.empty-filter-state');
    if (visibleCount === 0 && items.length > 0) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-filter-state';
            emptyState.style.cssText = 'text-align: center; padding: 3rem; grid-column: 1 / -1;';
            emptyState.innerHTML = '<div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div><h3>Tidak ada prestasi di filter ini</h3><p style="color: var(--text-muted);">Coba ubah kategori atau tahun pencarian.</p>';
            timeline.appendChild(emptyState);
        }
    } else if (emptyState) {
        emptyState.remove();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>