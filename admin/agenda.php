<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== AUTO-STATUS: agenda lewat tanggal otomatis jadi "Selesai" =====
$pdo->exec("UPDATE agenda SET status = 'Selesai' WHERE tanggal_mulai < CURDATE() AND status = 'Aktif'");

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

// ===== EXPORT HANDLER =====
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'ics'])) {
    $format = $_GET['export'];
    $stmt = $pdo->query("SELECT * FROM agenda ORDER BY tanggal_mulai DESC");
    $data = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agenda-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($out, ['ID', 'Judul', 'Tanggal Mulai', 'Tanggal Selesai', 'Jenis', 'Lokasi', 'Status', 'Deskripsi']);
        foreach ($data as $r) {
            fputcsv($out, [$r['id'], $r['judul'], $r['tanggal_mulai'], $r['tanggal_selesai'], $r['jenis'], $r['lokasi'], $r['status'], $r['deskripsi']]);
        }
        fclose($out);
        exit;
    }
    
    if ($format === 'ics') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="agenda-fkip-unimof.ics"');
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//FKIP UNIMOF//Agenda//ID\r\n";
        foreach ($data as $r) {
            $dtstart = date('Ymd\THis', strtotime($r['tanggal_mulai'] . ' 08:00:00'));
            $dtend = $r['tanggal_selesai'] ? date('Ymd\THis', strtotime($r['tanggal_selesai'] . ' 17:00:00')) : date('Ymd\THis', strtotime($r['tanggal_mulai'] . ' 17:00:00'));
            echo "BEGIN:VEVENT\r\n";
            echo "UID:agenda-" . $r['id'] . "@fkip-unimof\r\n";
            echo "DTSTART:$dtstart\r\n";
            echo "DTEND:$dtend\r\n";
            echo "SUMMARY:" . str_replace(["\r", "\n"], ' ', $r['judul']) . "\r\n";
            if (!empty($r['deskripsi'])) echo "DESCRIPTION:" . str_replace(["\r", "\n"], ' ', $r['deskripsi']) . "\r\n";
            if (!empty($r['lokasi'])) echo "LOCATION:" . $r['lokasi'] . "\r\n";
            echo "END:VEVENT\r\n";
        }
        echo "END:VCALENDAR\r\n";
        exit;
    }
}

// ===== FILTER & SEARCH =====
$q = trim($_GET['q'] ?? '');
$jenis_filter = $_GET['jenis'] ?? '';
$status_filter = $_GET['status'] ?? '';
$view_mode = $_GET['view'] ?? 'calendar';
$month_filter = (int)($_GET['month'] ?? date('n'));
$year_filter = (int)($_GET['year'] ?? date('Y'));
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { 
    $where .= ' AND (judul LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ?)'; 
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; 
}
if ($jenis_filter !== '') { $where .= ' AND jenis = ?'; $params[] = $jenis_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($date_from !== '') { $where .= ' AND tanggal_mulai >= ?'; $params[] = $date_from; }
if ($date_to !== '') { $where .= ' AND tanggal_mulai <= ?'; $params[] = $date_to; }

$agenda = $pdo->prepare("SELECT * FROM agenda $where ORDER BY tanggal_mulai DESC");
$agenda->execute($params);
$agenda_list = $agenda->fetchAll();

// ===== STATISTIK =====
$total_agenda = count($agenda_list);
$agenda_bulan_ini = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE())")->fetchColumn();
$agenda_mendatang = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif'")->fetchColumn();
$agenda_selesai = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE status = 'Selesai'")->fetchColumn();
$agenda_minggu_ini = (int)$pdo->query("SELECT COUNT(*) FROM agenda WHERE tanggal_mulai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$stmt_next = $pdo->query("SELECT * FROM agenda WHERE tanggal_mulai >= CURDATE() AND status = 'Aktif' ORDER BY tanggal_mulai ASC LIMIT 1");
$agenda_terdekat = $stmt_next->fetch();

// Statistik per jenis (untuk pie chart)
$jenis_stats = $pdo->query("SELECT jenis, COUNT(*) as total FROM agenda GROUP BY jenis ORDER BY total DESC")->fetchAll();

// Statistik 12 bulan
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
/* ===== PREMIUM STATS ===== */
.agenda-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.agenda-stat-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; position: relative; overflow: hidden; transition: all 0.3s; }
.agenda-stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--stat-color, var(--primary)); }
.agenda-stat-card::after { content: ''; position: absolute; top: 0; right: 0; width: 150px; height: 150px; background: var(--stat-color, var(--primary)); opacity: 0.05; border-radius: 50%; transform: translate(30%, -30%); }
.agenda-stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--stat-color, var(--primary)); }
.agenda-stat-icon { font-size: 2rem; margin-bottom: 0.75rem; }
.agenda-stat-num { font-family: var(--font-display); font-size: 2.5rem; font-weight: 900; color: var(--stat-color, var(--primary)); line-height: 1; margin-bottom: 0.5rem; }
.agenda-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.agenda-stat-trend { font-size: 0.75rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.3rem; font-weight: 600; }
.agenda-stat-trend.up { color: #10b981; }
.agenda-stat-trend.down { color: #ef4444; }

/* ===== CHARTS GRID ===== */
.charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
.chart-container { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-sm); }
.chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.chart-header h3 { font-size: 1.1rem; font-weight: 700; }

/* ===== TOOLBAR ===== */
.agenda-toolbar { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
.agenda-search { flex: 1; min-width: 250px; position: relative; }
.agenda-search input { width: 100%; padding: 0.75rem 1rem 0.75rem 2.75rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; transition: all 0.3s; }
.agenda-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.agenda-search .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.agenda-filter, .agenda-date { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.9rem; background: var(--bg-secondary); cursor: pointer; transition: all 0.2s; }
.agenda-filter:focus, .agenda-date:focus { outline: none; border-color: var(--primary); }
.view-toggle { display: flex; background: var(--bg-secondary); border-radius: var(--radius-md); padding: 0.25rem; }
.view-btn { padding: 0.6rem 1rem; border-radius: 8px; border: none; background: transparent; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); transition: all 0.2s; }
.view-btn.active { background: var(--primary); color: white; }
.view-btn:hover:not(.active) { background: var(--bg-tertiary); color: var(--text-primary); }

/* ===== BULK BAR ===== */
.bulk-bar { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 1rem 1.5rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; display: none; align-items: center; gap: 1rem; flex-wrap: wrap; animation: slideDown 0.3s ease; box-shadow: var(--shadow-lg); }
.bulk-bar.show { display: flex; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info { font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.bulk-count { background: white; color: var(--primary); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 800; }
.bulk-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.bulk-btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 0.85rem; transition: all 0.2s; }
.bulk-btn:hover { transform: translateY(-2px); }
.bulk-btn.primary { background: white; color: var(--primary); }
.bulk-btn.danger { background: #dc2626; color: white; }
.bulk-btn.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }

/* ===== CALENDAR ===== */
.calendar-view { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm); }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.calendar-nav { display: flex; gap: 0.5rem; align-items: center; }
.calendar-nav button { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-secondary); cursor: pointer; font-family: inherit; font-weight: 600; transition: all 0.2s; }
.calendar-nav button:hover { background: var(--primary); color: white; border-color: var(--primary); }
.calendar-month { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; }
.calendar-day-header { text-align: center; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 0.5rem; }
.calendar-day { aspect-ratio: 1; background: var(--bg-secondary); border-radius: 8px; padding: 0.5rem; position: relative; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; overflow: hidden; }
.calendar-day:hover { background: var(--bg-tertiary); transform: scale(1.02); box-shadow: var(--shadow-sm); }
.calendar-day.other-month { opacity: 0.3; }
.calendar-day.today { border-color: var(--primary); background: rgba(10,104,71,0.05); }
.calendar-day.has-events { background: linear-gradient(135deg, var(--bg-secondary), rgba(10,104,71,0.08)); }
.calendar-day-number { font-size: 0.85rem; font-weight: 700; margin-bottom: 0.25rem; }
.calendar-event { font-size: 0.65rem; padding: 0.15rem 0.3rem; border-radius: 4px; margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; cursor: pointer; transition: transform 0.15s; }
.calendar-event:hover { transform: translateX(2px); }
.event-ujian { background: #fee2e2; color: #dc2626; }
.event-seminar { background: #dbeafe; color: #2563eb; }
.event-wisuda { background: #fef3c7; color: #d97706; }
.event-libur { background: #e0e7ff; color: #4338ca; }
.event-pmb { background: #dcfce7; color: #16a34a; }
.event-umum { background: #f3f4f6; color: #4b5563; }

/* ===== LIST VIEW ===== */
.list-view { display: flex; flex-direction: column; gap: 1rem; }
.agenda-group { margin-bottom: 2rem; }
.agenda-group-header { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: 0.5rem; }
.agenda-group-count { background: var(--primary); color: white; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
.agenda-item { display: flex; gap: 1.25rem; padding: 1.25rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); transition: all 0.3s; position: relative; overflow: hidden; }
.agenda-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--priority-color, var(--primary)); transition: width 0.3s; }
.agenda-item:hover { transform: translateX(5px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.agenda-item:hover::before { width: 6px; }
.agenda-item.unread { border-left: 4px solid var(--primary); }
.agenda-item-checkbox { display: flex; align-items: center; }
.agenda-item-checkbox input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
.agenda-date-badge { width: 70px; flex-shrink: 0; text-align: center; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: var(--radius-md); padding: 0.75rem 0.5rem; box-shadow: var(--shadow-sm); }
.agenda-date-day { font-size: 1.75rem; font-weight: 900; line-height: 1; }
.agenda-date-month { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; opacity: 0.9; }
.agenda-content { flex: 1; min-width: 0; }
.agenda-content h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); cursor: pointer; transition: color 0.2s; }
.agenda-content h3:hover { color: var(--primary); }
.agenda-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem; }
.agenda-meta span { display: flex; align-items: center; gap: 0.3rem; }
.agenda-desc { font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0.75rem; }
.agenda-actions { display: flex; gap: 0.5rem; opacity: 0.5; transition: opacity 0.2s; }
.agenda-item:hover .agenda-actions { opacity: 1; }
.act-btn { width: 34px; height: 34px; border-radius: 8px; background: var(--bg-secondary); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 0.9rem; }
.act-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.act-btn.edit:hover { background: #3b82f6; color: white; }
.act-btn.danger:hover { background: #dc2626; color: white; }
.act-btn.status:hover { background: #10b981; color: white; }
.act-btn.view:hover { background: #8b5cf6; color: white; }

/* ===== PRIORITY BADGES ===== */
.priority-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-high { background: #fef3c7; color: #d97706; }
.priority-normal { background: #f3f4f6; color: #6b7280; }

/* ===== MODAL ===== */
.modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.75); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 10000; padding: 2rem; animation: fadeIn 0.2s; }
.modal-overlay.show { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-box { background: var(--bg-primary); border-radius: var(--radius-xl); max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 60px rgba(0,0,0,0.3); animation: modalPop 0.3s; }
@keyframes modalPop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.modal-header { padding: 1.5rem 2rem; background: linear-gradient(135deg, var(--modal-color-1, var(--primary)), var(--modal-color-2, var(--primary-light))); color: white; position: relative; border-radius: var(--radius-xl) var(--radius-xl) 0 0; }
.modal-close { position: absolute; top: 1rem; right: 1rem; width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.2rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
.modal-header h2 { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 0.5rem; }
.modal-header p { opacity: 0.9; font-size: 0.95rem; }
.modal-body { padding: 2rem; }
.modal-info { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
.modal-info-item { padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); }
.modal-info-item strong { display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
.modal-info-item span { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.modal-desc { color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem; }
.modal-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

/* ===== TOAST ===== */
.toast-container { position: fixed; top: 100px; right: 2rem; z-index: 10001; display: flex; flex-direction: column; gap: 0.5rem; }
.toast { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem 1.25rem; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 0.75rem; min-width: 280px; animation: slideInRight 0.3s; border-left: 4px solid var(--primary); }
.toast.success { border-left-color: #10b981; }
.toast.error { border-left-color: #ef4444; }
.toast.info { border-left-color: #3b82f6; }
@keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* ===== EMPTY STATE ===== */
.empty-state-premium { text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, var(--bg-secondary), var(--bg-primary)); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-large { font-size: 5rem; margin-bottom: 1rem; opacity: 0.6; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
.empty-state-premium h3 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.75rem; color: var(--text-primary); }
.empty-state-premium p { color: var(--text-muted); margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; line-height: 1.7; }

/* ===== NEXT AGENDA WIDGET ===== */
.next-agenda-widget { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
.next-agenda-widget::before { content: '⏰'; position: absolute; right: -10px; top: -10px; font-size: 8rem; opacity: 0.1; }
.next-agenda-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9; margin-bottom: 0.5rem; font-weight: 600; }
.next-agenda-title { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; }
.next-agenda-time { font-size: 0.9rem; opacity: 0.9; display: flex; align-items: center; gap: 0.5rem; }

/* ===== PRINT STYLES ===== */
@media print {
    body * { visibility: hidden; }
    .list-view, .list-view * { visibility: visible; }
    .list-view { position: absolute; left: 0; top: 0; width: 100%; }
    .agenda-item { break-inside: avoid; page-break-inside: avoid; }
    .agenda-actions, .bulk-bar, .agenda-toolbar, .calendar-view, .agenda-stats, .charts-grid, .admin-footer, .sidebar, .top-bar { display: none !important; }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .agenda-stats { grid-template-columns: repeat(2, 1fr); }
    .calendar-grid { gap: 0.25rem; }
    .calendar-day { padding: 0.25rem; }
    .calendar-event { font-size: 0.55rem; }
    .agenda-item { flex-direction: column; }
    .agenda-date-badge { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; }
    .modal-info { grid-template-columns: 1fr; }
    .toast-container { right: 1rem; left: 1rem; }
    .toast { min-width: auto; }
}
</style>

<!-- ===== NEXT AGENDA WIDGET ===== -->
<?php if ($agenda_terdekat): ?>
<div class="next-agenda-widget" data-aos="fade-down">
    <div class="next-agenda-label">🔔 Agenda Terdekat</div>
    <div class="next-agenda-title"><?= sanitize($agenda_terdekat['judul']) ?></div>
    <div class="next-agenda-time">
        📅 <?= format_tanggal_range($agenda_terdekat['tanggal_mulai'], $agenda_terdekat['tanggal_selesai'] ?? null) ?>
        <?php if (!empty($agenda_terdekat['lokasi'])): ?>
            <span style="margin-left: 0.5rem;">📍 <?= sanitize($agenda_terdekat['lokasi']) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_minggu_ini ?>">0</div>
        <div class="agenda-stat-label">Minggu Ini</div>
        <div class="agenda-stat-trend up">↑ 7 hari ke depan</div>
    </div>
    <div class="agenda-stat-card" style="--stat-color: #8b5cf6;">
        <div class="agenda-stat-icon">⏳</div>
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_mendatang ?>">0</div>
        <div class="agenda-stat-label">Mendatang</div>
        <div class="agenda-stat-trend up">↑ Aktif</div>
    </div>
    <div class="agenda-stat-card" style="--stat-color: #64748b;">
        <div class="agenda-stat-icon">✅</div>
        <div class="agenda-stat-num count-up" data-target="<?= $agenda_selesai ?>">0</div>
        <div class="agenda-stat-label">Selesai</div>
        <div class="agenda-stat-trend">📊 <?= $total_agenda > 0 ? round($agenda_selesai / $total_agenda * 100) : 0 ?>%</div>
    </div>
</div>

<!-- ===== CHARTS GRID ===== -->
<div class="charts-grid" data-aos="fade-up">
    <div class="chart-container">
        <div class="chart-header">
            <h3>📈 Statistik 12 Bulan Terakhir</h3>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Total: <?= array_sum(array_column($bulan_stats, 'total')) ?></span>
        </div>
        <div id="agendaChart"></div>
    </div>
    <div class="chart-container">
        <div class="chart-header">
            <h3>🎯 Distribusi Jenis</h3>
        </div>
        <div id="jenisChart"></div>
    </div>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="agenda-toolbar" data-aos="fade-up">
    <div class="agenda-search">
        <span class="search-icon">🔍</span>
        <input type="text" id="agendaSearch" placeholder="Cari judul, deskripsi, lokasi..." value="<?= sanitize($q) ?>">
    </div>
    <input type="date" class="agenda-date" id="dateFrom" value="<?= sanitize($date_from) ?>" title="Dari tanggal">
    <input type="date" class="agenda-date" id="dateTo" value="<?= sanitize($date_to) ?>" title="Sampai tanggal">
    <select class="agenda-filter" id="jenisFilter">
        <option value="">Semua Jenis</option>
        <option value="ujian" <?= $jenis_filter === 'ujian' ? 'selected' : '' ?>>📝 Ujian</option>
        <option value="seminar" <?= $jenis_filter === 'seminar' ? 'selected' : '' ?>>🎤 Seminar</option>
        <option value="wisuda" <?= $jenis_filter === 'wisuda' ? 'selected' : '' ?>>🎓 Wisuda</option>
        <option value="libur" <?= $jenis_filter === 'libur' ? 'selected' : '' ?>>🏖️ Libur</option>
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
    <a href="agenda-form.php" class="btn-sm" style="padding: 0.75rem 1.25rem;">+ Tambah</a>
    <button class="btn-sm gray" onclick="exportAgenda('csv')" style="padding: 0.75rem 1rem;">📥 CSV</button>
    <button class="btn-sm gray" onclick="exportAgenda('ics')" style="padding: 0.75rem 1rem;">📆 ICS</button>
    <button class="btn-sm gray" onclick="window.print()" style="padding: 0.75rem 1rem;">🖨️ Cetak</button>
</div>

<!-- ===== BULK BAR ===== -->
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
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn danger" onclick="return confirm('Hapus agenda terpilih?')">🗑️ Hapus</button>
        </div>
    </form>
    <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
</div>

<!-- ===== CONTENT ===== -->
<?php if (empty($agenda_list)): ?>
    <div class="empty-state-premium" data-aos="fade-up">
        <div class="empty-icon-large">📅</div>
        <h3>Belum Ada Agenda</h3>
        <p>Mulai tambahkan agenda pertama Anda untuk mengelola jadwal kegiatan fakultas dengan lebih terorganisir dan profesional.</p>
        <a href="agenda-form.php" class="btn-sm" style="padding: 0.75rem 1.5rem; font-size: 1rem;">+ Tambah Agenda Pertama</a>
    </div>
<?php else: ?>
    <?php if ($view_mode === 'calendar'): ?>
        <div class="calendar-view" data-aos="fade-up">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button onclick="changeMonth(-1)">← Prev</button>
                    <span class="calendar-month"><?= date('F Y', mktime(0, 0, 0, $month_filter, 1, $year_filter)) ?></span>
                    <button onclick="changeMonth(1)">Next →</button>
                </div>
                <button class="btn-sm gray" onclick="resetToCurrentMonth()">📍 Hari Ini</button>
            </div>
            <div class="calendar-grid" id="calendarGrid"></div>
        </div>
    <?php else: ?>
        <div class="list-view" data-aos="fade-up">
            <?php
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
                <?php foreach ($items as $a): 
                    $jenis_colors = ['ujian'=>'#dc2626','seminar'=>'#2563eb','wisuda'=>'#d97706','libur'=>'#4338ca','pmb'=>'#16a34a','umum'=>'#6b7280'];
                    $color = $jenis_colors[$a['jenis']] ?? '#6b7280';
                ?>
                <div class="agenda-item" data-id="<?= $a['id'] ?>" style="--priority-color: <?= $color ?>;" ondblclick="showDetail(<?= $a['id'] ?>)">
                    <div class="agenda-item-checkbox">
                        <input type="checkbox" class="agenda-checkbox" value="<?= $a['id'] ?>" onchange="updateBulkCount()">
                    </div>
                    <div class="agenda-date-badge">
                        <div class="agenda-date-day"><?= date('d', strtotime($a['tanggal_mulai'])) ?></div>
                        <div class="agenda-date-month"><?= date('M', strtotime($a['tanggal_mulai'])) ?></div>
                    </div>
                    <div class="agenda-content">
                        <h3 onclick="showDetail(<?= $a['id'] ?>)"><?= sanitize($a['judul']) ?></h3>
                        <div class="agenda-meta">
                            <span class="badge-ultimate" style="background: <?= $color ?>20; color: <?= $color ?>; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700;"><?= ucfirst($a['jenis']) ?></span>
                            <span>📍 <?= sanitize($a['lokasi'] ?? 'Tidak ada lokasi') ?></span>
                            <span>🕐 <?= format_tanggal_range($a['tanggal_mulai'], $a['tanggal_selesai'] ?? null) ?></span>
                            <span class="badge-ultimate" style="background: <?= $a['status'] === 'Aktif' ? '#dcfce7' : ($a['status'] === 'Selesai' ? '#e0e7ff' : '#fee2e2') ?>; color: <?= $a['status'] === 'Aktif' ? '#166534' : ($a['status'] === 'Selesai' ? '#4338ca' : '#991b1b') ?>; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700;"><?= $a['status'] ?></span>
                        </div>
                        <?php if (!empty($a['deskripsi'])): ?>
                            <p class="agenda-desc"><?= excerpt($a['deskripsi'], 100) ?></p>
                        <?php endif; ?>
                        <div class="agenda-actions">
                            <button class="act-btn view" onclick="showDetail(<?= $a['id'] ?>)" title="Detail">👁️</button>
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

<!-- ===== MODAL DETAIL ===== -->
<div class="modal-overlay" id="detailModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-header" id="modalHeader" style="--modal-color-1: var(--primary); --modal-color-2: var(--primary-light);">
            <button class="modal-close" onclick="closeModal()">✕</button>
            <h2 id="modalTitle">Judul Agenda</h2>
            <p id="modalSubtitle">Jenis • Tanggal</p>
        </div>
        <div class="modal-body">
            <div class="modal-info">
                <div class="modal-info-item">
                    <strong>📅 Tanggal Mulai</strong>
                    <span id="modalDateStart">-</span>
                </div>
                <div class="modal-info-item">
                    <strong>📅 Tanggal Selesai</strong>
                    <span id="modalDateEnd">-</span>
                </div>
                <div class="modal-info-item">
                    <strong>📍 Lokasi</strong>
                    <span id="modalLocation">-</span>
                </div>
                <div class="modal-info-item">
                    <strong>📊 Status</strong>
                    <span id="modalStatus">-</span>
                </div>
            </div>
            <div class="modal-desc" id="modalDesc">-</div>
            <div class="modal-actions">
                <a href="#" id="modalEdit" class="btn-sm" style="padding: 0.75rem 1.25rem;">✏️ Edit Agenda</a>
                <button class="btn-sm gray" onclick="addToCalendar()" style="padding: 0.75rem 1.25rem;">📆 Tambah ke Kalender</button>
                <button class="btn-sm gray" onclick="shareAgenda()" style="padding: 0.75rem 1.25rem;">🔗 Bagikan</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ===== DATA =====
const agendaData = <?= json_encode(array_map(function($a) use ($csrf) {
    return [
        'id' => (int)$a['id'],
        'judul' => $a['judul'],
        'deskripsi' => $a['deskripsi'] ?? '',
        'tanggal_mulai' => $a['tanggal_mulai'],
        'tanggal_selesai' => $a['tanggal_selesai'],
        'jenis' => $a['jenis'],
        'status' => $a['status'],
        'lokasi' => $a['lokasi'] ?? '',
        'csrf' => $csrf
    ];
}, $agenda_list)) ?>;

const currentMonth = <?= $month_filter ?>;
const currentYear = <?= $year_filter ?>;

// ===== TOAST SYSTEM =====
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };
    toast.innerHTML = `<span style="font-size: 1.2rem;">${icons[type]}</span><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3500);
}

// ===== COUNT UP =====
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
    entries.forEach(entry => { if (entry.isIntersecting) { animateCount(entry.target); countObserver.unobserve(entry.target); } });
}, { threshold: 0.3 });
document.querySelectorAll('.count-up').forEach(el => countObserver.observe(el));

// ===== CHARTS =====
new ApexCharts(document.querySelector("#agendaChart"), {
    series: [{ name: 'Jumlah Agenda', data: <?= json_encode(array_column($bulan_stats, 'total')) ?> }],
    chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: true, easing: 'easeinout', speed: 1000 } },
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 } },
    colors: ['#0a6847'],
    dataLabels: { enabled: false },
    xaxis: { categories: <?= json_encode(array_column($bulan_stats, 'bulan')) ?>, labels: { style: { fontSize: '11px' } } },
    yaxis: { title: { text: 'Jumlah' }, min: 0 },
    grid: { borderColor: '#f1f5f9' },
    tooltip: { y: { formatter: (val) => val + ' agenda' } }
}).render();

const jenisData = <?= json_encode($jenis_stats) ?>;
if (jenisData.length > 0) {
    new ApexCharts(document.querySelector("#jenisChart"), {
        series: jenisData.map(j => j.total),
        labels: jenisData.map(j => j.jenis.charAt(0).toUpperCase() + j.jenis.slice(1)),
        chart: { type: 'donut', height: 280 },
        colors: ['#dc2626', '#2563eb', '#d97706', '#4338ca', '#16a34a', '#6b7280'],
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => jenisData.reduce((a,b) => a + b.total, 0) } } } } },
        dataLabels: { enabled: true, style: { fontSize: '11px' } },
        legend: { position: 'bottom', fontSize: '12px' }
    }).render();
}

// ===== CALENDAR =====
function generateCalendar(month, year) {
    const grid = document.getElementById('calendarGrid');
    if (!grid) return;
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const today = new Date();
    
    let html = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'].map(d => `<div class="calendar-day-header">${d}</div>`).join('');
    for (let i = 0; i < firstDay; i++) html += '<div class="calendar-day other-month"></div>';
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = today.getDate() === day && today.getMonth() === month - 1 && today.getFullYear() === year;
        const dayAgendas = agendaData.filter(a => a.tanggal_mulai === dateStr);
        html += `<div class="calendar-day ${isToday ? 'today' : ''} ${dayAgendas.length ? 'has-events' : ''}" onclick="showDayAgendas('${dateStr}')">`;
        html += `<div class="calendar-day-number">${day}</div>`;
        dayAgendas.slice(0, 3).forEach(a => { html += `<div class="calendar-event event-${a.jenis}" onclick="event.stopPropagation(); showDetail(${a.id})">${a.judul}</div>`; });
        if (dayAgendas.length > 3) html += `<div class="calendar-event event-umum">+${dayAgendas.length - 3} lagi</div>`;
        html += '</div>';
    }
    grid.innerHTML = html;
}

function changeMonth(delta) {
    let m = currentMonth + delta, y = currentYear;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    const url = new URL(window.location);
    url.searchParams.set('month', m);
    url.searchParams.set('year', y);
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
    if (dayAgendas.length === 0) {
        showToast('Tidak ada agenda pada tanggal ini', 'info');
        return;
    }
    if (dayAgendas.length === 1) {
        showDetail(dayAgendas[0].id);
        return;
    }
    showToast(`${dayAgendas.length} agenda pada ${dateStr}. Klik agenda di kalender untuk detail.`, 'info');
}

if (document.getElementById('calendarGrid')) generateCalendar(currentMonth, currentYear);

// ===== MODAL =====
function showDetail(id) {
    const a = agendaData.find(x => x.id === id);
    if (!a) return;
    const colors = { ujian:'#dc2626', seminar:'#2563eb', wisuda:'#d97706', libur:'#4338ca', pmb:'#16a34a', umum:'#6b7280' };
    const c = colors[a.jenis] || '#0a6847';
    document.getElementById('modalHeader').style.setProperty('--modal-color-1', c);
    document.getElementById('modalHeader').style.setProperty('--modal-color-2', c + 'cc');
    document.getElementById('modalTitle').textContent = a.judul;
    document.getElementById('modalSubtitle').textContent = `${a.jenis.toUpperCase()} • ${a.status}`;
    document.getElementById('modalDateStart').textContent = a.tanggal_mulai;
    document.getElementById('modalDateEnd').textContent = a.tanggal_selesai || 'Sama dengan tanggal mulai';
    document.getElementById('modalLocation').textContent = a.lokasi || 'Tidak ditentukan';
    document.getElementById('modalStatus').textContent = a.status;
    document.getElementById('modalDesc').textContent = a.deskripsi || 'Tidak ada deskripsi.';
    document.getElementById('modalEdit').href = `agenda-form.php?id=${a.id}`;
    window.currentAgenda = a;
    document.getElementById('detailModal').classList.add('show');
}

function closeModal() { document.getElementById('detailModal').classList.remove('show'); }

function addToCalendar() {
    const a = window.currentAgenda;
    if (!a) return;
    const url = new URL(window.location);
    url.searchParams.set('export', 'ics');
    showToast('Mengunduh file ICS...', 'info');
    window.location = url;
}

function shareAgenda() {
    const a = window.currentAgenda;
    if (!a) return;
    const text = `📅 ${a.judul}\n📆 ${a.tanggal_mulai}\n📍 ${a.lokasi || 'TBD'}\n\n${a.deskripsi || ''}`;
    if (navigator.share) {
        navigator.share({ title: a.judul, text: text });
    } else {
        navigator.clipboard.writeText(text);
        showToast('Detail agenda disalin ke clipboard!', 'success');
    }
}

// ===== BULK =====
function updateBulkCount() {
    const count = document.querySelectorAll('.agenda-checkbox:checked').length;
    document.getElementById('bulkCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('show', count > 0);
    const bulkForm = document.getElementById('bulkForm');
    bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    document.querySelectorAll('.agenda-checkbox:checked').forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
        bulkForm.appendChild(input);
    });
}

function clearSelection() {
    document.querySelectorAll('.agenda-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('bulkBar').classList.remove('show');
}

// ===== QUICK STATUS =====
function quickStatusChange(id, currentStatus) {
    const statuses = ['Aktif', 'Selesai', 'Dibatalkan'];
    const newStatus = statuses[(statuses.indexOf(currentStatus) + 1) % statuses.length];
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>"><input type="hidden" name="ids[]" value="${id}"><input type="hidden" name="new_status" value="${newStatus}"><input type="hidden" name="action" value="bulk_status">`;
    document.body.appendChild(form);
    form.submit();
}

// ===== FILTERS =====
let searchTimeout;
function applyFilters() {
    const url = new URL(window.location);
    const q = document.getElementById('agendaSearch').value;
    const j = document.getElementById('jenisFilter').value;
    const s = document.getElementById('statusFilter').value;
    const df = document.getElementById('dateFrom').value;
    const dt = document.getElementById('dateTo').value;
    if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
    if (j) url.searchParams.set('jenis', j); else url.searchParams.delete('jenis');
    if (s) url.searchParams.set('status', s); else url.searchParams.delete('status');
    if (df) url.searchParams.set('date_from', df); else url.searchParams.delete('date_from');
    if (dt) url.searchParams.set('date_to', dt); else url.searchParams.delete('date_to');
    window.location = url;
}
document.getElementById('agendaSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
});
['jenisFilter', 'statusFilter', 'dateFrom', 'dateTo'].forEach(id => {
    document.getElementById(id).addEventListener('change', applyFilters);
});

// ===== MISC =====
function switchView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location = url;
}

function exportAgenda(format) {
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    showToast(`Mengunduh ${format.toUpperCase()}...`, 'info');
    window.location = url;
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); window.location.href = 'agenda-form.php'; }
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') { e.preventDefault(); document.getElementById('agendaSearch').focus(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') { e.preventDefault(); exportAgenda('csv'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') { e.preventDefault(); window.print(); }
});

console.log('%c📅 Kelola Agenda FKIP UNIMOF - SUPER EXTREME', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+N (Tambah), Ctrl+F (Search), Ctrl+E (Export CSV), Ctrl+P (Print), Esc (Tutup modal)', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>