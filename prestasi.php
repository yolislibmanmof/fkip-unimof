<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Prestasi Mahasiswa';
$page_description = 'Deretan prestasi gemilang mahasiswa FKIP UNIMOF di tingkat regional, nasional, dan internasional';

$stmt = $pdo->query("SELECT pr.*, p.nama as prodi_nama FROM prestasi pr LEFT JOIN program_studi p ON pr.program_studi_id = p.id ORDER BY pr.tahun DESC, pr.tingkat DESC");
$prestasi_list = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.prestasi-hero { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 8rem 0 5rem; text-align: center; position: relative; overflow: hidden; }
.prestasi-hero::before { content: '🏆'; position: absolute; font-size: 20rem; opacity: 0.1; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; }

.prestasi-timeline { max-width: 900px; margin: 0 auto; position: relative; padding: 2rem 0; }
.prestasi-timeline::before { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 4px; background: var(--border); transform: translateX(-50%); }

.prestasi-item { display: flex; justify-content: flex-end; padding-right: 50%; position: relative; margin-bottom: 3rem; }
.prestasi-item:nth-child(even) { justify-content: flex-start; padding-right: 0; padding-left: 50%; }

.prestasi-dot { position: absolute; left: 50%; top: 1.5rem; width: 24px; height: 24px; background: var(--secondary); border: 4px solid var(--bg-primary); border-radius: 50%; transform: translateX(-50%); z-index: 2; box-shadow: 0 0 0 4px rgba(245,166,35,0.2); }

.prestasi-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.75rem; margin: 0 2rem; position: relative; transition: all 0.3s; width: 100%; max-width: 400px; }
.prestasi-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--secondary); }
.prestasi-card::before { content: ''; position: absolute; top: 1.5rem; width: 20px; height: 20px; background: var(--bg-primary); border: 1px solid var(--border); transform: rotate(45deg); }
.prestasi-item:nth-child(odd) .prestasi-card::before { right: -11px; border-left: none; border-bottom: none; }
.prestasi-item:nth-child(even) .prestasi-card::before { left: -11px; border-right: none; border-top: none; }

.prestasi-year { font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; color: var(--secondary); margin-bottom: 0.5rem; }
.prestasi-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.75rem; line-height: 1.4; }
.prestasi-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; font-size: 0.85rem; color: var(--text-muted); }
.prestasi-meta span { display: flex; align-items: center; gap: 0.3rem; background: var(--bg-secondary); padding: 0.3rem 0.7rem; border-radius: 999px; }

.tingkat-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.75rem; }
.tingkat-internasional { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.tingkat-nasional { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.tingkat-wilayah { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

@media (max-width: 768px) {
    .prestasi-timeline::before { left: 20px; }
    .prestasi-item, .prestasi-item:nth-child(even) { justify-content: flex-start; padding-left: 60px; padding-right: 0; }
    .prestasi-dot { left: 20px; }
    .prestasi-card { margin: 0; max-width: 100%; }
    .prestasi-item:nth-child(odd) .prestasi-card::before, .prestasi-item:nth-child(even) .prestasi-card::before { left: -11px; border-right: none; border-top: none; }
}
</style>

<section class="prestasi-hero">
    <div class="container" style="position: relative; z-index: 2;">
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;">Prestasi Mahasiswa</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto;">Membanggakan! Berikut adalah deretan pencapaian gemilang mahasiswa FKIP UNIMOF di berbagai kompetisi.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="prestasi-timeline" data-aos="fade-up">
            <?php if (empty($prestasi_list)): ?>
                <div style="text-align: center; padding: 4rem; background: var(--bg-secondary); border-radius: var(--radius-xl);">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🏆</div>
                    <h3>Belum ada data prestasi</h3>
                    <p style="color: var(--text-muted);">Prestasi terbaru akan segera diupdate di sini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($prestasi_list as $index => $p): 
                    $badge_class = 'tingkat-' . strtolower($p['tingkat'] ?? 'nasional');
                ?>
                <div class="prestasi-item" data-aos="fade-up" data-aos-delay="<?= ($index % 2) * 100 ?>">
                    <div class="prestasi-dot"></div>
                    <div class="prestasi-card">
                        <span class="tingkat-badge <?= $badge_class ?>"><?= sanitize($p['tingkat'] ?? 'Nasional') ?></span>
                        <div class="prestasi-year"><?= sanitize($p['tahun']) ?></div>
                        <h3 class="prestasi-title"><?= sanitize($p['judul']) ?></h3>
                        <div class="prestasi-meta">
                            <span>👤 <?= sanitize($p['mahasiswa']) ?></span>
                            <span>🎓 <?= sanitize($p['prodi_nama'] ?? 'Umum') ?></span>
                            <span>🥇 <?= sanitize($p['juara']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>