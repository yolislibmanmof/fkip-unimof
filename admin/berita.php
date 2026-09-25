<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== Proses AKSI POST (hapus / toggle / bulk) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Token keamanan tidak valid.');
    } else {
        $action = $_POST['action'] ?? '';
        
        // Bulk actions
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
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
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
$view_mode = $_GET['view'] ?? 'table';
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$per_page = $view_mode === 'grid' ? 12 : 10;
$offset = ($halaman - 1) * $per_page;

$where = 'WHERE 1=1';
$params = [];
if ($q !== '') { $where .= ' AND (judul LIKE ? OR konten LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($kat_filter !== '') { $where .= ' AND kategori = ?'; $params[] = $kat_filter; }
if ($status_filter !== '') { $where .= ' AND status = ?'; $params[] = $status_filter; }

$cs = $pdo->prepare("SELECT COUNT(*) FROM berita $where");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

$stmt = $pdo->prepare("SELECT * FROM berita $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$per_page, $offset]));
$berita = $stmt->fetchAll();

// Mini stats
$stat_published = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Published'")->fetchColumn();
$stat_draft = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Draft'")->fetchColumn();
$stat_archived = (int)$pdo->query("SELECT COUNT(*) FROM berita WHERE status='Archived'")->fetchColumn();
$stat_total_views = (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM berita")->fetchColumn();

$kategoris = ['Akademik','Pengumuman','Prestasi','Kegiatan','Riset','Umum'];
$csrf = generate_csrf_token();

$active_menu = 'berita';
$page_heading = 'Kelola Berita';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Berita', null]];
require __DIR__ . '/includes/header.php';
?>

<!-- ===== MINI STATS BAR ===== -->
<div class="berita-mini-stats" data-aos="fade-down">
    <div class="mini-stat" style="--ms-color:#10b981">
        <span class="mini-stat-num"><?= $stat_published ?></span>
        <span class="mini-stat-label">Published</span>
    </div>
    <div class="mini-stat" style="--ms-color:#f59e0b">
        <span class="mini-stat-num"><?= $stat_draft ?></span>
        <span class="mini-stat-label">Draft</span>
    </div>
    <div class="mini-stat" style="--ms-color:#64748b">
        <span class="mini-stat-num"><?= $stat_archived ?></span>
        <span class="mini-stat-label">Archived</span>
    </div>
    <div class="mini-stat" style="--ms-color:#3b82f6">
        <span class="mini-stat-num"><?= number_format($stat_total_views) ?></span>
        <span class="mini-stat-label">Total Views</span>
    </div>
</div>

<div class="card berita-page">
    <div class="card-header">
        <h2>📰 Daftar Berita <span class="count-badge"><?= $total ?></span></h2>
        <div class="header-actions">
            <a href="?export=csv" class="btn-sm gray" title="Export CSV">📥 Export</a>
            <a href="berita-form.php" class="btn-sm">+ Tambah Berita</a>
        </div>
    </div>

    <!-- ===== SEARCH & FILTER BAR ===== -->
    <div class="berita-toolbar">
        <form method="GET" class="search-form">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" class="search-input" 
                    placeholder="Cari judul atau isi berita..." 
                    value="<?= sanitize($q) ?>">
                <?php if ($q !== ''): ?>
                    <a href="berita.php" class="search-clear">✕</a>
                <?php endif; ?>
            </div>
            <!-- preserve other filters -->
            <?php if ($kat_filter): ?><input type="hidden" name="kategori" value="<?= sanitize($kat_filter) ?>"><?php endif; ?>
            <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= sanitize($status_filter) ?>"><?php endif; ?>
            <input type="hidden" name="view" value="<?= sanitize($view_mode) ?>">
        </form>

        <div class="toolbar-controls">
            <!-- Status filter -->
            <select class="filter-select" onchange="filterByStatus(this.value)">
                <option value="">Semua Status</option>
                <option value="Published" <?= $status_filter==='Published'?'selected':'' ?>>Published</option>
                <option value="Draft" <?= $status_filter==='Draft'?'selected':'' ?>>Draft</option>
                <option value="Archived" <?= $status_filter==='Archived'?'selected':'' ?>>Archived</option>
            </select>

            <!-- View toggle -->
            <div class="view-toggle">
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'table'])) ?>" 
                    class="view-btn <?= $view_mode==='table'?'active':'' ?>" title="Tampilan Tabel">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>" 
                    class="view-btn <?= $view_mode==='grid'?'active':'' ?>" title="Tampilan Grid">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- ===== CATEGORY CHIPS ===== -->
    <div class="category-chips">
        <a class="chip <?= $kat_filter === '' ? 'active' : '' ?>"
           href="?<?= http_build_query(array_merge($_GET, ['kategori' => '', 'halaman' => 1])) ?>">
            Semua <span class="chip-count"><?= $stat_published + $stat_draft + $stat_archived ?></span>
        </a>
        <?php foreach ($kategoris as $k):
            $cstmt = $pdo->prepare("SELECT COUNT(*) FROM berita WHERE kategori = ?");
            $cstmt->execute([$k]);
            $count = (int)$cstmt->fetchColumn();
        ?>
            <a class="chip <?= $kat_filter === $k ? 'active' : '' ?>"
               href="?<?= http_build_query(array_merge($_GET, ['kategori' => $k, 'halaman' => 1])) ?>">
                <?= $k ?> <span class="chip-count"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== BULK ACTIONS BAR ===== -->
    <div class="bulk-bar" id="bulkBar" style="display:none">
        <span class="bulk-info">
            <span id="bulkCount">0</span> berita dipilih
        </span>
        <form method="POST" class="bulk-form" id="bulkForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <button type="submit" name="action" value="bulk_publish" class="bulk-btn publish">✅ Publish</button>
            <button type="submit" name="action" value="bulk_draft" class="bulk-btn draft">📝 Jadi Draft</button>
            <button type="submit" name="action" value="bulk_delete" class="bulk-btn delete" onclick="return confirmBulk('HAPUS permanen')">🗑️ Hapus</button>
        </form>
        <button class="bulk-btn cancel" onclick="clearSelection()">Batal</button>
    </div>

    <?php if (empty($berita)): ?>
    <!-- ===== EMPTY STATE ===== -->
    <div class="empty-state-pro">
        <div class="empty-illustration-wrap">
            <div class="empty-circle"></div>
            <div class="empty-icon">📭</div>
        </div>
        <h3>
            <?php if ($q || $kat_filter || $status_filter): ?>
                Tidak ada hasil untuk pencarian ini
            <?php else: ?>
                Belum ada berita
            <?php endif; ?>
        </h3>
        <p>
            <?php if ($q || $kat_filter || $status_filter): ?>
                Coba ubah kata kunci atau filter untuk menemukan berita.
            <?php else: ?>
                Mulailah dengan membuat berita pertama Anda untuk dibagikan ke pengunjung website.
            <?php endif; ?>
        </p>
        <div class="empty-actions">
            <?php if ($q || $kat_filter || $status_filter): ?>
                <a href="berita.php" class="btn-sm gray">🔄 Reset Filter</a>
            <?php endif; ?>
            <a href="berita-form.php" class="btn-sm">+ Buat Berita Pertama</a>
        </div>
    </div>

    <?php elseif ($view_mode === 'grid'): ?>
    <!-- ===== GRID VIEW ===== -->
    <div class="berita-grid-view">
        <label class="select-all-wrap">
            <input type="checkbox" id="selectAllGrid" onchange="toggleSelectAll(this)">
            <span>Pilih Semua</span>
        </label>
        <div class="berita-grid">
            <?php foreach ($berita as $b): ?>
            <div class="berita-grid-item" data-id="<?= $b['id'] ?>">
                <div class="grid-item-check">
                    <input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()">
                </div>
                <?php if ($b['is_featured']): ?>
                    <span class="featured-star" title="Featured">⭐</span>
                <?php endif; ?>
                <div class="grid-thumb" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', '<?= sanitize($b['judul']) ?>')">
                    <?php if ($b['gambar']): ?>
                        <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="">
                    <?php else: ?>
                        <div class="thumb-placeholder-grid">📰</div>
                    <?php endif; ?>
                </div>
                <div class="grid-body">
                    <span class="badge-ultimate badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                    <h3><?= highlight_search(sanitize($b['judul']), $q) ?></h3>
                    <p><?= excerpt($b['excerpt'] ?: $b['konten'], 90) ?></p>
                    <div class="grid-meta">
                        <span>📁 <?= sanitize($b['kategori']) ?></span>
                        <span>👁 <?= number_format($b['views']) ?></span>
                    </div>
                    <div class="grid-date"><?= format_tanggal_singkat($b['created_at']) ?></div>
                </div>
                <div class="grid-actions">
                    <a href="berita-form.php?id=<?= $b['id'] ?>" class="ga-btn edit" title="Edit">✏️</a>
                    <button class="ga-btn" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status">
                        <?= $b['status'] === 'Published' ? '📤' : '📥' ?>
                    </button>
                    <button class="ga-btn" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                    <button class="ga-btn danger" onclick="confirmDelete(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['judul'])) ?>')" title="Hapus">🗑️</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ===== TABLE VIEW ===== -->
    <div class="berita-table-wrap">
        <table class="berita-table-pro">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                    <th style="width:80px">Gambar</th>
                    <th>Judul</th>
                    <th style="width:110px">Kategori</th>
                    <th style="width:110px">Status</th>
                    <th style="width:90px">Views</th>
                    <th style="width:110px">Tanggal</th>
                    <th style="width:200px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($berita as $b): ?>
                <tr data-id="<?= $b['id'] ?>" class="berita-row">
                    <td><input type="checkbox" class="row-check" value="<?= $b['id'] ?>" form="bulkForm" name="ids[]" onchange="updateBulkBar()"></td>
                    <td>
                        <div class="table-thumb" onclick="previewImage('<?= asset('uploads/' . basename($b['gambar'] ?: '')) ?>', '<?= sanitize($b['judul']) ?>')">
                            <?php if ($b['gambar']): ?>
                                <img src="<?= asset('uploads/' . basename($b['gambar'])) ?>" alt="">
                            <?php else: ?>
                                <div class="thumb-ph">📰</div>
                            <?php endif; ?>
                            <div class="thumb-zoom">🔍</div>
                        </div>
                    </td>
                    <td>
                        <div class="title-cell">
                            <?php if ($b['is_featured']): ?><span class="featured-tag">⭐</span><?php endif; ?>
                            <strong><?= highlight_search(sanitize($b['judul']), $q) ?></strong>
                            <small><?= sanitize($b['slug']) ?></small>
                        </div>
                    </td>
                    <td><span class="badge-ultimate badge-info"><?= sanitize($b['kategori']) ?></span></td>
                    <td><span class="badge-ultimate badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                    <td>
                        <div class="views-cell">
                            <strong><?= number_format($b['views']) ?></strong>
                        </div>
                    </td>
                    <td>
                        <div class="date-cell">
                            <div><?= date('d M', strtotime($b['created_at'])) ?></div>
                            <small><?= date('Y', strtotime($b['created_at'])) ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="action-cell">
                            <a href="berita-form.php?id=<?= $b['id'] ?>" class="act-btn edit" title="Edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </a>
                            <button class="act-btn" onclick="quickAction(<?= $b['id'] ?>, 'toggle')" title="Toggle Status">
                                <?= $b['status'] === 'Published' ? '📤' : '📥' ?>
                            </button>
                            <button class="act-btn" onclick="quickAction(<?= $b['id'] ?>, 'duplicate')" title="Duplikat">📋</button>
                            <button class="act-btn danger" onclick="confirmDelete(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['judul'])) ?>')" title="Hapus">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="pagination-pro">
        <div class="page-info">
            Menampilkan <?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?> berita
        </div>
        <div class="page-buttons">
            <?php
            $base_query = $_GET;
            // Prev
            if ($halaman > 1): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman - 1])) ?>" class="page-btn">← Prev</a>
            <?php endif;
            
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i === 1 || $i === $total_pages || ($i >= $halaman - 2 && $i <= $halaman + 2)):
                    echo $i === $halaman 
                        ? "<span class='page-btn current'>$i</span>"
                        : "<a href='?" . http_build_query(array_merge($base_query, ['halaman' => $i])) . "' class='page-btn'>$i</a>";
                elseif ($i === $halaman - 3 || $i === $halaman + 3):
                    echo "<span class='page-dots'>…</span>";
                endif;
            endfor;
            
            if ($halaman < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($base_query, ['halaman' => $halaman + 1])) ?>" class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>

<!-- ===== IMAGE PREVIEW MODAL ===== -->
<div class="modal-overlay" id="imageModal" onclick="if(event.target===this)closeImageModal()">
    <div class="image-modal-box">
        <button class="image-modal-close" onclick="closeImageModal()">✕</button>
        <div class="image-modal-title" id="imageModalTitle"></div>
        <div class="image-modal-body">
            <img id="imageModalImg" src="" alt="">
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal-overlay" id="confirmModal" onclick="if(event.target===this)closeConfirm()">
    <div class="confirm-modal-box">
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <h3 id="confirmTitle">Konfirmasi</h3>
        <p id="confirmMessage">Apakah Anda yakin?</p>
        <div class="confirm-actions">
            <button class="btn-sm gray" onclick="closeConfirm()">Batal</button>
            <button class="btn-sm red" id="confirmOk">Ya, Lanjutkan</button>
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

<!-- ===== SCOPED STYLES ===== -->
<style>
/* Mini Stats Bar */
.berita-mini-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
.mini-stat{background:#fff;border-radius:16px;padding:1.25rem;display:flex;align-items:center;gap:1rem;border:1px solid #f1f5f9;box-shadow:0 2px 8px rgba(0,0,0,.04);position:relative;overflow:hidden;transition:all .3s}
.mini-stat::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--ms-color)}
.mini-stat:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.08)}
.mini-stat-num{font-size:1.75rem;font-weight:800;color:var(--ms-color);line-height:1}
.mini-stat-label{font-size:.8rem;color:#64748b;font-weight:500}

/* Header actions */
.header-actions{display:flex;gap:.5rem}
.count-badge{background:#0a6847;color:#fff;font-size:.7rem;padding:.15rem .5rem;border-radius:999px;margin-left:.5rem}

/* Toolbar */
.berita-toolbar{display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center}
.search-form{flex:1;min-width:250px}
.search-wrap{position:relative;display:flex;align-items:center}
.search-icon{position:absolute;left:1rem;color:#94a3b8;pointer-events:none}
.search-input{width:100%;padding:.75rem 2.5rem .75rem 2.75rem;border:2px solid #e2e8f0;border-radius:12px;font-size:.9rem;font-family:inherit;background:#f8fafc;transition:all .2s}
.search-input:focus{outline:none;border-color:#0a6847;background:#fff;box-shadow:0 0 0 4px rgba(10,104,71,.1)}
.search-clear{position:absolute;right:1rem;color:#94a3b8;text-decoration:none;font-size:1rem;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all .2s}
.search-clear:hover{background:#fee2e2;color:#dc2626}
.toolbar-controls{display:flex;gap:.75rem;align-items:center}
.filter-select{padding:.7rem .9rem;border:2px solid #e2e8f0;border-radius:12px;font-size:.85rem;font-family:inherit;background:#f8fafc;cursor:pointer;transition:all .2s}
.filter-select:focus{outline:none;border-color:#0a6847}
.view-toggle{display:flex;background:#f1f5f9;border-radius:10px;padding:.2rem;gap:.15rem}
.view-btn{padding:.5rem .7rem;border-radius:8px;color:#64748b;transition:all .2s;display:flex;align-items:center;text-decoration:none}
.view-btn:hover{color:#0f172a}
.view-btn.active{background:#fff;color:#0a6847;box-shadow:0 2px 4px rgba(0,0,0,.05)}

/* Category Chips */
.category-chips{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid #f1f5f9}
.chip{padding:.5rem 1rem;background:#f8fafc;border:2px solid transparent;border-radius:999px;font-size:.8rem;font-weight:600;color:#475569;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:.4rem}
.chip:hover{background:#f1f5f9;border-color:#e2e8f0}
.chip.active{background:#0a6847;color:#fff;border-color:#0a6847}
.chip-count{background:rgba(0,0,0,.08);padding:.1rem .45rem;border-radius:999px;font-size:.7rem}
.chip.active .chip-count{background:rgba(255,255,255,.25)}

/* Bulk Bar */
.bulk-bar{background:linear-gradient(135deg,#0a6847,#16a34a);color:#fff;padding:1rem 1.5rem;border-radius:12px;margin-bottom:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;animation:slideDown .3s}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.bulk-info{font-weight:600;display:flex;align-items:center;gap:.5rem}
#bulkCount{background:#fff;color:#0a6847;padding:.2rem .6rem;border-radius:999px;font-size:.85rem;font-weight:800}
.bulk-form{display:flex;gap:.5rem;flex-wrap:wrap}
.bulk-btn{padding:.5rem 1rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;font-size:.85rem;transition:all .2s}
.bulk-btn:hover{filter:brightness(1.1);transform:translateY(-1px)}
.bulk-btn.publish{background:#fff;color:#0a6847}
.bulk-btn.draft{background:rgba(255,255,255,.2);color:#fff}
.bulk-btn.delete{background:#dc2626;color:#fff}
.bulk-btn.cancel{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.3)}

/* Empty State Pro */
.empty-state-pro{text-align:center;padding:4rem 2rem}
.empty-illustration-wrap{position:relative;width:140px;height:140px;margin:0 auto 1.5rem}
.empty-circle{position:absolute;inset:0;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border-radius:50%;animation:emptyPulse 3s ease-in-out infinite}
@keyframes emptyPulse{0%,100%{transform:scale(1);opacity:.8}50%{transform:scale(1.1);opacity:.4}}
.empty-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:4rem;animation:emptyFloat 3s ease-in-out infinite}
@keyframes emptyFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.empty-state-pro h3{font-size:1.35rem;margin-bottom:.5rem;color:#0f172a}
.empty-state-pro p{color:#64748b;max-width:400px;margin:0 auto 1.5rem;line-height:1.6}
.empty-actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}

/* Grid View */
.berita-grid-view{margin-top:1rem}
.select-all-wrap{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#64748b;margin-bottom:1rem;cursor:pointer}
.select-all-wrap input{cursor:pointer;width:16px;height:16px}
.berita-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem}
.berita-grid-item{background:#fff;border:2px solid #f1f5f9;border-radius:16px;overflow:hidden;transition:all .3s;position:relative}
.berita-grid-item:hover{border-color:#0a6847;box-shadow:0 10px 30px rgba(10,104,71,.1);transform:translateY(-4px)}
.berita-grid-item.selected{border-color:#0a6847;background:#f0fdf4}
.grid-item-check{position:absolute;top:.75rem;left:.75rem;z-index:2;width:20px;height:20px}
.grid-item-check input{width:100%;height:100%;cursor:pointer}
.featured-star{position:absolute;top:.75rem;right:.75rem;z-index:2;font-size:1.25rem;filter:drop-shadow(0 2px 4px rgba(0,0,0,.2));animation:starPulse 2s infinite}
@keyframes starPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
.grid-thumb{height:160px;overflow:hidden;background:#f1f5f9;cursor:pointer;position:relative}
.grid-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.berita-grid-item:hover .grid-thumb img{transform:scale(1.05)}
.thumb-placeholder-grid{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;background:linear-gradient(135deg,#667eea,#764ba2)}
.grid-body{padding:1.25rem}
.grid-body h3{font-size:1rem;margin:.75rem 0 .5rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.6em}
.grid-body p{font-size:.82rem;color:#64748b;line-height:1.5;margin-bottom:.75rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.grid-meta{display:flex;gap:.75rem;font-size:.75rem;color:#94a3b8;margin-bottom:.5rem}
.grid-date{font-size:.75rem;color:#cbd5e1}
.grid-actions{display:flex;border-top:1px solid #f1f5f9;background:#f8fafc}
.ga-btn{flex:1;padding:.75rem;background:transparent;border:none;cursor:pointer;font-size:1rem;transition:all .2s;border-right:1px solid #f1f5f9}
.ga-btn:last-child{border-right:none}
.ga-btn:hover{background:#fff}
.ga-btn.edit:hover{background:#dbeafe;color:#2563eb}
.ga-btn.danger:hover{background:#fee2e2;color:#dc2626}

/* Table View */
.berita-table-wrap{overflow-x:auto;margin-top:1rem}
.berita-table-pro{width:100%;border-collapse:separate;border-spacing:0}
.berita-table-pro thead th{background:#f8fafc;padding:.85rem 1rem;text-align:left;font-size:.72rem;text-transform:uppercase;color:#64748b;letter-spacing:.05em;font-weight:700;border-bottom:2px solid #e2e8f0;position:sticky;top:0}
.berita-table-pro tbody td{padding:.85rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-size:.9rem}
.berita-table-pro tbody tr{transition:background .15s}
.berita-table-pro tbody tr:hover{background:#f8fafc}
.berita-table-pro tbody tr.selected{background:#f0fdf4}
.table-thumb{width:60px;height:45px;border-radius:8px;overflow:hidden;cursor:pointer;position:relative;background:#f1f5f9}
.table-thumb img{width:100%;height:100%;object-fit:cover}
.thumb-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;background:linear-gradient(135deg,#667eea,#764ba2)}
.thumb-zoom{position:absolute;inset:0;background:rgba(0,0,0,.5);color:#fff;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.table-thumb:hover .thumb-zoom{opacity:1}
.title-cell strong{display:block;font-size:.9rem;margin-bottom:.15rem;color:#0f172a}
.title-cell small{color:#94a3b8;font-size:.72rem;font-family:ui-monospace,monospace;display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.featured-tag{display:inline-block;margin-right:.25rem;font-size:.75rem}
.views-cell strong{font-weight:800;color:#0a6847;font-size:1rem}
.date-cell{font-size:.85rem;font-weight:600;color:#475569}
.date-cell small{display:block;font-size:.7rem;color:#94a3b8;font-weight:400}
.action-cell{display:flex;gap:.25rem}
.act-btn{width:34px;height:34px;border-radius:8px;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;color:#475569;font-size:.95rem}
.act-btn:hover{background:#0a6847;color:#fff;transform:translateY(-2px);box-shadow:0 4px 8px rgba(10,104,71,.2)}
.act-btn.edit:hover{background:#3b82f6}
.act-btn.danger:hover{background:#dc2626}
mark{background:#fef3c7;padding:0 .15rem;border-radius:2px}

/* Pagination Pro */
.pagination-pro{display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:1rem}
.page-info{font-size:.85rem;color:#64748b}
.page-buttons{display:flex;gap:.25rem;align-items:center}
.page-btn{padding:.5rem .85rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;text-decoration:none;color:#475569;transition:all .2s;background:#fff;font-weight:500;cursor:pointer}
.page-btn:hover:not(.current){border-color:#0a6847;color:#0a6847}
.page-btn.current{background:#0a6847;color:#fff;border-color:#0a6847;font-weight:700}
.page-dots{padding:0 .5rem;color:#94a3b8}

/* Modals */
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.8);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;z-index:9999;padding:2rem;animation:fadeIn .2s}
.modal-overlay.open{display:flex}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.image-modal-box{background:#fff;border-radius:16px;max-width:90vw;max-height:90vh;overflow:hidden;position:relative;animation:zoomIn .3s;box-shadow:0 30px 80px rgba(0,0,0,.4)}
@keyframes zoomIn{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.image-modal-close{position:absolute;top:1rem;right:1rem;width:36px;height:36px;border-radius:50%;background:rgba(0,0,0,.6);color:#fff;border:none;cursor:pointer;font-size:1.1rem;z-index:2;transition:all .2s}
.image-modal-close:hover{background:#000;transform:rotate(90deg)}
.image-modal-title{padding:1rem 1.5rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:600;max-width:600px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.image-modal-body{max-height:70vh;overflow:auto;background:#0f172a}
.image-modal-body img{max-width:100%;max-height:70vh;display:block;margin:0 auto}
.confirm-modal-box{background:#fff;border-radius:16px;padding:2rem;max-width:420px;width:100%;text-align:center;animation:zoomIn .3s}
.confirm-icon{font-size:3rem;margin-bottom:1rem;animation:bounce 1s}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.confirm-modal-box h3{font-size:1.25rem;margin-bottom:.5rem}
.confirm-modal-box p{color:#64748b;margin-bottom:1.5rem;line-height:1.6}
.confirm-actions{display:flex;gap:.75rem;justify-content:center}

/* Badge ultimate (scoped) */
.badge-ultimate{padding:.3rem .7rem;border-radius:999px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;display:inline-block}
.badge-published{background:#dcfce7;color:#166534}
.badge-draft{background:#fef3c7;color:#92400e}
.badge-archived{background:#e2e8f0;color:#475569}
.badge-info{background:#dbeafe;color:#1e40af}

/* Responsive */
@media(max-width:968px){
    .berita-mini-stats{grid-template-columns:repeat(2,1fr)}
    .berita-toolbar{flex-direction:column}
    .toolbar-controls{width:100%;justify-content:space-between}
    .berita-grid{grid-template-columns:repeat(auto-fill,minmax(240px,1fr))}
}
@media(max-width:640px){
    .berita-mini-stats{grid-template-columns:1fr 1fr}
    .mini-stat{padding:1rem}
    .mini-stat-num{font-size:1.4rem}
    .page-info{width:100%;text-align:center}
}
</style>

<script>
// ===== Search highlight =====
function highlightText(el, q) {
    if (!q) return;
    const html = el.innerHTML;
    const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    el.innerHTML = html.replace(regex, '<mark>$1</mark>');
}
document.querySelectorAll('.title-cell strong, .grid-body h3').forEach(el => {
    if (!el.querySelector('mark')) highlightText(el, <?= json_encode($q) ?>);
});

// ===== Filter by status (redirect) =====
function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    url.searchParams.set('halaman', '1');
    window.location = url;
}

// ===== Selection =====
function toggleSelectAll(master) {
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
    document.getElementById('selectAll').checked = 
        document.querySelectorAll('.row-check').length > 0 && 
        checked === document.querySelectorAll('.row-check').length;
    const gridSel = document.getElementById('selectAllGrid');
    if (gridSel) gridSel.checked = document.getElementById('selectAll').checked;
}
function clearSelection() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('[data-id]').forEach(r => r.classList.remove('selected'));
    document.getElementById('selectAll').checked = false;
    const g = document.getElementById('selectAllGrid'); if (g) g.checked = false;
    updateBulkBar();
}
function confirmBulk(msg) {
    return confirm(msg + ' ' + document.querySelectorAll('.row-check:checked').length + ' berita?');
}

// ===== Image preview =====
function previewImage(src, title) {
    if (!src || !/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(src)) return;
    document.getElementById('imageModalImg').src = src;
    document.getElementById('imageModalTitle').textContent = title || 'Preview';
    document.getElementById('imageModal').classList.add('open');
}
function closeImageModal() {
    document.getElementById('imageModal').classList.remove('open');
}

// ===== Confirm modal (universal) =====
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

// ===== Quick action (toggle / duplicate) =====
function quickAction(id, action) {
    const msgs = {
        toggle: { t: 'Ubah Status?', m: 'Status berita akan diubah.', i: '🔄' },
        duplicate: { t: 'Duplikat Berita?', m: 'Akan dibuat salinan sebagai draft.', i: '📋' }
    };
    const cfg = msgs[action];
    showConfirm(cfg.t, cfg.m, cfg.i, () => {
        document.getElementById('qaId').value = id;
        document.getElementById('qaAction').value = action;
        document.getElementById('quickActionForm').submit();
    });
}

// ===== Delete confirmation =====
function confirmDelete(id, title) {
    showConfirm(
        'Hapus Berita?',
        'Berita "' + title + '" akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.',
        '🗑️',
        () => {
            document.getElementById('delId').value = id;
            document.getElementById('deleteForm').submit();
        }
    );
}

// ===== ESC closes modals =====
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeImageModal();
        closeConfirm();
    }
});

// ===== Auto submit search with debounce =====
let searchTimer;
document.querySelector('.search-input')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const val = this.value;
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        if (val) url.searchParams.set('q', val);
        else url.searchParams.delete('q');
        url.searchParams.set('halaman', '1');
        window.location = url;
    }, 500);
});

// ===== Keyboard shortcut: Ctrl+/ focus search =====
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        document.querySelector('.search-input')?.focus();
    }
});

console.log('%c📰 Kelola Berita FKIP UNIMOF', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cTip: Ctrl+/ untuk fokus pencarian', 'color:#64748b');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>