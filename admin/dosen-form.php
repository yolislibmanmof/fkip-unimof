<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$duplicate_from = (int)($_GET['duplicate'] ?? 0);
$edit = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM dosen WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($duplicate_from > 0) {
    $stmt = $pdo->prepare("SELECT * FROM dosen WHERE id = ?");
    $stmt->execute([$duplicate_from]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source) {
        $edit = $source;
        $edit['id'] = null;
        $edit['nama'] = $source['nama'] . ' (Copy)';
        $edit['foto'] = null;
        flash_message('info', '📋 Menduplikasi data dosen: ' . htmlspecialchars($source['nama']));
    }
}

// Ambil daftar prodi
$prodi_list = [];
try {
    $prodi_list = $pdo->query("SELECT id, nama FROM program_studi WHERE status='Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Autocomplete data dari dosen existing
$autocomplete_data = [
    'pendidikan' => [],
    'bidang_keahlian' => []
];
try {
    $autocomplete_data['pendidikan'] = $pdo->query("SELECT DISTINCT pendidikan_terakhir FROM dosen WHERE pendidikan_terakhir IS NOT NULL AND pendidikan_terakhir != '' ORDER BY pendidikan_terakhir LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    $autocomplete_data['bidang_keahlian'] = $pdo->query("SELECT DISTINCT bidang_keahlian FROM dosen WHERE bidang_keahlian IS NOT NULL AND bidang_keahlian != '' ORDER BY bidang_keahlian LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $nama = trim($_POST['nama'] ?? '');
    $nidn = trim($_POST['nidn'] ?? '');
    $jabatan = $_POST['jabatan_fungsional'] ?? 'Tenaga Pengajar';
    $pendidikan = trim($_POST['pendidikan_terakhir'] ?? '');
    $prodi_id = (int)($_POST['program_studi_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $google_scholar = trim($_POST['google_scholar'] ?? '');
    $scopus = trim($_POST['scopus'] ?? '');
    $bidang_keahlian = trim($_POST['bidang_keahlian'] ?? '');
    $riwayat_pendidikan = trim($_POST['riwayat_pendidikan'] ?? '');
    $penelitian = trim($_POST['penelitian'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';
    $foto = $edit['foto'] ?? null;

    // Validasi
    if ($nama === '') {
        flash_message('error', '❌ Nama dosen wajib diisi.');
        header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Validasi NIDN (format: 10 digit atau 14 digit)
    if ($nidn !== '' && !preg_match('/^\d{10,14}$/', preg_replace('/[^0-9]/', '', $nidn))) {
        flash_message('warning', '⚠️ Format NIDN tidak standar (10-14 digit angka).');
    }

    // Validasi email
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_message('error', '❌ Format email tidak valid.');
        header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }

    // Normalisasi URL academic profiles
    if ($google_scholar && !preg_match('~^https?://~', $google_scholar)) {
        $google_scholar = 'https://' . $google_scholar;
    }
    if ($scopus && !preg_match('~^https?://~', $scopus)) {
        $scopus = 'https://' . $scopus;
    }

    // Upload foto
    if (!empty($_FILES['foto']['name'])) {
        if (function_exists('upload_image')) {
            $up = upload_image($_FILES['foto'], 'dosen', 3 * 1024 * 1024);
            if (!$up['ok']) {
                flash_message('error', $up['error']);
                header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($up['name']) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'dosen');
                $foto = $up['name'];
            }
        } else {
            // Fallback manual upload
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['foto']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                flash_message('error', '❌ Hanya file gambar (JPG, PNG, WEBP) yang diizinkan.');
                header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            if ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                flash_message('error', '❌ Ukuran foto maksimal 3MB.');
                header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
                exit;
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $new_name = 'dosen_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir = APP_DIR . '/uploads/dosen';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . '/' . $new_name)) {
                if ($foto && function_exists('delete_upload')) delete_upload($foto, 'dosen');
                $foto = $new_name;
            }
        }
    }

    try {
        if ($edit && $id > 0) {
            $pdo->prepare("UPDATE dosen SET nama=?, nidn=?, jabatan_fungsional=?, pendidikan_terakhir=?, program_studi_id=?, email=?, phone=?, google_scholar=?, scopus=?, bidang_keahlian=?, riwayat_pendidikan=?, penelitian=?, bio=?, foto=?, status=? WHERE id=?")
                ->execute([$nama, $nidn, $jabatan, $pendidikan, $prodi_id, $email, $phone, $google_scholar, $scopus, $bidang_keahlian, $riwayat_pendidikan, $penelitian, $bio, $foto, $status, $id]);
            flash_message('success', '✅ Data dosen berhasil diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO dosen (nama, nidn, jabatan_fungsional, pendidikan_terakhir, program_studi_id, email, phone, google_scholar, scopus, bidang_keahlian, riwayat_pendidikan, penelitian, bio, foto, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$nama, $nidn, $jabatan, $pendidikan, $prodi_id, $email, $phone, $google_scholar, $scopus, $bidang_keahlian, $riwayat_pendidikan, $penelitian, $bio, $foto, $status]);
            flash_message('success', '✅ Dosen baru berhasil ditambahkan.');
        }
        header('Location: dosen.php');
        exit;
    } catch (PDOException $e) {
        flash_message('error', '❌ Gagal menyimpan: ' . $e->getMessage());
        header('Location: dosen-form.php' . ($id ? "?id=$id" : ''));
        exit;
    }
}

$csrf = generate_csrf_token();
$active_menu = 'dosen';
$page_heading = $edit ? 'Edit Dosen' : 'Tambah Dosen';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Data Dosen', 'dosen.php'], [$page_heading, null]];

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
    background: linear-gradient(90deg, #ef4444 0%, #f59e0b 50%, #1e40af 100%);
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
    color: #1e40af;
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
    background: linear-gradient(90deg, #1e40af, #3b82f6);
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
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border-color: #1e40af;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30,64,175,0.25);
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
    background: rgba(30,64,175,0.1);
    color: #1e40af;
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
.form-textarea { resize: vertical; min-height: 100px; }
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

/* ===== NIDN VALIDATOR ===== */
.nidn-validator {
    margin-top: 0.5rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    display: none;
    align-items: center;
    gap: 0.5rem;
}
.nidn-validator.show { display: flex; }
.nidn-validator.valid {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.nidn-validator.invalid {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.nidn-validator.warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
}

/* NIDN breakdown display */
.nidn-breakdown {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
    display: none;
}
.nidn-breakdown.show { display: grid; }
.nidn-unit {
    text-align: center;
    padding: 0.5rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
}
.nidn-unit-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: #1e40af;
    line-height: 1;
    font-family: monospace;
}
.nidn-unit-label {
    font-size: 0.65rem;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-top: 0.2rem;
    font-weight: 600;
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

/* ===== SMART BIO BUILDER ===== */
.bio-builder {
    margin-top: 0.75rem;
    padding: 0.85rem;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.bio-builder-header {
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
.bio-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.bio-chip {
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
.bio-chip:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
    transform: translateY(-1px);
}

/* ===== UPLOAD ZONE ===== */
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
    border-color: #3b82f6;
    background: rgba(59,130,246,0.03);
}
.upload-zone.dragover {
    border-style: solid;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
}
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
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
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}
.upload-zone-sub { font-size: 0.85rem; color: var(--text-muted); }
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

/* ===== IMAGE PREVIEW ===== */
.image-preview {
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
.image-preview.show { display: flex; }
.image-preview-img {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.image-preview-img img { width: 100%; height: 100%; object-fit: cover; }
.image-preview-info { flex: 1; min-width: 0; }
.image-preview-info h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.image-preview-info small { font-size: 0.78rem; color: var(--text-muted); display: block; }
.image-preview-remove {
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
.image-preview-remove:hover {
    background: #dc2626;
    color: white;
    transform: rotate(90deg);
}

.aspect-warning {
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    font-size: 0.78rem;
    display: none;
    align-items: center;
    gap: 0.4rem;
}
.aspect-warning.show { display: flex; }

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
    background: #1e40af;
    color: white;
    border-color: #1e40af;
    transform: translateY(-2px);
}

/* Dosen Card Preview (seperti LinkedIn card) */
.dosen-card-preview {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    position: relative;
}
.dosen-card-preview::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 70px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
}
.dosen-card-preview-body {
    padding: 1.25rem;
    padding-top: 3rem;
    position: relative;
}
.preview-avatar {
    position: absolute;
    top: -35px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 800;
    border: 4px solid var(--bg-primary);
    box-shadow: 0 8px 20px rgba(30,64,175,0.3);
    overflow: hidden;
}
.preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
.preview-title {
    text-align: center;
    font-size: 1.15rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    margin-top: 0.5rem;
    font-family: 'Georgia', serif;
    letter-spacing: -0.01em;
}
.preview-role {
    text-align: center;
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.preview-nidn {
    text-align: center;
    font-size: 0.75rem;
    color: #1e40af;
    font-weight: 600;
    font-family: monospace;
    margin-bottom: 1rem;
}

.preview-meta {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.preview-meta-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-size: 0.82rem;
    color: var(--text-secondary);
}
.preview-meta-item .meta-icon {
    width: 26px; height: 26px;
    background: var(--bg-primary);
    border-radius: 7px;
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
}

.preview-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    margin: 0 auto;
    display: flex;
    width: fit-content;
}
.preview-status-badge.aktif { background: #dcfce7; color: #166534; }
.preview-status-badge.non-aktif { background: #fee2e2; color: #991b1b; }

.preview-bio {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--text-secondary);
    font-style: italic;
    border-left: 3px solid #1e40af;
    text-align: left;
    min-height: 60px;
    margin-top: 1rem;
}
.preview-bio::before {
    content: '"';
    font-size: 2rem;
    color: #1e40af;
    line-height: 0.5;
    display: block;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Academic profile preview */
.academic-profile-preview {
    padding: 1rem;
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border: 1px solid #93c5fd;
    border-radius: var(--radius-md);
    margin-top: 1rem;
}
.academic-profile-preview h4 {
    font-size: 0.78rem;
    color: #1e40af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    margin-bottom: 0.65rem;
}
.academic-preview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}
.academic-preview-stat {
    text-align: center;
    padding: 0.5rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #93c5fd;
}
.academic-preview-value {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e40af;
    line-height: 1;
    margin-bottom: 0.2rem;
    font-family: 'Georgia', serif;
}
.academic-preview-label {
    font-size: 0.65rem;
    color: #1e40af;
    text-transform: uppercase;
    font-weight: 600;
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
    background: linear-gradient(135deg, #1e40af, #3b82f6);
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
    box-shadow: 0 10px 25px rgba(30,64,175,0.35);
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
    border-color: #1e40af;
    color: #1e40af;
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
    .academic-preview-stats { grid-template-columns: 1fr; }
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
        <span class="progress-label">Kelengkapan Profil</span>
    </div>
</div>

<div class="form-layout-extreme">
    <div class="form-card-extreme" data-aos="fade-right">
        <div class="form-header-extreme">
            <h2><?= $edit ? '✏️ Edit' : '➕ Tambah' ?> Dosen</h2>
            <p><?= $edit ? 'Perbarui data dosen di bawah ini.' : 'Isi data dosen baru untuk ditambahkan ke direktori fakultas.' ?></p>
        </div>

        <!-- Quick Templates -->
        <div class="template-section" data-aos="fade-up">
            <div class="template-label">⚡ Template Cepat (Klik untuk auto-fill)</div>
            <div class="template-buttons">
                <button type="button" class="template-btn" onclick="applyTemplate('guru_besar')">🏆 Guru Besar</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lektor_kepala')">🎖️ Lektor Kepala</button>
                <button type="button" class="template-btn" onclick="applyTemplate('lektor')">📚 Lektor</button>
                <button type="button" class="template-btn" onclick="applyTemplate('asisten_ahli')">📖 Asisten Ahli</button>
                <button type="button" class="template-btn" onclick="applyTemplate('tenaga_pengajar')">🏫 Tenaga Pengajar</button>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="dosenForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

            <!-- Section 1: Informasi Pribadi -->
            <div class="form-section">
                <div class="form-section-title">👤 Informasi Pribadi</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">👤</span>
                            Nama Lengkap <span class="required">*</span>
                        </label>
                        <input type="text" id="nama" name="nama" class="form-input" required
                               placeholder="Contoh: Dr. Ahmad, M.Pd."
                               value="<?= sanitize($edit['nama'] ?? '') ?>"
                               maxlength="150">
                        <div class="form-hint"><span id="namaCounter"><?= strlen($edit['nama'] ?? '') ?></span>/150 karakter</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🆔</span>
                            NIDN
                        </label>
                        <input type="text" id="nidn" name="nidn" class="form-input"
                               placeholder="Contoh: 198001012005011001"
                               value="<?= sanitize($edit['nidn'] ?? '') ?>"
                               maxlength="20">
                        <div class="nidn-validator" id="nidnValidator"></div>
                        <div class="nidn-breakdown" id="nidnBreakdown">
                            <div class="nidn-unit">
                                <div class="nidn-unit-value" id="nidn-wilayah">-</div>
                                <div class="nidn-unit-label">Wilayah</div>
                            </div>
                            <div class="nidn-unit">
                                <div class="nidn-unit-value" id="nidn-tgl">-</div>
                                <div class="nidn-unit-label">Tgl Lahir</div>
                            </div>
                            <div class="nidn-unit">
                                <div class="nidn-unit-value" id="nidn-thn">-</div>
                                <div class="nidn-unit-label">Tahun</div>
                            </div>
                            <div class="nidn-unit">
                                <div class="nidn-unit-value" id="nidn-jk">-</div>
                                <div class="nidn-unit-label">Gender</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎖️</span>
                            Jabatan Fungsional <span class="required">*</span>
                        </label>
                        <select id="jabatan_fungsional" name="jabatan_fungsional" class="form-select" required>
                            <?php foreach(['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($edit['jabatan_fungsional'] ?? 'Tenaga Pengajar') === $j ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">
                            <span class="label-icon">🎓</span>
                            Pendidikan Terakhir
                        </label>
                        <input type="text" id="pendidikan_terakhir" name="pendidikan_terakhir" class="form-input"
                               placeholder="Contoh: S3 Pendidikan Matematika"
                               value="<?= sanitize($edit['pendidikan_terakhir'] ?? '') ?>"
                               autocomplete="off">
                        <div class="autocomplete-list" id="pendidikanAutocomplete">
                            <?php foreach ($autocomplete_data['pendidikan'] as $p): ?>
                            <div class="autocomplete-item" data-value="<?= sanitize($p) ?>"><?= sanitize($p) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🏫</span>
                            Program Studi
                        </label>
                        <select id="program_studi_id" name="program_studi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($edit['program_studi_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['nama']) ?></option>
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
                            <option value="Non-Aktif" <?= ($edit['status'] ?? '') === 'Non-Aktif' ? 'selected' : '' ?>>📴 Non-Aktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2: Kontak & Academic Profiles (NEW!) -->
            <div class="form-section">
                <div class="form-section-title">📞 Kontak & Profil Akademik</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📧</span>
                            Email
                        </label>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="dosen@fkip-unimof.ac.id"
                               value="<?= sanitize($edit['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📞</span>
                            No. Telepon
                        </label>
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="081234567890"
                               value="<?= sanitize($edit['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">🎓</span>
                            Google Scholar
                        </label>
                        <input type="url" id="google_scholar" name="google_scholar" class="form-input"
                               placeholder="https://scholar.google.com/citations?user=..."
                               value="<?= sanitize($edit['google_scholar'] ?? '') ?>">
                        <div class="link-validator" id="scholarValidator"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="label-icon">📚</span>
                            Scopus ID
                        </label>
                        <input type="url" id="scopus" name="scopus" class="form-input"
                               placeholder="https://www.scopus.com/authid/..."
                               value="<?= sanitize($edit['scopus'] ?? '') ?>">
                        <div class="link-validator" id="scopusValidator"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🔬</span>
                        Bidang Keahlian
                    </label>
                    <input type="text" id="bidang_keahlian" name="bidang_keahlian" class="form-input"
                           placeholder="Contoh: Matematika, Statistika, Pendidikan"
                           value="<?= sanitize($edit['bidang_keahlian'] ?? '') ?>"
                           autocomplete="off">
                </div>
            </div>

            <!-- Section 3: Riwayat & Publikasi (NEW!) -->
            <div class="form-section">
                <div class="form-section-title">📖 Riwayat & Publikasi</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🎓</span>
                        Riwayat Pendidikan
                    </label>
                    <textarea id="riwayat_pendidikan" name="riwayat_pendidikan" class="form-textarea" rows="3"
                              placeholder="S1 - Universitas X (2005-2009)&#10;S2 - Universitas Y (2010-2012)&#10;S3 - Universitas Z (2015-2019)"
                              maxlength="1000"><?= sanitize($edit['riwayat_pendidikan'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Format: Jenjang - Kampus (Tahun)</span>
                        <span><span id="riwayatCounter"><?= strlen($edit['riwayat_pendidikan'] ?? '') ?></span>/1000 karakter</span>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">
                        <span class="label-icon">🔬</span>
                        Penelitian & Publikasi
                    </label>
                    <textarea id="penelitian" name="penelitian" class="form-textarea" rows="3"
                              placeholder="Judul penelitian, jurnal, tahun publikasi..."
                              maxlength="2000"><?= sanitize($edit['penelitian'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Daftar penelitian dan publikasi ilmiah</span>
                        <span><span id="penelitianCounter"><?= strlen($edit['penelitian'] ?? '') ?></span>/2000 karakter</span>
                    </div>
                </div>
            </div>

            <!-- Section 4: Foto Dosen -->
            <div class="form-section">
                <div class="form-section-title">📷 Foto Dosen (Opsional)</div>
                <div class="upload-zone" id="uploadZone">
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <div class="upload-zone-icon">📤</div>
                    <div class="upload-zone-text">Drag & drop foto di sini</div>
                    <div class="upload-zone-sub">atau klik untuk memilih file</div>
                    <div class="upload-zone-info">
                        <span>🖼️ JPG/PNG/WEBP</span>
                        <span>📦 Max: 3MB</span>
                        <span>📐 Rasio 1:1</span>
                    </div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <div class="image-preview-img" id="imagePreviewImg"><span style="font-size: 2rem;">👤</span></div>
                    <div class="image-preview-info">
                        <h4 id="imageName">-</h4>
                        <small id="imageSize">-</small>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem;" id="imageDim"></div>
                    </div>
                    <button type="button" class="image-preview-remove" onclick="removeImage()" title="Hapus foto">✕</button>
                </div>
                <div class="aspect-warning" id="aspectWarning">
                    ⚠️ <span>Foto sebaiknya rasio 1:1 (persegi) untuk tampilan terbaik</span>
                </div>
                <?php if (!empty($edit['foto'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); font-size: 0.85rem; display: flex; align-items: center; gap: 1rem; border: 1px solid var(--border);">
                        <img src="<?= asset('uploads/dosen/' . basename($edit['foto'])) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                        <div style="flex: 1; min-width: 0;">
                            <strong>Foto saat ini:</strong>
                            <div style="font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace;">
                                <?= sanitize($edit['foto']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Section 5: Biografi -->
            <div class="form-section">
                <div class="form-section-title">📝 Biografi Singkat</div>
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">📝</span>
                        Bio Dosen
                    </label>
                    <textarea id="bio" name="bio" class="form-textarea" rows="5"
                              placeholder="Tuliskan biografi singkat dosen..."
                              maxlength="1500"><?= sanitize($edit['bio'] ?? '') ?></textarea>
                    <div class="form-hint" style="display: flex; justify-content: space-between;">
                        <span>Biografi akan tampil di halaman profil dosen</span>
                        <span>
                            <span id="bioWords">0</span> kata •
                            <span id="bioCounter"><?= strlen($edit['bio'] ?? '') ?></span>/1500 karakter
                        </span>
                    </div>
                </div>

                <!-- Smart Bio Builder -->
                <div class="bio-builder">
                    <div class="bio-builder-header">💡 Klik untuk menambahkan frase umum:</div>
                    <div class="bio-chips">
                        <span class="bio-chip" onclick="addBioPhrase('Beliau adalah dosen tetap FKIP UNIMOF sejak')">+ Dosen tetap sejak...</span>
                        <span class="bio-chip" onclick="addBioPhrase('Mengampu mata kuliah')">+ Mengampu mata kuliah...</span>
                        <span class="bio-chip" onclick="addBioPhrase('Telah mempublikasikan berbagai penelitian di bidang')">+ Publikasi di bidang...</span>
                        <span class="bio-chip" onclick="addBioPhrase('Aktif sebagai pembimbing skripsi mahasiswa')">+ Pembimbing skripsi</span>
                        <span class="bio-chip" onclick="addBioPhrase('Merupakan anggota organisasi profesi')">+ Anggota organisasi</span>
                        <span class="bio-chip" onclick="addBioPhrase('Memiliki minat riset pada bidang')">+ Minat riset...</span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn-extreme" id="submitBtn">
                <span class="btn-text"><?= $edit ? '💾 Perbarui Data' : '✨ Simpan Dosen' ?></span>
                <span class="spinner"></span>
            </button>

            <!-- Secondary Actions -->
            <div class="secondary-actions">
                <?php if ($edit && $id > 0): ?>
                <a href="?duplicate=<?= $id ?>" class="secondary-btn" onclick="return confirm('Duplikasi data ini?')">
                    📋 Duplikasi
                </a>
                <?php endif; ?>
                <a href="dosen.php" class="secondary-btn">← Kembali</a>
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

    <div class="preview-card-extreme" data-aos="fade-left">
        <div class="preview-header">
            <div>
                <h3>👁️ Live Preview</h3>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">Tampilan kartu dosen</p>
            </div>
            <div class="preview-actions">
                <button class="preview-action-btn" onclick="togglePreviewMode()" title="Toggle mode">🔄</button>
                <button class="preview-action-btn" onclick="sharePreview()" title="Share">🔗</button>
                <?php if ($edit && $id > 0): ?>
                <a href="dosen.php" class="preview-action-btn" target="_blank" title="Lihat di list">📋</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dosen Card Preview -->
        <div class="dosen-card-preview">
            <div class="dosen-card-preview-body">
                <div class="preview-avatar" id="previewAvatar">
                    <span id="previewInitials">👤</span>
                </div>
                <h2 class="preview-title" id="previewTitle">Nama Dosen</h2>
                <div class="preview-role" id="previewRole">Jabatan @ Prodi</div>
                <div class="preview-nidn" id="previewNidn">NIDN: -</div>

                <div class="preview-meta">
                    <div class="preview-meta-item">
                        <span class="meta-icon">🎖️</span>
                        <strong id="previewJabatan">Tenaga Pengajar</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">🎓</span>
                        <strong id="previewPendidikan">-</strong>
                    </div>
                    <div class="preview-meta-item">
                        <span class="meta-icon">🏫</span>
                        <strong id="previewProdi">-</strong>
                    </div>
                    <div class="preview-meta-item" id="previewKeahlianItem" style="display: none;">
                        <span class="meta-icon">🔬</span>
                        <strong id="previewKeahlian">-</strong>
                    </div>
                </div>

                <div class="preview-status-badge aktif" id="previewStatus">✅ Aktif</div>
            </div>
        </div>

        <!-- Academic Profile Preview -->
        <div class="academic-profile-preview">
            <h4>📊 Profil Akademik</h4>
            <div class="academic-preview-stats">
                <div class="academic-preview-stat">
                    <div class="academic-preview-value" id="previewLevel">-</div>
                    <div class="academic-preview-label">Level</div>
                </div>
                <div class="academic-preview-stat">
                    <div class="academic-preview-value" id="previewDegree">-</div>
                    <div class="academic-preview-label">Degree</div>
                </div>
                <div class="academic-preview-stat">
                    <div class="academic-preview-value" id="previewPublications">0</div>
                    <div class="academic-preview-label">Publikasi</div>
                </div>
            </div>
        </div>

        <!-- Bio Preview -->
        <div class="preview-bio" id="previewBio">
            <span style="color: var(--text-muted);">Biografi dosen akan muncul di sini...</span>
        </div>

        <!-- Share QR -->
        <div class="share-preview">
            <div class="share-preview-label">🔗 QR Share</div>
            <div class="share-preview-qr">
                <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=FKIP-UNIMOF-Dosen" alt="QR">
            </div>
        </div>
    </div>
</div>

<div class="autosave-indicator" id="autosaveIndicator">
    <span id="autosaveIcon">💾</span>
    <span id="autosaveText">Menyimpan...</span>
</div>

<script>
// ===== DATA =====
const autocompleteData = <?= json_encode($autocomplete_data) ?>;

// ===== TEMPLATES =====
const templates = {
    guru_besar: {
        jabatan_fungsional: 'Guru Besar',
        pendidikan_terakhir: 'S3 (Doktor)',
        status: 'Aktif',
        bio: 'Merupakan Guru Besar di FKIP UNIMOF dengan keahlian di bidangnya. Telah mempublikasikan berbagai penelitian dan aktif membimbing mahasiswa.'
    },
    lektor_kepala: {
        jabatan_fungsional: 'Lektor Kepala',
        pendidikan_terakhir: 'S3 (Doktor)',
        status: 'Aktif',
        bio: 'Dosen senior dengan jabatan Lektor Kepala. Aktif dalam penelitian, pengabdian masyarakat, dan pembinaan mahasiswa.'
    },
    lektor: {
        jabatan_fungsional: 'Lektor',
        pendidikan_terakhir: 'S2 (Magister)',
        status: 'Aktif',
        bio: 'Dosen dengan jabatan Lektor yang berpengalaman dalam pengajaran dan penelitian di bidangnya.'
    },
    asisten_ahli: {
        jabatan_fungsional: 'Asisten Ahli',
        pendidikan_terakhir: 'S2 (Magister)',
        status: 'Aktif',
        bio: 'Dosen muda dengan jabatan Asisten Ahli. Aktif dalam pengajaran dan pengembangan keilmuan.'
    },
    tenaga_pengajar: {
        jabatan_fungsional: 'Tenaga Pengajar',
        pendidikan_terakhir: 'S2 (Magister)',
        status: 'Aktif',
        bio: 'Tenaga pengajar yang berdedikasi dalam mendidik mahasiswa di FKIP UNIMOF.'
    }
};

function applyTemplate(key) {
    const t = templates[key];
    if (!t) return;

    if (t.jabatan_fungsional) document.getElementById('jabatan_fungsional').value = t.jabatan_fungsional;
    if (t.pendidikan_terakhir) document.getElementById('pendidikan_terakhir').value = t.pendidikan_terakhir;
    if (t.status) document.getElementById('status').value = t.status;
    if (t.bio) {
        document.getElementById('bio').value = t.bio;
        document.getElementById('bioCounter').textContent = t.bio.length;
    }

    updatePreview();
    updateProgress();
    triggerAutosave();

    showToast('Template Diterapkan', `Template "${key.replace(/_/g, ' ')}" berhasil diisi`, 'success');
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

setupAutocomplete('pendidikan_terakhir', 'pendidikanAutocomplete');

// ===== NIDN VALIDATOR =====
document.getElementById('nidn').addEventListener('input', function() {
    const nidn = this.value.trim();
    const validator = document.getElementById('nidnValidator');
    const breakdown = document.getElementById('nidnBreakdown');

    if (!nidn) {
        validator.classList.remove('show');
        breakdown.classList.remove('show');
        return;
    }

    const digits = nidn.replace(/[^0-9]/g, '');

    // NIDN format: 14 digits (1 + 2 + 2 + 2 + 1 + 6) atau 10 digit
    if (digits.length < 10) {
        validator.className = 'nidn-validator show warning';
        validator.innerHTML = `⚠️ Terlalu pendek (${digits.length} digit). NIDN biasanya 10-14 digit`;
        breakdown.classList.remove('show');
        return;
    }

    if (digits.length > 14) {
        validator.className = 'nidn-validator show invalid';
        validator.innerHTML = `❌ Terlalu panjang (${digits.length} digit). Max 14 digit`;
        breakdown.classList.remove('show');
        return;
    }

    if (digits.length === 14) {
        // Parse NIDN 14 digit: [wilayah][DDMMYY][JK][urut]
        const wilayah = digits.substring(0, 1);
        const tgl = digits.substring(1, 3) + '/' + digits.substring(3, 5) + '/' + digits.substring(5, 7);
        const tahun = '20' + digits.substring(5, 7);
        const jkDigit = digits.substring(7, 8);
        const jk = jkDigit === '1' ? 'L' : (jkDigit === '2' ? 'P' : '?');

        validator.className = 'nidn-validator show valid';
        validator.innerHTML = `✅ NIDN valid (14 digit)`;

        breakdown.classList.add('show');
        document.getElementById('nidn-wilayah').textContent = wilayah;
        document.getElementById('nidn-tgl').textContent = tgl;
        document.getElementById('nidn-thn').textContent = tahun;
        document.getElementById('nidn-jk').textContent = jk;
    } else {
        validator.className = 'nidn-validator show valid';
        validator.innerHTML = `✅ Format diterima (${digits.length} digit)`;
        breakdown.classList.remove('show');
    }

    triggerAutosave();
});

// ===== LINK VALIDATORS =====
function validateLink(inputId, validatorId, pattern) {
    const input = document.getElementById(inputId);
    const validator = document.getElementById(validatorId);

    input.addEventListener('input', function() {
        const url = this.value.trim();

        if (!url) {
            validator.classList.remove('show');
            return;
        }

        let fullUrl = url;
        if (!/^https?:\/\//.test(url)) fullUrl = 'https://' + url;

        if (pattern.test(fullUrl)) {
            validator.className = 'link-validator show valid';
            validator.innerHTML = '✅ URL valid';
        } else if (url.includes('scholar') || url.includes('scopus')) {
            validator.className = 'link-validator show invalid';
            validator.innerHTML = '⚠️ Format URL tidak sesuai';
        } else {
            validator.className = 'link-validator show invalid';
            validator.innerHTML = '❌ Bukan URL yang diharapkan';
        }

        triggerAutosave();
    });
}

validateLink('google_scholar', 'scholarValidator', /scholar\.google\./i);
validateLink('scopus', 'scopusValidator', /scopus\.com/i);

// ===== SMART BIO BUILDER =====
function addBioPhrase(phrase) {
    const textarea = document.getElementById('bio');
    const current = textarea.value.trim();
    const newBio = current ? current + '\n\n' + phrase : phrase;
    textarea.value = newBio;
    document.getElementById('bioCounter').textContent = newBio.length;
    updatePreview();
    triggerAutosave();
    updateProgress();
    textarea.focus();
    showToast('Frase Ditambahkan', phrase.substring(0, 40) + '...', 'success');
}

// ===== DRAG & DROP UPLOAD =====
const uploadZone = document.getElementById('uploadZone');
const fotoInput = document.getElementById('foto');
const imagePreview = document.getElementById('imagePreview');

['dragenter', 'dragover'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.classList.remove('dragover'); });
});
uploadZone.addEventListener('drop', e => {
    if (e.dataTransfer.files.length > 0) handleImage(e.dataTransfer.files[0]);
});
fotoInput.addEventListener('change', e => {
    if (e.target.files.length > 0) handleImage(e.target.files[0]);
});

function handleImage(file) {
    if (!file.type.startsWith('image/')) {
        showToast('Error', 'Hanya file gambar yang diizinkan!', 'error');
        return;
    }
    if (file.size > 3 * 1024 * 1024) {
        showToast('Error', 'Ukuran file maksimal 3MB!', 'error');
        return;
    }

    const dt = new DataTransfer();
    dt.items.add(file);
    fotoInput.files = dt.files;

    document.getElementById('imageName').textContent = file.name;
    document.getElementById('imageSize').textContent = formatFileSize(file.size);

    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imagePreviewImg').innerHTML = `<img src="${e.target.result}" alt="">`;
        document.getElementById('previewAvatar').innerHTML = `<img src="${e.target.result}" alt="">`;

        const img = new Image();
        img.onload = () => {
            const ratio = img.width / img.height;
            const aspectWarning = document.getElementById('aspectWarning');
            const imageDim = document.getElementById('imageDim');
            imageDim.textContent = `${img.width} × ${img.height} px`;

            if (Math.abs(ratio - 1) > 0.2) {
                aspectWarning.classList.add('show');
            } else {
                aspectWarning.classList.remove('show');
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);

    imagePreview.classList.add('show');
    uploadZone.style.display = 'none';
    updatePreview();
    triggerAutosave();
}

function removeImage() {
    fotoInput.value = '';
    imagePreview.classList.remove('show');
    uploadZone.style.display = 'block';
    document.getElementById('aspectWarning').classList.remove('show');
    updateInitials();
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
function updatePreview() {
    const nama = document.getElementById('nama').value || 'Nama Dosen';
    const nidn = document.getElementById('nidn').value || '-';
    const jabatan = document.getElementById('jabatan_fungsional').value || 'Tenaga Pengajar';
    const pendidikan = document.getElementById('pendidikan_terakhir').value || '-';
    const keahlian = document.getElementById('bidang_keahlian').value || '';
    const penelitian = document.getElementById('penelitian').value;
    const bio = document.getElementById('bio').value;
    const status = document.getElementById('status').value || 'Aktif';

    const prodiSelect = document.getElementById('program_studi_id');
    const prodiText = prodiSelect.options[prodiSelect.selectedIndex]?.text || 'Program Studi';

    // Title & Role
    document.getElementById('previewTitle').textContent = nama;

    // Role (jabatan @ prodi)
    let role = '-';
    if (jabatan && prodiText !== '-- Pilih Prodi --') role = `${jabatan} @ ${prodiText}`;
    else if (jabatan) role = jabatan;
    document.getElementById('previewRole').textContent = role;

    document.getElementById('previewNidn').textContent = 'NIDN: ' + nidn;
    document.getElementById('previewJabatan').textContent = jabatan;
    document.getElementById('previewPendidikan').textContent = pendidikan;
    document.getElementById('previewProdi').textContent = prodiText === '-- Pilih Prodi --' ? 'Program Studi' : prodiText;

    // Keahlian
    const keahlianItem = document.getElementById('previewKeahlianItem');
    if (keahlian) {
        keahlianItem.style.display = 'flex';
        document.getElementById('previewKeahlian').textContent = keahlian;
    } else {
        keahlianItem.style.display = 'none';
    }

    // Initials
    updateInitials();

    // Status
    const statusEl = document.getElementById('previewStatus');
    if (status === 'Aktif') {
        statusEl.className = 'preview-status-badge aktif';
        statusEl.textContent = '✅ Aktif';
    } else {
        statusEl.className = 'preview-status-badge non-aktif';
        statusEl.textContent = '📴 Non-Aktif';
    }

    // Academic profile
    const levelMap = {
        'Guru Besar': '⭐⭐⭐',
        'Lektor Kepala': '⭐⭐',
        'Lektor': '⭐⭐',
        'Asisten Ahli': '⭐',
        'Tenaga Pengajar': '•'
    };
    document.getElementById('previewLevel').textContent = levelMap[jabatan] || '•';

    // Extract degree from pendidikan
    let degree = '-';
    if (pendidikan) {
        const match = pendidikan.match(/(S1|S2|S3|D4|Doktor|Magister|Sarjana|Bachelor)/i);
        if (match) {
            degree = match[1].toUpperCase();
            if (degree === 'DOKTOR') degree = 'S3';
            else if (degree === 'MAGISTER') degree = 'S2';
            else if (degree === 'SARJANA' || degree === 'BACHELOR') degree = 'S1';
        } else if (pendidikan.length <= 5) {
            degree = pendidikan.toUpperCase();
        }
    }
    document.getElementById('previewDegree').textContent = degree;

    // Count publications (lines in penelitian)
    const publications = penelitian ? penelitian.split('\n').filter(p => p.trim()).length : 0;
    document.getElementById('previewPublications').textContent = publications;

    // Bio
    const bioEl = document.getElementById('previewBio');
    if (bio) {
        bioEl.textContent = bio;
        bioEl.style.color = 'var(--text-secondary)';
        bioEl.style.fontStyle = 'italic';
    } else {
        bioEl.innerHTML = '<span style="color: var(--text-muted);">Biografi dosen akan muncul di sini...</span>';
    }

    // QR Code
    const qrData = `DOSEN:${nama}|${jabatan}|${prodiText}|NIDN:${nidn}`;
    document.getElementById('qrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;
}

function updateInitials() {
    const nama = document.getElementById('nama').value || '';
    const avatar = document.getElementById('previewAvatar');
    if (avatar.querySelector('img')) return;

    const initials = nama.split(' ')
        .filter(n => n.trim())
        .slice(0, 2)
        .map(n => n[0])
        .join('')
        .toUpperCase();

    document.getElementById('previewInitials').textContent = initials || '👤';
}

// ===== CHARACTER & WORD COUNTERS =====
['nama', 'bio', 'riwayat_pendidikan', 'penelitian'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', function() {
        const counter = document.getElementById(id + 'Counter');
        if (counter) counter.textContent = this.value.length;

        if (id === 'bio') {
            const words = this.value.trim().split(/\s+/).filter(w => w).length;
            document.getElementById('bioWords').textContent = words;
        }

        triggerAutosave();
        updateProgress();
    });
});

// ===== PROGRESS INDICATOR =====
function updateProgress() {
    const fields = [
        { el: 'nama', weight: 20 },
        { el: 'jabatan_fungsional', weight: 15 },
        { el: 'pendidikan_terakhir', weight: 15 },
        { el: 'program_studi_id', weight: 10 },
        { el: 'nidn', weight: 10 },
        { el: 'email', weight: 8 },
        { el: 'bidang_keahlian', weight: 7 },
        { el: 'bio', weight: 10 },
        { check: () => document.getElementById('previewAvatar').querySelector('img'), weight: 5 }
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

    const percent = Math.round((score / total) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').textContent = percent + '%';
}

// ===== AUTOSAVE =====
const storageKey = 'fkip_dosen_draft_<?= $id ?: "new" ?>';
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
            nidn: document.getElementById('nidn').value,
            jabatan_fungsional: document.getElementById('jabatan_fungsional').value,
            pendidikan_terakhir: document.getElementById('pendidikan_terakhir').value,
            program_studi_id: document.getElementById('program_studi_id').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            google_scholar: document.getElementById('google_scholar').value,
            scopus: document.getElementById('scopus').value,
            bidang_keahlian: document.getElementById('bidang_keahlian').value,
            riwayat_pendidikan: document.getElementById('riwayat_pendidikan').value,
            penelitian: document.getElementById('penelitian').value,
            bio: document.getElementById('bio').value,
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
                document.getElementById('nidn').value = data.nidn || '';
                document.getElementById('jabatan_fungsional').value = data.jabatan_fungsional || 'Tenaga Pengajar';
                document.getElementById('pendidikan_terakhir').value = data.pendidikan_terakhir || '';
                document.getElementById('program_studi_id').value = data.program_studi_id || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('phone').value = data.phone || '';
                document.getElementById('google_scholar').value = data.google_scholar || '';
                document.getElementById('scopus').value = data.scopus || '';
                document.getElementById('bidang_keahlian').value = data.bidang_keahlian || '';
                document.getElementById('riwayat_pendidikan').value = data.riwayat_pendidikan || '';
                document.getElementById('penelitian').value = data.penelitian || '';
                document.getElementById('bio').value = data.bio || '';
                document.getElementById('status').value = data.status || 'Aktif';

                ['nama', 'bio', 'riwayat_pendidikan', 'penelitian'].forEach(id => {
                    const el = document.getElementById(id);
                    const counter = document.getElementById(id + 'Counter');
                    if (el && counter) counter.textContent = (data[id] || '').length;
                });

                const bioWords = (data.bio || '').trim().split(/\s+/).filter(w => w).length;
                document.getElementById('bioWords').textContent = bioWords;

                updatePreview();
                updateProgress();

                // Trigger validators
                ['nidn', 'google_scholar', 'scopus'].forEach(id => {
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
['nama', 'nidn', 'jabatan_fungsional', 'pendidikan_terakhir', 'program_studi_id', 'email', 'phone', 'google_scholar', 'scopus', 'bidang_keahlian', 'riwayat_pendidikan', 'penelitian', 'bio', 'status'].forEach(id => {
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

// ===== FORM SUBMIT (FIXED - NO DISABLE!) =====
document.getElementById('dosenForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const jabatan = document.getElementById('jabatan_fungsional').value;

    if (!nama) {
        e.preventDefault();
        document.getElementById('nama').classList.add('error');
        showToast('Validasi Error', 'Nama dosen wajib diisi!', 'error');
        return;
    }

    if (!jabatan) {
        e.preventDefault();
        document.getElementById('jabatan_fungsional').classList.add('error');
        showToast('Validasi Error', 'Jabatan fungsional wajib dipilih!', 'error');
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
    const nama = document.getElementById('nama').value || 'Dosen';
    const jabatan = document.getElementById('jabatan_fungsional').value || '-';
    const prodi = document.getElementById('program_studi_id').options[document.getElementById('program_studi_id').selectedIndex]?.text || '-';
    const pendidikan = document.getElementById('pendidikan_terakhir').value || '-';
    const nidn = document.getElementById('nidn').value || '-';

    const text = `👨‍🏫 ${nama}\n🎖️ ${jabatan}\n🏫 ${prodi}\n🎓 ${pendidikan}\n🆔 NIDN: ${nidn}\n\nDosen FKIP UNIMOF`;

    if (navigator.share) {
        navigator.share({ title: nama, text: text });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        showToast('Disalin', 'Profil dosen disalin ke clipboard', 'success');
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('dosenForm').dispatchEvent(new Event('submit'));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('.template-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Template', 'Pilih template di atas', 'info');
    }
    if (e.key === 'Escape') {
        if (confirm('Batalkan perubahan dan kembali?')) {
            window.location.href = 'dosen.php';
        }
    }
});

// ===== INITIAL =====
updatePreview();
updateProgress();
<?php if (!empty($edit['nidn'])): ?>
document.getElementById('nidn').dispatchEvent(new Event('input'));
<?php endif; ?>
<?php if (!empty($edit['google_scholar'])): ?>
document.getElementById('google_scholar').dispatchEvent(new Event('input'));
<?php endif; ?>
<?php if (!empty($edit['scopus'])): ?>
document.getElementById('scopus').dispatchEvent(new Event('input'));
<?php endif; ?>

console.log('%c👨‍🏫 Form Dosen FKIP UNIMOF - Super Extreme', 'color: #1e40af; font-size: 16px; font-weight: bold;');
console.log('%cShortcuts: Ctrl+S (Simpan), Ctrl+K (Template), ESC (Batal)', 'color: #64748b;');
console.log('%cFitur: NIDN Parser, Smart Bio Builder, Google Scholar/Scopus Validator, Academic Profile Preview', 'color: #64748b;');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>