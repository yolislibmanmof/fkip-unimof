<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) { $stmt = $pdo->prepare("SELECT * FROM alumni WHERE id = ?"); $stmt->execute([$id]); $edit = $stmt->fetch(PDO::FETCH_ASSOC); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); $nim = trim($_POST['nim']); $angkatan = (int)$_POST['angkatan'];
    $pekerjaan = trim($_POST['pekerjaan']); $perusahaan = trim($_POST['perusahaan']);
    $kontak = trim($_POST['kontak']); $testimoni = trim($_POST['testimoni']);
    $foto = $edit['foto'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $up = upload_image($_FILES['foto'], 'alumni', 3 * 1024 * 1024);
        if (!$up['ok']) { flash_message('error', $up['error']); header('Location: alumni-form.php' . ($id ? "?id=$id" : '')); exit; }
        if ($up['name']) { delete_upload($foto, 'alumni'); $foto = $up['name']; }
    }
    if ($edit) { $pdo->prepare("UPDATE alumni SET nama=?, nim=?, angkatan=?, pekerjaan=?, perusahaan=?, kontak=?, testimoni=?, foto=? WHERE id=?")->execute([$nama, $nim, $angkatan, $pekerjaan, $perusahaan, $kontak, $testimoni, $foto, $id]); flash_message('success', '✅ Alumni diperbarui.'); }
    else { $pdo->prepare("INSERT INTO alumni (nama, nim, angkatan, pekerjaan, perusahaan, kontak, testimoni, foto) VALUES (?,?,?,?,?,?,?,?)")->execute([$nama, $nim, $angkatan, $pekerjaan, $perusahaan, $kontak, $testimoni, $foto]); flash_message('success', '✅ Alumni ditambahkan.'); }
    header('Location: alumni.php'); exit;
}
$csrf = generate_csrf_token(); $active_menu = 'alumni'; $page_heading = $edit ? 'Edit Alumni' : 'Tambah Alumni';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Alumni', 'alumni.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>
<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
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
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 4px rgba(139,92,246,0.1); }
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #8b5cf6; background: rgba(139,92,246,0.03); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; text-align: center; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center; }
.preview-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 1.5rem; overflow: hidden; border: 3px solid var(--border); }
.preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-nim { font-size: 0.9rem; color: var(--text-muted); font-family: monospace; margin-bottom: 1.5rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; text-align: left; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-testimoni { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); font-style: italic; border-left: 3px solid #8b5cf6; text-align: left; min-height: 60px; }
.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(139,92,246,0.35); }
@media (max-width: 968px) { .form-layout-extreme { grid-template-columns: 1fr; } .preview-card-extreme { position: static; order: -1; } .form-row { grid-template-columns: 1fr; } }
</style>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme"><h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Alumni</h2><p>Isi data alumni.</p></div>
        <form method="POST" enctype="multipart/form-data" id="alumniForm">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <div class="form-section">
                <div class="form-section-title">👤 Informasi Pribadi</div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Nama Lengkap <span class="required">*</span></label><input type="text" id="nama" name="nama" class="form-input" required value="<?= sanitize($edit['nama'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">NIM</label><input type="text" id="nim" name="nim" class="form-input" value="<?= sanitize($edit['nim'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Angkatan <span class="required">*</span></label><input type="number" id="angkatan" name="angkatan" class="form-input" required min="1900" max="2100" value="<?= $edit['angkatan'] ?? date('Y') ?>"></div>
                    <div class="form-group"><label class="form-label">Kontak (Email/HP)</label><input type="text" id="kontak" name="kontak" class="form-input" value="<?= sanitize($edit['kontak'] ?? '') ?>"></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-title">💼 Karir</div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Pekerjaan</label><input type="text" id="pekerjaan" name="pekerjaan" class="form-input" value="<?= sanitize($edit['pekerjaan'] ?? '') ?>"></div>
                    <div class="form-group"><label class="form-label">Perusahaan/Instansi</label><input type="text" id="perusahaan" name="perusahaan" class="form-input" value="<?= sanitize($edit['perusahaan'] ?? '') ?>"></div>
                </div>
            </div>
            <div class="form-section">
                <div class="form-section-title">💬 Testimoni</div>
                <div class="form-group"><label class="form-label">Testimoni Alumni</label><textarea id="testimoni" name="testimoni" class="form-textarea" rows="4"><?= sanitize($edit['testimoni'] ?? '') ?></textarea></div>
            </div>
            <div class="form-section">
                <div class="form-section-title">📷 Foto Alumni (Opsional)</div>
                <div class="upload-zone" id="uploadZone"><input type="file" id="foto" name="foto" accept="image/*"><div style="font-size: 3rem; margin-bottom: 0.5rem;">📤</div><div style="font-weight: 700;">Drag & drop foto di sini</div><div style="font-size: 0.85rem; color: var(--text-muted);">atau klik untuk memilih (Maks 3MB)</div></div>
                <?php if (!empty($edit['foto'])): ?><div style="margin-top: 1rem; font-size: 0.85rem;"><strong>Foto saat ini:</strong> <?= sanitize($edit['foto']) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="submit-btn-extreme"><?= $edit ? '💾 Perbarui' : '✨ Simpan' ?></button>
        </form>
    </div>
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header"><h3>👁️ Live Preview</h3></div>
        <div class="preview-avatar" id="previewAvatar"><span id="previewInitials">🎓</span></div>
        <h2 class="preview-title" id="previewTitle">Nama alumni akan muncul di sini...</h2>
        <div class="preview-nim" id="previewNim">NIM: -</div>
        <div class="preview-meta">
            <div class="preview-meta-item"><span>🎓</span><strong id="previewAngkatan">-</strong></div>
            <div class="preview-meta-item"><span>💼</span><strong id="previewPekerjaan">-</strong></div>
            <div class="preview-meta-item"><span>🏢</span><strong id="previewPerusahaan">-</strong></div>
        </div>
        <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; text-align: left;">Testimoni:</div>
        <div class="preview-testimoni" id="previewTestimoni"><span style="color: var(--text-muted); font-style: italic;">Testimoni akan muncul di sini...</span></div>
    </div>
</div>

<script>
const uploadZone = document.getElementById('uploadZone'); const fotoInput = document.getElementById('foto');
['dragenter', 'dragover'].forEach(ev => { uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); }); });
['dragleave', 'drop'].forEach(ev => { uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); }); });
uploadZone.addEventListener('drop', e => { if (e.dataTransfer.files.length > 0) handleImage(e.dataTransfer.files[0]); });
fotoInput.addEventListener('change', e => { if (e.target.files.length > 0) handleImage(e.target.files[0]); });
function handleImage(file) { if (!file.type.startsWith('image/')) { alert('Hanya file gambar!'); return; } const dt = new DataTransfer(); dt.items.add(file); fotoInput.files = dt.files; const reader = new FileReader(); reader.onload = e => { document.getElementById('previewAvatar').innerHTML = `<img src="${e.target.result}" alt="">`; }; reader.readAsDataURL(file); updatePreview(); }
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama alumni akan muncul di sini...';
    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewNim').textContent = 'NIM: ' + (document.getElementById('nim').value || '-');
    document.getElementById('previewAngkatan').textContent = 'Angkatan ' + (document.getElementById('angkatan').value || '-');
    document.getElementById('previewPekerjaan').textContent = document.getElementById('pekerjaan').value || '-';
    document.getElementById('previewPerusahaan').textContent = document.getElementById('perusahaan').value || '-';
    const initials = nama.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    if (!document.getElementById('previewAvatar').querySelector('img')) document.getElementById('previewInitials').textContent = initials || '🎓';
    const testi = document.getElementById('testimoni').value;
    document.getElementById('previewTestimoni').textContent = testi || 'Testimoni akan muncul di sini...';
}
['nama', 'nim', 'angkatan', 'pekerjaan', 'perusahaan', 'kontak', 'testimoni'].forEach(id => { document.getElementById(id).addEventListener('input', updatePreview); });
updatePreview();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>