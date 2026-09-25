<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if (!$edit) {
        flash_message('error', 'Agenda tidak ditemukan.');
        header('Location: agenda.php');
        exit;
    }
}

// Check conflicts
$conflicts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tanggal_mulai'])) {
    $check_date = $_POST['tanggal_mulai'];
    $stmt = $pdo->prepare("SELECT id, judul FROM agenda WHERE tanggal_mulai = ? AND id != ?");
    $stmt->execute([$check_date, $id]);
    $conflicts = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $judul = trim($_POST['judul'] ?? '');
    $jenis = $_POST['jenis'] ?? 'umum';
    $status = $_POST['status'] ?? 'Aktif';
    $tgl_mulai = $_POST['tanggal_mulai'] ?? '';
    $tgl_selesai = $_POST['tanggal_selesai'] ?? null;
    $lokasi = trim($_POST['lokasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $reminder = (int)($_POST['reminder'] ?? 0);

    // Validasi
    if ($judul === '' || $tgl_mulai === '') {
        flash_message('error', 'Judul dan tanggal mulai wajib diisi.');
        header('Location: agenda-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    if ($edit) {
        $pdo->prepare("UPDATE agenda SET judul=?, jenis=?, tanggal_mulai=?, tanggal_selesai=?, lokasi=?, deskripsi=?, status=?, reminder=? WHERE id=?")
            ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $deskripsi, $status, $reminder, $id]);
        flash_message('success', '✅ Agenda berhasil diperbarui.');
    } else {
        $pdo->prepare("INSERT INTO agenda (judul, jenis, tanggal_mulai, tanggal_selesai, lokasi, deskripsi, status, reminder) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$judul, $jenis, $tgl_mulai, $tgl_selesai, $lokasi, $deskripsi, $status, $reminder]);
        flash_message('success', '✅ Agenda baru berhasil ditambahkan.');
    }
    header('Location: agenda.php');
    exit;
}

$csrf = generate_csrf_token();
$active_menu = 'agenda';
$page_heading = $edit ? 'Edit Agenda' : 'Tambah Agenda';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Kelola Agenda', 'agenda.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<!-- ===== SCOPED STYLES ===== -->
<style>
.agenda-form-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: start; }

/* Form Card */
.form-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
.form-card-premium::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--secondary)); }

.form-header-premium { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.form-header-premium h2 { font-family: var(--font-display); font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
.form-header-premium p { color: var(--text-muted); font-size: 0.9rem; }

/* Visual Type Selector */
.type-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
.type-option { position: relative; }
.type-option input { position: absolute; opacity: 0; pointer-events: none; }
.type-option label {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 1rem 0.5rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; text-align: center;
}
.type-option label:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.type-option input:checked + label { border-color: var(--primary); background: rgba(10,104,71,0.05); box-shadow: 0 4px 12px rgba(10,104,71,0.15); }
.type-icon { font-size: 1.75rem; }
.type-name { font-size: 0.8rem; font-weight: 700; color: var(--text-primary); }

/* Template Buttons */
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
.form-input-premium.error { border-color: #ef4444; }
.form-input-premium.error ~ .input-icon-premium { color: #ef4444; }
.error-message { color: #ef4444; font-size: 0.78rem; margin-top: 0.35rem; display: none; align-items: center; gap: 0.3rem; }
.error-message.show { display: flex; }

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

/* Duration Calculator */
.duration-calc { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: #166534; font-weight: 600; }
.duration-calc .duration-icon { font-size: 1.5rem; }

/* Conflict Warning */
.conflict-warning { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #fcd34d; border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem; display: none; align-items: flex-start; gap: 0.75rem; font-size: 0.85rem; color: #92400e; }
.conflict-warning.show { display: flex; }
.conflict-icon { font-size: 1.5rem; flex-shrink: 0; }
.conflict-list { margin-top: 0.5rem; padding-left: 1.25rem; font-size: 0.8rem; }

/* Status Selector */
.status-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.status-option { position: relative; }
.status-option input { position: absolute; opacity: 0; pointer-events: none; }
.status-option label {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.85rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: var(--radius-md); cursor: pointer; transition: all 0.3s; font-weight: 600; font-size: 0.85rem;
}
.status-option label:hover { transform: translateY(-2px); }
.status-option input:checked + label.status-aktif { background: #dcfce7; border-color: #10b981; color: #166534; }
.status-option input:checked + label.status-selesai { background: #dbeafe; border-color: #3b82f6; color: #1e40af; }
.status-option input:checked + label.status-dibatalkan { background: #fee2e2; border-color: #ef4444; color: #991b1b; }

/* Reminder Selector */
.reminder-selector { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.reminder-option { position: relative; }
.reminder-option input { position: absolute; opacity: 0; pointer-events: none; }
.reminder-option label {
    display: block; padding: 0.6rem 1rem; background: var(--bg-secondary); border: 2px solid var(--border);
    border-radius: 999px; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; font-weight: 600;
}
.reminder-option input:checked + label { background: var(--primary); color: white; border-color: var(--primary); }

/* Preview Card */
.preview-card-premium { background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-lg); position: sticky; top: 100px; }
.preview-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.preview-header h3 { font-family: var(--font-display); font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.preview-badge { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; }
.preview-title { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; min-height: 3rem; }
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

/* Responsive */
@media (max-width: 968px) {
    .agenda-form-layout { grid-template-columns: 1fr; }
    .preview-card-premium { position: static; order: -1; }
    .type-selector { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .form-card-premium, .preview-card-premium { padding: 1.5rem; }
    .status-selector { grid-template-columns: 1fr; }
}
</style>

<div class="agenda-form-layout">
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-premium" data-aos="fade-right">
        <div class="form-header-premium">
            <h2><?= $edit ? '️ Edit Agenda' : '➕ Agenda Baru' ?></h2>
            <p><?= $edit ? 'Perbarui detail kegiatan di bawah ini.' : 'Isi detail kegiatan untuk ditambahkan ke kalender.' ?></p>
        </div>

        <form method="POST" id="agendaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Judul -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="judul" name="judul" class="form-input-premium" placeholder=" " required maxlength="255" value="<?= sanitize($edit['judul'] ?? '') ?>">
                    <span class="input-icon-premium">📝</span>
                    <label class="floating-label-premium" for="judul">Judul Kegiatan *</label>
                </div>
                <div class="error-message" id="judulError">️ Judul wajib diisi</div>
            </div>

            <!-- Jenis Agenda (Visual Selector) -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">🎯 Jenis Agenda *</label>
                <div class="type-selector">
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-ujian" value="ujian" <?= ($edit['jenis'] ?? '') === 'ujian' ? 'checked' : '' ?>>
                        <label for="jenis-ujian"><span class="type-icon">📝</span><span class="type-name">Ujian</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-seminar" value="seminar" <?= ($edit['jenis'] ?? '') === 'seminar' ? 'checked' : '' ?>>
                        <label for="jenis-seminar"><span class="type-icon">🎤</span><span class="type-name">Seminar</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-wisuda" value="wisuda" <?= ($edit['jenis'] ?? '') === 'wisuda' ? 'checked' : '' ?>>
                        <label for="jenis-wisuda"><span class="type-icon">🎓</span><span class="type-name">Wisuda</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-libur" value="libur" <?= ($edit['jenis'] ?? '') === 'libur' ? 'checked' : '' ?>>
                        <label for="jenis-libur"><span class="type-icon">🏖️</span><span class="type-name">Libur</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-pmb" value="pmb" <?= ($edit['jenis'] ?? '') === 'pmb' ? 'checked' : '' ?>>
                        <label for="jenis-pmb"><span class="type-icon">📋</span><span class="type-name">PMB</span></label>
                    </div>
                    <div class="type-option">
                        <input type="radio" name="jenis" id="jenis-umum" value="umum" <?= ($edit['jenis'] ?? 'umum') === 'umum' ? 'checked' : '' ?>>
                        <label for="jenis-umum"><span class="type-icon">📌</span><span class="type-name">Umum</span></label>
                    </div>
                </div>
            </div>

            <!-- Quick Templates -->
            <div class="template-section">
                <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
                <div class="template-buttons">
                    <button type="button" class="template-btn" onclick="applyTemplate('uts')">📝 UTS Ganjil</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('uas')">📝 UAS Ganjil</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('wisuda')">🎓 Wisuda</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('seminar')">🎤 Seminar Nasional</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('libur')">️ Libur Semester</button>
                    <button type="button" class="template-btn" onclick="applyTemplate('pmb')">📋 Pendaftaran PMB</button>
                </div>
            </div>

            <!-- Tanggal -->
            <div class="form-row">
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input-premium" placeholder=" " required value="<?= $edit['tanggal_mulai'] ?? '' ?>" style="padding-left: 1rem;">
                        <label class="floating-label-premium" for="tanggal_mulai" style="left: 1rem;">Tanggal Mulai *</label>
                    </div>
                </div>
                <div class="form-group-premium">
                    <div class="input-wrapper-premium">
                        <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input-premium" placeholder=" " value="<?= $edit['tanggal_selesai'] ?? '' ?>" style="padding-left: 1rem;">
                        <label class="floating-label-premium" for="tanggal_selesai" style="left: 1rem;">Tanggal Selesai</label>
                    </div>
                </div>
            </div>

            <!-- Duration Calculator -->
            <div class="duration-calc" id="durationCalc" style="display: none;">
                <span class="duration-icon">️</span>
                <span id="durationText">Durasi: -</span>
            </div>

            <!-- Conflict Warning -->
            <div class="conflict-warning" id="conflictWarning">
                <span class="conflict-icon">⚠️</span>
                <div>
                    <strong>Ada agenda lain di tanggal ini!</strong>
                    <ul class="conflict-list" id="conflictList"></ul>
                </div>
            </div>

            <!-- Lokasi -->
            <div class="form-group-premium">
                <div class="input-wrapper-premium">
                    <input type="text" id="lokasi" name="lokasi" class="form-input-premium" placeholder=" " maxlength="255" value="<?= sanitize($edit['lokasi'] ?? '') ?>">
                    <span class="input-icon-premium"></span>
                    <label class="floating-label-premium" for="lokasi">Lokasi Kegiatan</label>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="form-group-premium">
                <div class="textarea-wrapper">
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea-premium" placeholder=" " maxlength="1000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <span class="char-counter-premium" id="charCounter">0/1000</span>
                </div>
            </div>

            <!-- Status -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">📊 Status *</label>
                <div class="status-selector">
                    <div class="status-option">
                        <input type="radio" name="status" id="status-aktif" value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'checked' : '' ?>>
                        <label for="status-aktif" class="status-aktif">✅ Aktif</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status-selesai" value="Selesai" <?= ($edit['status'] ?? '') === 'Selesai' ? 'checked' : '' ?>>
                        <label for="status-selesai" class="status-selesai">✔️ Selesai</label>
                    </div>
                    <div class="status-option">
                        <input type="radio" name="status" id="status-dibatalkan" value="Dibatalkan" <?= ($edit['status'] ?? '') === 'Dibatalkan' ? 'checked' : '' ?>>
                        <label for="status-dibatalkan" class="status-dibatalkan">❌ Dibatalkan</label>
                    </div>
                </div>
            </div>

            <!-- Reminder -->
            <div class="form-group-premium">
                <label class="form-label" style="margin-bottom: 0.75rem;">🔔 Pengingat</label>
                <div class="reminder-selector">
                    <div class="reminder-option">
                        <input type="radio" name="reminder" id="reminder-0" value="0" <?= ($edit['reminder'] ?? 0) == 0 ? 'checked' : '' ?>>
                        <label for="reminder-0">Tidak ada</label>
                    </div>
                    <div class="reminder-option">
                        <input type="radio" name="reminder" id="reminder-1" value="1" <?= ($edit['reminder'] ?? 0) == 1 ? 'checked' : '' ?>>
                        <label for="reminder-1">1 hari sebelum</label>
                    </div>
                    <div class="reminder-option">
                        <input type="radio" name="reminder" id="reminder-3" value="3" <?= ($edit['reminder'] ?? 0) == 3 ? 'checked' : '' ?>>
                        <label for="reminder-3">3 hari sebelum</label>
                    </div>
                    <div class="reminder-option">
                        <input type="radio" name="reminder" id="reminder-7" value="7" <?= ($edit['reminder'] ?? 0) == 7 ? 'checked' : '' ?>>
                        <label for="reminder-7">1 minggu sebelum</label>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="submit-btn-premium" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Agenda' : '✨ Simpan Agenda' ?></span>
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
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Pratinjau tampilan agenda</p>
        </div>

        <div id="previewContent">
            <div class="preview-badge" id="previewBadge" style="background: #f3f4f6; color: #4b5563;">📌 UMUM</div>
            <h2 class="preview-title" id="previewTitle">Judul agenda akan muncul di sini...</h2>
            
            <div class="preview-meta">
                <div class="preview-meta-item">
                    <span>📅</span>
                    <strong id="previewDate">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span></span>
                    <strong id="previewLocation">Belum diisi</strong>
                </div>
                <div class="preview-meta-item">
                    <span>⏱️</span>
                    <strong id="previewDuration">-</strong>
                </div>
                <div class="preview-meta-item">
                    <span></span>
                    <strong id="previewStatus">Aktif</strong>
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

<!-- ===== JAVASCRIPT ===== -->
<script>
// ===== TEMPLATES =====
const templates = {
    uts: { judul: 'Ujian Tengah Semester (UTS) Ganjil', jenis: 'ujian', deskripsi: 'Pelaksanaan Ujian Tengah Semester untuk seluruh program studi. Mahasiswa wajib membawa KTM dan mengikuti jadwal yang telah ditentukan.' },
    uas: { judul: 'Ujian Akhir Semester (UAS) Ganjil', jenis: 'ujian', deskripsi: 'Pelaksanaan Ujian Akhir Semester. Seluruh mahasiswa wajib mengikuti sesuai jadwal yang telah dipublikasikan.' },
    wisuda: { judul: 'Wisuda Periode I', jenis: 'wisuda', deskripsi: 'Upacara wisuda dan pelepasan sarjana baru FKIP UNIMOF. Undangan terbuka untuk keluarga dan tamu undangan.' },
    seminar: { judul: 'Seminar Nasional Pendidikan', jenis: 'seminar', deskripsi: 'Seminar nasional dengan tema pendidikan terkini. Narasumber dari berbagai universitas terkemuka di Indonesia.' },
    libur: { judul: 'Libur Antar Semester', jenis: 'libur', deskripsi: 'Masa libur antar semester. Tidak ada kegiatan perkuliahan.' },
    pmb: { judul: 'Pendaftaran Mahasiswa Baru Gelombang 1', jenis: 'pmb', deskripsi: 'Pembukaan pendaftaran mahasiswa baru FKIP UNIMOF. Pendaftaran dapat dilakukan secara online maupun offline.' }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;
    document.getElementById('judul').value = t.judul;
    document.querySelector(`input[name="jenis"][value="${t.jenis}"]`).checked = true;
    document.getElementById('deskripsi').value = t.deskripsi;
    updatePreview();
    updateCharCounter();
    triggerAutosave();
}

// ===== LIVE PREVIEW =====
const typeConfig = {
    ujian: { icon: '', color: '#dc2626', bg: '#fee2e2', label: 'UJIAN' },
    seminar: { icon: '🎤', color: '#2563eb', bg: '#dbeafe', label: 'SEMINAR' },
    wisuda: { icon: '🎓', color: '#d97706', bg: '#fef3c7', label: 'WISUDA' },
    libur: { icon: '🏖️', color: '#4338ca', bg: '#e0e7ff', label: 'LIBUR' },
    pmb: { icon: '', color: '#16a34a', bg: '#dcfce7', label: 'PMB' },
    umum: { icon: '📌', color: '#4b5563', bg: '#f3f4f6', label: 'UMUM' }
};

function updatePreview() {
    const judul = document.getElementById('judul').value || 'Judul agenda akan muncul di sini...';
    const jenis = document.querySelector('input[name="jenis"]:checked')?.value || 'umum';
    const tglMulai = document.getElementById('tanggal_mulai').value;
    const tglSelesai = document.getElementById('tanggal_selesai').value;
    const lokasi = document.getElementById('lokasi').value || 'Belum diisi';
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.querySelector('input[name="status"]:checked')?.value || 'Aktif';

    const config = typeConfig[jenis];
    const badge = document.getElementById('previewBadge');
    badge.textContent = `${config.icon} ${config.label}`;
    badge.style.background = config.bg;
    badge.style.color = config.color;

    document.getElementById('previewTitle').textContent = judul;
    document.getElementById('previewLocation').textContent = lokasi;
    document.getElementById('previewStatus').textContent = status;

    if (tglMulai) {
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const d = new Date(tglMulai);
        let dateStr = `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
        if (tglSelesai) {
            const d2 = new Date(tglSelesai);
            dateStr += ` - ${d2.getDate()} ${months[d2.getMonth()]} ${d2.getFullYear()}`;
        }
        document.getElementById('previewDate').textContent = dateStr;
    } else {
        document.getElementById('previewDate').textContent = '-';
    }

    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi akan muncul di sini...</span>';
    }
}

// ===== DURATION CALCULATOR =====
function calculateDuration() {
    const mulai = document.getElementById('tanggal_mulai').value;
    const selesai = document.getElementById('tanggal_selesai').value;
    const calc = document.getElementById('durationCalc');
    const text = document.getElementById('durationText');

    if (!mulai) {
        calc.style.display = 'none';
        return;
    }

    if (!selesai) {
        calc.style.display = 'flex';
        text.textContent = '⏱️ Durasi: 1 hari (sehari)';
        return;
    }

    const start = new Date(mulai);
    const end = new Date(selesai);
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

    if (diff < 0) {
        calc.style.display = 'flex';
        calc.style.background = 'linear-gradient(135deg, #fee2e2, #fecaca)';
        calc.style.borderColor = '#fca5a5';
        calc.style.color = '#991b1b';
        text.textContent = '⚠️ Tanggal selesai harus setelah tanggal mulai!';
    } else {
        calc.style.display = 'flex';
        calc.style.background = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
        calc.style.borderColor = '#86efac';
        calc.style.color = '#166534';
        text.textContent = `⏱️ Durasi: ${diff} hari`;
    }

    updatePreview();
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

// ===== CONFLICT DETECTOR =====
let conflictTimeout;
function checkConflicts() {
    const tgl = document.getElementById('tanggal_mulai').value;
    if (!tgl) return;

    clearTimeout(conflictTimeout);
    conflictTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`agenda-check-conflict.php?date=${tgl}&exclude=<?= $id ?>`);
            const data = await response.json();
            const warning = document.getElementById('conflictWarning');
            const list = document.getElementById('conflictList');

            if (data.conflicts && data.conflicts.length > 0) {
                list.innerHTML = data.conflicts.map(c => `<li>${c.judul}</li>`).join('');
                warning.classList.add('show');
            } else {
                warning.classList.remove('show');
            }
        } catch (e) {
            // Silent fail
        }
    }, 500);
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_agenda_draft_<?= $id ?: "new" ?>';
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
            jenis: document.querySelector('input[name="jenis"]:checked')?.value,
            tanggal_mulai: document.getElementById('tanggal_mulai').value,
            tanggal_selesai: document.getElementById('tanggal_selesai').value,
            lokasi: document.getElementById('lokasi').value,
            deskripsi: document.getElementById('deskripsi').value,
            status: document.querySelector('input[name="status"]:checked')?.value,
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
                if (data.jenis) document.querySelector(`input[name="jenis"][value="${data.jenis}"]`).checked = true;
                document.getElementById('tanggal_mulai').value = data.tanggal_mulai || '';
                document.getElementById('tanggal_selesai').value = data.tanggal_selesai || '';
                document.getElementById('lokasi').value = data.lokasi || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                if (data.status) document.querySelector(`input[name="status"][value="${data.status}"]`).checked = true;
                updatePreview();
                updateCharCounter();
                calculateDuration();
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['judul', 'tanggal_mulai', 'tanggal_selesai', 'lokasi', 'deskripsi'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
        updatePreview();
        triggerAutosave();
    });
});
document.getElementById('tanggal_mulai').addEventListener('change', calculateDuration);
document.getElementById('tanggal_selesai').addEventListener('change', calculateDuration);
document.getElementById('deskripsi').addEventListener('input', updateCharCounter);
document.querySelectorAll('input[name="jenis"]').forEach(r => r.addEventListener('change', updatePreview));
document.querySelectorAll('input[name="status"]').forEach(r => r.addEventListener('change', updatePreview));

// Conflict check on date change
document.getElementById('tanggal_mulai').addEventListener('change', checkConflicts);

// Form submit with loading
document.getElementById('agendaForm').addEventListener('submit', function(e) {
    const judul = document.getElementById('judul').value.trim();
    const tgl = document.getElementById('tanggal_mulai').value;
    
    if (!judul || !tgl) {
        e.preventDefault();
        if (!judul) document.getElementById('judul').classList.add('error');
        alert('Mohon lengkapi field yang wajib diisi.');
        return;
    }
    
    document.getElementById('submitBtn').classList.add('loading');
    document.getElementById('submitBtn').disabled = true;
    
    // Clear autosave on successful submit
    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// Keyboard shortcut: Ctrl+S
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('agendaForm').submit();
    }
});

// Initial preview
updatePreview();
updateCharCounter();
calculateDuration();

console.log('%c📅 Form Agenda FKIP UNIMOF', 'color: #0a6847; font-size: 16px; font-weight: bold;');
console.log('%cShortcut: Ctrl+S untuk simpan cepat', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>