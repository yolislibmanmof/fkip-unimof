<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; 
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM riset WHERE id = ?")->execute([$id]);
        flash_message('success', 'Data riset berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE riset SET status = IF(status='Published','Draft','Published') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status riset diperbarui.');
    }
    header('Location: riset.php?' . http_build_query($_GET)); 
    exit;
}

$q = trim($_GET['q'] ?? ''); 
$kat_filter = $_GET['kategori'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';

$where = 'WHERE 1=1'; 
$params = [];
if ($q !== '') { 
    $where .= ' AND (judul LIKE ? OR deskripsi LIKE ? OR abstrak LIKE ?)'; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}
if ($kat_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kat_filter; }
if ($jenis_filter !== '') { $where .= ' AND jenis = ?'; $params[] = $jenis_filter; }

$stmt = $pdo->prepare("SELECT r.*, d.nama as ketua_nama, ps.nama as prodi_nama 
                        FROM riset r 
                        LEFT JOIN dosen d ON r.ketua_id = d.id 
                        LEFT JOIN program_studi ps ON r.program_studi_id = ps.id 
                        $where 
                        ORDER BY r.tahun DESC, r.created_at DESC");
$stmt->execute($params); 
$riset_list = $stmt->fetchAll();

$stat_total = count($riset_list);
$stat_pub = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE status='Published'")->fetchColumn();
$stat_year = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE tahun = YEAR(CURDATE())")->fetchColumn();
$stat_hibah = (int)$pdo->query("SELECT COUNT(*) FROM riset WHERE jenis='Hibah'")->fetchColumn();

$kat_stats = [];
foreach ($pdo->query("SELECT kategori, COUNT(*) as total FROM riset GROUP BY kategori")->fetchAll() as $r) {
    $kat_stats[$r['kategori']] = (int)$r['total'];
}

$jenis_stats = [];
foreach ($pdo->query("SELECT jenis, COUNT(*) as total FROM riset GROUP BY jenis")->fetchAll() as $r) {
    $jenis_stats[$r['jenis']] = (int)$r['total'];
}

$csrf = generate_csrf_token();
$active_menu = 'riset'; 
$page_heading = 'Kelola Riset';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Riset', null]];

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
.search-box .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.filter-select { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s; }
.filter-select:focus { outline: none; border-color: var(--primary); }
.btn-action { padding: 0.75rem 1.25rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; border: none; }
.btn-action.primary { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
.btn-action.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16,185,129,0.4); }
.table-container { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); }
.table-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.table-header h2 { font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; }
.table-wrapper { overflow-x: auto; }
table.extreme { width: 100%; border-collapse: collapse; }
table.extreme th { background: var(--bg-secondary); padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); border-bottom: 2px solid var(--border); }
table.extreme td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); }
.badge-extreme { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.badge-published { background: #dcfce7; color: #166534; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-publikasi { background: #dbeafe; color: #1e40af; }
.badge-hibah { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-pengabdian { background: #dcfce7; color: #166534; }
.badge-info { background: #e0e7ff; color: #4338ca; }
.action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
.btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.3s; text-decoration: none; }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }
.empty-state-extreme { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-extreme { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
@media (max-width: 640px) { .stats-extreme { grid-template-columns: 1fr; } .toolbar-extreme { flex-direction: column; align-items: stretch; } .search-box { min-width: 100%; } }
</style>

<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-icon-extreme">🔬</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Riset</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_pub ?>">0</div>
        <div class="stat-label-extreme">Dipublikasi</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">📅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_year ?>">0</div>
        <div class="stat-label-extreme">Tahun Ini</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">💰</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_hibah ?>">0</div>
        <div class="stat-label-extreme">Hibah</div>
    </div>
</div>

<?php if (!empty($kat_stats) || !empty($jenis_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <?php if (!empty($jenis_stats)): ?>
    <div class="chart-card">
        <h3>📊 Jenis Riset</h3>
        <div id="jenisChart"></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($kat_stats)): ?>
    <div class="chart-card">
        <h3>📈 Kategori Riset</h3>
        <div id="katChart"></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari judul, deskripsi, atau abstrak..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="jenisFilter">
        <option value="">Semua Jenis</option>
        <option value="Publikasi" <?= $jenis_filter === 'Publikasi' ? 'selected' : '' ?>> Publikasi</option>
        <option value="Hibah" <?= $jenis_filter === 'Hibah' ? 'selected' : '' ?>>💰 Hibah</option>
        <option value="Pengabdian" <?= $jenis_filter === 'Pengabdian' ? 'selected' : '' ?>>🤝 Pengabdian</option>
    </select>
    <select class="filter-select" id="katFilter">
        <option value="">Semua Kategori</option>
        <option value="Pendidikan" <?= $kat_filter === 'Pendidikan' ? 'selected' : '' ?>>🎓 Pendidikan</option>
        <option value="Teknologi Pendidikan" <?= $kat_filter === 'Teknologi Pendidikan' ? 'selected' : '' ?>>💻 Teknologi Pendidikan</option>
        <option value="Pengabdian" <?= $kat_filter === 'Pengabdian' ? 'selected' : '' ?>> Pengabdian</option>
    </select>
    <a href="riset-form.php" class="btn-action primary">➕ Tambah Riset</a>
</div>

<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div>
            <h2>🔬 Daftar Riset & Publikasi</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Kelola publikasi ilmiah dosen & mahasiswa</p>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;">📊 <?= count($riset_list) ?> data</span>
    </div>
    <?php if (empty($riset_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">🔬</div>
            <h3>Belum ada data riset</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan data riset dan publikasi.</p>
            <a href="riset-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Riset Pertama</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead>
                    <tr>
                        <th>Judul Riset</th>
                        <th>Ketua & Anggota</th>
                        <th>Jenis</th>
                        <th>Tahun</th>
                        <th>Kategori</th>
                        <th>Jurnal/DOI</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riset_list as $r): 
                    $status_class = $r['status'] === 'Published' ? 'badge-published' : 'badge-draft';
                    $jenis_lower = strtolower($r['jenis'] ?? 'lainnya');
                    $jenis_class = 'badge-' . $jenis_lower;
                ?>
                <tr>
                    <td>
                        <strong style="color: var(--text-primary);"><?= sanitize($r['judul']) ?></strong>
                        <?php if (!empty($r['deskripsi'])): ?>
                            <br><small style="color: var(--text-muted); font-size: 0.8rem;"><?= excerpt($r['deskripsi'], 60) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($r['program_studi_id']) && !empty($r['prodi_nama'])): ?>
                            <br><small style="color: var(--primary); font-size: 0.75rem;">🏫 <?= sanitize($r['prodi_nama']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;">
                        <?php if (!empty($r['ketua_nama'])): ?>
                            <strong><?= sanitize($r['ketua_nama']) ?></strong>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">ID: <?= sanitize($r['ketua_id'] ?? '-') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($r['anggota'])): ?>
                            <br><small style="color: var(--text-muted);">👥 <?= excerpt($r['anggota'], 40) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-extreme <?= $jenis_class ?>"><?= sanitize($r['jenis'] ?? '-') ?></span></td>
                    <td><span style="font-family: monospace; font-weight: 700;"><?= sanitize($r['tahun']) ?></span></td>
                    <td><span class="badge-extreme badge-info"><?= sanitize($r['kategori'] ?? '-') ?></span></td>
                    <td style="font-size: 0.85rem;">
                        <?php if (!empty($r['jurnal'])): ?>
                            <div>📖 <?= sanitize($r['jurnal']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($r['doi'])): ?>
                            <a href="https://doi.org/<?= sanitize($r['doi']) ?>" target="_blank" style="color: var(--primary); font-size: 0.75rem; text-decoration: none;"> DOI</a>
                        <?php endif; ?>
                        <?php if (empty($r['jurnal']) && empty($r['doi'])): ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-extreme <?= $status_class ?>"><?= $r['status'] ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <a href="riset-form.php?id=<?= $r['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status riset?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn-icon toggle" title="Toggle">🔄</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus riset ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn-icon delete" title="Hapus">🗑️</button>
                            </form>
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
function animateCount(el) { 
    const target = parseInt(el.dataset.target) || 0; 
    const duration = 2000; 
    const start = performance.now(); 
    function step(now) { 
        const progress = Math.min((now - start) / duration, 1); 
        el.textContent = Math.floor((1 - Math.pow(1 - progress, 3)) * target).toLocaleString('id-ID'); 
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

<?php if (!empty($jenis_stats)): ?>
new ApexCharts(document.querySelector("#jenisChart"), { 
    series: <?= json_encode(array_values($jenis_stats)) ?>, 
    labels: <?= json_encode(array_keys($jenis_stats)) ?>, 
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } }, 
    colors: ['#3b82f6', '#f59e0b', '#10b981'], 
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $stat_total ?> } } } } }, 
    dataLabels: { enabled: true, style: { fontSize: '12px' } }, 
    legend: { position: 'bottom' } 
}).render();
<?php endif; ?>

<?php if (!empty($kat_stats)): ?>
new ApexCharts(document.querySelector("#katChart"), { 
    series: <?= json_encode(array_values($kat_stats)) ?>, 
    labels: <?= json_encode(array_keys($kat_stats)) ?>, 
    chart: { type: 'bar', height: 280, animations: { enabled: true, speed: 800 } }, 
    colors: ['#8b5cf6'], 
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } }, 
    dataLabels: { enabled: false }, 
    xaxis: { title: { text: 'Kategori' } }, 
    yaxis: { title: { text: 'Jumlah' } } 
}).render();
<?php endif; ?>

const searchInput = document.getElementById('searchInput'); 
const katFilter = document.getElementById('katFilter');
const jenisFilter = document.getElementById('jenisFilter');
let searchTimeout;

searchInput.addEventListener('input', function() { 
    clearTimeout(searchTimeout); 
    searchTimeout = setTimeout(applyFilters, 500); 
});
katFilter.addEventListener('change', applyFilters);
jenisFilter.addEventListener('change', applyFilters);

function applyFilters() { 
    const q = searchInput.value; 
    const kat = katFilter.value; 
    const jenis = jenisFilter.value; 
    const url = new URL(window.location); 
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q'); 
    if (kat) url.searchParams.set('kategori', kat); else url.searchParams.delete('kategori'); 
    if (jenis) url.searchParams.set('jenis', jenis); else url.searchParams.delete('jenis'); 
    window.location = url; 
}

console.log('%c🔬 Kelola Riset FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>