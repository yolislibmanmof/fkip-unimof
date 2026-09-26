<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM prestasi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Ambil data prodi untuk dropdown
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul']);
    $mahasiswa = trim($_POST['mahasiswa']);
    $lomba = trim($_POST['lomba']);
    $juara = $_POST['juara'];
    $tingkat = $_POST['tingkat'];
    $tahun = (int)$_POST['tahun'];
    $program_studi_id = (int)($_POST['program_studi_id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi']);
    $foto = $edit['foto'] ?? null;

    if (!empty($_FILES['foto']['name'])) {
        $up = upload_image($_FILES['foto'], 'prestasi', 3 * 1024 * 1024); // Max 3MB
        if (!$up['ok']) {
            flash_message('error', $up['error']);
            header('Location: prestasi-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($up['name']) {
            delete_upload($foto, 'prestasi');
            $foto = $up['name'];
        }
    }

    if ($edit) {
        $pdo->prepare("UPDATE prestasi SET judul=?, mahasiswa=?, lomba=?, juara=?, tingkat=?, tahun=?, program_studi_id=?, deskripsi=?, foto=? WHERE id=?")
            ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto, $id]);
        flash_message('success', '✅ Data prestasi berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO prestasi (judul, mahasiswa, lomba, juara, tingkat, tahun, program_studi_id, deskripsi, foto) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$judul, $mahasiswa, $lomba, $juara, $tingkat, $tahun, $program_studi_id, $deskripsi, $foto]);
        flash_message('success', '✅ Prestasi baru berhasil ditambahkan.');
    }
    header('Location: prestasi.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'prestasi';
$page_heading = $edit ? 'Edit Prestasi' : 'Tambah Prestasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Prestasi', 'prestasi.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #f59e0b, #d97706); }
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
.form-textarea { resize: vertical; min-height: 100px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #f59e0b; background: rgba(245,158,11,0.03); }
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

.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; text-align: center; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center; }
.preview-trophy { font-size: 5rem; margin-bottom: 1rem; animation: bounce 2s infinite; }
.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-mahasiswa { font-size: 1rem; color: var(--text-primary); font-weight: 600; margin-bottom: 0.25rem; }
.preview-lomba { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
.preview-meta { display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.preview-meta-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); text-align: left; border-left: 3px solid #f59e0b; min-height: 60px; }
.preview-empty { color: var(--text-muted); font-style: italic; }

.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(245,158,11,0.35); }
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
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Prestasi</h2>
            <p>Isi detail pencapaian prestasi mahasiswa atau dosen.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="prestasiForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">🏆 Detail Prestasi</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Judul Prestasi <span class="required">*</span></label>
                    <input type="text" id="judul" name="judul" class="form-input" required placeholder="Contoh: Juara 1 Olimpiade Matematika" value="<?= sanitize($edit['judul'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Mahasiswa <span class="required">*</span></label>
                        <input type="text" id="mahasiswa" name="mahasiswa" class="form-input" required placeholder="Contoh: Ahmad Fauzi" value="<?= sanitize($edit['mahasiswa'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Lomba <span class="required">*</span></label>
                        <input type="text" id="lomba" name="lomba" class="form-input" required placeholder="Contoh: OSN 2024" value="<?= sanitize($edit['lomba'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Perolehan Juara <span class="required">*</span></label>
                        <select id="juara" name="juara" class="form-select" required>
                            <?php foreach(['Juara 1', 'Juara 2', 'Juara 3', 'Harapan 1', 'Harapan 2', 'Harapan 3', 'Peserta'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['juara'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tingkat <span class="required">*</span></label>
                        <select id="tingkat" name="tingkat" class="form-select" required>
                            <?php foreach(['Internasional', 'Nasional', 'Provinsi', 'Kabupaten', 'Sekolah'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($edit['tingkat'] ?? '') === $t ? 'selected' : '' ?>><?= $t === 'Internasional' ? '🌍' : ($t === 'Nasional' ? '🇮🇩' : '🏛️') ?> <?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tahun <span class="required">*</span></label>
                        <input type="number" id="tahun" name="tahun" class="form-input" required min="1900" max="2100" value="<?= $edit['tahun'] ?? date('Y') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program Studi</label>
                        <select id="program_studi_id" name="program_studi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['program_studi_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi</div>
                <div class="form-group">
                    <label class="form-label">Deskripsi Prestasi</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="4" placeholder="Ceritakan sedikit tentang pencapaian ini..."><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan tampil di halaman publik</span>
                        <span><span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span> karakter</span>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📷 Foto Prestasi (Opsional)</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file (Maks 3MB)</div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span>🖼️</span></div>
                    <div class="image-preview-info">
                        <h4 id="imageName">-</h4>
                        <small id="imageSize">-</small>
                    </div>
                    <button type="button" class="image-preview-remove" onclick="removeImage()">✕</button>
                </div>
                <?php if (!empty($edit['foto'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; display: flex; align-items: center; gap: 1rem;">
                        <img src="<?= asset('uploads/prestasi/' . basename($edit['foto'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div>
                            <strong>Foto saat ini:</strong> <?= sanitize($edit['foto']) ?>
                            <br><small style="color: var(--text-muted);">Upload foto baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Prestasi' ?></span>
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
        </div>

        <div id="previewContent">
            <div class="preview-trophy">🏆</div>
            <div class="preview-badge" id="previewBadge">🥇 NASIONAL</div>
            <h2 class="preview-title" id="previewTitle">Judul prestasi akan muncul di sini...</h2>
            <div class="preview-mahasiswa" id="previewMahasiswa">Oleh: -</div>
            <div class="preview-lomba" id="previewLomba">Lomba: -</div>
            
            <div class="preview-meta">
                <div class="preview-meta-item"><span>📅</span><strong id="previewTahun">-</strong></div>
                <div class="preview-meta-item"><span>🏫</span><strong id="previewProdi">-</strong></div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; text-align: left;">Deskripsi:</div>
            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi akan muncul di sini...</span>
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
    if (e.dataTransfer.files.length > 0) handleImage(e.dataTransfer.files[0]);
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
    const judul = document.getElementById('judul').value || 'Judul prestasi akan muncul di sini...';
    const mahasiswa = document.getElementById('mahasiswa').value || '-';
    const lomba = document.getElementById('lomba').value || '-';
    const tahun = document.getElementById('tahun').value || '-';
    const tingkat = document.getElementById('tingkat').value || 'Nasional';
    const juara = document.getElementById('juara').value || 'Juara 1';
    const deskripsi = document.getElementById('deskripsi').value;
    
    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect.options[prodiSelect.selectedIndex]?.text || '-';

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewMahasiswa').textContent = 'Oleh: ' + mahasiswa;
    document.getElementById('previewLomba').textContent = 'Lomba: ' + lomba;
    document.getElementById('previewTahun').textContent = tahun;
    document.getElementById('previewProdi').textContent = prodiText !== '-- Pilih Prodi --' ? prodiText : '-';
    
    document.getElementById('previewBadge').textContent = '🏆 ' + tingkat.toUpperCase() + ' - ' + juara.toUpperCase();
    
    if (deskripsi) {
        document.getElementById('previewDesc').textContent = deskripsi;
        document.getElementById('previewDesc').classList.remove('preview-empty');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi akan muncul di sini...</span>';
    }
}

document.getElementById('deskripsi').addEventListener('input', function() {
    document.getElementById('deskripsiCounter').textContent = this.value.length;
    triggerAutosave();
});

const storageKey = 'fkip_prestasi_draft_<?= $id ?: "new" ?>';
let autosaveTimer;

function triggerAutosave() {
    clearTimeout(autosaveTimer);
    const indicator = document.getElementById('autosaveIndicator');
    indicator.classList.add('show', 'saving');
    indicator.classList.remove('saved');
    document.getElementById('autosaveIcon').textContent = '⏳';
    document.getElementById('autosaveText').textContent = 'Menyimpan draft...';

    autosaveTimer = setTimeout(() => {
        const data = {
            judul: document.getElementById('judul').value,
            mahasiswa: document.getElementById('mahasiswa').value,
            lomba: document.getElementById('lomba').value,
            juara: document.getElementById('juara').value,
            tingkat: document.getElementById('tingkat').value,
            tahun: document.getElementById('tahun').value,
            program_studi_id: document.getElementById('program_studi_id').value,
            deskripsi: document.getElementById('deskripsi').value,
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
                document.getElementById('judul').value = data.judul || '';
                document.getElementById('mahasiswa').value = data.mahasiswa || '';
                document.getElementById('lomba').value = data.lomba || '';
                document.getElementById('juara').value = data.juara || 'Juara 1';
                document.getElementById('tingkat').value = data.tingkat || 'Nasional';
                document.getElementById('tahun').value = data.tahun || new Date().getFullYear();
                document.getElementById('program_studi_id').value = data.program_studi_id || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                updatePreview();
                document.getElementById('deskripsiCounter').textContent = (data.deskripsi || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

['judul', 'mahasiswa', 'lomba', 'juara', 'tingkat', 'tahun', 'program_studi_id', 'deskripsi'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); });
});

document.getElementById('prestasiForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    if (!judul) {
        e.preventDefault();
        document.getElementById('judul').classList.add('error');
        alert('Judul prestasi wajib diisi!');
        return;
    }
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('prestasiForm').submit();
    }
});

updatePreview();

console.log('%c🏆 Form Prestasi FKIP UNIMOF', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>