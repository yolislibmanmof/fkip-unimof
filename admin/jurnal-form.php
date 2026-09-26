<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0 ? $pdo->prepare("SELECT * FROM jurnal WHERE id = ?")->execute([$id])->fetchAll()[0] ?? null : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); $penerbit = trim($_POST['penerbit']); $issn = trim($_POST['issn']);
    $akreditasi = $_POST['akreditasi']; $url = trim($_POST['url']); $deskripsi = trim($_POST['deskripsi']);
    $status = $_POST['status'];

    if ($edit) {
        $pdo->prepare("UPDATE jurnal SET nama=?, penerbit=?, issn=?, akreditasi=?, url=?, deskripsi=?, status=? WHERE id=?")
            ->execute([$nama, $penerbit, $issn, $akreditasi, $url, $deskripsi, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO jurnal (nama, penerbit, issn, akreditasi, url, deskripsi, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nama, $penerbit, $issn, $akreditasi, $url, $deskripsi, $status]);
    }
    header('Location: jurnal.php'); exit;
}

$csrf = generate_csrf_token();
$active_menu = 'jurnal'; $page_heading = $edit ? 'Edit Jurnal' : 'Tambah Jurnal';
require __DIR__ . '/includes/header.php';
?>

<div class="card ultimate" data-aos="fade-up" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Jurnal Ilmiah</h2><a href="jurnal.php" class="btn-sm gray">← Kembali</a></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Nama Jurnal *</label><input class="form-input" name="nama" required value="<?= sanitize($edit['nama'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Penerbit *</label><input class="form-input" name="penerbit" required value="<?= sanitize($edit['penerbit'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">ISSN</label><input class="form-input" name="issn" placeholder="Contoh: 1234-5678" value="<?= sanitize($edit['issn'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Akreditasi</label>
                <select class="form-select" name="akreditasi">
                    <?php foreach(['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6', 'Belum Terakreditasi'] as $a): ?>
                    <option value="<?= $a ?>" <?= ($edit['akreditasi'] ?? 'Belum Terakreditasi') === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">URL Jurnal</label><input class="form-input" name="url" type="url" placeholder="https://..." value="<?= sanitize($edit['url'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Deskripsi Singkat</label><textarea class="form-textarea" name="deskripsi" rows="4"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-sm" style="padding:.7rem 1.6rem">💾 Simpan Jurnal</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>