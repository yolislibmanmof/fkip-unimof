<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Unduhan';
$page_description = 'Download dokumen akademik, formulir, pedoman, dan informasi PMB FKIP UNIMOF.';

$kat = $_GET['kategori'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'list';

$where = "WHERE status = 'Aktif'";
$params = [];
if ($kat !== 'all') { $where .= " AND kategori = ?"; $params[] = $kat; }
if ($q !== '') { $where .= " AND (judul LIKE ? OR deskripsi LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }

$stmt = $pdo->prepare("SELECT * FROM downloads $where ORDER BY created_at DESC");
$stmt->execute($params);
$files = $stmt->fetchAll();

// Stats
$total_files = count($files);
$total_downloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads_count),0) FROM downloads WHERE status='Aktif'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO WITH SEARCH ===== */
.download-hero-extreme {
    position: relative; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1e3a8a 100%);
    color: white; padding: 8rem 0 6rem; text-align: center; overflow: hidden;
}
.download-hero-extreme::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 30% 20%, rgba(251,191,36,0.2) 0%, transparent 50%),
                radial-gradient(circle at 70% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
}
.hero-search-extreme {
    max-width: 600px; margin: 2rem auto 0; position: relative;
}
.hero-search-extreme input {
    width: 100%; padding: 1.1rem 1.5rem 1.1rem 3.5rem; border: none; border-radius: 999px;
    font-size: 1rem; font-family: inherit; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    transition: all 0.3s;
}
.hero-search-extreme input:focus { outline: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); transform: scale(1.02); }
.hero-search-extreme .search-icon {
    position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%);
    font-size: 1.2rem; color: #64748b; pointer-events: none;
}

/* ===== STATS BAR ===== */
.dl-stats-pub {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem; margin: -3rem 0 3rem; position: relative; z-index: 10;
}
.dl-stat-pub {
    background: var(--bg-primary); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.5rem; text-align: center;
    box-shadow: var(--shadow-lg); transition: all 0.3s;
}
.dl-stat-pub:hover { transform: translateY(-5px); }
.dl-stat-num { font-family: var(--font-display); font-size: 2.5rem; font-weight: 900; color: var(--primary); line-height: 1; margin-bottom: 0.5rem; }
.dl-stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

/* ===== FILTERS & VIEW TOGGLE ===== */
.dl-toolbar-pub {
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
    gap: 1rem; margin-bottom: 2rem; padding: 1.25rem; background: var(--bg-primary);
    border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);
}
.filter-chips-pub { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.chip-pub {
    padding: 0.5rem 1rem; border-radius: 999px; border: 1px solid var(--border);
    background: var(--bg-secondary); color: var(--text-secondary); font-size: 0.85rem;
    font-weight: 600; text-decoration: none; transition: all 0.3s;
}
.chip-pub:hover { border-color: var(--primary); color: var(--primary); }
.chip-pub.active { background: var(--primary); color: white; border-color: var(--primary); }
.view-toggle-pub { display: flex; background: var(--bg-secondary); border-radius: var(--radius-md); padding: 0.25rem; }
.view-btn-pub {
    padding: 0.5rem 0.85rem; border-radius: 8px; border: none; background: transparent;
    cursor: pointer; font-size: 1rem; transition: all 0.2s; color: var(--text-muted);
}
.view-btn-pub.active { background: var(--bg-primary); color: var(--primary); box-shadow: var(--shadow-sm); }

/* ===== FILE LIST VIEW ===== */
.file-list-pub { display: flex; flex-direction: column; gap: 1rem; max-width: 900px; margin: 0 auto; }
.file-item-pub {
    display: flex; align-items: center; gap: 1.25rem; background: var(--bg-primary);
    border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
    transition: all 0.3s;
}
.file-item-pub:hover { transform: translateX(8px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.file-icon-pub {
    width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center;
    justify-content: center; font-size: 1.75rem; flex-shrink: 0;
}
.file-pdf { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #dc2626; }
.file-doc { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
.file-zip { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
.file-other { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #4b5563; }
.file-info-pub { flex: 1; min-width: 0; }
.file-info-pub h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-meta-pub { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-muted); flex-wrap: wrap; }
.file-meta-pub span { display: flex; align-items: center; gap: 0.3rem; }
.btn-download-pub {
    display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.25rem;
    background: var(--primary); color: white; border-radius: var(--radius-md);
    text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; flex-shrink: 0;
}
.btn-download-pub:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10,104,71,0.3); }

/* ===== FILE GRID VIEW ===== */
.file-grid-pub { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
.file-card-pub {
    background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg);
    padding: 1.5rem; transition: all 0.3s; display: flex; flex-direction: column;
}
.file-card-pub:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.file-card-header { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
.file-card-info h4 { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.file-card-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem; font-size: 0.78rem; color: var(--text-muted); }
.file-card-meta span { display: flex; align-items: center; gap: 0.25rem; background: var(--bg-secondary); padding: 0.25rem 0.6rem; border-radius: 999px; }
.file-card-pub .btn-download-pub { width: 100%; justify-content: center; margin-top: auto; }

/* Empty State */
.empty-state-premium { text-align: center; padding: 4rem 2rem; background: var(--bg-secondary); border-radius: var(--radius-xl); border: 2px dashed var(--border); }
.empty-icon-lg { font-size: 5rem; margin-bottom: 1rem; opacity: 0.5; animation: float 3s ease-in-out infinite; }
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 768px) {
    .dl-toolbar-pub { flex-direction: column; align-items: stretch; }
    .filter-chips-pub { justify-content: center; }
    .view-toggle-pub { justify-content: center; }
    .file-item-pub { flex-direction: column; text-align: center; }
    .file-meta-pub { justify-content: center; }
}
</style>

<!-- ===== HERO WITH SEARCH ===== -->
<section class="download-hero-extreme">
    <div class="container" style="position: relative; z-index: 2;">
        <nav class="breadcrumb" style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; justify-content: center;" data-aos="fade-down">
            <a href="<?= base_url() ?>" style="color: rgba(255,255,255,0.8);">Beranda</a><span>›</span><span>Pusat Unduhan</span>
        </nav>
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; margin-bottom: 1rem;" data-aos="fade-up">Pusat Unduhan</h1>
        <p class="page-subtitle" style="max-width: 600px; margin: 0 auto; opacity: 0.9; font-size: 1.1rem;" data-aos="fade-up" data-aos-delay="100">
            Akses mudah dan cepat ke berbagai dokumen akademik, formulir, pedoman, dan informasi PMB FKIP UNIMOF.
        </p>
        
        <div class="hero-search-extreme" data-aos="fade-up" data-aos-delay="200">
            <span class="search-icon">🔍</span>
            <form method="GET" action="">
                <?php if ($kat !== 'all'): ?><input type="hidden" name="kategori" value="<?= sanitize($kat) ?>"><?php endif; ?>
                <input type="text" name="q" placeholder="Cari judul dokumen, formulir, atau pedoman..." value="<?= sanitize($q) ?>" autocomplete="off">
            </form>
        </div>
    </div>
</section>

<!-- ===== MAIN CONTENT ===== -->
<section class="section" style="padding-top: 0;">
    <div class="container">
        <!-- Stats Bar -->
        <div class="dl-stats-pub" data-aos="fade-up">
            <div class="dl-stat-pub">
                <div class="dl-stat-num"><?= $total_files ?></div>
                <div class="dl-stat-label">Total Dokumen</div>
            </div>
            <div class="dl-stat-pub">
                <div class="dl-stat-num"><?= number_format($total_downloads) ?></div>
                <div class="dl-stat-label">Total Unduhan</div>
            </div>
            <div class="dl-stat-pub">
                <div class="dl-stat-num">5</div>
                <div class="dl-stat-label">Kategori</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="dl-toolbar-pub" data-aos="fade-up">
            <div class="filter-chips-pub">
                <a class="chip-pub <?= $kat==='all'?'active':'' ?>" href="<?= base_url('download.php?kategori=all' . ($q ? '&q='.urlencode($q) : '')) ?>">Semua</a>
                <?php foreach(['Akademik','PMB','Formulir','Pedoman','Lainnya'] as $k): ?>
                    <a class="chip-pub <?= $kat===$k?'active':'' ?>" href="<?= base_url('download.php?kategori='.$k . ($q ? '&q='.urlencode($q) : '')) ?>"><?= $k ?></a>
                <?php endforeach; ?>
            </div>
            <div class="view-toggle-pub">
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>" class="view-btn-pub <?= $view === 'list' ? 'active' : '' ?>" title="Tampilan List">📋</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>" class="view-btn-pub <?= $view === 'grid' ? 'active' : '' ?>" title="Tampilan Grid"> Grid</a>
            </div>
        </div>

        <!-- Files Display -->
        <?php if (empty($files)): ?>
            <div class="empty-state-premium" data-aos="fade-up">
                <div class="empty-icon-lg">📂</div>
                <h3>Tidak ada file ditemukan</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
                <?php if ($q || $kat !== 'all'): ?>
                    <a href="<?= base_url('download.php') ?>" class="btn btn-secondary" style="margin-top: 1rem;">🔄 Reset Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($view === 'grid'): ?>
                <!-- GRID VIEW -->
                <div class="file-grid-pub">
                    <?php foreach ($files as $f): 
                        $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                        $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip','rar','7z']) ? 'file-zip' : 'file-other'));
                        $icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['zip','rar','7z']) ? '📦' : '📄'));
                    ?>
                    <div class="file-card-pub" data-aos="fade-up">
                        <div class="file-card-header">
                            <div class="file-icon-pub <?= $file_class ?>"><?= $icon ?></div>
                            <div class="file-card-info">
                                <h4><?= sanitize($f['judul']) ?></h4>
                            </div>
                        </div>
                        <div class="file-card-meta">
                            <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                            <span>💾 <?= sanitize($f['file_size']) ?></span>
                            <span>⬇️ <?= number_format($f['downloads_count']) ?></span>
                        </div>
                        <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-download-pub" download onclick="incrementDownload(<?= $f['id'] ?>)">
                            Unduh Sekarang <span>→</span>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- LIST VIEW -->
                <div class="file-list-pub">
                    <?php foreach ($files as $f): 
                        $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                        $file_class = in_array($ext, ['pdf']) ? 'file-pdf' : (in_array($ext, ['doc','docx']) ? 'file-doc' : (in_array($ext, ['zip','rar','7z']) ? 'file-zip' : 'file-other'));
                        $icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['zip','rar','7z']) ? '📦' : '📄'));
                    ?>
                    <div class="file-item-pub" data-aos="fade-up">
                        <div class="file-icon-pub <?= $file_class ?>"><?= $icon ?></div>
                        <div class="file-info-pub">
                            <h4><?= sanitize($f['judul']) ?></h4>
                            <div class="file-meta-pub">
                                <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                                <span>💾 <?= sanitize($f['file_size']) ?></span>
                                <span>⬇️ <?= number_format($f['downloads_count']) ?> unduhan</span>
                                <span>📅 <?= date('d M Y', strtotime($f['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="file-action">
                            <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-download-pub" download onclick="incrementDownload(<?= $f['id'] ?>)">
                                Unduh <span>→</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
function incrementDownload(id) {
    // Kirim request background untuk menambah counter tanpa mengganggu download
    fetch('<?= base_url('api/download-counter.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).catch(() => {}); // Silent fail agar tidak mengganggu UX
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>