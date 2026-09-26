<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM beasiswa WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM beasiswa WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        flash_message('info', '📋 Menduplikasi beasiswa: ' . htmlspecialchars($source['nama']));
    }
}

// Ambil data untuk autocomplete (sumber beasiswa existing)
$autocomplete_data = [
    'sumber' => []
];
try {
    $autocomplete_data['sumber'] = $pdo->query("SELECT DISTINCT sumber FROM beasiswa WHERE sumber IS NOT NULL AND sumber != '' ORDER BY sumber LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama'] ?? '');
    $jenis = $_POST['jenis'] ?? 'Lainnya';
    $sumber = trim($_POST['sumber'] ?? '');
    $nominal = trim($_POST['nominal'] ?? '');
    $syarat = trim($_POST['syarat'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $link_pendaftaran = trim($_POST['link_pendaftaran'] ?? '');
    $deadline = $_POST['deadline'] ?: null;
    $status = $_POST['status'] ?? 'Terbuka';

    // Validasi dasar
    if ($nama === '') {
        flash_message('error', '❌ Nama beasiswa wajib diisi.');
        header('Location: beasiswa-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi deadline tidak boleh di masa lalu (kecuali status Tertutup)
    if ($deadline && $status === 'Terbuka' && strtotime($deadline) < strtotime('-1 day')) {
        flash_message('warning', '⚠️ Deadline sudah lewat. Status otomatis diubah ke Tertutup.');
        $status = 'Tertutup';
    }

    // Normalisasi URL link pendaftaran
    if ($link_pendaftaran && !preg_match('~^https?://~', $link_pendaftaran)) {
        $link_pendaftaran = 'https://' . $link_pendaftaran;
    }

    try {
        if ($edit && $id > 0) {
            $pdo->prepare("UPDATE beasiswa SET nama=?, jenis=?, sumber=?, nominal=?, syarat=?, deskripsi=?, link_pendaftaran=?, deadline=?, status=? WHERE id=?")
                ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deskripsi, $link_pendaftaran, $deadline, $status, $id]);
            flash_message('success', '✅ Data beasiswa berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO beasiswa (nama, jenis, sumber, nominal, syarat, deskripsi, link_pendaftaran, deadline, status) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$nama, $jenis, $sumber, $nominal, $syarat, $deskripsi, $link_pendaftaran, $deadline, $status]);
            flash_message('success', '✅ Program beasiswa baru berhasil ditambahkan.');
        }
        header('Location: beasiswa.php');
        exit;
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: beasiswa-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
}

$csrf = generate_csrf_token();
$active_menu = 'beasiswa';
$page_heading = $edit ? 'Edit Beasiswa' : 'Tambah Beasiswa';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Beasiswa', 'beasiswa.php'], [$page_heading, null]];

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

/* ===== PROGRESS INDICATOR ===== */
.progress-indicator {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
}
.progress-bar-wrap {
    flex: 1;
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 999px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #10b981 100%);
    transition: width 0.4s ease;
    border-radius: 999px;
}
.progress-stats {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
}
.progress-percent {
    color: #f59e0b;
    font-weight: 800;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}
.progress-label { color: var(--text-muted); }

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
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f59e0b, #d97706, #92400e);
}

.form-header-extreme {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.form-header-extreme h2 {
    font-family: var(--font-display);
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.form-header-extreme p { color: var(--text-muted); font-size: 0.9rem; }

/* ===== QUICK TEMPLATES ===== */
.template-section {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.template-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.template-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.template-btn {
    padding: 0.5rem 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.template-btn:hover {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-color: #f59e0b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245,158,11,0.25);
}

/* ===== FORM SECTIONS ===== */
.form-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}
.form-section:focus-within { border-color: #f59e0b; }
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
.form-group { margin-bottom: 0; position: relative; }
.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.form-label .required { color: #ef4444; margin-left: 0.15rem; }
.form-label .label-icon {
    width: 18px; height: 18px;
    background: rgba(245,158,11,0.1);
    color: #f59e0b;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
}

.form-input, .form-select, .form-textarea {
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
.form-textarea { resize: vertical; min-height: 120px; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245,158,11,0.1);
}
.form-input.error, .form-select.error {
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239,68,68,0.1);
    animation: shake 0.4s;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
.form-input.success { border-color: #10b981; }
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.form-hint.error { color: #ef4444; font-weight: 600; }
.form-hint.success { color: #10b981; }
.form-hint.warning { color: #f59e0b; }

/* ===== AUTOCOMPLETE ===== */
.autocomplete-wrapper { position: relative; }
.autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0; right: 0;
    margin-top: 0.25rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    max-height: 220px;
    overflow-y: auto;
    z-index: 50;
    display: none;
}
.autocomplete-list.show { display: block; }
.autocomplete-item {
    padding: 0.6rem 1rem;
    cursor: pointer;
    transition: background 0.15s;
    font-size: 0.88rem;
    border-bottom: 1px solid var(--border);
}
.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover, .autocomplete-item.active { background: var(--bg-secondary); }

/* ===== NOMINAL HELPER ===== */
.nominal-helper {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    display: none;
}
.nominal-helper.show { display: block; }
.nominal-helper strong { font-weight: 700; }

/* ===== DEADLINE CALCULATOR (Enhanced) ===== */
.deadline-calc {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: var(--radius-md);
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.88rem;
    font-weight: 600;
    animation: slideInUp 0.3s;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.deadline-calc.show { display: flex; }
.deadline-calc.safe {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border: 1px solid #86efac;
    color: #166534;
}
.deadline-calc.warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    color: #92400e;
}
.deadline-calc.danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border: 1px solid #fca5a5;
    color: #991b1b;
    animation: slideInUp 0.3s, pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.3); }
    50% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
}

/* Deadline detailed breakdown */
.deadline-breakdown {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    display: none;
    gap: 1rem;
    flex-wrap: wrap;
}
.deadline-breakdown.show { display: flex; }
.deadline-unit {
    flex: 1;
    min-width: 60px;
    text-align: center;
    padding: 0.5rem;
    background: var(--bg-primary);
    border-radius: 8px;
    border: 1px solid var(--border);
}
.deadline-unit-value {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.deadline-unit-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-top: 0.25rem;
    font-weight: 600;
}

/* ===== SMART SYARAT BUILDER ===== */
.syarat-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.syarat-builder-header {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.syarat-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.syarat-chip {
    padding: 0.35rem 0.7rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.syarat-chip:hover {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
    transform: translateY(-1px);
}

/* ===== LINK VALIDATOR ===== */
.link-validator {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.link-validator.show { display: flex; }
.link-validator.valid {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.link-validator.invalid {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* ===== PREVIEW CARD EXTREME ===== */
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.preview-header h3 {
    font-family: var(--font-display);
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.preview-actions { display: flex; gap: 0.4rem; }
.preview-action-btn {
    width: 34px; height: 34px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    text-decoration: none;
    color: var(--text-secondary);
}
.preview-action-btn:hover {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
    transform: translateY(-2px);
}

/* Scholarship Card Preview */
.scholarship-card-preview {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 2px solid #fbbf24;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 1rem;
}
.scholarship-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #fbbf24, #f59e0b, #fbbf24);
}
.scholarship-card-preview::after {
    content: '';
    position: absolute;
    top: -30%;
    right: -15%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(251,191,36,0.2) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.scholarship-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(245,158,11,0.15);
    color: #92400e;
    border: 1px solid rgba(245,158,11,0.3);
    margin-bottom: 0.75rem;
    position: relative;
    z-index: 1;
}

.scholarship-title {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    position: relative;
    z-index: 1;
}

.scholarship-sumber {
    font-size: 0.85rem;
    color: #92400e;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    position: relative;
    z-index: 1;
}

.scholarship-nominal {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    text-align: center;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
}
.scholarship-nominal-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    opacity: 0.9;
    margin-bottom: 0.25rem;
    font-weight: 700;
}
.scholarship-nominal-value {
    font-family: var(--font-display);
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.3;
}

.scholarship-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    position: relative;
    z-index: 1;
}
.scholarship-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: #78350f;
}
.scholarship-meta-item strong {
    font-weight: 700;
    color: #92400e;
}

/* Deadline display in preview */
.scholarship-deadline {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.75rem;
    background: white;
    border-radius: var(--radius-md);
    margin-top: 1rem;
    border: 1px solid rgba(245,158,11,0.3);
    position: relative;
    z-index: 1;
}
.scholarship-deadline.safe {
    background: #dcfce7;
    border-color: #86efac;
    color: #166534;
}
.scholarship-deadline.urgent {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
    animation: pulse 2s infinite;
}

.scholarship-deadline-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}
.scholarship-deadline-info { flex: 1; min-width: 0; }
.scholarship-deadline-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    opacity: 0.7;
    margin-bottom: 0.15rem;
}
.scholarship-deadline-text {
    font-weight: 700;
    font-size: 0.88rem;
}

/* Syarat list in preview */
.preview-syarat-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.preview-syarat-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.preview-syarat-list {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: syarat;
}
.preview-syarat-list li {
    counter-increment: syarat;
    padding: 0.5rem 0 0.5rem 2rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
    position: relative;
    border-bottom: 1px dashed var(--border);
}
.preview-syarat-list li:last-child { border-bottom: none; }
.preview-syarat-list li::before {
    content: counter(syarat);
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 22px;
    height: 22px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
}
.preview-syarat-empty {
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    color: var(--text-muted);
    font-style: italic;
    text-align: center;
    font-size: 0.85rem;
}

/* Share preview */
.share-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.share-preview-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.share-preview-qr {
    display: inline-block;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
}
.share-preview-qr img { width: 100px; height: 100px; }

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
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
    margin-top: 1rem;
}
.submit-btn-extreme:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(245,158,11,0.35);
}
.submit-btn-extreme:disabled { opacity: 0.7; cursor: not-allowed; }
.submit-btn-extreme .spinner {
    width: 20px; height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: none;
}
.submit-btn-extreme.loading .spinner { display: inline-block; }
.submit-btn-extreme.loading .btn-text { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Secondary Actions */
.secondary-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}
.secondary-btn {
    flex: 1;
    padding: 0.75rem;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    text-decoration: none;
    min-width: 120px;
}
.secondary-btn:hover {
    background: var(--bg-tertiary);
    border-color: #f59e0b;
    color: #f59e0b;
    transform: translateY(-1px);
}

/* Shortcuts Legend */
.shortcuts-legend {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}
.shortcuts-legend kbd {
    background: var(--bg-secondary);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.72rem;
    border: 1px solid var(--border);
    margin: 0 0.15rem;
}

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
    .template-buttons { flex-direction: column; }
    .shortcuts-legend { font-size: 0.7rem; }
}
</style>

<!-- Progress Indicator -->
<div class="progress-indicator" data-aos="fade-down">
    <span style="font-size: 1.25rem;">📊</span>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" id="progressBar" style="width: 0%"></div>
    </div>
    <div class="progress-stats">
        <span class="progress-percent" id="progressPercent">0%</span>
        <span class="progress-label">Kelengkapan</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Program Beasiswa</h2>
            <p><?= $edit ? 'Perbarui detail program beasiswa di bawah ini.' : 'Isi detail program beasiswa untuk ditampilkan di website publik.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('kip')">💰 KIP Kuliah</button>
                <button type="button" class="template-btn" onclick="applyTemplate('prestasi')">🏆 Prestasi Akademik</button>
                <button type="button" class="template-btn" onclick="applyTemplate('muhammadiyah')">🕌 Muhammadiyah</button>
                <button type="button" class="template-btn" onclick="applyTemplate('talent')">🌟 Talent Scouting</button>
                <button type="button" class="template-btn" onclick="applyTemplate('umum')">📌 Beasiswa Umum</button>
            </div>
        </div>

        <form method="POST" id="beasiswaForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">🎓 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📝</span>
                            Nama Beasiswa <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Beasiswa Prestasi Akademik"
                               value="<?= sanitize($edit['nama'] ?? '') ?>"
                               maxlength="150">
                        <div class="form-hint"><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/150 karakter</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏆</span>
                            Jenis Beasiswa <span class="required">*</span>
                        </label>
                        <select id="jenis" name="jenis" class="form-select" required>
                            <?php foreach(['Prestasi Akademik', 'KIP Kuliah', 'Muhammadiyah', 'Talent Scouting', 'Lainnya'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '' ?>>
                                <?= $j === 'Prestasi Akademik' ? '🏆' : ($j === 'KIP Kuliah' ? '💰' : ($j === 'Muhammadiyah' ? '🕌' : ($j === 'Talent Scouting' ? '🌟' : '📌'))) ?>
                                <?= $j ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group autocomplete-wrapper">
                    <label class="form-label">
                        <span class="label-icon">🏛️</span>
                        Sumber / Pemberi Beasiswa
                    </label>
                    <input type="text" id="sumber" name="sumber" class="form-input"
                           placeholder="Contoh: Yayasan Muhammadiyah / Kemendikbud"
                           value="<?= sanitize($edit['sumber'] ?? '') ?>"
                           autocomplete="off">
                    <div class="autocomplete-list" id="sumberAutocomplete">
                        <?php foreach ($autocomplete_data['sumber'] as $s): ?>
                        <div class="autocomplete-item" data-value="<?= sanitize($s) ?>"><?= sanitize($s) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint">Pilih dari daftar atau ketik sumber baru</div>
                </div>
            </div>

            <!-- Section 2: Nominal & Deadline -->
            <div class="form-section">
                <div class="form-section-title">💰 Nominal & Jadwal</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">💵</span>
                            Nominal / Cakupan
                        </label>
                        <input type="text" id="nominal" name="nominal" class="form-input"
                               placeholder="Contoh: 100% SPP + Uang Saku Rp 500rb/bulan"
                               value="<?= sanitize($edit['nominal'] ?? '') ?>"
                               maxlength="200">
                        <div class="nominal-helper" id="nominalHelper">
                            💡 Contoh: <strong>Rp 5.000.000</strong>, <strong>100% SPP</strong>, <strong>Full biaya kuliah + asrama</strong>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Deadline Pendaftaran
                        </label>
                        <input type="date" id="deadline" name="deadline" class="form-input"
                               value="<?= $edit['deadline'] ?? '' ?>"
                               min="<?= date('Y-m-d') ?>">
                        <div class="form-hint">Kosongkan jika pendaftaran dibuka sepanjang tahun</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Terbuka" <?= ($edit['status'] ?? 'Terbuka') === 'Terbuka' ? 'selected' : '' ?>>✅ Terbuka</option>
                            <option value="Tertutup" <?= ($edit['status'] ?? '') === 'Tertutup' ? 'selected' : '' ?>>🔒 Tertutup</option>
                        </select>
                        <div class="form-hint" id="statusHint">💡 Status otomatis "Tertutup" jika deadline sudah lewat</div>
                    </div>
                </div>

                <!-- Deadline Calculator (Enhanced) -->
                <div class="deadline-calc" id="deadlineCalc">
                    <span style="font-size: 1.5rem;">⏰</span>
                    <span id="deadlineCalcText">-</span>
                </div>
                <div class="deadline-breakdown" id="deadlineBreakdown">
                    <div class="deadline-unit">
                        <div class="deadline-unit-value" id="dl-days">-</div>
                        <div class="deadline-unit-label">Hari</div>
                    </div>
                    <div class="deadline-unit">
                        <div class="deadline-unit-value" id="dl-weeks">-</div>
                        <div class="deadline-unit-label">Minggu</div>
                    </div>
                    <div class="deadline-unit">
                        <div class="deadline-unit-value" id="dl-months">-</div>
                        <div class="deadline-unit-label">Bulan</div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Syarat & Ketentuan -->
            <div class="form-section">
                <div class="form-section-title">📋 Syarat & Ketentuan</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Detail Syarat <span class="required">*</span>
                    </label>
                    <textarea id="syarat" name="syarat" class="form-textarea" required
                              placeholder="1. Mahasiswa aktif FKIP UNIMOF&#10;2. IPK minimal 3.00&#10;3. Tidak sedang menerima beasiswa lain"
                              maxlength="5000"><?= sanitize($edit['syarat'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Pisahkan setiap syarat dengan baris baru (Enter)</span>
                        <span><span id="syaratCounter"><?= strlen($edit['syarat'] ?? '') ?></span>/5000 karakter</span>
                    </div>
                </div>

                <!-- Smart Syarat Builder -->
                <div class="syarat-builder">
                    <div class="syarat-builder-header">💡 Klik untuk menambahkan syarat umum:</div>
                    <div class="syarat-chips">
                        <span class="syarat-chip" onclick="addSyarat('Mahasiswa aktif FKIP UNIMOF')">+ Mahasiswa aktif</span>
                        <span class="syarat-chip" onclick="addSyarat('IPK minimal 3.00')">+ IPK ≥ 3.00</span>
                        <span class="syarat-chip" onclick="addSyarat('Tidak sedang menerima beasiswa lain')">+ Tidak terima beasiswa lain</span>
                        <span class="syarat-chip" onclick="addSyarat('Maksimal semester 8')">+ Maks semester 8</span>
                        <span class="syarat-chip" onclick="addSyarat('Aktif dalam organisasi kampus')">+ Aktif organisasi</span>
                        <span class="syarat-chip" onclick="addSyarat('Bersedia mengikuti program pendampingan')">+ Bersedia pendampingan</span>
                        <span class="syarat-chip" onclick="addSyarat('Memiliki prestasi akademik/non-akademik')">+ Berprestasi</span>
                        <span class="syarat-chip" onclick="addSyarat('Lulus seleksi administrasi dan wawancara')">+ Lulus seleksi</span>
                    </div>
                </div>
            </div>

            <!-- Section 4: Deskripsi & Link (NEW!) -->
            <div class="form-section">
                <div class="form-section-title">📖 Deskripsi & Link</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📄</span>
                        Deskripsi Lengkap (Opsional)
                    </label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="3"
                              placeholder="Deskripsi detail tentang program beasiswa ini..."
                              maxlength="3000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Informasi tambahan tentang program</span>
                        <span><span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span>/3000 karakter</span>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🔗</span>
                        Link Pendaftaran
                    </label>
                    <input type="url" id="link_pendaftaran" name="link_pendaftaran" class="form-input"
                           placeholder="https://example.com/daftar-beasiswa"
                           value="<?= sanitize($edit['link_pendaftaran'] ?? '') ?>">
                    <div class="link-validator" id="linkValidator"></div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Beasiswa' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="beasiswa.php" class="secondary-btn">← Kembali</a>
                <button type="reset" class="secondary-btn" onclick="return confirm('Reset semua field?')">🔄 Reset</button>
            </div>

            <!-- Shortcuts Legend -->
            <div class="shortcuts-legend">
                <span>💡 Shortcuts:</span>
                <span><kbd>Ctrl</kbd>+<kbd>S</kbd> Simpan</span>
                <span><kbd>Ctrl</kbd>+<kbd>K</kbd> Template</span>
                <span><kbd>Esc</kbd> Batal</span>
            </div>
        </form>
    </div>

    <!-- RIGHT: LIVE PREVIEW -->
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <div>
                <h3>👁️ Live Preview</h3>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu beasiswa</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="beasiswa.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Scholarship Card Preview -->
        <div class="scholarship-card-preview">
            <div class="scholarship-badge" id="previewBadge">🎓 PRESTASI AKADEMIK</div>
            <h2 class="scholarship-title" id="previewTitle">Nama Beasiswa</h2>
            <div class="scholarship-sumber">
                <span>🏛️</span>
                <span id="previewSumber">Sumber Beasiswa</span>
            </div>

            <div class="scholarship-nominal">
                <div class="scholarship-nominal-label">💰 Cakupan Beasiswa</div>
                <div class="scholarship-nominal-value" id="previewNominal">-</div>
            </div>

            <div class="scholarship-deadline safe" id="previewDeadlineBox" style="display: none;">
                <span class="scholarship-deadline-icon">⏰</span>
                <div class="scholarship-deadline-info">
                    <div class="scholarship-deadline-label">Deadline Pendaftaran</div>
                    <div class="scholarship-deadline-text" id="previewDeadlineText">-</div>
                </div>
            </div>

            <div class="scholarship-meta" id="previewStatusMeta" style="margin-top: 0.75rem; display: none;">
                <div class="scholarship-meta-item">
                    <span>📊</span>
                    <span>Status: <strong id="previewStatus">-</strong></span>
                </div>
            </div>
        </div>

        <!-- Syarat List -->
        <div class="preview-syarat-section">
            <div class="preview-syarat-title">
                <span>📋</span>
                <span>Syarat & Ketentuan</span>
            </div>
            <div id="previewSyarat">
                <div class="preview-syarat-empty">Detail syarat akan muncul di sini...</div>
            </div>
        </div>

        <!-- Share QR -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Share</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Beasiswa" alt="QR">
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
// ===== DATA untuk autocomplete =====
const autocompleteData = <?= json_encode($autocomplete_data) ?>;

// ===== TEMPLATES =====
const templates = {
    kip: {
        nama: 'Beasiswa KIP Kuliah',
        jenis: 'KIP Kuliah',
        sumber: 'Kemendikbud Ristek',
        nominal: 'Rp 2.400.000/semester + Biaya UKT',
        syarat: '1. Mahasiswa dari keluarga kurang mampu\n2. Terdaftar di PDDikti\n3. IPK minimal 2.75\n4. Tidak sedang menerima beasiswa lain\n5. Bersedia mengikuti program pembinaan',
        status: 'Terbuka'
    },
    prestasi: {
        nama: 'Beasiswa Prestasi Akademik',
        jenis: 'Prestasi Akademik',
        sumber: 'Yayasan Pendidikan',
        nominal: 'Rp 5.000.000/tahun',
        syarat: '1. Mahasiswa aktif FKIP UNIMOF\n2. IPK minimal 3.50\n3. Maksimal semester 8\n4. Memiliki prestasi akademik/non-akademik\n5. Tidak sedang cuti akademik',
        status: 'Terbuka'
    },
    muhammadiyah: {
        nama: 'Beasiswa Muhammadiyah',
        jenis: 'Muhammadiyah',
        sumber: 'Pimpinan Pusat Muhammadiyah',
        nominal: '50% Biaya Kuliah',
        syarat: '1. Mahasiswa aktif FKIP UNIMOF\n2. Beragama Islam\n3. Aktif dalam kegiatan Muhammadiyah\n4. IPK minimal 3.00\n5. Tidak sedang menerima beasiswa lain',
        status: 'Terbuka'
    },
    talent: {
        nama: 'Beasiswa Talent Scouting',
        jenis: 'Talent Scouting',
        sumber: 'Universitas',
        nominal: '100% Biaya Kuliah + Uang Saku',
        syarat: '1. Lulusan SMA/sederajat tahun ini\n2. Memiliki prestasi tingkat nasional/internasional\n3. Lulus seleksi masuk UNIMOF\n4. Bersedia mengikuti program pembinaan\n5. Bersedia menjadi duta kampus',
        status: 'Terbuka'
    },
    umum: {
        nama: 'Beasiswa Bantuan Studi',
        jenis: 'Lainnya',
        sumber: 'Donatur',
        nominal: 'Rp 3.000.000/semester',
        syarat: '1. Mahasiswa aktif FKIP UNIMOF\n2. IPK minimal 2.75\n3. Berasal dari keluarga kurang mampu\n4. Tidak sedang menerima beasiswa lain',
        status: 'Terbuka'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    if (t.nama) document.getElementById('nama').value = t.nama;
    if (t.jenis) document.getElementById('jenis').value = t.jenis;
    if (t.sumber) document.getElementById('sumber').value = t.sumber;
    if (t.nominal) document.getElementById('nominal').value = t.nominal;
    if (t.syarat) {
        document.getElementById('syarat').value = t.syarat;
        document.getElementById('syaratCounter').textContent = t.syarat.length;
    }
    if (t.status) document.getElementById('status').value = t.status;

    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key}" berhasil diisi`, 'success');
}

// ===== AUTOCOMPLETE =====
function setupAutocomplete(inputId, listId) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    if (!input || !list) return;

    input.addEventListener('focus', () => filterAutocomplete(input, list));
    input.addEventListener('input', () => {
        filterAutocomplete(input, list);
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
    input.addEventListener('blur', () => {
        setTimeout(() => list.classList.remove('show'), 200);
    });

    list.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
            input.value = item.dataset.value;
            list.classList.remove('show');
            updatePreview();
            triggerAutosave();
            updateProgress();
        });
    });
}

function filterAutocomplete(input, list) {
    const q = input.value.toLowerCase().trim();
    const items = list.querySelectorAll('.autocomplete-item');
    let visible = 0;
    items.forEach(item => {
        const val = item.dataset.value.toLowerCase();
        const match = !q || val.includes(q);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    list.classList.toggle('show', visible > 0);
}

setupAutocomplete('sumber', 'sumberAutocomplete');

// ===== SMART SYARAT BUILDER =====
function addSyarat(text) {
    const textarea = document.getElementById('syarat');
    const current = textarea.value.trim();
    const newSyarat = current ? current + '\n' + text : text;
    textarea.value = newSyarat;
    document.getElementById('syaratCounter').textContent = newSyarat.length;
    updatePreview();
    triggerAutosave();
    updateProgress();
    showToast('Syarat Ditambahkan', text, 'success');
}

// ===== NOMINAL HELPER =====
document.getElementById('nominal').addEventListener('focus', function() {
    document.getElementById('nominalHelper').classList.add('show');
});
document.getElementById('nominal').addEventListener('blur', function() {
    setTimeout(() => document.getElementById('nominalHelper').classList.remove('show'), 200);
});

// ===== LINK VALIDATOR =====
document.getElementById('link_pendaftaran').addEventListener('input', function() {
    const url = this.value.trim();
    const validator = document.getElementById('linkValidator');

    if (!url) {
        validator.classList.remove('show');
        return;
    }

    let fullUrl = url;
    if (!/^https?:\/\//.test(url)) fullUrl = 'https://' + url;

    const urlPattern = /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/i;

    if (urlPattern.test(fullUrl)) {
        validator.className = 'link-validator show valid';
        validator.innerHTML = '✅ URL valid';
    } else {
        validator.className = 'link-validator show invalid';
        validator.innerHTML = '❌ Format URL tidak valid';
    }

    triggerAutosave();
});

// ===== DEADLINE CALCULATOR (Enhanced) =====
function calculateDeadline() {
    const deadline = document.getElementById('deadline').value;
    const status = document.getElementById('status').value;
    const calc = document.getElementById('deadlineCalc');
    const text = document.getElementById('deadlineCalcText');
    const breakdown = document.getElementById('deadlineBreakdown');

    if (!deadline || status === 'Tertutup') {
        calc.classList.remove('show');
        breakdown.classList.remove('show');
        return;
    }

    const deadlineDate = new Date(deadline);
    const now = new Date();
    const diff = deadlineDate - now;
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
    const weeks = Math.floor(days / 7);
    const months = Math.floor(days / 30);

    calc.classList.add('show');
    calc.classList.remove('safe', 'warning', 'danger');

    if (diff < 0) {
        calc.classList.add('danger');
        text.textContent = `⚠️ Sudah kadaluarsa ${Math.abs(days)} hari yang lalu!`;
        if (document.getElementById('status').value === 'Terbuka') {
            document.getElementById('status').value = 'Tertutup';
            showToast('Auto-Status', 'Status otomatis diubah ke "Tertutup"', 'warning');
        }
        breakdown.classList.remove('show');
    } else if (days === 0) {
        calc.classList.add('danger');
        text.textContent = '⚠️ Deadline hari ini!';
        breakdown.classList.remove('show');
    } else if (days <= 7) {
        calc.classList.add('danger');
        text.textContent = `🚨 URGENT! Hanya ${days} hari lagi!`;
        showBreakdown(days, weeks, months);
    } else if (days <= 30) {
        calc.classList.add('warning');
        text.textContent = `⏰ Segera! ${days} hari lagi sebelum deadline`;
        showBreakdown(days, weeks, months);
    } else {
        calc.classList.add('safe');
        text.textContent = `✅ Masih ${days} hari sebelum deadline`;
        showBreakdown(days, weeks, months);
    }

    updatePreview();
}

function showBreakdown(days, weeks, months) {
    const breakdown = document.getElementById('deadlineBreakdown');
    breakdown.classList.add('show');
    document.getElementById('dl-days').textContent = days;
    document.getElementById('dl-weeks').textContent = weeks;
    document.getElementById('dl-months').textContent = months;
}

document.getElementById('deadline').addEventListener('input', calculateDeadline);
document.getElementById('deadline').addEventListener('change', calculateDeadline);

// ===== LIVE PREVIEW =====
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama Beasiswa';
    const jenis = document.getElementById('jenis').value || 'Lainnya';
    const sumber = document.getElementById('sumber').value || 'Sumber Beasiswa';
    const nominal = document.getElementById('nominal').value || '-';
    const syarat = document.getElementById('syarat').value;
    const deadline = document.getElementById('deadline').value;
    const status = document.getElementById('status').value || 'Terbuka';
    const link = document.getElementById('link_pendaftaran').value;

    // Icons per jenis
    const icons = {
        'Prestasi Akademik': '🏆',
        'KIP Kuliah': '💰',
        'Muhammadiyah': '🕌',
        'Talent Scouting': '🌟',
        'Lainnya': '🎓'
    };
    const icon = icons[jenis] || '🎓';

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewBadge').textContent = `${icon} ${jenis.toUpperCase()}`;
    document.getElementById('previewSumber').textContent = sumber;
    document.getElementById('previewNominal').textContent = nominal;

    // Status
    const statusMeta = document.getElementById('previewStatusMeta');
    if (status === 'Tertutup') {
        statusMeta.style.display = 'block';
        document.getElementById('previewStatus').textContent = '🔒 Tertutup';
    } else {
        statusMeta.style.display = 'none';
    }

    // Deadline display
    const deadlineBox = document.getElementById('previewDeadlineBox');
    const deadlineText = document.getElementById('previewDeadlineText');

    if (deadline && status === 'Terbuka') {
        const deadlineDate = new Date(deadline);
        const days = Math.ceil((deadlineDate - new Date()) / (1000 * 60 * 60 * 24));
        const dateStr = deadlineDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

        deadlineBox.style.display = 'flex';
        if (days < 0) {
            deadlineBox.className = 'scholarship-deadline urgent';
            deadlineText.textContent = `Kadaluarsa ${Math.abs(days)} hari lalu`;
        } else if (days === 0) {
            deadlineBox.className = 'scholarship-deadline urgent';
            deadlineText.textContent = 'Hari ini!';
        } else if (days <= 14) {
            deadlineBox.className = 'scholarship-deadline urgent';
            deadlineText.textContent = `${days} hari lagi • ${dateStr}`;
        } else {
            deadlineBox.className = 'scholarship-deadline safe';
            deadlineText.textContent = `${dateStr} • ${days} hari lagi`;
        }
    } else if (status === 'Tertutup') {
        deadlineBox.style.display = 'flex';
        deadlineBox.className = 'scholarship-deadline urgent';
        deadlineText.textContent = 'Pendaftaran ditutup';
    } else {
        deadlineBox.style.display = 'flex';
        deadlineBox.className = 'scholarship-deadline safe';
        deadlineText.textContent = 'Sepanjang tahun';
    }

    // Syarat list
    const syaratContainer = document.getElementById('previewSyarat');
    if (syarat && syarat.trim()) {
        const items = syarat.split('\n')
            .map(s => s.trim())
            .filter(s => s)
            .map((s, i) => `<li>${escapeHtml(s.replace(/^(\d+\.|-|\*)\s*/, ''))}</li>`)
            .join('');
        syaratContainer.innerHTML = `<ul class="preview-syarat-list">${items}</ul>`;
    } else {
        syaratContainer.innerHTML = '<div class="preview-syarat-empty">Detail syarat akan muncul di sini...</div>';
    }

    // QR Code
    const qrData = `BEASISWA:${nama}|${jenis}|${sumber}|${deadline || 'Sepanjang tahun'}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ===== CHARACTER COUNTERS =====
['nama', 'syarat', 'deskripsi'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function() {
        const counter = document.getElementById(id + 'Counter');
        if (counter) counter.textContent = this.value.length;
        triggerAutosave();
        updateProgress();
    });
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama', weight: 25 },
        { el: 'jenis', weight: 15 },
        { el: 'sumber', weight: 10 },
        { el: 'nominal', weight: 15 },
        { el: 'syarat', weight: 25 },
        { el: 'deadline', weight: 10 }
    ];

    let score = 0;
    let total = 0;

    fields.forEach(f => {
        const el = document.getElementById(f.el);
        total += f.weight;
        if (el && el.value && el.value.trim()) score += f.weight;
    });

    const percent = Math.round((score / total) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

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
            deskripsi: document.getElementById('deskripsi').value,
            link_pendaftaran: document.getElementById('link_pendaftaran').value,
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
            const savedTime = new Date(data.saved_at).toLocaleString('id-ID');
            if (confirm(`Ada draft tersimpan dari ${savedTime}. Muat draft tersebut?`)) {
                document.getElementById('nama').value = data.nama || '';
                document.getElementById('jenis').value = data.jenis || 'Lainnya';
                document.getElementById('sumber').value = data.sumber || '';
                document.getElementById('nominal').value = data.nominal || '';
                document.getElementById('syarat').value = data.syarat || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                document.getElementById('link_pendaftaran').value = data.link_pendaftaran || '';
                document.getElementById('deadline').value = data.deadline || '';
                document.getElementById('status').value = data.status || 'Terbuka';

                ['nama', 'syarat', 'deskripsi'].forEach(id => {
                    const el = document.getElementById(id);
                    const counter = document.getElementById(id + 'Counter');
                    if (el && counter) counter.textContent = (data[id] || '').length;
                });

                updatePreview();
                calculateDeadline();
                updateProgress();

                if (data.link_pendaftaran) {
                    document.getElementById('link_pendaftaran').dispatchEvent(new Event('input'));
                }

                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama', 'jenis', 'sumber', 'nominal', 'syarat', 'deskripsi', 'link_pendaftaran', 'deadline', 'status'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        updatePreview();
        if (id === 'deadline' || id === 'status') calculateDeadline();
        triggerAutosave();
        updateProgress();
    });
    el.addEventListener('change', () => {
        updatePreview();
        if (id === 'deadline' || id === 'status') calculateDeadline();
        triggerAutosave();
        updateProgress();
    });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('beasiswaForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const syarat = document.getElementById('syarat').value.trim();

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama beasiswa wajib diisi!', 'error');
        return;
    }

    if (!syarat) {
        e.preventDefault();
        document.getElementById('syarat').classList.add('error');
        showToast('Validasi Error', 'Syarat & ketentuan wajib diisi!', 'error');
        return;
    }

    // ⚠️ PENTING: JANGAN disable tombol!
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    // btn.disabled = true; // ❌ JANGAN!

    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// ===== TOAST HELPER =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else if (window.showToast) {
        window.showToast(title, message, type);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ===== TOGGLE PREVIEW MODE =====
function togglePreviewMode() {
    const card = document.querySelector('.preview-card-extreme');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
    card.style.transition = 'transform 0.3s';
}

// ===== SHARE PREVIEW =====
function sharePreview() {
    const nama = document.getElementById('nama').value || 'Beasiswa';
    const jenis = document.getElementById('jenis').value || '-';
    const nominal = document.getElementById('nominal').value || '-';
    const deadline = document.getElementById('deadline').value;
    const deadlineText = deadline ? `Deadline: ${new Date(deadline).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}` : 'Pendaftaran sepanjang tahun';

    const text = `🎓 ${nama}\n🏆 ${jenis}\n💰 ${nominal}\n📅 ${deadlineText}\n\nInfo beasiswa dari FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info beasiswa disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // Ctrl+S: Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('beasiswaForm').dispatchEvent(new Event('submit'));
    }
    // Ctrl+K: Open template section
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    // Esc: Reset
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'beasiswa.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
calculateDeadline();
updateProgress();
<?php if (!empty($edit['link_pendaftaran'])): ?>
document.getElementById('link_pendaftaran').dispatchEvent(new Event('input'));
<?php endif; ?>

console.log('%c🎓 Form Beasiswa FKIP UNIMOF - Super Extreme', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: Autocomplete, Smart Syarat Builder, Deadline Calculator, Templates, QR Code', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>