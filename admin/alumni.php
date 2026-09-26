<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) { $stmt = $pdo->prepare("SELECT foto FROM alumni WHERE id = ?"); $stmt->execute([$id]); delete_upload($stmt->fetchColumn(), 'alumni'); $pdo->prepare("DELETE FROM alumni WHERE id = ?")->execute([$id]); flash_message('success', 'Alumni dihapus.'); }
    header('Location: alumni.php?' . http_build_query($_GET)); exit;
}
$q = trim($_GET['q'] ?? ''); $angkatan_filter = $_GET['angkatan'] ?? '';
$where = 'WHERE 1=1'; $params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR pekerjaan LIKE ? OR perusahaan LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($angkatan_filter !== '') { $where .= ' AND angkatan = ?'; $params[] = $angkatan_filter; }
$stmt = $pdo->prepare("SELECT * FROM alumni $where ORDER BY angkatan DESC, created_at DESC"); $stmt->execute($params); $alumni_list = $stmt->fetchAll();
$stat_total = count($alumni_list);
$stat_latest = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE angkatan >= YEAR(CURDATE()) - 4")->fetchColumn();
$stat_work = (int)$pdo->query("SELECT COUNT(*) FROM alumni WHERE pekerjaan IS NOT NULL AND pekerjaan != ''")->fetchColumn();
$angkatan_stats = []; foreach ($pdo->query("SELECT angkatan, COUNT(*) as total FROM alumni GROUP BY angkatan ORDER BY angkatan DESC LIMIT 5")->fetchAll() as $r) $angkatan_stats[$r['angkatan']] = (int)$r['total'];
$csrf = generate_csrf_token(); $active_menu = 'alumni'; $page_heading = 'Kelola Alumni';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Alumni', null]];
require __DIR__ . '/includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
.stats-extreme { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
.stat-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.75rem; position: relative; overflow: hidden; transition: all 0.4s; box-shadow: var(--shadow-sm); }
.stat-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent); }
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
.stat-icon-extreme { font-size: 2.5rem; margin-bottom: 1rem; display: inline-block; }
.stat-number-extreme { font-family: var(--font-display); font-size: 3rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.stat-label-extreme { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.chart-section { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (min-width: 768px) { .chart-section { grid-template-columns: 1fr 1fr; } }
.chart-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); }
.chart-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.toolbar-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
.search-box { flex: 1; min-width: 250px; position: relative; }
.search-box input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; transition: all 0.3s; background: var(--bg-secondary); }
.search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.search-box .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
.filter-select { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary); cursor: pointer; }
.btn-action { padding: 0.75rem 1.25rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; }
.btn-action.primary { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; box-shadow: 0 4px 12px rgba(139,92,246,0.3); }
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(139,92,246,0.4); }
.table-container { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); }
.table-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.table-header h2 { font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; }
.table-wrapper { overflow-x: auto; }
table.extreme { width: 100%; border-collapse: collapse; }
table.extreme th { background: var(--bg-secondary); padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border); }
table.extreme td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); }
.alumni-avatar-sm { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; overflow: hidden; border: 2px solid var(--border); }
.alumni-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
.action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
.btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.3s; text-decoration: none; }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }
.empty-state-extreme { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-extreme { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
@media (max-width: 640px) { .stats-extreme { grid-template-columns: 1fr; } .toolbar-extreme { flex-direction: column; align-items: stretch; } .search-box { min-width: 100%; } }
</style>

<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;"><div class="stat-icon-extreme">🎓</div><div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div><div class="stat-label-extreme">Total Alumni</div></div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;"><div class="stat-icon-extreme">🆕</div><div class="stat-number-extreme count-up" data-target="<?= $stat_latest ?>">0</div><div class="stat-label-extreme">5 Angkatan Terakhir</div></div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;"><div class="stat-icon-extreme">💼</div><div class="stat-number-extreme count-up" data-target="<?= $stat_work ?>">0</div><div class="stat-label-extreme">Sudah Bekerja</div></div>
</div>

<?php if (!empty($angkatan_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card"><h3>📊 Distribusi Angkatan (Top 5)</h3><div id="angkatanChart"></div></div>
</div>
<?php endif; ?>

<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="searchInput" placeholder="Cari nama, pekerjaan, atau perusahaan..." value="<?= sanitize($q) ?>"></div>
    <select class="filter-select" id="angkatanFilter">
        <option value="">Semua Angkatan</option>
        <?php for ($y = date('Y'); $y >= 2010; $y--): ?><option value="<?= $y ?>" <?= $angkatan_filter == $y ? 'selected' : '' ?>>🎓 <?= $y ?></option><?php endfor; ?>
    </select>
    <a href="alumni-form.php" class="btn-action primary">➕ Tambah Alumni</a>
</div>

<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div><h2> Daftar Alumni</h2><p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Jejaring lulusan FKIP UNIMOF</p></div>
        <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;">📊 <?= count($alumni_list) ?> data</span>
    </div>
    <?php if (empty($alumni_list)): ?>
        <div class="empty-state-extreme"><div class="empty-icon-extreme">🎓</div><h3>Belum ada data alumni</h3><a href="alumni-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Alumni Pertama</a></div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead><tr><th>Alumni</th><th>Angkatan</th><th>Pekerjaan</th><th>Perusahaan</th><th>Kontak</th><th style="text-align: right;">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($alumni_list as $a): 
                    $initials = strtoupper(substr($a['nama'], 0, 1));
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div class="alumni-avatar-sm"><?php if (!empty($a['foto'])): ?><img src="<?= asset('uploads/alumni/' . basename($a['foto'])) ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?></div>
                            <div><strong style="color: var(--text-primary);"><?= sanitize($a['nama']) ?></strong><?php if (!empty($a['nim'])): ?><br><small style="color: var(--text-muted); font-family: monospace;">NIM: <?= sanitize($a['nim']) ?></small><?php endif; ?></div>
                        </div>
                    </td>
                    <td><span style="font-family: monospace; font-weight: 700; color: var(--primary);"><?= sanitize($a['angkatan']) ?></span></td>
                    <td style="color: var(--text-secondary);"><?= sanitize($a['pekerjaan'] ?: '-') ?></td>
                    <td style="color: var(--text-secondary);"><?= sanitize($a['perusahaan'] ?: '-') ?></td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= sanitize($a['kontak'] ?: '-') ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="alumni-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus alumni ini?')"><input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn-icon delete" title="Hapus">🗑️</button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function animateCount(el) { const target = parseInt(el.dataset.target) || 0; const duration = 2000; const start = performance.now(); function step(now) { const progress = Math.min((now - start) / duration, 1); el.textContent = Math.floor((1 - Math.pow(1 - progress, 3)) * target).toLocaleString('id-ID'); if (progress < 1) requestAnimationFrame(step); } requestAnimationFrame(step); }
const countObserver = new IntersectionObserver((entries) => { entries.forEach(entry => { if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); } }); }, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));
<?php if (!empty($angkatan_stats)): ?>
new ApexCharts(document.querySelector("#angkatanChart"), { series: <?= json_encode(array_values($angkatan_stats)) ?>, labels: <?= json_encode(array_keys($angkatan_stats)) ?>, chart: { type: 'bar', height: 280 }, colors: ['#8b5cf6'], plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } }, dataLabels: { enabled: false }, xaxis: { title: { text: 'Angkatan' } }, yaxis: { title: { text: 'Jumlah Alumni' } } }).render();
<?php endif; ?>
const searchInput = document.getElementById('searchInput'); const angkatanFilter = document.getElementById('angkatanFilter');
let searchTimeout;
searchInput.addEventListener('input', function() { clearTimeout(searchTimeout); searchTimeout = setTimeout(applyFilters, 500); });
angkatanFilter.addEventListener('change', applyFilters);
function applyFilters() { const q = searchInput.value; const angkatan = angkatanFilter.value; const url = new URL(window.location); if (q) url.searchParams.set('q', q); else url.searchParams.delete('q'); if (angkatan) url.searchParams.set('angkatan', angkatan); else url.searchParams.delete('angkatan'); window.location = url; }
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>