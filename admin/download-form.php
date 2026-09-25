<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0 ? $pdo->prepare("SELECT * FROM downloads WHERE id = ?")->execute([$id])->fetchAll()[0] ?? null : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul']); $kategori = $_POST['kategori']; $deskripsi = trim($_POST['deskripsi']);
    $file_name = $edit['file_name'] ?? null; $file_size = $edit['file_size'] ?? null;

    if (!empty($_FILES['file']['name'])) {
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-rar-compressed'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        if (!in_array($mime, $allowed)) { flash_message('error', 'Format file tidak diizinkan (Hanya PDF, DOC, DOCX, ZIP, RAR).'); header('Location: download-form.php' . ($id?"?id=$id":'')); exit; }
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) { flash_message('error', 'Ukuran file maksimal 10MB.'); exit; }
        
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $file_size = number_format($_FILES['file']['size'] / 1024, 1) . ' KB';
        
        $dir = APP_DIR . '/assets/downloads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $file_name);
        
        if ($edit && $edit['file_name']) @unlink($dir . '/' . $edit['file_name']);
    }

    if ($edit) {
        $pdo->prepare("UPDATE downloads SET judul=?, kategori=?, deskripsi=?, file_name=?, file_size=? WHERE id=?")
            ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size, $id]);
    } else {
        $pdo->prepare("INSERT INTO downloads (judul, kategori, deskripsi, file_name, file_size) VALUES (?,?,?,?,?)")
            ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size]);
    }
    header('Location: download.php'); exit;
}
$csrf = generate_csrf_token();
$active_menu = 'download'; $page_heading = $edit ? 'Edit File' : 'Upload File';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header"><h2><?= $edit ? '✏️ Edit' : '📤 Upload' ?> File</h2><a href="download.php" class="btn-sm gray">← Kembali</a></div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="form-group"><label class="form-label">Judul Dokumen *</label><input class="form-input" name="judul" required value="<?= sanitize($edit['judul'] ?? '') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Kategori *</label>
                <select class="form-select" name="kategori">
                    <?php foreach(['Akademik','PMB','Formulir','Pedoman','Lainnya'] as $k): ?>
                    <option value="<?= $k ?>" <?= ($edit['kategori']??'') === $k ? 'selected' : '' ?>><?= $k ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">File (Maks 10MB: PDF, DOC, DOCX, ZIP, RAR)</label>
                <input class="form-input" type="file" name="file" <?= $edit ? '' : 'required' ?>>
                <?php if ($edit): ?><small style="color:#64748b">File saat ini: <?= sanitize($edit['file_name']) ?></small><?php endif; ?>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Deskripsi</label><textarea class="form-textarea" name="deskripsi"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-sm" style="padding:.7rem 1.6rem">💾 Simpan</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>