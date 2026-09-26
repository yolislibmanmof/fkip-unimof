<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT logo FROM kerjasama WHERE id = ?");
        $stmt->execute([$id]);
        $logo = $stmt->fetchColumn();
        delete_upload($logo, 'kerjasama');
        $pdo->prepare("DELETE FROM kerjasama WHERE id = ?")->execute([$id]);
        flash_message('success', 'Mitra berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE kerjasama SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status mitra diperbarui.');
    }
    header('Location: kerjasama.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama_institusi LIKE ? OR negara LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($jenis_filter !== '') { $where .= ' AND jenis = ?'; $params[] = $jenis_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM kerjasama $where ORDER BY created_at DESC");
$stmt->execute($params);
$mitra_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($mitra_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM kerjasama WHERE status='Aktif'")->fetchColumn();
$stat_intl = (int)$pdo->query("SELECT COUNT(*) FROM kerjasama WHERE negara != 'Indonesia' AND status='Aktif'")->fetchColumn();
$stat_univ = (int)$pdo->query("SELECT COUNT(*) FROM kerjasama WHERE jenis='Universitas' AND status='Aktif'")->fetchColumn();

// Data untuk chart
$jenis_stats = [];
$jenis_rows = $pdo->query("SELECT jenis, COUNT(*) as total FROM kerjasama WHERE status='Aktif' GROUP BY jenis")->fetchAll();
foreach ($jenis_rows as $r) $jenis_stats[$r['jenis']] = (int)$r['total'];

$negara_stats = [];
$negara_rows = $pdo->query("SELECT negara, COUNT(*) as total FROM kerjasama WHERE status='Aktif' GROUP BY negara ORDER BY total DESC LIMIT 8")->fetchAll();
foreach ($negara_rows as $r) $negara_stats[$r['negara']] = (int)$r['total'];

$csrf = generate_csrf_token();
$active_menu = 'kerjasama';
$page_heading = 'Kelola Kerjasama & Mitra';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Kerjasama', null]];

require __DIR__ . '/../includes/header.php';
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

.chart-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
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
.btn-action.secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border); }
.btn-action.secondary:hover { background: var(--bg-tertiary); transform: translateY(-2px); }

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

.mitra-logo-sm { width: 50px; height: 50px; border-radius: 12px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; overflow: hidden; border: 1px solid var(--border); }
.mitra-logo-sm img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; }

.badge-extreme { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.badge-aktif { background: #dcfce7; color: #166534; }
.badge-non-aktif { background: #fee2e2; color: #991b1b; }
.badge-universitas { background: #dbeafe; color: #1e40af; }
.badge-industri { background: #fef3c7; color: #92400e; }
.badge-pemerintah { background: #e0e7ff; color: #4338ca; }
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

@media (max-width: 1024px) { .chart-section { grid-template-columns: 1fr; } .stats-extreme { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .stats-extreme { grid-template-columns: 1fr; } .toolbar-extreme { flex-direction: column; align-items: stretch; } .search-box { min-width: 100%; } }
</style>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-icon-extreme">🤝</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Mitra</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Mitra Aktif</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">🌍</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_intl ?>">0</div>
        <div class="stat-label-extreme">Mitra Internasional</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🎓</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_univ ?>">0</div>
        <div class="stat-label-extreme">Universitas Mitra</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($jenis_stats) || !empty($negara_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Jenis Mitra</h3>
        <div id="jenisChart"></div>
    </div>
    <div class="chart-card">
        <h3>🌍 Top Negara Mitra</h3>
        <div id="negaraChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama institusi atau negara..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="jenisFilter">
        <option value="">Semua Jenis</option>
        <option value="Universitas" <?= $jenis_filter === 'Universitas' ? 'selected' : '' ?>>🎓 Universitas</option>
        <option value="Industri" <?= $jenis_filter === 'Industri' ? 'selected' : '' ?>> Industri</option>
        <option value="Pemerintah" <?= $jenis_filter === 'Pemerintah' ? 'selected' : '' ?>>🏛️ Pemerintah</option>
        <option value="Lainnya" <?= $jenis_filter === 'Lainnya' ? 'selected' : '' ?>> Lainnya</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <a href="kerjasama-form.php" class="btn-action primary">➕ Tambah Mitra</a>
</div>

<!-- ===== TABLE ===== -->
<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div>
            <h2>🤝 Daftar Kerjasama & Mitra</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Kelola mitra institusi FKIP UNIMOF</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;">📊 <?= count($mitra_list) ?> data</span>
        </div>
    </div>
    
    <?php if (empty($mitra_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">🤝</div>
            <h3>Belum ada data mitra</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan mitra kerjasama institusi Anda.</p>
            <a href="kerjasama-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Mitra Pertama</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead>
                    <tr>
                        <th>Institusi</th>
                        <th>Jenis</th>
                        <th>Negara</th>
                        <th>Masa Kerjasama</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mitra_list as $m): 
                    $jenis_class = strtolower($m['jenis'] ?? 'lainnya');
                    $status_class = strtolower($m['status']) === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                    $icon = $m['jenis'] === 'Universitas' ? '🎓' : ($m['jenis'] === 'Industri' ? '' : ($m['jenis'] === 'Pemerintah' ? '🏛️' : ''));
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div class="mitra-logo-sm">
                                <?php if (!empty($m['logo'])): ?>
                                    <img src="<?= asset('uploads/kerjasama/' . basename($m['logo'])) ?>" alt="">
                                <?php else: ?>
                                    <span><?= $icon ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong style="color: var(--text-primary); font-size: 1rem;"><?= sanitize($m['nama_institusi']) ?></strong>
                                <?php if (!empty($m['bentuk_kerjasama'])): ?>
                                    <br><small style="color: var(--text-muted); font-size: 0.8rem;"><?= excerpt($m['bentuk_kerjasama'], 50) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-extreme badge-<?= $jenis_class ?>"><?= $icon ?> <?= sanitize($m['jenis']) ?></span>
                    </td>
                    <td style="color: var(--text-secondary);">
                        <?= $m['negara'] !== 'Indonesia' ? '🌍' : '🇩' ?> <?= sanitize($m['negara']) ?>
                    </td>
                    <td style="color: var(--text-secondary); font-size: 0.9rem;">
                        <?php if ($m['tanggal_mulai']): ?>
                            <?= date('Y', strtotime($m['tanggal_mulai'])) ?>
                            <?php if ($m['tanggal_selesai']): ?> - <?= date('Y', strtotime($m['tanggal_selesai'])) ?><?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-extreme <?= $status_class ?>"><?= $m['status'] ?></span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="kerjasama-form.php?id=<?= $m['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status mitra ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus mitra ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
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
// Count Up Animation
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

// Charts
<?php if (!empty($jenis_stats)): ?>
new ApexCharts(document.querySelector("#jenisChart"), {
    series: <?= json_encode(array_values($jenis_stats)) ?>,
    labels: <?= json_encode(array_keys($jenis_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#3b82f6', '#f59e0b', '#8b5cf6', '#6b7280'],
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $stat_aktif ?> } } } } },
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();
<?php endif; ?>

<?php if (!empty($negara_stats)): ?>
new ApexCharts(document.querySelector("#negaraChart"), {
    series: <?= json_encode(array_values($negara_stats)) ?>,
    labels: <?= json_encode(array_keys($negara_stats)) ?>,
    chart: { type: 'bar', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: false },
    xaxis: { labels: { style: { fontSize: '11px' } } },
    yaxis: { title: { text: 'Jumlah Mitra' } }
}).render();
<?php endif; ?>

// Search & Filter
const searchInput = document.getElementById('searchInput');
const jenisFilter = document.getElementById('jenisFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
jenisFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const jenis = jenisFilter.value;
    const status = statusFilter.value;
    
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (jenis) url.searchParams.set('jenis', jenis); else url.searchParams.delete('jenis');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    url.searchParams.set('halaman', '1');
    
    window.location = url;
}

console.log('%c🤝 Kelola Kerjasama FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>