<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Akreditasi & Sertifikasi';
$page_description = 'Status akreditasi resmi seluruh program studi di FKIP UNIMOF dari BAN-PT dan LAMDIK.';

$stmt = $pdo->query("SELECT * FROM akreditasi ORDER BY FIELD(peringkat, 'Unggul', 'Baik Sekali', 'Baik', 'C'), nama_prodi ASC");
$akreditasi_list = $stmt->fetchAll();

$stat_total = count($akreditasi_list);
$stat_unggul = count(array_filter($akreditasi_list, fn($a) => $a['peringkat'] === 'Unggul'));
$stat_baik = count(array_filter($akreditasi_list, fn($a) => $a['peringkat'] === 'Baik Sekali'));

require_once __DIR__ . '/includes/header.php';
?>

<style>
.akreditasi-hero { position: relative; background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #334155 100%); color: white; padding: 10rem 0 6rem; overflow: hidden; }
.akreditasi-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 30%, rgba(16,185,129,0.25) 0%, transparent 50%), radial-gradient(circle at 80% 70%, rgba(59,130,246,0.3) 0%, transparent 50%); animation: auroraShift 25s ease-in-out infinite; }

.akreditasi-stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin: -4rem auto 3rem; max-width: 1000px; position: relative; z-index: 10; }
.akreditasi-stat-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; text-align: center; box-shadow: var(--shadow-lg); transition: all 0.4s; }
.akreditasi-stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.akreditasi-stat-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
.akreditasi-stat-num { font-family: var(--font-display); font-size: 3rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.akreditasi-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

.akreditasi-table-wrap { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); margin-top: 2rem; }
.akreditasi-table { width: 100%; border-collapse: collapse; }
.akreditasi-table th { background: var(--bg-secondary); padding: 1.25rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 1px solid var(--border); }
.akreditasi-table td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); color: var(--text-primary); font-size: 0.95rem; vertical-align: middle; }
.akreditasi-table tr:last-child td { border-bottom: none; }
.akreditasi-table tr:hover td { background: rgba(10,104,71,0.02); }

.badge-peringkat { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
.badge-unggul { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
.badge-baik-sekali { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.badge-baik { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }

.btn-download-sk { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; background: var(--bg-secondary); color: var(--text-primary); border-radius: var(--radius-md); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; border: 1px solid var(--border); }
.btn-download-sk:hover { background: var(--primary); color: white; border-color: var(--primary); }

@media (max-width: 768px) {
    .akreditasi-stats-bar { grid-template-columns: 1fr 1fr; margin: -3rem 1rem 2rem; }
    .akreditasi-table-wrap { overflow-x: auto; }
    .akreditasi-table { min-width: 800px; }
}
</style>

<section class="akreditasi-hero">
    <div class="container" style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Akreditasi</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Akreditasi & <span style="background: linear-gradient(135deg, #10b981, #059669); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Sertifikasi</span></h1>
        <p class="page-subtitle" style="font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto; line-height: 1.7;" data-aos="fade-up" data-aos-delay="100">
            Komitmen kami terhadap kualitas pendidikan dibuktikan melalui akreditasi resmi dari badan akreditasi nasional yang diakui.
        </p>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="container">
        <div class="akreditasi-stats-bar" data-aos="fade-up">
            <div class="akreditasi-stat-card" style="--stat-color: #f59e0b;"><div class="akreditasi-stat-icon">🏆</div><div class="akreditasi-stat-num count-up" data-target="<?= $stat_total ?>">0</div><div class="akreditasi-stat-label">Total Prodi</div></div>
            <div class="akreditasi-stat-card" style="--stat-color: #10b981;"><div class="akreditasi-stat-icon">🥇</div><div class="akreditasi-stat-num count-up" data-target="<?= $stat_unggul ?>">0</div><div class="akreditasi-stat-label">Peringkat Unggul</div></div>
            <div class="akreditasi-stat-card" style="--stat-color: #3b82f6;"><div class="akreditasi-stat-icon">🥈</div><div class="akreditasi-stat-num count-up" data-target="<?= $stat_baik ?>">0</div><div class="akreditasi-stat-label">Peringkat Baik Sekali</div></div>
        </div>

        <div class="akreditasi-table-wrap" data-aos="fade-up">
            <table class="akreditasi-table">
                <thead>
                    <tr>
                        <th>Program Studi</th>
                        <th>Badan Akreditasi</th>
                        <th>Peringkat</th>
                        <th>Nomor SK</th>
                        <th>Berlaku Sampai</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($akreditasi_list)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:3rem;color:#64748b">Belum ada data akreditasi.</td></tr>
                    <?php else: foreach ($akreditasi_list as $a): 
                        $badge_class = strtolower(str_replace(' ', '-', $a['peringkat']));
                        $is_expired = $a['tanggal_berlaku'] && strtotime($a['tanggal_berlaku']) < time();
                    ?>
                    <tr>
                        <td><strong><?= sanitize($a['nama_prodi']) ?></strong></td>
                        <td><?= sanitize($a['badan_akreditasi']) ?></td>
                        <td><span class="badge-peringkat badge-<?= in_array($badge_class, ['unggul']) ? 'unggul' : (in_array($badge_class, ['baik-sekali']) ? 'baik-sekali' : 'baik') ?>"><?= sanitize($a['peringkat']) ?></span></td>
                        <td style="font-family: monospace; font-size: 0.85rem;"><?= sanitize($a['nomor_sk'] ?? '-') ?></td>
                        <td style="<?= $is_expired ? 'color:#ef4444;font-weight:700;' : '' ?>"><?= $a['tanggal_berlaku'] ? date('d M Y', strtotime($a['tanggal_berlaku'])) : '-' ?></td>
                        <td><span class="badge-ultimate badge-<?= strtolower($a['status']) === 'aktif' ? 'success' : ($a['status'] === 'Proses' ? 'info' : 'warning') ?>"><?= $a['status'] ?></span></td>
                        <td>
                            <?php if (!empty($a['sertifikat_file'])): ?>
                                <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="btn-download-sk">📄 Unduh SK</a>
                            <?php else: ?>
                                <span style="color:#94a3b8;font-size:0.85rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>