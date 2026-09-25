<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = $id > 0 ? $pdo->prepare("SELECT * FROM agenda WHERE id = ?")->execute([$id])->fetchAll()[0] ?? null : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul']); $jenis = $_POST['jenis']; $status = $_POST['status'];
    $tgl_mulai = $_POST['tanggal_mulai']; $tgl_selesai = $_POST['tanggal_selesai'] ?: null;
    $lokasi = trim($_POST['lokasi']); $deskripsi = trim($_POST['deskripsi']);

    if ($edit) {
        $pdo->prepare("UPDATE agenda SET judul=?, jenis=?, tanggal_mulai=?, tanggal_selesai=?, lokasi=?, deskripsi=?, status=? WHERE id=?")
            ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $deskripsi, $status, $id]);
    } else {
        $pdo->prepare("INSERT INTO agenda (judul, jenis, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $deskripsi, $status]);
    }
    header('Location: agenda.php'); exit;
}
$csrf = generate_csrf_token();
$active_menu = 'agenda'; $page_heading = $edit ? 'Edit Agenda' : 'Tambah Agenda';
require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Agenda</h2><a href="agenda.php" class="btn-sm gray">← Kembali</a></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
        <div class="form-group"><label class="form-label">Judul Kegiatan *</label><input class="form-input" name="judul" required value="<?= sanitize($edit['judul'] ?? '') ?>"></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Jenis *</label>
                <select class="form-select" name="jenis">
                    <?php foreach(['ujian','libur','seminar','wisuda','pmb','umum'] as $j): ?>
                    <option value="<?= $j ?>" <?= ($edit['jenis']??'') === $j ? 'selected' : '' ?>><?= ucfirst($j) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Status *</label>
                <select class="form-select" name="status">
                    <?php foreach(['Aktif','Selesai','Dibatalkan'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($edit['status']??'Aktif') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Tanggal Mulai *</label><input class="form-input" type="date" name="tanggal_mulai" required value="<?= $edit['tanggal_mulai'] ?? '' ?>"></div>
            <div class="form-group"><label class="form-label">Tanggal Selesai (Opsional)</label><input class="form-input" type="date" name="tanggal_selesai" value="<?= $edit['tanggal_selesai'] ?? '' ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Lokasi</label><input class="form-input" name="lokasi" value="<?= sanitize($edit['lokasi'] ?? '') ?>"></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><textarea class="form-textarea" name="deskripsi"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea></div>
        <button type="submit" class="btn-sm" style="padding:.7rem 1.6rem">💾 Simpan Agenda</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>