<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT gambar FROM fasilitas WHERE id = ?");
        $stmt->execute([$id]); $img = $stmt->fetchColumn();
        delete_upload($img, 'fasilitas');
        $pdo->prepare("DELETE FROM fasilitas WHERE id = ?")->execute([$id]);
        flash_message('success', 'Fasilitas berhasil dihapus.');
    }
    header('Location: fasilitas.php'); exit;
}

$fasilitas_list = $pdo->query("SELECT * FROM fasilitas ORDER BY created_at DESC")->fetchAll();
$stat_total = count($fasilitas_list);
$stat_lab = (int)$pdo->query("SELECT COUNT(*) FROM fasilitas WHERE kategori='Laboratorium'")->fetchColumn();
$stat_kapasitas = (int)$pdo->query("SELECT SUM(kapasitas) FROM fasilitas")->fetchColumn();

$csrf = generate_csrf_token();
$active_menu = 'fasilitas'; $page_heading = 'Kelola Fasilitas & Lab';
require __DIR__ . '/includes/header.php';
?>

<div class="stats-ultimate" style="margin-bottom: 2rem;">
    <div class="stat-ultimate-card" style="--card-accent:#8b5cf6" data-aos="fade-up">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">🏢</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_total ?>">0</span></div>
        <div class="stat-ultimate-label">Total Fasilitas</div>
    </div>
    <div class="stat-ultimate-card" style="--card-accent:#ef4444" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">🧪</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_lab ?>">0</span></div>
        <div class="stat-ultimate-label">Laboratorium</div>
    </div>
    <div class="stat-ultimate-card" style="--card-accent:#10b981" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-ultimate-header"><div class="stat-ultimate-icon">👥</div></div>
        <div class="stat-ultimate-value"><span class="count-up" data-target="<?= $stat_kapasitas ?>">0</span></div>
        <div class="stat-ultimate-label">Total Kapasitas</div>
    </div>
</div>

<div class="card ultimate" data-aos="fade-up">
    <div class="card-header"><h2>🏢 Daftar Fasilitas & Laboratorium</h2><a href="fasilitas-form.php" class="btn-sm">+ Tambah Fasilitas</a></div>
    <div class="berita-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
        <?php if (empty($fasilitas_list)): ?>
            <div class="empty-state-ultimate" style="grid-column: 1/-1;"><div class="empty-illustration">🏢</div><p>Belum ada data fasilitas.</p></div>
        <?php else: foreach ($fasilitas_list as $f): 
            $icon = $f['kategori'] === 'Laboratorium' ? '🧪' : ($f['kategori'] === 'Perpustakaan' ? '📚' : '🏫');
        ?>
        <div class="berita-ultimate-item" style="flex-direction: column; align-items: stretch; padding: 0; overflow: hidden;">
            <div style="height: 160px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 3rem; position: relative;">
                <?php if ($f['gambar']): ?>
                    <img src="<?= asset('uploads/fasilitas/' . basename($f['gambar'])) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= $icon ?>
                <?php endif; ?>
                <span class="badge-ultimate badge-info" style="position:absolute;top:10px;left:10px;"><?= sanitize($f['kategori']) ?></span>
            </div>
            <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column;">
                <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;"><?= sanitize($f['nama']) ?></h4>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?= excerpt($f['deskripsi'], 100) ?></p>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: #0a6847;">👥 Kapasitas: <?= $f['kapasitas'] ?></span>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="fasilitas-form.php?id=<?= $f['id'] ?>" class="act-btn edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus fasilitas ini?')">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="act-btn danger" title="Hapus">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>