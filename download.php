<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Pusat Unduhan';
$page_description = 'Download dokumen akademik, formulir, pedoman, dan informasi PMB FKIP UNIMOF.';

$kat = $_GET['kategori'] ?? 'all';
$where = "WHERE status = 'Aktif'";
$params = [];
if ($kat !== 'all') { $where .= " AND kategori = ?"; $params[] = $kat; }

$downloads = $pdo->prepare("SELECT * FROM downloads $where ORDER BY created_at DESC");
$downloads->execute($params);
$files = $downloads->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<style>
.download-hero { background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); color: white; padding: 8rem 0 5rem; text-align: center; }
.file-list { display: grid; gap: 1rem; max-width: 900px; margin: 0 auto; }
.file-item { display: flex; align-items: center; gap: 1.5rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; transition: all 0.3s; }
.file-item:hover { transform: translateX(5px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.file-icon { width: 50px; height: 50px; background: #fee2e2; color: #dc2626; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.file-info { flex: 1; min-width: 0; }
.file-info h4 { font-size: 1.05rem; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-muted); }
.file-action { flex-shrink: 0; }
.btn-download { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; background: var(--primary); color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; }
.btn-download:hover { background: var(--primary-dark); transform: translateY(-2px); }
</style>

<section class="download-hero">
    <div class="container">
        <h1 class="page-title" style="font-size: clamp(2.5rem, 5vw, 4rem);">Pusat Unduhan</h1>
        <p class="page-subtitle" style="max-width: 600px; margin: 1rem auto 0; opacity: 0.9;">Akses mudah ke berbagai dokumen akademik, formulir, dan pedoman FKIP UNIMOF.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="filter-chips" style="justify-content: center; margin-bottom: 2.5rem;">
            <a class="chip <?= $kat==='all'?'active':'' ?>" href="<?= base_url('download.php?kategori=all') ?>">Semua</a>
            <?php foreach(['Akademik','PMB','Formulir','Pedoman','Lainnya'] as $k): ?>
                <a class="chip <?= $kat===$k?'active':'' ?>" href="<?= base_url('download.php?kategori='.$k) ?>"><?= $k ?></a>
            <?php endforeach; ?>
        </div>

        <div class="file-list">
            <?php if (empty($files)): ?>
                <div class="empty-state-pro"><div class="empty-icon-lg">📂</div><h3>Tidak ada file ditemukan</h3></div>
            <?php else: foreach ($files as $f): 
                $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                $icon = in_array($ext, ['pdf']) ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : '📦');
            ?>
            <div class="file-item" data-aos="fade-up">
                <div class="file-icon"><?= $icon ?></div>
                <div class="file-info">
                    <h4><?= sanitize($f['judul']) ?></h4>
                    <div class="file-meta">
                        <span>🏷️ <?= sanitize($f['kategori']) ?></span>
                        <span>💾 <?= sanitize($f['file_size']) ?></span>
                        <span>⬇️ <?= number_format($f['downloads_count']) ?> unduhan</span>
                    </div>
                </div>
                <div class="file-action">
                    <a href="<?= base_url('assets/downloads/' . urlencode($f['file_name'])) ?>" class="btn-download" download onclick="incrementDownload(<?= $f['id'] ?>)">
                        Unduh <span>→</span>
                    </a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<script>
function incrementDownload(id) {
    // Kirim request background untuk menambah counter tanpa mengganggu download
    fetch('<?= base_url('api/download-counter.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>