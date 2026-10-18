<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM akreditasi WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM akreditasi WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama_prodi'] = $source['nama_prodi'] . ' (Copy)';
        $edit['sertifikat_file'] = null; // Jangan duplikat file
        flash_message('info', '📋 Menduplikasi akreditasi: ' . htmlspecialchars($source['nama_prodi']));
    }
}

// ===== FETCH DAFTAR PRODI DARI DATABASE (untuk smart picker) =====
$prodi_list = [];
try {
    $prodi_list = $pdo->query("SELECT nama, kode FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $prodi = trim($_POST['nama_prodi'] ?? '');
    $badan = trim($_POST['badan_akreditasi'] ?? '');
    $peringkat = $_POST['peringkat'] ?? '';
    $sk = trim($_POST['nomor_sk'] ?? '');
    $tgl_terbit = $_POST['tanggal_terbit'] ?: null;
    $tgl_berlaku = $_POST['tanggal_berlaku'] ?: null;
    $status = $_POST['status'] ?? 'Aktif';
    $catatan = trim($_POST['catatan'] ?? '');
    $file = $edit['sertifikat_file'] ?? null;

    // Validasi dasar
    if ($prodi === '' || $badan === '' || $peringkat === '') {
        flash_message('error', '❌ Nama Prodi, Badan Akreditasi, dan Peringkat wajib diisi.');
        header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi tanggal: berlaku harus setelah terbit
    if ($tgl_terbit && $tgl_berlaku && strtotime($tgl_berlaku) < strtotime($tgl_terbit)) {
        flash_message('error', '❌ Tanggal berlaku harus setelah tanggal terbit.');
        header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Auto-status: jika tanggal_berlaku sudah lewat, set ke Kadaluarsa
    if ($tgl_berlaku && strtotime($tgl_berlaku) < time() && $status === 'Aktif') {
        $status = 'Kadaluarsa';
    }

    // Upload sertifikat
    if (!empty($_FILES['sertifikat']['name'])) {
        $allowed = ['application/pdf'];
        $max_size = 15 * 1024 * 1024; // 15MB (naikkan dari 10MB)
        
        if ($_FILES['sertifikat']['size'] > $max_size) {
            flash_message('error', '❌ Ukuran file maksimal 15MB.');
            header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['sertifikat']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            flash_message('error', '❌ Hanya file PDF yang diizinkan.');
            header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
        
        $ext = 'pdf';
        $file = 'sertifikat_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = APP_DIR . '/assets/akreditasi';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        if (move_uploaded_file($_FILES['sertifikat']['tmp_name'], $dir . '/' . $file)) {
            // Hapus file lama jika ada
            if ($edit && !empty($edit['sertifikat_file'])) {
                @unlink($dir . '/' . $edit['sertifikat_file']);
            }
        } else {
            flash_message('error', '❌ Gagal upload file.');
            header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
            exit;
        }
    }

    try {
        if ($edit && $id > 0) {
            $pdo->prepare("UPDATE akreditasi SET nama_prodi=?, badan_akreditasi=?, peringkat=?, nomor_sk=?, tanggal_terbit=?, tanggal_berlaku=?, sertifikat_file=?, status=? WHERE id=?")
                ->execute([$prodi, $badan, $peringkat, $sk, $tgl_terbit, $tgl_berlaku, $file, $status, $id]);
            flash_message('success', '✅ Data akreditasi berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO akreditasi (nama_prodi, badan_akreditasi, peringkat, nomor_sk, tanggal_terbit, tanggal_berlaku, sertifikat_file, status) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$prodi, $badan, $peringkat, $sk, $tgl_terbit, $tgl_berlaku, $file, $status]);
            flash_message('success', '✅ Data akreditasi berhasil ditambahkan.');
        }
        header('Location: akreditasi.php');
        exit;
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: akreditasi-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
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
    color: var(--primary);
    font-weight: 800;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}
.progress-label {
    color: var(--text-muted);
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
    top: 0; left: 0; right: 0;
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
.template-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
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
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(10,104,71,0.25);
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
.form-section:focus-within {
    border-color: var(--primary);
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
    background: rgba(10,104,71,0.1);
    color: var(--primary);
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
.form-textarea { resize: vertical; min-height: 80px; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
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

/* ===== AUTOCOMPLETE PRODI ===== */
.autocomplete-wrapper { position: relative; }
.autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover, .autocomplete-item.active { background: var(--bg-secondary); }
.autocomplete-item .prodi-name { font-weight: 600; }
.autocomplete-item .prodi-code { font-size: 0.72rem; color: var(--text-muted); font-family: monospace; }

/* ===== DUPLICATE WARNING ===== */
.duplicate-warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #fcd34d;
    border-radius: var(--radius-md);
    padding: 0.75rem 1rem;
    margin-top: 0.5rem;
    font-size: 0.82rem;
    color: #92400e;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.duplicate-warning.show { display: flex; }
.duplicate-warning a {
    color: #92400e;
    font-weight: 700;
    text-decoration: underline;
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
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(10,104,71,0.1);
}
.upload-zone-icon {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
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
.upload-zone-info {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 0.75rem;
    font-size: 0.72rem;
    color: var(--text-muted);
    flex-wrap: wrap;
}
.upload-zone-info span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    background: var(--bg-secondary);
    border-radius: 999px;
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
    animation: slideInUp 0.3s;
}
@keyframes slideInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
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
    display: flex;
    align-items: center;
    justify-content: center;
}
.pdf-remove:hover {
    background: #dc2626;
    color: white;
    transform: rotate(90deg);
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
    font-size: 0.88rem;
    color: #166534;
    font-weight: 600;
    animation: slideInUp 0.3s;
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
    animation: slideInUp 0.3s, pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.3); }
    50% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
}

/* ===== EXTENSION PREVIEW ===== */
.extension-preview {
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    color: #1e40af;
    display: none;
    gap: 0.75rem;
    align-items: center;
}
.extension-preview.show { display: flex; }
.extension-preview-icon { font-size: 1.25rem; }
.extension-preview-text { flex: 1; }
.extension-preview-text strong { display: block; margin-bottom: 0.1rem; }
.extension-preview-text small { opacity: 0.85; font-size: 0.78rem; }
.extension-preview-btn {
    padding: 0.4rem 0.85rem;
    background: #1e40af;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 700;
    transition: all 0.2s;
    font-family: inherit;
    white-space: nowrap;
}
.extension-preview-btn:hover {
    background: #1e3a8a;
    transform: translateY(-1px);
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
.preview-header p {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}
.preview-actions {
    display: flex;
    gap: 0.4rem;
}
.preview-action-btn {
    width: 34px;
    height: 34px;
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
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Certificate card simulation */
.certificate-card {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 2px solid #fbbf24;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
}
.certificate-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #fbbf24, #f59e0b, #fbbf24);
}
.certificate-card.peringkat-unggul { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color: #f59e0b; }
.certificate-card.peringkat-baik-sekali { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-color: #3b82f6; }
.certificate-card.peringkat-baik { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-color: #10b981; }
.certificate-card.peringkat-c { background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-color: #9ca3af; }

.certificate-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px dashed currentColor;
    opacity: 0.5;
}
.certificate-badan {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.certificate-no {
    font-size: 0.7rem;
    font-family: monospace;
    opacity: 0.7;
}
.certificate-body { text-align: center; }
.certificate-prodi {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    line-height: 1.3;
    min-height: 2.5rem;
}
.certificate-peringkat {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.25rem;
    border-radius: 999px;
    font-size: 0.95rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(10px);
    margin-bottom: 1rem;
}
.certificate-footer {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px dashed currentColor;
    opacity: 0.6;
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
    gap: 0.65rem;
    margin-bottom: 1.25rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 28px;
    height: 28px;
    background: var(--bg-primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.preview-meta-item strong {
    color: var(--text-primary);
    font-weight: 600;
}
.preview-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.preview-status.aktif { background: #dcfce7; color: #166534; }
.preview-status.kadaluarsa { background: #fee2e2; color: #991b1b; }
.preview-status.proses { background: #fef3c7; color: #92400e; }

/* QR Code Preview */
.qr-preview {
    margin-top: 1.25rem;
    padding: 1rem;
    background: white;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-align: center;
}
.qr-preview img {
    width: 120px;
    height: 120px;
    image-rendering: pixelated;
}
.qr-preview-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

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
    margin-top: 1rem;
}
.submit-btn-extreme:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(10,104,71,0.35);
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
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
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

/* ===== SK FORMAT HELPER ===== */
.sk-format-helper {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-muted);
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    display: none;
}
.sk-format-helper.show { display: block; }
.sk-format-helper code {
    font-family: monospace;
    font-size: 0.72rem;
    background: var(--bg-tertiary);
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
    color: var(--primary);
}

/* ===== SHORTCUTS LEGEND ===== */
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

/* ===== RESPONSIVE ===== */
@media (max-width: 968px) {
    .form-layout-extreme { grid-template-columns: 1fr; }
    .preview-card-extreme { position: static; order: -1; }
    .form-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .form-card-extreme, .preview-card-extreme { padding: 1.5rem; }
    .template-buttons { flex-direction: column; }
    .template-btn { justify-content: flex-start; }
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
    <!-- ===== LEFT: FORM ===== -->
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Data Akreditasi</h2>
            <p><?= $edit ? 'Perbarui detail akreditasi program studi di bawah ini.' : 'Isi detail akreditasi program studi untuk ditambahkan ke database.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('unggul')">🥇 Unggul 5 Tahun</button>
                <button type="button" class="template-btn" onclick="applyTemplate('baik_sekali')">🥈 Baik Sekali 5 Tahun</button>
                <button type="button" class="template-btn" onclick="applyTemplate('baik')">🥉 Baik 5 Tahun</button>
                <button type="button" class="template-btn" onclick="applyTemplate('proses')">⏳ Proses Akreditasi</button>
                <button type="button" class="template-btn" onclick="applyTemplate('kadaluarsa')">⚠️ Kadaluarsa</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="akreditasiForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Prodi -->
            <div class="form-section">
                <div class="form-section-title">🎓 Informasi Program Studi</div>
                <div class="form-row">
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">
                            <span class="label-icon">🎓</span>
                            Nama Program Studi <span class="required">*</span>
                        </label>
                        <input type="text" id="nama_prodi" name="nama_prodi" class="form-input" required
                               placeholder="Contoh: Pendidikan Matematika"
                               value="<?= sanitize($edit['nama_prodi'] ?? '') ?>"
                               autocomplete="off">
                        <div class="autocomplete-list" id="prodiAutocomplete">
                            <?php foreach ($prodi_list as $p): ?>
                            <div class="autocomplete-item" data-name="<?= sanitize($p['nama']) ?>" data-code="<?= sanitize($p['kode'] ?? '') ?>">
                                <span class="prodi-name"><?= sanitize($p['nama']) ?></span>
                                <span class="prodi-code"><?= sanitize($p['kode'] ?? '') ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-hint" id="prodiHint">Pilih dari daftar atau ketik nama baru</div>
                        <div class="duplicate-warning" id="duplicateWarning">
                            ⚠️ <span>Program studi ini sudah ada akreditasinya. <a href="#" id="duplicateLink">Lihat data yang ada</a></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏛️</span>
                            Badan Akreditasi <span class="required">*</span>
                        </label>
                        <select id="badan_akreditasi" name="badan_akreditasi" class="form-select" required>
                            <?php foreach(['BAN-PT', 'LAMDIK', 'LAMEMKes', 'LAKT', 'LAM-PTKes', 'Lainnya'] as $b): ?>
                            <option value="<?= $b ?>" <?= ($edit['badan_akreditasi'] ?? 'BAN-PT') === $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Lembaga pemberi akreditasi</div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Detail Peringkat -->
            <div class="form-section">
                <div class="form-section-title">🏆 Detail Peringkat & Status</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">⭐</span>
                            Peringkat Akreditasi <span class="required">*</span>
                        </label>
                        <select id="peringkat" name="peringkat" class="form-select" required>
                            <?php foreach(['Unggul', 'Baik Sekali', 'Baik', 'C', 'Proses Akreditasi'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($edit['peringkat'] ?? 'Proses Akreditasi') === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Peringkat yang diperoleh</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📊</span>
                            Status <span class="required">*</span>
                        </label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Aktif" <?= ($edit['status'] ?? 'Aktif') === 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                            <option value="Kadaluarsa" <?= ($edit['status'] ?? '') === 'Kadaluarsa' ? 'selected' : '' ?>>❌ Kadaluarsa</option>
                            <option value="Proses" <?= ($edit['status'] ?? '') === 'Proses' ? 'selected' : '' ?>>⏳ Proses</option>
                        </select>
                        <div class="form-hint" id="statusHint">💡 Status akan otomatis "Kadaluarsa" jika tanggal berlaku sudah lewat</div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">📜</span>
                        Nomor SK
                    </label>
                    <input type="text" id="nomor_sk" name="nomor_sk" class="form-input"
                           placeholder="Contoh: 1234/SK/BAN-PT/Akred/S/2023"
                           value="<?= sanitize($edit['nomor_sk'] ?? '') ?>"
                           maxlength="100">
                    <div class="form-hint"><span id="skCounter"><?= strlen($edit['nomor_sk'] ?? '') ?></span>/100 karakter</div>
                    <div class="sk-format-helper" id="skFormatHelper">
                        💡 Format BAN-PT: <code>1234/SK/BAN-PT/Akred/S/2023</code> atau <code>1234/SK/BAN-PT/Ak/PST/2023</code>
                    </div>
                </div>
            </div>

            <!-- Section 3: Upload Sertifikat -->
            <div class="form-section">
                <div class="form-section-title">📄 Upload Sertifikat (PDF)</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="sertifikat" name="sertifikat" accept=".pdf,application/pdf">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop file PDF di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                    <div class="upload-zone-info">
                        <span>📄 Format: PDF</span>
                        <span>📦 Max: 15MB</span>
                        <span>🔒 Secure upload</span>
                    </div>
                </div>
                <div class="pdf-preview" id="pdfPreview">
                    <div class="pdf-icon">📕</div>
                    <div class="pdf-info">
                        <h4 id="pdfName">-</h4>
                        <small id="pdfSize">-</small>
                    </div>
                    <button type="button" class="pdf-remove" onclick="removeFile()" title="Hapus file">✕</button>
                </div>
                <?php if (!empty($edit['sertifikat_file'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; border: 1px solid var(--border);">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <span style="font-size: 1.5rem;">📄</span>
                            <div style="flex: 1; min-width: 0;">
                                <strong>File saat ini:</strong>
                                <div style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace;">
                                    <?= sanitize($edit['sertifikat_file']) ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                            <a href="<?= asset('akreditasi/' . basename($edit['sertifikat_file'])) ?>" target="_blank"
                               style="flex: 1; padding: 0.5rem; background: var(--primary); color: white; text-decoration: none; border-radius: 6px; text-align: center; font-size: 0.78rem; font-weight: 600;">
                                👁️ Lihat File
                            </a>
                            <a href="<?= asset('akreditasi/' . basename($edit['sertifikat_file'])) ?>" download
                               style="flex: 1; padding: 0.5rem; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none; border-radius: 6px; text-align: center; font-size: 0.78rem; font-weight: 600; border: 1px solid var(--border);">
                                📥 Download
                            </a>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; font-style: italic;">
                            ℹ️ Upload file baru untuk mengganti
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 4: Masa Berlaku -->
            <div class="form-section">
                <div class="form-section-title">📅 Masa Berlaku</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📅</span>
                            Tanggal Terbit
                        </label>
                        <input type="date" id="tanggal_terbit" name="tanggal_terbit" class="form-input"
                               value="<?= $edit['tanggal_terbit'] ?? '' ?>">
                        <div class="form-hint">Tanggal SK diterbitkan</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">⏰</span>
                            Tanggal Berlaku Sampai
                        </label>
                        <input type="date" id="tanggal_berlaku" name="tanggal_berlaku" class="form-input"
                               value="<?= $edit['tanggal_berlaku'] ?? '' ?>">
                        <div class="form-hint">Masa berlaku akreditasi</div>
                    </div>
                </div>
                <div class="date-calc" id="dateCalc">
                    <span style="font-size: 1.5rem;">⏱️</span>
                    <span id="dateCalcText">-</span>
                </div>
                <div class="extension-preview" id="extensionPreview">
                    <span class="extension-preview-icon">🔄</span>
                    <div class="extension-preview-text">
                        <strong>Perpanjang otomatis 5 tahun?</strong>
                        <small id="extensionDate">-</small>
                    </div>
                    <button type="button" class="extension-preview-btn" onclick="applyExtension()">Terapkan</button>
                </div>
            </div>

            <!-- Section 5: Catatan (NEW) -->
            <div class="form-section">
                <div class="form-section-title">📝 Catatan Internal (Opsional)</div>
                <div class="form-group">
                    <textarea id="catatan" name="catatan" class="form-textarea"
                              placeholder="Tambahkan catatan internal tentang proses akreditasi ini..."
                              maxlength="500"></textarea>
                    <div class="form-hint">
                        <span id="catatanCounter">0</span>/500 karakter • Hanya untuk admin
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Data' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="akreditasi.php" class="secondary-btn">← Kembali</a>
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

    <!-- ===== RIGHT: LIVE PREVIEW ===== -->
    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <div>
                <h3>👁️ Live Preview</h3>
                <p>Pratinjau tampilan data akreditasi</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <?php if ($edit && $id > 0): ?>
                <a href="akreditasi.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Certificate Card Simulation -->
        <div class="certificate-card" id="certificateCard">
            <div class="certificate-header">
                <span class="certificate-badan" id="certBadan">BAN-PT</span>
                <span class="certificate-no" id="certNo">No. SK: -</span>
            </div>
            <div class="certificate-body">
                <div class="certificate-prodi" id="certProdi">Nama Program Studi</div>
                <div class="certificate-peringkat" id="certPeringkat">
                    <span id="certPeringkatIcon">⏳</span>
                    <span id="certPeringkatText">PROSES</span>
                </div>
            </div>
            <div class="certificate-footer">
                <span id="certTerbit">Terbit: -</span>
                <span id="certBerlaku">Berlaku: -</span>
            </div>
        </div>

        <!-- Metadata -->
        <div class="preview-meta">
            <div class="preview-meta-item">
                <span class="meta-icon">🏛️</span>
                <div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Badan Akreditasi</div>
                    <strong id="previewBadan">BAN-PT</strong>
                </div>
            </div>
            <div class="preview-meta-item">
                <span class="meta-icon">📜</span>
                <div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Nomor SK</div>
                    <strong id="previewSK" style="font-family: monospace; font-size: 0.82rem;">Belum diisi</strong>
                </div>
            </div>
            <div class="preview-meta-item">
                <span class="meta-icon">📅</span>
                <div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Masa Berlaku</div>
                    <strong id="previewDate">-</strong>
                </div>
            </div>
            <div class="preview-meta-item">
                <span class="meta-icon">⏱️</span>
                <div>
                    <div style="font-size: 0.72rem; color: var(--text-muted);">Sisa Waktu</div>
                    <strong id="previewRemaining">-</strong>
                </div>
            </div>
        </div>

        <div class="preview-status aktif" id="previewStatus">✅ Aktif</div>

        <!-- QR Code Preview -->
        <div class="qr-preview">
            <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=FKIP-UNIMOF-Akreditasi" alt="QR Code">
            <div class="qr-preview-label">QR Code Verifikasi</div>
        </div>
    </div>
</div>

<!-- Autosave Indicator -->
<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DATA PRODI untuk autocomplete =====
const prodiData = <?= json_encode($prodi_list) ?>;

// Data existing untuk duplicate check
const existingAkreditasi = <?= json_encode(
    $pdo->query("SELECT id, nama_prodi, peringkat FROM akreditasi")->fetchAll(PDO::FETCH_ASSOC)
) ?>;

// ===== TEMPLATES =====
const templates = {
    unggul: {
        peringkat: 'Unggul',
        status: 'Aktif',
        auto_extend: true
    },
    baik_sekali: {
        peringkat: 'Baik Sekali',
        status: 'Aktif',
        auto_extend: true
    },
    baik: {
        peringkat: 'Baik',
        status: 'Aktif',
        auto_extend: true
    },
    proses: {
        peringkat: 'Proses Akreditasi',
        status: 'Proses',
        auto_extend: false
    },
    kadaluarsa: {
        status: 'Kadaluarsa',
        auto_extend: false
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    if (t.peringkat) document.getElementById('peringkat').value = t.peringkat;
    if (t.status) document.getElementById('status').value = t.status;

    // Set auto dates jika auto_extend
    if (t.auto_extend) {
        const today = new Date();
        const terbit = today.toISOString().split('T')[0];
        const berlaku = new Date();
        berlaku.setFullYear(berlaku.getFullYear() + 5);
        const berlakuStr = berlaku.toISOString().split('T')[0];

        document.getElementById('tanggal_terbit').value = terbit;
        document.getElementById('tanggal_berlaku').value = berlakuStr;
    }

    updatePreview();
    calculateDate();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.replace('_', ' ')}" berhasil diisi`, 'success');
}

// ===== AUTOCOMPLETE PRODI =====
const prodiInput = document.getElementById('nama_prodi');
const autocompleteList = document.getElementById('prodiAutocomplete');
const prodiHint = document.getElementById('prodiHint');
const duplicateWarning = document.getElementById('duplicateWarning');
const duplicateLink = document.getElementById('duplicateLink');

prodiInput.addEventListener('focus', () => {
    if (prodiInput.value.length >= 0) {
        filterAutocomplete(prodiInput.value);
    }
});

prodiInput.addEventListener('input', () => {
    filterAutocomplete(prodiInput.value);
    checkDuplicate();
    updateProgress();
    updatePreview();
    triggerAutosave();
});

prodiInput.addEventListener('blur', () => {
    setTimeout(() => autocompleteList.classList.remove('show'), 200);
});

function filterAutocomplete(query) {
    const q = query.toLowerCase().trim();
    const items = autocompleteList.querySelectorAll('.autocomplete-item');
    let visible = 0;
    items.forEach(item => {
        const name = item.dataset.name.toLowerCase();
        const match = !q || name.includes(q);
        item.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    autocompleteList.classList.toggle('show', visible > 0);
}

autocompleteList.querySelectorAll('.autocomplete-item').forEach(item => {
    item.addEventListener('click', () => {
        prodiInput.value = item.dataset.name;
        autocompleteList.classList.remove('show');
        checkDuplicate();
        updateProgress();
        updatePreview();
        triggerAutosave();
    });
});

function checkDuplicate() {
    const nama = prodiInput.value.trim().toLowerCase();
    if (!nama || nama.length < 3) {
        duplicateWarning.classList.remove('show');
        return;
    }

    const existing = existingAkreditasi.find(a =>
        a.nama_prodi.toLowerCase() === nama && a.id != <?= $id ?: 0 ?>
    );

    if (existing) {
        duplicateWarning.classList.add('show');
        duplicateLink.href = `akreditasi-form.php?id=${existing.id}`;
        duplicateLink.textContent = `Lihat (${existing.peringkat})`;
        prodiHint.textContent = `⚠️ Sudah ada data dengan peringkat "${existing.peringkat}"`;
        prodiHint.className = 'form-hint warning';
    } else {
        duplicateWarning.classList.remove('show');
        prodiHint.textContent = '✓ Nama prodi tersedia';
        prodiHint.className = 'form-hint success';
    }
}

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
        showToast('Error', 'Hanya file PDF yang diizinkan!', 'error');
        fileInput.value = '';
        return;
    }
    if (file.size > 15 * 1024 * 1024) {
        showToast('Error', 'Ukuran file maksimal 15MB!', 'error');
        fileInput.value = '';
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
    showToast('File Dipilih', `${file.name} siap diupload`, 'success');
}

function removeFile() {
    fileInput.value = '';
    pdfPreview.classList.remove('show');
    uploadZone.style.display = 'block';
    updatePreview();
    showToast('File Dihapus', 'File akan dihapus saat submit', 'info');
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
    'Unggul': { icon: '🥇', class: 'unggul', label: 'UNGGUL', color: '#f59e0b' },
    'Baik Sekali': { icon: '🥈', class: 'baik-sekali', label: 'BAIK SEKALI', color: '#3b82f6' },
    'Baik': { icon: '🥉', class: 'baik', label: 'BAIK', color: '#10b981' },
    'C': { icon: '📋', class: 'default', label: 'C', color: '#6b7280' },
    'Proses Akreditasi': { icon: '⏳', class: 'default', label: 'PROSES', color: '#6b7280' }
};

function updatePreview() {
    const nama = document.getElementById('nama_prodi').value || 'Nama Program Studi';
    const badan = document.getElementById('badan_akreditasi').value || 'BAN-PT';
    const peringkat = document.getElementById('peringkat').value || 'Proses Akreditasi';
    const sk = document.getElementById('nomor_sk').value || '';
    const tglTerbit = document.getElementById('tanggal_terbit').value;
    const tglBerlaku = document.getElementById('tanggal_berlaku').value;
    const status = document.getElementById('status').value || 'Aktif';

    const config = badgeConfig[peringkat] || badgeConfig['Proses Akreditasi'];

    // Update certificate card
    const certCard = document.getElementById('certificateCard');
    certCard.className = `certificate-card peringkat-${config.class}`;

    document.getElementById('certBadan').textContent = badan;
    document.getElementById('certNo').textContent = sk ? `No: ${sk.substring(0, 20)}${sk.length > 20 ? '...' : ''}` : 'No. SK: -';
    document.getElementById('certProdi').textContent = nama;
    document.getElementById('certPeringkatIcon').textContent = config.icon;
    document.getElementById('certPeringkatText').textContent = config.label;

    const formatDate = (d) => {
        if (!d) return '-';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const date = new Date(d);
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    };

    document.getElementById('certTerbit').textContent = `Terbit: ${formatDate(tglTerbit)}`;
    document.getElementById('certBerlaku').textContent = `Berlaku: ${formatDate(tglBerlaku)}`;

    // Update metadata
    document.getElementById('previewBadan').textContent = badan;
    document.getElementById('previewSK').textContent = sk || 'Belum diisi';

    if (tglTerbit && tglBerlaku) {
        document.getElementById('previewDate').textContent = `${formatDate(tglTerbit)} - ${formatDate(tglBerlaku)}`;
    } else if (tglBerlaku) {
        document.getElementById('previewDate').textContent = `s/d ${formatDate(tglBerlaku)}`;
    } else {
        document.getElementById('previewDate').textContent = '-';
    }

    // Calculate remaining days
    if (tglBerlaku) {
        const days = Math.ceil((new Date(tglBerlaku) - new Date()) / (1000 * 60 * 60 * 24));
        const remainingEl = document.getElementById('previewRemaining');
        if (days < 0) {
            remainingEl.textContent = `Kadaluarsa ${Math.abs(days)} hari lalu`;
            remainingEl.style.color = '#ef4444';
        } else if (days === 0) {
            remainingEl.textContent = 'Kadaluarsa hari ini!';
            remainingEl.style.color = '#ef4444';
        } else if (days <= 90) {
            remainingEl.textContent = `⚠️ ${days} hari lagi (URGENT)`;
            remainingEl.style.color = '#ef4444';
        } else if (days <= 180) {
            remainingEl.textContent = `⏰ ${days} hari lagi`;
            remainingEl.style.color = '#f59e0b';
        } else {
            remainingEl.textContent = `✅ ${days} hari lagi`;
            remainingEl.style.color = '#10b981';
        }
    } else {
        document.getElementById('previewRemaining').textContent = '-';
        document.getElementById('previewRemaining').style.color = '';
    }

    // Status
    const statusEl = document.getElementById('previewStatus');
    statusEl.textContent = (status === 'Aktif' ? '✅' : (status === 'Kadaluarsa' ? '❌' : '⏳')) + ' ' + status;
    statusEl.className = `preview-status ${status.toLowerCase()}`;

    // Update QR Code
    const qrData = `AKREDITASI:${nama}|${peringkat}|${badan}|${sk || 'N/A'}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrData)}`;
}

// ===== DATE CALCULATOR =====
function calculateDate() {
    const tglTerbit = document.getElementById('tanggal_terbit').value;
    const tglBerlaku = document.getElementById('tanggal_berlaku').value;
    const calc = document.getElementById('dateCalc');
    const text = document.getElementById('dateCalcText');
    const extPreview = document.getElementById('extensionPreview');

    if (!tglBerlaku) {
        calc.classList.remove('show');
        extPreview.classList.remove('show');
        return;
    }

    const days = Math.ceil((new Date(tglBerlaku) - new Date()) / (1000 * 60 * 60 * 24));

    calc.classList.add('show');
    calc.classList.remove('warning', 'danger');

    if (days < 0) {
        calc.classList.add('danger');
        text.textContent = `⚠️ Sudah kadaluarsa ${Math.abs(days)} hari yang lalu!`;
        // Auto-set status ke Kadaluarsa
        if (document.getElementById('status').value === 'Aktif') {
            document.getElementById('status').value = 'Kadaluarsa';
            showToast('Auto-Status', 'Status otomatis diubah ke "Kadaluarsa"', 'warning');
        }
    } else if (days === 0) {
        calc.classList.add('danger');
        text.textContent = '⚠️ Kadaluarsa hari ini!';
    } else if (days <= 90) {
        calc.classList.add('danger');
        text.textContent = `🚨 Hanya ${days} hari lagi sebelum kadaluarsa! SEGERA perpanjang!`;
    } else if (days <= 180) {
        calc.classList.add('warning');
        text.textContent = `⏰ ${days} hari lagi sebelum kadaluarsa - Siapkan perpanjangan`;
    } else {
        text.textContent = `✅ Masih ${days} hari (${Math.floor(days/365)} tahun ${days%365} hari) sebelum kadaluarsa`;
    }

    // Show extension preview jika sudah dekat kadaluarsa
    if (days > 0 && days <= 365) {
        const newDate = new Date(tglBerlaku);
        newDate.setFullYear(newDate.getFullYear() + 5);
        document.getElementById('extensionDate').textContent = `Berlaku baru: ${newDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}`;
        extPreview.classList.add('show');
    } else {
        extPreview.classList.remove('show');
    }

    updatePreview();
}

function applyExtension() {
    const tglBerlaku = document.getElementById('tanggal_berlaku').value;
    if (!tglBerlaku) return;

    const newDate = new Date(tglBerlaku);
    newDate.setFullYear(newDate.getFullYear() + 5);
    document.getElementById('tanggal_berlaku').value = newDate.toISOString().split('T')[0];
    document.getElementById('status').value = 'Aktif';

    calculateDate();
    showToast('Perpanjangan Diterapkan', 'Tanggal berlaku ditambah 5 tahun', 'success');
}

// ===== SK FORMAT HELPER =====
document.getElementById('nomor_sk').addEventListener('focus', function() {
    document.getElementById('skFormatHelper').classList.add('show');
});
document.getElementById('nomor_sk').addEventListener('blur', function() {
    setTimeout(() => document.getElementById('skFormatHelper').classList.remove('show'), 200);
});

// ===== CHARACTER COUNTER =====
document.getElementById('nomor_sk').addEventListener('input', function() {
    document.getElementById('skCounter').textContent = this.value.length;
    triggerAutosave();
    updateProgress();
});

document.getElementById('catatan').addEventListener('input', function() {
    document.getElementById('catatanCounter').textContent = this.value.length;
    triggerAutosave();
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama_prodi', weight: 20 },
        { el: 'badan_akreditasi', weight: 15 },
        { el: 'peringkat', weight: 20 },
        { el: 'nomor_sk', weight: 15 },
        { el: 'tanggal_terbit', weight: 10 },
        { el: 'tanggal_berlaku', weight: 15 },
        { el: 'status', weight: 5 }
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
const storageKey = 'fkip_akreditasi_draft_<?= $id ?: "new" ?>';
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
            nama_prodi: document.getElementById('nama_prodi').value,
            badan_akreditasi: document.getElementById('badan_akreditasi').value,
            peringkat: document.getElementById('peringkat').value,
            nomor_sk: document.getElementById('nomor_sk').value,
            tanggal_terbit: document.getElementById('tanggal_terbit').value,
            tanggal_berlaku: document.getElementById('tanggal_berlaku').value,
            status: document.getElementById('status').value,
            catatan: document.getElementById('catatan').value,
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
                document.getElementById('nama_prodi').value = data.nama_prodi || '';
                document.getElementById('badan_akreditasi').value = data.badan_akreditasi || 'BAN-PT';
                document.getElementById('peringkat').value = data.peringkat || 'Proses Akreditasi';
                document.getElementById('nomor_sk').value = data.nomor_sk || '';
                document.getElementById('tanggal_terbit').value = data.tanggal_terbit || '';
                document.getElementById('tanggal_berlaku').value = data.tanggal_berlaku || '';
                document.getElementById('status').value = data.status || 'Aktif';
                document.getElementById('catatan').value = data.catatan || '';
                document.getElementById('catatanCounter').textContent = (data.catatan || '').length;
                updatePreview();
                calculateDate();
                updateProgress();
                document.getElementById('skCounter').textContent = (data.nomor_sk || '').length;
                showToast('Draft Dimuat', 'Data draft berhasil dimuat', 'success');
            } else {
                localStorage.removeItem(storageKey);
            }
        }
    } catch (e) {}
})();
<?php endif; ?>

// ===== EVENT LISTENERS =====
['nama_prodi', 'badan_akreditasi', 'peringkat', 'nomor_sk', 'tanggal_terbit', 'tanggal_berlaku', 'status', 'catatan'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
    el.addEventListener('change', () => {
        updatePreview();
        triggerAutosave();
        updateProgress();
    });
});

document.getElementById('tanggal_berlaku').addEventListener('change', calculateDate);
document.getElementById('tanggal_terbit').addEventListener('change', () => {
    // Validasi tanggal terbit < berlaku
    const terbit = document.getElementById('tanggal_terbit').value;
    const berlaku = document.getElementById('tanggal_berlaku').value;
    if (terbit && berlaku && new Date(berlaku) < new Date(terbit)) {
        showToast('Validasi Error', 'Tanggal berlaku harus setelah tanggal terbit', 'error');
    }
});

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('akreditasiForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama_prodi').value.trim();
    const badan = document.getElementById('badan_akreditasi').value.trim();
    const peringkat = document.getElementById('peringkat').value.trim();

    if (!nama || !badan || !peringkat) {
        e.preventDefault();
        if (!nama) document.getElementById('nama_prodi').classList.add('error');
        if (!badan) document.getElementById('badan_akreditasi').classList.add('error');
        if (!peringkat) document.getElementById('peringkat').classList.add('error');
        showToast('Validasi Error', 'Mohon lengkapi field yang wajib diisi', 'error');
        return;
    }

    // ⚠️ PENTING: JANGAN disable tombol - bisa membatalkan submit di Chrome
    // Hanya ganti teks visual
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    // btn.disabled = true;  // ❌ JANGAN lakukan ini!

    setTimeout(() => {
        try { localStorage.removeItem(storageKey); } catch(e) {}
    }, 500);
});

// ===== TOAST HELPER (uses AdminPanel.Toast if available) =====
function showToast(title, message, type = 'info') {
    if (window.AdminPanel?.Toast) {
        window.AdminPanel.Toast.show(message, type, title);
    } else if (window.showToast) {
        window.showToast(title, message, type);
    } else {
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // Ctrl+S: Save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('akreditasiForm').dispatchEvent(new Event('submit'));
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
            window.location.href = 'akreditasi.php';
        }
    }
});

// ===== TOGGLE PREVIEW MODE =====
function togglePreviewMode() {
    const card = document.querySelector('.preview-card-extreme');
    card.style.transform = card.style.transform === 'scale(0.95)' ? 'scale(1)' : 'scale(0.95)';
    card.style.transition = 'transform 0.3s';
}

// ===== INITIAL =====
updatePreview();
calculateDate();
updateProgress();
<?php if (!empty($edit['nama_prodi'])): ?>
checkDuplicate();
<?php endif; ?>

console.log('%c🏆 Form Akreditasi FKIP UNIMOF - Super Extreme', 'color: #f59e0b; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: Autocomplete, Duplicate Check, Auto-Status, QR Code, Templates, Extension Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>