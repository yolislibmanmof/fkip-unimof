<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) { $stmt = $pdo->prepare("SELECT * FROM riset WHERE id = ?"); $stmt->execute([$id]); $edit = $stmt->fetch(PDO::FETCH_ASSOC); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul']); $penulis = trim($_POST['penulis']); $tahun = (int)$_POST['tahun'];
    $kategori = $_POST['kategori']; $abstrak = trim($_POST['abstrak']); $status = $_POST['status'];
    $file = $edit['file_pdf'] ?? null;
    if (!empty($_FILES['file_pdf']['name'])) {
        $up = upload_image($_FILES['file_pdf'], 'riset', 10 * 1024 * 1024); // Max 10MB PDF
        if (!$up['ok']) { flash_message('error', $up['error']); header('Location: riset-form.php' . ($id ? "?id=$id" : '')); exit; }
        if ($up['name']) { delete_upload($file, 'riset'); $file = $up['name']; }
    }
    if ($edit) { $pdo->prepare("UPDATE riset SET judul=?, penulis=?, tahun=?, kategori=?, abstrak=?, file_pdf=?, status=? WHERE id=?")->execute([$judul, $penulis, $tahun, $kategori, $abstrak, $file, $status, $id]); flash_message('success', '✅ Riset diperbarui.'); }
    else { $pdo->prepare("INSERT INTO riset (judul, penulis, tahun, kategori, abstrak, file_pdf, status) VALUES (?,?,?,?,?,?,?)")->execute([$judul, $penulis, $tahun, $kategori, $abstrak, $file, $status]); flash_message('success', '✅ Riset ditambahkan.'); }
    header('Location: riset.php'); exit;
}
$csrf = generate_csrf_token(); $active_menu = 'riset'; $page_heading = $edit ? 'Edit Riset' : 'Tambah Riset';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Riset', 'riset.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>
<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb); }
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
.form-textarea { resize: vertical; min-height: 120px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #3b82f6; background: rgba(59,130,246,0.03); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: #dbeafe; color: #1e40af; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-abstrak { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); border-left: 3px solid #3b82f6; min-height: 60px; }
.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(59,130,246,0.35); }
@media (max-width: 968px) { .form-layout-extreme { grid-template-columns: 1fr; } .preview-card-extreme { position: static; order: -1; } .form-row { grid-template-columns: 1fr; } }
</style>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Riset</h2><p>Isi detail publikasi riset.</p></div>
        <form method="POST" enctype="multipart/form-data" id="risetForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <div class="form-section">
                <div class="form-section-title">📄 Informasi Riset</div>
                <div class="form-group" style="margin-bottom: 1rem;"><label class="form-label">Judul Riset <span class="required">*</span></label><input type="text" id="judul" name="judul" class="form-input" required value="<?= sanitize($edit['judul'] ?? '') ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Penulis <span class="required">*</span></label><input type="text" id="penulis" name="penulis" class="form-input" required value="<?= sanitize($edit['penulis'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Tahun <span class="required">*</span></label><input type="number" id="tahun" name="tahun" class="form-input" required min="1900" max="2100" value="<?= $edit['tahun'] ?? date('Y') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Kategori</label><select id="kategori" name="kategori" class="form-select"><option value="Pendidikan" <?= ($edit['kategori'] ?? '') === 'Pendidikan' ? 'selected' : '' ?>>🎓 Pendidikan</option><option value="Teknologi" <?= ($edit['kategori'] ?? '') === 'Teknologi' ? 'selected' : '' ?>>💻 Teknologi</option><option value="Sosial" <?= ($edit['kategori'] ?? '') === 'Sosial' ? 'selected' : '' ?>>🤝 Sosial</option></select></div>
                    <div class="form-group"><label class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'selected' : '' ?>>✅ Published</option><option value="Draft" <?= ($edit['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>📝 Draft</option></select></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-title">📝 Abstrak</div>
                <div class="form-group"><label class="form-label">Abstrak / Deskripsi</label><textarea id="abstrak" name="abstrak" class="form-textarea" rows="5"><?= sanitize($edit['abstrak'] ?? '') ?></textarea></div>
            </div>
            <div class="form-section">
                <div class="form-section-title">📎 File PDF (Opsional)</div>
                <div class="upload-zone" id="uploadZone"><input type="file" id="file_pdf" name="file_pdf" accept=".pdf"><div style="font-size: 3rem; margin-bottom: 0.5rem;">📤</div><div style="font-weight: 700;">Drag & drop PDF di sini</div><div style="font-size: 0.85rem; color: var(--text-muted);">atau klik untuk memilih (Maks 10MB)</div></div>
                <?php if (!empty($edit['file_pdf'])): ?><div style="margin-top: 1rem; font-size: 0.85rem;"><strong>File saat ini:</strong> <?= sanitize($edit['file_pdf']) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="submit-btn-extreme"><?= $edit ? '💾 Perbarui' : '✨ Simpan' ?></button>
        </form>
    </div>
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header"><h3>👁️ Live Preview</h3></div>
        <div class="preview-badge" id="previewBadge">📄 PENDIDIKAN</div>
        <h2 class="preview-title" id="previewTitle">Judul riset akan muncul di sini...</h2>
        <div class="preview-meta">
            <div class="preview-meta-item"><span>✍️</span><strong id="previewPenulis">-</strong></div>
            <div class="preview-meta-item"><span>📅</span><strong id="previewTahun">-</strong></div>
            <div class="preview-meta-item"><span>📊</span><strong id="previewStatus">✅ Published</strong></div>
        </div>
        <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem;">Abstrak:</div>
        <div class="preview-abstrak" id="previewAbstrak"><span style="color: var(--text-muted); font-style: italic;">Abstrak akan muncul di sini...</span></div>
    </div>
</div>

<script>
function updatePreview() {
    document.getElementById('previewTitle').textContent = document.getElementById('judul').value || 'Judul riset akan muncul di sini...';
    document.getElementById('previewPenulis').textContent = document.getElementById('penulis').value || '-';
    document.getElementById('previewTahun').textContent = document.getElementById('tahun').value || '-';
    document.getElementById('previewStatus').textContent = (document.getElementById('status').value === 'Published' ? '✅ ' : ' ') + document.getElementById('status').value;
    const kat = document.getElementById('kategori').value;
    document.getElementById('previewBadge').textContent = ' ' + kat.toUpperCase();
    const abstrak = document.getElementById('abstrak').value;
    document.getElementById('previewAbstrak').textContent = abstrak || 'Abstrak akan muncul di sini...';
}
['judul', 'penulis', 'tahun', 'kategori', 'abstrak', 'status'].forEach(id => { document.getElementById(id).addEventListener('input', updatePreview); document.getElementById(id).addEventListener('change', updatePreview); });
updatePreview();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>