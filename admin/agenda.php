<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM agenda WHERE id = ?")->execute([$id]);
        flash_message('success', 'Agenda berhasil dihapus.');
    } elseif ($action === 'bulk_delete' && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM agenda WHERE id IN ($placeholders)")->execute($ids);
        flash_message('success', count($ids) . ' agenda berhasil dihapus.');
    } elseif ($action === 'bulk_status' && !empty($ids)) {
        $status = $_POST['new_status'] ?? 'Aktif';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status], $ids);
        $pdo->prepare("UPDATE agenda SET status = ? WHERE id IN ($placeholders)")->execute($params);
        flash_message('success', 'Status ' . count($ids) . ' agenda diperbarui.');
    }
    header('Location: agenda.php?' . http_build_query($_GET));
    exit;
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';
$view_mode = $_GET['view'] ?? 'calendar';
$month_filter = (int)($_GET['month'] ?? date('n'));
$year_filter = (int)($_GET['year'] ?? date('Y'));

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND judul LIKE ?'; $params[] = "%$q%"; }
if ($jenis_filter !== '') { $where .= ' AND jenis = ?'; $params[] = $jenis_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$agenda = $pdo->prepare("SELECT * FROM agenda $where ORDER BY tanggal_mulai DESC");
$agenda->execute($params);
$agenda_list = $agenda->fetchAll();

// ===== STATISTIK =====
$total_agenda = count($agenda_list);
$agenda_bulan_ini = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE())")->fetchColumn();
$agenda_mendatang = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif'")->fetchColumn();
$agenda_selesai = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE status = 'Selesai'")->fetchColumn();

// Agenda terdekat
$stmt_next = $pdo->query("SELECT * FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif' ORDER BY tanggal_mulai ASC LIMIT 1");
$agenda_terdekat = $stmt_next->fetch();

// Statistik per jenis
$jenis_stats = $pdo->query("SELECT jenis, COUNT(*) as total FROM agenda GROUP BY jenis ORDER BY total DESC")->fetchAll();

// Statistik per bulan (12 bulan terakhir)
$bulan_stats = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agenda WHERE DATE_FORMAT(tanggal_mulai, '%Y-%m') = ?");
    $stmt->execute([$date]);
    $bulan_stats[] = [
        'bulan' => date('M Y', strtotime($date)),
        'total' => (int)$stmt->fetchColumn()
    ];
}

$csrf = generate_csrf_token();
$active_menu = 'agenda';
$page_heading = 'Kelola Agenda';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Agenda', null]];

require __DIR__ . '/includes/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Stats Bar */
.agenda-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.agenda-stat-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; position: relative; overflow: hidden; transition: all 0.3s; }
.agenda-stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--stat-color, var(--primary)); }
.agenda-stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.agenda-stat-icon { font-size: 2rem; margin-bottom: 0.75rem; }
.agenda-stat-num { font-family: var(--font-display); font-size: 2.25rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.agenda-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
.agenda-stat-trend { font-size: 0.75rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.3rem; }
.agenda-stat-trend.up { color: #10b981; }
.agenda-stat-trend.down { color: #ef4444; }

/* Toolbar */
.agenda-toolbar { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
.agenda-search { flex: 1; min-width: 250px; position: relative; }
.agenda-search input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; transition: all 0.3s; }
.agenda-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.agenda-search .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.agenda-filter { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary); cursor: pointer; }
.agenda-filter:focus { outline: none; border-color: var(--primary); }
.view-toggle { display: flex; background: var(--bg-secondary); border-radius: var(--radius-md); padding: 0.25rem; }
.view-btn { padding: 0.6rem 1rem; border-radius: 8px; border: none; background: transparent; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); transition: all 0.2s; display: flex; align-items: center; gap: 0.4rem; }
.view-btn.active { background: var(--primary); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); }

/* Bulk Action Bar */
.bulk-bar { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; display: none; align-items: center; gap: 1rem; flex-wrap: wrap; animation: slideDown 0.3s ease; }
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: var(--primary); padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.85rem; font-weight: 800; }
.bulk-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem; transition: all 0.2s; }
.bulk-btn:hover { transform: translateY(-2px); }
.bulk-btn.primary { background: white; color: var(--primary); }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* Calendar View */
.calendar-view { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem; }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.calendar-nav { display: flex; gap: 0.5rem; align-items: center; }
.calendar-nav button { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); cursor: pointer; font-family: inherit; font-weight: 600; transition: all 0.2s; }
.calendar-nav button:hover { background: var(--primary); color: white; border-color: var(--primary); }
.calendar-month { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; }
.calendar-day-header { text-align: center; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 0.5rem; }
.calendar-day { aspect-ratio: 1; background: var(--bg-secondary); border-radius: 8px; padding: 0.5rem; position: relative; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
.calendar-day:hover { background: var(--bg-tertiary); transform: scale(1.05); }
.calendar-day.other-month { opacity: 0.3; }
.calendar-day.today { border-color: var(--primary); background: rgba(10,104,71,0.05); }
.calendar-day-number { font-size: 0.85rem; font-weight: 700; margin-bottom: 0.25rem; }
.calendar-event { font-size: 0.65rem; padding: 0.15rem 0.3rem; border-radius: 4px; margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
.event-ujian { background: #fee2e2; color: #dc2626; }
.event-seminar { background: #dbeafe; color: #2563eb; }
.event-wisuda { background: #fef3c7; color: #d97706; }
.event-libur { background: #e0e7ff; color: #4338ca; }
.event-pmb { background: #dcfce7; color: #16a34a; }
.event-umum { background: #f3f4f6; color: #4b5563; }

/* List/Timeline View */
.list-view { display: flex; flex-direction: column; gap: 1rem; }
.agenda-group { margin-bottom: 2rem; }
.agenda-group-header { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: 0.5rem; }
.agenda-group-count { background: var(--primary); color: white; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.agenda-item { display: flex; gap: 1.25rem; padding: 1.25rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.3s; position: relative; }
.agenda-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.agenda-item.unread { border-left: 4px solid var(--primary); }
.agenda-item-checkbox { display: flex; align-items: center; }
.agenda-item-checkbox input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
.agenda-date-badge { width: 70px; flex-shrink: 0; text-align: center; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: var(--radius-md); padding: 0.75rem 0.5rem; }
.agenda-date-day { font-size: 1.75rem; font-weight: 900; line-height: 1; }
.agenda-date-month { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; opacity: 0.9; }
.agenda-content { flex: 1; min-width: 0; }
.agenda-content h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); }
.agenda-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem; }
.agenda-meta span { display: flex; align-items: center; gap: 0.3rem; }
.agenda-desc { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0.75rem; }
.agenda-actions { display: flex; gap: 0.5rem; }
.act-btn { width: 34px; height: 34px; border-radius: 8px; background: var(--bg-secondary); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 0.9rem; }
.act-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.act-btn.edit:hover { background: #3b82f6; color: white; }
.act-btn.danger:hover { background: #dc2626; color: white; }
.act-btn.status:hover { background: #10b981; color: white; }

/* Empty State */
.empty-state-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-large { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
.empty-state-premium h3 { font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-primary); }
.empty-state-premium p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

/* Chart Container */
.chart-container { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem; }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.chart-header h3 { font-size: 1.1rem; font-weight: 700; }

/* Responsive */
@media (max-width: 768px) {
    .agenda-stats { grid-template-columns: repeat(2, 1fr); }
    .calendar-grid { gap: 0.25rem; }
    .calendar-day { padding: 0.25rem; }
    .calendar-event { font-size: 0.55rem; }
    .agenda-item { flex-direction: column; }
    .agenda-date-badge { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; }
    .agenda-date-day { font-size: 1.25rem; }
}
</style>

<!-- ===== STATS BAR ===== -->
<div class="agenda-stats" data-aos="fade-down">
    <div class="agenda-stat-card" style="--stat-color: #3b82f6;">
        <div class="agenda-stat-icon">📅</div>
        <div class="agenda-stat-num count-up" data-target="<?= $total_agenda ?>">0</div>
        <div class="agenda-stat-label">Total Agenda</div>
        <div class="agenda-stat-trend up">↑ Semua waktu</div>
    </div>
    <div class="agenda-stat-card" style="--stat-color: #10b981;">
        <div class="agenda-stat-icon">📆</div>
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_bulan_ini ?>">0</div>
        <div class="agenda-stat-label">Bulan Ini</div>
        <div class="agenda-stat-trend up">↑ <?= date('F Y') ?></div>
    </div>
    <div class="agenda-stat-card" style="--stat-color: #f59e0b;">
        <div class="agenda-stat-icon">⏰</div>
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_mendatang ?>">0</div>
        <div class="agenda-stat-label">Mendatang</div>
        <?php if ($agenda_terdekat): ?>
            <div class="agenda-stat-trend up">️ <?= time_ago(strtotime($agenda_terdekat['tanggal_mulai'])) ?></div>
        <?php endif; ?>
    </div>
    <div class="agenda-stat-card" style="--stat-color: #64748b;">
        <div class="agenda-stat-icon">✅</div>
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_selesai ?>">0</div>
        <div class="agenda-stat-label">Selesai</div>
        <div class="agenda-stat-trend down">↓已完成</div>
    </div>
</div>

<!-- ===== CHART ===== -->
<div class="chart-container" data-aos="fade-up">
    <div class="chart-header">
        <h3>📊 Statistik Agenda (12 Bulan Terakhir)</h3>
    </div>
    <div id="agendaChart"></div>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="agenda-toolbar" data-aos="fade-up">
    <div class="agenda-search">
        <span class="search-icon">🔍</span>
        <input type="text" id="agendaSearch" placeholder="Cari judul agenda..." value="<?= sanitize($q) ?>">
    </div>
    <select class="agenda-filter" id="jenisFilter">
        <option value="">Semua Jenis</option>
        <option value="ujian" <?= $jenis_filter === 'ujian' ? 'selected' : '' ?>>📝 Ujian</option>
        <option value="seminar" <?= $jenis_filter === 'seminar' ? 'selected' : '' ?>>🎤 Seminar</option>
        <option value="wisuda" <?= $jenis_filter === 'wisuda' ? 'selected' : '' ?>>🎓 Wisuda</option>
        <option value="libur" <?= $jenis_filter === 'libur' ? 'selected' : '' ?>>️ Libur</option>
        <option value="pmb" <?= $jenis_filter === 'pmb' ? 'selected' : '' ?>>📝 PMB</option>
        <option value="umum" <?= $jenis_filter === 'umum' ? 'selected' : '' ?>>📌 Umum</option>
    </select>
    <select class="agenda-filter" id="statusFilter">
        <option value="">Semua Status</option>
        <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
        <option value="Selesai" <?= $status_filter === 'Selesai' ? 'selected' : '' ?>>✔️ Selesai</option>
        <option value="Dibatalkan" <?= $status_filter === 'Dibatalkan' ? 'selected' : '' ?>>❌ Dibatalkan</option>
    </select>
    <div class="view-toggle">
        <button class="view-btn <?= $view_mode === 'calendar' ? 'active' : '' ?>" onclick="switchView('calendar')">📅 Kalender</button>
        <button class="view-btn <?= $view_mode === 'list' ? 'active' : '' ?>" onclick="switchView('list')">📋 List</button>
    </div>
    <a href="agenda-form.php" class="btn-sm" style="padding: 0.75rem 1.25rem;">+ Tambah Agenda</a>
    <button class="btn-sm gray" onclick="exportAgenda('csv')" style="padding: 0.75rem 1rem;">📥 CSV</button>
    <button class="btn-sm gray" onclick="exportAgenda('ics')" style="padding: 0.75rem 1rem;">📆 ICS</button>
</div>

<!-- ===== BULK ACTION BAR ===== -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-info">
        <span class="bulk-count" id="bulkCount">0</span>
        <span>agenda dipilih</span>
    </div>
    <form method="POST" id="bulkForm" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="bulk-actions">
            <select name="new_status" class="bulk-btn primary" style="cursor: pointer;">
                <option value="Aktif">✅ Aktif</option>
                <option value="Selesai">✔️ Selesai</option>
                <option value="Dibatalkan">❌ Dibatalkan</option>
            </select>
            <button type="submit" name="action" value="bulk_status" class="bulk-btn primary">Ubah Status</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus agenda terpilih?')">️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($agenda_list)): ?>
    <div class="empty-state-premium" data-aos="fade-up">
        <div class="empty-icon-large">📅</div>
        <h3>Belum Ada Agenda</h3>
        <p>Mulai tambahkan agenda pertama Anda untuk mengelola jadwal kegiatan fakultas dengan lebih terorganisir.</p>
        <a href="agenda-form.php" class="btn-sm" style="padding: 0.75rem 1.5rem; font-size: 1rem;">+ Tambah Agenda Pertama</a>
    </div>
<?php else: ?>
    <?php if ($view_mode === 'calendar'): ?>
        <!-- CALENDAR VIEW -->
        <div class="calendar-view" data-aos="fade-up">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button onclick="changeMonth(-1)">← Prev</button>
                    <span class="calendar-month"><?= date('F Y', mktime(0, 0, 0, $month_filter, 1, $year_filter)) ?></span>
                    <button onclick="changeMonth(1)">Next →</button>
                </div>
                <button class="btn-sm gray" onclick="resetToCurrentMonth()">Hari Ini</button>
            </div>
            <div class="calendar-grid" id="calendarGrid">
                <!-- Day Headers -->
                <div class="calendar-day-header">Min</div>
                <div class="calendar-day-header">Sen</div>
                <div class="calendar-day-header">Sel</div>
                <div class="calendar-day-header">Rab</div>
                <div class="calendar-day-header">Kam</div>
                <div class="calendar-day-header">Jum</div>
                <div class="calendar-day-header">Sab</div>
                <!-- Calendar days will be generated by JavaScript -->
            </div>
        </div>
    <?php else: ?>
        <!-- LIST/TIMELINE VIEW -->
        <div class="list-view" data-aos="fade-up">
            <?php
            // Group by month
            $grouped = [];
            foreach ($agenda_list as $a) {
                $month_key = date('F Y', strtotime($a['tanggal_mulai']));
                $grouped[$month_key][] = $a;
            }
            
            foreach ($grouped as $month => $items):
            ?>
            <div class="agenda-group">
                <div class="agenda-group-header">
                    📆 <?= $month ?>
                    <span class="agenda-group-count"><?= count($items) ?></span>
                </div>
                <?php foreach ($items as $a): ?>
                <div class="agenda-item" data-id="<?= $a['id'] ?>">
                    <div class="agenda-item-checkbox">
                        <input type="checkbox" class="agenda-checkbox" value="<?= $a['id'] ?>" onchange="updateBulkCount()">
                    </div>
                    <div class="agenda-date-badge">
                        <div class="agenda-date-day"><?= date('d', strtotime($a['tanggal_mulai'])) ?></div>
                        <div class="agenda-date-month"><?= date('M', strtotime($a['tanggal_mulai'])) ?></div>
                    </div>
                    <div class="agenda-content">
                        <h3><?= sanitize($a['judul']) ?></h3>
                        <div class="agenda-meta">
                            <span class="badge-ultimate badge-<?= strtolower($a['jenis']) ?>"><?= ucfirst($a['jenis']) ?></span>
                            <span>📍 <?= sanitize($a['lokasi'] ?? 'Tidak ada lokasi') ?></span>
                            <span>🕐 <?= format_tanggal_range($a['tanggal_mulai'], $a['tanggal_selesai'] ?? null) ?></span>
                        </div>
                        <?php if (!empty($a['deskripsi'])): ?>
                            <p class="agenda-desc"><?= excerpt($a['deskripsi'], 100) ?></p>
                        <?php endif; ?>
                        <div class="agenda-actions">
                            <a href="agenda-form.php?id=<?= $a['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                            <button class="act-btn status" onclick="quickStatusChange(<?= $a['id'] ?>, '<?= $a['status'] ?>')" title="Ubah Status">🔄</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus agenda ini?')">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="act-btn danger" title="Hapus">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ===== JAVASCRIPT ===== -->
<script>
// ===== COUNT UP ANIMATION =====
function animateCount(el) {
    const target = parseInt(el.dataset.target) || 0;
    const duration = 1500;
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
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== APEXCHARTS: BAR CHART =====
const chartOptions = {
    series: [{
        name: 'Jumlah Agenda',
        data: <?= json_encode(array_column($bulan_stats, 'total')) ?>
    }],
    chart: {
        type: 'bar',
        height: 250,
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    plotOptions: {
        bar: { borderRadius: 8, columnWidth: '60%' }
    },
    colors: ['#0a6847'],
    dataLabels: { enabled: false },
    xaxis: {
        categories: <?= json_encode(array_column($bulan_stats, 'bulan')) ?>,
        labels: { style: { fontSize: '11px' } }
    },
    yaxis: {
        title: { text: 'Jumlah Agenda' },
        min: 0,
        tickAmount: 5
    },
    grid: { borderColor: '#f1f5f9' },
    tooltip: {
        y: { formatter: (val) => val + ' agenda' }
    }
};
new ApexCharts(document.querySelector("#agendaChart"), chartOptions).render();

// ===== VIEW SWITCHING =====
function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

// ===== CALENDAR GENERATION =====
const currentMonth = <?= $month_filter ?>;
const currentYear = <?= $year_filter ?>;
const agendaData = <?= json_encode(array_map(function($a) {
    return [
        'id' => $a['id'],
        'judul' => $a['judul'],
        'tanggal_mulai' => $a['tanggal_mulai'],
        'jenis' => $a['jenis']
    ];
}, $agenda_list)) ?>;

function generateCalendar(month, year) {
    const grid = document.getElementById('calendarGrid');
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const today = new Date();
    
    let html = '';
    // Day headers
    ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'].forEach(day => {
        html += `<div class="calendar-day-header">${day}</div>`;
    });
    
    // Empty cells for previous month
    for (let i = 0; i < firstDay; i++) {
        html += '<div class="calendar-day other-month"></div>';
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = today.getDate() === day && today.getMonth() === month - 1 && today.getFullYear() === year;
        const dayAgendas = agendaData.filter(a => a.tanggal_mulai === dateStr);
        
        html += `<div class="calendar-day ${isToday ? 'today' : ''}" onclick="showDayAgendas('${dateStr}')">`;
        html += `<div class="calendar-day-number">${day}</div>`;
        dayAgendas.slice(0, 3).forEach(a => {
            html += `<div class="calendar-event event-${a.jenis}">${a.judul}</div>`;
        });
        if (dayAgendas.length > 3) {
            html += `<div class="calendar-event event-umum">+${dayAgendas.length - 3} lagi</div>`;
        }
        html += '</div>';
    }
    
    grid.innerHTML = html;
}

function changeMonth(delta) {
    let newMonth = currentMonth + delta;
    let newYear = currentYear;
    if (newMonth > 12) { newMonth = 1; newYear++; }
    if (newMonth < 1) { newMonth = 12; newYear--; }
    const url = new URL(window.location);
    url.searchParams.set('month', newMonth);
    url.searchParams.set('year', newYear);
    window.location = url;
}

function resetToCurrentMonth() {
    const url = new URL(window.location);
    url.searchParams.set('month', new Date().getMonth() + 1);
    url.searchParams.set('year', new Date().getFullYear());
    window.location = url;
}

function showDayAgendas(dateStr) {
    const dayAgendas = agendaData.filter(a => a.tanggal_mulai === dateStr);
    if (dayAgendas.length > 0) {
        const list = dayAgendas.map(a => `• ${a.judul}`).join('\n');
        alert(`Agenda pada ${dateStr}:\n\n${list}`);
    }
}

generateCalendar(currentMonth, currentYear);

// ===== BULK SELECTION =====
function updateBulkCount() {
    const checkboxes = document.querySelectorAll('.agenda-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);
    
    // Add hidden inputs to bulk form
    const bulkForm = document.getElementById('bulkForm');
    bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    checkboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        bulkForm.appendChild(input);
    });
}

function clearSelection() {
    document.querySelectorAll('.agenda-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== QUICK STATUS CHANGE =====
function quickStatusChange(id, currentStatus) {
    const statuses = ['Aktif', 'Selesai', 'Dibatalkan'];
    const currentIndex = statuses.indexOf(currentStatus);
    const newStatus = statuses[(currentIndex + 1) % statuses.length];
    
    if (confirm(`Ubah status agenda ini menjadi "${newStatus}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="ids[]" value="${id}">
            <input type="hidden" name="new_status" value="${newStatus}">
            <input type="hidden" name="action" value="bulk_status">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ===== SEARCH & FILTER =====
let searchTimeout;
document.getElementById('agendaSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        if (this.value) url.searchParams.set('q', this.value);
        else url.searchParams.delete('q');
        url.searchParams.set('halaman', '1');
        window.location = url;
    }, 500);
});

document.getElementById('jenisFilter').addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('jenis', this.value);
    else url.searchParams.delete('jenis');
    url.searchParams.set('halaman', '1');
    window.location = url;
});

document.getElementById('statusFilter').addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) url.searchParams.set('status', this.value);
    else url.searchParams.delete('status');
    url.searchParams.set('halaman', '1');
    window.location = url;
});

// ===== EXPORT FUNCTIONS =====
function exportAgenda(format) {
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    window.location = url;
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'agenda-form.php';
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('agendaSearch').focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        exportAgenda('csv');
    }
});

console.log('%c📅 Kelola Agenda FKIP UNIMOF', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+N (Tambah), Ctrl+F (Search), Ctrl+E (Export)', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>