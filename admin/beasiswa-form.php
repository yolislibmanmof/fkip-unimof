<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

// ✅ PERBAIKAN FATAL: Pisahkan prepare, execute, dan fetch
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM beasiswa WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama']); 
    $jenis = $_POST['jenis']; 
    $sumber = trim($_POST['sumber']);
    $nominal = trim($_POST['nominal']); 
    $syarat = trim($_POST['syarat']); 
    $deadline = $_POST['deadline'] ?: null;
    $status = $_POST['status'];

    if ($edit) {
        $pdo->prepare("UPDATE beasiswa SET nama=?, jenis=?, sumber=?, nominal=?, syarat=?, deadline=?, status=? WHERE id=?")
            ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deadline, $status, $id]);
        flash_message('success', '✅ Data beasiswa berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO beasiswa (nama, jenis, sumber, nominal, syarat, deadline, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deadline, $status]);
        flash_message('success', '✅ Program beasiswa baru berhasil ditambahkan.');
    }
    header('Location: beasiswa.php'); 
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'beasiswa'; 
$page_heading = $edit ? 'Edit Beasiswa' : 'Tambah Beasiswa';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Beasiswa', 'beasiswa.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM EXTREME LAYOUT ===== */
.form-layout-extreme { display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: start; }
.form-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-extreme::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #f59e0b, #d97706, #92400e); }
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
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,0.1); }
.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; }

/* ===== PREVIEW CARD ===== */
.preview-card-extreme { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-header p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }

.preview-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; margin-bottom: 1rem; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.preview-title { font-family: var(--font-display); font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
.preview-sumber { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }

.preview-nominal { background: linear-gradient(135deg, rgba(245,158,11,0.1), rgba(217,119,6,0.1)); border: 1px solid rgba(245,158,11,0.2); border-radius: var(--radius-md); padding: 1rem; text-align: center; margin-bottom: 1.5rem; }
.preview-nominal-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem; }
.preview-nominal-value { font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; color: #d97706; }

.preview-deadline { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; }
.preview-deadline.urgent { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.preview-deadline.safe { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

.preview-syarat { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap; background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); border-left: 3px solid #f59e0b; min-height: 80px; }
.preview-empty { color: var(--text-muted); font-style: italic; }

/* ===== SUBMIT & AUTOSAVE ===== */
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
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Beasiswa</h2>
            <p><?= $edit ? 'Perbarui detail program beasiswa di bawah ini.' : 'Isi detail program beasiswa untuk ditampilkan di website publik.' ?></p>
        </div>

        <form method="POST" id="beasiswaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">🎓 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Beasiswa <span class="required">*</span></label>
                        <input type="text" id="nama" name="nama" class="form-input" required placeholder="Contoh: Beasiswa Prestasi Akademik" value="<?= sanitize($edit['nama'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jenis Beasiswa <span class="required">*</span></label>
                        <select id="jenis" name="jenis" class="form-select" required>
                            <?php foreach(['Prestasi Akademik', 'KIP Kuliah', 'Muhammadiyahan', 'Talent Scouting', 'Lainnya'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Sumber / Pemberi Beasiswa</label>
                    <input type="text" id="sumber" name="sumber" class="form-input" placeholder="Contoh: Yayasan Muhammadiyah / Kemendikbud" value="<?= sanitize($edit['sumber'] ?? '') ?>">
                </div>
            </div>

            <!-- Section 2: Nominal & Deadline -->
            <div class="form-section">
                <div class="form-section-title">💰 Nominal & Jadwal</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nominal / Cakupan</label>
                        <input type="text" id="nominal" name="nominal" class="form-input" placeholder="Contoh: 100% SPP + Uang Saku Rp 500rb/bulan" value="<?= sanitize($edit['nominal'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deadline Pendaftaran</label>
                        <input type="date" id="deadline" name="deadline" class="form-input" value="<?= $edit['deadline'] ?? '' ?>">
                        <div class="form-hint">Kosongkan jika pendaftaran dibuka sepanjang tahun</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Terbuka" <?= ($edit['status'] ?? 'Terbuka') === 'Terbuka' ? 'selected' : '' ?>>✅ Terbuka</option>
                            <option value="Tertutup" <?= ($edit['status'] ?? '') === 'Tertutup' ? 'selected' : '' ?>>❌ Tertutup</option>
                        </select>
                    </div>
                </div>
                <!-- Deadline Calculator -->
                <div class="preview-deadline" id="deadlineCalc" style="display: none; margin-top: 1rem;">
                    <span style="font-size: 1.25rem;">⏰</span>
                    <span id="deadlineCalcText">-</span>
                </div>
            </div>

            <!-- Section 3: Syarat -->
            <div class="form-section">
                <div class="form-section-title">📋 Syarat & Ketentuan</div>
                <div class="form-group">
                    <label class="form-label">Detail Syarat <span class="required">*</span></label>
                    <textarea id="syarat" name="syarat" class="form-textarea" required placeholder="1. ...&#10;2. ..."><?= sanitize($edit['syarat'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Pisahkan setiap syarat dengan baris baru (Enter)</span>
                        <span><span id="syaratCounter"><?= strlen($edit['syarat'] ?? '') ?></span> karakter</span>
                    </div>
                </div
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Beasiswa' ?></span>
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
            <div class="preview-badge" id="previewBadge">🎓 PRESTASI AKADEMIK</div>
            <h2 class="preview-title" id="previewTitle">Nama beasiswa akan muncul di sini...</h2>
            <div class="preview-sumber">
                <span>🏛️</span>
                <span id="previewSumber">-</span>
            </div>
            
            <div class="preview-nominal">
                <div class="preview-nominal-label">Cakupan Beasiswa</div>
                <div class="preview-nominal-value" id="previewNominal">-</div>
            </div>

            <div class="preview-deadline safe" id="previewDeadlineBox" style="display: none;">
                <span style="font-size: 1.25rem;">⏰</span>
                <span id="previewDeadlineText">-</span>
            </div>

            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Syarat & Ketentuan:</div>
            <div class="preview-syarat" id="previewSyarat">
                <span class="preview-empty">Detail syarat akan muncul di sini...</span>
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
// ===== LIVE PREVIEW =====
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama beasiswa akan muncul di sini...';
    const jenis = document.getElementById('jenis').value || 'Lainnya';
    const sumber = document.getElementById('sumber').value || '-';
    const nominal = document.getElementById('nominal').value || '-';
    const syarat = document.getElementById('syarat').value;
    const deadline = document.getElementById('deadline').value;
    const status = document.getElementById('status').value;

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewBadge').textContent = '🎓 ' + jenis.toUpperCase();
    document.getElementById('previewSumber').textContent = sumber;
    document.getElementById('previewNominal').textContent = nominal;

    if (syarat) {
        document.getElementById('previewSyarat').textContent = syarat;
        document.getElementById('previewSyarat').classList.remove('preview-empty');
    } else {
        document.getElementById('previewSyarat').innerHTML = '<span class="preview-empty">Detail syarat akan muncul di sini...</span>';
    }

    // Deadline Logic
    const deadlineBox = document.getElementById('previewDeadlineBox');
    const deadlineText = document.getElementById('previewDeadlineText');
    
    if (deadline && status === 'Terbuka') {
        const days = Math.ceil((new Date(deadline) - new Date()) / (1000 * 60 * 60 * 24));
        deadlineBox.style.display = 'flex';
        if (days < 0) {
            deadlineBox.className = 'preview-deadline urgent';
            deadlineText.textContent = `Sudah kadaluarsa ${Math.abs(days)} hari yang lalu`;
        } else if (days === 0) {
            deadlineBox.className = 'preview-deadline urgent';
            deadlineText.textContent = 'Deadline hari ini!';
        } else if (days <= 14) {
            deadlineBox.className = 'preview-deadline urgent';
            deadlineText.textContent = `Segera! Hanya ${days} hari lagi`;
        } else {
            deadlineBox.className = 'preview-deadline safe';
            deadlineText.textContent = `Dibuka hingga ${new Date(deadline).toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'})} (${days} hari lagi)`;
        }
    } else if (status === 'Tertutup') {
        deadlineBox.style.display = 'flex';
        deadlineBox.className = 'preview-deadline urgent';
        deadlineText.textContent = 'Pendaftaran saat ini ditutup';
    } else {
        deadlineBox.style.display = 'none';
    }
}

// ===== DEADLINE CALCULATOR (Form Side) =====
function calculateDeadline() {
    const deadline = document.getElementById('deadline').value;
    const status = document.getElementById('status').value;
    const calc = document.getElementById('deadlineCalc');
    const text = document.getElementById('deadlineCalcText');

    if (!deadline || status === 'Tertutup') {
        calc.style.display = 'none';
        return;
    }

    const days = Math.ceil((new Date(deadline) - new Date()) / (1000 * 60 * 60 * 24));
    calc.style.display = 'flex';
    calc.classList.remove('urgent', 'safe');

    if (days < 0) {
        calc.classList.add('urgent');
        text.textContent = `⚠️ Sudah kadaluarsa ${Math.abs(days)} hari yang lalu!`;
    } else if (days === 0) {
        calc.classList.add('urgent');
        text.textContent = '⚠️ Deadline hari ini!';
    } else if (days <= 14) {
        calc.classList.add('urgent');
        text.textContent = `⏰ Segera! Hanya ${days} hari lagi`;
    } else {
        calc.classList.add('safe');
        text.textContent = `✅ Masih ${days} hari sebelum deadline`;
    }
}

// ===== CHARACTER COUNTER =====
document.getElementById('syarat').addEventListener('input', function() {
    document.getElementById('syaratCounter').textContent = this.value.length;
    triggerAutosave();
});

// ===== AUTOSAVE =====
const storageKey = 'fkip_beasiswa_draft_<?= $id ?: "new" ?>';
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
            jenis: document.getElementById('jenis').value,
            sumber: document.getElementById('sumber').value,
            nominal: document.getElementById('nominal').value,
            syarat: document.getElementById('syarat').value,
            deadline: document.getElementById('deadline').value,
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
                document.getElementById('jenis').value = data.jenis || 'Lainnya';
                document.getElementById('sumber').value = data.sumber || '';
                document.getElementById('nominal').value = data.nominal || '';
                document.getElementById('syarat').value = data.syarat || '';
                document.getElementById('deadline').value = data.deadline || '';
                document.getElementById('status').value = data.status || 'Terbuka';
                
                updatePreview();
                calculateDeadline();
                document.getElementById('syaratCounter').textContent = (data.syarat || '').length;
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama', 'jenis', 'sumber', 'nominal', 'syarat', 'deadline', 'status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); calculateDeadline(); triggerAutosave(); });
    document.getElementById(id).addEventListener('change', () => { updatePreview(); calculateDeadline(); triggerAutosave(); });
});

// Form submit with loading
document.getElementById('beasiswaForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        alert('Nama Beasiswa wajib diisi!');
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
        document.getElementById('beasiswaForm').submit();
    }
});

// Initial preview
updatePreview();
calculateDeadline();

console.log('%c🎓 Form Beasiswa FKIP UNIMOF', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>