<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM downloads WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash_message('error', 'File tidak ditemukan.');
        header('Location: download.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Lainnya';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $file_name = $edit['file_name'] ?? null;
    $file_size = $edit['file_size'] ?? null;

    if ($judul === '') {
        flash_message('error', 'Judul dokumen wajib diisi.');
        header('Location: download-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    if (!empty($_FILES['file']['name'])) {
        $allowed = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['file']['tmp_name']);
        
        if (!in_array($mime, $allowed)) {
            flash_message('error', 'Format file tidak diizinkan. Hanya PDF, DOC, DOCX, XLS, XLSX, ZIP, RAR, 7Z.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            flash_message('error', 'Ukuran file maksimal 10MB.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
        $file_name = bin2hex(random_bytes(16)) . ($safe_ext ? '.' . $safe_ext : '');
        $file_size = number_format($_FILES['file']['size'] / 1024, 1) . ' KB';
        
        $dir = APP_DIR . '/assets/downloads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $file_name)) {
            flash_message('error', 'Gagal mengupload file.');
            header('Location: download-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        
        if ($edit && $edit['file_name']) {
            @unlink($dir . '/' . $edit['file_name']);
        }
    }

    if ($edit) {
        $pdo->prepare("UPDATE downloads SET judul=?, kategori=?, deskripsi=?, file_name=?, file_size=? WHERE id=?")
            ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size, $id]);
        flash_message('success', '✅ File berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO downloads (judul, kategori, deskripsi, file_name, file_size) VALUES (?,?,?,?,?)")
            ->execute([$judul, $kategori, $deskripsi, $file_name, $file_size]);
        flash_message('success', '✅ File berhasil diupload.');
    }
    header('Location: download.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'download';
$page_heading = $edit ? 'Edit File' : 'Upload File';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Download Center', 'download.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.upload-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: start; }

/* Form Card */
.form-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-premium::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899); }
.form-header-premium { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-premium h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.form-header-premium p { color: var(--text-muted); font-size: 0.9rem; }

/* Drag & Drop Zone */
.drop-zone {
    border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2.5rem 1.5rem;
    text-align: center; background: var(--bg-secondary); transition: all 0.3s; cursor: pointer;
    position: relative; overflow: hidden;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: var(--primary); background: rgba(10,104,71,0.03);
}
.drop-zone.dragover { transform: scale(1.02); }
.drop-zone-icon { font-size: 3.5rem; margin-bottom: 1rem; animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.drop-zone-text { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; }
.drop-zone-sub { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; }
.drop-zone-hint { font-size: 0.75rem; color: var(--text-muted); background: var(--bg-primary); padding: 0.5rem 1rem; border-radius: 999px; display: inline-block; }
.drop-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

/* File Preview */
.file-preview { display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); }
.file-preview.show { display: flex; align-items: center; gap: 1rem; }
.file-preview-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.file-preview-info { flex: 1; min-width: 0; }
.file-preview-info h4 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-preview-info small { font-size: 0.78rem; color: var(--text-muted); }
.file-preview-remove { background: #fee2e2; color: #dc2626; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
.file-preview-remove:hover { background: #dc2626; color: white; }

/* Category Visual Selector */
.cat-selector { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.cat-option { position: relative; }
.cat-option input { position: absolute; opacity: 0; pointer-events: none; }
.cat-option label {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 1rem 0.5rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; text-align: center;
}
.cat-option label:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.cat-option input:checked + label { border-color: var(--primary); background: rgba(10,104,71,0.05); box-shadow: 0 4px 12px rgba(10,104,71,0.15); }
.cat-icon { font-size: 1.5rem; }
.cat-name { font-size: 0.78rem; font-weight: 700; color: var(--text-primary); }

/* Templates */
.template-section { margin-bottom: 1.5rem; }
.template-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
.template-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.template-btn { padding: 0.5rem 0.85rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 999px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; color: var(--text-secondary); }
.template-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

/* Floating Label Form */
.form-group-premium { margin-bottom: 1.5rem; position: relative; }
.input-wrapper-premium { position: relative; }
.form-input-premium {
    width: 100%; padding: 1rem 1rem 1rem 3rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem;
    background: var(--bg-secondary); transition: all 0.3s; color: var(--text-primary);
}
.form-input-premium:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-input-premium::placeholder { color: transparent; }
.input-icon-premium { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; pointer-events: none; transition: color 0.3s; }
.form-input-premium:focus ~ .input-icon-premium { color: var(--primary); }
.floating-label-premium {
    position: absolute; left: 3rem; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 0.95rem; pointer-events: none;
    transition: all 0.25s; background: var(--bg-secondary); padding: 0 0.35rem;
}
.form-input-premium:focus ~ .floating-label-premium,
.form-input-premium:not(:placeholder-shown) ~ .floating-label-premium {
    top: 0; font-size: 0.75rem; color: var(--primary); font-weight: 600;
}

/* Textarea with counter */
.textarea-wrapper { position: relative; }
.form-textarea-premium {
    width: 100%; padding: 1rem; padding-bottom: 2.5rem; border: 2px solid var(--border);
    border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem;
    background: var(--bg-secondary); transition: all 0.3s; min-height: 120px; resize: vertical;
}
.form-textarea-premium:focus { outline: none; border-color: var(--primary); background: var(--bg-primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.char-counter-premium { position: absolute; bottom: 0.75rem; right: 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; background: var(--bg-primary); padding: 0.2rem 0.5rem; border-radius: 999px; }
.char-counter-premium.warn { color: #f59e0b; }
.char-counter-premium.danger { color: #ef4444; }

/* Preview Card */
.preview-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; min-height: 2.5rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 80px; border-left: 3px solid var(--primary); }
.preview-empty { color: var(--text-muted); font-style: italic; }

/* Submit Button */
.submit-btn-premium {
    width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white; border: none; border-radius: var(--radius-md); font-family: inherit;
    font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem; position: relative; overflow: hidden;
}
.submit-btn-premium:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }
.submit-btn-premium:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-premium .spinner { width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
.submit-btn-premium.loading .spinner { display: inline-block; }
.submit-btn-premium.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Autosave indicator */
.autosave-indicator { position: fixed; bottom: 2rem; right: 2rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 999px; padding: 0.75rem 1.25rem; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; font-weight: 600; opacity: 0; transform: translateY(20px); transition: all 0.3s; z-index: 100; }
.autosave-indicator.show { opacity: 1; transform: translateY(0); }
.autosave-indicator.saving { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.autosave-indicator.saved { background: #dcfce7; border-color: #86efac; color: #166534; }

@media (max-width: 968px) {
    .upload-layout { grid-template-columns: 1fr; }
    .preview-card-premium { position: static; order: -1; }
    .cat-selector { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="upload-layout">
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-premium" data-aos="fade-right">
        <div class="form-header-premium">
            <h2><?= $edit ? '️ Edit File' : '📤 Upload File Baru' ?></h2>
            <p><?= $edit ? 'Perbarui detail dokumen di bawah ini.' : 'Upload dokumen untuk dibagikan ke pengunjung website.' ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Drop Zone -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">📎 File Dokumen *</label>
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.7z" <?= $edit ? '' : 'required' ?>>
                    <div class="drop-zone-icon">📤</div>
                    <div class="drop-zone-text">Drag & drop file di sini</div>
                    <div class="drop-zone-sub">atau klik untuk memilih file</div>
                    <div class="drop-zone-hint">PDF, DOC, DOCX, XLS, XLSX, ZIP, RAR, 7Z (Maks 10MB)</div>
                </div>
                <div class="file-preview" id="filePreview">
                    <div class="file-preview-icon" id="filePreviewIcon">📄</div>
                    <div class="file-preview-info">
                        <h4 id="filePreviewName">-</h4>
                        <small id="filePreviewSize">-</small>
                    </div>
                    <button type="button" class="file-preview-remove" onclick="removeFile()">✕</button>
                </div>
                <?php if ($edit && $edit['file_name']): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem;">
                        <strong>📄 File saat ini:</strong> <?= sanitize($edit['file_name']) ?> (<?= sanitize($edit['file_size']) ?>)
                        <br><small style="color: var(--text-muted);">Upload file baru untuk mengganti</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Judul -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="judul" name="judul" class="form-input-premium" placeholder=" " required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <span class="input-icon-premium">📝</span>
                    <label class="floating-label-premium" for="judul">Judul Dokumen *</label>
                </div>
            </div>

            <!-- Kategori Visual -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">🏷️ Kategori *</label>
                <div class="cat-selector">
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-akademik" value="Akademik" <?= ($edit['kategori'] ?? '') === 'Akademik' ? 'checked' : '' ?>>
                        <label for="kat-akademik"><span class="cat-icon">🎓</span><span class="cat-name">Akademik</span></label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-pmb" value="PMB" <?= ($edit['kategori'] ?? '') === 'PMB' ? 'checked' : '' ?>>
                        <label for="kat-pmb"><span class="cat-icon">📋</span><span class="cat-name">PMB</span></label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-formulir" value="Formulir" <?= ($edit['kategori'] ?? '') === 'Formulir' ? 'checked' : '' ?>>
                        <label for="kat-formulir"><span class="cat-icon">📄</span><span class="cat-name">Formulir</span></label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-pedoman" value="Pedoman" <?= ($edit['kategori'] ?? '') === 'Pedoman' ? 'checked' : '' ?>>
                        <label for="kat-pedoman"><span class="cat-icon">📚</span><span class="cat-name">Pedoman</span></label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="kategori" id="kat-lainnya" value="Lainnya" <?= ($edit['kategori'] ?? 'Lainnya') === 'Lainnya' ? 'checked' : '' ?>>
                        <label for="kat-lainnya"><span class="cat-icon"></span><span class="cat-name">Lainnya</span></label>
                    </div>
                </div>
            </div>

            <!-- Templates -->
            <div class="template-section">
                <div class="template-label"> Template Cepat (Klik untuk auto-fill)</div>
                <div class="template-buttons">
                    <button type="button" class="template-btn" onclick="applyTemplate('kurikulum')"> Kurikulum Prodi</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('silabus')">📋 Silabus</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('brosur')">📄 Brosur PMB</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('formulir')">📝 Formulir Pendaftaran</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('pedoman')">📖 Pedoman Skripsi</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('jadwal')"> Jadwal Kuliah</button>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="form-group-premium">
                <div class="textarea-wrapper">
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea-premium" placeholder=" " maxlength="1000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <span class="char-counter-premium" id="charCounter">0/1000</span>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="submit-btn-premium" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui File' : '✨ Upload File' ?></span>
                <span class="spinner"></span>
            </button>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                💡 Tip: Tekan <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+S</kbd> untuk menyimpan cepat
            </p>
        </form>
    </div>

    <!-- ===== RIGHT: LIVE PREVIEW ===== -->
    <div class="preview-card-premium" data-aos="fade-left">
        <div class="preview-header">
            <h3>👁️ Live Preview</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Pratinjau tampilan di website</p>
        </div>

        <div id="previewContent">
            <div class="preview-badge" id="previewBadge" style="background: #f3f4f6; color: #4b5563;">📌 LAINNYA</div>
            <h2 class="preview-title" id="previewTitle">Judul dokumen akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>📄</span>
                    <strong id="previewFile">Belum ada file</strong>
                </div>
                <div class="preview-meta-item">
                    <span>💾</span>
                    <strong id="previewSize">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>⬇️</span>
                    <strong id="previewDownloads"><?= $edit ? number_format($edit['downloads_count']) : '0' ?> downloads</strong>
                </div>
            </div>

            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi akan muncul di sini...</span>
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
// ===== TEMPLATES =====
const templates = {
    kurikulum: { judul: 'Kurikulum Program Studi', kategori: 'Akademik', deskripsi: 'Dokumen kurikulum resmi program studi yang berlaku untuk tahun akademik saat ini. Berisi struktur mata kuliah, SKS, dan deskripsi mata kuliah.' },
    silabus: { judul: 'Silabus Mata Kuliah', kategori: 'Akademik', deskripsi: 'Silabus lengkap mata kuliah termasuk RPS, tujuan pembelajaran, metode evaluasi, dan referensi.' },
    brosur: { judul: 'Brosur Penerimaan Mahasiswa Baru', kategori: 'PMB', deskripsi: 'Brosur informasi lengkap pendaftaran mahasiswa baru FKIP UNIMOF termasuk program studi, biaya, dan fasilitas.' },
    formulir: { judul: 'Formulir Pendaftaran', kategori: 'Formulir', deskripsi: 'Formulir pendaftaran resmi yang harus diisi oleh calon mahasiswa baru.' },
    pedoman: { judul: 'Pedoman Penulisan Skripsi', kategori: 'Pedoman', deskripsi: 'Pedoman lengkap penulisan skripsi termasuk format, sistematika, dan contoh.' },
    jadwal: { judul: 'Jadwal Kuliah Semester Ganjil', kategori: 'Akademik', deskripsi: 'Jadwal perkuliahan semester ganjil untuk seluruh program studi.' }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;
    document.getElementById('judul').value = t.judul;
    document.querySelector(`input[name="kategori"][value="${t.kategori}"]`).checked = true;
    document.getElementById('deskripsi').value = t.deskripsi;
    updatePreview();
    updateCharCounter();
    triggerAutosave();
}

// ===== DRAG & DROP =====
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');

['dragenter', 'dragover'].forEach(ev => {
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    dropZone.addEventListener(ev, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
});
dropZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleFile(files[0]);
});
fileInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    const allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'];
    const finfo = new (window.File || Object)();
    
    // Check extension
    const ext = file.name.split('.').pop().toLowerCase();
    const allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z'];
    if (!allowedExts.includes(ext)) {
        alert('Format file tidak diizinkan!');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB!');
        return;
    }
    
    // Update file input (for form submission)
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    
    // Show preview
    const icons = { pdf: '📕', doc: '📘', docx: '📘', xls: '📗', xlsx: '📗', zip: '', rar: '🗜️', '7z': '🗜️' };
    document.getElementById('filePreviewIcon').textContent = icons[ext] || '📄';
    document.getElementById('filePreviewName').textContent = file.name;
    document.getElementById('filePreviewSize').textContent = formatFileSize(file.size);
    filePreview.classList.add('show');
    dropZone.style.display = 'none';
    
    updatePreview();
    triggerAutosave();
}

function removeFile() {
    fileInput.value = '';
    filePreview.classList.remove('show');
    dropZone.style.display = 'block';
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
const catConfig = {
    'Akademik': { icon: '🎓', color: '#10b981', bg: '#dcfce7', label: 'AKADEMIK' },
    'PMB': { icon: '📋', color: '#3b82f6', bg: '#dbeafe', label: 'PMB' },
    'Formulir': { icon: '📄', color: '#f59e0b', bg: '#fef3c7', label: 'FORMULIR' },
    'Pedoman': { icon: '', color: '#8b5cf6', bg: '#ede9fe', label: 'PEDOMAN' },
    'Lainnya': { icon: '📌', color: '#6b7280', bg: '#f3f4f6', label: 'LAINNYA' }
};

function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul dokumen akan muncul di sini...';
    const kategori = document.querySelector('input[name="kategori"]:checked')?.value || 'Lainnya';
    const deskripsi = document.getElementById('deskripsi').value;
    const fileName = fileInput.files[0]?.name || '<?= $edit ? sanitize($edit["file_name"]) : "" ?>';
    const fileSize = fileInput.files[0] ? formatFileSize(fileInput.files[0].size) : '<?= $edit ? sanitize($edit["file_size"]) : "-" ?>';

    const config = catConfig[kategori];
    const badge = document.getElementById('previewBadge');
    badge.textContent = `${config.icon} ${config.label}`;
    badge.style.background = config.bg;
    badge.style.color = config.color;

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewFile').textContent = fileName || 'Belum ada file';
    document.getElementById('previewSize').textContent = fileSize;

    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi akan muncul di sini...</span>';
    }
}

// ===== CHARACTER COUNTER =====
function updateCharCounter() {
    const textarea = document.getElementById('deskripsi');
    const counter = document.getElementById('charCounter');
    const len = textarea.value.length;
    counter.textContent = `${len}/1000`;
    counter.classList.toggle('warn', len > 800 && len <= 950);
    counter.classList.toggle('danger', len > 950);
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_download_draft_<?= $id ?: "new" ?>';
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
            judul: document.getElementById('judul').value,
            kategori: document.querySelector('input[name="kategori"]:checked')?.value,
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

// Load autosaved draft
<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm(`Ada draft tersimpan dari ${new Date(data.saved_at).toLocaleString('id-ID')}. Muat draft tersebut?`)) {
                document.getElementById('judul').value = data.judul || '';
                if (data.kategori) document.querySelector(`input[name="kategori"][value="${data.kategori}"]`).checked = true;
                document.getElementById('deskripsi').value = data.deskripsi || '';
                updatePreview();
                updateCharCounter();
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['judul', 'deskripsi'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
        updatePreview();
        triggerAutosave();
    });
});
document.getElementById('deskripsi').addEventListener('input', updateCharCounter);
document.querySelectorAll('input[name="kategori"]').forEach(r => r.addEventListener('change', updatePreview));

// Form submit with loading
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const file = fileInput.files[0];
    
    if (!judul) {
        e.preventDefault();
        alert('Judul dokumen wajib diisi.');
        return;
    }
    if (!file && !<?= $edit ? 'true' : 'false' ?>) {
        e.preventDefault();
        alert('File wajib diupload.');
        return;
    }
    
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    
    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// Keyboard shortcut: Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('uploadForm').submit();
    }
});

// Initial preview
updatePreview();
updateCharCounter();

console.log('%c Upload File FKIP UNIMOF', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>