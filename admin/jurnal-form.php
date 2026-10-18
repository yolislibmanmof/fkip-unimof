<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM jurnal WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        flash_message('error', '❌ Jurnal tidak ditemukan.');
        header('Location: jurnal.php');
        exit;
    }
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM jurnal WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        flash_message('info', '📋 Menduplikasi jurnal: ' . htmlspecialchars($source['nama']));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama'] ?? '');
    $penerbit = trim($_POST['penerbit'] ?? '');
    $issn = trim($_POST['issn'] ?? '');
    $eissn = trim($_POST['eissn'] ?? '');
    $akreditasi = $_POST['akreditasi'] ?? 'Belum Terakreditasi';
    $url = trim($_POST['url'] ?? '');
    $focus_area = trim($_POST['focus_area'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $language = trim($_POST['language'] ?? 'Indonesia');
    $email_kontak = trim($_POST['email_kontak'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';

    // Validasi
    if ($nama === '') {
        flash_message('error', '❌ Nama jurnal wajib diisi.');
        header('Location: jurnal-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    if ($penerbit === '') {
        flash_message('error', '❌ Penerbit wajib diisi.');
        header('Location: jurnal-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi ISSN (format: XXXX-XXXX, 8 digits)
    if ($issn !== '' && !preg_match('/^\d{4}-?\d{3}[\dXx]$/', $issn)) {
        flash_message('warning', '⚠️ Format ISSN tidak standar. Gunakan format XXXX-XXXX');
    }

    // Validasi eISSN
    if ($eissn !== '' && !preg_match('/^\d{4}-?\d{3}[\dXx]$/', $eissn)) {
        flash_message('warning', '⚠️ Format eISSN tidak standar.');
    }

    // Normalisasi URL
    if ($url && !preg_match('~^https?://~', $url)) {
        $url = 'https://' . $url;
    }

    // Validasi email
    if ($email_kontak !== '' && !filter_var($email_kontak, FILTER_VALIDATE_EMAIL)) {
        flash_message('error', '❌ Format email tidak valid.');
        header('Location: jurnal-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Akreditasi validation
    $valid_akreditasi = ['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6', 'Belum Terakreditasi'];
    if (!in_array($akreditasi, $valid_akreditasi)) $akreditasi = 'Belum Terakreditasi';

    try {
        if ($edit && $id > 0) {
            // Cek kolom optional
            $has_extra = false;
            try {
                $pdo->query("SELECT eissn FROM jurnal LIMIT 1");
                $has_extra = true;
            } catch (Exception $e) {}

            if ($has_extra) {
                $pdo->prepare("UPDATE jurnal SET nama=?, penerbit=?, issn=?, eissn=?, akreditasi=?, url=?, focus_area=?, frequency=?, language=?, email_kontak=?, deskripsi=?, status=? WHERE id=?")
                    ->execute([$nama, $penerbit, $issn, $eissn, $akreditasi, $url, $focus_area, $frequency, $language, $email_kontak, $deskripsi, $status, $id]);
            } else {
                $pdo->prepare("UPDATE jurnal SET nama=?, penerbit=?, issn=?, akreditasi=?, url=?, deskripsi=?, status=? WHERE id=?")
                    ->execute([$nama, $penerbit, $issn, $akreditasi, $url, $deskripsi, $status, $id]);
            }
            flash_message('success', '✅ Data jurnal berhasil diperbarui.');
        } else {
            $has_extra = false;
            try {
                $pdo->query("SELECT eissn FROM jurnal LIMIT 1");
                $has_extra = true;
            } catch (Exception $e) {}

            if ($has_extra) {
                $pdo->prepare("INSERT INTO jurnal (nama, penerbit, issn, eissn, akreditasi, url, focus_area, frequency, language, email_kontak, deskripsi, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$nama, $penerbit, $issn, $eissn, $akreditasi, $url, $focus_area, $frequency, $language, $email_kontak, $deskripsi, $status]);
            } else {
                $pdo->prepare("INSERT INTO jurnal (nama, penerbit, issn, akreditasi, url, deskripsi, status) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nama, $penerbit, $issn, $akreditasi, $url, $deskripsi, $status]);
            }
            flash_message('success', '✅ Jurnal ilmiah baru berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: jurnal-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
    header('Location: jurnal.php');
    exit;
}

// Cek kolom optional
$has_extra = false;
try {
    $pdo->query("SELECT eissn FROM jurnal LIMIT 1");
    $has_extra = true;
} catch (Exception $e) {}

$csrf = generate_csrf_token();
$active_menu = 'jurnal';
$page_heading = $edit ? 'Edit Jurnal' : 'Tambah Jurnal';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Jurnal Ilmiah', 'jurnal.php'], [$page_heading, null]];

require __DIR__ . '/includes/header.php';
?>

<style>
/* ===== FORM LAYOUT ===== */
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
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #3b82f6 100%);
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
    color: #3b82f6;
    font-weight: 800;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}
.progress-label { color: var(--text-muted); }

/* Form Card */
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
    background: linear-gradient(90deg, #3b82f6, #2563eb, #1d4ed8);
}
.form-header-extreme {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.form-header-extreme h2 {
    font-family: 'Georgia', serif;
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    letter-spacing: -0.01em;
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
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.25);
}

.form-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}
.form-section:focus-within { border-color: #3b82f6; }
.form-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Georgia', serif;
}
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
    background: rgba(59,130,246,0.1);
    color: #3b82f6;
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
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
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
.form-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* ===== ISSN VALIDATOR ===== */
.issn-validator {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.issn-validator.show { display: flex; }
.issn-validator.valid {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.issn-validator.invalid {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.issn-validator.warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}

/* ===== URL VALIDATOR ===== */
.url-validator {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.url-validator.show { display: flex; }
.url-validator.valid { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.url-validator.invalid { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* ===== SMART DESKRIPSI BUILDER ===== */
.deskripsi-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.deskripsi-builder-header {
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
.deskripsi-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.deskripsi-chip {
    padding: 0.35rem 0.7rem;
    background: var(--bg-secondary);
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
.deskripsi-chip:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
    transform: translateY(-1px);
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn-extreme {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
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
    box-shadow: 0 10px 25px rgba(59,130,246,0.35);
}
.submit-btn-extreme:disabled { opacity: 0.7; cursor: not-allowed; }
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
    border-color: #3b82f6;
    color: #3b82f6;
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

/* ===== PREVIEW CARD ===== */
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
    font-family: 'Georgia', serif;
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
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    transform: translateY(-2px);
}

/* Journal Card Preview */
.journal-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
    overflow: hidden;
}
.journal-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--card-accent, #3b82f6);
}

.preview-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 1rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1rem;
}
.badge-scopus { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #7e22ce; border: 1px solid #d8b4fe; }
.badge-sinta-1 { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fcd34d; }
.badge-sinta-2 { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-sinta-3 { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.badge-sinta-4 { background: #e0e7ff; color: #4338ca; border: 1px solid #a5b4fc; }
.badge-sinta-5 { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.badge-sinta-6 { background: #fed7aa; color: #c2410c; border: 1px solid #fdba74; }
.badge-lainnya { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }

.preview-title {
    font-family: 'Georgia', serif;
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    letter-spacing: -0.01em;
}
.preview-publisher {
    font-size: 0.88rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 26px; height: 26px;
    background: var(--bg-primary);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    flex-shrink: 0;
}
.preview-meta-item strong {
    color: var(--text-primary);
    font-weight: 600;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: monospace;
}

.preview-desc {
    background: var(--bg-primary);
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--text-secondary);
    min-height: 60px;
    border-left: 3px solid #3b82f6;
    font-style: italic;
}
.preview-desc::before {
    content: '"';
    font-size: 1.5rem;
    color: #3b82f6;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.preview-empty { color: var(--text-muted); font-style: italic; }

.preview-url {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #2563eb;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 0.5rem;
    word-break: break-all;
    padding: 0.5rem 0.75rem;
    background: #dbeafe;
    border-radius: 6px;
    transition: all 0.2s;
}
.preview-url:hover { background: #3b82f6; color: white; transform: translateX(3px); }

/* Impact Score */
.impact-score-box {
    margin-top: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
}
.impact-score-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.impact-score-label {
    font-size: 0.75rem;
    color: #1e40af;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
}
.impact-score-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: #1e40af;
    font-family: 'Georgia', serif;
}
.impact-bar {
    height: 8px;
    background: white;
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 0.35rem;
}
.impact-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #1e40af);
    border-radius: 999px;
    transition: width 0.8s ease;
}
.impact-level {
    font-size: 0.72rem;
    color: #1e40af;
    font-weight: 600;
}

/* QR Preview */
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

/* ===== AUTOSAVE ===== */
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
    .secondary-actions { flex-direction: column; }
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
        <span class="progress-label">Kelengkapan Jurnal</span>
    </div>
</div>

<div class="form-layout-extreme">
    <!-- LEFT: FORM -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Jurnal Ilmiah</h2>
            <p><?= $edit ? 'Perbarui detail jurnal di bawah ini.' : 'Isi detail jurnal ilmiah untuk ditampilkan di website publik.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('pendidikan')">🎓 Pendidikan</button>
                <button type="button" class="template-btn" onclick="applyTemplate('matematika')">🔢 Matematika</button>
                <button type="button" class="template-btn" onclick="applyTemplate('bahasa')">📖 Bahasa</button>
                <button type="button" class="template-btn" onclick="applyTemplate('sains')">🔬 Sains</button>
                <button type="button" class="template-btn" onclick="applyTemplate('sosial')">🌍 Sosial</button>
                <button type="button" class="template-btn" onclick="applyTemplate('teknologi')">💻 Teknologi</button>
            </div>
        </div>

        <form method="POST" id="jurnalForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Dasar -->
            <div class="form-section">
                <div class="form-section-title">📚 Informasi Dasar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📚</span>
                            Nama Jurnal <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Jurnal Pendidikan FKIP"
                               value="<?= sanitize($edit['nama'] ?? '') ?>"
                               maxlength="200">
                        <div class="form-hint" style="display: flex; justify-content: space-between;">
                            <span>Nama resmi jurnal</span>
                            <span><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/200</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏛️</span>
                            Penerbit <span class="required">*</span>
                        </label>
                        <input type="text" id="penerbit" name="penerbit" class="form-input" required
                               placeholder="Contoh: FKIP UNIMOF"
                               value="<?= sanitize($edit['penerbit'] ?? '') ?>">
                    </div>
                </div>
                <?php if ($has_extra): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔢</span>
                            ISSN (Print)
                        </label>
                        <input type="text" id="issn" name="issn" class="form-input"
                               placeholder="Contoh: 1234-5678"
                               value="<?= sanitize($edit['issn'] ?? '') ?>"
                               maxlength="9">
                        <div class="issn-validator" id="issnValidator"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🌐</span>
                            eISSN (Online)
                        </label>
                        <input type="text" id="eissn" name="eissn" class="form-input"
                               placeholder="Contoh: 8765-4321"
                               value="<?= sanitize($edit['eissn'] ?? '') ?>"
                               maxlength="9">
                        <div class="issn-validator" id="eissnValidator"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔬</span>
                            Focus & Scope
                        </label>
                        <input type="text" id="focus_area" name="focus_area" class="form-input"
                               placeholder="Contoh: Pendidikan Matematika, Pedagogi"
                               value="<?= sanitize($edit['focus_area'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Frekuensi Terbit
                        </label>
                        <select id="frequency" name="frequency" class="form-select">
                            <?php foreach(['2x Setahun (Mei & Nov)', '3x Setahun', '4x Setahun (Quarterly)', '6x Setahun (Bimonthly)', '12x Setahun (Monthly)', 'Tahunan'] as $f): ?>
                            <option value="<?= $f ?>" <?= ($edit['frequency'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔢</span>
                            ISSN
                        </label>
                        <input type="text" id="issn" name="issn" class="form-input"
                               placeholder="Contoh: 1234-5678"
                               value="<?= sanitize($edit['issn'] ?? '') ?>"
                               maxlength="9">
                        <div class="issn-validator" id="issnValidator"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($has_extra): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🌍</span>
                            Bahasa
                        </label>
                        <select id="language" name="language" class="form-select">
                            <?php foreach(['Indonesia', 'English', 'Bilingual (ID/EN)'] as $lang): ?>
                            <option value="<?= $lang ?>" <?= ($edit['language'] ?? 'Indonesia') === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>❌ Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Section 2: Akreditasi & URL -->
            <div class="form-section">
                <div class="form-section-title">🏅 Akreditasi & Akses</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏆</span>
                            Peringkat Akreditasi
                        </label>
                        <select id="akreditasi" name="akreditasi" class="form-select">
                            <?php foreach(['Scopus', 'Sinta 1', 'Sinta 2', 'Sinta 3', 'Sinta 4', 'Sinta 5', 'Sinta 6', 'Belum Terakreditasi'] as $a): ?>
                            <option value="<?= $a ?>" <?= ($edit['akreditasi'] ?? 'Belum Terakreditasi') === $a ? 'selected' : '' ?>>
                                <?php
                                $icons = ['Scopus' => '🌍', 'Sinta 1' => '🥇', 'Sinta 2' => '🥈', 'Sinta 3' => '🥉', 'Sinta 4' => '📜', 'Sinta 5' => '📄', 'Sinta 6' => '📃', 'Belum Terakreditasi' => '⏳'];
                                echo ($icons[$a] ?? '📚') . ' ' . $a;
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🔗</span>
                            URL Website Jurnal
                        </label>
                        <input type="url" id="url" name="url" class="form-input"
                               placeholder="https://jurnal.unimof.ac.id"
                               value="<?= sanitize($edit['url'] ?? '') ?>">
                        <div class="url-validator" id="urlValidator"></div>
                    </div>
                </div>
                <?php if ($has_extra): ?>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📧</span>
                        Email Kontak Redaksi
                    </label>
                    <input type="email" id="email_kontak" name="email_kontak" class="form-input"
                           placeholder="jurnal@fkip-unimof.ac.id"
                           value="<?= sanitize($edit['email_kontak'] ?? '') ?>">
                </div>
                <?php endif; ?>
            </div>

            <!-- Section 3: Deskripsi -->
            <div class="form-section">
                <div class="form-section-title">📝 Deskripsi Singkat</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Deskripsi Jurnal
                    </label>
                    <textarea id="deskripsi" name="deskripsi" class="form-textarea" rows="4"
                              placeholder="Jelaskan fokus dan ruang lingkup jurnal..."
                              maxlength="2000"><?= sanitize($edit['deskripsi'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Deskripsi akan muncul di halaman publik</span>
                        <span>
                            <span id="deskripsiWords">0</span> kata •
                            <span id="deskripsiCounter"><?= strlen($edit['deskripsi'] ?? '') ?></span>/2000 karakter
                        </span>
                    </div>
                </div>

                <!-- Smart Deskripsi Builder -->
                <div class="deskripsi-builder">
                    <div class="deskripsi-builder-header">💡 Klik untuk menambahkan frase umum:</div>
                    <div class="deskripsi-chips">
                        <span class="deskripsi-chip" onclick="addDescPhrase('Jurnal ilmiah peer-reviewed yang menerima artikel penelitian original, kajian literatur, dan studi kasus.')">+ Peer-reviewed</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Menerima kontribusi dari akademisi, peneliti, dan praktisi dari berbagai institusi.')">+ Open submission</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Terbit secara berkala dan telah terindeks di berbagai database ilmiah nasional dan internasional.')">+ Terindeks</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Menyediakan akses terbuka (Open Access) untuk seluruh artikel yang dipublikasikan.')">+ Open Access</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Menggunakan double-blind peer review untuk menjaga kualitas dan objektivitas artikel.')">+ Double-blind review</span>
                        <span class="deskripsi-chip" onclick="addDescPhrase('Fokus pada pengembangan ilmu pengetahuan dan praktik terbaik di bidangnya.')">+ Fokus pengembangan</span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Jurnal' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="jurnal.php" class="secondary-btn">← Kembali</a>
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
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan jurnal di website</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="jurnal.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Journal Card Preview -->
        <div class="journal-card-preview" id="previewCard">
            <div class="preview-badge badge-lainnya" id="previewBadge">📜 BELUM TERAKREDITASI</div>
            <h2 class="preview-title" id="previewTitle">Nama jurnal akan muncul di sini...</h2>
            <div class="preview-publisher">
                <span>🏛️</span>
                <span id="previewPublisher">-</span>
            </div>

            <div class="preview-meta">
                <div class="preview-meta-item" id="previewIssnRow">
                    <span class="meta-icon">🔢</span>
                    <strong id="previewIssn">ISSN: -</strong>
                </div>
                <?php if ($has_extra): ?>
                <div class="preview-meta-item" id="previewEissnRow" style="display: none;">
                    <span class="meta-icon">🌐</span>
                    <strong id="previewEissn">eISSN: -</strong>
                </div>
                <div class="preview-meta-item" id="previewFocusRow" style="display: none;">
                    <span class="meta-icon">🔬</span>
                    <strong id="previewFocus">-</strong>
                </div>
                <div class="preview-meta-item" id="previewFrequencyRow">
                    <span class="meta-icon">📅</span>
                    <strong id="previewFrequency">-</strong>
                </div>
                <?php endif; ?>
                <div class="preview-meta-item">
                    <span class="meta-icon">📊</span>
                    <strong id="previewStatus">✅ Aktif</strong>
                </div>
            </div>

            <div class="preview-desc" id="previewDesc">
                <span class="preview-empty">Deskripsi jurnal akan muncul di sini...</span>
            </div>

            <a href="#" class="preview-url" id="previewUrl" target="_blank" style="display: none;">
                🔗 Kunjungi Website Jurnal
            </a>
        </div>

        <!-- Impact Score -->
        <div class="impact-score-box" id="impactScoreBox">
            <div class="impact-score-header">
                <span class="impact-score-label">📊 Academic Impact</span>
                <span class="impact-score-value" id="impactScoreValue">0</span>
            </div>
            <div class="impact-bar">
                <div class="impact-bar-fill" id="impactBarFill" style="width: 0%"></div>
            </div>
            <div class="impact-level" id="impactLevel">Belum Terakreditasi</div>
        </div>

        <!-- QR Preview -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Jurnal</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Jurnal" alt="QR">
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
    pendidikan: {
        nama: 'Jurnal Pendidikan dan Pembelajaran',
        penerbit: 'FKIP UNIMOF',
        focus_area: 'Pendidikan, Pedagogi, Kurikulum, Evaluasi Pembelajaran',
        frequency: '2x Setahun (Mei & Nov)',
        language: 'Indonesia',
        akreditasi: 'Sinta 3',
        deskripsi: 'Jurnal ilmiah peer-reviewed yang mempublikasikan artikel penelitian di bidang pendidikan dan pembelajaran. Menerima kontribusi berupa penelitian original, kajian literatur, dan studi kasus dari berbagai jenjang pendidikan.'
    },
    matematika: {
        nama: 'Jurnal Matematika dan Pendidikan Matematika',
        penerbit: 'FKIP UNIMOF - Prodi Pendidikan Matematika',
        focus_area: 'Matematika Murni, Matematika Terapan, Pendidikan Matematika, Statistika',
        frequency: '2x Setahun (Juni & Des)',
        language: 'Bilingual (ID/EN)',
        akreditasi: 'Sinta 3',
        deskripsi: 'Jurnal ilmiah yang fokus pada pengembangan matematika dan pendidikan matematika. Menerima artikel penelitian murni, terapan, dan inovasi pembelajaran matematika di berbagai jenjang.'
    },
    bahasa: {
        nama: 'Jurnal Bahasa, Sastra, dan Pengajarannya',
        penerbit: 'FKIP UNIMOF - Prodi Pendidikan Bahasa',
        focus_area: 'Linguistik, Sastra, Pengajaran Bahasa, Bahasa Indonesia, Bahasa Inggris',
        frequency: '2x Setahun (Apr & Okt)',
        language: 'Bilingual (ID/EN)',
        akreditasi: 'Sinta 4',
        deskripsi: 'Jurnal ilmiah yang mempublikasikan penelitian di bidang bahasa, sastra, dan pengajarannya. Menerima artikel kajian linguistik, analisis sastra, dan inovasi pembelajaran bahasa.'
    },
    sains: {
        nama: 'Jurnal Sains dan Pendidikan Sains',
        penerbit: 'FKIP UNIMOF - Prodi Pendidikan IPA',
        focus_area: 'Fisika, Kimia, Biologi, Pendidikan Sains, STEM Education',
        frequency: '2x Setahun (Mei & Nov)',
        language: 'Indonesia',
        akreditasi: 'Sinta 3',
        deskripsi: 'Jurnal ilmiah yang mempublikasikan penelitian di bidang sains dan pendidikan sains. Menerima artikel penelitian murni, terapan, dan inovasi pembelajaran sains berbasis STEM.'
    },
    sosial: {
        nama: 'Jurnal Pendidikan IPS dan Ilmu Sosial',
        penerbit: 'FKIP UNIMOF - Prodi Pendidikan IPS',
        focus_area: 'Sejarah, Geografi, Ekonomi, Sosiologi, Pendidikan IPS',
        frequency: '2x Setahun (Mar & Sep)',
        language: 'Indonesia',
        akreditasi: 'Sinta 4',
        deskripsi: 'Jurnal ilmiah yang fokus pada pendidikan IPS dan ilmu-ilmu sosial. Menerima artikel penelitian, kajian teori, dan inovasi pembelajaran di bidang sejarah, geografi, ekonomi, dan sosiologi.'
    },
    teknologi: {
        nama: 'Jurnal Teknologi Pendidikan',
        penerbit: 'FKIP UNIMOF',
        focus_area: 'E-Learning, Media Pembelajaran, Teknologi Pendidikan, AI in Education',
        frequency: '2x Setahun (Jun & Des)',
        language: 'Bilingual (ID/EN)',
        akreditasi: 'Sinta 2',
        deskripsi: 'Jurnal ilmiah peer-reviewed yang mempublikasikan penelitian tentang integrasi teknologi dalam pendidikan. Fokus pada e-learning, media pembelajaran digital, dan inovasi teknologi pendidikan terkini.'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    document.getElementById('nama').value = t.nama;
    document.getElementById('namaCounter').textContent = t.nama.length;
    document.getElementById('penerbit').value = t.penerbit;
    document.getElementById('akreditasi').value = t.akreditasi;
    document.getElementById('deskripsi').value = t.deskripsi;

    <?php if ($has_extra): ?>
    if (document.getElementById('focus_area')) document.getElementById('focus_area').value = t.focus_area;
    if (document.getElementById('frequency')) document.getElementById('frequency').value = t.frequency;
    if (document.getElementById('language')) document.getElementById('language').value = t.language;
    <?php endif; ?>

    updateDeskripsiCount();
    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key}" berhasil diisi`, 'success');
}

// ===== SMART DESKRIPSI BUILDER =====
function addDescPhrase(phrase) {
    const textarea = document.getElementById('deskripsi');
    const current = textarea.value.trim();
    const newDesc = current ? current + '\n\n' + phrase : phrase;
    textarea.value = newDesc;
    updateDeskripsiCount();
    updatePreview();
    triggerAutosave();
    updateProgress();
    textarea.focus();
    showToast('Frase Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

// ===== LIVE PREVIEW =====
function getBadgeClass(akr) {
    const lower = akr.toLowerCase();
    if (lower.includes('scopus')) return 'badge-scopus';
    if (lower === 'sinta 1') return 'badge-sinta-1';
    if (lower === 'sinta 2') return 'badge-sinta-2';
    if (lower === 'sinta 3') return 'badge-sinta-3';
    if (lower === 'sinta 4') return 'badge-sinta-4';
    if (lower === 'sinta 5') return 'badge-sinta-5';
    if (lower === 'sinta 6') return 'badge-sinta-6';
    return 'badge-lainnya';
}

function getImpactScore(akr) {
    const lower = akr.toLowerCase();
    if (lower.includes('scopus')) return { score: 100, level: 'International • Premium', color: '#7e22ce' };
    if (lower === 'sinta 1') return { score: 90, level: 'Nasional Premium', color: '#92400e' };
    if (lower === 'sinta 2') return { score: 80, level: 'Nasional Tinggi', color: '#166534' };
    if (lower === 'sinta 3') return { score: 65, level: 'Nasional Menengah', color: '#1e40af' };
    if (lower === 'sinta 4') return { score: 50, level: 'Nasional Standard', color: '#4338ca' };
    if (lower === 'sinta 5') return { score: 35, level: 'Nasional Emerging', color: '#92400e' };
    if (lower === 'sinta 6') return { score: 20, level: 'Nasional Basic', color: '#c2410c' };
    return { score: 5, level: 'Belum Terakreditasi', color: '#4b5563' };
}

const accentMap = {
    'Scopus': '#7e22ce',
    'Sinta 1': '#92400e',
    'Sinta 2': '#166534',
    'Sinta 3': '#1e40af',
    'Sinta 4': '#4338ca',
    'Sinta 5': '#92400e',
    'Sinta 6': '#c2410c',
    'Belum Terakreditasi': '#4b5563'
};

function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama jurnal akan muncul di sini...';
    const penerbit = document.getElementById('penerbit').value || '-';
    const issn = document.getElementById('issn').value || '-';
    const akreditasi = document.getElementById('akreditasi').value || 'Belum Terakreditasi';
    const deskripsi = document.getElementById('deskripsi').value;
    const status = document.getElementById('status').value || 'Aktif';
    const url = document.getElementById('url').value;

    <?php if ($has_extra): ?>
    const eissn = document.getElementById('eissn')?.value || '';
    const focus = document.getElementById('focus_area')?.value || '';
    const frequency = document.getElementById('frequency')?.value || '-';
    <?php endif; ?>

    const accent = accentMap[akreditasi] || '#4b5563';
    document.getElementById('previewCard').style.setProperty('--card-accent', accent);

    document.getElementById('previewTitle').textContent = nama;
    document.getElementById('previewPublisher').textContent = penerbit;
    document.getElementById('previewIssn').textContent = 'ISSN: ' + issn;
    document.getElementById('previewStatus').textContent = (status === 'Aktif' ? '✅' : '❌') + ' ' + status;

    <?php if ($has_extra): ?>
    // eISSN
    const eissnRow = document.getElementById('previewEissnRow');
    if (eissn && eissnRow) {
        document.getElementById('previewEissn').textContent = 'eISSN: ' + eissn;
        eissnRow.style.display = 'flex';
    } else if (eissnRow) {
        eissnRow.style.display = 'none';
    }

    // Focus
    const focusRow = document.getElementById('previewFocusRow');
    if (focus && focusRow) {
        document.getElementById('previewFocus').textContent = focus;
        focusRow.style.display = 'flex';
    } else if (focusRow) {
        focusRow.style.display = 'none';
    }

    // Frequency
    document.getElementById('previewFrequency').textContent = frequency;
    <?php endif; ?>

    // Badge
    const badge = document.getElementById('previewBadge');
    const iconMap = {
        'Scopus': '🌍', 'Sinta 1': '🥇', 'Sinta 2': '🥈', 'Sinta 3': '🥉',
        'Sinta 4': '📜', 'Sinta 5': '📄', 'Sinta 6': '📃', 'Belum Terakreditasi': '⏳'
    };
    badge.textContent = (iconMap[akreditasi] || '📚') + ' ' + akreditasi.toUpperCase();
    badge.className = 'preview-badge ' + getBadgeClass(akreditasi);

    // Deskripsi
    if (deskripsi) {
        document.getElementById('previewDesc').innerHTML = deskripsi.replace(/\n/g, '<br>');
        document.getElementById('previewDesc').style.color = 'var(--text-secondary)';
    } else {
        document.getElementById('previewDesc').innerHTML = '<span class="preview-empty">Deskripsi jurnal akan muncul di sini...</span>';
    }

    // URL
    const urlEl = document.getElementById('previewUrl');
    if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
        urlEl.href = url;
        urlEl.style.display = 'inline-flex';
    } else if (url && !url.startsWith('http')) {
        urlEl.href = 'https://' + url;
        urlEl.style.display = 'inline-flex';
    } else {
        urlEl.style.display = 'none';
    }

    // Impact Score
    const impact = getImpactScore(akreditasi);
    document.getElementById('impactScoreValue').textContent = impact.score;
    document.getElementById('impactBarFill').style.width = impact.score + '%';
    document.getElementById('impactLevel').textContent = impact.level;

    // QR Code
    const qrData = `JURNAL:${nama}|${penerbit}|ISSN:${issn}|${akreditasi}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

// ===== CHARACTER & WORD COUNTER =====
function updateDeskripsiCount() {
    const textarea = document.getElementById('deskripsi');
    const counter = document.getElementById('deskripsiCounter');
    const wordsEl = document.getElementById('deskripsiWords');
    const len = textarea.value.length;
    const words = textarea.value.trim().split(/\s+/).filter(w => w).length;
    counter.textContent = len;
    wordsEl.textContent = words;
}

document.getElementById('nama').addEventListener('input', function() {
    document.getElementById('namaCounter').textContent = this.value.length;
    updatePreview();
    updateProgress();
    triggerAutosave();
});

document.getElementById('deskripsi').addEventListener('input', function() {
    updateDeskripsiCount();
    updatePreview();
    updateProgress();
    triggerAutosave();
});

// ===== ISSN VALIDATOR =====
function validateISSN(inputId, validatorId) {
    const input = document.getElementById(inputId);
    const validator = document.getElementById(validatorId);
    if (!input || !validator) return;

    input.addEventListener('input', function() {
        const val = this.value.trim();

        if (!val) {
            validator.classList.remove('show');
            return;
        }

        // Format: XXXX-XXXX (8 digits, bisa dengan atau tanpa dash)
        const clean = val.replace(/-/g, '');

        if (!/^\d{7}[\dXx]$/.test(clean)) {
            validator.className = 'issn-validator show invalid';
            validator.innerHTML = `❌ Format tidak valid. Gunakan XXXX-XXXX`;
            return;
        }

        // Format dengan dash
        if (/^\d{4}-\d{3}[\dXx]$/.test(val)) {
            validator.className = 'issn-validator show valid';
            validator.innerHTML = `✅ Format ISSN valid (${val.toUpperCase()})`;
        } else if (/^\d{8}$/.test(clean)) {
            validator.className = 'issn-validator show warning';
            validator.innerHTML = `⚠️ Tambahkan dash: ${clean.substring(0,4)}-${clean.substring(4)}`;
        } else {
            validator.className = 'issn-validator show invalid';
            validator.innerHTML = `❌ Format tidak valid`;
        }

        triggerAutosave();
    });
}

validateISSN('issn', 'issnValidator');
validateISSN('eissn', 'eissnValidator');

// ===== URL VALIDATOR =====
document.getElementById('url').addEventListener('input', function() {
    const url = this.value.trim();
    const validator = document.getElementById('urlValidator');

    if (!url) {
        validator.classList.remove('show');
        return;
    }

    let fullUrl = url;
    if (!/^https?:\/\//.test(url)) fullUrl = 'https://' + url;

    try {
        const urlObj = new URL(fullUrl);
        if (urlObj.hostname.includes('.')) {
            validator.className = 'url-validator show valid';
            validator.innerHTML = `✅ URL valid: ${urlObj.hostname}`;
        } else {
            throw new Error('Invalid');
        }
    } catch (e) {
        validator.className = 'url-validator show invalid';
        validator.innerHTML = `❌ URL tidak valid`;
    }

    updatePreview();
    triggerAutosave();
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama', weight: 20 },
        { el: 'penerbit', weight: 15 },
        { el: 'issn', weight: 15 },
        { el: 'akreditasi', weight: 10, check: () => document.getElementById('akreditasi').value !== 'Belum Terakreditasi' },
        { el: 'url', weight: 10 },
        { el: 'deskripsi', weight: 20 },
        <?php if ($has_extra): ?>
        { el: 'eissn', weight: 3 },
        { el: 'focus_area', weight: 3 },
        { el: 'frequency', weight: 2 },
        { el: 'language', weight: 2 },
        <?php endif; ?>
    ];

    let score = 0;
    let total = 0;

    fields.forEach(f => {
        total += f.weight;
        if (f.check) {
            if (f.check()) score += f.weight;
        } else {
            const el = document.getElementById(f.el);
            if (el && el.value && el.value.trim()) score += f.weight;
        }
    });

    const percent = Math.min(100, Math.round((score / total) * 100));
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_jurnal_draft_<?= $id ?: "new" ?>';
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
            penerbit: document.getElementById('penerbit').value,
            issn: document.getElementById('issn').value,
            akreditasi: document.getElementById('akreditasi').value,
            url: document.getElementById('url').value,
            deskripsi: document.getElementById('deskripsi').value,
            status: document.getElementById('status').value,
            <?php if ($has_extra): ?>
            eissn: document.getElementById('eissn')?.value || '',
            focus_area: document.getElementById('focus_area')?.value || '',
            frequency: document.getElementById('frequency')?.value || '',
            language: document.getElementById('language')?.value || '',
            email_kontak: document.getElementById('email_kontak')?.value || '',
            <?php endif; ?>
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
                document.getElementById('namaCounter').textContent = (data.nama || '').length;
                document.getElementById('penerbit').value = data.penerbit || '';
                document.getElementById('issn').value = data.issn || '';
                document.getElementById('akreditasi').value = data.akreditasi || 'Belum Terakreditasi';
                document.getElementById('url').value = data.url || '';
                document.getElementById('deskripsi').value = data.deskripsi || '';
                document.getElementById('status').value = data.status || 'Aktif';

                <?php if ($has_extra): ?>
                if (document.getElementById('eissn')) document.getElementById('eissn').value = data.eissn || '';
                if (document.getElementById('focus_area')) document.getElementById('focus_area').value = data.focus_area || '';
                if (document.getElementById('frequency')) document.getElementById('frequency').value = data.frequency || '2x Setahun (Mei & Nov)';
                if (document.getElementById('language')) document.getElementById('language').value = data.language || 'Indonesia';
                if (document.getElementById('email_kontak')) document.getElementById('email_kontak').value = data.email_kontak || '';
                <?php endif; ?>

                updateDeskripsiCount();
                updatePreview();
                updateProgress();

                // Trigger validators
                ['issn', 'eissn', 'url'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.value) el.dispatchEvent(new Event('input'));
                });

                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
const allFields = ['nama', 'penerbit', 'issn', 'akreditasi', 'url', 'deskripsi', 'status'<?php if ($has_extra): ?>, 'eissn', 'focus_area', 'frequency', 'language', 'email_kontak'<?php endif; ?>];
allFields.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => { updatePreview(); triggerAutosave(); updateProgress(); });
    el.addEventListener('change', () => { updatePreview(); triggerAutosave(); updateProgress(); });
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('jurnalForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const penerbit = document.getElementById('penerbit').value.trim();

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama Jurnal wajib diisi!', 'error');
        return;
    }

    if (!penerbit) {
        e.preventDefault();
        document.getElementById('penerbit').classList.add('error');
        showToast('Validasi Error', 'Penerbit wajib diisi!', 'error');
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
    const nama = document.getElementById('nama').value || 'Jurnal';
    const penerbit = document.getElementById('penerbit').value || '-';
    const issn = document.getElementById('issn').value || '-';
    const akreditasi = document.getElementById('akreditasi').value || 'Belum Terakreditasi';
    const url = document.getElementById('url').value;

    let text = `📚 ${nama}\n🏛️ ${penerbit}\n🔢 ISSN: ${issn}\n🏅 ${akreditasi}`;
    if (url) text += `\n🔗 ${url}`;
    text += `\n\nJurnal FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Info jurnal disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('jurnalForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'jurnal.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
updateDeskripsiCount();
updateProgress();

// Trigger validators jika edit mode
<?php if (!empty($edit['issn'])): ?>
document.getElementById('issn').dispatchEvent(new Event('input'));
<?php endif; ?>
<?php if ($has_extra && !empty($edit['eissn'])): ?>
document.getElementById('eissn').dispatchEvent(new Event('input'));
<?php endif; ?>
<?php if (!empty($edit['url'])): ?>
document.getElementById('url').dispatchEvent(new Event('input'));
<?php endif; ?>

console.log('%c📚 Form Jurnal FKIP UNIMOF - Super Extreme', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: 6 Templates, ISSN/eISSN Validator, Impact Score, Smart Deskripsi Builder, Live Preview, QR Code', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>