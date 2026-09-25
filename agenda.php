<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Agenda & Kalender Akademik';
$page_description = 'Jadwal kegiatan, ujian, seminar, dan libur FKIP UNIMOF.';

$agenda = $pdo->query("SELECT * FROM agenda WHERE status = 'Aktif' ORDER BY tanggal_mulai ASC")->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<style>
.agenda-hero { background: linear-gradient(135deg, #8b5cf6 0%, #4c1d95 100%); color: white; padding: 8rem 0 5rem; text-align: center; }
.agenda-timeline { max-width: 800px; margin: 0 auto; position: relative; padding: 2rem 0; }
.agenda-timeline::before { content: ''; position: absolute; left: 20px; top: 0; bottom: 0; width: 4px; background: var(--border); border-radius: 4px; }
.agenda-item { display: flex; gap: 1.5rem; margin-bottom: 2rem; position: relative; }
.agenda-dot { width: 44px; height: 44px; background: var(--bg-primary); border: 4px solid #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; z-index: 2; box-shadow: 0 4px 10px rgba(139,92,246,0.3); }
.agenda-card { flex: 1; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.3s; }
.agenda-card:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: #8b5cf6; }
.agenda-date { font-family: var(--font-display); font-size: 0.9rem; font-weight: 700; color: #8b5cf6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
.agenda-card h3 { font-size: 1.15rem; margin-bottom: 0.5rem; }
.agenda-card p { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; }
.badge-jenis { display: inline-block; padding: 0.25rem 0.6rem; background: var(--bg-secondary); border-radius: 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-top: 0.75rem; text-transform: uppercase; }
</style>

<section class="agenda-hero">
    <div class="container">
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem);">Agenda & Kalender</h1>
        <p class="page-subtitle" style="max-width: 600px; margin: 1rem auto 0; opacity: 0.9;">Pantau jadwal kegiatan akademik, ujian, seminar, dan hari libur fakultas.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="agenda-timeline">
            <?php if (empty($agenda)): ?>
                <div class="empty-state-pro"><div class="empty-icon-lg">📅</div><h3>Belum ada agenda mendatang</h3></div>
            <?php else: foreach ($agenda as $a): 
                $icon = $a['jenis'] === 'ujian' ? '📝' : ($a['jenis'] === 'libur' ? '🏖️' : ($a['jenis'] === 'wisuda' ? '🎓' : '📌'));
                $date_str = format_tanggal($a['tanggal_mulai']);
                if ($a['tanggal_selesai'] && $a['tanggal_selesai'] !== $a['tanggal_mulai']) {
                    $date_str .= ' s.d. ' . format_tanggal($a['tanggal_selesai']);
                }
            ?>
            <div class="agenda-item" data-aos="fade-up">
                <div class="agenda-dot"><?= $icon ?></div>
                <div class="agenda-card">
                    <div class="agenda-date">📅 <?= $date_str ?></div>
                    <h3><?= sanitize($a['judul']) ?></h3>
                    <?php if ($a['lokasi']): ?><p>📍 <?= sanitize($a['lokasi']) ?></p><?php endif; ?>
                    <?php if ($a['deskripsi']): ?><p><?= nl2br(sanitize($a['deskripsi'])) ?></p><?php endif; ?>
                    <span class="badge-jenis"><?= ucfirst($a['jenis']) ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>