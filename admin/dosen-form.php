<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM dosen WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']);
    $nip = trim($_POST['nip']);
    $jabatan = $_POST['jabatan_fungsional'];
    $pendidikan = trim($_POST['pendidikan_terakhir']);
    $prodi_id = (int)($_POST['program_studi_id'] ?? 0);
    $bio = trim($_POST['bio']);
    $status = $_POST['status'];
    $foto = $edit['foto'] ?? null;

    if (!empty($_FILES['foto']['name'])) {
        $up = upload_image($_FILES['foto'], 'dosen', 3 * 1024 * 1024);
        if (!$up['ok']) {
            flash_message('error', $up['error']);
            header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($up['name']) {
            delete_upload($foto, 'dosen');
            $foto = $up['name'];
        }
    }

    if ($edit) {
        $pdo->prepare("UPDATE dosen SET nama=?, nip=?, jabatan_fungsional=?, pendidikan_terakhir=?, program_studi_id=?, bio=?, foto=?, status=? WHERE id=?")
            ->execute([$nama, $nip, $jabatan, $pendidikan, $prodi_id, $bio, $foto, $status, $id]);
        flash_message('success', '✅ Data dosen berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO dosen (nama, nip, jabatan_fungsional, pendidikan_terakhir, program_studi_id, bio, foto, status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$nama, $nip, $jabatan, $pendidikan, $prodi_id, $bio, $foto, $status]);
        flash_message('success', '✅ Dosen baru berhasil ditambahkan.');
    }
    header('Location: dosen.php');
    exit;
}

$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();
$csrf = generate_csrf_token();
$active_menu = 'dosen';
$page_heading = $edit ? 'Edit Dosen' : 'Tambah Dosen';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Data Dosen', 'dosen.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #10b981, #059669, #047857); }
.form-header-extreme { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-extreme h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.form-header-extreme p { color: var(--text-muted); font-size: 0.9rem; }
.form-section { background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border); margin-bottom: 1.5rem; }
.form-section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.form-label .required { color: #ef4444; margin-left: 0.25rem; }
.form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-textarea { resize: vertical; min-height: 120px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #10b981; background: rgba(16,185,129,0.03); }
.upload-zone-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.upload-zone-text { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-zone-sub { font-size: 0.85rem; color: var(--text-muted); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.image-preview { display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); align-items: center; gap: 1rem; }
.image-preview.show { display: flex; }
.image-preview-img { width: 80px; height: 80px; border-radius: 12px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); }
.image-preview-img img { width: 100%; height: 100%; object-fit: cover; }
.image-preview-info { flex: 1; min-width: 0; }
.image-preview-info h4 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.image-preview-info small { font-size: 0.78rem; color: var(--text-muted); }
.image-preview-remove { background: #fee2e2; color: #dc2626; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
.image-preview-remove:hover { background: #dc2626; color: white; }

.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-header p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }
.preview-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #0a6847, #16a34a); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 1.5rem; overflow: hidden; border: 3px solid var(--border); }
.preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; text-align: center; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-nip { text-align: center; font-size: 0.9rem; color: var(--text-muted); font-family: monospace; margin-bottom: 1.5rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-bio { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 60px; border-left: 3px solid #10b981; }
.preview-empty { color: var(--text-muted); font-style: italic; }

.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16,185,129,0.35); }
.submit-btn-extreme:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-extreme .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
.submit-btn-extreme.loading .spinner { display: inline-block; }
.submit-btn-extreme.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

.autosave-indicator { position: fixed; bottom: 2rem; right: 2rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 999px; padding: 0.75rem 1.25rem; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; opacity: 0; transform: translateY(20px); transition: all 0.3s; z-index: 100; }
.autosave-indicator.show { opacity: 1; transform: translateY(0); }
.autosave-indicator.saving { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.autosave-indicator.saved { background: #dcfce7; border-color: #86efac; color: #166534; }

@media (max-width: 968px) { .form-layout-extreme { grid-template-columns: 1fr; } .preview-card-extreme { position: static; order: -1; } .form-row { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .form-card-extreme, .preview-card-extreme { padding: 1.5rem; } }
</style>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Dosen</h2>
            <p><?= $edit ? 'Perbarui data dosen.' : 'Isi data dosen baru.' ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="dosenForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">👤 Informasi Pribadi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" id="nama" name="nama" class="form-input" required placeholder="Contoh: Dr. Ahmad, M.Pd." value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIP</label>
                        <input type="text" id="nip" name="nip" class="form-input" placeholder="Contoh: 198001012005011001" value="<?= sanitize($edit['nip'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jabatan Fungsional <span class="required">*</span></label>
                        <select id="jabatan_fungsional" name="jabatan_fungsional" class="form-select" required>
                            <?php foreach(['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jabatan_fungsional'] ?? 'Tenaga Pengajar') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pendidikan Terakhir</label>
                        <input type="text" id="pendidikan_terakhir" name="pendidikan_terakhir" class="form-input" placeholder="Contoh: S3 Pendidikan" value="<?= sanitize($edit['pendidikan_terakhir'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Program Studi</label>
                        <select id="program_studi_id" name="program_studi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['program_studi_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>> Non-Aktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"> Foto Dosen</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file (Maks 3MB)</div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span>👤</span></div>
                    <div class="image-preview-info">
                        <h4 id="imageName">-</h4>
                        <small id="imageSize">-</small>
                    </div>
                    <button type="button" class="image-preview-remove" onclick="removeImage()">✕</button>
                </div>
                <?php if (!empty($edit['foto'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; display: flex; align-items: center; gap: 1rem;">
                        <img src="<?= asset('uploads/dosen/' . basename($edit['foto'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div>
                            <strong>Foto saat ini:</strong> <?= sanitize($edit['foto']) ?>
                            <br><small style="color: var(--text-muted);">Upload foto baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <div class="form-section-title">📝 Biografi Singkat</div>
                <div class="form-group">
                    <label class="form-label">Bio Dosen</label>
                    <textarea id="bio" name="bio" class="form-textarea" rows="5" placeholder="Tuliskan biografi singkat dosen..."><?= sanitize($edit['bio'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Biografi akan tampil di halaman profil dosen</span>
                        <span><span id="bioCounter"><?= strlen($edit['bio'] ?? '') ?></span> karakter</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Dosen' ?></span>
                <span class="spinner"></span>
            </button>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                💡 Tip: Tekan <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+S</kbd> untuk menyimpan cepat
            </p>
        </form>
    </div>

    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <h3>👁️ Live Preview</h3>
            <p>Pratinjau tampilan di website publik</p>
        </div>

        <div id="previewContent">
            <div class="preview-avatar" id="previewAvatar"><span id="previewInitials">👤</span></div>
            <h2 class="preview-title" id="previewTitle">Nama dosen akan muncul di sini...</h2>
            <div class="preview-nip" id="previewNip">NIP: -</div>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>🎖️</span>
                    <strong id="previewJabatan">Tenaga Pengajar</strong>
                </div>
                <div class="preview-meta-item">
                    <span>🎓</span>
                    <strong id="previewPendidikan">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>🏫</span>
                    <strong id="previewProdi">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span></span>
                    <strong id="previewStatus">✅ Aktif</strong>
                </div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Biografi:</div>
            <div class="preview-bio" id="previewBio">
                <span class="preview-empty">Biografi dosen akan muncul di sini...</span>
            </div>
        </div>
    </div>
</div>

<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
const uploadZone = document.getElementById('uploadZone');
const fotoInput = document.getElementById('foto');
const imagePreview = document.getElementById('imagePreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleImage(files[0]);
});
fotoInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleImage(e.target.files[0]);
});

function handleImage(file) {
    if (!file.type.startsWith('image/')) { alert('Hanya file gambar yang diizinkan!'); return; }
    if (file.size > 3 * 1024 * 1024) { alert('Ukuran file maksimal 3MB!'); return; }
    
    const dt = new DataTransfer();
    dt.items.add(file);
    fotoInput.files = dt.files;
    
    document.getElementById('imageName').textContent = file.name;
    document.getElementById('imageSize').textContent = formatFileSize(file.size);
    
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imagePreviewImg').innerHTML = `<img src="${e.target.result}" alt="">`;
        document.getElementById('previewAvatar').innerHTML = `<img src="${e.target.result}" alt="">`;
    };
    reader.readAsDataURL(file);
    
    imagePreview.classList.add('show');
    uploadZone.style.display = 'none';
    updatePreview();
    triggerAutosave();
}

function removeImage() {
    fotoInput.value = '';
    imagePreview.classList.remove('show');
    uploadZone.style.display = 'block';
    document.getElementById('previewAvatar').innerHTML = '<span id="previewInitials">👤</span>';
    updatePreview();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama dosen akan muncul di sini...';
    const nip = document.getElementById('nip').value || '-';
    const jabatan = document.getElementById('jabatan_fungsional').value || 'Tenaga Pengajar';
    const pendidikan = document.getElementById('pendidikan_terakhir').value || '-';
    const bio = document.getElementById('bio').value;
    const status = document.getElementById('status').value || 'Aktif';
    
    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect.options[prodiSelect.selectedIndex]?.text || '-';

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewNip').textContent = 'NIP: ' + (nip || '-');
    document.getElementById('previewJabatan').textContent = jabatan;
    document.getElementById('previewPendidikan').textContent = pendidikan;
    document.getElementById('previewProdi').textContent = prodiText;
    document.getElementById('previewStatus').textContent = (status === 'Aktif' ? '✅' : '❌') + ' ' + status;
    
    const initials = nama.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    if (!document.getElementById('previewAvatar').querySelector('img')) {
        document.getElementById('previewInitials').textContent = initials || '👤';
    }
    
    if (bio) {
        document.getElementById('previewBio').textContent = bio;
        document.getElementById('previewBio').classList.remove('preview-empty');
    } else {
        document.getElementById('previewBio').innerHTML = '<span class="preview-empty">Biografi dosen akan muncul di sini...</span>';
    }
}

document.getElementById('bio').addEventListener('input', function() {
    document.getElementById('bioCounter').textContent = this.value.length;
    triggerAutosave();
});

const storageKey = 'fkip_dosen_draft_<?= $id ?: "new" ?>';
let autosaveTimer;

function triggerAutosave() {
    clearTimeout(autosaveTimer);
    const indicator = document.getElementById('autosaveIndicator');
    indicator.classList.add('show', 'saving');
    indicator.classList.remove('saved');
    document.getElementById('autosaveIcon').textContent = '';
    document.getElementById('autosaveText').textContent = 'Menyimpan draft...';

    autosaveTimer = setTimeout(() => {
        const data = {
            nama: document.getElementById('nama').value,
            nip: document.getElementById('nip').value,
            jabatan_fungsional: document.getElementById('jabatan_fungsional').value,
            pendidikan_terakhir: document.getElementById('pendidikan_terakhir').value,
            program_studi_id: document.getElementById('program_studi_id').value,
            bio: document.getElementById('bio').value,
            status: document.getElementById('status').value,
            saved_at: new Date().toISOString()
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(data));
            indicator.classList.remove('saving');
            indicator.classList.add('saved');
            document.getElementById('autosaveIcon').textContent = '✅';
            document.getElementById('autosaveText').textContent = 'Draft tersimpan';
            setTimeout(() => indicator.classList.remove('show'), 2000);
        } catch (e) {}
    }, 1000);
}

<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm(`Ada draft tersimpan dari ${new Date(data.saved_at).toLocaleString('id-ID')}. Muat draft tersebut?`)) {
                document.getElementById('nama').value = data.nama || '';
                document.getElementById('nip').value = data.nip || '';
                document.getElementById('jabatan_fungsional').value = data.jabatan_fungsional || 'Tenaga Pengajar';
                document.getElementById('pendidikan_terakhir').value = data.pendidikan_terakhir || '';
                document.getElementById('program_studi_id').value = data.program_studi_id || '';
                document.getElementById('bio').value = data.bio || '';
                document.getElementById('status').value = data.status || 'Aktif';
                updatePreview();
                document.getElementById('bioCounter').textContent = (data.bio || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

['nama', 'nip', 'jabatan_fungsional', 'pendidikan_terakhir', 'program_studi_id', 'bio', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); });
});

document.getElementById('dosenForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        alert('Nama dosen wajib diisi!');
        return;
    }
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('dosenForm').submit();
    }
});

updatePreview();

console.log('%c👨‍🏫 Form Dosen FKIP UNIMOF', 'color: #10b981; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>