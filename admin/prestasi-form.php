<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) { $stmt = $pdo->prepare("SELECT * FROM prestasi WHERE id = ?"); $stmt->execute([$id]); $edit = $stmt->fetch(PDO::FETCH_ASSOC); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama_prestasi']); $peserta = trim($_POST['nama_peserta']);
    $tingkat = $_POST['tingkat']; $tahun = (int)$_POST['tahun']; $deskripsi = trim($_POST['deskripsi']);
    $foto = $edit['foto'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $up = upload_image($_FILES['foto'], 'prestasi', 3 * 1024 * 1024);
        if (!$up['ok']) { flash_message('error', $up['error']); header('Location: prestasi-form.php' . ($id ? "?id=$id" : '')); exit; }
        if ($up['name']) { delete_upload($foto, 'prestasi'); $foto = $up['name']; }
    }
    if ($edit) { $pdo->prepare("UPDATE prestasi SET nama_prestasi=?, nama_peserta=?, tingkat=?, tahun=?, deskripsi=?, foto=? WHERE id=?")->execute([$nama, $peserta, $tingkat, $tahun, $deskripsi, $foto, $id]); flash_message('success', '✅ Prestasi diperbarui.'); }
    else { $pdo->prepare("INSERT INTO prestasi (nama_prestasi, nama_peserta, tingkat, tahun, deskripsi, foto) VALUES (?,?,?,?,?,?)")->execute([$nama, $peserta, $tingkat, $tahun, $deskripsi, $foto]); flash_message('success', '✅ Prestasi ditambahkan.'); }
    header('Location: prestasi.php'); exit;
}
$csrf = generate_csrf_token(); $active_menu = 'prestasi'; $page_heading = $edit ? 'Edit Prestasi' : 'Tambah Prestasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Prestasi', 'prestasi.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>
<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #f59e0b, #d97706); }
.form-header-extreme { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-extreme h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.form-section { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border); margin-bottom: 1.5rem; }
.form-section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.form-label .required { color: #ef4444; margin-left: 0.25rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-textarea { resize: vertical; min-height: 100px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #f59e0b; background: rgba(245,158,11,0.03); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; text-align: center; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center; }
.preview-trophy { font-size: 5rem; margin-bottom: 1rem; animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-peserta { font-size: 1rem; color: var(--text-muted); margin-bottom: 1.5rem; }
.preview-meta { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.preview-meta-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); text-align: left; border-left: 3px solid #f59e0b; }
.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(245,158,11,0.35); }
@media (max-width: 968px) { .form-layout-extreme { grid-template-columns: 1fr; } .preview-card-extreme { position: static; order: -1; } .form-row { grid-template-columns: 1fr; } }
</style>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Prestasi</h2><p>Isi detail pencapaian prestasi.</p></div>
        <form method="POST" enctype="multipart/form-data" id="prestasiForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <div class="form-section">
                <div class="form-section-title">🏆 Detail Prestasi</div>
                <div class="form-group" style="margin-bottom: 1rem;"><label class="form-label">Nama Prestasi <span class="required">*</span></label><input type="text" id="nama_prestasi" name="nama_prestasi" class="form-input" required value="<?= sanitize($edit['nama_prestasi'] ?? '') ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Nama Peserta <span class="required">*</span></label><input type="text" id="nama_peserta" name="nama_peserta" class="form-input" required value="<?= sanitize($edit['nama_peserta'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Tahun <span class="required">*</span></label><input type="number" id="tahun" name="tahun" class="form-input" required min="1900" max="2100" value="<?= $edit['tahun'] ?? date('Y') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Tingkat <span class="required">*</span></label><select id="tingkat" name="tingkat" class="form-select" required><option value="Sekolah" <?= ($edit['tingkat'] ?? '') === 'Sekolah' ? 'selected' : '' ?>>🏫 Sekolah</option><option value="Kabupaten" <?= ($edit['tingkat'] ?? '') === 'Kabupaten' ? 'selected' : '' ?>>🏛️ Kabupaten</option><option value="Provinsi" <?= ($edit['tingkat'] ?? '') === 'Provinsi' ? 'selected' : '' ?>>🏛️ Provinsi</option><option value="Nasional" <?= ($edit['tingkat'] ?? '') === 'Nasional' ? 'selected' : '' ?>>🇮🇩 Nasional</option><option value="Internasional" <?= ($edit['tingkat'] ?? '') === 'Internasional' ? 'selected' : '' ?>>🌍 Internasional</option></select></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi</div>
                <div class="form-group"><label class="form-label">Deskripsi Prestasi</label><textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="4"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea></div>
            </div>
            <div class="form-section">
                <div class="form-section-title">📷 Foto Prestasi (Opsional)</div>
                <div class="upload-zone" id="uploadZone"><input type="file" id="foto" name="foto" accept="image/*"><div style="font-size: 3rem; margin-bottom: 0.5rem;"></div><div style="font-weight: 700;">Drag & drop foto di sini</div><div style="font-size: 0.85rem; color: var(--text-muted);">atau klik untuk memilih (Maks 3MB)</div></div>
                <?php if (!empty($edit['foto'])): ?><div style="margin-top: 1rem; font-size: 0.85rem;"><strong>Foto saat ini:</strong> <?= sanitize($edit['foto']) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="submit-btn-extreme"><?= $edit ? '💾 Perbarui' : '✨ Simpan' ?></button>
        </form>
    </div>
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header"><h3>👁️ Live Preview</h3></div>
        <div class="preview-trophy">🏆</div>
        <div class="preview-badge" id="previewBadge">🥇 NASIONAL</div>
        <h2 class="preview-title" id="previewTitle">Nama prestasi akan muncul di sini...</h2>
        <div class="preview-peserta" id="previewPeserta">Oleh: -</div>
        <div class="preview-meta">
            <div class="preview-meta-item"><span>📅</span><strong id="previewTahun">-</strong></div>
        </div>
        <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; text-align: left;">Deskripsi:</div>
        <div class="preview-desc" id="previewDesc"><span style="color: var(--text-muted); font-style: italic;">Deskripsi akan muncul di sini...</span></div>
    </div>
</div>

<script>
function updatePreview() {
    document.getElementById('previewTitle').textContent = document.getElementById('nama_prestasi').value || 'Nama prestasi akan muncul di sini...';
    document.getElementById('previewPeserta').textContent = 'Oleh: ' + (document.getElementById('nama_peserta').value || '-');
    document.getElementById('previewTahun').textContent = document.getElementById('tahun').value || '-';
    const tingkat = document.getElementById('tingkat').value;
    document.getElementById('previewBadge').textContent = '🏆 ' + tingkat.toUpperCase();
    const desc = document.getElementById('deskripsi').value;
    document.getElementById('previewDesc').textContent = desc || 'Deskripsi akan muncul di sini...';
}
['nama_prestasi', 'nama_peserta', 'tahun', 'tingkat', 'deskripsi'].forEach(id => { document.getElementById(id).addEventListener('input', updatePreview); document.getElementById(id).addEventListener('change', updatePreview); });
updatePreview();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>