<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Riset & Publikasi';
$page_description = 'Penelitian dan publikasi ilmiah dosen serta mahasiswa FKIP UNIMOF di berbagai bidang pendidikan dan sains.';

// Ambil data riset
$stmt = $pdo->query("SELECT r.*, p.nama as prodi_nama, p.singkatan as prodi_singkatan, d.nama as ketua_nama 
                     FROM riset r 
                     LEFT JOIN program_studi p ON r.program_studi_id = p.id 
                     LEFT JOIN dosen d ON r.ketua_id = d.id 
                     WHERE r.status = 'Published' 
                     ORDER BY r.tahun DESC, r.created_at DESC");
$riset_list = $stmt->fetchAll();

// Statistik riset
$stat_total = count($riset_list);
$stat_publikasi = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Publikasi'")->fetchColumn();
$stat_hibah = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Hibah'")->fetchColumn();
$stat_pengabdian = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published' AND jenis='Pengabdian'")->fetchColumn();

// Featured research (2 riset terbaru dengan abstrak)
$featured = array_filter($riset_list, fn($r) => !empty($r['abstrak']));
$featured = array_slice($featured, 0, 2);

// Kategori unik
$kategoris = array_unique(array_filter(array_column($riset_list, 'kategori')));

// Tahun unik untuk filter
$years = array_unique(array_column($riset_list, 'tahun'));
rsort($years);

// Data untuk chart (riset per tahun)
$chart_data = [];
foreach ($years as $y) {
    $chart_data[$y] = count(array_filter($riset_list, fn($r) => $r['tahun'] == $y));
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== HERO EXTREME ===== */
.riset-hero-extreme {
    position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%);
    color: white; padding: 10rem 0 6rem; overflow: hidden;
}
.riset-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(139,92,246,0.2) 0%, transparent 60%);
    animation: auroraShift 25s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-30px, 20px) scale(1.05); }
    66% { transform: translate(20px, -30px) scale(0.95); }
}
.riset-hero-extreme::after {
    content: ''; position: absolute; inset: 0;
    background-image: 
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 50px 50px;
    pointer-events: none;
}
.hero-particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.hero-particle {
    position: absolute; width: 3px; height: 3px;
    background: rgba(255,255,255,0.6); border-radius: 50%;
    animation: floatParticle 30s infinite linear;
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

/* ===== STATS BAR ===== */
.riset-stats-extreme {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -4rem 0 3rem; position: relative; z-index: 10;
}
.riset-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.4s; position: relative; overflow: hidden;
}
.riset-stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.riset-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.riset-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.riset-stat-num {
    font-family: var(--font-display); font-size: 3rem; font-weight: 900;
    color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem;
}
.riset-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

/* ===== FEATURED RESEARCH ===== */
.featured-section { margin-bottom: 4rem; }
.featured-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; }
.featured-card {
    background: var(--bg-primary); border: 2px solid var(--border);
    border-radius: var(--radius-xl); padding: 2.5rem; position: relative;
    overflow: hidden; transition: all 0.4s;
}
.featured-card::before {
    content: '⭐ FEATURED'; position: absolute; top: 1.5rem; right: -2.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
    padding: 0.3rem 3rem; font-size: 0.7rem; font-weight: 800;
    transform: rotate(45deg); letter-spacing: 0.1em;
}
.featured-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.featured-type-badge {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem;
}
.featured-year {
    font-family: var(--font-display); font-size: 0.9rem; font-weight: 700;
    color: var(--text-muted); margin-bottom: 0.75rem;
}
.featured-title {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 1rem; line-height: 1.4;
}
.featured-desc {
    color: var(--text-secondary); font-size: 0.95rem; line-height: 1.7;
    margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 4;
    -webkit-box-orient: vertical; overflow: hidden;
}
.featured-meta {
    display: flex; flex-wrap: wrap; gap: 0.75rem; padding-top: 1.5rem;
    border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted);
}
.featured-meta span { display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-read-abstract {
    margin-top: 1.5rem; width: 100%; padding: 0.85rem; background: var(--primary);
    color: white; border: none; border-radius: var(--radius-md); font-weight: 700;
    font-size: 0.95rem; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.btn-read-abstract:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

/* ===== CHART SECTION ===== */
.chart-section {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 3rem;
    box-shadow: var(--shadow-sm);
}
.chart-section h3 {
    font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}

/* ===== TOOLBAR ===== */
.riset-toolbar-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
    box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.riset-search-extreme { flex: 1; min-width: 250px; position: relative; }
.riset-search-extreme input {
    width: 100%; padding: 0.85rem 1rem 0.85rem 3rem;
    border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; transition: all 0.3s;
}
.riset-search-extreme input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.riset-search-extreme .icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }

.riset-filter-extreme {
    padding: 0.85rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md);
    font-family: inherit; font-size: 0.95rem; background: var(--bg-secondary); cursor: pointer;
    transition: all 0.3s;
}
.riset-filter-extreme:focus { outline: none; border-color: var(--primary); }

/* ===== RISET GRID ===== */
.riset-grid-extreme {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;
}
.riset-card-extreme {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    overflow: hidden; transition: all 0.4s; display: flex; flex-direction: column;
}
.riset-card-extreme::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #4c1d95, #1e3a8a);
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.riset-card-extreme:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: #4c1d95;
}
.riset-card-extreme:hover::before { transform: scaleX(1); }

.riset-type-badge-extreme {
    display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; margin-bottom: 1rem; width: fit-content;
}
.type-publikasi-ext { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.type-hibah-ext { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.type-pengabdian-ext { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.type-lainnya-ext { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }

.riset-year-ext {
    font-family: var(--font-display); font-size: 0.85rem; font-weight: 700;
    color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;
}
.riset-title-ext {
    font-family: var(--font-display); font-size: 1.2rem; font-weight: 800;
    color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-desc-ext {
    color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;
    margin-bottom: 1.25rem; flex: 1;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.riset-meta-ext {
    display: flex; flex-wrap: wrap; gap: 0.5rem; padding-top: 1rem;
    border-top: 1px solid var(--border); font-size: 0.8rem; color: var(--text-muted);
}
.riset-meta-ext span { display: inline-flex; align-items: center; gap: 0.3rem; }
.btn-view-abstract {
    margin-top: 1rem; padding: 0.65rem; background: var(--bg-secondary);
    color: var(--text-primary); border: 1px solid var(--border); border-radius: var(--radius-md);
    font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
}
.btn-view-abstract:hover { background: var(--primary); color: white; border-color: var(--primary); }

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
.modal-keywords { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.keyword-tag {
    padding: 0.4rem 0.85rem; background: var(--bg-secondary); border-radius: 999px;
    font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;
}

/* ===== CTA SECTION ===== */
.riset-cta-ext {
    background: linear-gradient(135deg, var(--primary) 0%, #4c1d95 100%);
    border-radius: var(--radius-xl); padding: 4rem 2rem; text-align: center;
    color: white; position: relative; overflow: hidden; margin-top: 4rem;
}
.riset-cta-ext::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.riset-cta-content { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }

/* ===== EMPTY STATE ===== */
.empty-riset-ext {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .featured-grid { grid-template-columns: 1fr; }
    .riset-stats-extreme { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .riset-hero-extreme { padding: 8rem 0 4rem; }
    .riset-stats-extreme { grid-template-columns: 1fr; margin: -3rem 1rem 2rem; }
    .riset-toolbar-extreme { flex-direction: column; align-items: stretch; }
    .riset-filter-extreme { width: 100%; }
    .riset-grid-extreme { grid-template-columns: 1fr; }
}
</style>

<!-- ===== HERO EXTREME ===== -->
<section class="riset-hero-extreme">
    <div class="hero-particles" id="heroParticles"></div>
    <div class="container hero-content-extreme">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Riset & Publikasi</span>
        </nav>
        <div class="hero-badge-extreme" data-aos="fade-down" data-aos-delay="100">
            <span class="hero-badge-pulse"></span>
            <span>Research Excellence Center</span>
        </div>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;" data-aos="fade-up">
            Pusat <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Riset & Publikasi</span>
        </h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; line-height: 1.7;" data-aos="fade-up" data-aos-delay="200">
            Kontribusi ilmiah dosen dan mahasiswa FKIP UNIMOF dalam memajukan ilmu pengetahuan dan teknologi pendidikan melalui penelitian inovatif dan pengabdian masyarakat.
        </p>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats Bar -->
        <div class="riset-stats-extreme" data-aos="fade-up">
            <div class="riset-stat-card" style="--stat-color: #3b82f6;">
                <div class="riset-stat-icon">🔬</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_total ?>">0</div>
                <div class="riset-stat-label">Total Riset</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #10b981;">
                <div class="riset-stat-icon">📚</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_publikasi ?>">0</div>
                <div class="riset-stat-label">Publikasi</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #f59e0b;">
                <div class="riset-stat-icon">💰</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_hibah ?>">0</div>
                <div class="riset-stat-label">Hibah Penelitian</div>
            </div>
            <div class="riset-stat-card" style="--stat-color: #8b5cf6;">
                <div class="riset-stat-icon">🤝</div>
                <div class="riset-stat-num count-up" data-target="<?= $stat_pengabdian ?>">0</div>
                <div class="riset-stat-label">Pengabdian</div>
            </div>
        </div>

        <!-- Featured Research -->
        <?php if (count($featured) > 0): ?>
        <div class="featured-section" data-aos="fade-up">
            <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
                <span class="section-tag">Unggulan</span>
                <h2 class="section-title">Riset <span class="gradient-text">Pilihan</span></h2>
            </div>
            <div class="featured-grid">
                <?php foreach ($featured as $f): 
                    $type_class = 'type-' . strtolower($f['jenis'] ?? 'lainnya') . '-ext';
                    $icon = $f['jenis'] === 'Publikasi' ? '📚' : ($f['jenis'] === 'Hibah' ? '💰' : ($f['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                ?>
                <div class="featured-card">
                    <span class="featured-type-badge <?= $type_class ?>"><?= $icon ?> <?= strtoupper($f['jenis'] ?? 'Riset') ?></span>
                    <div class="featured-year">📅 Tahun <?= sanitize($f['tahun']) ?></div>
                    <h3 class="featured-title"><?= sanitize($f['judul']) ?></h3>
                    <p class="featured-desc"><?= excerpt($f['abstrak'] ?? $f['deskripsi'] ?? '', 200) ?></p>
                    <div class="featured-meta">
                        <span>👤 <?= sanitize($f['ketua_nama'] ?? 'Tim Peneliti') ?></span>
                        <span>🎓 <?= sanitize($f['prodi_singkatan'] ?? $f['prodi_nama'] ?? 'Umum') ?></span>
                        <?php if (!empty($f['kategori'])): ?>
                            <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-read-abstract" onclick='openAbstractModal(<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <span>📖</span> Baca Abstrak Lengkap
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart Section -->
        <div class="chart-section" data-aos="fade-up">
            <h3>📊 Distribusi Riset per Tahun</h3>
            <div id="risetChart"></div>
        </div>

        <!-- Toolbar -->
        <div class="riset-toolbar-extreme" data-aos="fade-up">
            <div class="riset-search-extreme">
                <span class="icon">🔍</span>
                <input type="text" id="risetSearch" placeholder="Cari judul riset, ketua peneliti, atau kata kunci...">
            </div>
            <select class="riset-filter-extreme" id="risetTypeFilter">
                <option value="all">Semua Jenis</option>
                <option value="Publikasi">📚 Publikasi</option>
                <option value="Hibah">💰 Hibah</option>
                <option value="Pengabdian">🤝 Pengabdian</option>
            </select>
            <select class="riset-filter-extreme" id="risetKatFilter">
                <option value="all">Semua Kategori</option>
                <?php foreach ($kategoris as $k): ?>
                    <option value="<?= sanitize($k) ?>"><?= sanitize($k) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (count($years) > 1): ?>
            <select class="riset-filter-extreme" id="risetYearFilter">
                <option value="all">Semua Tahun</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>

        <!-- Riset Grid -->
        <div class="riset-grid-extreme" id="risetGrid" data-aos="fade-up">
            <?php if (empty($riset_list)): ?>
                <div class="empty-riset-ext">
                    <div class="empty-icon-lg">🔬</div>
                    <h3>Belum ada data riset</h3>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">Publikasi dan penelitian akan segera ditampilkan di sini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($riset_list as $r): 
                    // Skip featured items
                    if (in_array($r, $featured)) continue;
                    
                    $type_class = 'type-' . strtolower($r['jenis'] ?? 'lainnya') . '-ext';
                    $icon = $r['jenis'] === 'Publikasi' ? '📚' : ($r['jenis'] === 'Hibah' ? '💰' : ($r['jenis'] === 'Pengabdian' ? '🤝' : '🔬'));
                    $initials = strtoupper(substr($r['ketua_nama'] ?? 'T', 0, 1) . (strpos($r['ketua_nama'] ?? '', ' ') ? substr($r['ketua_nama'], strpos($r['ketua_nama'], ' ') + 1, 1) : ''));
                ?>
                <article class="riset-card-ext" 
                    data-judul="<?= strtolower(sanitize($r['judul'])) ?>"
                    data-ketua="<?= strtolower(sanitize($r['ketua_nama'] ?? '')) ?>"
                    data-jenis="<?= sanitize($r['jenis'] ?? '') ?>"
                    data-kategori="<?= sanitize($r['kategori'] ?? '') ?>"
                    data-tahun="<?= sanitize($r['tahun']) ?>">
                    
                    <span class="riset-type-badge-extreme <?= $type_class ?>"><?= $icon ?> <?= strtoupper($r['jenis'] ?? 'Riset') ?></span>
                    <div class="riset-year-ext">📅 Tahun <?= sanitize($r['tahun']) ?></div>
                    <h3 class="riset-title-ext"><?= sanitize($r['judul']) ?></h3>
                    <p class="riset-desc-ext"><?= excerpt($r['deskripsi'] ?? $r['abstrak'] ?? 'Tidak ada deskripsi tersedia.', 150) ?></p>
                    
                    <div class="riset-meta-ext">
                        <span>👤 <?= sanitize($r['ketua_nama'] ?? 'Tim Peneliti') ?></span>
                        <span>🎓 <?= sanitize($r['prodi_singkatan'] ?? $r['prodi_nama'] ?? 'Umum') ?></span>
                        <?php if (!empty($r['kategori'])): ?>
                            <span>🏷️ <?= sanitize($r['kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($r['abstrak'])): ?>
                    <button class="btn-view-abstract" onclick='openAbstractModal(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        <span>📖</span> Lihat Abstrak
                    </button>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- CTA Section -->
        <div class="riset-cta-ext" data-aos="zoom-in">
            <div class="riset-cta-content">
                <h2 style="font-family: var(--font-display); font-size: clamp(1.75rem, 3vw, 2.5rem); margin-bottom: 1rem;">Tertarik Berkolaborasi dalam Riset?</h2>
                <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.95;">
                    Kami terbuka untuk kerjasama penelitian dengan institusi lain, baik dalam negeri maupun internasional. Mari bersama-sama memajukan ilmu pengetahuan!
                </p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-lg" style="background: white; color: var(--primary); font-weight: 800;">
                    🤝 Hubungi Kami untuk Kolaborasi
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===== MODAL ABSTRAK ===== -->
<div class="modal-overlay-ext" id="abstractModal" onclick="if(event.target===this)closeAbstractModal()">
    <div class="modal-content-ext">
        <button class="modal-close-ext" onclick="closeAbstractModal()">✕</button>
        <div class="modal-header-ext">
            <h2 id="modalTitle"></h2>
            <div class="modal-meta-ext">
                <span id="modalYear"></span>
                <span id="modalType"></span>
                <span id="modalKetua"></span>
                <span id="modalProdi"></span>
            </div>
        </div>
        <div class="modal-body-ext">
            <div class="modal-section-ext">
                <h4>📝 Abstrak</h4>
                <p id="modalAbstract"></p>
            </div>
            <?php if (!empty($r['doi'])): ?>
            <div class="modal-section-ext">
                <h4>🔗 DOI (Digital Object Identifier)</h4>
                <p><a href="https://doi.org/<?= sanitize($r['doi']) ?>" target="_blank" style="color: var(--primary);"><?= sanitize($r['doi']) ?></a></p>
            </div>
            <?php endif; ?>
            <div class="modal-section-ext">
                <h4>🏷️ Kata Kunci</h4>
                <div class="modal-keywords" id="modalKeywords"></div>
            </div>
        </div>
    </div>
</div>

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

// ===== APEXCHARTS =====
const chartData = <?= json_encode($chart_data) ?>;
const chartYears = Object.keys(chartData).sort();
const chartValues = chartYears.map(y => chartData[y]);

new ApexCharts(document.querySelector("#risetChart"), {
    series: [{
        name: 'Jumlah Riset',
        data: chartValues
    }],
    chart: {
        type: 'area',
        height: 300,
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    colors: ['#4c1d95'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 90, 100]
        }
    },
    stroke: { curve: 'smooth', width: 3 },
    dataLabels: { enabled: false },
    xaxis: {
        categories: chartYears,
        labels: { style: { fontSize: '12px', fontWeight: 600 } }
    },
    yaxis: {
        title: { text: 'Jumlah Riset' },
        min: 0,
        tickAmount: 5
    },
    grid: { borderColor: '#f1f5f9' },
    tooltip: {
        y: { formatter: (val) => val + ' riset' }
    }
}).render();

// ===== FILTER FUNCTIONALITY =====
const risetSearch = document.getElementById('risetSearch');
const risetTypeFilter = document.getElementById('risetTypeFilter');
const risetKatFilter = document.getElementById('risetKatFilter');
const risetYearFilter = document.getElementById('risetYearFilter');
const risetCards = document.querySelectorAll('.riset-card-ext');

function filterRiset() {
    const query = risetSearch.value.toLowerCase();
    const type = risetTypeFilter.value;
    const kat = risetKatFilter.value;
    const year = risetYearFilter ? risetYearFilter.value : 'all';
    let visibleCount = 0;

    risetCards.forEach(card => {
        const judul = card.dataset.judul;
        const ketua = card.dataset.ketua;
        const cardType = card.dataset.jenis;
        const cardKat = card.dataset.kategori;
        const cardYear = card.dataset.tahun;
        
        const matchSearch = !query || judul.includes(query) || ketua.includes(query);
        const matchType = type === 'all' || cardType === type;
        const matchKat = kat === 'all' || cardKat === kat;
        const matchYear = year === 'all' || cardYear === year;

        if (matchSearch && matchType && matchKat && matchYear) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide empty state
    const grid = document.getElementById('risetGrid');
    let emptyEl = grid.querySelector('.empty-riset-dynamic');
    if (visibleCount === 0 && risetCards.length > 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'empty-riset-ext empty-riset-dynamic';
            emptyEl.innerHTML = '<div class="empty-icon-lg">🔍</div><h3>Tidak ada riset ditemukan</h3><p style="color: var(--text-muted); margin-top: 0.5rem;">Coba ubah kata kunci atau filter pencarian.</p>';
            grid.appendChild(emptyEl);
        }
    } else if (emptyEl) {
        emptyEl.remove();
    }
}

risetSearch.addEventListener('input', filterRiset);
risetTypeFilter.addEventListener('change', filterRiset);
risetKatFilter.addEventListener('change', filterRiset);
if (risetYearFilter) risetYearFilter.addEventListener('change', filterRiset);

// ===== MODAL FUNCTIONS =====
function openAbstractModal(riset) {
    document.getElementById('modalTitle').textContent = riset.judul;
    document.getElementById('modalYear').textContent = '📅 Tahun ' + riset.tahun;
    document.getElementById('modalType').textContent = '🔬 ' + (riset.jenis || 'Riset');
    document.getElementById('modalKetua').textContent = '👤 ' + (riset.ketua_nama || 'Tim Peneliti');
    document.getElementById('modalProdi').textContent = '🎓 ' + (riset.prodi_nama || 'Umum');
    document.getElementById('modalAbstract').textContent = riset.abstrak || riset.deskripsi || 'Abstrak tidak tersedia.';
    
    // Keywords (jika ada di database, atau generate dari kategori)
    const keywordsContainer = document.getElementById('modalKeywords');
    keywordsContainer.innerHTML = '';
    if (riset.kategori) {
        keywordsContainer.innerHTML += `<span class="keyword-tag">${riset.kategori}</span>`;
    }
    if (riset.jenis) {
        keywordsContainer.innerHTML += `<span class="keyword-tag">${riset.jenis}</span>`;
    }
    
    document.getElementById('abstractModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAbstractModal() {
    document.getElementById('abstractModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAbstractModal();
});

console.log('%c🔬 Riset FKIP UNIMOF', 'color:#4c1d95;font-size:16px;font-weight:bold');
console.log('%cTip: Klik "Baca Abstrak" untuk melihat detail lengkap', 'color:#64748b');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>