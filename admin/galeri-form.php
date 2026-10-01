<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM galeri WHERE id=?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) { 
        flash_message('error', '❌ Foto tidak ditemukan.'); 
        header('Location: galeri.php'); 
        exit; 
    }
}

// ===== PROSES POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'Umum') ?: 'Umum';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Published';
    $gambar = $edit['gambar'] ?? null;

    if ($judul === '') { 
        flash_message('error', '❌ Judul wajib diisi.'); 
        header('Location: galeri-form.php' . ($id ? "?id=$id" : '')); 
        exit; 
    }

    $files = $_FILES['gambar'] ?? [];
    $uploaded = [];

    if (!empty($files['name'][0])) {
        $dir = APP_DIR . '/assets/uploads/galeri';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $count = count($files['name']);
        $errors = [];
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($files['tmp_name'][$i]);
            
            if (!in_array($mime, $allowed)) {
                $errors[] = $files['name'][$i] . ' (format tidak didukung)';
                continue;
            }
            if ($files['size'][$i] > 5 * 1024 * 1024) {
                $errors[] = $files['name'][$i] . ' (terlalu besar, maks 5MB)';
                continue;
            }

            $ext = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'][$mime];
            $name = 'gal_' . time() . '_' . $i . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            
            if (move_uploaded_file($files['tmp_name'][$i], $dir . '/' . $name)) {
                $uploaded[] = $name;
            } else {
                $errors[] = $files['name'][$i] . ' (gagal disimpan)';
            }
        }
        
        if (!empty($errors)) {
            flash_message('warning', '⚠️ Beberapa file dilewati: ' . implode(', ', $errors));
        }
    }

    try {
        if ($edit && $id > 0) {
            if (!empty($uploaded)) {
                // Hapus gambar lama
                if ($gambar) { 
                    $p = APP_DIR . '/assets/uploads/galeri/' . basename($gambar); 
                    if (is_file($p)) @unlink($p); 
                }
                $gambar = $uploaded[0]; // Ambil yang pertama untuk replace
            }
            $pdo->prepare("UPDATE galeri SET judul=?, deskripsi=?, kategori=?, tanggal=?, gambar=?, status=? WHERE id=?")
                ->execute([$judul, $deskripsi, $kategori, $tanggal, $gambar, $status, $id]);
            flash_message('success', '✅ Foto berhasil diperbarui.');
        } else {
            if (empty($uploaded)) { 
                flash_message('error', '❌ Minimal 1 foto wajib diunggah.'); 
                header('Location: galeri-form.php'); 
                exit; 
            }
            foreach ($uploaded as $u) {
                $pdo->prepare("INSERT INTO galeri (judul, deskripsi, kategori, tanggal, gambar, status) VALUES (?,?,?,?,?,?)")
                    ->execute([$judul, $deskripsi, $kategori, $tanggal, $u, $status]);
            }
            flash_message('success', '✅ ' . count($uploaded) . ' foto berhasil diunggah.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
    }
    
    header('Location: galeri.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'galeri';
$page_heading = $edit ? 'Edit Foto' : 'Upload Foto';
$breadcrumbs = [['Dashboard','dashboard.php'], ['Galeri','galeri.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== EXTREME MULTIMATE FORM STYLES ===== */
.form-layout { 
    display: grid; 
    grid-template-columns: 1.3fr 1fr; 
    gap: 2rem; 
    align-items: start; 
}
.form-card { 
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: var(--radius-xl); 
    padding: 2rem; 
    box-shadow: var(--shadow-lg); 
    position: relative; 
    overflow: hidden; 
}
.form-card::before { 
    content: ''; 
    position: absolute; 
    top: 0; left: 0; right: 0; 
    height: 4px; 
    background: linear-gradient(90deg, #8b5cf6, #6366f1, #4f46e5); 
}
.form-card h2 { 
    font-family: var(--font-display); 
    font-size: 1.6rem; 
    margin-bottom: 0.5rem; 
    display: flex; 
    align-items: center; 
    gap: 0.6rem; 
}
.form-card > p { 
    color: var(--text-muted); 
    font-size: 0.9rem; 
    margin-bottom: 1.5rem; 
}
.form-section { 
    background: var(--bg-secondary); 
    padding: 1.5rem; 
    border-radius: var(--radius-lg); 
    border: 1px solid var(--border); 
    margin-bottom: 1.5rem; 
    transition: border-color 0.3s;
}
.form-section:focus-within {
    border-color: var(--primary);
}
.form-section h3 { 
    font-size: 1rem; 
    font-weight: 700; 
    margin-bottom: 1rem; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}
.form-row { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 1rem; 
    margin-bottom: 1rem; 
}
.form-row:last-child { margin-bottom: 0; }
.form-group { margin-bottom: 0; }
.form-group label { 
    display: block; 
    font-size: 0.85rem; 
    font-weight: 600; 
    color: var(--text-secondary); 
    margin-bottom: 0.5rem; 
}
.form-group label .req { color: #ef4444; }
.form-input, .form-select, .form-textarea { 
    width: 100%; 
    padding: 0.75rem 1rem; 
    border: 2px solid var(--border); 
    border-radius: var(--radius-md); 
    font-family: inherit; 
    font-size: 0.95rem; 
    background: var(--bg-primary); 
    color: var(--text-primary); 
    transition: all 0.25s; 
}
.form-textarea { resize: vertical; min-height: 100px; }
.form-input:focus, .form-select:focus, .form-textarea:focus { 
    outline: none; 
    border-color: var(--primary); 
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1); 
}

/* Upload Zone Extreme */
.upload-zone { 
    border: 2px dashed var(--border); 
    border-radius: var(--radius-lg); 
    padding: 2.5rem 2rem; 
    text-align: center; 
    background: var(--bg-primary); 
    transition: all 0.3s; 
    cursor: pointer; 
    position: relative; 
}
.upload-zone:hover, .upload-zone.dragover { 
    border-color: var(--primary); 
    background: rgba(10,104,71,0.03); 
    transform: translateY(-2px);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.upload-zone input[type="file"] { 
    position: absolute; 
    inset: 0; 
    opacity: 0; 
    cursor: pointer; 
}
.upload-zone .ic { 
    font-size: 3rem; 
    margin-bottom: 0.5rem; 
    transition: transform 0.3s;
}
.upload-zone:hover .ic { transform: scale(1.1); }
.upload-zone .t1 { 
    font-weight: 700; 
    color: var(--text-primary); 
    margin-bottom: 0.25rem; 
}
.upload-zone .t2 { 
    font-size: 0.82rem; 
    color: var(--text-muted); 
}

/* Preview List */
.preview-list { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); 
    gap: 0.75rem; 
    margin-top: 1rem; 
}
.preview-item { 
    position: relative; 
    aspect-ratio: 1; 
    border-radius: var(--radius-md); 
    overflow: hidden; 
    background: var(--bg-tertiary); 
    border: 1px solid var(--border);
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
.preview-item img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
}
.preview-item .rm { 
    position: absolute; 
    top: 0.35rem; 
    right: 0.35rem; 
    width: 24px; 
    height: 24px; 
    border-radius: 50%; 
    background: rgba(220,38,38,0.9); 
    color: #fff; 
    border: none; 
    cursor: pointer; 
    font-size: 0.8rem; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    transition: transform 0.2s;
}
.preview-item .rm:hover { transform: scale(1.1); }

.current-img { 
    margin-top: 1rem; 
    padding: 1rem; 
    background: var(--bg-primary); 
    border-radius: var(--radius-md); 
    border: 1px solid var(--border); 
    display: flex; 
    align-items: center; 
    gap: 1rem; 
}
.current-img img { 
    width: 60px; 
    height: 60px; 
    object-fit: cover; 
    border-radius: 8px; 
}

/* Save Button */
.btn-save { 
    width: 100%; 
    padding: 1rem; 
    background: linear-gradient(135deg, #8b5cf6, #6366f1); 
    color: #fff; 
    border: none; 
    border-radius: var(--radius-md); 
    font-family: inherit; 
    font-size: 1rem; 
    font-weight: 700; 
    cursor: pointer; 
    transition: all 0.25s; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 0.5rem; 
    position: relative;
    overflow: hidden;
}
.btn-save:hover:not(:disabled) { 
    transform: translateY(-2px); 
    box-shadow: 0 10px 25px rgba(139,92,246,0.35); 
}
.btn-save:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.btn-save .spinner {
    width: 20px; height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.btn-save.loading .spinner { display: inline-block; }
.btn-save.loading .btn-text { display: none; }

/* Preview Panel */
.preview-card { 
    background: var(--bg-primary); 
    border: 1px solid var(--border); 
    border-radius: var(--radius-xl); 
    padding: 1.5rem; 
    box-shadow: var(--shadow-lg); 
    position: sticky; 
    top: 100px; 
    transition: all 0.3s;
}
.preview-card h3 { 
    font-family: var(--font-display); 
    font-size: 1.1rem; 
    margin-bottom: 1rem; 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}
.preview-img { 
    width: 100%; 
    aspect-ratio: 4/3; 
    border-radius: var(--radius-md); 
    overflow: hidden; 
    background: var(--bg-tertiary); 
    margin-bottom: 1rem; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 4rem; 
    color: var(--text-muted);
    transition: all 0.3s;
}
.preview-img img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
}
.preview-title { 
    font-family: var(--font-display); 
    font-size: 1.2rem; 
    font-weight: 800; 
    margin-bottom: 0.5rem; 
    color: var(--text-primary);
    min-height: 1.4em;
}
.preview-meta { 
    display: flex; 
    flex-direction: column; 
    gap: 0.5rem; 
    font-size: 0.85rem; 
    color: var(--text-secondary); 
}
.preview-meta span { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}

@media (max-width: 968px) { 
    .form-layout { grid-template-columns: 1fr; } 
    .preview-card { position: static; order: -1; } 
    .form-row { grid-template-columns: 1fr; } 
}
</style>

<div class="form-layout">
    <div class="form-card">
        <h2><?= $edit ? '✏️ Edit Foto' : '📤 Upload Foto' ?></h2>
        <p><?= $edit ? 'Perbarui detail foto di bawah ini.' : 'Unggah dokumentasi kegiatan FKIP UNIMOF. Bisa upload banyak foto sekaligus.' ?></p>

        <form method="POST" enctype="multipart/form-data" id="galForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <h3>📤 Foto</h3>
                <div class="upload-zone" id="uploadZone">
                    <!-- Hapus 'multiple' jika mode edit agar hanya 1 file yang bisa diganti -->
                    <input type="file" id="gambarInput" name="gambar[]" accept="image/jpeg,image/png,image/webp" <?= $edit ? '' : 'required' ?> <?= $edit ? '' : 'multiple' ?>>
                    <div class="ic">📁</div>
                    <div class="t1">Drag & drop foto di sini</div>
                    <div class="t2">atau klik untuk memilih • JPG/PNG/WEBP • Maks 5MB per foto</div>
                </div>
                <div class="preview-list" id="previewList"></div>

                <?php if ($edit && !empty($edit['gambar'])): ?>
                <div class="current-img">
                    <img src="<?= asset('uploads/galeri/' . basename($edit['gambar'])) ?>" alt="Current">
                    <div style="flex:1; min-width:0;">
                        <strong>Foto saat ini:</strong>
                        <div style="font-size:0.78rem; color:var(--text-muted); font-family:monospace; word-break:break-all;"><?= sanitize($edit['gambar']) ?></div>
                        <small style="color:var(--text-muted);">Upload foto baru untuk mengganti</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <h3>📝 Informasi</h3>
                <div class="form-row">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Judul <span class="req">*</span></label>
                        <input type="text" name="judul" id="judul" class="form-input" required value="<?= sanitize($edit['judul'] ?? '') ?>" placeholder="Contoh: Wisuda Periode I 2026" maxlength="255">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" name="kategori" id="kategori" class="form-input" list="katList" value="<?= sanitize($edit['kategori'] ?? 'Kegiatan') ?>" placeholder="Kegiatan">
                        <datalist id="katList">
                            <option value="Akademik">
                            <option value="Kegiatan">
                            <option value="Prestasi">
                            <option value="Wisuda">
                            <option value="Riset">
                            <option value="Kerjasama">
                            <option value="Umum">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" id="tanggal" class="form-input" value="<?= $edit['tanggal'] ?? date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Deskripsi</label>
                        <textarea name="deskripsi" id="deskripsi" class="form-textarea" rows="3" placeholder="Deskripsi singkat kegiatan…"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="Published" <?= ($edit['status'] ?? 'Published') === 'Published' ? 'selected' : '' ?>>✅ Published (tampil di publik)</option>
                            <option value="Draft" <?= ($edit['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>📝 Draft</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-save" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Foto' : '✨ Upload Foto' ?></span>
                <span class="spinner"></span>
            </button>

            <div style="text-align:center; margin-top:1rem; font-size:0.78rem; color:var(--text-muted);">
                💡 Tip: <kbd style="background:var(--bg-secondary); padding:0.15rem 0.4rem; border-radius:4px; font-family:monospace; border:1px solid var(--border);">Ctrl+S</kbd> untuk simpan cepat
            </div>
        </form>
    </div>

    <!-- Preview Panel -->
    <div class="preview-card">
        <h3>👁️ Live Preview</h3>
        <div class="preview-img" id="prevImg">📷</div>
        <div class="preview-title" id="prevTitle"><?= $edit ? sanitize($edit['judul']) : 'Judul foto akan muncul di sini…' ?></div>
        <div class="preview-meta">
            <span>📂 <strong id="prevKat"><?= sanitize($edit['kategori'] ?? 'Kegiatan') ?></strong></span>
            <span>📅 <strong id="prevTgl"><?= !empty($edit['tanggal']) ? date('d M Y', strtotime($edit['tanggal'])) : date('d M Y') ?></strong></span>
            <span>📊 <strong id="prevSt"><?= ($edit['status'] ?? 'Published') === 'Published' ? '✅ Published' : '📝 Draft' ?></strong></span>
        </div>
    </div>
</div>

<script>
// ===== MULTI-FILE PREVIEW & DRAG DROP =====
const uploadZone = document.getElementById('uploadZone');
const gambarInput = document.getElementById('gambarInput');
const previewList = document.getElementById('previewList');
let selectedFiles = [];

['dragenter', 'dragover'].forEach(ev => { 
    uploadZone.addEventListener(ev, e => { 
        e.preventDefault(); 
        uploadZone.classList.add('dragover'); 
    }); 
});
['dragleave', 'drop'].forEach(ev => { 
    uploadZone.addEventListener(ev, e => { 
        e.preventDefault(); 
        uploadZone.classList.remove('dragover'); 
    }); 
});

uploadZone.addEventListener('drop', e => { 
    if (e.dataTransfer.files.length) handleFiles(e.dataTransfer.files); 
});
gambarInput.addEventListener('change', e => { 
    if (e.target.files.length) handleFiles(e.target.files); 
});

function handleFiles(files) {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    for (const f of files) {
        if (!allowed.includes(f.type)) { 
            showToast('Format tidak didukung: ' + f.name, 'warning'); 
            continue; 
        }
        if (f.size > 5 * 1024 * 1024) { 
            showToast('File terlalu besar (max 5MB): ' + f.name, 'warning'); 
            continue; 
        }
        selectedFiles.push(f);
    }
    renderPreviews();
}

function renderPreviews() {
    previewList.innerHTML = '';
    selectedFiles.forEach((f, i) => {
        const div = document.createElement('div');
        div.className = 'preview-item';
        const url = URL.createObjectURL(f);
        div.innerHTML = `<img src="${url}" alt=""><button type="button" class="rm" onclick="removeFile(${i})">✕</button>`;
        previewList.appendChild(div);
    });
    
    // Update input files using DataTransfer
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    gambarInput.files = dt.files;
    
    // Update preview panel (ambil file pertama)
    if (selectedFiles.length) {
        const url = URL.createObjectURL(selectedFiles[0]);
        document.getElementById('prevImg').innerHTML = `<img src="${url}" alt="">`;
    }
}

function removeFile(i) {
    selectedFiles.splice(i, 1);
    renderPreviews();
}

// ===== LIVE PREVIEW =====
['judul', 'kategori', 'tanggal', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', updatePreview);
    document.getElementById(id).addEventListener('change', updatePreview);
});

function updatePreview() {
    document.getElementById('prevTitle').textContent = document.getElementById('judul').value || 'Judul foto…';
    document.getElementById('prevKat').textContent = document.getElementById('kategori').value || '-';
    
    const tglVal = document.getElementById('tanggal').value;
    // Tambahkan 'T00:00:00' agar browser membacanya sebagai waktu lokal, bukan UTC
    document.getElementById('prevTgl').textContent = tglVal 
        ? new Date(tglVal + 'T00:00:00').toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) 
        : '-';
        
    const st = document.getElementById('status').value;
    document.getElementById('prevSt').textContent = st === 'Published' ? '✅ Published' : '📝 Draft';
}

// ===== SUBMIT HANDLER =====
document.getElementById('galForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    if (!judul) { 
        e.preventDefault(); 
        document.getElementById('judul').focus(); 
        showToast('Judul wajib diisi!', 'error'); 
        return; 
    }
    
    const isEdit = <?= $edit ? 'true' : 'false' ?>;
    if (!isEdit && selectedFiles.length === 0) { 
        e.preventDefault(); 
        showToast('Minimal 1 foto wajib diunggah!', 'error'); 
        return; 
    }
    
    // Loading state
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});

// ===== TOAST NOTIFICATION =====
function showToast(message, type = 'info') {
    if (window.showToast) {
        window.showToast('Notifikasi', message, type);
    } else {
        // Fallback toast
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed; bottom:2rem; right:2rem; padding:1rem 1.5rem; background:${type==='error'?'#fee2e2':(type==='warning'?'#fef3c7':'#dcfce7')}; color:${type==='error'?'#dc2626':(type==='warning'?'#92400e':'#166534')}; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999; font-weight:600; animation: fadeIn 0.3s ease;`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { 
        e.preventDefault(); 
        document.getElementById('galForm').requestSubmit(); 
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>