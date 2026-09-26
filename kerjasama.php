<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Kerjasama & Mitra';
$page_description = 'Jaringan kerjasama FKIP UNIMOF dengan berbagai universitas, industri, dan pemerintah dalam negeri maupun internasional.';

$stmt = $pdo->query("SELECT * FROM kerjasama WHERE status = 'Aktif' ORDER BY created_at DESC");
$mitra_list = $stmt->fetchAll();

$stat_total = count($mitra_list);
$stat_univ = count(array_filter($mitra_list, fn($m) => $m['jenis'] === 'Universitas'));
$stat_industri = count(array_filter($mitra_list, fn($m) => $m['jenis'] === 'Industri'));
$stat_intl = count(array_filter($mitra_list, fn($m) => $m['negara'] !== 'Indonesia'));

require_once __DIR__ . '/includes/header.php';
?>

<style>
.kerjasama-hero { position: relative; background: linear-gradient(135deg, #1e3a8a 0%, #312e81 40%, #4c1d95 100%); color: white; padding: 10rem 0 6rem; overflow: hidden; }
.kerjasama-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.25) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%); animation: auroraShift 25s ease-in-out infinite; }
@keyframes auroraShift { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-30px, 20px) scale(1.05); } }

.mitra-stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10; }
.mitra-stat-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; text-align: center; box-shadow: var(--shadow-lg); transition: all 0.4s; }
.mitra-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.mitra-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.mitra-stat-num { font-family: var(--font-display); font-size: 3rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.mitra-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

.mitra-toolbar { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem; display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
.mitra-filter-chip { padding: 0.6rem 1.25rem; border-radius: 999px; border: 2px solid var(--border); background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.mitra-filter-chip:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.mitra-filter-chip.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

.mitra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.mitra-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; text-align: center; transition: all 0.4s; display: flex; flex-direction: column; align-items: center; }
.mitra-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.mitra-logo-box { width: 100px; height: 100px; background: var(--bg-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin-bottom: 1.5rem; overflow: hidden; border: 2px solid var(--border); }
.mitra-logo-box img { width: 100%; height: 100%; object-fit: contain; padding: 1rem; }
.mitra-title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem; }
.mitra-jenis { display: inline-block; padding: 0.3rem 0.8rem; background: rgba(10,104,71,0.1); color: var(--primary); border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-bottom: 1rem; }
.mitra-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.5rem; flex: 1; }
.mitra-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-muted); }

@media (max-width: 640px) { .mitra-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; } }
</style>

<section class="kerjasama-hero">
    <div class="container" style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Kerjasama</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Jaringan <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Kerjasama Global</span></h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            FKIP UNIMOF berkomitmen membangun ekosistem pendidikan yang kolaboratif melalui kemitraan strategis dengan universitas, industri, dan pemerintah.
        </p>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="mitra-stats-bar" data-aos="fade-up">
            <div class="mitra-stat-card" style="--stat-color: #3b82f6;"><div class="mitra-stat-icon">🤝</div><div class="mitra-stat-num count-up" data-target="<?= $stat_total ?>">0</div><div class="mitra-stat-label">Total Mitra</div></div>
            <div class="mitra-stat-card" style="--stat-color: #10b981;"><div class="mitra-stat-icon">🎓</div><div class="mitra-stat-num count-up" data-target="<?= $stat_univ ?>">0</div><div class="mitra-stat-label">Universitas</div></div>
            <div class="mitra-stat-card" style="--stat-color: #f59e0b;"><div class="mitra-stat-icon">🏭</div><div class="mitra-stat-num count-up" data-target="<?= $stat_industri ?>">0</div><div class="mitra-stat-label">Industri</div></div>
            <div class="mitra-stat-card" style="--stat-color: #8b5cf6;"><div class="mitra-stat-icon">🌍</div><div class="mitra-stat-num count-up" data-target="<?= $stat_intl ?>">0</div><div class="mitra-stat-label">Internasional</div></div>
        </div>

        <?php if (empty($mitra_list)): ?>
            <div style="text-align:center;padding:4rem;background:var(--bg-secondary);border-radius:var(--radius-xl);border:2px dashed var(--border);">
                <div style="font-size:4rem;margin-bottom:1rem;opacity:0.5;">🤝</div><h3>Belum ada data kerjasama</h3>
            </div>
        <?php else: ?>
            <div class="mitra-toolbar" data-aos="fade-up">
                <button class="mitra-filter-chip active" onclick="filterMitra('all', this)">🌐 Semua</button>
                <button class="mitra-filter-chip" onclick="filterMitra('Universitas', this)">🎓 Universitas</button>
                <button class="mitra-filter-chip" onclick="filterMitra('Industri', this)">🏭 Industri</button>
                <button class="mitra-filter-chip" onclick="filterMitra('Pemerintah', this)">🏛️ Pemerintah</button>
            </div>

            <div class="mitra-grid" id="mitraGrid" data-aos="fade-up">
                <?php foreach ($mitra_list as $m): 
                    $icon = $m['jenis'] === 'Universitas' ? '🎓' : ($m['jenis'] === 'Industri' ? '🏭' : '🏛️');
                ?>
                <div class="mitra-card" data-jenis="<?= sanitize($m['jenis']) ?>">
                    <div class="mitra-logo-box">
                        <?php if (!empty($m['logo'])): ?>
                            <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="<?= sanitize($m['nama_institusi']) ?>">
                        <?php else: ?>
                            <span><?= $icon ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="mitra-title"><?= sanitize($m['nama_institusi']) ?></h3>
                    <span class="mitra-jenis"><?= sanitize($m['jenis']) ?></span>
                    <p class="mitra-desc"><?= excerpt($m['bentuk_kerjasama'] ?? 'Kerjasama strategis', 100) ?></p>
                    <div class="mitra-meta">
                        <span>🌍 <?= sanitize($m['negara']) ?></span>
                        <?php if ($m['tanggal_mulai']): ?><span>📅 Sejak <?= date('Y', strtotime($m['tanggal_mulai'])) ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 2000; const start = performance.now();
    function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        el.textContent = Math.floor((1 - Math.pow(1 - progress, 3)) * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}
const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => { if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); } });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

const mitraCards = document.querySelectorAll('.mitra-card');
function filterMitra(jenis, btn) {
    document.querySelectorAll('.mitra-filter-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    mitraCards.forEach(card => {
        card.style.display = (jenis === 'all' || card.dataset.jenis === jenis) ? 'flex' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>