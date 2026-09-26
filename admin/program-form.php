<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM program_studi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']);
    $singkatan = trim($_POST['singkatan']);
    $jenjang = $_POST['jenjang'];
    $akreditasi = $_POST['akreditasi'];
    $deskripsi = trim($_POST['deskripsi']);
    $status = $_POST['status'];

    if ($edit) {
        $pdo->prepare("UPDATE program_studi SET nama=?, singkatan=?, jenjang=?, akreditasi=?, deskripsi=?, status=? WHERE id=?")
            ->execute([$nama, $singkatan, $jenjang, $akreditasi, $deskripsi, $status, $id]);
        flash_message('success', '✅ Program studi berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO program_studi (nama, singkatan, jenjang, akreditasi, deskripsi, status) VALUES (?,?,?,?,?,?)")
            ->execute([$nama, $singkatan, $jenjang, $akreditasi, $deskripsi, $status]);
        flash_message('success', '✅ Program studi baru berhasil ditambahkan.');
    }
    header('Location: program.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'prodi';
$page_heading = $edit ? 'Edit Program Studi' : 'Tambah Program Studi';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Program Studi', 'program.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb, #1d4ed8); }
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
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-header p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }
.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-singkatan { font-size: 1.1rem; font-weight: 700; color: var(--primary); font-family: monospace; margin-bottom: 1rem; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-desc { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); min-height: 60px; border-left: 3px solid #3b82f6; }
.preview-empty { color: var(--text-muted); font-style: italic; }

.submit-btn-extreme { width: 100%; padding: 1.1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.submit-btn-extreme:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(59,130,246,0.35); }
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
            <h2><?= $edit ? '️ Edit' : '➕ Tambah' ?> Program Studi</h2>
            <p><?= $edit ? 'Perbarui detail program studi.' : 'Isi detail program studi baru.' ?></p>
        </div>

        <form method="POST" id="programForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title"> Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Program Studi <span class="required">*</span></label>
                        <input type="text" id="nama" name="nama" class="form-input" required placeholder="Contoh: Pendidikan Matematika" value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Singkatan <span class="required">*</span></label>
                        <input type="text" id="singkatan" name="singkatan" class="form-input" required placeholder="Contoh: Pend. Mat." value="<?= sanitize($edit['singkatan'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenjang <span class="required">*</span></label>
                        <select id="jenjang" name="jenjang" class="form-select" required>
                            <?php foreach(['S1', 'S2', 'D3'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jenjang'] ?? 'S1') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Akreditasi</label>
                        <select id="akreditasi" name="akreditasi" class="form-select">
                            <?php foreach(['Unggul', 'Baik Sekali', 'Baik', 'C', 'Belum Terakreditasi'] as $a): ?>
                            <option value="<?= $a ?>" <?= ($edit['akreditasi'] ?? 'Belum Terakreditasi') === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi</div>
                <div class="form-group">
                    <label class="form-label">Deskripsi Program</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="5" placeholder="Jelaskan visi, misi, dan profil program studi..."><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan tampil di halaman publik</span>
                        <span><span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span> karakter</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Program' ?></span>
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
            <div class="preview-badge" id="previewBadge"> S1</div>
            <h2 class="preview-title" id="previewTitle">Nama program studi akan muncul di sini...</h2>
            <div class="preview-singkatan" id="previewSingkatan">-</div>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>🏅</span>
                    <strong id="previewAkreditasi">Belum Terakreditasi</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📊</span>
                    <strong id="previewStatus">✅ Aktif</strong>
                </div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Deskripsi:</div>
            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi program akan muncul di sini...</span>
            </div>
        </div>
    </div>
</div>

<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama program studi akan muncul di sini...';
    const singkatan = document.getElementById('singkatan').value || '-';
    const jenjang = document.getElementById('jenjang').value || 'S1';
    const akreditasi = document.getElementById('akreditasi').value || 'Belum Terakreditasi';
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.getElementById('status').value || 'Aktif';

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewSingkatan').textContent = singkatan;
    document.getElementById('previewAkreditasi').textContent = akreditasi;
    document.getElementById('previewStatus').textContent = (status === 'Aktif' ? '✅' : '❌') + ' ' + status;
    document.getElementById('previewBadge').textContent = '🎓 ' + jenjang;
    
    if (deskripsi) {
        document.getElementById('previewDesc').textContent = deskripsi;
        document.getElementById('previewDesc').classList.remove('preview-empty');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi program akan muncul di sini...</span>';
    }
}

document.getElementById('deskripsi').addEventListener('input', function() {
    document.getElementById('deskripsiCounter').textContent = this.value.length;
    triggerAutosave();
});

const storageKey = 'fkip_program_draft_<?= $id ?: "new" ?>';
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
            nama: document.getElementById('nama').value,
            singkatan: document.getElementById('singkatan').value,
            jenjang: document.getElementById('jenjang').value,
            akreditasi: document.getElementById('akreditasi').value,
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

<?php if (!$edit): ?>
(function() {
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (confirm(`Ada draft tersimpan dari ${new Date(data.saved_at).toLocaleString('id-ID')}. Muat draft tersebut?`)) {
                document.getElementById('nama').value = data.nama || '';
                document.getElementById('singkatan').value = data.singkatan || '';
                document.getElementById('jenjang').value = data.jenjang || 'S1';
                document.getElementById('akreditasi').value = data.akreditasi || 'Belum Terakreditasi';
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

['nama', 'singkatan', 'jenjang', 'akreditasi', 'deskripsi', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); });
});

document.getElementById('programForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        alert('Nama Program Studi wajib diisi!');
        return;
    }
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('programForm').submit();
    }
});

updatePreview();

console.log('%c🎓 Form Program Studi FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>