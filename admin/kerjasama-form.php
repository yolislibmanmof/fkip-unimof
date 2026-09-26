<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

// PERBAIKAN: Pisahkan prepare, execute, dan fetch
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM kerjasama WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama_institusi']);
    $negara = trim($_POST['negara']);
    $jenis = $_POST['jenis'];
    $bentuk = trim($_POST['bentuk_kerjasama']);
    $tgl_mulai = $_POST['tanggal_mulai'] ?: null;
    $tgl_selesai = $_POST['tanggal_selesai'] ?: null;
    $status = $_POST['status'];
    $logo = $edit['logo'] ?? null;

    if (!empty($_FILES['logo']['name'])) {
        $up = upload_image($_FILES['logo'], 'kerjasama', 3 * 1024 * 1024);
        if (!$up['ok']) {
            flash_message('error', $up['error']);
            header('Location: kerjasama-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        if ($up['name']) {
            delete_upload($logo, 'kerjasama');
            $logo = $up['name'];
        }
    }

    if ($edit) {
        $pdo->prepare("UPDATE kerjasama SET nama_institusi=?, negara=?, jenis=?, bentuk_kerjasama=?, tanggal_mulai=?, tanggal_selesai=?, logo=?, status=? WHERE id=?")
            ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status, $id]);
        flash_message('success', '✅ Data mitra berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO kerjasama (nama_institusi, negara, jenis, bentuk_kerjasama, tanggal_mulai, tanggal_selesai, logo, status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$nama, $negara, $jenis, $bentuk, $tgl_mulai, $tgl_selesai, $logo, $status]);
        flash_message('success', '✅ Mitra baru berhasil ditambahkan.');
    }
    header('Location: kerjasama.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'kerjasama';
$page_heading = $edit ? 'Edit Mitra' : 'Tambah Mitra';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kerjasama', 'kerjasama.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899); }
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
.form-input, .form-select { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; background: var(--bg-primary); color: var(--text-primary); transition: all 0.3s; }
.form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(10,104,71,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; background: var(--bg-primary); transition: all 0.3s; cursor: pointer; position: relative; }
.upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(10,104,71,0.03); }
.upload-zone-icon { font-size: 3rem; margin-bottom: 0.75rem; animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.upload-zone-text { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
.upload-zone-sub { font-size: 0.85rem; color: var(--text-muted); }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.logo-preview { display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-md); align-items: center; gap: 1rem; }
.logo-preview.show { display: flex; }
.logo-preview-img { width: 80px; height: 80px; border-radius: 12px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border); }
.logo-preview-img img { width: 100%; height: 100%; object-fit: contain; padding: 0.5rem; }
.logo-preview-info { flex: 1; min-width: 0; }
.logo-preview-info h4 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.logo-preview-info small { font-size: 0.78rem; color: var(--text-muted); }
.logo-preview-remove { background: #fee2e2; color: #dc2626; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
.logo-preview-remove:hover { background: #dc2626; color: white; }

.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-header p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }
.preview-logo { width: 100px; height: 100px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 1.5rem; overflow: hidden; border: 3px solid var(--border); }
.preview-logo img { width: 100%; height: 100%; object-fit: contain; padding: 1rem; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; text-align: center; margin-bottom: 0.5rem; line-height: 1.3; min-height: 2.5rem; }
.preview-jenis { display: block; text-align: center; margin-bottom: 1.5rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 60px; border-left: 3px solid var(--primary); }
.preview-empty { color: var(--text-muted); font-style: italic; }

.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(10,104,71,0.35); }
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
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Mitra Kerjasama</h2>
            <p><?= $edit ? 'Perbarui detail mitra di bawah ini.' : 'Isi detail mitra kerjasama untuk ditambahkan.' ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="kerjasamaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">🏢 Informasi Institusi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Institusi <span class="required">*</span></label>
                        <input type="text" id="nama_institusi" name="nama_institusi" class="form-input" required placeholder="Contoh: Universiti Malaya" value="<?= sanitize($edit['nama_institusi'] ?? '') ?>">
                        <div class="form-hint">Nama lengkap institusi mitra</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Negara <span class="required">*</span></label>
                        <input type="text" id="negara" name="negara" class="form-input" required placeholder="Contoh: Malaysia" value="<?= sanitize($edit['negara'] ?? 'Indonesia') ?>">
                        <div class="form-hint">Negara asal institusi</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenis Mitra <span class="required">*</span></label>
                        <select id="jenis" name="jenis" class="form-select" required>
                            <?php foreach(['Universitas', 'Industri', 'Pemerintah', 'Lainnya'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '' ?>><?= $j === 'Universitas' ? '🎓' : ($j === 'Industri' ? '🏭' : ($j === 'Pemerintah' ? '🏛️' : '📌')) ?> <?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Kategori mitra</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                        <div class="form-hint">Status kerjasama saat ini</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📄 Logo Institusi</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="logo" name="logo" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop logo di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file (Maks 3MB)</div>
                </div>
                <div class="logo-preview" id="logoPreview">
                    <div class="logo-preview-img" id="logoPreviewImg"><span>️</span></div>
                    <div class="logo-preview-info">
                        <h4 id="logoName">-</h4>
                        <small id="logoSize">-</small>
                    </div>
                    <button type="button" class="logo-preview-remove" onclick="removeLogo()">✕</button>
                </div>
                <?php if (!empty($edit['logo'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; display: flex; align-items: center; gap: 1rem;">
                        <img src="<?= asset('uploads/kerjasama/' . basename($edit['logo'])) ?>" style="width: 60px; height: 60px; object-fit: contain; border-radius: 8px; background: white; padding: 0.25rem;">
                        <div>
                            <strong>Logo saat ini:</strong> <?= sanitize($edit['logo']) ?>
                            <br><small style="color: var(--text-muted);">Upload logo baru untuk mengganti</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <div class="form-section-title">🤝 Detail Kerjasama</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Bentuk Kerjasama</label>
                    <textarea id="bentuk_kerjasama" name="bentuk_kerjasama" class="form-input" rows="4" placeholder="Deskripsikan bentuk kerjasama (contoh: Pertukaran mahasiswa, riset bersama, magang, dll)" style="resize: vertical;"><?= sanitize($edit['bentuk_kerjasama'] ?? '') ?></textarea>
                    <div class="form-hint"><span id="bentukCounter"><?= strlen($edit['bentuk_kerjasama'] ?? '') ?></span>/500 karakter</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input" value="<?= $edit['tanggal_mulai'] ?? '' ?>">
                        <div class="form-hint">Awal masa kerjasama</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input" value="<?= $edit['tanggal_selesai'] ?? '' ?>">
                        <div class="form-hint">Akhir masa kerjasama (opsional)</div>
                    </div>
                </div>
                <div class="date-calc" id="dateCalc" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; display: none; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: #166534; font-weight: 600;">
                    <span style="font-size: 1.5rem;">⏱️</span>
                    <span id="dateCalcText">-</span>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Data' ?></span>
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
            <p>Pratinjau tampilan mitra</p>
        </div>

        <div id="previewContent">
            <div class="preview-logo" id="previewLogo"><span>🏢</span></div>
            <h2 class="preview-title" id="previewTitle">Nama institusi akan muncul di sini...</h2>
            <span class="preview-jenis badge-extreme badge-lainnya" id="previewJenis">📌 Lainnya</span>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>🌍</span>
                    <strong id="previewNegara">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span></span>
                    <strong id="previewDate">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📊</span>
                    <strong id="previewStatus">✅ Aktif</strong>
                </div>
            </div>

            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi kerjasama akan muncul di sini...</span>
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
// ===== DRAG & DROP LOGO =====
const uploadZone = document.getElementById('uploadZone');
const logoInput = document.getElementById('logo');
const logoPreview = document.getElementById('logoPreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    const files = e.dataTransfer.files;
    if (files.length > 0) handleLogo(files[0]);
});
logoInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleLogo(e.target.files[0]);
});

function handleLogo(file) {
    if (!file.type.startsWith('image/')) { alert('Hanya file gambar yang diizinkan!'); return; }
    if (file.size > 3 * 1024 * 1024) { alert('Ukuran file maksimal 3MB!'); return; }
    
    const dt = new DataTransfer();
    dt.items.add(file);
    logoInput.files = dt.files;
    
    document.getElementById('logoName').textContent = file.name;
    document.getElementById('logoSize').textContent = formatFileSize(file.size);
    
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logoPreviewImg').innerHTML = `<img src="${e.target.result}" alt="">`;
    };
    reader.readAsDataURL(file);
    
    logoPreview.classList.add('show');
    uploadZone.style.display = 'none';
    
    updatePreview();
    triggerAutosave();
}

function removeLogo() {
    logoInput.value = '';
    logoPreview.classList.remove('show');
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
const jenisConfig = {
    'Universitas': { icon: '🎓', class: 'badge-universitas' },
    'Industri': { icon: '🏭', class: 'badge-industri' },
    'Pemerintah': { icon: '🏛️', class: 'badge-pemerintah' },
    'Lainnya': { icon: '📌', class: 'badge-lainnya' }
};

function updatePreview() {
    const nama = document.getElementById('nama_institusi').value || 'Nama institusi akan muncul di sini...';
    const negara = document.getElementById('negara').value || '-';
    const jenis = document.getElementById('jenis').value || 'Lainnya';
    const bentuk = document.getElementById('bentuk_kerjasama').value;
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const status = document.getElementById('status').value || 'Aktif';

    const config = jenisConfig[jenis] || jenisConfig['Lainnya'];
    document.getElementById('previewTitle').textContent = nama;
    
    const jenisEl = document.getElementById('previewJenis');
    jenisEl.textContent = `${config.icon} ${jenis}`;
    jenisEl.className = `preview-jenis badge-extreme ${config.class}`;
    
    document.getElementById('previewNegara').textContent = (negara !== 'Indonesia' ? '🌍 ' : '🇮 ') + negara;
    
    if (tglMulai) {
        let dateStr = 'Sejak ' + new Date(tglMulai).getFullYear();
        if (tglSelesai) dateStr += ' - ' + new Date(tglSelesai).getFullYear();
        document.getElementById('previewDate').textContent = dateStr;
    } else {
        document.getElementById('previewDate').textContent = '-';
    }
    
    const statusEl = document.getElementById('previewStatus');
    statusEl.textContent = (status === 'Aktif' ? '✅' : '❌') + ' ' + status;
    statusEl.className = 'preview-meta-item';
    statusEl.querySelector('strong') || (statusEl.innerHTML = `<strong>${status === 'Aktif' ? '✅' : '❌'} ${status}</strong>`);
    
    if (bentuk) {
        document.getElementById('previewDesc').innerHTML = bentuk.replace(/\n/g, '<br>');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi kerjasama akan muncul di sini...</span>';
    }
}

// ===== DATE CALCULATOR =====
function calculateDate() {
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const calc = document.getElementById('dateCalc');
    const text = document.getElementById('dateCalcText');

    if (!tglMulai && !tglSelesai) { calc.style.display = 'none'; return; }
    
    calc.style.display = 'flex';
    
    if (tglMulai && tglSelesai) {
        const days = Math.ceil((new Date(tglSelesai) - new Date(tglMulai)) / (1000 * 60 * 60 * 24));
        const years = Math.floor(days / 365);
        const months = Math.floor((days % 365) / 30);
        text.textContent = `Durasi kerjasama: ${years > 0 ? years + ' tahun ' : ''}${months} bulan (${days} hari)`;
    } else if (tglMulai) {
        const days = Math.ceil((new Date() - new Date(tglMulai)) / (1000 * 60 * 60 * 24));
        text.textContent = `Sudah berjalan ${days} hari sejak ${new Date(tglMulai).toLocaleDateString('id-ID')}`;
    }
    
    updatePreview();
}

// ===== CHARACTER COUNTER =====
document.getElementById('bentuk_kerjasama').addEventListener('input', function() {
    document.getElementById('bentukCounter').textContent = this.value.length;
    triggerAutosave();
});

// ===== AUTOSAVE =====
const storageKey = 'fkip_kerjasama_draft_<?= $id ?: "new" ?>';
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
            nama_institusi: document.getElementById('nama_institusi').value,
            negara: document.getElementById('negara').value,
            jenis: document.getElementById('jenis').value,
            bentuk_kerjasama: document.getElementById('bentuk_kerjasama').value,
            tanggal_mulai: document.getElementById('tanggal_mulai').value,
            tanggal_selesai: document.getElementById('tanggal_selesai').value,
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
                document.getElementById('nama_institusi').value = data.nama_institusi || '';
                document.getElementById('negara').value = data.negara || 'Indonesia';
                document.getElementById('jenis').value = data.jenis || 'Lainnya';
                document.getElementById('bentuk_kerjasama').value = data.bentuk_kerjasama || '';
                document.getElementById('tanggal_mulai').value = data.tanggal_mulai || '';
                document.getElementById('tanggal_selesai').value = data.tanggal_selesai || '';
                document.getElementById('status').value = data.status || 'Aktif';
                updatePreview();
                calculateDate();
                document.getElementById('bentukCounter').textContent = (data.bentuk_kerjasama || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama_institusi', 'negara', 'jenis', 'bentuk_kerjasama', 'tanggal_mulai', 'tanggal_selesai', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); calculateDate(); });
});

// Form submit with loading
document.getElementById('kerjasamaForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama_institusi').value.trim();
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama_institusi').classList.add('error');
        alert('Nama Institusi wajib diisi!');
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
        document.getElementById('kerjasamaForm').submit();
    }
});

// Initial preview
updatePreview();
calculateDate();

console.log('%c🤝 Form Kerjasama FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
console.log('%cShortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; 