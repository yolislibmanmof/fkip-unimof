<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM program_studi WHERE id = ?")->execute([$id]);
        flash_message('success', 'Program studi berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE program_studi SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status program studi diperbarui.');
    }
    header('Location: program.php?' . http_build_query($_GET));
    exit;
}

$q = trim($_GET['q'] ?? '');
$jenjang_filter = $_GET['jenjang'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR singkatan LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($jenjang_filter !== '') { $where .= ' AND jenjang = ?'; $params[] = $jenjang_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM program_studi $where ORDER BY created_at DESC");
$stmt->execute($params);
$program_list = $stmt->fetchAll();

$stat_total = count($program_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE status='Aktif'")->fetchColumn();
$stat_s1 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='S1' AND status='Aktif'")->fetchColumn();
$stat_s2 = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE jenjang='S2' AND status='Aktif'")->fetchColumn();

$jenjang_stats = [];
$jenjang_rows = $pdo->query("SELECT jenjang, COUNT(*) as total FROM program_studi WHERE status='Aktif' GROUP BY jenjang")->fetchAll();
foreach ($jenjang_rows as $r) $jenjang_stats[$r['jenjang']] = (int)$r['total'];

$csrf = generate_csrf_token();
$active_menu = 'prodi';
$page_heading = 'Kelola Program Studi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Program Studi', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
.stats-extreme { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
.stat-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.75rem; position: relative; overflow: hidden; transition: all 0.4s; box-shadow: var(--shadow-sm); }
.stat-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent); }
.stat-card-extreme:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--stat-color, var(--primary)); }
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
.search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); background: var(--bg-primary); }
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
table.extreme th { background: var(--bg-secondary); padding: 1rem; text-align: left; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 2px solid var(--border); }
table.extreme td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.extreme tbody tr { transition: all 0.2s; }
table.extreme tbody tr:hover { background: var(--bg-secondary); }
table.extreme tbody tr:last-child td { border-bottom: none; }
.badge-extreme { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-non-aktif { background: #fee2e2; color: #991b1b; }
.badge-s1 { background: #dbeafe; color: #1e40af; }
.badge-s2 { background: #f3e8ff; color: #7e22ce; }
.badge-d3 { background: #fef3c7; color: #92400e; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; }
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
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Program</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Program Aktif</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_s1 ?>">0</div>
        <div class="stat-label-extreme">Program S1</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_s2 ?>">0</div>
        <div class="stat-label-extreme">Program S2</div>
    </div>
</div>

<?php if (!empty($jenjang_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Jenjang</h3>
        <div id="jenjangChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Ringkasan Status</h3>
        <div id="statusChart"></div>
    </div>
</div>
<?php endif; ?>

<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama atau singkatan prodi..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="jenjangFilter">
        <option value="">Semua Jenjang</option>
        <option value="S1" <?= $jenjang_filter === 'S1' ? 'selected' : '' ?>> S1</option>
        <option value="S2" <?= $jenjang_filter === 'S2' ? 'selected' : '' ?>> S2</option>
        <option value="D3" <?= $jenjang_filter === 'D3' ? 'selected' : '' ?>>🎓 D3</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <a href="program-form.php" class="btn-action primary">➕ Tambah Program</a>
</div>

<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div>
            <h2>🎓 Daftar Program Studi</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Kelola program studi FKIP UNIMOF</p>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;"> <?= count($program_list) ?> data</span>
    </div>
    
    <?php if (empty($program_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">🎓</div>
            <h3>Belum ada data program studi</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan program studi fakultas.</p>
            <a href="program-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Program Pertama</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead>
                    <tr>
                        <th>Nama Program Studi</th>
                        <th>Singkatan</th>
                        <th>Jenjang</th>
                        <th>Akreditasi</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($program_list as $p): 
                    $jenjang_class = 'badge-' . strtolower($p['jenjang'] ?? 'lainnya');
                    $status_class = strtolower($p['status']) === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                ?>
                <tr>
                    <td>
                        <strong style="color: var(--text-primary); font-size: 1rem;"><?= sanitize($p['nama']) ?></strong>
                        <?php if (!empty($p['deskripsi'])): ?>
                            <br><small style="color: var(--text-muted); font-size: 0.8rem;"><?= excerpt($p['deskripsi'], 60) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span style="font-family: monospace; font-weight: 700; color: var(--primary);"><?= sanitize($p['singkatan'] ?? '-') ?></span></td>
                    <td><span class="badge-extreme <?= $jenjang_class ?>"><?= sanitize($p['jenjang']) ?></span></td>
                    <td><span class="badge-extreme badge-info"><?= sanitize($p['akreditasi'] ?? '-') ?></span></td>
                    <td><span class="badge-extreme <?= $status_class ?>"><?= $p['status'] ?></span></td>
                    <td>
                        <div class="action-buttons">
                            <a href="program-form.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status program ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus program ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn-icon delete" title="Hapus">️</button>
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
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}
const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

<?php if (!empty($jenjang_stats)): ?>
new ApexCharts(document.querySelector("#jenjangChart"), {
    series: <?= json_encode(array_values($jenjang_stats)) ?>,
    labels: <?= json_encode(array_keys($jenjang_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#3b82f6', '#8b5cf6', '#f59e0b'],
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $stat_aktif ?> } } } } },
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();

new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_total - $stat_aktif ?>],
    labels: ['Aktif', 'Non-Aktif'],
    chart: { type: 'pie', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444'],
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();
<?php endif; ?>

const searchInput = document.getElementById('searchInput');
const jenjangFilter = document.getElementById('jenjangFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
jenjangFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const jenjang = jenjangFilter.value;
    const status = statusFilter.value;
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jenjang) url.searchParams.set('jenjang', jenjang); else url.searchParams.delete('jenjang');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    window.location = url;
}

console.log('%c🎓 Kelola Program Studi FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>