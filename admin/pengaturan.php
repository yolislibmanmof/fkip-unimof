<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

// ===== PROSES UPDATE PENGATURAN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors = [];
    
    // 1. Update Text Settings
    $settings_to_update = [
        'nama_fakultas' => trim($_POST['nama_fakultas']),
        'nama_universitas' => trim($_POST['nama_universitas']),
        'singkatan' => trim($_POST['singkatan']),
        'alamat' => trim($_POST['alamat']),
        'telepon' => trim($_POST['telepon']),
        'email' => trim($_POST['email']),
        'website' => trim($_POST['website'])
    ];

    foreach ($settings_to_update as $key => $value) {
        $pdo->prepare("UPDATE pengaturan SET nilai = ?, updated_at = NOW() WHERE nama_key = ?")
            ->execute([$value, $key]);
    }

    // 2. Handle Logo Upload
    if (!empty($_FILES['logo']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['logo']['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/svg+xml'];
        
        if (in_array($mime, $allowed_types) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/../assets/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            // Hapus logo lama
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $old_ext) {
                if (file_exists($upload_dir . 'logo.' . $old_ext)) {
                    unlink($upload_dir . 'logo.' . $old_ext);
                }
            }
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                flash_message('success', '✅ Logo berhasil diperbarui.');
            } else {
                $errors[] = '❌ Gagal memindahkan file logo.';
            }
        } else {
            $errors[] = '❌ Format atau ukuran logo tidak valid. Maksimal 2MB (JPG, PNG, SVG).';
        }
    }

    // 3. Handle Favicon Upload
    if (!empty($_FILES['favicon']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['favicon']['tmp_name']);
        $allowed_types = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        
        if (in_array($mime, $allowed_types) && $_FILES['favicon']['size'] <= 1 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/../assets/images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            $new_filename = 'favicon.' . ($ext === 'ico' ? 'ico' : 'png');
            $upload_path = $upload_dir . $new_filename;
            
            // Hapus favicon lama
            foreach (['ico', 'png', 'svg'] as $old_ext) {
                if (file_exists($upload_dir . 'favicon.' . $old_ext)) {
                    unlink($upload_dir . 'favicon.' . $old_ext);
                }
            }
            
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_path)) {
                flash_message('success', '✅ Favicon berhasil diperbarui.');
            } else {
                $errors[] = '❌ Gagal memindahkan file favicon.';
            }
        } else {
            $errors[] = '❌ Format atau ukuran favicon tidak valid. Maksimal 1MB (ICO, PNG, SVG).';
        }
    }

    if (empty($errors)) {
        flash_message('success', '✅ Semua pengaturan berhasil disimpan.');
    } else {
        foreach ($errors as $err) {
            flash_message('error', $err);
        }
    }
    
    header('Location: pengaturan.php');
    exit;
}

// ===== AMBIL DATA PENGATURAN =====
$settings = [];
$stmt = $pdo->query("SELECT * FROM pengaturan");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['nama_key']] = $row['nilai'];
}

// Cek file yang ada
$current_logo = '';
foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
    if (file_exists(__DIR__ . '/../assets/images/logo.' . $ext)) {
        $current_logo = 'logo.' . $ext;
        break;
    }
}

$current_favicon = '';
foreach (['ico', 'png', 'svg'] as $ext) {
    if (file_exists(__DIR__ . '/../assets/images/favicon.' . $ext)) {
        $current_favicon = 'favicon.' . $ext;
        break;
    }
}

$csrf = generate_csrf_token();
$active_menu = 'pengaturan';
$page_heading = 'Pengaturan Sistem';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Pengaturan', null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== LAYOUT EXTREME ===== */
.settings-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 2rem; align-items: start; }
.settings-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
.settings-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0a6847, #16a34a); }
.settings-card h3 { font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); }

/* ===== FORM ELEMENTS ===== */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.form-grid .full-width { grid-column: 1 / -1; }
.form-group { margin-bottom: 0; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
.form-label .required { color: #ef4444; margin-left: 0.25rem; }
.form-input, .form-textarea { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-textarea { resize: vertical; min-height: 100px; }
.form-input:focus, .form-textarea:focus { outline: none; border-color: #0a6847; box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: flex; align-items: center; gap: 0.25rem; }

/* ===== UPLOAD ZONE EXTREME ===== */
.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-secondary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: #0a6847; background: rgba(10,104,71,0.03); transform: translateY(-2px); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-icon { font-size: 3rem; margin-bottom: 0.75rem; opacity: 0.6; transition: all 0.3s; }
.upload-zone:hover .upload-icon { opacity: 1; transform: scale(1.1); }
.upload-text { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-sub { font-size: 0.8rem; color: var(--text-muted); }

/* ===== PREVIEW BOX ===== */
.preview-box { margin-top: 1.5rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); display: flex; align-items: center; gap: 1rem; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.preview-img-container { width: 80px; height: 80px; border-radius: 12px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); flex-shrink: 0; }
.preview-img-container img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; }
.preview-info { flex: 1; text-align: left; }
.preview-info h4 { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; word-break: break-all; }
.preview-info small { font-size: 0.75rem; color: var(--text-muted); }
.btn-remove { background: #fee2e2; color: #dc2626; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.btn-remove:hover { background: #dc2626; color: white; }

/* ===== CURRENT FILE BADGE ===== */
.current-file-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: rgba(10,104,71,0.1); border: 1px solid rgba(10,104,71,0.2); border-radius: 999px; font-size: 0.8rem; font-weight: 600; color: #0a6847; margin-top: 1rem; }

/* ===== SAVE BUTTON ===== */
.btn-save-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #0a6847, #16a34a); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
.btn-save-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }
.btn-save-extreme:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-save-extreme .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
.btn-save-extreme.loading .spinner { display: inline-block; }
.btn-save-extreme.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) { 
    .settings-layout { grid-template-columns: 1fr; } 
    .form-grid { grid-template-columns: 1fr; }
}
</style>

<form method="POST" enctype="multipart/form-data" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
    
    <div class="settings-layout" data-aos="fade-up">
        <!-- LEFT: General Settings -->
        <div class="settings-card">
            <h3>⚙️ Pengaturan Umum</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Fakultas <span class="required">*</span></label>
                    <input type="text" name="nama_fakultas" class="form-input" required value="<?= sanitize($settings['nama_fakultas'] ?? '') ?>" placeholder="Contoh: Fakultas Keguruan dan Ilmu Pendidikan">
                </div>
                <div class="form-group">
                    <label class="form-label">Singkatan <span class="required">*</span></label>
                    <input type="text" name="singkatan" class="form-input" required value="<?= sanitize($settings['singkatan'] ?? '') ?>" placeholder="Contoh: FKIP UNIMOF">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Nama Universitas <span class="required">*</span></label>
                    <input type="text" name="nama_universitas" class="form-input" required value="<?= sanitize($settings['nama_universitas'] ?? '') ?>" placeholder="Contoh: Universitas Muhammadiyah Maumere">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="alamat" class="form-textarea" rows="3" placeholder="Alamat lengkap kampus..."><?= sanitize($settings['alamat'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Telepon</label>
                    <input type="text" name="telepon" class="form-input" value="<?= sanitize($settings['telepon'] ?? '') ?>" placeholder="(0382) 21234">
                    <div class="form-hint">💡 Format: (Kode Area) Nomor</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Resmi</label>
                    <input type="email" name="email" class="form-input" value="<?= sanitize($settings['email'] ?? '') ?>" placeholder="fkip@unimof.ac.id">
                </div>
                <div class="form-group full-width">
                    <label class="form-label">Website</label>
                    <input type="url" name="website" class="form-input" value="<?= sanitize($settings['website'] ?? '') ?>" placeholder="https://fkip.unimof.ac.id">
                </div>
            </div>
        </div>

        <!-- RIGHT: Branding (Logo & Favicon) -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Logo Upload -->
            <div class="settings-card">
                <h3>🎨 Logo Institusi</h3>
                <div class="upload-zone" id="logoZone">
                    <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png,image/svg+xml">
                    <div class="upload-icon">📷</div>
                    <div class="upload-text">Drag & drop logo di sini</div>
                    <div class="upload-sub">atau klik untuk memilih (Maks 2MB, PNG/SVG terbaik)</div>
                </div>
                
                <?php if ($current_logo): ?>
                    <div class="current-file-badge">
                        <span>✅ Logo aktif:</span>
                        <span><?= strtoupper(pathinfo($current_logo, PATHINFO_EXTENSION)) ?></span>
                    </div>
                <?php endif; ?>

                <div class="preview-box" id="logoPreview" style="display: none;">
                    <div class="preview-img-container">
                        <img id="logoPreviewImg" src="" alt="Preview Logo">
                    </div>
                    <div class="preview-info">
                        <h4 id="logoFileName">logo.png</h4>
                        <small id="logoFileSize">0 KB</small>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeFile('logo')" title="Hapus pilihan">✕</button>
                </div>
            </div>

            <!-- Favicon Upload -->
            <div class="settings-card">
                <h3>🔖 Favicon</h3>
                <div class="upload-zone" id="faviconZone">
                    <input type="file" id="faviconInput" name="favicon" accept="image/x-icon,image/png,image/svg+xml">
                    <div class="upload-icon">🔖</div>
                    <div class="upload-text">Drag & drop favicon di sini</div>
                    <div class="upload-sub">atau klik untuk memilih (Maks 1MB, ICO/PNG)</div>
                </div>

                <?php if ($current_favicon): ?>
                    <div class="current-file-badge">
                        <span>✅ Favicon aktif:</span>
                        <span><?= strtoupper(pathinfo($current_favicon, PATHINFO_EXTENSION)) ?></span>
                    </div>
                <?php endif; ?>

                <div class="preview-box" id="faviconPreview" style="display: none;">
                    <div class="preview-img-container">
                        <img id="faviconPreviewImg" src="" alt="Preview Favicon">
                    </div>
                    <div class="preview-info">
                        <h4 id="faviconFileName">favicon.ico</h4>
                        <small id="faviconFileSize">0 KB</small>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeFile('favicon')" title="Hapus pilihan">✕</button>
                </div>
            </div>

            <!-- Save Button -->
            <button type="submit" class="btn-save-extreme" id="saveBtn">
                <span class="btn-text">💾 Simpan Semua Pengaturan</span>
                <span class="spinner"></span>
            </button>
        </div>
    </div>
</form>

<script>
// ===== DRAG & DROP + PREVIEW LOGIC =====
function setupUploadZone(zoneId, inputId, previewId, imgId, nameId, sizeId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const img = document.getElementById(imgId);
    const fileName = document.getElementById(nameId);
    const fileSize = document.getElementById(sizeId);

    ['dragenter', 'dragover'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('dragover'); });
    });
    
    zone.addEventListener('drop', e => {
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handleFile(input.files[0], preview, img, fileName, fileSize);
        }
    });
    
    input.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0], preview, img, fileName, fileSize);
        }
    });
}

function handleFile(file, preview, img, fileName, fileSize) {
    if (!file.type.startsWith('image/')) {
        alert('Hanya file gambar yang diizinkan!');
        return;
    }
    
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    
    const reader = new FileReader();
    reader.onload = e => {
        img.src = e.target.result;
        preview.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removeFile(type) {
    const input = document.getElementById(type + 'Input');
    const preview = document.getElementById(type + 'Preview');
    input.value = '';
    preview.style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Initialize zones
setupUploadZone('logoZone', 'logoInput', 'logoPreview', 'logoPreviewImg', 'logoFileName', 'logoFileSize');
setupUploadZone('faviconZone', 'faviconInput', 'faviconPreview', 'faviconPreviewImg', 'faviconFileName', 'faviconFileSize');

// ===== FORM SUBMISSION WITH LOADING STATE =====
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('saveBtn');
    btn.classList.add('loading');
    btn.disabled = true;
    
    // Optional: Prevent double submission visually
    setTimeout(() => {
        // Form will submit naturally, but this ensures UI feedback
    }, 100);
});

// ===== AUTO-HIGHLIGHT ON CHANGE =====
document.querySelectorAll('.form-input, .form-textarea').forEach(field => {
    field.addEventListener('input', function() {
        this.style.borderColor = '#0a6847';
        setTimeout(() => {
            if (document.activeElement !== this) {
                this.style.borderColor = '';
            }
        }, 1000);
    });
    field.addEventListener('blur', function() {
        this.style.borderColor = '';
    });
});

console.log('%c⚙️ Pengaturan FKIP UNIMOF', 'color: #0a6847; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>