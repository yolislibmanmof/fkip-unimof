<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0 ? $pdo->prepare("SELECT * FROM fasilitas WHERE id = ?")->execute([$id])->fetchAll()[0] ?? null : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); $kategori = $_POST['kategori']; $deskripsi = trim($_POST['deskripsi']);
    $kapasitas = (int)($_POST['kapasitas'] ?? 0); $status = $_POST['status'];
    $gambar = $edit['gambar'] ?? null;

    if (!empty($_FILES['gambar']['name'])) {
        $up = upload_image($_FILES['gambar'], 'fasilitas', 5 * 1024 * 1024); // Max 5MB
        if (!$up['ok']) { flash_message('error', $up['error']); header('Location: fasilitas-form.php' . ($id?"?id=$id":'')); exit; }
        if ($up['name']) { delete_upload($gambar, 'fasilitas'); $gambar = $up['name']; }
    }

    if ($edit) {
        $pdo->prepare("UPDATE fasilitas SET nama=?, kategori=?, deskripsi=?, gambar=?, kapasitas=?, status=? WHERE id=?")
            ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO fasilitas (nama, kategori, deskripsi, gambar, kapasitas, status) VALUES (?,?,?,?,?,?)")
            ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status]);
    }
    header('Location: fasilitas.php'); exit;
}

$csrf = generate_csrf_token();
$active_menu = 'fasilitas'; $page_heading = $edit ? 'Edit Fasilitas' : 'Tambah Fasilitas';
require __DIR__ . '/includes/header.php';
?>

<div class="card ultimate" data-aos="fade-up" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Fasilitas</h2><a href="fasilitas.php" class="btn-sm gray">← Kembali</a></div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Nama Fasilitas *</label><input class="form-input" name="nama" required value="<?= sanitize($edit['nama'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Kategori *</label>
                <select class="form-select" name="kategori">
                    <?php foreach(['Laboratorium', 'Ruang Kelas', 'Perpustakaan', 'Fasilitas Umum', 'Lainnya'] as $k): ?>
                    <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Lainnya') === $k ? 'selected' : '' ?>><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Kapasitas (Orang)</label><input class="form-input" type="number" name="kapasitas" value="<?= $edit['kapasitas'] ?? 0 ?>"></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Foto Fasilitas</label>
            <input class="form-input" type="file" name="gambar" accept="image/*">
            <?php if (!empty($edit['gambar'])): ?>
                <div style="margin-top:0.5rem"><img src="<?= asset('uploads/fasilitas/' . basename($edit['gambar'])) ?>" style="max-width:200px;border-radius:8px;"><br><small style="color:#64748b">Upload baru untuk mengganti</small></div>
            <?php endif; ?>
        </div>
        <div class="form-group"><label class="form-label">Deskripsi Lengkap</label><textarea class="form-textarea" name="deskripsi" rows="5"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-sm" style="padding:.7rem 1.6rem">💾 Simpan Fasilitas</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>