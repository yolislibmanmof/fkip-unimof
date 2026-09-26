<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

// ✅ PERBAIKAN FATAL: Pisahkan prepare, execute, dan fetch
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM fasilitas WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); 
    $kategori = $_POST['kategori']; 
    $deskripsi = trim($_POST['deskripsi']);
    $kapasitas = (int)($_POST['kapasitas'] ?? 0); 
    $status = $_POST['status'];
    $gambar = $edit['gambar'] ?? null;

    if (!empty($_FILES['gambar']['name'])) {
        $up = upload_image($_FILES['gambar'], 'fasilitas', 5 * 1024 * 1024); // Max 5MB
        if (!$up['ok']) { 
            flash_message('error', $up['error']); 
            header('Location: fasilitas-form.php' . ($id ? "?id=$id" : '')); 
            exit; 
        }
        if ($up['name']) { 
            delete_upload($gambar, 'fasilitas'); 
            $gambar = $up['name']; 
        }
    }

    if ($edit) {
        $pdo->prepare("UPDATE fasilitas SET nama=?, kategori=?, deskripsi=?, gambar=?, kapasitas=?, status=? WHERE id=?")
            ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status, $id]);
        flash_message('success', '✅ Data fasilitas berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO fasilitas (nama, kategori, deskripsi, gambar, kapasitas, status) VALUES (?,?,?,?,?,?)")
            ->execute([$nama, $kategori, $deskripsi, $gambar, $kapasitas, $status]);
        flash_message('success', '✅ Fasilitas baru berhasil ditambahkan.');
    }
    header('Location: fasilitas.php'); 
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'fasilitas'; 
$page_heading = $edit ? 'Edit Fasilitas' : 'Tambah Fasilitas';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Fasilitas', 'fasilitas.php'], [$page_heading, null]];

// ✅ PATH BENAR: Tanpa ../
require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM EXTREME LAYOUT ===== */
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #8b5cf6, #6366f1, #4f46e5); }
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
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 4px rgba(139,92,246,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

/* ===== UPLOAD ZONE ===== */
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #8b5cf6; background: rgba(139,92,246,0.03); }
.upload-zone-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.upload-zone-text { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-zone-sub { font-size: 0.85rem; color: var(--text-muted); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.image-preview { display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); align-items: center; gap: 1rem; }
.image-preview.show { display: flex; }
.image-preview-img { width: 100px; height: 100px; border-radius: 12px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); }
.image-preview-img img { width: 100%; height: 100%; object-fit: cover; }
.image-preview-info { flex: 1; min-width: 0; }
.image-preview-info h4 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.image-preview-info small { font-size: 0.78rem; color: var(--text-muted); }
.image-preview-remove { background: #fee2e2; color: #dc2626; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
.image-preview-remove:hover { background: #dc2626; color: white; }

/* ===== PREVIEW CARD ===== */
.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-header p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }

.preview-image-box { width: 100%; height: 180px; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 5rem; margin-bottom: 1.5rem; overflow: hidden; border: 1px solid var(--border); position: relative; }
.preview-image-box img { width: 100%; height: 100%; object-fit: cover; }
.preview-badge { position: absolute; top: 1rem; left: 1rem; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #4f46e5; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 60px; border-left: 3px solid #8b5cf6; }
.preview-empty { color: var(--text-muted); font-style: italic; }

/* ===== SUBMIT & AUTOSAVE ===== */
.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(139,92,246,0.35); }
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
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Fasilitas</h2>
            <p><?= $edit ? 'Perbarui detail fasilitas di bawah ini.' : 'Isi detail fasilitas untuk ditampilkan di website publik.' ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="fasilitasForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">🏢 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Fasilitas <span class="required">*</span></label>
                        <input type="text" id="nama" name="nama" class="form-input" required placeholder="Contoh: Laboratorium Komputer 1" value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori <span class="required">*</span></label>
                        <select id="kategori" name="kategori" class="form-select" required>
                            <?php foreach(['Laboratorium', 'Ruang Kelas', 'Perpustakaan', 'Fasilitas Umum', 'Lainnya'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($edit['kategori'] ?? 'Lainnya') === $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kapasitas (Orang)</label>
                        <input type="number" id="kapasitas" name="kapasitas" class="form-input" min="0" placeholder="Contoh: 30" value="<?= $edit['kapasitas'] ?? 0 ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Upload Foto -->
            <div class="form-section">
                <div class="form-section-title">📷 Foto Fasilitas</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="gambar" name="gambar" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file (Maks 5MB)</div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span>️</span></div>
                    <div class="image-preview-info">
                        <h4 id="imageName">-</h4>
                        <small id="imageSize">-</small>
                    </div>
                    <button type="button" class="image-preview-remove" onclick="removeImage()">✕</button>
                </div>
                <?php if (!empty($edit['gambar'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; display: flex; align-items: center; gap: 1rem;">
                        <img src="<?= asset('uploads/fasilitas/' . basename($edit['gambar'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div>
                            <strong>Foto saat ini:</strong> <?= sanitize($edit['gambar']) ?>
                            <br><small style="color: var(--text-muted);">Upload foto baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 3: Deskripsi -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi Lengkap</div>
                <div class="form-group">
                    <label class="form-label">Deskripsi Fasilitas</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="5" placeholder="Jelaskan fasilitas, peralatan, dan kegunaan..."><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan muncul di halaman publik</span>
                        <span><span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span> karakter</span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Fasilitas' ?></span>
                <span class="spinner"></span>
            </button>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                💡 Tip: Tekan <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+S</kbd> untuk menyimpan cepat
            </p>
        </form>
    </div>

    <!-- RIGHT: LIVE PREVIEW -->
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <h3>👁️ Live Preview</h3>
            <p>Pratinjau tampilan di website publik</p>
        </div>

        <div id="previewContent">
            <div class="preview-image-box" id="previewImageBox">
                <span id="previewIcon">🏢</span>
                <span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>
            </div>
            <h2 class="preview-title" id="previewTitle">Nama fasilitas akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>👥</span>
                    <strong id="previewKapasitas">0 orang</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📊</span>
                    <strong id="previewStatus">✅ Aktif</strong>
                </div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Deskripsi:</div>
            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi fasilitas akan muncul di sini...</span>
            </div>
        </div>
    </div>
</div>

<!-- Autosave Indicator -->
<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DRAG & DROP IMAGE =====
const uploadZone = document.getElementById('uploadZone');
const imageInput = document.getElementById('gambar');
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
imageInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleImage(e.target.files[0]);
});

function handleImage(file) {
    if (!file.type.startsWith('image/')) { alert('Hanya file gambar yang diizinkan!'); return; }
    if (file.size > 5 * 1024 * 1024) { alert('Ukuran file maksimal 5MB!'); return; }
    
    const dt = new DataTransfer();
    dt.items.add(file);
    imageInput.files = dt.files;
    
    document.getElementById('imageName').textContent = file.name;
    document.getElementById('imageSize').textContent = formatFileSize(file.size);
    
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imagePreviewImg').innerHTML = `<img src="${e.target.result}" alt="">`;
        document.getElementById('previewImageBox').innerHTML = `<img src="${e.target.result}" alt=""><span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>`;
    };
    reader.readAsDataURL(file);
    
    imagePreview.classList.add('show');
    uploadZone.style.display = 'none';
    
    updatePreview();
    triggerAutosave();
}

function removeImage() {
    imageInput.value = '';
    imagePreview.classList.remove('show');
    uploadZone.style.display = 'block';
    
    // Reset preview box to icon
    const icon = getIconForCategory(document.getElementById('kategori').value);
    document.getElementById('previewImageBox').innerHTML = `<span id="previewIcon">${icon}</span><span class="preview-badge" id="previewBadge">🏢 LAINNYA</span>`;
    
    updatePreview();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ===== LIVE PREVIEW =====
function getIconForCategory(kat) {
    const icons = { 'Laboratorium': '🧪', 'Ruang Kelas': '🏫', 'Perpustakaan': '', 'Fasilitas Umum': '🏢', 'Lainnya': '📌' };
    return icons[kat] || '';
}

function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama fasilitas akan muncul di sini...';
    const kategori = document.getElementById('kategori').value || 'Lainnya';
    const kapasitas = document.getElementById('kapasitas').value || 0;
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.getElementById('status').value || 'Aktif';
    const icon = getIconForCategory(kategori);

    document.getElementById('previewTitle').textContent = nama;
    
    const badge = document.getElementById('previewBadge');
    if (badge) badge.textContent = `${icon} ${kategori.toUpperCase()}`;
    
    document.getElementById('previewIcon').textContent = icon;
    document.getElementById('previewKapasitas').textContent = `${kapasitas} orang`;
    document.getElementById('previewStatus').textContent = (status === 'Aktif' ? '✅' : '❌') + ' ' + status;
    
    if (deskripsi) {
        document.getElementById('previewDesc').textContent = deskripsi;
        document.getElementById('previewDesc').classList.remove('preview-empty');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi fasilitas akan muncul di sini...</span>';
    }
}

// ===== CHARACTER COUNTER =====
document.getElementById('deskripsi').addEventListener('input', function() {
    document.getElementById('deskripsiCounter').textContent = this.value.length;
    triggerAutosave();
});

// ===== AUTOSAVE =====
const storageKey = 'fkip_fasilitas_draft_<?= $id ?: "new" ?>';
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
            kategori: document.getElementById('kategori').value,
            kapasitas: document.getElementById('kapasitas').value,
            deskripsi: document.getElementById('deskripsi').value,
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

// Load autosaved draft
<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm(`Ada draft tersimpan dari ${new Date(data.saved_at).toLocaleString('id-ID')}. Muat draft tersebut?`)) {
                document.getElementById('nama').value = data.nama || '';
                document.getElementById('kategori').value = data.kategori || 'Lainnya';
                document.getElementById('kapasitas').value = data.kapasitas || 0;
                document.getElementById('deskripsi').value = data.deskripsi || '';
                document.getElementById('status').value = data.status || 'Aktif';
                
                updatePreview();
                document.getElementById('deskripsiCounter').textContent = (data.deskripsi || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama', 'kategori', 'kapasitas', 'deskripsi', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); });
});

// Form submit with loading
document.getElementById('fasilitasForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        alert('Nama Fasilitas wajib diisi!');
        return;
    }
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

// Keyboard shortcut: Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('fasilitasForm').submit();
    }
});

// Initial preview
updatePreview();

console.log('%c🏢 Form Fasilitas FKIP UNIMOF', 'color: #8b5cf6; font-size: 16px; font-weight: bold;');
console.log('%cShortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>