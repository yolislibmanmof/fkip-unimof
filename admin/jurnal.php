<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM jurnal WHERE id = ?")->execute([$id]);
        flash_message('success', 'Jurnal berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE jurnal SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status jurnal diperbarui.');
    }
    header('Location: jurnal.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$akreditasi_filter = $_GET['akreditasi'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR penerbit LIKE ? OR issn LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($akreditasi_filter !== '') { $where .= ' AND akreditasi = ?'; $params[] = $akreditasi_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM jurnal $where ORDER BY created_at DESC");
$stmt->execute($params);
$jurnal_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($jurnal_list);
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE status='Aktif'")->fetchColumn();
$stat_url = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE url IS NOT NULL AND url != '' AND status='Aktif'")->fetchColumn();
$stat_terakreditasi = (int)$pdo->query("SELECT COUNT(*) FROM jurnal WHERE akreditasi LIKE '%Sinta%' OR akreditasi LIKE '%Scopus%'")->fetchColumn();

// Data untuk chart
$akreditasi_stats = [];
$akreditasi_rows = $pdo->query("SELECT akreditasi, COUNT(*) as total FROM jurnal WHERE status='Aktif' GROUP BY akreditasi ORDER BY total DESC")->fetchAll();
foreach ($akreditasi_rows as $r) {
    $akreditasi_stats[$r['akreditasi']] = (int)$r['total'];
}

$csrf = generate_csrf_token();
$active_menu = 'jurnal';
$page_heading = 'Kelola Jurnal Ilmiah';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Jurnal Ilmiah', null]];

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
.badge-scopus { background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; }
.badge-sinta-1-2 { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-sinta-3-4 { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.badge-sinta-5-6 { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

.action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
.btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.3s; text-decoration: none; }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.link { background: #dcfce7; color: #16a34a; }
.btn-icon.link:hover { background: #16a34a; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

.empty-state-extreme { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-extreme { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 640px) { .stats-extreme { grid-template-columns: 1fr; } .toolbar-extreme { flex-direction: column; align-items: stretch; } .search-box { min-width: 100%; } }
</style>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-icon-extreme">📚</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Jurnal</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Jurnal Aktif</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">🏅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_terakreditasi ?>">0</div>
        <div class="stat-label-extreme">Terakreditasi (Sinta/Scopus)</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🔗</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_url ?>">0</div>
        <div class="stat-label-extreme">Memiliki URL Online</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($akreditasi_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Akreditasi Jurnal Aktif</h3>
        <div id="akreditasiChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Ringkasan Status</h3>
        <div id="statusChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama jurnal, penerbit, atau ISSN..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="akreditasiFilter">
        <option value="">Semua Akreditasi</option>
        <option value="Scopus" <?= $akreditasi_filter === 'Scopus' ? 'selected' : '' ?>>🌍 Scopus</option>
        <option value="Sinta 1" <?= $akreditasi_filter === 'Sinta 1' ? 'selected' : '' ?>>🥇 Sinta 1</option>
        <option value="Sinta 2" <?= $akreditasi_filter === 'Sinta 2' ? 'selected' : '' ?>>🥈 Sinta 2</option>
        <option value="Sinta 3" <?= $akreditasi_filter === 'Sinta 3' ? 'selected' : '' ?>>🥉 Sinta 3</option>
        <option value="Sinta 4" <?= $akreditasi_filter === 'Sinta 4' ? 'selected' : '' ?>>📜 Sinta 4</option>
        <option value="Sinta 5" <?= $akreditasi_filter === 'Sinta 5' ? 'selected' : '' ?>>📄 Sinta 5</option>
        <option value="Sinta 6" <?= $akreditasi_filter === 'Sinta 6' ? 'selected' : '' ?>>📃 Sinta 6</option>
        <option value="Belum Terakreditasi" <?= $akreditasi_filter === 'Belum Terakreditasi' ? 'selected' : '' ?>>⏳ Belum Terakreditasi</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <a href="jurnal-form.php" class="btn-action primary">➕ Tambah Jurnal</a>
</div>

<!-- ===== TABLE ===== -->
<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div>
            <h2>📚 Daftar Jurnal Ilmiah</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Kelola publikasi dan jurnal ilmiah FKIP UNIMOF</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;">📊 <?= count($jurnal_list) ?> data</span>
        </div>
    </div>
    
    <?php if (empty($jurnal_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">📚</div>
            <h3>Belum ada data jurnal</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan jurnal ilmiah fakultas.</p>
            <a href="jurnal-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Jurnal Pertama</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead>
                    <tr>
                        <th>Nama Jurnal & ISSN</th>
                        <th>Penerbit</th>
                        <th>Akreditasi</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($jurnal_list as $j): 
                    $akr_lower = strtolower($j['akreditasi'] ?? '');
                    if (stripos($akr_lower, 'scopus') !== false) $badge_class = 'badge-scopus';
                    elseif (in_array($akr_lower, ['sinta 1', 'sinta 2'])) $badge_class = 'badge-sinta-1-2';
                    elseif (in_array($akr_lower, ['sinta 3', 'sinta 4'])) $badge_class = 'badge-sinta-3-4';
                    elseif (in_array($akr_lower, ['sinta 5', 'sinta 6'])) $badge_class = 'badge-sinta-5-6';
                    else $badge_class = 'badge-lainnya';
                    
                    $status_class = strtolower($j['status']) === 'aktif' ? 'badge-aktif' : 'badge-non-aktif';
                ?>
                <tr>
                    <td>
                        <strong style="color: var(--text-primary); font-size: 1rem;"><?= sanitize($j['nama']) ?></strong>
                        <br>
                        <small style="color: var(--text-muted); font-family: monospace; font-size: 0.8rem;">ISSN: <?= sanitize($j['issn'] ?: 'Belum diisi') ?></small>
                    </td>
                    <td style="color: var(--text-secondary);"><?= sanitize($j['penerbit']) ?></td>
                    <td>
                        <span class="badge-extreme <?= $badge_class ?>">
                            <?= $j['akreditasi'] ?: 'Belum Terakreditasi' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-extreme <?= $status_class ?>"><?= $j['status'] ?></span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="jurnal-form.php?id=<?= $j['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <?php if (!empty($j['url'])): ?>
                                <a href="<?= sanitize($j['url']) ?>" target="_blank" class="btn-icon link" title="Kunjungi Website">🔗</a>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status jurnal ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus jurnal ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
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
<?php if (!empty($akreditasi_stats)): ?>
new ApexCharts(document.querySelector("#akreditasiChart"), {
    series: <?= json_encode(array_values($akreditasi_stats)) ?>,
    labels: <?= json_encode(array_keys($akreditasi_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#7e22ce', '#166534', '#1e40af', '#92400e', '#6b7280'],
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

// Search & Filter
const searchInput = document.getElementById('searchInput');
const akreditasiFilter = document.getElementById('akreditasiFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
akreditasiFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const akreditasi = akreditasiFilter.value;
    const status = statusFilter.value;
    
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (akreditasi) url.searchParams.set('akreditasi', akreditasi); else url.searchParams.delete('akreditasi');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    
    window.location = url;
}

console.log('%c📚 Kelola Jurnal FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>