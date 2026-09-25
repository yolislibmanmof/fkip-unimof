<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Alumni Sukses';
$page_description = 'Jejaring alumni FKIP UNIMOF yang berkarir gemilang di berbagai bidang';

// Ambil data alumni
$stmt = $pdo->query("SELECT a.*, p.nama as prodi_nama FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status = 'Aktif' ORDER BY a.tahun_lulus DESC, a.nama ASC");
$alumni_list = $stmt->fetchAll();

// Statistik alumni
$stat_total = count($alumni_list);
$stat_bekerja = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND pekerjaan='Bekerja'")->fetchColumn();
$stat_wirausaha = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND pekerjaan='Wirausaha'")->fetchColumn();
$stat_lanjut = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE status='Aktif' AND pekerjaan='Lanjut Studi'")->fetchColumn();

// Testimoni (ambil 3 alumni dengan testimoni)
$stmtTesti = $pdo->query("SELECT a.*, p.nama as prodi_nama FROM alumni a LEFT JOIN program_studi p ON a.program_studi_id = p.id WHERE a.status='Aktif' AND a.testimoni IS NOT NULL AND a.testimoni != '' ORDER BY RAND() LIMIT 3");
$testimoni_list = $stmtTesti->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.alumni-hero {
    background: linear-gradient(135deg, #059669 0%, #0a6847 50%, #d97706 100%);
    color: white; padding: 8rem 0 5rem; position: relative; overflow: hidden;
}
.alumni-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(251,191,36,0.25) 0%, transparent 50%),
                radial-gradient(circle at 70% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
    animation: auroraShift 20s ease-in-out infinite;
}
@keyframes auroraShift {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(-20px, 20px); }
}
.alumni-hero .container { position: relative; z-index: 2; }

/* Alumni Stats */
.alumni-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;
    margin: -3rem 0 3rem; position: relative; z-index: 3;
}
.alumni-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-md); transition: all 0.3s;
}
.alumni-stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.alumni-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.alumni-stat-num {
    font-family: var(--font-display); font-size: 2.5rem; font-weight: 900;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; line-height: 1; margin-bottom: 0.5rem;
}
.alumni-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

/* Testimoni Section */
.testimoni-section { margin-bottom: 4rem; }
.testimoni-header { text-align: center; margin-bottom: 2.5rem; }
.testimoni-header h2 { font-family: var(--font-display); font-size: 2rem; margin-bottom: 0.5rem; }
.testimoni-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.testimoni-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 2rem; position: relative;
    transition: all 0.3s;
}
.testimoni-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.testimoni-card::before {
    content: '❝'; position: absolute; top: 1rem; right: 1.5rem;
    font-size: 4rem; color: var(--primary); opacity: 0.15; font-family: Georgia, serif;
}
.testimoni-text {
    font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7;
    font-style: italic; margin-bottom: 1.5rem; position: relative; z-index: 2;
}
.testimoni-author { display: flex; align-items: center; gap: 1rem; }
.testimoni-avatar {
    width: 50px; height: 50px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
}
.testimoni-info strong { display: block; font-size: 0.95rem; color: var(--text-primary); }
.testimoni-info span { font-size: 0.8rem; color: var(--text-muted); }

/* Alumni Grid */
.alumni-grid-header { text-align: center; margin-bottom: 2.5rem; }
.alumni-grid-header h2 { font-family: var(--font-display); font-size: 2rem; margin-bottom: 0.5rem; }

.alumni-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;
}
.alumni-card {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 1.75rem; text-align: center;
    transition: all 0.4s; position: relative; overflow: hidden;
}
.alumni-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
}
.alumni-card:hover {
    transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary);
}
.alumni-card:hover::before { transform: scaleX(1); }

.alumni-avatar {
    width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 1.75rem; font-weight: 800; box-shadow: 0 8px 20px rgba(10,104,71,0.2);
}
.alumni-card h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.25rem; color: var(--text-primary); }
.alumni-card .alumni-year { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem; }
.alumni-card .alumni-prodi {
    display: inline-block; padding: 0.3rem 0.8rem; background: var(--bg-secondary);
    border-radius: 999px; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem;
}
.alumni-card .alumni-job {
    font-size: 0.85rem; color: var(--primary); font-weight: 600;
    display: flex; align-items: center; justify-content: center; gap: 0.4rem;
}
.alumni-card .alumni-job .job-icon { font-size: 1.1rem; }

.empty-alumni {
    grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
    background: var(--bg-secondary); border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}

/* CTA Card */
.alumni-cta {
    margin-top: 4rem; background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: var(--radius-xl); padding: 3rem; text-align: center; color: white;
    position: relative; overflow: hidden;
}
.alumni-cta::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
}
.alumni-cta .container-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }
.alumni-cta h2 { font-family: var(--font-display); font-size: 2rem; margin-bottom: 1rem; }
.alumni-cta p { font-size: 1.05rem; opacity: 0.95; margin-bottom: 2rem; }
.alumni-cta .btn-primary {
    background: white; color: var(--primary); font-weight: 700;
}

@media (max-width: 768px) {
    .alumni-stats { grid-template-columns: repeat(2, 1fr); }
    .alumni-hero { padding: 6rem 0 3rem; }
    .testimoni-grid { grid-template-columns: 1fr; }
}
</style>

<section class="alumni-hero">
    <div class="container">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem;">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Alumni Sukses</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.02em;">Jejaring Alumni Sukses</h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px;">Lulusan FKIP UNIMOF yang telah berkarir gemilang dan menjadi inspirasi bagi generasi berikutnya.</p>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="container">
        
        <!-- Stats Bar -->
        <div class="alumni-stats" data-aos="fade-up">
            <div class="alumni-stat-card">
                <div class="alumni-stat-icon">🎓</div>
                <div class="alumni-stat-num" data-count="<?= $stat_total ?>">0</div>
                <div class="alumni-stat-label">Total Alumni</div>
            </div>
            <div class="alumni-stat-card">
                <div class="alumni-stat-icon">💼</div>
                <div class="alumni-stat-num" data-count="<?= $stat_bekerja ?>">0</div>
                <div class="alumni-stat-label">Bekerja</div>
            </div>
            <div class="alumni-stat-card">
                <div class="alumni-stat-icon">🚀</div>
                <div class="alumni-stat-num" data-count="<?= $stat_wirausaha ?>">0</div>
                <div class="alumni-stat-label">Wirausaha</div>
            </div>
            <div class="alumni-stat-card">
                <div class="alumni-stat-icon">📚</div>
                <div class="alumni-stat-num" data-count="<?= $stat_lanjut ?>">0</div>
                <div class="alumni-stat-label">Lanjut Studi</div>
            </div>
        </div>

        <!-- Testimoni Section -->
        <?php if (!empty($testimoni_list)): ?>
        <div class="testimoni-section" data-aos="fade-up">
            <div class="testimoni-header">
                <span class="section-tag">Testimoni Alumni</span>
                <h2>Kata Mereka Tentang <span class="gradient-text">FKIP UNIMOF</span></h2>
            </div>
            <div class="testimoni-grid">
                <?php foreach ($testimoni_list as $t): 
                    $initials = strtoupper(substr($t['nama'], 0, 1));
                ?>
                <div class="testimoni-card">
                    <p class="testimoni-text">"<?= sanitize($t['testimoni']) ?>"</p>
                    <div class="testimoni-author">
                        <div class="testimoni-avatar"><?= $initials ?></div>
                        <div class="testimoni-info">
                            <strong><?= sanitize($t['nama']) ?></strong>
                            <span>Alumni <?= sanitize($t['tahun_lulus']) ?> • <?= sanitize($t['prodi_nama'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alumni Grid -->
        <div class="alumni-grid-header" data-aos="fade-up">
            <span class="section-tag">Direktori Alumni</span>
            <h2>Alumni <span class="gradient-text">Inspiratif</span></h2>
            <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Berbagai profesi dan pencapaian yang diraih oleh lulusan kami di seluruh Indonesia.</p>
        </div>

        <div class="alumni-grid" id="alumniGrid" data-aos="fade-up">
            <?php if (empty($alumni_list)): ?>
                <div class="empty-alumni">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🎓</div>
                    <h3>Belum ada data alumni</h3>
                    <p style="color: var(--text-muted);">Direktori alumni akan segera diperbarui.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alumni_list as $a): 
                    $initials = strtoupper(substr($a['nama'], 0, 1) . (strpos($a['nama'], ' ') ? substr($a['nama'], strpos($a['nama'], ' ') + 1, 1) : ''));
                    $job_icons = ['Bekerja' => '💼', 'Wirausaha' => '🚀', 'Lanjut Studi' => '📚', 'Guru' => '👨‍🏫', 'Dosen' => '🎓'];
                    $job_icon = $job_icons[$a['pekerjaan'] ?? ''] ?? '⭐';
                ?>
                <div class="alumni-card">
                    <div class="alumni-avatar"><?= $initials ?></div>
                    <h3><?= sanitize($a['nama']) ?></h3>
                    <div class="alumni-year">Angkatan <?= sanitize($a['tahun_lulus']) ?></div>
                    <div class="alumni-prodi"><?= sanitize($a['prodi_nama'] ?? 'Umum') ?></div>
                    <div class="alumni-job">
                        <span class="job-icon"><?= $job_icon ?></span>
                        <span><?= sanitize($a['pekerjaan'] ?? 'Profesional') ?></span>
                    </div>
                    <?php if (!empty($a['prestasi'])): ?>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.75rem; font-style: italic;">
                            "<?= excerpt($a['prestasi'], 80) ?>"
                        </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- CTA -->
        <div class="alumni-cta" data-aos="zoom-in">
            <div class="container-inner">
                <h2>Apakah Anda Alumni FKIP UNIMOF?</h2>
                <p>Bergabunglah dengan jejaring alumni kami dan bagikan kisah sukses Anda untuk menginspirasi adik-adik tingkat.</p>
                <a href="<?= base_url('kontak.php') ?>" class="btn btn-primary btn-lg">Daftarkan Diri Anda →</a>
            </div>
        </div>
    </div>
</section>

<script>
// Animated counters
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

console.log('%c🎓 Alumni FKIP UNIMOF', 'color:#059669;font-size:16px;font-weight:bold');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>