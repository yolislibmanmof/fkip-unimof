<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT file_name FROM downloads WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if ($file) @unlink(APP_DIR . '/assets/downloads/' . $file);
        $pdo->prepare("DELETE FROM downloads WHERE id = ?")->execute([$id]);
        flash_message('success', 'File berhasil dihapus.');
    }
    header('Location: download.php'); exit;
}

$downloads = $pdo->query("SELECT * FROM downloads ORDER BY created_at DESC")->fetchAll();
$csrf = generate_csrf_token();
$active_menu = 'download'; $page_heading = 'Kelola Download Center';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header"><h2>📥 Kelola Download Center</h2><a href="download-form.php" class="btn-sm">+ Upload File</a></div>
    <table class="berita-table-pro">
        <thead><tr><th>Judul</th><th>Kategori</th><th>Ukuran</th><th>Diunduh</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($downloads as $d): ?>
        <tr>
            <td><strong><?= sanitize($d['judul']) ?></strong><br><small style="color:#64748b"><?= sanitize($d['file_name']) ?></small></td>
            <td><span class="badge-ultimate badge-info"><?= sanitize($d['kategori']) ?></span></td>
            <td><?= sanitize($d['file_size']) ?></td>
            <td><?= number_format($d['downloads_count']) ?>x</td>
            <td>
                <a href="download-form.php?id=<?= $d['id'] ?>" class="act-btn edit">✏️</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus file ini?')">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="action" value="delete">
                    <button class="act-btn danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>