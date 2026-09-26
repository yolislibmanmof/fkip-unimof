<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    
    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("SELECT sertifikat_file FROM akreditasi WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        delete_upload($file, 'akreditasi');
        $pdo->prepare("DELETE FROM akreditasi WHERE id = ?")->execute([$id]);
        flash_message('success', 'Data akreditasi berhasil dihapus.');
    } elseif ($action === 'bulk_delete' && !empty($ids)) {
        foreach ($ids as $del_id) {
            $stmt = $pdo->prepare("SELECT sertifikat_file FROM akreditasi WHERE id = ?");
            $stmt->execute([$del_id]);
            $file = $stmt->fetchColumn();
            delete_upload($file, 'akreditasi');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM akreditasi WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' data akreditasi berhasil dihapus.');
    } elseif ($action === 'export') {
        // Export CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akreditasi-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Program Studi', 'Badan Akreditasi', 'Peringkat', 'Nomor SK', 'Tanggal Terbit', 'Tanggal Berlaku', 'Status']);
        foreach ($akreditasi_list as $r) {
            fputcsv($out, [$r['nama_prodi'], $r['badan_akreditasi'], $r['peringkat'], $r['nomor_sk'], $r['tanggal_terbit'], $r['tanggal_berlaku'], $r['status']]);
        }
        exit;
    }
    header('Location: akreditasi.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$peringkat_filter = $_GET['peringkat'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (nama_prodi LIKE ? OR nomor_sk LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($peringkat_filter !== '') { $where .= ' AND peringkat = ?'; $params[] = $peringkat_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$stmt = $pdo->prepare("SELECT * FROM akreditasi $where ORDER BY FIELD(peringkat, 'Unggul', 'Baik Sekali', 'Baik', 'C', 'Proses Akreditasi'), tanggal_berlaku DESC");
$stmt->execute($params);
$akreditasi_list = $stmt->fetchAll();

// ===== STATISTIK LENGKAP =====
$stat_total = count($akreditasi_list);
$stat_unggul = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Unggul'")->fetchColumn();
$stat_baik = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Baik Sekali'")->fetchColumn();
$stat_baik_c = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE peringkat='Baik'")->fetchColumn();
$stat_aktif = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE status='Aktif'")->fetchColumn();
$stat_kadaluarsa = (int)$pdo->query("SELECT COUNT(*) FROM akreditasi WHERE status='Kadaluarsa'")->fetchColumn();

// Data untuk chart
$chart_data = [
    'Unggul' => $stat_unggul,
    'Baik Sekali' => $stat_baik,
    'Baik' => $stat_baik_c,
    'Lainnya' => max(0, $stat_total - $stat_unggul - $stat_baik - $stat_baik_c)
];

// Akreditasi yang akan kadaluarsa dalam 6 bulan
$expiring_soon = $pdo->query("SELECT * FROM akreditasi WHERE tanggal_berlaku BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH) AND status='Aktif' ORDER BY tanggal_berlaku ASC")->fetchAll();

$csrf = generate_csrf_token();
$active_menu = 'akreditasi';
$page_heading = 'Kelola Akreditasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Akreditasi', null]];

require __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== STATS EXTREME ===== */
.stats-extreme {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}
.stat-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.75rem;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
}
.stat-card-extreme::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color, var(--primary)), transparent);
}
.stat-card-extreme:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--stat-color, var(--primary));
}
.stat-icon-extreme {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: inline-block;
    animation: bounce 2s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.stat-number-extreme {
    font-family: var(--font-display);
    font-size: 3rem;
    font-weight: 900;
    color: var(--stat-color, var(--primary));
    line-height: 1;
    margin-bottom: 0.5rem;
}
.stat-label-extreme {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.stat-trend {
    font-size: 0.75rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.stat-trend.up { color: #10b981; }
.stat-trend.down { color: #ef4444; }

/* ===== TOOLBAR EXTREME ===== */
.toolbar-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}
.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: var(--bg-secondary);
}
.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
    background: var(--bg-primary);
}
.search-box .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}
.filter-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.9rem;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.3s;
}
.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}
.btn-action {
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
}
.btn-action.primary {
    background: var(--primary);
    color: white;
}
.btn-action.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}
.btn-action.secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}
.btn-action.secondary:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

/* ===== CHART SECTION ===== */
.chart-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.chart-card h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ===== EXPIRING SOON ALERT ===== */
.expiring-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.5s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
.expiring-icon {
    font-size: 2rem;
    flex-shrink: 0;
}
.expiring-content {
    flex: 1;
}
.expiring-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #92400e;
    margin-bottom: 0.25rem;
}
.expiring-content p {
    font-size: 0.85rem;
    color: #78350f;
    margin: 0;
}

/* ===== TABLE EXTREME ===== */
.table-container {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.table-header h2 {
    font-size: 1.25rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.table-wrapper {
    overflow-x: auto;
}
table.extreme {
    width: 100%;
    border-collapse: collapse;
}
table.extreme th {
    background: var(--bg-secondary);
    padding: 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
}
table.extreme td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
table.extreme tr {
    transition: all 0.2s;
}
table.extreme tbody tr:hover {
    background: var(--bg-secondary);
    transform: translateX(4px);
}
table.extreme tbody tr:last-child td {
    border-bottom: none;
}

/* ===== BADGES EXTREME ===== */
.badge-extreme {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-unggul {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #fcd34d;
}
.badge-baik-sekali {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    border: 1px solid #93c5fd;
}
.badge-baik {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
    border: 1px solid #86efac;
}
.badge-aktif {
    background: #dcfce7;
    color: #166534;
}
.badge-kadaluarsa {
    background: #fee2e2;
    color: #991b1b;
}
.badge-proses {
    background: #fef3c7;
    color: #92400e;
}

/* ===== PROGRESS BAR ===== */
.progress-bar-container {
    width: 100%;
    height: 6px;
    background: var(--bg-secondary);
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.5rem;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 999px;
    transition: width 1s ease;
}
.progress-bar-fill.warning {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}
.progress-bar-fill.danger {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

/* ===== ACTION BUTTONS ===== */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-icon.edit {
    background: #dbeafe;
    color: #2563eb;
}
.btn-icon.edit:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-2px);
}
.btn-icon.download {
    background: #dcfce7;
    color: #16a34a;
}
.btn-icon.download:hover {
    background: #16a34a;
    color: white;
    transform: translateY(-2px);
}
.btn-icon.delete {
    background: #fee2e2;
    color: #dc2626;
}
.btn-icon.delete:hover {
    background: #dc2626;
    color: white;
    transform: translateY(-2px);
}

/* ===== EMPTY STATE ===== */
.empty-state-extreme {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-xl);
    border: 2px dashed var(--border);
}
.empty-icon-extreme {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

/* ===== MODAL ===== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.85);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1.5rem;
}
.modal-overlay.show {
    display: flex;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-xl);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
}
@keyframes slideUp {
    from { transform: translateY(20px) scale(0.95); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.modal-header {
    padding: 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.modal-header h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}
.modal-body {
    padding: 2rem;
}
.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.25rem;
    color: white;
    transition: all 0.3s;
}
.modal-close:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

@media (max-width: 1024px) {
    .chart-section { grid-template-columns: 1fr; }
    .stats-extreme { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .stats-extreme { grid-template-columns: 1fr; }
    .toolbar-extreme { flex-direction: column; align-items: stretch; }
    .search-box { min-width: 100%; }
}
</style>

<!-- ===== STATS EXTREME ===== -->
<div class="stats-extreme" data-aos="fade-up">
    <div class="stat-card-extreme" style="--stat-color: #f59e0b;">
        <div class="stat-icon-extreme">🏆</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_total ?>">0</div>
        <div class="stat-label-extreme">Total Terakreditasi</div>
        <div class="stat-trend up">↑ Semua Prodi</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #10b981;">
        <div class="stat-icon-extreme">🥇</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_unggul ?>">0</div>
        <div class="stat-label-extreme">Peringkat Unggul</div>
        <div class="stat-trend up">↑ Terbaik</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #3b82f6;">
        <div class="stat-icon-extreme">🥈</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_baik ?>">0</div>
        <div class="stat-label-extreme">Baik Sekali</div>
        <div class="stat-trend up">↑ Sangat Baik</div>
    </div>
    <div class="stat-card-extreme" style="--stat-color: #8b5cf6;">
        <div class="stat-icon-extreme">✅</div>
        <div class="stat-number-extreme count-up" data-target="<?= $stat_aktif ?>">0</div>
        <div class="stat-label-extreme">Status Aktif</div>
        <div class="stat-trend up">↑ Berjalan</div>
    </div>
</div>

<!-- ===== EXPIRING SOON ALERT ===== -->
<?php if (!empty($expiring_soon)): ?>
<div class="expiring-alert" data-aos="fade-down">
    <div class="expiring-icon">⚠️</div>
    <div class="expiring-content">
        <h4>Perhatian: <?= count($expiring_soon) ?> Akreditasi Akan Kadaluarsa!</h4>
        <p>Ada <?= count($expiring_soon) ?> program studi yang masa berlaku akreditasinya akan berakhir dalam 6 bulan ke depan. Segera lakukan perpanjangan.</p>
    </div>
    <button onclick="showExpiringModal()" class="btn-action primary" style="flex-shrink: 0;">Lihat Detail</button>
</div>
<?php endif; ?>

<!-- ===== CHART SECTION ===== -->
<div class="chart-section" data-aos="fade-up">
    <div class="chart-card">
        <h3>📊 Distribusi Peringkat Akreditasi</h3>
        <div id="peringkatChart"></div>
    </div>
    <div class="chart-card">
        <h3>📈 Status Akreditasi</h3>
        <div id="statusChart"></div>
    </div>
</div>

<!-- ===== TOOLBAR EXTREME ===== -->
<div class="toolbar-extreme" data-aos="fade-up">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari nama prodi atau nomor SK..." value="<?= sanitize($q) ?>">
    </div>
    <select class="filter-select" id="peringkatFilter">
        <option value="">Semua Peringkat</option>
        <option value="Unggul" <?= $peringkat_filter === 'Unggul' ? 'selected' : '' ?>>🥇 Unggul</option>
        <option value="Baik Sekali" <?= $peringkat_filter === 'Baik Sekali' ? 'selected' : '' ?>>🥈 Baik Sekali</option>
        <option value="Baik" <?= $peringkat_filter === 'Baik' ? 'selected' : '' ?>>🥉 Baik</option>
        <option value="C" <?= $peringkat_filter === 'C' ? 'selected' : '' ?>>C</option>
    </select>
    <select class="filter-select" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Kadaluarsa" <?= $status_filter === 'Kadaluarsa' ? 'selected' : '' ?>>❌ Kadaluarsa</option>
        <option value="Proses" <?= $status_filter === 'Proses' ? 'selected' : '' ?>>⏳ Proses</option>
    </select>
    
    <!-- TOMBOL TAMBAH AKREDITASI -->
    <a href="akreditasi-form.php" class="btn-action primary" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
        <span style="font-size: 1.2rem;"></span>
        <span>Tambah Akreditasi</span>
    </a>
    
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-action secondary">
        <span>📥</span>
        <span>Export CSV</span>
    </a>
</div>

<!-- ===== TABLE EXTREME ===== -->
<div class="table-container" data-aos="fade-up">
    <div class="table-header">
        <div>
            <h2>🏆 Data Akreditasi Program Studi</h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                Kelola data akreditasi semua program studi FKIP UNIMOF
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <span style="font-size: 0.85rem; color: var(--text-muted); background: var(--bg-secondary); padding: 0.5rem 1rem; border-radius: 999px;">
                📊 <?= count($akreditasi_list) ?> data ditemukan
            </span>
            <a href="akreditasi-form.php" class="btn-action primary" style="padding: 0.6rem 1.25rem;">
                <span>➕</span>
                <span>Tambah Data</span>
            </a>
        </div>
    </div>
    
    <?php if (empty($akreditasi_list)): ?>
        <div class="empty-state-extreme">
            <div class="empty-icon-extreme">📜</div>
            <h3>Belum ada data akreditasi</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Mulai tambahkan data akreditasi program studi Anda.</p>
            <a href="akreditasi-form.php" class="btn-action primary" style="margin-top: 1rem;">+ Tambah Data Pertama</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="extreme">
                <thead>
                    <tr>
                        <th>Program Studi</th>
                        <th>Badan</th>
                        <th>Peringkat</th>
                        <th>Masa Berlaku</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($akreditasi_list as $a): 
                    $badge = strtolower(str_replace(' ', '-', $a['peringkat']));
                    $is_expired = $a['tanggal_berlaku'] && strtotime($a['tanggal_berlaku']) < time();
                    $days_remaining = $a['tanggal_berlaku'] ? floor((strtotime($a['tanggal_berlaku']) - time()) / 86400) : null;
                    $badge_class = in_array($badge, ['unggul']) ? 'badge-unggul' : (in_array($badge, ['baik-sekali']) ? 'badge-baik-sekali' : (in_array($badge, ['baik']) ? 'badge-baik' : 'badge-proses'));
                    $status_class = strtolower($a['status']) === 'aktif' ? 'badge-aktif' : ($a['status'] === 'Proses' ? 'badge-proses' : 'badge-kadaluarsa');
                ?>
                <tr>
                    <td>
                        <strong style="color: var(--text-primary); font-size: 1rem;"><?= sanitize($a['nama_prodi']) ?></strong>
                        <br>
                        <small style="color: var(--text-muted); font-family: monospace; font-size: 0.8rem;">SK: <?= sanitize($a['nomor_sk'] ?: 'Belum ada') ?></small>
                    </td>
                    <td style="color: var(--text-secondary);"><?= sanitize($a['badan_akreditasi']) ?></td>
                    <td>
                        <span class="badge-extreme <?= $badge_class ?>">
                            <?= $a['peringkat'] === 'Unggul' ? '🥇' : ($a['peringkat'] === 'Baik Sekali' ? '🥈' : ($a['peringkat'] === 'Baik' ? '🥉' : '📋')) ?>
                            <?= sanitize($a['peringkat']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($a['tanggal_berlaku']): ?>
                            <div style="font-weight: 600; color: <?= $is_expired ? '#ef4444' : 'var(--text-primary)' ?>;">
                                <?= date('d M Y', strtotime($a['tanggal_berlaku'])) ?>
                                <?php if ($is_expired): ?>
                                    <span style="font-size: 0.7rem; display: block; color: #ef4444; font-weight: 700;">(Kadaluarsa)</span>
                                <?php elseif ($days_remaining !== null && $days_remaining <= 180): ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar-fill <?= $days_remaining <= 90 ? 'danger' : 'warning' ?>" style="width: <?= max(0, min(100, ($days_remaining / 180) * 100)) ?>%"></div>
                                    </div>
                                    <span style="font-size: 0.7rem; color: <?= $days_remaining <= 90 ? '#ef4444' : '#f59e0b' ?>; font-weight: 600;">
                                        <?= $days_remaining ?> hari lagi
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-extreme <?= $status_class ?>"><?= $a['status'] ?></span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (!empty($a['sertifikat_file'])): ?>
                                <a href="<?= asset('akreditasi/' . basename($a['sertifikat_file'])) ?>" target="_blank" class="btn-icon download" title="Unduh Sertifikat">📥</a>
                            <?php endif; ?>
                            <a href="akreditasi-form.php?id=<?= $a['id'] ?>" class="btn-icon edit" title="Edit">✏️</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Yakin ingin menghapus data akreditasi ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
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

<!-- ===== MODAL EXPIRING SOON ===== -->
<?php if (!empty($expiring_soon)): ?>
<div class="modal-overlay" id="expiringModal" onclick="if(event.target===this)closeExpiringModal()">
    <div class="modal-content">
        <button class="modal-close" onclick="closeExpiringModal()">✕</button>
        <div class="modal-header">
            <h3>⚠️ Akreditasi Akan Kadaluarsa</h3>
            <p style="opacity: 0.9; margin: 0;">Daftar program studi yang perlu segera diperpanjang</p>
        </div>
        <div class="modal-body">
            <?php foreach ($expiring_soon as $exp): 
                $days = floor((strtotime($exp['tanggal_berlaku']) - time()) / 86400);
            ?>
            <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); margin-bottom: 1rem; border-left: 4px solid <?= $days <= 90 ? '#ef4444' : '#f59e0b' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                    <strong style="font-size: 1rem;"><?= sanitize($exp['nama_prodi']) ?></strong>
                    <span class="badge-extreme badge-<?= $days <= 90 ? 'kadaluarsa' : 'proses' ?>" style="font-size: 0.7rem;">
                        <?= $days ?> hari lagi
                    </span>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Berlaku sampai: <strong><?= date('d M Y', strtotime($exp['tanggal_berlaku'])) ?></strong>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Peringkat: <strong><?= sanitize($exp['peringkat']) ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ===== COUNT UP ANIMATION =====
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
        if (entry.isIntersecting) {
            animateCount(entry.target);
            countObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== APEXCHARTS: PERINGKAT =====
const peringkatData = <?= json_encode($chart_data) ?>;
new ApexCharts(document.querySelector("#peringkatChart"), {
    series: Object.values(peringkatData),
    labels: Object.keys(peringkatData),
    chart: { type: 'donut', height: 250, animations: { enabled: true, speed: 800 } },
    colors: ['#fbbf24', '#3b82f6', '#10b981', '#6b7280'],
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => <?= $stat_total ?> } } } } },
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();

// ===== APEXCHARTS: STATUS =====
new ApexCharts(document.querySelector("#statusChart"), {
    series: [<?= $stat_aktif ?>, <?= $stat_kadaluarsa ?>, <?= max(0, $stat_total - $stat_aktif - $stat_kadaluarsa) ?>],
    labels: ['Aktif', 'Kadaluarsa', 'Proses'],
    chart: { type: 'pie', height: 250, animations: { enabled: true, speed: 800 } },
    colors: ['#10b981', '#ef4444', '#f59e0b'],
    dataLabels: { enabled: true, style: { fontSize: '12px' } },
    legend: { position: 'bottom' }
}).render();

// ===== SEARCH & FILTER =====
const searchInput = document.getElementById('searchInput');
const peringkatFilter = document.getElementById('peringkatFilter');
const statusFilter = document.getElementById('statusFilter');

let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});
peringkatFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);

function applyFilters() {
    const q = searchInput.value.toLowerCase();
    const peringkat = peringkatFilter.value;
    const status = statusFilter.value;
    
    const url = new URL(window.location);
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (peringkat) url.searchParams.set('peringkat', peringkat); else url.searchParams.delete('peringkat');
    if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
    url.searchParams.set('halaman', '1');
    
    window.location = url;
}

// ===== MODAL FUNCTIONS =====
function showExpiringModal() {
    document.getElementById('expiringModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeExpiringModal() {
    document.getElementById('expiringModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeExpiringModal();
});

console.log('%c Kelola Akreditasi FKIP UNIMOF', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cFitur: Chart, Filter, Search, Export, Modal', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>