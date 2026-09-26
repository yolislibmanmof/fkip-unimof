<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM riset WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Ambil data untuk dropdown
$dosen_list = $pdo->query("SELECT id, nama FROM dosen WHERE status='Aktif' ORDER BY nama")->fetchAll();
$prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul']);
    $ketua_id = (int)($_POST['ketua_id'] ?? 0);
    $anggota = trim($_POST['anggota']);
    $jenis = $_POST['jenis'];
    $tahun = (int)$_POST['tahun'];
    $kategori = $_POST['kategori'];
    $program_studi_id = (int)($_POST['program_studi_id'] ?? 0);
    $jurnal = trim($_POST['jurnal']);
    $doi = trim($_POST['doi']);
    $abstrak = trim($_POST['abstrak']);
    $status = $_POST['status'];

    if ($edit) {
        $pdo->prepare("UPDATE riset SET judul=?, ketua_id=?, anggota=?, jenis=?, tahun=?, kategori=?, program_studi_id=?, jurnal=?, doi=?, abstrak=?, status=? WHERE id=?")
            ->execute([$judul, $ketua_id, $anggota, $jenis, $tahun, $kategori, $program_studi_id, $jurnal, $doi, $abstrak, $status, $id]);
        flash_message('success', '✅ Data riset berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO riset (judul, ketua_id, anggota, jenis, tahun, kategori, program_studi_id, jurnal, doi, abstrak, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$judul, $ketua_id, $anggota, $jenis, $tahun, $kategori, $program_studi_id, $jurnal, $doi, $abstrak, $status]);
        flash_message('success', '✅ Riset baru berhasil ditambahkan.');
    }
    header('Location: riset.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'riset';
$page_heading = $edit ? 'Edit Riset' : 'Tambah Riset';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Riset', 'riset.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #3b82f6, #2563eb); }
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
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: #dbeafe; color: #1e40af; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
.preview-meta { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
.preview-meta-item { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); }
.preview-meta-item strong { color: var(--text-primary); font-weight: 600; }
.preview-abstrak { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.9rem; line-height: 1.6; color: var(--text-secondary); border-left: 3px solid #3b82f6; min-height: 60px; }
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
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Riset</h2>
            <p>Isi detail publikasi atau kegiatan riset.</p>
        </div>

        <form method="POST" id="risetForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <div class="form-section">
                <div class="form-section-title">📄 Informasi Dasar</div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Judul Riset <span class="required">*</span></label>
                    <input type="text" id="judul" name="judul" class="form-input" required placeholder="Contoh: Implementasi AI dalam Pembelajaran" value="<?= sanitize($edit['judul'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ketua Peneliti <span class="required">*</span></label>
                        <select id="ketua_id" name="ketua_id" class="form-select" required>
                            <option value="">-- Pilih Dosen --</option>
                            <?php foreach ($dosen_list as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit['ketua_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= sanitize($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                <div class="form-group">
                    <label class="form-label">Anggota Peneliti</label>
                    <textarea id="anggota" name="anggota" class="form-textarea" rows="2" placeholder="Pisahkan dengan koma (Contoh: Dr. A, M.Pd., B, S.Pd.)"><?= sanitize($edit['anggota'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📊 Detail Riset</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jenis Riset <span class="required">*</span></label>
                        <select id="jenis" name="jenis" class="form-select" required>
                            <option value="Publikasi" <?= ($edit['jenis'] ?? '') === 'Publikasi' ? 'selected' : '' ?>>📄 Publikasi</option>
                            <option value="Hibah" <?= ($edit['jenis'] ?? '') === 'Hibah' ? 'selected' : '' ?>>💰 Hibah</option>
                            <option value="Pengabdian" <?= ($edit['jenis'] ?? '') === 'Pengabdian' ? 'selected' : '' ?>>🤝 Pengabdian</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tahun <span class="required">*</span></label>
                        <input type="number" id="tahun" name="tahun" class="form-input" required min="1900" max="2100" value="<?= $edit['tahun'] ?? date('Y') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select id="kategori" name="kategori" class="form-select">
                            <option value="Pendidikan" <?= ($edit['kategori'] ?? '') === 'Pendidikan' ? 'selected' : '' ?>>🎓 Pendidikan</option>
                            <option value="Teknologi Pendidikan" <?= ($edit['kategori'] ?? '') === 'Teknologi Pendidikan' ? 'selected' : '' ?>>💻 Teknologi Pendidikan</option>
                            <option value="Pengabdian" <?= ($edit['kategori'] ?? '') === 'Pengabdian' ? 'selected' : '' ?>>🤝 Pengabdian</option>
                            <option value="Lainnya" <?= ($edit['kategori'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>📌 Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Published" <?= ($edit['status'] ?? '') === 'Published' ? 'selected' : '' ?>>✅ Published</option>
                            <option value="Draft" <?= ($edit['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>📝 Draft</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Jurnal / Prosiding</label>
                        <input type="text" id="jurnal" name="jurnal" class="form-input" placeholder="Contoh: Jurnal Pendidikan Dasar" value="<?= sanitize($edit['jurnal'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">DOI (Opsional)</label>
                        <input type="text" id="doi" name="doi" class="form-input" placeholder="10.xxxx/xxxxx" value="<?= sanitize($edit['doi'] ?? '') ?>">
                        <div class="form-hint">Tanpa "https://doi.org/"</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">📝 Abstrak / Deskripsi</div>
                <div class="form-group">
                    <label class="form-label">Abstrak</label>
                    <textarea id="abstrak" name="abstrak" class="form-textarea" rows="5" placeholder="Tuliskan abstrak atau deskripsi singkat riset..."><?= sanitize($edit['abstrak'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Abstrak akan tampil di halaman publik</span>
                        <span><span id="abstrakCounter"><?= strlen($edit['abstrak'] ?? '') ?></span> karakter</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Riset' ?></span>
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
            <div class="preview-badge" id="previewBadge">📄 PENDIDIKAN</div>
            <h2 class="preview-title" id="previewTitle">Judul riset akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>👤</span>
                    <strong id="previewKetua">Ketua: -</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📅</span>
                    <strong id="previewTahun">Tahun: -</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📖</span>
                    <strong id="previewJurnal">Jurnal: -</strong>
                </div>
                <div class="preview-meta-item">
                    <span>📊</span>
                    <strong id="previewStatus">✅ Published</strong>
                </div>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem;">Abstrak:</div>
            <div class="preview-abstrak" id="previewAbstrak">
                <span class="preview-empty">Abstrak akan muncul di sini...</span>
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
    document.getElementById('previewTitle').textContent = document.getElementById('judul').value || 'Judul riset akan muncul di sini...';
    
    const ketuaSelect = document.getElementById('ketua_id');
    const ketuaName = ketuaSelect.options[ketuaSelect.selectedIndex]?.text || '-';
    document.getElementById('previewKetua').textContent = ketuaName !== '-- Pilih Dosen --' ? ketuaName : '-';
    
    document.getElementById('previewTahun').textContent = 'Tahun: ' + (document.getElementById('tahun').value || '-');
    
    const jurnal = document.getElementById('jurnal').value;
    const doi = document.getElementById('doi').value;
    let jurnalText = jurnal;
    if (doi) jurnalText += (jurnal ? ' | DOI: ' : 'DOI: ') + doi;
    document.getElementById('previewJurnal').textContent = jurnalText || 'Jurnal: -';
    
    document.getElementById('previewStatus').textContent = (document.getElementById('status').value === 'Published' ? '✅ ' : '📝 ') + document.getElementById('status').value;
    
    const kat = document.getElementById('kategori').value;
    document.getElementById('previewBadge').textContent = '📄 ' + kat.toUpperCase();
    
    const abstrak = document.getElementById('abstrak').value;
    if (abstrak) {
        document.getElementById('previewAbstrak').textContent = abstrak;
        document.getElementById('previewAbstrak').classList.remove('preview-empty');
    } else {
        document.getElementById('previewAbstrak').innerHTML = '<span class="preview-empty">Abstrak akan muncul di sini...</span>';
    }
}

document.getElementById('abstrak').addEventListener('input', function() {
    document.getElementById('abstrakCounter').textContent = this.value.length;
    triggerAutosave();
});

const storageKey = 'fkip_riset_draft_<?= $id ?: "new" ?>';
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
            ketua_id: document.getElementById('ketua_id').value,
            anggota: document.getElementById('anggota').value,
            jenis: document.getElementById('jenis').value,
            tahun: document.getElementById('tahun').value,
            kategori: document.getElementById('kategori').value,
            program_studi_id: document.getElementById('program_studi_id').value,
            jurnal: document.getElementById('jurnal').value,
            doi: document.getElementById('doi').value,
            abstrak: document.getElementById('abstrak').value,
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
                document.getElementById('judul').value = data.judul || '';
                document.getElementById('ketua_id').value = data.ketua_id || '';
                document.getElementById('anggota').value = data.anggota || '';
                document.getElementById('jenis').value = data.jenis || 'Publikasi';
                document.getElementById('tahun').value = data.tahun || new Date().getFullYear();
                document.getElementById('kategori').value = data.kategori || 'Pendidikan';
                document.getElementById('program_studi_id').value = data.program_studi_id || '';
                document.getElementById('jurnal').value = data.jurnal || '';
                document.getElementById('doi').value = data.doi || '';
                document.getElementById('abstrak').value = data.abstrak || '';
                document.getElementById('status').value = data.status || 'Published';
                updatePreview();
                document.getElementById('abstrakCounter').textContent = (data.abstrak || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

['judul', 'ketua_id', 'anggota', 'jenis', 'tahun', 'kategori', 'program_studi_id', 'jurnal', 'doi', 'abstrak', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); triggerAutosave(); });
});

document.getElementById('risetForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    if (!judul) {
        e.preventDefault();
        document.getElementById('judul').classList.add('error');
        alert('Judul riset wajib diisi!');
        return;
    }
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 500);
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('risetForm').submit();
    }
});

updatePreview();

console.log('%c🔬 Form Riset FKIP UNIMOF', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>