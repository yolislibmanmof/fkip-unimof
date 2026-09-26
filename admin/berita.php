<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== CSS INLINE (Agar langsung tampil tanpa file terpisah) =====
$custom_css = '
<style>
/* ===== BERITA PAGE STYLES ===== */
.berita-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card-ultimate { background: linear-gradient(135deg, var(--stat-color, #0a6847) 0%, rgba(255,255,255,0.1) 100%); border-radius: 16px; padding: 1.5rem; color: white; position: relative; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.3s; }
.stat-card-ultimate:hover { transform: translateY(-5px); }
.stat-content { display: flex; align-items: baseline; gap: 0.75rem; }
.stat-number { font-size: 2.5rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: 0.9rem; opacity: 0.9; }
.stat-change { font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.8; padding: 0.25rem 0.5rem; background: rgba(255,255,255,0.2); border-radius: 6px; display: inline-block; }
.stat-change.positive { background: rgba(16, 185, 129, 0.3); }

.card-header-pro { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border); }
.header-left h2 { font-size: 1.75rem; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; }
.count-badge-pro { background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 700; }
.header-actions-pro { display: flex; gap: 0.75rem; }

.btn-action-pro { padding: 0.75rem 1.25rem; border-radius: 10px; border: 2px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; font-size: 0.9rem; }
.btn-action-pro:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.btn-action-pro.primary { background: var(--primary); color: white; border-color: var(--primary); }

.advanced-filter-bar { background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border); }
.advanced-search-form { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.search-group { flex: 1; min-width: 300px; }
.search-input-wrap { position: relative; }
.search-icon-pro { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.1rem; color: var(--text-muted); }
.search-input-pro { width: 100%; padding: 0.875rem 3rem 0.875rem 3rem; border: 2px solid var(--border); border-radius: 10px; font-size: 0.95rem; transition: all 0.3s; }
.search-input-pro:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.search-clear-pro { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.search-clear-pro:hover { background: var(--bg-tertiary); color: #dc2626; }

.filter-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.filter-select-pro, .date-filter-pro { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: 10px; font-size: 0.9rem; background: white; cursor: pointer; transition: all 0.3s; }
.filter-select-pro:focus, .date-filter-pro:focus { outline: none; border-color: var(--primary); }
.btn-reset-filter { padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: 10px; background: white; cursor: pointer; font-weight: 600; transition: all 0.3s; }
.btn-reset-filter:hover { background: var(--bg-tertiary); border-color: var(--primary); }

.view-toggle-pro { display: flex; background: var(--bg-tertiary); border-radius: 10px; padding: 0.25rem; gap: 0.25rem; }
.view-btn-pro { padding: 0.5rem 0.75rem; border-radius: 8px; border: none; background: transparent; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); text-decoration: none; }
.view-btn-pro.active { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

.category-chips-pro { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border); }
.chip-pro { padding: 0.5rem 1rem; border-radius: 999px; border: 2px solid transparent; background: white; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; color: var(--text-primary); }
.chip-pro:hover { border-color: var(--primary); transform: translateY(-2px); }
.chip-pro.active { background: var(--primary); color: white; border-color: var(--primary); }
.chip-count-pro { background: rgba(0,0,0,0.1); padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; }
.chip-pro.active .chip-count-pro { background: rgba(255,255,255,0.2); }

.bulk-bar-pro { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; animation: slideDown 0.3s; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.bulk-info-pro { display: flex; align-items: center; gap: 0.75rem; font-weight: 600; }
.bulk-actions-pro { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.bulk-btn-pro { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; }
.bulk-btn-pro:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
.bulk-btn-pro.publish { background: white; color: var(--primary); }
.bulk-btn-pro.draft { background: rgba(255,255,255,0.2); color: white; }
.bulk-btn-pro.delete { background: #dc2626; color: white; }
.bulk-btn-pro.cancel { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.3); }
.bulk-category-select { padding: 0.5rem; border-radius: 8px; border: none; font-size: 0.85rem; cursor: pointer; }

.table-wrapper-pro { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.berita-table-premium { width: 100%; border-collapse: collapse; background: var(--bg-primary); }
.berita-table-premium thead { background: var(--bg-tertiary); }
.berita-table-premium th { padding: 1rem; text-align: left; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); border-bottom: 2px solid var(--border); }
.berita-table-premium td { padding: 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.berita-table-premium tbody tr { transition: all 0.2s; }
.berita-table-premium tbody tr:hover { background: var(--bg-secondary); }
.berita-table-premium tbody tr.featured-row { background: rgba(245, 158, 11, 0.05); }

.table-thumb-pro { width: 60px; height: 45px; border-radius: 8px; overflow: hidden; cursor: pointer; background: var(--bg-tertiary); position: relative; }
.table-thumb-pro img { width: 100%; height: 100%; object-fit: cover; }
.thumb-placeholder-pro { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); }

.title-cell-pro strong { display: block; font-size: 0.95rem; margin-bottom: 0.25rem; line-height: 1.3; }
.title-cell-pro small { display: block; font-size: 0.75rem; color: var(--text-muted); font-family: monospace; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.featured-icon-pro { color: #f59e0b; margin-right: 0.25rem; }

.badge-pro { padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; display: inline-block; }
.badge-published { background: #dcfce7; color: #166534; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-archived { background: #e2e8f0; color: #475569; }
.badge-category { background: #dbeafe; color: #1e40af; }

.views-cell-pro { text-align: center; }
.views-cell-pro strong { display: block; font-size: 1.1rem; color: var(--primary); font-weight: 800; }
.views-label { font-size: 0.7rem; color: var(--text-muted); }
.date-cell-pro { font-size: 0.85rem; }
.date-cell-pro small { color: var(--text-muted); font-size: 0.75rem; }

.action-buttons-pro { display: flex; gap: 0.25rem; }
.act-btn-pro { width: 34px; height: 34px; border-radius: 8px; border: none; background: var(--bg-tertiary); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 1rem; text-decoration: none; color: inherit; }
.act-btn-pro:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
.act-btn-pro.edit:hover { background: #3b82f6; color: white; }
.act-btn-pro.danger:hover { background: #dc2626; color: white; }

.pagination-premium { display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border); flex-wrap: wrap; gap: 1rem; }
.pagination-info { font-size: 0.9rem; color: var(--text-muted); }
.pagination-buttons { display: flex; gap: 0.5rem; align-items: center; }
.page-btn-pro { padding: 0.5rem 0.875rem; border: 2px solid var(--border); border-radius: 8px; background: white; cursor: pointer; font-weight: 600; transition: all 0.3s; text-decoration: none; color: var(--text-primary); display: inline-flex; align-items: center; gap: 0.5rem; }
.page-btn-pro:hover:not(.current) { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.page-btn-pro.current { background: var(--primary); color: white; border-color: var(--primary); }
.page-dots { padding: 0 0.5rem; color: var(--text-muted); }

.modal-overlay-premium { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 2rem; animation: fadeIn 0.3s; }
.modal-overlay-premium.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.image-modal-premium { background: white; border-radius: 16px; max-width: 90vw; max-height: 90vh; overflow: hidden; position: relative; animation: zoomIn 0.3s; box-shadow: 0 30px 80px rgba(0,0,0,0.4); }
@keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.modal-close-premium { position: absolute; top: 1rem; right: 1rem; width: 36px; height: 36px; border-radius: 50%; background: rgba(0,0,0,0.6); color: white; border: none; cursor: pointer; font-size: 1.2rem; z-index: 2; transition: all 0.2s; }
.modal-close-premium:hover { background: black; transform: rotate(90deg); }
.modal-header-premium { padding: 1rem 1.5rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border); font-weight: 700; font-size: 1.1rem; max-width: 600px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.modal-body-premium { max-height: 70vh; overflow: auto; background: #0f172a; display: flex; align-items: center; justify-content: center; }
.modal-body-premium img { max-width: 100%; max-height: 70vh; object-fit: contain; }

.confirm-modal-premium { background: white; border-radius: 16px; padding: 2rem; max-width: 420px; width: 100%; text-align: center; animation: zoomIn 0.3s; }
.confirm-icon-premium { font-size: 3rem; margin-bottom: 1rem; animation: bounce 1s; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.confirm-modal-premium h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }
.confirm-modal-premium p { color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.6; }
.confirm-actions-premium { display: flex; gap: 0.75rem; justify-content: center; }

.empty-state-premium { text-align: center; padding: 4rem 2rem; }
.empty-animation { position: relative; width: 140px; height: 140px; margin: 0 auto 1.5rem; }
.empty-circle-pro { position: absolute; inset: 0; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: 50%; animation: emptyPulse 3s ease-in-out infinite; }
@keyframes emptyPulse { 0%, 100% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.1); opacity: 0.4; } }
.empty-icon-pro { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 4rem; animation: emptyFloat 3s ease-in-out infinite; }
@keyframes emptyFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.empty-state-premium h3 { font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--text-primary); }
.empty-state-premium p { color: var(--text-muted); max-width: 400px; margin: 0 auto 1.5rem; line-height: 1.6; }
.empty-actions-pro { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

.grid-container-pro { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
.grid-item-pro { background: var(--bg-primary); border: 2px solid var(--border); border-radius: 16px; overflow: hidden; transition: all 0.3s; position: relative; }
.grid-item-pro:hover { border-color: var(--primary); box-shadow: 0 10px 30px rgba(10,104,71,0.1); transform: translateY(-4px); }
.grid-item-pro.selected { border-color: var(--primary); background: #f0fdf4; }
.grid-check-pro { position: absolute; top: 1rem; left: 1rem; z-index: 2; }
.featured-badge-pro { position: absolute; top: 1rem; right: 1rem; font-size: 1.5rem; z-index: 2; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); animation: starPulse 2s infinite; }
@keyframes starPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }

.grid-image-pro { height: 180px; overflow: hidden; background: var(--bg-tertiary); cursor: pointer; position: relative; }
.grid-image-pro img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.grid-item-pro:hover .grid-image-pro img { transform: scale(1.05); }
.image-placeholder-pro { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; background: linear-gradient(135deg, #667eea, #764ba2); }
.image-overlay-pro { position: absolute; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; }
.grid-image-pro:hover .image-overlay-pro { opacity: 1; }
.zoom-icon { color: white; font-size: 2rem; }

.grid-content-pro { padding: 1.25rem; }
.grid-badges-pro { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.grid-content-pro h3 { font-size: 1.05rem; margin: 0.75rem 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.grid-content-pro p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.grid-meta-pro { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-muted); }
.meta-item { display: flex; align-items: center; gap: 0.25rem; }

.grid-actions-pro { display: flex; border-top: 1px solid var(--border); background: var(--bg-secondary); }
.action-btn-pro { flex: 1; padding: 0.75rem; background: transparent; border: none; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; border-right: 1px solid var(--border); color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: center; }
.action-btn-pro:last-child { border-right: none; }
.action-btn-pro:hover { background: white; }
.action-btn-pro.danger:hover { background: #fee2e2; }

.select-all-grid { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; cursor: pointer; font-weight: 600; }
.select-all-grid input { width: 18px; height: 18px; cursor: pointer; }

@media (max-width: 968px) {
    .berita-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .card-header-pro { flex-direction: column; gap: 1rem; }
    .header-actions-pro { width: 100%; justify-content: stretch; }
    .btn-action-pro { flex: 1; justify-content: center; }
    .advanced-filter-bar { padding: 1rem; }
    .advanced-search-form { flex-direction: column; }
    .search-group { min-width: 100%; }
    .filter-group { width: 100%; }
    .filter-select-pro, .date-filter-pro, .btn-reset-filter { flex: 1; }
    .grid-container-pro { grid-template-columns: 1fr; }
    .table-wrapper-pro { overflow-x: auto; }
    .berita-table-premium { min-width: 800px; }
}
@media (max-width: 640px) {
    .berita-stats-grid { grid-template-columns: 1fr; }
    .stat-number { font-size: 2rem; }
    .bulk-bar-pro { flex-direction: column; align-items: stretch; }
    .bulk-actions-pro { flex-direction: column; }
    .bulk-btn-pro, .bulk-category-select { width: 100%; }
}
</style>
';

// ===== Proses AKSI POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'bulk_delete' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT gambar FROM berita WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $g) delete_upload($g);
            $pdo->prepare("DELETE FROM berita WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', count($ids) . ' berita berhasil dihapus.');
        } elseif ($action === 'bulk_publish' && !empty($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET status='Published', published_at=NOW() WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', count($ids) . ' berita dipublikasikan.');
        } elseif ($action === 'bulk_draft' && !empty($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE berita SET status='Draft' WHERE id IN ($placeholders)")->execute($ids);
            flash_message('success', count($ids) . ' berita diubah ke draft.');
        } elseif ($action === 'bulk_category' && !empty($_POST['ids']) && !empty($_POST['new_category'])) {
            $ids = array_map('intval', $_POST['ids']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $category = sanitize($_POST['new_category']);
            $pdo->prepare("UPDATE berita SET kategori=? WHERE id IN ($placeholders)")->execute(array_merge([$category], $ids));
            flash_message('success', 'Kategori berhasil diubah untuk ' . count($ids) . ' berita.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT gambar FROM berita WHERE id = ?");
            $stmt->execute([$id]);
            delete_upload($stmt->fetchColumn());
            $pdo->prepare("DELETE FROM berita WHERE id = ?")->execute([$id]);
            flash_message('success', 'Berita berhasil dihapus.');
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE berita SET status = IF(status='Published','Draft','Published'), published_at = IF(status='Published', published_at, NOW()) WHERE id = ?")->execute([$id]);
            flash_message('success', 'Status berita diubah.');
        } elseif ($action === 'duplicate') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM berita WHERE id = ?");
            $stmt->execute([$id]);
            $orig = $stmt->fetch();
            if ($orig) {
                $slug = $orig['slug'] . '-copy-' . time();
                $pdo->prepare("INSERT INTO berita (judul,slug,konten,excerpt,gambar,kategori,penulis,status,is_featured) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$orig['judul'] . ' (Copy)', $slug, $orig['konten'], $orig['excerpt'], $orig['gambar'], $orig['kategori'], $orig['penulis'], 'Draft', 0]);
                flash_message('success', 'Berita berhasil diduplikasi sebagai draft.');
            }
        } elseif ($action === 'toggle_featured') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE berita SET is_featured = IF(is_featured=1,0,1) WHERE id = ?")->execute([$id]);
            flash_message('success', 'Status featured diubah.');
        }
    }
    header('Location: berita.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// ===== Export CSV =====
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="berita-fkip-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Judul','Kategori','Status','Views','Penulis','Tanggal']);
    $rows = $pdo->query("SELECT * FROM berita ORDER BY created_at DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['judul'], $r['kategori'], $r['status'], $r['views'], $r['penulis'], $r['created_at']]);
    }
    exit;
}

// ===== Filter & Pagination =====
$q = trim($_GET['q'] ?? '');
$kat_filter = trim($_GET['kategori'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_filter = trim($_GET['tanggal'] ?? '');
$view_mode = $_GET['view'] ?? 'table';
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = $view_mode === 'grid' ? 12 : 10;
$offset = ($halaman - 1) * $per_page;

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR konten LIKE ? OR slug LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kat_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($date_filter !== '') { $where .= ' AND DATE(created_at) = ?'; $params[] = $date_filter; }

$cs = $pdo->prepare("SELECT COUNT(*) FROM berita $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM berita $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$berita = $stmt->fetchAll();

$stat_published = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();
$stat_draft = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
$stat_archived = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Archived'")->fetchColumn();
$stat_total_views = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM berita")->fetchColumn();
$stat_featured = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE is_featured=1")->fetchColumn();
$stat_today = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$kategoris = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
$csrf = generate_csrf_token();

$active_menu = 'berita';
$page_heading = 'Kelola Berita';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Berita', null]];

require __DIR__ . '/includes/header.php';
echo $custom_css; // Output CSS setelah header agar style diterapkan
?>

<!-- ===== EXTREME STATS BAR ===== -->
<div class="berita-stats-grid" data-aos="fade-down">
    <div class="stat-card-ultimate" style="--stat-color: #10b981;">
        <div class="stat-content">
            <span class="stat-number" data-count="<?= $stat_published ?>">0</span>
            <span class="stat-label">Published</span>
        </div>
        <div class="stat-change positive">+<?= $stat_today ?> hari ini</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #f59e0b;">
        <div class="stat-content">
            <span class="stat-number" data-count="<?= $stat_draft ?>">0</span>
            <span class="stat-label">Draft</span>
        </div>
        <div class="stat-change">Perlu review</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #3b82f6;">
        <div class="stat-content">
            <span class="stat-number" data-count="<?= $stat_total_views ?>">0</span>
            <span class="stat-label">Total Views</span>
        </div>
        <div class="stat-change">Semua waktu</div>
    </div>
    <div class="stat-card-ultimate" style="--stat-color: #8b5cf6;">
        <div class="stat-content">
            <span class="stat-number" data-count="<?= $stat_featured ?>">0</span>
            <span class="stat-label">Featured</span>
        </div>
        <div class="stat-change">Highlight</div>
    </div>
</div>

<div class="card berita-page-pro">
    <div class="card-header-pro">
        <div class="header-left">
            <h2>📰 Daftar Berita <span class="count-badge-pro"><?= $total ?></span></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">Kelola konten berita dan publikasi FKIP UNIMOF</p>
        </div>
        <div class="header-actions-pro">
            <button onclick="exportToCSV()" class="btn-action-pro" title="Export CSV">
                <span>📥</span> Export
            </button>
            <a href="berita-form.php" class="btn-action-pro primary">
                <span>+</span> Tambah Berita
            </a>
        </div>
    </div>

    <!-- ===== ADVANCED SEARCH & FILTER ===== -->
    <div class="advanced-filter-bar">
        <form method="GET" class="advanced-search-form" id="searchForm">
            <div class="search-group">
                <div class="search-input-wrap">
                    <span class="search-icon-pro">🔍</span>
                    <input type="text" name="q" class="search-input-pro" placeholder="Cari judul, konten, atau slug..." value="<?= sanitize($q) ?>" autocomplete="off">
                    <?php if ($q !== ''): ?>
                        <button type="button" class="search-clear-pro" onclick="clearSearch()">✕</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="filter-group">
                <select name="kategori" class="filter-select-pro" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoris as $k): ?>
                        <option value="<?= $k ?>" <?= $kat_filter === $k ? 'selected' : '' ?>><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="filter-select-pro" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="Published" <?= $status_filter === 'Published' ? 'selected' : '' ?>>Published</option>
                    <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Archived" <?= $status_filter === 'Archived' ? 'selected' : '' ?>>Archived</option>
                </select>
                <input type="date" name="tanggal" class="date-filter-pro" value="<?= sanitize($date_filter) ?>" onchange="this.form.submit()" title="Filter berdasarkan tanggal">
                <button type="button" class="btn-reset-filter" onclick="resetFilters()" title="Reset semua filter">🔄 Reset</button>
            </div>
            <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
        </form>
        <div class="view-controls-pro">
            <div class="view-toggle-pro">
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'table'])) ?>" class="view-btn-pro <?= $view_mode==='table'?'active':'' ?>" title="Tampilan Tabel">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>" class="view-btn-pro <?= $view_mode==='grid'?'active':'' ?>" title="Tampilan Grid">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- ===== CATEGORY CHIPS ===== -->
    <div class="category-chips-pro">
        <a class="chip-pro <?= $kat_filter === '' ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['kategori' => '', 'halaman' => 1])) ?>">
            Semua <span class="chip-count-pro"><?= $total ?></span>
        </a>
        <?php foreach ($kategoris as $k):
            $cstmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE kategori = ?");
            $cstmt->execute([$k]);
            $count = (int)$cstmt->fetchColumn();
        ?>
            <a class="chip-pro <?= $kat_filter === $k ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['kategori' => $k, 'halaman' => 1])) ?>">
                <?= $k === 'Akademik' ? '🎓' : ($k === 'Prestasi' ? '🏆' : ($k === 'Pengumuman' ? '📢' : ($k === 'Kegiatan' ? '🎉' : ($k === 'Riset' ? '🔬' : '📌')))) ?>
                <?= $k ?> <span class="chip-count-pro"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== BULK ACTIONS BAR ===== -->
    <div class="bulk-bar-pro" id="bulkBar" style="display:none">
        <div class="bulk-info-pro">
            <input type="checkbox" id="bulkSelectAll" onchange="toggleBulkSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
            <span><strong id="bulkCount">0</strong> berita dipilih</span>
        </div>
        <div class="bulk-actions-pro">
            <form method="POST" class="bulk-form-inline" id="bulkForm">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <button type="submit" name="action" value="bulk_publish" class="bulk-btn-pro publish" onclick="return confirm('Publikasikan berita yang dipilih?')">✅ Publish</button>
                <button type="submit" name="action" value="bulk_draft" class="bulk-btn-pro draft" onclick="return confirm('Ubah ke draft?')">📝 Draft</button>
                <select name="new_category" class="bulk-category-select" onchange="if(this.value){if(confirm('Ubah kategori?')){document.getElementById('bulkForm').querySelector('[name=action]').value='bulk_category';document.getElementById('bulkForm').submit()}}">
                    <option value="">Ubah Kategori...</option>
                    <?php foreach ($kategoris as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
                </select>
                <button type="submit" name="action" value="bulk_delete" class="bulk-btn-pro delete" onclick="return confirm('HAPUS PERMANEN berita yang dipilih? Tindakan ini tidak bisa dibatalkan!')">🗑️ Hapus</button>
            </form>
            <button class="bulk-btn-pro cancel" onclick="clearSelection()">Batal</button>
        </div>
    </div>

    <?php if (empty($berita)): ?>
    <div class="empty-state-premium">
        <div class="empty-animation">
            <div class="empty-circle-pro"></div>
            <div class="empty-icon-pro">📭</div>
        </div>
        <h3><?= ($q || $kat_filter || $status_filter || $date_filter) ? 'Tidak ada hasil untuk pencarian ini' : 'Belum ada berita' ?></h3>
        <p><?= ($q || $kat_filter || $status_filter || $date_filter) ? 'Coba ubah kata kunci atau filter untuk menemukan berita.' : 'Mulailah dengan membuat berita pertama Anda untuk dibagikan ke pengunjung website.' ?></p>
        <div class="empty-actions-pro">
            <?php if ($q || $kat_filter || $status_filter || $date_filter): ?><a href="berita.php" class="btn-action-pro">🔄 Reset Filter</a><?php endif; ?>
            <a href="berita-form.php" class="btn-action-pro primary">+ Buat Berita Pertama</a>
        </div>
    </div>

    <?php elseif ($view_mode === 'grid'): ?>
    <div class="berita-grid-premium">
        <label class="select-all-grid">
            <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll('grid')">
            <span>Pilih Semua</span>
        </label>
        <div class="grid-container-pro">
            <?php foreach ($berita as $b): ?>
            <div class="grid-item-pro" data-id="<?= $b['id'] ?>" data-aos="fade-up">
                <div class="grid-check-pro">
                    <input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()">
                </div>
                <?php if ($b['is_featured']): ?><span class="featured-badge-pro" title="Featured Article">⭐</span><?php endif; ?>
                <div class="grid-image-pro" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', <?= json_encode(sanitize($b['judul'])) ?>)">
                    <?php if ($b['gambar']): ?>
                        <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="<?= sanitize($b['judul']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="image-placeholder-pro">📰</div>
                    <?php endif; ?>
                    <div class="image-overlay-pro"><span class="zoom-icon">🔍</span></div>
                </div>
                <div class="grid-content-pro">
                    <div class="grid-badges-pro">
                        <span class="badge-pro badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                        <span class="badge-pro badge-category"><?= sanitize($b['kategori']) ?></span>
                    </div>
                    <h3><?= sanitize($b['judul']) ?></h3>
                    <p><?= excerpt($b['excerpt'] ?: strip_tags($b['konten']), 100) ?></p>
                    <div class="grid-meta-pro">
                        <span class="meta-item">👁 <strong><?= number_format($b['views']) ?></strong></span>
                        <span class="meta-item"><?= format_tanggal_singkat($b['created_at']) ?></span>
                    </div>
                </div>
                <div class="grid-actions-pro">
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="action-btn-pro edit" title="Edit">✏️</a>
                    <button class="action-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status"><?= $b['status'] === 'Published' ? '📤' : '📥' ?></button>
                    <button class="action-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'toggle_featured')" title="<?= $b['is_featured'] ? 'Unfeature' : 'Feature' ?>"><?= $b['is_featured'] ? '⭐' : '☆' ?></button>
                    <button class="action-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                    <button class="action-btn-pro danger" onclick="confirmDelete(<?= $b['id'] ?>, <?= json_encode(sanitize($b['judul'])) ?>)" title="Hapus">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="table-wrapper-pro">
        <table class="berita-table-premium">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll('table')"></th>
                    <th style="width:70px">Gambar</th>
                    <th>Judul & Slug</th>
                    <th style="width:120px">Kategori</th>
                    <th style="width:100px">Status</th>
                    <th style="width:90px">Views</th>
                    <th style="width:120px">Tanggal</th>
                    <th style="width:220px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($berita as $b): ?>
                <tr data-id="<?= $b['id'] ?>" class="<?= $b['is_featured'] ? 'featured-row' : '' ?>">
                    <td><input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></td>
                    <td>
                        <div class="table-thumb-pro" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', <?= json_encode(sanitize($b['judul'])) ?>)">
                            <?php if ($b['gambar']): ?>
                                <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="thumb-placeholder-pro">📰</div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="title-cell-pro">
                            <?php if ($b['is_featured']): ?><span class="featured-icon-pro" title="Featured">⭐</span><?php endif; ?>
                            <strong><?= sanitize($b['judul']) ?></strong>
                            <small><?= sanitize($b['slug']) ?></small>
                        </div>
                    </td>
                    <td><span class="badge-pro badge-category"><?= sanitize($b['kategori']) ?></span></td>
                    <td><span class="badge-pro badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                    <td>
                        <div class="views-cell-pro">
                            <strong><?= number_format($b['views']) ?></strong>
                            <span class="views-label">views</span>
                        </div>
                    </td>
                    <td>
                        <div class="date-cell-pro">
                            <div><?= date('d M Y', strtotime($b['created_at'])) ?></div>
                            <small><?= date('H:i', strtotime($b['created_at'])) ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons-pro">
                            <a href="berita-form.php?id=<?= $b['id'] ?>" class="act-btn-pro edit" title="Edit">✏️</a>
                            <button class="act-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status"><?= $b['status'] === 'Published' ? '📤' : '📥' ?></button>
                            <button class="act-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'toggle_featured')" title="<?= $b['is_featured'] ? 'Unfeature' : 'Feature' ?>"><?= $b['is_featured'] ? '⭐' : '☆' ?></button>
                            <button class="act-btn-pro" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                            <button class="act-btn-pro danger" onclick="confirmDelete(<?= $b['id'] ?>, <?= json_encode(sanitize($b['judul'])) ?>)" title="Hapus">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
    <nav class="pagination-premium">
        <div class="pagination-info">
            Menampilkan <strong><?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?></strong> dari <strong><?= $total ?></strong> berita
        </div>
        <div class="pagination-buttons">
            <?php
            $base_query = $_GET;
            if ($halaman > 1): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman - 1])) ?>" class="page-btn-pro">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Prev
                </a>
            <?php endif;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i === 1 || $i === $total_pages || ($i >= $halaman - 2 && $i <= $halaman + 2)):
                    echo $i === $halaman ? "<span class='page-btn-pro current'>$i</span>" : "<a href='?" . http_build_query(array_merge($base_query, ['halaman' => $i])) . "' class='page-btn-pro'>$i</a>";
                elseif ($i === $halaman - 3 || $i === $halaman + 3):
                    echo "<span class='page-dots'>…</span>";
                endif;
            endfor;
            if ($halaman < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman + 1])) ?>" class="page-btn-pro">
                    Next <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>

<!-- ===== IMAGE PREVIEW MODAL ===== -->
<div class="modal-overlay-premium" id="imageModal" onclick="if(event.target===this)closeImageModal()">
    <div class="image-modal-premium">
        <button class="modal-close-premium" onclick="closeImageModal()">✕</button>
        <div class="modal-header-premium" id="imageModalTitle"></div>
        <div class="modal-body-premium">
            <img id="imageModalImg" src="" alt="">
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay-premium" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="confirm-modal-premium">
        <div class="confirm-icon-premium" id="confirmIcon">⚠️</div>
        <h3 id="confirmTitle">Konfirmasi</h3>
        <p id="confirmMessage">Apakah Anda yakin?</p>
        <div class="confirm-actions-premium">
            <button class="btn-action-pro" onclick="closeConfirm()">Batal</button>
            <button class="btn-action-pro danger" id="confirmOk" style="background:#dc2626; color:white; border-color:#dc2626;">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

<!-- ===== HIDDEN FORMS ===== -->
<form id="quickActionForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="qaId">
    <input type="hidden" name="action" id="qaAction">
</form>
<form id="deleteForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    <input type="hidden" name="id" id="delId">
    <input type="hidden" name="action" value="delete">
</form>

<script>
// ===== Counter Animation =====
document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.getAttribute('data-count')) || 0;
    const duration = 2000;
    const start = performance.now();
    const animate = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(animate);
    };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { animate(performance.now()); observer.unobserve(entry.target); }
        });
    }, { threshold: 0.5 });
    observer.observe(el);
});

// ===== Search dengan debounce =====
let searchTimer;
document.querySelector('.search-input-pro')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const val = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (val) url.searchParams.set('q', val); else url.searchParams.delete('q');
        url.searchParams.set('halaman', '1');
        window.location = url;
    }, 500);
});

function clearSearch() {
    const url = new URL(window.location);
    url.searchParams.delete('q');
    window.location = url;
}

function resetFilters() {
    if(confirm('Reset semua filter?')) window.location = 'berita.php';
}

// ===== Selection Management =====
function toggleSelectAll(type) {
    const master = type === 'grid' ? document.getElementById('selectAllGrid') : document.getElementById('selectAll');
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulkBar();
}

function toggleBulkSelectAll() {
    const master = document.getElementById('bulkSelectAll');
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = master.checked;
        cb.closest('[data-id]')?.classList.toggle('selected', master.checked);
    });
    updateBulkBar();
}

document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('[data-id]')?.classList.toggle('selected', this.checked);
        updateBulkBar();
    });
});

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('bulkCount').textContent = checked;
    document.getElementById('bulkBar').style.display = checked > 0 ? 'flex' : 'none';
    const allChecks = document.querySelectorAll('.row-check');
    const checkedAll = allChecks.length > 0 && checked === allChecks.length;
    document.getElementById('selectAll').checked = checkedAll;
    const gridSel = document.getElementById('selectAllGrid');
    if (gridSel) gridSel.checked = checkedAll;
    document.getElementById('bulkSelectAll').checked = checkedAll;
}

function clearSelection() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(r => r.classList.remove('selected'));
    document.getElementById('selectAll').checked = false;
    const g = document.getElementById('selectAllGrid'); if (g) g.checked = false;
    document.getElementById('bulkSelectAll').checked = false;
    updateBulkBar();
}

// ===== Image Preview =====
function previewImage(src, title) {
    if (!src || !/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(src)) {
        alert('Gambar tidak tersedia');
        return;
    }
    document.getElementById('imageModalImg').src = src;
    document.getElementById('imageModalTitle').textContent = title || 'Preview';
    document.getElementById('imageModal').classList.add('open');
}
function closeImageModal() { document.getElementById('imageModal').classList.remove('open'); }

// ===== Confirm Modal =====
let confirmCallback = null;
function showConfirm(title, message, icon, onConfirm) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmIcon').textContent = icon;
    confirmCallback = onConfirm;
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    confirmCallback = null;
}
document.getElementById('confirmOk').addEventListener('click', function() {
    if (confirmCallback) confirmCallback();
    closeConfirm();
});

// ===== Quick Actions =====
function quickAction(id, action) {
    const configs = {
        toggle: { title: 'Ubah Status?', message: 'Status berita akan diubah.', icon: '🔄' },
        duplicate: { title: 'Duplikat Berita?', message: 'Akan dibuat salinan sebagai draft.', icon: '📋' },
        toggle_featured: { title: 'Toggle Featured?', message: 'Status featured artikel akan diubah.', icon: '⭐' }
    };
    const cfg = configs[action];
    showConfirm(cfg.title, cfg.message, cfg.icon, () => {
        document.getElementById('qaId').value = id;
        document.getElementById('qaAction').value = action;
        document.getElementById('quickActionForm').submit();
    });
}

function confirmDelete(id, title) {
    showConfirm('Hapus Berita Permanen?', `Berita "${title}" akan dihapus permanen. Tindakan ini TIDAK BISA dibatalkan!`, '⚠️', () => {
        document.getElementById('delId').value = id;
        document.getElementById('deleteForm').submit();
    });
}

// ===== Export CSV =====
function exportToCSV() {
    const url = new URL(window.location);
    url.searchParams.set('export', 'csv');
    window.location = url;
}

// ===== Keyboard Shortcuts =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeImageModal(); closeConfirm(); }
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        document.querySelector('.search-input-pro')?.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'berita-form.php';
    }
});

// ===== Highlight search terms =====
function highlightSearchTerms() {
    const q = <?= json_encode($q) ?>;
    if (!q) return;
    document.querySelectorAll('.title-cell-pro strong, .grid-content-pro h3').forEach(el => {
        if (!el.querySelector('mark')) {
            const html = el.innerHTML;
            const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            el.innerHTML = html.replace(regex, '<mark>$1</mark>');
        }
    });
}
highlightSearchTerms();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>