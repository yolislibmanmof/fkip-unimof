<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM akreditasi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $prodi = trim($_POST['nama_prodi']); 
    $badan = trim($_POST['badan_akreditasi']); 
    $peringkat = $_POST['peringkat'];
    $sk = trim($_POST['nomor_sk']); 
    $tgl_terbit = $_POST['tanggal_terbit'] ?: null; 
    $tgl_berlaku = $_POST['tanggal_berlaku'] ?: null;
    $status = $_POST['status']; 
    $file = $edit['sertifikat_file'] ?? null;

    if (!empty($_FILES['sertifikat']['name'])) {
        $allowed = ['application/pdf'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['sertifikat']['tmp_name']);
        if (!in_array($mime, $allowed)) { 
            flash_message('error', 'Hanya file PDF yang diizinkan.'); 
            header('Location: akreditasi-form.php' . ($id ? "?id=$id" : '')); 
            exit; 
        }
        
        $ext = 'pdf';
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = APP_DIR . '/assets/akreditasi';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        move_uploaded_file($_FILES['sertifikat']['tmp_name'], $dir . '/' . $file);
        
        if ($edit && $edit['sertifikat_file']) @unlink($dir . '/' . $edit['sertifikat_file']);
    }

    if ($edit) {
        $pdo->prepare("UPDATE akreditasi SET nama_prodi=?, badan_akreditasi=?, peringkat=?, nomor_sk=?, tanggal_terbit=?, tanggal_berlaku=?, sertifikat_file=?, status=? WHERE id=?")
            ->execute([$prodi, $badan, $peringkat, $sk, $tgl_terbit, $tgl_berlaku, $file, $status, $id]);
        flash_message('success', '✅ Data akreditasi berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO akreditasi (nama_prodi, badan_akreditasi, peringkat, nomor_sk, tanggal_terbit, tanggal_berlaku, sertifikat_file, status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$prodi, $badan, $peringkat, $sk, $tgl_terbit, $tgl_berlaku, $file, $status]);
        flash_message('success', '✅ Data akreditasi berhasil ditambahkan.');
    }
    header('Location: akreditasi.php'); exit;
}

$csrf = generate_csrf_token();
$active_menu = 'akreditasi'; 
$page_heading = $edit ? 'Edit Akreditasi' : 'Tambah Akreditasi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Akreditasi', 'akreditasi.php'], [$page_heading, null]];
require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM EXTREME LAYOUT ===== */
.form-layout-extreme {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 2rem;
    align-items: start;
}

/* ===== FORM CARD ===== */
.form-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}
.form-card-extreme::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f59e0b, #10b981, #3b82f6);
}

.form-header-extreme {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.form-header-extreme h2 {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.form-header-extreme p {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* ===== FORM SECTIONS ===== */
.form-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
}
.form-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ===== FORM ROWS & GROUPS ===== */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.form-label .required {
    color: #ef4444;
    margin-left: 0.25rem;
}
.form-input, .form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.95rem;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s;
}
.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.form-input.error {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
}
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
}

/* ===== FILE UPLOAD ZONE ===== */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    text-align: center;
    background: var(--bg-primary);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--primary);
    background: rgba(10,104,71,0.03);
}
.upload-zone-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    animation: bounce 2s infinite;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.upload-zone-text {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}
.upload-zone-sub {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

/* ===== PDF PREVIEW ===== */
.pdf-preview {
    display: none;
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    align-items: center;
    gap: 1rem;
}
.pdf-preview.show { display: flex; }
.pdf-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.pdf-info { flex: 1; min-width: 0; }
.pdf-info h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pdf-info small {
    font-size: 0.78rem;
    color: var(--text-muted);
}
.pdf-remove {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
}
.pdf-remove:hover {
    background: #dc2626;
    color: white;
}

/* ===== DATE CALCULATOR ===== */
.date-calc {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #86efac;
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-top: 1rem;
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: #166534;
    font-weight: 600;
}
.date-calc.show { display: flex; }
.date-calc.warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #fcd34d;
    color: #92400e;
}
.date-calc.danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border-color: #fca5a5;
    color: #991b1b;
}

/* ===== LIVE PREVIEW CARD ===== */
.preview-card-extreme {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 100px;
}
.preview-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.preview-header h3 {
    font-family: var(--font-display);
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.preview-header p {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.preview-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 1rem;
}
.preview-badge.unggul {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #fcd34d;
}
.preview-badge.baik-sekali {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    border: 1px solid #93c5fd;
}
.preview-badge.baik {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #166534;
    border: 1px solid #86efac;
}
.preview-badge.default {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.preview-title {
    font-family: var(--font-display);
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.3;
    min-height: 2.5rem;
}
.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}
.preview-meta-item strong {
    color: var(--text-primary);
    font-weight: 600;
}
.preview-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.preview-status.aktif { background: #dcfce7; color: #166534; }
.preview-status.kadaluarsa { background: #fee2e2; color: #991b1b; }
.preview-status.proses { background: #fef3c7; color: #92400e; }

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
}
.submit-btn-extreme:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(10,104,71,0.35);
}
.submit-btn-extreme:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.submit-btn-extreme .spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
.submit-btn-extreme.loading .spinner { display: inline-block; }
.submit-btn-extreme.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== AUTOSAVE INDICATOR ===== */
.autosave-indicator {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.75rem 1.25rem;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s;
    z-index: 100;
}
.autosave-indicator.show { opacity: 1; transform: translateY(0); }
.autosave-indicator.saving { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.autosave-indicator.saved { background: #dcfce7; border-color: #86efac; color: #166534; }

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .form-layout-extreme { grid-template-columns: 1fr; }
    .preview-card-extreme { position: static; order: -1; }
    .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .form-card-extreme, .preview-card-extreme { padding: 1.5rem; }
}
</style>

<div class="form-layout-extreme">
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Data Akreditasi</h2>
            <p><?= $edit ? 'Perbarui detail akreditasi program studi di bawah ini.' : 'Isi detail akreditasi program studi untuk ditambahkan ke database.' ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="akreditasiForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Prodi -->
            <div class="form-section">
                <div class="form-section-title">🎓 Informasi Program Studi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Program Studi <span class="required">*</span></label>
                        <input type="text" id="nama_prodi" name="nama_prodi" class="form-input" required placeholder="Contoh: Pendidikan Matematika" value="<?= sanitize($edit['nama_prodi'] ?? '') ?>">
                        <div class="form-hint">Nama lengkap program studi</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badan Akreditasi <span class="required">*</span></label>
                        <select id="badan_akreditasi" name="badan_akreditasi" class="form-select" required>
                            <?php foreach(['BAN-PT', 'LAMDIK', 'LAMEMKes', 'Lainnya'] as $b): ?>
                            <option value="<?= $b ?>" <?= ($edit['badan_akreditasi'] ?? 'BAN-PT') === $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Lembaga pemberi akreditasi</div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Detail Peringkat -->
            <div class="form-section">
                <div class="form-section-title"> Detail Peringkat & Status</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Peringkat Akreditasi <span class="required">*</span></label>
                        <select id="peringkat" name="peringkat" class="form-select" required>
                            <?php foreach(['Unggul', 'Baik Sekali', 'Baik', 'C', 'Proses Akreditasi'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($edit['peringkat'] ?? 'Proses Akreditasi') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Peringkat yang diperoleh</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Kadaluarsa" <?= ($edit['status'] ?? '') === 'Kadaluarsa' ? 'selected' : '' ?>>❌ Kadaluarsa</option>
                            <option value="Proses" <?= ($edit['status'] ?? '') === 'Proses' ? 'selected' : '' ?>>⏳ Proses</option>
                        </select>
                        <div class="form-hint">Status saat ini</div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Nomor SK</label>
                    <input type="text" id="nomor_sk" name="nomor_sk" class="form-input" placeholder="Contoh: 1234/SK/BAN-PT/Akred/S/2023" value="<?= sanitize($edit['nomor_sk'] ?? '') ?>" maxlength="100">
                    <div class="form-hint"><span id="skCounter"><?= strlen($edit['nomor_sk'] ?? '') ?></span>/100 karakter</div>
                </div>
            </div>

            <!-- Section 3: Upload Sertifikat -->
            <div class="form-section">
                <div class="form-section-title">📄 Upload Sertifikat (PDF)</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="sertifikat" name="sertifikat" accept=".pdf">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop file PDF di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                </div>
                <div class="pdf-preview" id="pdfPreview">
                    <div class="pdf-icon">📕</div>
                    <div class="pdf-info">
                        <h4 id="pdfName">-</h4>
                        <small id="pdfSize">-</small>
                    </div>
                    <button type="button" class="pdf-remove" onclick="removeFile()">✕</button>
                </div>
                <?php if (!empty($edit['sertifikat_file'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem;">
                        <strong>📄 File saat ini:</strong> <?= sanitize($edit['sertifikat_file']) ?>
                        <br>
                        <a href="<?= asset('akreditasi/' . basename($edit['sertifikat_file'])) ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;">️ Lihat File</a>
                        <br>
                        <small style="color: var(--text-muted);">Upload file baru untuk mengganti</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 4: Masa Berlaku -->
            <div class="form-section">
                <div class="form-section-title">📅 Masa Berlaku</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Terbit</label>
                        <input type="date" id="tanggal_terbit" name="tanggal_terbit" class="form-input" value="<?= $edit['tanggal_terbit'] ?? '' ?>">
                        <div class="form-hint">Tanggal SK diterbitkan</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Berlaku Sampai</label>
                        <input type="date" id="tanggal_berlaku" name="tanggal_berlaku" class="form-input" value="<?= $edit['tanggal_berlaku'] ?? '' ?>">
                        <div class="form-hint">Masa berlaku akreditasi</div>
                    </div>
                </div>
                <div class="date-calc" id="dateCalc">
                    <span style="font-size: 1.5rem;">️</span>
                    <span id="dateCalcText">-</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Data' ?></span>
                <span class="spinner"></span>
            </button>

            <p style="text-align: center; margin-top: 1rem; font-size: 0.78rem; color: var(--text-muted);">
                💡 Tip: Tekan <kbd style="background: var(--bg-secondary); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace;">Ctrl+S</kbd> untuk menyimpan cepat
            </p>
        </form>
    </div>

    <!-- ===== RIGHT: LIVE PREVIEW ===== -->
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <h3>👁️ Live Preview</h3>
            <p>Pratinjau tampilan data akreditasi</p>
        </div>

        <div id="previewContent">
            <div class="preview-badge default" id="previewBadge">📋 PROSES AKREDITASI</div>
            <h2 class="preview-title" id="previewTitle">Nama program studi akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>🏛️</span>
                    <strong id="previewBadan">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📜</span>
                    <strong id="previewSK">Belum diisi</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📅</span>
                    <strong id="previewDate">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>⏱️</span>
                    <strong id="previewRemaining">-</strong>
                </div>
            </div>

            <div class="preview-status aktif" id="previewStatus">✅ Aktif</div>
        </div>
    </div>
</div>

<!-- Autosave Indicator -->
<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DRAG & DROP UPLOAD =====
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('sertifikat');
const pdfPreview = document.getElementById('pdfPreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleFile(files[0]);
});
fileInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleFile(e.target.files[0]);
});

function handleFile(file) {
    if (file.type !== 'application/pdf') {
        alert('Hanya file PDF yang diizinkan!');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB!');
        return;
    }
    
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    
    document.getElementById('pdfName').textContent = file.name;
    document.getElementById('pdfSize').textContent = formatFileSize(file.size);
    pdfPreview.classList.add('show');
    uploadZone.style.display = 'none';
    
    updatePreview();
    triggerAutosave();
}

function removeFile() {
    fileInput.value = '';
    pdfPreview.classList.remove('show');
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

// ===== LIVE PREVIEW =====
const badgeConfig = {
    'Unggul': { icon: '🥇', class: 'unggul', label: 'UNGGUL' },
    'Baik Sekali': { icon: '🥈', class: 'baik-sekali', label: 'BAIK SEKALI' },
    'Baik': { icon: '🥉', class: 'baik', label: 'BAIK' },
    'C': { icon: '', class: 'default', label: 'C' },
    'Proses Akreditasi': { icon: '⏳', class: 'default', label: 'PROSES AKREDITASI' }
};

function updatePreview() {
    const nama = document.getElementById('nama_prodi').value || 'Nama program studi akan muncul di sini...';
    const badan = document.getElementById('badan_akreditasi').value || '-';
    const peringkat = document.getElementById('peringkat').value || 'Proses Akreditasi';
    const sk = document.getElementById('nomor_sk').value || 'Belum diisi';
    const tglTerbit = document.getElementById('tanggal_terbit').value;
    const tglBerlaku = document.getElementById('tanggal_berlaku').value;
    const status = document.getElementById('status').value || 'Aktif';

    const config = badgeConfig[peringkat] || badgeConfig['Proses Akreditasi'];
    const badge = document.getElementById('previewBadge');
    badge.textContent = `${config.icon} ${config.label}`;
    badge.className = `preview-badge ${config.class}`;

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewBadan').textContent = badan;
    document.getElementById('previewSK').textContent = sk;

    if (tglTerbit && tglBerlaku) {
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const d1 = new Date(tglTerbit);
        const d2 = new Date(tglBerlaku);
        document.getElementById('previewDate').textContent = `${d1.getDate()} ${months[d1.getMonth()]} ${d1.getFullYear()} - ${d2.getDate()} ${months[d2.getMonth()]} ${d2.getFullYear()}`;
    } else {
        document.getElementById('previewDate').textContent = '-';
    }

    const statusEl = document.getElementById('previewStatus');
    statusEl.textContent = (status === 'Aktif' ? '✅' : (status === 'Kadaluarsa' ? '❌' : '⏳')) + ' ' + status;
    statusEl.className = `preview-status ${status.toLowerCase()}`;

    // Calculate remaining days
    if (tglBerlaku) {
        const days = Math.ceil((new Date(tglBerlaku) - new Date()) / (1000 * 60 * 60 * 24));
        if (days < 0) {
            document.getElementById('previewRemaining').textContent = `Kadaluarsa ${Math.abs(days)} hari lalu`;
        } else if (days === 0) {
            document.getElementById('previewRemaining').textContent = 'Kadaluarsa hari ini!';
        } else {
            document.getElementById('previewRemaining').textContent = `${days} hari lagi`;
        }
    } else {
        document.getElementById('previewRemaining').textContent = '-';
    }
}

// ===== DATE CALCULATOR =====
function calculateDate() {
    const tglTerbit = document.getElementById('tanggal_terbit').value;
    const tglBerlaku = document.getElementById('tanggal_berlaku').value;
    const calc = document.getElementById('dateCalc');
    const text = document.getElementById('dateCalcText');

    if (!tglBerlaku) {
        calc.classList.remove('show');
        return;
    }

    const days = Math.ceil((new Date(tglBerlaku) - new Date()) / (1000 * 60 * 60 * 24));
    
    calc.classList.add('show');
    calc.classList.remove('warning', 'danger');

    if (days < 0) {
        calc.classList.add('danger');
        text.textContent = `⚠️ Sudah kadaluarsa ${Math.abs(days)} hari yang lalu!`;
    } else if (days === 0) {
        calc.classList.add('danger');
        text.textContent = '⚠️ Kadaluarsa hari ini!';
    } else if (days <= 90) {
        calc.classList.add('danger');
        text.textContent = `⚠️ Hanya ${days} hari lagi sebelum kadaluarsa!`;
    } else if (days <= 180) {
        calc.classList.add('warning');
        text.textContent = `⏰ ${days} hari lagi sebelum kadaluarsa`;
    } else {
        text.textContent = `✅ Masih ${days} hari sebelum kadaluarsa`;
    }

    updatePreview();
}

// ===== CHARACTER COUNTER =====
document.getElementById('nomor_sk').addEventListener('input', function() {
    document.getElementById('skCounter').textContent = this.value.length;
    triggerAutosave();
});

// ===== AUTOSAVE =====
const storageKey = 'fkip_akreditasi_draft_<?= $id ?: "new" ?>';
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
            nama_prodi: document.getElementById('nama_prodi').value,
            badan_akreditasi: document.getElementById('badan_akreditasi').value,
            peringkat: document.getElementById('peringkat').value,
            nomor_sk: document.getElementById('nomor_sk').value,
            tanggal_terbit: document.getElementById('tanggal_terbit').value,
            tanggal_berlaku: document.getElementById('tanggal_berlaku').value,
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
                document.getElementById('nama_prodi').value = data.nama_prodi || '';
                document.getElementById('badan_akreditasi').value = data.badan_akreditasi || 'BAN-PT';
                document.getElementById('peringkat').value = data.peringkat || 'Proses Akreditasi';
                document.getElementById('nomor_sk').value = data.nomor_sk || '';
                document.getElementById('tanggal_terbit').value = data.tanggal_terbit || '';
                document.getElementById('tanggal_berlaku').value = data.tanggal_berlaku || '';
                document.getElementById('status').value = data.status || 'Aktif';
                updatePreview();
                calculateDate();
                document.getElementById('skCounter').textContent = (data.nomor_sk || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama_prodi', 'badan_akreditasi', 'peringkat', 'nomor_sk', 'tanggal_terbit', 'tanggal_berlaku', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
        updatePreview();
        triggerAutosave();
    });
    document.getElementById(id).addEventListener('change', () => {
        updatePreview();
        triggerAutosave();
    });
});
document.getElementById('tanggal_berlaku').addEventListener('change', calculateDate);

// Form submit with loading
document.getElementById('akreditasiForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama_prodi').value.trim();
    
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama_prodi').classList.add('error');
        alert('Nama Program Studi wajib diisi!');
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
        document.getElementById('akreditasiForm').submit();
    }
});

// Initial preview
updatePreview();
calculateDate();

console.log('%c Form Akreditasi FKIP UNIMOF', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%c Shortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>