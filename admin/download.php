<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT file_name FROM downloads WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if ($file) @unlink(APP_DIR . '/assets/downloads/' . $file);
        $pdo->prepare("DELETE FROM downloads WHERE id = ?")->execute([$id]);
        flash_message('success', 'File berhasil dihapus.');
    } elseif ($action === 'bulk_delete' && !empty($ids)) {
        $stmt = $pdo->prepare("SELECT file_name FROM downloads WHERE id IN (" . implode(',', $ids) . ")");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if ($f) @unlink(APP_DIR . '/assets/downloads/' . $f);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM downloads WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' file berhasil dihapus.');
    }
    header('Location: download.php?' . http_build_query($_GET));
    exit;
}

// ===== EXPORT CSV =====
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="downloads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Judul','Kategori','Ukuran','Downloads','Tanggal']);
    foreach ($pdo->query("SELECT * FROM downloads ORDER BY created_at DESC") as $r) {
        fputcsv($out, [$r['id'],$r['judul'],$r['kategori'],$r['file_size'],$r['downloads_count'],$r['created_at']]);
    }
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$kat = $_GET['kategori'] ?? '';
$type = $_GET['type'] ?? '';
$view = $_GET['view'] ?? 'list';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR deskripsi LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat !== '') { $where .= ' AND kategori = ?'; $params[] = $kat; }
if ($type !== '') {
    $ext_map = ['pdf' => 'pdf', 'doc' => 'doc', 'docx' => 'docx', 'zip' => 'zip', 'rar' => 'rar'];
    if (isset($ext_map[$type])) {
        $where .= ' AND file_name LIKE ?';
        $params[] = '%.' . $ext_map[$type];
    }
}

$stmt = $pdo->prepare("SELECT * FROM downloads $where ORDER BY created_at DESC");
$stmt->execute($params);
$downloads = $stmt->fetchAll();

// ===== STATISTIK =====
$total_files = count($downloads);
$total_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM downloads")->fetchColumn();
$total_size_bytes = 0;
$kat_stats = [];
$type_stats = ['pdf'=>0,'doc'=>0,'zip'=>0,'rar'=>0,'other'=>0];
$recent_downloads = [];

foreach ($downloads as $d) {
    $kat = $d['kategori'];
    $kat_stats[$kat] = ($kat_stats[$kat] ?? 0) + 1;
    
    $ext = strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['pdf','doc','docx','zip','rar'])) {
        if ($ext === 'docx') $type_stats['doc']++;
        else $type_stats[$ext]++;
    } else $type_stats['other']++;
    
    if ($d['downloads_count'] > 0) {
        $recent_downloads[] = $d;
    }
}
arsort($kat_stats);
arsort($type_stats);

$csrf = generate_csrf_token();
$active_menu = 'download';
$page_heading = 'Kelola Download Center';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Download Center', null]];

require __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* Stats Bar */
.dl-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.dl-stat-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; position: relative; overflow: hidden; transition: all 0.3s; }
.dl-stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--stat-color, var(--primary)); }
.dl-stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.dl-stat-icon { font-size: 2rem; margin-bottom: 0.75rem; }
.dl-stat-num { font-family: var(--font-display); font-size: 2.25rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.dl-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

/* Toolbar */
.dl-toolbar { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
.dl-search { flex: 1; min-width: 250px; position: relative; }
.dl-search input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; transition: all 0.3s; }
.dl-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.dl-search .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.dl-filter { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary); cursor: pointer; }
.dl-filter:focus { outline: none; border-color: var(--primary); }
.view-toggle { display: flex; background: var(--bg-secondary); border-radius: var(--radius-md); padding: 0.25rem; }
.view-btn { padding: 0.6rem 1rem; border-radius: 8px; border: none; background: transparent; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); transition: all 0.2s; display: flex; align-items: center; gap: 0.4rem; }
.view-btn.active { background: var(--primary); color: white; }

/* Bulk Bar */
.bulk-bar { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; display: none; align-items: center; gap: 1rem; flex-wrap: wrap; animation: slideDown 0.3s ease; }
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-count { background: white; color: var(--primary); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.85rem; font-weight: 800; }
.bulk-btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem; transition: all 0.2s; }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* Charts Row */
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.chart-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; }
.chart-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

/* Grid View */
.dl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
.dl-grid-item { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; transition: all 0.3s; position: relative; }
.dl-grid-item:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.dl-grid-item .file-icon-lg { width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem; }
.file-pdf { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
.file-doc { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
.file-zip { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
.file-rar { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #4338ca; }
.file-other { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; }
.dl-grid-item h4 { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.dl-grid-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem; }
.dl-grid-meta span { display: flex; align-items: center; gap: 0.25rem; }
.dl-grid-actions { display: flex; gap: 0.5rem; border-top: 1px solid var(--border); padding-top: 1rem; }
.dl-grid-actions .act-btn { flex: 1; }
.dl-grid-check { position: absolute; top: 1rem; right: 1rem; }
.dl-grid-check input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }

/* List View */
.dl-list { display: flex; flex-direction: column; gap: 0.75rem; }
.dl-list-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.3s; }
.dl-list-item:hover { background: var(--bg-secondary); border-color: var(--primary); }
.dl-list-item .file-icon-sm { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
.dl-list-info { flex: 1; min-width: 0; }
.dl-list-info h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dl-list-meta { display: flex; gap: 1rem; font-size: 0.78rem; color: var(--text-muted); flex-wrap: wrap; }
.dl-list-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }

/* Empty State */
.empty-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

/* Top Downloads */
.top-downloads { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; }
.top-downloads h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.top-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border-radius: 10px; transition: background 0.2s; margin-bottom: 0.5rem; }
.top-item:hover { background: var(--bg-secondary); }
.top-rank { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; }
.top-item:nth-child(2) .top-rank { background: linear-gradient(135deg, #94a3b8, #64748b); }
.top-item:nth-child(3) .top-rank { background: linear-gradient(135deg, #cd7f32, #b87333); }
.top-info { flex: 1; min-width: 0; }
.top-info h4 { font-size: 0.88rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.top-info small { font-size: 0.75rem; color: var(--text-muted); }
.top-count { font-weight: 800; color: var(--primary); font-size: 0.9rem; }

@media (max-width: 968px) {
    .charts-row { grid-template-columns: 1fr; }
    .dl-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== STATS BAR ===== -->
<div class="dl-stats" data-aos="fade-down">
    <div class="dl-stat-card" style="--stat-color: #3b82f6;">
        <div class="dl-stat-icon">📁</div>
        <div class="dl-stat-num"><?= $total_files ?></div>
        <div class="dl-stat-label">Total File</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #10b981;">
        <div class="dl-stat-icon">⬇️</div>
        <div class="dl-stat-num"><?= number_format($total_downloads) ?></div>
        <div class="dl-stat-label">Total Download</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #f59e0b;">
        <div class="dl-stat-icon">📊</div>
        <div class="dl-stat-num"><?= count($kat_stats) ?></div>
        <div class="dl-stat-label">Kategori Aktif</div>
    </div>
    <div class="dl-stat-card" style="--stat-color: #8b5cf6;">
        <div class="dl-stat-icon">🔥</div>
        <div class="dl-stat-num"><?= count($recent_downloads) ?></div>
        <div class="dl-stat-label">File Populer</div>
    </div>
</div>

<!-- ===== CHARTS ROW ===== -->
<div class="charts-row" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Kategori</h3>
        <div id="chartKategori"></div>
    </div>
    <div class="chart-card">
        <h3>📄 Tipe File</h3>
        <div id="chartTipe"></div>
    </div>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="dl-toolbar" data-aos="fade-up">
    <div class="dl-search">
        <span class="search-icon">🔍</span>
        <input type="text" id="dlSearch" placeholder="Cari judul atau deskripsi..." value="<?= sanitize($q) ?>">
    </div>
    <select class="dl-filter" id="katFilter">
        <option value="">Semua Kategori</option>
        <?php foreach (array_keys($kat_stats) as $k): ?>
            <option value="<?= sanitize($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= sanitize($k) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="dl-filter" id="typeFilter">
        <option value="">Semua Tipe</option>
        <option value="pdf" <?= $type === 'pdf' ? 'selected' : '' ?>>📕 PDF</option>
        <option value="doc" <?= $type === 'doc' ? 'selected' : '' ?>>📘 DOC/DOCX</option>
        <option value="zip" <?= $type === 'zip' ? 'selected' : '' ?>>📦 ZIP</option>
        <option value="rar" <?= $type === 'rar' ? 'selected' : '' ?>>🗜️ RAR</option>
    </select>
    <div class="view-toggle">
        <button class="view-btn <?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
        <button class="view-btn <?= $view === 'grid' ? 'active' : '' ?>" onclick="switchView('grid')"> Grid</button>
    </div>
    <a href="download-form.php" class="btn-sm" style="padding: 0.75rem 1.25rem;">+ Upload File</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-sm gray" style="padding: 0.75rem 1rem;">📥 CSV</a>
</div>

<!-- ===== BULK BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <span style="font-weight: 700;"><span class="bulk-count" id="bulkCount">0</span> file dipilih</span>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus file terpilih?')">️ Hapus Terpilih</button>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($downloads)): ?>
    <div class="empty-premium" data-aos="fade-up">
        <div class="empty-icon-lg">📂</div>
        <h3>Belum Ada File</h3>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0.5rem auto 1.5rem;">Mulai upload dokumen pertama Anda untuk dibagikan ke pengunjung website.</p>
        <a href="download-form.php" class="btn-sm" style="padding: 0.75rem 1.5rem; font-size: 1rem;">+ Upload File Pertama</a>
    </div>
<?php else: ?>
    <?php if ($view === 'grid'): ?>
        <!-- GRID VIEW -->
        <div class="dl-grid" data-aos="fade-up">
            <?php foreach ($downloads as $d): 
                $ext = strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION));
                $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip']) ? 'file-zip' : (in_array($ext, ['rar']) ? 'file-rar' : 'file-other')));
                $file_icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['zip']) ? '📦' : (in_array($ext, ['rar']) ? '🗜️' : '')));
            ?>
            <div class="dl-grid-item">
                <div class="dl-grid-check">
                    <input type="checkbox" class="dl-checkbox" value="<?= $d['id'] ?>" onchange="updateBulk()">
                </div>
                <div class="file-icon-lg <?= $file_class ?>"><?= $file_icon ?></div>
                <h4><?= sanitize($d['judul']) ?></h4>
                <div class="dl-grid-meta">
                    <span class="badge-ultimate badge-info"><?= sanitize($d['kategori']) ?></span>
                    <span>💾 <?= sanitize($d['file_size']) ?></span>
                    <span>⬇️ <?= number_format($d['downloads_count']) ?></span>
                </div>
                <div class="dl-grid-actions">
                    <a href="download-form.php?id=<?= $d['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                    <a href="<?= base_url('assets/downloads/' . urlencode($d['file_name'])) ?>" class="act-btn" title="Download" download>⬇️</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus file ini?')">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="act-btn danger" title="Hapus">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- LIST VIEW -->
        <div class="dl-list" data-aos="fade-up">
            <?php foreach ($downloads as $d): 
                $ext = strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION));
                $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip']) ? 'file-zip' : (in_array($ext, ['rar']) ? 'file-rar' : 'file-other')));
                $file_icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['zip']) ? '📦' : (in_array($ext, ['rar']) ? '🗜️' : '📄')));
            ?>
            <div class="dl-list-item">
                <input type="checkbox" class="dl-checkbox" value="<?= $d['id'] ?>" onchange="updateBulk()" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);">
                <div class="file-icon-sm <?= $file_class ?>"><?= $file_icon ?></div>
                <div class="dl-list-info">
                    <h4><?= sanitize($d['judul']) ?></h4>
                    <div class="dl-list-meta">
                        <span class="badge-ultimate badge-info"><?= sanitize($d['kategori']) ?></span>
                        <span>💾 <?= sanitize($d['file_size']) ?></span>
                        <span>⬇️ <?= number_format($d['downloads_count']) ?>x</span>
                        <span> <?= date('d M Y', strtotime($d['created_at'])) ?></span>
                    </div>
                </div>
                <div class="dl-list-actions">
                    <a href="download-form.php?id=<?= $d['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                    <a href="<?= base_url('assets/downloads/' . urlencode($d['file_name'])) ?>" class="act-btn" title="Download" download>️</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus file ini?')">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="act-btn danger" title="Hapus">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// ===== APEXCHARTS =====
const katLabels = <?= json_encode(array_keys($kat_stats)) ?>;
const katData = <?= json_encode(array_values($kat_stats)) ?>;
const typeLabels = ['PDF', 'DOC/DOCX', 'ZIP', 'RAR', 'Lainnya'];
const typeData = [<?= $type_stats['pdf'] ?>, <?= $type_stats['doc'] ?>, <?= $type_stats['zip'] ?>, <?= $type_stats['rar'] ?>, <?= $type_stats['other'] ?>];

new ApexCharts(document.querySelector("#chartKategori"), {
    series: katData,
    labels: katLabels,
    chart: { type: 'donut', height: 250, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $total_files ?> } } } } },
    dataLabels: { enabled: false },
    legend: { position: 'bottom' }
}).render();

new ApexCharts(document.querySelector("#chartTipe"), {
    series: typeData,
    labels: typeLabels,
    chart: { type: 'pie', height: 250, animations: { enabled: true, speed: 800 } },
    colors: ['#dc2626', '#2563eb', '#d97706', '#4338ca', '#6b7280'],
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();

// ===== VIEW SWITCHING =====
function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== BULK SELECTION =====
function updateBulk() {
    const count = document.querySelectorAll('.dl-checkbox:checked').length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);
    
    const bulkForm = document.getElementById('bulkForm');
    bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    document.querySelectorAll('.dl-checkbox:checked').forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        bulkForm.appendChild(input);
    });
}

function clearSelection() {
    document.querySelectorAll('.dl-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== SEARCH & FILTER =====
let searchTimeout;
document.getElementById('dlSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        if (this.value) url.searchParams.set('q', this.value);
        else url.searchParams.delete('q');
        url.searchParams.set('halaman', '1');
        window.location = url;
    }, 500);
});

document.getElementById('katFilter').addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('kategori', this.value);
    else url.searchParams.delete('kategori');
    window.location = url;
});

document.getElementById('typeFilter').addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('type', this.value);
    else url.searchParams.delete('type');
    window.location = url;
});

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'download-form.php';
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('dlSearch').focus();
    }
});

console.log('%c📥 Download Center Admin', 'color: #0a6847; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>