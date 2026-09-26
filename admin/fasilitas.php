<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT gambar FROM fasilitas WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        delete_upload($img, 'fasilitas');
        $pdo->prepare("DELETE FROM fasilitas WHERE id = ?")->execute([$id]);
        flash_message('success', 'Fasilitas berhasil dihapus.');
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE fasilitas SET status = IF(status='Aktif','Non-Aktif','Aktif') WHERE id = ?")->execute([$id]);
        flash_message('success', 'Status fasilitas diperbarui.');
    }
    header('Location: fasilitas.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$kategori_filter = $_GET['kategori'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama LIKE ? OR deskripsi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kategori_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kategori_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM fasilitas $where ORDER BY created_at DESC");
$stmt->execute($params);
$fasilitas_list = $stmt->fetchAll();

// ===== STATISTIK =====
$stat_total = count($fasilitas_list);
$stat_lab = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Laboratorium' AND status='Aktif'")->fetchColumn();
$stat_kelas = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Ruang Kelas' AND status='Aktif'")->fetchColumn();
$stat_perpus = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Perpustakaan' AND status='Aktif'")->fetchColumn();
$stat_kapasitas = (int)$pdo->query("SELECT COALESCE(SUM(kapasitas), 0) FROM fasilitas WHERE status='Aktif'")->fetchColumn();
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE status='Aktif'")->fetchColumn();

// Data untuk chart
$kategori_stats = [];
$kategori_rows = $pdo->query("SELECT kategori, COUNT(*) as total FROM fasilitas WHERE status='Aktif' GROUP BY kategori")->fetchAll();
foreach ($kategori_rows as $r) $kategori_stats[$r['kategori']] = (int)$r['total'];

$csrf = generate_csrf_token();
$active_menu = 'fasilitas';
$page_heading = 'Kelola Fasilitas & Lab';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Fasilitas', null]];

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

.facility-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.facility-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; transition: all 0.4s; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; }
.facility-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); border-color: var(--primary); }
.facility-image { height: 180px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); display: flex; align-items: center; justify-content: center; font-size: 4rem; position: relative; overflow: hidden; }
.facility-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s; }
.facility-card:hover .facility-image img { transform: scale(1.08); }
.facility-kategori-badge { position: absolute; top: 1rem; left: 1rem; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.facility-status-badge { position: absolute; top: 1rem; right: 1rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
.facility-status-badge.aktif { background: #dcfce7; color: #166534; }
.facility-status-badge.non-aktif { background: #fee2e2; color: #991b1b; }
.facility-content { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
.facility-title { font-family: var(--font-display); font-size: 1.2rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3; }
.facility-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.facility-meta { display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border); }
.facility-kapasitas { font-size: 0.85rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.4rem; }
.action-buttons { display: flex; gap: 0.5rem; }
.btn-icon { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.3s; text-decoration: none; }
.btn-icon.edit { background: #dbeafe; color: #2563eb; }
.btn-icon.edit:hover { background: #2563eb; color: white; transform: translateY(-2px); }
.btn-icon.toggle { background: #fef3c7; color: #d97706; }
.btn-icon.toggle:hover { background: #d97706; color: white; transform: translateY(-2px); }
.btn-icon.delete { background: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background: #dc2626; color: white; transform: translateY(-2px); }

.badge-laboratorium { background: #dbeafe; color: #1e40af; }
.badge-ruang-kelas { background: #dcfce7; color: #166534; }
.badge-perpustakaan { background: #fef3c7; color: #92400e; }
.badge-fasilitas-umum { background: #e0e7ff; color: #4338ca; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; }

.empty-state-extreme { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); grid-column: 1/-1; }
.empty-icon-extreme { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 640px) { .stats-extreme { grid-template-columns: 1fr; } .toolbar-extreme { flex-direction: column; align-items: stretch; } .search-box { min-width: 100%; } .facility-grid { grid-template-columns: 1fr; } }
</style>

<!-- ===== STATS ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">🏢</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Fasilitas</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Fasilitas Aktif</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #ef4444;">
        <div class="stat-icon-extreme">🧪</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_lab ?>">0</div>
        <div class="stat-label-extreme">Laboratorium</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">👥</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_kapasitas ?>">0</div>
        <div class="stat-label-extreme">Total Kapasitas</div>
    </div>
</div>

<!-- ===== CHARTS ===== -->
<?php if (!empty($kategori_stats)): ?>
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3> Distribusi Kategori Fasilitas</h3>
        <div id="kategoriChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Ringkasan Fasilitas</h3>
        <div id="ringkasanChart"></div>
    </div>
</div>
<?php endif; ?>

<!-- ===== TOOLBAR ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama fasilitas atau deskripsi..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="kategoriFilter">
        <option value="">Semua Kategori</option>
        <option value="Laboratorium" <?= $kategori_filter === 'Laboratorium' ? 'selected' : '' ?>>🧪 Laboratorium</option>
        <option value="Ruang Kelas" <?= $kategori_filter === 'Ruang Kelas' ? 'selected' : '' ?>>🏫 Ruang Kelas</option>
        <option value="Perpustakaan" <?= $kategori_filter === 'Perpustakaan' ? 'selected' : '' ?>>📚 Perpustakaan</option>
        <option value="Fasilitas Umum" <?= $kategori_filter === 'Fasilitas Umum' ? 'selected' : '' ?>>🏢 Fasilitas Umum</option>
        <option value="Lainnya" <?= $kategori_filter === 'Lainnya' ? 'selected' : '' ?>>📌 Lainnya</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Non-Aktif" <?= $status_filter === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
    </select>
    <a href="fasilitas-form.php" class="btn-action primary">➕ Tambah Fasilitas</a>
</div>

<!-- ===== FACILITY GRID ===== -->
<div class="facility-grid" data-aos="fade-up">
    <?php if (empty($fasilitas_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">🏢</div>
            <h3>Belum ada data fasilitas</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan fasilitas dan laboratorium kampus.</p>
            <a href="fasilitas-form.php" class="btn-action primary" style="margin-top: 1rem;">➕ Tambah Fasilitas Pertama</a>
        </div>
    <?php else: foreach ($fasilitas_list as $f): 
        $icon = $f['kategori'] === 'Laboratorium' ? '' : ($f['kategori'] === 'Perpustakaan' ? '📚' : ($f['kategori'] === 'Ruang Kelas' ? '' : '🏢'));
        $kategori_class = 'badge-' . strtolower(str_replace(' ', '-', $f['kategori'] ?? 'lainnya'));
        $status_class = strtolower($f['status']) === 'aktif' ? 'aktif' : 'non-aktif';
    ?>
    <div class="facility-card">
        <div class="facility-image">
            <?php if (!empty($f['gambar'])): ?>
                <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" alt="<?= sanitize($f['nama']) ?>">
            <?php else: ?>
                <span><?= $icon ?></span>
            <?php endif; ?>
            <span class="facility-kategori-badge badge-<?= $kategori_class ?>"><?= $icon ?> <?= sanitize($f['kategori']) ?></span>
            <span class="facility-status-badge <?= $status_class ?>"><?= $f['status'] ?></span>
        </div>
        <div class="facility-content">
            <h3 class="facility-title"><?= sanitize($f['nama']) ?></h3>
            <p class="facility-desc"><?= excerpt($f['deskripsi'] ?? 'Tidak ada deskripsi', 120) ?></p>
            <div class="facility-meta">
                <span class="facility-kapasitas">👥 Kapasitas: <?= $f['kapasitas'] ?> orang</span>
                <div class="action-buttons">
                    <a href="fasilitas-form.php?id=<?= $f['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Ubah status fasilitas ini?')">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <input type="hidden" name="action" value="toggle">
                        <button class="btn-icon toggle" title="Toggle Status">🔄</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus fasilitas ini?')">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn-icon delete" title="Hapus">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
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
<?php if (!empty($kategori_stats)): ?>
new ApexCharts(document.querySelector("#kategoriChart"), {
    series: <?= json_encode(array_values($kategori_stats)) ?>,
    labels: <?= json_encode(array_keys($kategori_stats)) ?>,
    chart: { type: 'donut', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#6b7280'],
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $stat_aktif ?> } } } } },
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();

new ApexCharts(document.querySelector("#ringkasanChart"), {
    series: [<?= $stat_lab ?>, <?= $stat_kelas ?>, <?= $stat_perpus ?>, <?= max(0, $stat_aktif - $stat_lab - $stat_kelas - $stat_perpus) ?>],
    labels: ['Laboratorium', 'Ruang Kelas', 'Perpustakaan', 'Lainnya'],
    chart: { type: 'bar', height: 280, animations: { enabled: true, speed: 800 } },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#6b7280'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '60%' } },
    dataLabels: { enabled: false },
    xaxis: { labels: { style: { fontSize: '11px' } } },
    yaxis: { title: { text: 'Jumlah' } }
}).render();
<?php endif; ?>

// Search & Filter
const searchInput = document.getElementById('searchInput');
const kategoriFilter = document.getElementById('kategoriFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
kategoriFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value;
    const kategori = kategoriFilter.value;
    const status = statusFilter.value;
    
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (kategori) url.searchParams.set('kategori', kategori); else url.searchParams.delete('kategori');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    
    window.location = url;
}

console.log('%c🏢 Kelola Fasilitas FKIP UNIMOF', 'color: #8b5cf6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>