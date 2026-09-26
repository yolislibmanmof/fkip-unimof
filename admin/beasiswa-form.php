<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0 ? $pdo->prepare("SELECT * FROM beasiswa WHERE id = ?")->execute([$id])->fetchAll()[0] ?? null : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); $jenis = $_POST['jenis']; $sumber = trim($_POST['sumber']);
    $nominal = trim($_POST['nominal']); $syarat = trim($_POST['syarat']); $deadline = $_POST['deadline'] ?: null;
    $status = $_POST['status'];

    if ($edit) {
        $pdo->prepare("UPDATE beasiswa SET nama=?, jenis=?, sumber=?, nominal=?, syarat=?, deadline=?, status=? WHERE id=?")
            ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deadline, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO beasiswa (nama, jenis, sumber, nominal, syarat, deadline, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deadline, $status]);
    }
    header('Location: beasiswa.php'); exit;
}

$csrf = generate_csrf_token();
$active_menu = 'beasiswa'; $page_heading = $edit ? 'Edit Beasiswa' : 'Tambah Beasiswa';
require __DIR__ . '/includes/header.php';
?>

<div class="card ultimate" data-aos="fade-up" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Beasiswa</h2><a href="beasiswa.php" class="btn-sm gray">← Kembali</a></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Nama Beasiswa *</label><input class="form-input" name="nama" required value="<?= sanitize($edit['nama'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Jenis Beasiswa *</label>
                <select class="form-select" name="jenis">
                    <?php foreach(['Prestasi Akademik', 'KIP Kuliah', 'Muhammadiyah', 'Talent Scouting', 'Lainnya'] as $j): ?>
                    <option value="<?= $j ?>" <?= ($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Sumber/Pemberi</label><input class="form-input" name="sumber" value="<?= sanitize($edit['sumber'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Nominal/Cakupan</label><input class="form-input" name="nominal" placeholder="Contoh: 100% SPP + Uang Saku" value="<?= sanitize($edit['nominal'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Deadline Pendaftaran</label><input class="form-input" type="date" name="deadline" value="<?= $edit['deadline'] ?? '' ?>"></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="Terbuka" <?= ($edit['status'] ?? 'Terbuka') === 'Terbuka' ? 'selected' : '' ?>>Terbuka</option>
                    <option value="Tertutup" <?= ($edit['status'] ?? '') === 'Tertutup' ? 'selected' : '' ?>>Tertutup</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Syarat & Ketentuan *</label><textarea class="form-textarea" name="syarat" rows="6" required placeholder="1. ...&#10;2. ..."><?= sanitize($edit['syarat'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-sm" style="padding:.7rem 1.6rem">💾 Simpan Beasiswa</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>